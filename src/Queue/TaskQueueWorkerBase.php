<?php

namespace Drupal\task_ui\Queue;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\intranet_core\Logger\IntranetLoggerTrait;
use Drupal\task_ui\Entity\Storage\TaskStorageInterface;
use Drupal\task_ui\Event\TaskCompletedEvent;
use Drupal\task_ui\Exception\TaskException;
use Drupal\task_ui\Log\LoggerTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Base class for TaskUI workers.
 *
 * @package Drupal\task_ui\Queue
 */
abstract class TaskQueueWorkerBase extends QueueWorkerBase implements TaskQueueWorkerInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;
  use LoggerTrait;

  /**
   * The task storage handler.
   *
   * @var \Drupal\task_ui\Entity\Storage\TaskStorageInterface
   */
  protected $taskStorage;

  /**
   * Retrieve the task storage handler.
   *
   * @return \Drupal\task_ui\Entity\Storage\TaskStorageInterface
   *   A storage handler for task entities.
   */
  protected function taskStorage() {
    return $this->taskStorage;
  }

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $dispatcher;

  /**
   * Retrieve the event dispatcher.
   *
   * @return \Symfony\Component\EventDispatcher\EventDispatcherInterface
   *   An event dispatcher.
   */
  protected function eventDispatcher() {
    return $this->dispatcher;
  }

  /**
   * Create a new TaskQueueWorker instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\task_ui\Entity\Storage\TaskStorageInterface $task_storage
   *   A storage handler for task entities.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
   *   An event dispatcher.
   * @param \Drupal\Core\Logger\LoggerChannelInterface|null $logger
   *   (optional) A logger channel.
   */
  public function __construct(array $configuration, string $plugin_id, $plugin_definition, TaskStorageInterface $task_storage, EventDispatcherInterface $dispatcher, LoggerChannelInterface $logger = NULL) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->taskStorage = $task_storage;
    $this->dispatcher = $dispatcher;
    $this->logger = $logger;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $required = static::dependencies($container);
    $optional = static::optionalDependencies(($container));
    return new static($configuration, $plugin_id, $plugin_definition, ...$required, ...$optional);
  }

  /**
   * Retrieve required dependencies this plugin should be initialized with.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The Drupal service container.
   *
   * @return array
   *   An array of dependencies.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected static function dependencies(ContainerInterface $container) {
    return [
      $container->get('entity_type.manager')
        ->getStorage('task'),
      $container->get('event_dispatcher'),
    ];
  }

  /**
   * Retrieve optional dependencies this plugin should be initialized with.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The Drupal service container.
   *
   * @return array
   *   An array of dependencies.
   */
  protected static function optionalDependencies(ContainerInterface $container) {
    return [
      $container->get('logger.channel.task') ?: NULL,
    ];
  }

  /**
   * {@inheritDoc}
   */
  abstract public function run(QueueItem $item);

  /**
   * {@inheritDoc}
   */
  public function processItem($item) {
    /** @var \Drupal\task_ui\Entity\Task $task */
    $task = $this->taskStorage()->load($item->taskId);
    $started_at = new DrupalDateTime();
    try {
      $this->notice(sprintf('Task %s started execution.', $task->id()));
      $task->setHasStarted();
      if (!$result = $this->run($item)) {
        $result = new Result([]);
      }
      $task->setHasStopped(TRUE, $started_at->getTimestamp());
      if ($result->successful()) {
        $this->notice(sprintf('Task %s finished successfully.', $task->id()));
      }
      else {
        $this->warning('Task %s finished with errors.', $task->id());
        foreach ($result->errors() as $error) {
          $this->error($error);
        }
      }
    }
    catch (TaskException $te) {
      $task->setHasStopped(FALSE, $started_at->getTimestamp());
      if (!$result = $te->getResult()) {
        $result = new Result([$te->getMessage()]);
      }
    }
    catch (\Exception $e) {
      $task->setHasStopped(FALSE, $started_at->getTimestamp());
      $result = new Result([$e->getMessage()]);
      $message = $e->getMessage() . "\n\n" . $e->getTraceAsString();
      $this->notice(sprintf('Task %s finished with an error: %s', $task->id(), $message));
    }

    $this->eventDispatcher()
      ->dispatch(TaskCompletedEvent::EVENT_NAME, new TaskCompletedEvent($task, $result));
    return $result;
  }

  /**
   * {@inheritDoc}
   */
  public function buildSubForm(array $params) {
    return [
      '#type' => 'container',
    ];
  }

}
