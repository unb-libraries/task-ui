<?php

namespace Drupal\task_ui\Entity\Storage;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Entity\ConfigEntityStorage;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Storage handler for task entities.
 *
 * @package Drupal\task_ui\Entity\Storage
 */
class TaskStorage extends ConfigEntityStorage implements TaskStorageInterface {

  /**
   * The queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * The queue worker plugin manager.
   *
   * @var \Drupal\Core\Queue\QueueWorkerManagerInterface
   */
  protected $queueWorkerManager;

  /**
   * Get the queue factory.
   *
   * @return \Drupal\Core\Queue\QueueFactory
   *   A queue factory object.
   */
  protected function queueFactory() {
    return $this->queueFactory;
  }

  /**
   * Get the queue worker plugin manager.
   *
   * @return \Drupal\Core\Queue\QueueWorkerManagerInterface
   *   A plugin manager instance.
   */
  protected function queueWorkerManager() {
    return $this->queueWorkerManager;
  }

  /**
   * TaskStorage constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid_service
   *   The UUID service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory.
   * @param \Drupal\Core\Queue\QueueWorkerManagerInterface $queue_worker_manager
   *   The queue worker plugin manager.
   * @param \Drupal\Core\Cache\MemoryCache\MemoryCacheInterface|null $memory_cache
   *   The memory cache backend.
   */
  public function __construct(EntityTypeInterface $entity_type, ConfigFactoryInterface $config_factory, UuidInterface $uuid_service, LanguageManagerInterface $language_manager, QueueFactory $queue_factory, QueueWorkerManagerInterface $queue_worker_manager, MemoryCacheInterface $memory_cache = NULL) {
    parent::__construct($entity_type, $config_factory, $uuid_service, $language_manager, $memory_cache);
    $this->queueFactory = $queue_factory;
    $this->queueWorkerManager = $queue_worker_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('config.factory'),
      $container->get('uuid'),
      $container->get('language_manager'),
      $container->get('queue'),
      $container->get('plugin.manager.queue_worker'),
      $container->get('entity.memory_cache')
    );
  }

  /**
   * {@inheritDoc}
   */
  protected function mapFromStorageRecords(array $records) {
    $entities = [];
    foreach ($records as $record) {
      $entity = $this->doCreate($record);
      $entities[$entity->id()] = $entity;
    }
    return $entities;
  }

  /**
   * {@inheritDoc}
   */
  protected function doCreate(array $values) {
    $worker = $this->createWorker($values['worker_id']);
    $queue = $this->getQueue($worker->getPluginId());
    return new $this->entityClass($values, $this->entityTypeId, $queue, $worker);
  }

  /**
   * Get a queue from the queue factory.
   *
   * @param string $name
   *   The name of the queue.
   *
   * @return \Drupal\Core\Queue\QueueInterface
   *   A queue.
   */
  protected function getQueue($name = 'task_ui') {
    return $this->queueFactory()->get($name);
  }

  /**
   * Create a worker plugin instance.
   *
   * @param string $worker_id
   *   The plugin ID.
   * @param array $configuration
   *   (optional) The plugin configuration.
   *
   * @return \Drupal\task_ui\Queue\TaskQueueWorkerInterface
   *   A task queue worker plugin.
   */
  protected function createWorker(string $worker_id, array $configuration = []) {
    try {
      /** @var \Drupal\task_ui\Queue\TaskQueueWorkerInterface $worker */
      $worker = $this->queueWorkerManager()
        ->createInstance($worker_id, $configuration);
      return $worker;
    }
    catch (PluginException $e) {
      // @todo Some kind of error handling.
      return NULL;
    }

  }

}
