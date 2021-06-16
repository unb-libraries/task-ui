<?php

namespace Drupal\task_ui;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\task_ui\Queue\TaskState;

/**
 * EntityListBuilder for task entities.
 *
 * @package Drupal\task_ui
 */
class TaskListBuilder extends EntityListBuilder {

  /**
   * {@inheritDoc}
   */
  public function buildHeader() {
    $header['name'] = $this->t('Name');
    $header['enabled'] = $this->t('Status');
    $header['exec_next'] = $this->t('Executes next');
    $header['last_exec'] = $this->t('Executed last');
    $header['last_result'] = $this->t('Last result');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritDoc}
   */
  public function buildRow(EntityInterface $entity) {
    $timezone = new \DateTimeZone(\Drupal::currentUser()->getTimeZone());

    /** @var \Drupal\task_ui\Entity\Task $task */
    $task = $entity;
    $row['name'] = $task->getName();

    if ($task->isEnabled()) {
      $status = $task->getState()->getStatus();
      switch ($status) {
        case TaskState::STATE_QUEUED:
          $row['enabled'] = $this->t('Enabled (Queued)');
          break;

        case TaskState::STATE_RUNNING:
          $row['enabled'] = $this->t('Enabled (Running)');
          break;

        case TaskState::STATE_IDLE:
          $row['enabled'] = $this->t('Enabled (Idle)');
          break;

        default:
          break;
      }
    }
    else {
      $row['enabled'] = $this->t('Disabled');
    }

    if ($next = $task->isDueNext()) {
      $row['exec_next'] = DrupalDateTime::createFromTimestamp($next)
        ->setTimezone($timezone)
        ->format('D, d M Y H:i');
    }
    else {
      $row['exec_next'] = '';
    }

    if ($task->hasRun()) {
      $row['last_exec'] = DrupalDateTime::createFromTimestamp($task->getState()->getLastExecution())
        ->setTimezone($timezone)
        ->format('D, d M Y H:i');
      $row['last_result'] = $task->lastExecutionSucceeded()
        ? $this->t('Success')
        : $this->t('Error');
    }
    else {
      $row['last_exec'] = '';
      $row['last_result'] = '';
    }

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritDoc}
   */
  public function getOperations(EntityInterface $entity) {
    /** @var \Drupal\task_ui\Entity\Task $task */
    $task = $entity;
    $operations = parent::getOperations($entity);
    $operations['run'] = [
      'title' => $this->t('Run'),
      'weight' => 10,
      'url' => $this->ensureDestination($entity->toUrl('run')),
    ];
    if ($task->isEnabled()) {
      $operations['disable'] = [
        'title' => $this->t('Disable'),
        'weight' => 10,
        'url' => $this->ensureDestination($entity->toUrl('disable')),
      ];
    }
    else {
      $operations['enable'] = [
        'title' => $this->t('Enable'),
        'weight' => 10,
        'url' => $this->ensureDestination($entity->toUrl('enable')),
      ];
    }

    return $operations;
  }

}
