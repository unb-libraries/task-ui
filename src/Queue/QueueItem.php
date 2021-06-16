<?php

namespace Drupal\task_ui\Queue;

/**
 * Defines QueueItem class.
 */
final class QueueItem {

  /**
   * A task ID.
   *
   * @var string
   */
  public $taskId;

  /**
   * Params.
   *
   * @var array
   */
  public $params;

  /**
   * Creates a new QueueItem.
   */
  public function __construct($task_id, $params) {
    $this->taskId = $task_id;
    $this->params = $params;
  }

}
