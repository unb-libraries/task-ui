<?php

namespace Drupal\task_ui\Queue;

use Drupal\Core\Queue\QueueWorkerInterface;

/**
 * Interface for task queue workers.
 *
 * @package Drupal\task_ui\Queue
 */
interface TaskQueueWorkerInterface extends QueueWorkerInterface {

  /**
   * Execute the task.
   *
   * @param QueueItem $item
   *   The item to describe the task to complete.
   *
   * @return \Drupal\task_ui\Queue\ResultInterface|void
   *   (optional) An object containing details about the execution.
   *
   * @throws \Drupal\task_ui\Exception\TaskException
   */
  public function run(QueueItem $item);

  /**
   * Sub form to configure worker specific options.
   *
   * @param array $params
   *   Array of parameters that may be needed for form creation.
   *
   * @return array
   *   A Drupal render array describing the form elements.
   */
  public function buildSubForm(array $params);

}
