<?php

namespace Drupal\task_ui\Log;

use Psr\Log\LoggerTrait as PsrLoggerTrait;

/**
 * Provides logger injection.
 *
 * @package Drupal\intranet_core\Logger
 */
trait LoggerTrait {

  use PsrLoggerTrait;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Retrieve the logger channel.
   *
   * @return \Drupal\Core\Logger\LoggerChannelInterface
   *   A logger channel.
   */
  protected function logger() {
    return $this->logger;
  }

  /**
   * {@inheritDoc}
   */
  public function log($level, $message, array $context = []) {
    if ($this->logger()) {
      $this->logger()
        ->log($level, $message, $context);
    }
  }

}
