<?php

namespace Drupal\task_ui\Event;

use Drupal\task_ui\Entity\Task;
use Drupal\task_ui\Queue\ResultInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Event fired when a TaskUI task starts or finishes.
 *
 * @package Drupal\task_ui\Event
 */
class TaskCompletedEvent extends Event {

  const EVENT_NAME = 'task_ui.task';

  /**
   * The task that triggered the event.
   *
   * @var \Drupal\task_ui\Entity\Task
   */
  protected $task;

  /**
   * Result of the task exception.
   *
   * @var \Drupal\task_ui\Queue\ResultInterface
   */
  protected $result;

  /**
   * Return the task which triggered this event.
   *
   * @return \Drupal\task_ui\Entity\Task
   *   A task instance.
   */
  public function getTask() {
    return $this->task;
  }

  /**
   * Retrieve the result of the task execution.
   *
   * @return \Drupal\task_ui\Queue\ResultInterface
   *   A result object.
   */
  public function getResult() {
    return $this->result;
  }

  /**
   * Create a new TaskEvent instance.
   *
   * @param \Drupal\task_ui\Entity\Task $task
   *   The task that triggered the event.
   * @param \Drupal\task_ui\Queue\ResultInterface $result
   *   Object containing details about the execution.
   */
  public function __construct(Task $task, ResultInterface $result) {
    $this->task = $task;
    $this->result = $result;
  }

}
