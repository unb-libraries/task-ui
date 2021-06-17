<?php

namespace Drupal\task_ui\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Queue\QueueInterface;
use Drupal\task_ui\Queue\QueueItem;
use Drupal\task_ui\Queue\TaskQueueWorkerInterface;
use Drupal\task_ui\Queue\TaskState;

/**
 * Defines the Task entity.
 *
 * @ConfigEntityType(
 *   id = "task",
 *   label = @Translation("Task"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\task_ui\TaskListBuilder",
 *     "form" = {
 *       "add" = "Drupal\task_ui\Form\TaskForm",
 *       "edit" = "Drupal\task_ui\Form\TaskForm",
 *       "delete" = "Drupal\task_ui\Form\TaskDeleteForm"
 *     },
 *     "storage" = "Drupal\task_ui\Entity\Storage\TaskStorage",
 *   },
 *   config_prefix = "task",
 *   admin_permission = "administer tasks",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id",
 *     "name",
 *     "worker_id",
 *     "params",
 *     "interval",
 *     "first_exec",
 *   },
 *   links = {
 *     "canonical" = "/admin/config/system/tasks/{task}",
 *     "add-form" = "/admin/config/system/tasks/create",
 *     "edit-form" = "/admin/config/system/tasks/{task}/edit",
 *     "delete-form" = "/admin/config/system/tasks/{task}/delete",
 *     "run" = "/admin/config/system/tasks/{task}/run",
 *     "enable" = "/admin/config/system/tasks/{task}/enable",
 *     "disable" = "/admin/config/system/tasks/{task}/disable",
 *     "collection" = "/admin/config/system/tasks"
 *   }
 * )
 */
class Task extends ConfigEntityBase {

  const CRON_INTERVAL = 60;

  /**
   * Unique identifier.
   *
   * @var int
   */
  protected $id;

  /**
   * Human readable, short description of the task.
   *
   * @var string
   */
  protected $name;

  /**
   * The queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * Worker plugin instance.
   *
   * @var \Drupal\Core\Queue\QueueWorkerInterface
   */
  protected $worker;

  /**
   * Optional parameters to pass to the worker, as defined by the worker itself.
   *
   * @var array
   */
  protected $params;

  /**
   * Interval at which this task shall execute, in seconds.
   *
   * @var int
   */
  protected $interval;

  /**
   * Unix timestamp indicating when the task shall first be executed.
   *
   * @var int
   */
  protected $first_exec;

  /**
   * Whether the task should be disabled on creation.
   *
   * @var bool
   */
  protected $enabled;

  /**
   * The tasks current state.
   *
   * @var \Drupal\task_ui\Queue\TaskState
   */
  protected $state;

  /**
   * {@inheritDoc}
   */
  public function __construct(array $values, $entity_type, QueueInterface $queue = NULL, TaskQueueWorkerInterface $worker = NULL) {
    parent::__construct($values, $entity_type);
    $this->state = $this->getState();
    $this->queue = $queue;
    $this->worker = $worker;
  }

  /**
   * Whether the task is currently enabled or not. This is an alias for @see Task::isEnabled().
   *
   * @return bool
   *   True if the task is enabled. False otherwise.
   */
  public function status() {
    return $this->isEnabled();
  }

  /**
   * Whether the task is currently enabled or not.
   *
   * @return bool
   *   True if the task is enabled. False otherwise.
   */
  public function isEnabled() {
    return $this->getState()->getStatus() !== TaskState::STATE_DISABLED;
  }

  /**
   * Enable the task.
   */
  public function enable() {
    $this->getState()->setStatus(TaskState::STATE_IDLE);
  }

  /**
   * Disable the task.
   */
  public function disable() {
    $this->getState()->setStatus(TaskState::STATE_DISABLED);
  }

  /**
   * Remove the task. This includes removing any information about its state from StateAPI.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function delete() {
    $this->getState()->delete();
    parent::delete();
  }

  /**
   * Retrieve the task's state from StateAPI.
   *
   * @return \Drupal\task_ui\Queue\TaskState
   *   The task's state.
   */
  public function getState() {
    if (!isset($this->state)) {
      $this->state = TaskState::import($this->id);
    }
    return $this->state;
  }

  /**
   * Set the task to 'currently executing'.
   */
  public function setHasStarted() {
    $this->getState()->setStatus(TaskState::STATE_RUNNING);
  }

  /**
   * Set the task to 'has stopped'.
   *
   * @param bool $success
   *   Whether the task was stopped after successful execution, or after an error occurred.
   * @param int $timestamp
   *   Unix timestamp indicating when the task finished executing.
   */
  public function setHasStopped($success, $timestamp) {
    if ($success) {
      $this->getState()->setLastSuccessfulExecution($timestamp);
    }
    $this->getState()->setLastExecution($timestamp);
    $this->getState()->setStatus(TaskState::STATE_IDLE);
  }

  /**
   * Whether this task is a one-time or a recurring task.
   *
   * @return bool
   *   True if this task runs periodically. False otherwise.
   */
  public function isRecurring() {
    return $this->interval > 0;
  }

  /**
   * Retrieve the human readable, short description of the task.
   *
   * @return string
   *   String describing what the task does.
   */
  public function getName() {
    return $this->name;
  }

  /**
   * Time interval at which the task runs.
   *
   * @return int
   *   A positive integer indicates the interval (in seconds), at which the task repeats execution.
   *   A negative integer or 0 indicates, that the task does not run periodically.
   */
  public function getInterval() {
    return $this->interval;
  }

