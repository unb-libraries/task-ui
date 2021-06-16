<?php

namespace Drupal\task_ui\Queue;

/**
 * Minimal implementation of capturing a task execution result.
 *
 * @package Drupal\task_ui\Queue
 */
class Result implements ResultInterface {

  /**
   * Execution errors.
   *
   * @var array
   */
  protected $errors;

  /**
   * Create a new result object.
   *
   * @param array $errors
   *   Array of error descriptions.
   */
  public function __construct(array $errors = []) {
    $this->errors = $errors;
  }

  /**
   * {@inheritDoc}
   */
  public function successful() {
    if (empty($this->errors())) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function errors() {
    return $this->errors;
  }

  /**
   * Add an error.
   *
   * @param string $error
   *   A string.
   */
  public function addError($error) {
    $this->errors[] = $error;
  }

}
