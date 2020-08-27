<?php

namespace Drupal\openy_gc_log;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Logger\LoggerChannel;
use Drupal\Core\Site\Settings;
use Drupal\csv_serialization\Encoder\CsvEncoder;
use Drupal\file\Entity\File;

/**
 * LogArchiver service.
 */
class LogArchiver {

  const WORKER_CHUNK_SIZE = 600;

  const BASE_ARCHIVE_PATH = 'vy_logs';

  /**
   * Log Ids.
   *
   * @var array
   */
  protected $logIds;

  /**
   * Log Entities.
   *
   * @var \Drupal\Core\Entity\EntityInterface[]
   */
  protected $logEntities;

  /**
   * Prepared Logs.
   *
   * @var array
   */
  protected $preparedLogs;

  /**
   * File Entities.
   *
   * @var \Drupal\Core\Entity\EntityInterface[]
   */
  protected $fileEntities;

  /**
   * Entity Type Manager to work with.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  private $entityTypeManager;

  /**
   * Logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannel
   */
  private $logger;


  /**
   * Configs.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  private $config;

  /**
   * FileSystem.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * Site Settings.
   *
   * @var \Drupal\Core\Site\Settings
   */
  protected $settings;

  /**
   * LogArchiver constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   EntityTypeManager.
   * @param \Drupal\Core\Logger\LoggerChannel $logger
   *   LoggerChannel.
   * @param \Drupal\Core\Config\ConfigFactory $configFactory
   *   ConfigFactory.
   * @param \Drupal\Core\File\FileSystem $fileSystem
   *   FileSystem.
   * @param \Drupal\Core\Site\Settings $settings
   *   Settings.
   */
  public function __construct(
    EntityTypeManager $entityTypeManager,
    LoggerChannel $logger,
    ConfigFactory $configFactory,
    FileSystem $fileSystem,
    Settings $settings
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->logger = $logger;
    $this->config = $configFactory;
    $this->fileSystem = $fileSystem;
    $this->settings = $settings;
  }

  /**
   * Archiving loop, should be run from cron.
   */
  public function archive() {
    $tz = $this->config->get('system.date')->get('timezone')['default'];
    $end_of_month = (new \DateTime(
      date('Y-m-d', strtotime('last day of last month')),
      new \DateTimeZone($tz)
    ))->setTime(23, 59, 59);

    $end_time = $end_of_month->getTimeStamp();

    $start_of_month = clone $end_of_month;
    $start_of_month->modify('first day of this month 00:00');

    $start_time = $start_of_month->getTimestamp();

    $log_ids = $this->entityTypeManager->getStorage('log_entity')
      ->getQuery()
      ->condition('created', [$start_time, $end_time], 'BETWEEN')
      ->sort('created', 'ASC')
      ->range(0, self::WORKER_CHUNK_SIZE)
      ->execute();

    if (empty($log_ids)) {
      return;
    }

    $this->setLogIds($log_ids)
      ->loadLogEntities()
      ->prepareLogsForExport()
      ->loadFileEntities()
      ->writeLogsToFiles()
      ->saveFileEntities()
      ->reportToDbLog()
      ->removeLogsFromDb();
  }

  /**
   * Set log ids.
   */
  protected function setLogIds($logIds) {
    $this->logIds = $logIds;

    return $this;
  }

  /**
   * Load log entities.
   */
  protected function loadLogEntities() {
    $this->logEntities = $this->entityTypeManager
      ->getStorage('log_entity')
      ->loadMultiple($this->logIds);

    return $this;
  }

  /**
   * Check is private dir enabled.
   */
  protected function isPrivateDirEnabled() {
    return $this->settings::get('file_private_path', FALSE);
  }

  /**
   * Prepare directory for logs.
   */
  protected function prepareYearDirectory($dir) {
    if ($this->isPrivateDirEnabled()) {
      $dir = 'private://' . self::BASE_ARCHIVE_PATH . DIRECTORY_SEPARATOR . $dir;
    }
    else {
      $salt = $this->settings::getHashSalt();
      $dir = 'public://' . self::BASE_ARCHIVE_PATH . DIRECTORY_SEPARATOR . $salt .
        DIRECTORY_SEPARATOR . $dir;
    }
    if (!$this->fileSystem->prepareDirectory($dir,
      FileSystem::CREATE_DIRECTORY)) {
      throw new \RuntimeException(sprintf('Can not create directory "%s"', $dir));
    }

    return $dir;
  }