  /**
   * DateTime indicating the first (and possibly only) execution of the task.
   *
   * @return \Drupal\Core\Datetime\DrupalDateTime
   *   An Instance of DrupalDateTime object.
   */
  public function firstExecutes() {
    return DrupalDateTime::createFromTimestamp(intval($this->first_exec));
  }

  /**
   * Determine whether is shall execute at the time this method is called.
   *
   * @return bool
   *   True if the task should execute now, i.e.
   *   -  if the time of its first scheduled execution has come, or
   *   -  if the number of seconds, as indicated by the set interval, since its last execution has passed.
   *   False if
   *   - the time of its first scheduled execution has come, or
   *   - the task does not run periodically.
   */
  public function isDue() {
    if ($this->isEnabled() && !$this->isQueueing()) {
      $now = (new DrupalDateTime())->getTimestamp();
      if (!$this->hasRun()) {
        return $now - $this->firstExecutes()->getTimestamp() > 0;
      }
      if ($this->isRecurring()) {
        if (!$this->lastExecutionSucceeded()) {
          return TRUE;
        }
        $first_execution = $this->firstExecutes()->getTimestamp();
        $intervals_since_first_execution = \floor(($now - $first_execution) / $this->interval);
        $start_of_current_interval = $first_execution + $intervals_since_first_execution * $this->interval;
        return !($this->hasRunSince($start_of_current_interval));
      }
    }
    return FALSE;
  }

  /**
   * Determine the time when the task shall execute next.
   *
   * @return int|false
   *   If the task is currently enabled, in idle state and recurring, a UNIX timestamp
   *   will be returned to indicate either
   *   - the next time cron runs, if the task's last execution did not finish successfully, or
   *   - the next time this task shall execute again according to its defined time of
   *     initial execution and interval.
   *   If the task is currently not currently enabled or not idle, false will be returned.
   */
  public function isDueNext() {
    if (!$this->isEnabled() || $this->isQueueing() || (!$this->isRecurring() && $this->hasRun())) {
      return FALSE;
    }

    $now = (new DrupalDateTime())->getTimestamp();
    $first_execution = $this->firstExecutes()->getTimestamp();

    if (!$this->hasRun() && $first_execution > $now) {
      $next = $this->firstExecutes()->getTimestamp();
    }
    else {
      $intervals_since_first_execution = \floor(($now - $first_execution) / $this->interval);
      $start_of_current_interval = $first_execution + $intervals_since_first_execution * $this->interval;

      if ($this->hasRunSince($start_of_current_interval) && $this->lastExecutionSucceeded()) {
        $next = $start_of_current_interval + $this->interval;
      }
      else {
        $cron_intervals_since_start_of_task_interval = \ceil(($now - $start_of_current_interval) / self::CRON_INTERVAL);
        $next = $start_of_current_interval + $cron_intervals_since_start_of_task_interval * self::CRON_INTERVAL;
      }
    }

    return $next;
  }

  /**
   * Whether the task has executed at least once.
   *
   * @return bool
   *   True if the task's current state indicates that it has executed before (successful or not). False otherwise.
   */
  public function hasRun() {
    return $this->getState()->getLastExecution() !== TaskState::HAS_NEVER_EXECUTED;
  }

  /**
   * Whether the task has executed since the given timestamp.
   *
   * @param int $timestamp
   *   UNIX timestamp to act as point of reference.
   *
   * @return bool
   *   True if the task's most recent execution (successful or not) took place after the timestamp. False otherwise.
   */
  public function hasRunSince($timestamp) {
    return $this->getState()->getLastExecution() >= $timestamp;
  }

  /**
   * Whether the last execution was successful.
   *
   * @return bool
   *   True if the last execution finished successfully. False otherwise.
   */
  public function lastExecutionSucceeded() {
    if ($this->hasRun()) {
      return $this->getState()->getLastSuccessfulExecution() === $this->getState()->getLastExecution();
    }
    return FALSE;
  }

  /**
   * Whether the task is currently waiting to be executed.
   *
   * @return bool
   *   True if the task is currently not executing and is either
   *   - waiting to execute for the first time, or
   *   - waiting to execute again.
   *   False if currently executing or idle.
   */
  public function isQueueing() {
    return $this->getState()->getStatus() === TaskState::STATE_QUEUED;
  }

  /**
   * Whether the task is currently executing.
   *
   * @return bool
   *   True if the task is currently executing. False otherwise.
   */
  public function isRunning() {
    return $this->getState()->getStatus() === TaskState::STATE_RUNNING;
  }

  /**
   * Queue the task for execution. An already queued task will not be queued again.
   */
  public function queue() {
    if (!$this->isQueueing()) {
      if ($this->getQueue()->createItem($this->toQueueableItem())) {
        $this->getState()->setStatus(TaskState::STATE_QUEUED);
      }
    }
  }

  /**
   * Get the queue.
   *
   * @return \Drupal\Core\Queue\QueueInterface
   *   A queue.
   */
  protected function getQueue() {
    return $this->queue;
  }

  /**
   * Convert this task to an item that can be handled by Drupal's cron queue.
   *
   * @return \Drupal\task_ui\Queue\QueueItem
   *   An instance of QueueItem to be handled by Drupal's cron queue.
   */
  public function toQueueableItem() {
    return new QueueItem($this->id, $this->getParams());
  }

  /**
   * Retrieve the queue worker instance which executes this task.
   *
   * @return \Drupal\Core\Queue\QueueWorkerInterface
   *   A queue worker plugin.
   */
  public function getWorker() {
    return $this->worker;
  }

  /**
   * Retrieve any parameters that may affect the task's execution.
   *
   * @return array
   *   See the task's worker's implementation for details.
   */
  public function getParams() {
    if (!isset($this->params)) {
      $this->params = [];
    }
    return $this->params;
  }

}
