<?php

namespace Drupal\task_ui\Queue;

/**
 * Interface for task execution results.
 *
 * @package Drupal\task_ui\Queue
 */
interface ResultInterface {

  /**
   * Whether the task execution was successful.
   *
   * @return bool
   *   TRUE if the task execution is considered successful.
   *   FALSE otherwise.
   */
  public function successful();

  /**
   * Errors that occurred during task execution.
   *
   * @return array
   *   Array of error descriptions.
   */
  public function errors();

}