  /**
   * Make filename.
   */
  protected function makeFilename($logEntity) {
    $created = (int) $logEntity->get('created')->value;
    $created_month = date('m', $created);
    $created_year = date('Y', $created);

    return "virtual-y-logs-{$created_year}-{$created_month}.csv.gz";
  }

  /**
   * Prepare logs for export.
   */
  protected function prepareLogsForExport() {
    foreach ($this->logEntities as $log) {
      $fileName = $this->makeFilename($log);
      if (!isset($this->preparedLogs[$fileName])) {
        $this->preparedLogs[$fileName] = [];
      }

      $this->preparedLogs[$fileName][] = [
        'created' => $log->get('created')->value,
        'event_type' => $log->get('event_type')->value,
        'entity_type' => $log->get('entity_type')->value,
        'entity_bundle' => $log->get('entity_bundle')->value,
        'entity_id' => $log->get('entity_id')->value,
      ];
    }

    return $this;
  }

  /**
   * Create new file entity.
   */
  protected function createNewFileEntity($fileName) {
    $fileYear = date('Y', (int) $this->preparedLogs[$fileName][0]['created']);
    $yearDir = $this->prepareYearDirectory($fileYear);
    $file = File::create();
    $file->setFilename($fileName);
    if ($this->isPrivateDirEnabled()) {
      $file->setFileUri($yearDir . DIRECTORY_SEPARATOR . $fileName);
    }
    else {
      $file->setFileUri($yearDir . DIRECTORY_SEPARATOR . md5(mt_rand()) . '-' . $fileName);
    }
    $file->setMimeType('application/x-gzip');
    $file->setPermanent();
    return $file;
  }

  /**
   * Load file entities.
   */
  protected function loadFileEntities() {
    $file_ids = $this->entityTypeManager
      ->getStorage('file')
      ->getQuery()
      ->condition('filename', array_keys($this->preparedLogs), 'in')
      ->execute();

    $file_entities = $this->entityTypeManager
      ->getStorage('file')->loadMultiple($file_ids);

    $this->fileEntities = [];
    foreach ($file_entities as $file_entity) {
      $this->fileEntities[$file_entity->getFilename()] = $file_entity;
    }

    foreach (array_keys($this->preparedLogs) as $fileName) {
      if (!array_key_exists($fileName, $this->fileEntities)) {
        $this->fileEntities[$fileName] = $this->createNewFileEntity($fileName);
      }
    }

    return $this;
  }

  /**
   * Write logs to files.
   */
  protected function writeLogsToFiles() {
    foreach ($this->preparedLogs as $fileName => $logs) {
      $fileEntity = $this->fileEntities[$fileName];

      $csvEncoder = new CsvEncoder();
      $csvEncoder->setOutputHeader($fileEntity->isNew());

      $fileContents = $csvEncoder->encode($logs, 'csv');

      $filePointer = gzopen($fileEntity->getFileUri(), 'a3');
      gzwrite($filePointer, "\n" . $fileContents);
      gzclose($filePointer);
    }

    return $this;
  }

  /**
   * Save file entities.
   */
  protected function saveFileEntities() {
    foreach ($this->fileEntities as $fileEntity) {
      $fileEntity->save();
    }

    return $this;
  }

  /**
   * Report to DbLog.
   */
  protected function reportToDbLog() {
    $this->logger->debug(
      'Virtual Y Logs processed into archive: %count entities.',
      [
        '%count' => count($this->logIds),
      ]
    );

    return $this;
  }

  /**
   * Remove logs from db.
   */
  protected function removeLogsFromDb() {
    $this->entityTypeManager
      ->getStorage('log_entity')
      ->delete($this->logEntities);

    return $this;
  }

}
