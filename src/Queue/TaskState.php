<?php

namespace Drupal\task_ui\Queue;

/**
 * Makes StateAPI information about a certain task accessible in an object.
 *
 * @package Drupal\task_ui\Queue
 */
class TaskState {

  /**
   * Indicates that a task is disabled and cannot be executed.
   */
  const STATE_DISABLED = 'task_ui.state.disabled';

  /**
   * Indicates that a task is enabled, but currently not active.
   */
  const STATE_IDLE = 'task_ui.state.idle';

  /**
   * Indicates that a task is enabled and currently waiting to be executed.
   */
  const STATE_QUEUED = 'task_ui.state.queued';

  /**
   * Indicates that a task is enabled and currently executing.
   */
  const STATE_RUNNING = 'task_ui.state.running';

  /**
   * Indicates that a task has never been executed.
   */
  const HAS_NEVER_EXECUTED = 0;

  /**
   * StateAPI identifier.
   *
   * @var string
   */
  protected $id;

  /**
   * Current status. Accepts one of STATE_<DISABLED|IDLE|QUEUED|RUNNING> values.
   *
   * @var string
   */
  protected $status;

  /**
   * UNIX timestamp indicating the last time a task was executed.
   *
   * @var int
   */
  protected $lastExecution;

  /**
   * UNIX timestamp indicating the last time a task was successfully executed.
   *
   * @var int
   */
  protected $lastSuccessfulExecution;

  /**
   * Retrieve the ID.
   *
   * @return string
   *   String of the form 'task_ui.task.<TASK_ID>'.
   */
  public function id() {
    return $this->id;
  }

  /**
   * Retrieve the current state.
   *
   * @return string
   *   String indicating the state of the associated task.
   *   For possible return values @see TaskState::setStatus()
   */
  public function getStatus() {
    return $this->status;
  }

  /**
   * Set the current state.
   *
   * @param string $status
   *   One of the following values:
   *   - @see TaskState::STATE_DISABLED
   *   - @see TaskState::STATE_IDLE
   *   - @see TaskState::STATE_QUEUED
   *   - @see TaskState::STATE_RUNNING.
   */
  public function setStatus($status) {
    $allowed_states = [
      self::STATE_DISABLED,
      self::STATE_IDLE,
      self::STATE_QUEUED,
      self::STATE_RUNNING,
    ];
    if (in_array($status, $allowed_states)) {
      $this->status = $status;
      $this->save();
    }
  }

  /**
   * Retrieve the time the task was last executed.
   *
   * @return int
   *   UNIX timestamp.
   */
  public function getLastExecution() {
    return $this->lastExecution;
  }

  /**
   * Set the time the task was last executed.
   *
   * @param int $timestamp
   *   UNIX timestamp.
   */
  public function setLastExecution($timestamp) {
    $this->lastExecution = $timestamp;
    $this->save();
  }

  /**
   * Retrieve the time the task was last successfully executed.
   *
   * @return int
   *   UNIX timestamp.
   */
  public function getLastSuccessfulExecution() {
    return $this->lastSuccessfulExecution;
  }

  /**
   * Set the time the task was last successfully executed.
   *
   * @param int $timestamp
   *   UNIX timestamp.
   */
  public function setLastSuccessfulExecution($timestamp) {
    $this->lastSuccessfulExecution = $timestamp;
    $this->save();
  }

  /**
   * Create a new TaskState instance.
   *
   * @param string $id
   *   ID of the associated task.
   * @param string $status
   *   State of the associated task.
   * @param int $last_execution
   *   UNIX timestamp indicating the last time the associated task was executed.
   * @param int $last_successful_execution
   *   UNIX timestamp indicating the last time the associated task was successfully executed.
   */
  public function __construct($id, $status = self::STATE_DISABLED, $last_execution = self::HAS_NEVER_EXECUTED, $last_successful_execution = self::HAS_NEVER_EXECUTED) {
    $this->id = 'task_ui.task.' . $id;
    $this->status = $status;
    $this->lastExecution = $last_execution;
    $this->lastSuccessfulExecution = $last_successful_execution;
    $this->save();
  }

  /**
   * Recover a TaskState instance from StateAPI for the given task ID.
   *
   * @param string $id
   *   The ID of the associated task.
   *
   * @return TaskState
   *   An TaskState instance.
   */
  public static function import($id) {
    $state_properties = \Drupal::state()->get('task_ui.task.' . $id);
    if (!isset($state_properties)) {
      return new static($id);
    }
    return new static($id, $state_properties['status'], $state_properties['lastExec'], $state_properties['lastSuccess']);
  }

  /**
   * Save the current state to StateAPI.
   */
  public function save() {
    \Drupal::state()->set($this->id, $this->toArray());
  }

  /**
   * Remove the state from StateAPI.
   */
  public function delete() {
    \Drupal::state()->delete($this->id);
  }

  /**
   * Convert the state to an array.
   *
   * @return array
   *   An array containing the following keys:
   *   - status: (string) @see TaskState::getStatus() foe poosible values
   *   - lastExec: (int) @see TaskState::getLastExecution()
   *   - lastSuccess: (int) @see TaskState::getLastSuccessfulExecution()
   */
  protected function toArray() {
    return [
      'status' => $this->status,
      'lastExec' => $this->lastExecution,
      'lastSuccess' => $this->lastSuccessfulExecution,
    ];
  }

}
