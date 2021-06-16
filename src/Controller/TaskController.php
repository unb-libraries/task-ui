<?php

namespace Drupal\task_ui\Controller;

use Drupal\Core\Entity\Controller\EntityController;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\task_ui\Entity\Task;
use Drupal\task_ui\Event\TaskCompletedEvent;
use Drupal\task_ui\Exception\TaskException;
use Drupal\task_ui\Queue\Result;

/**
 * Class TaskController.
 *
 * @package Drupal\task_ui\Controller
 */
class TaskController extends EntityController {

  use MessengerTrait;

  /**
   * Execute the given task.
   *
   * @param \Drupal\task_ui\Entity\Task $task
   *   The task.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   An HTTP response object.
   */
  public function run(Task $task) {
    $status = $task->getState()->getStatus();

    $item = $task->toQueueableItem();
    $result = $task->getWorker()->processItem($item);
    if ($result->successful()) {
      $this->messenger()->addStatus($this->t('Task @task finished successfully.', [
        '@task' => $task->label(),
      ]));
    }
    else {
      $this->messenger()->addWarning($this->t('Task @task finished with errors.', [
        '@task' => $task->label()
      ]));
      foreach ($result->errors() as $error) {
        $this->messenger()->addError($error);
      }
    }
    $task->getState()->setStatus($status);

    return $this->redirect('entity.task.collection');
  }

  /**
   * Enable the given task.
   *
   * @param \Drupal\task_ui\Entity\Task $task
   *   The task.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   An HTTP response.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function enable(Task $task) {
    if ($task->isEnabled()) {
      \Drupal::messenger()->addStatus(sprintf('Task %s is already enabled.', $task->label()));
    }
    else {
      $task->enable();
      $task->save();
      \Drupal::messenger()->addStatus(sprintf('Task %s has been enabled.', $task->label()));
    }
    return $this->redirect('entity.task.collection');
  }

  /**
   * Disable the given task.
   *
   * @param \Drupal\task_ui\Entity\Task $task
   *   The task.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   An HTTP response object.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function disable(Task $task) {
    if (!$task->isEnabled()) {
      \Drupal::messenger()->addStatus(sprintf('Task %s is already disabled.', $task->label()));
    }
    else {
      $task->disable();
      $task->save();
      \Drupal::messenger()->addStatus(sprintf('Task %s has been disabled.', $task->label()));
    }
    return $this->redirect('entity.task.collection');
  }

}
