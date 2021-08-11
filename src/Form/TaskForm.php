<?php

namespace Drupal\task_ui\Form;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\task_ui\Queue\TaskQueueWorkerBase;

/**
 * Form for creating and editing tasks.
 *
 * @package Drupal\task_ui\Form
 */
class TaskForm extends EntityForm {

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    /** @var \Drupal\task_ui\Entity\Task $task */
    $task = $this->entity;

    $form['id'] = [
      '#type' => 'machine_name',
      '#title' => 'ID',
      '#default_value' => $task->id(),
      '#machine_name' => [
        'exists' => '\Drupal\task_ui\Entity\Task::load',
      ],
      '#disabled' => !$task->isNew(),
    ];

    $name = $task->isNew() ? '' : $task->getName();
    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#description' => $this->t('Names help identify what tasks are doing.'),
      '#required' => TRUE,
      '#default_value' => $name,
    ];

    /** @var \Drupal\Core\Queue\QueueWorkerManagerInterface $worker_manager */
    $worker_manager = \Drupal::service('plugin.manager.queue_worker');
    foreach ($worker_manager->getDefinitions() as $worker_id => $worker_info) {
      $worker_options[$worker_id] = $worker_info['title'];
    }
    $default_worker = !$task->isNew() ? $task->getWorker()->getPluginId() : '';
    $form['worker_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Worker'),
      '#description' => $this->t('Choose the type of work to be done.'),
      '#options' => !empty($worker_options) ? $worker_options : [],
      '#default_value' => $default_worker,
      '#empty_value' => '',
      '#empty_option' => '- Select -',
      '#required' => TRUE,
    ];

    $form['params'] = [
      '#type' => 'container',
      '#id' => 'worker-params',
      '#tree' => TRUE,
    ];

    foreach (array_keys($worker_options) as $worker_id) {
      /** @var \Drupal\task_ui\Queue\TaskQueueWorkerBase $worker */
      $worker = $worker_manager->createInstance($worker_id);
      $subform = [
        '#type' => 'container',
        '#states' => [
          'visible' => [
            'select[name="worker_id"]' => [
              'value' => $worker_id,
            ],
          ],
          'required' => [
            'select[name="worker_id"]' => [
              'value' => $worker_id,
            ],
          ],
        ],
        '#tree' => TRUE,
      ];

      if ($worker instanceof TaskQueueWorkerBase) {
        $params = [];
        if (!$task->isNew() && $worker->getPluginId() === $task->getWorker()->getPluginId()) {
          $params = $task->getParams();
        }
        foreach ($worker->buildSubForm($params) as $param => $parm_subform) {
          $subform[$param] = $parm_subform;
        }
        $form['params'][$worker_id] = $subform;
      }
    }

    $is_recurring = $task->isNew() ? 1 : intval($task->isRecurring());
    $form['is_recurring'] = [
      '#type' => 'select',
      '#title' => $this->t('Is Recurring?'),
      '#description' => $this->t('Shall this task execute just once or periodically run?'),
      '#required' => TRUE,
      '#options' => [
        0 => $this->t('No'),
        1 => $this->t('Yes'),
      ],
      '#default_value' => $is_recurring,
    ];

    $interval = $task->isNew() || !$task->isRecurring() ? 1440 : $task->getInterval() / 60;
    $form['interval'] = [
      '#type' => 'select',
      '#title' => $this->t('Frequency'),
      '#description' => $this->t('How frequent shall the task execute?'),
      '#options' => [
        15 => 'every 15min',
        30 => 'every 30min',
        60 => 'every 1 Hour',
        120 => 'every 2 Hours',
        240 => 'every 4 Hours',
        720 => 'twice per day',
        1440 => 'once per day',
        10080 => 'once per week',
      ],
      '#default_value' => $interval,
      '#states' => [
        'visible' => [
          'select[name="is_recurring"]' => [
            'value' => 1,
          ],
        ],
        'required' => [
          'select[name="is_recurring"]' => [
            'value' => 1,
          ],
        ],
      ],
    ];

    $datetime = $task->isNew() ? $this->roundToNextQuarterHour(new DrupalDateTime()) : $task->firstExecutes();
    $form['first_exec'] = [
      '#type' => 'datetime',
      '#title' => $this->t('First Execution'),
      '#description' => $this->t('When shall the task execute for the first time?'),
      '#default_value' => $datetime,
    ];

    return $form;
  }

  /**
   * Calculates the upcoming quarter hour, based on the given datetime object.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $datetime
   *   The datetime object to round up.
   *
   * @return \Drupal\Core\Datetime\DrupalDateTime
   *   A new datetime object representing the next upcoming quarter hour.
   */
  protected function roundToNextQuarterHour(DrupalDateTime $datetime) {
    $next_quarter_hour_timestamp = ceil($datetime->getTimestamp() / (15 * 60)) * 15 * 60;
    $datetime->setTimestamp($next_quarter_hour_timestamp);
    return $datetime;
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\task_ui\Entity\Task $task */
    $task = $this->entity;

    $is_recurring = $form_state->getValue('is_recurring');
    $interval = $is_recurring ? $form_state->getValue('interval') * 60 : 0;
    $form_state->setValue('interval', $interval);
    $form_state->unsetValue('is_recurring');

    $selected_worker_id = $form_state->getValue('worker_id');
    if (($params = $form_state->getValue('params')) && array_key_exists($selected_worker_id, $params)) {
      $params = $form_state->getValue('params')[$selected_worker_id];
    }
    $form_state->unsetValue('params');
    $form_state->setValue('params', $params);

    /** @var \Drupal\Core\Datetime\DrupalDateTime $first_exec */
    $first_exec = $this->roundToNextQuarterHour($form_state->getValue('first_exec'));
    $form_state->setValue('first_exec', $first_exec->getTimestamp());

    if ($task->isNew()) {
      $form_state->setValue('enabled', TRUE);
    }
    else {
      $form_state->setValue('enabled', $task->status());
    }

    $form_state->setRedirect('entity.task.collection');

    parent::submitForm($form, $form_state);
  }

}
