<?php

namespace Drupal\task_ui\Exception;

use Drupal\task_ui\Queue\ResultInterface;

/**
 * Exception to be thrown during task execution.
 *
 * @package Drupal\task_ui\Exception
 */
class TaskException extends \Exception {

  /**
   * Execution result until exception is thrown.
   *
   * @var \Drupal\task_ui\Queue\ResultInterface
   */
  protected $result;

  /**
   * Retrieve the task execution result until the exception is thrown.
   *
   * @return \Drupal\task_ui\Queue\ResultInterface
   *   A result object.
   */
  public function getResult() {
    return $this->result;
  }

  /**
   * Add a result to the exception.
   *
   * Use this to pass any result to an exception handler.
   *
   * @param \Drupal\task_ui\Queue\ResultInterface $result
   *   A result object.
   */
  public function setResult(ResultInterface $result) {
    $this->result = $result;
  }

}
