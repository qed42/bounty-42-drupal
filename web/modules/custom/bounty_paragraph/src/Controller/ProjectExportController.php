<?php

namespace Drupal\bounty_paragraph\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Project CSX export.
 */
class ProjectExportController extends ControllerBase {
  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Constructs a ProjectExportController object.
   *
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(FileSystemInterface $file_system, LoggerChannelFactoryInterface $logger_factory) {
    $this->fileSystem = $file_system;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
          $container->get('file_system'),
          $container->get('logger.factory')
      );
  }

  /**
   * Export project nodes to CSV via HTTP endpoint.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The CSV file response or error response.
   */
  public function exportCsv() {
    $logger = $this->loggerFactory->get('bounty_paragraph');

    try {
      // Create temporary file.
      $temp_file = $this->fileSystem->tempnam('temporary://', 'projects_export_');
      if (!$temp_file) {
        $logger->error('Failed to create temporary file for CSV export');
        return new Response('Error: Unable to create temporary file', 500);
      }

      $handle = fopen($temp_file, 'w');
      if (!$handle) {
        $logger->error("Cannot open temporary file for writing: $temp_file");
        return new Response('Error: Unable to open file for writing', 500);
      }

      // Write header row.
      fputcsv($handle, ['Title', 'Execution Plan 1', 'Execution Plan 2', 'Execution Plan 3']);

      // Get project nodes.
      $nids = $this->entityTypeManager()->getStorage('node')->getQuery()
        ->condition('type', 'project')
      // Only published nodes.
        ->condition('status', 1)
      // Bypass access checks for admin operation.
        ->accessCheck(FALSE)
        ->execute();

      if (empty($nids)) {
        $logger->warning('No project nodes found for export');
        fclose($handle);
        // Still return empty CSV with headers.
      }
      else {
        $logger->info('Found ' . count($nids) . ' project nodes to export');

        $nodes = Node::loadMultiple($nids);
        $exported_count = 0;

        foreach ($nodes as $node) {
          $title = $this->cleanText($node->label());
          $plans = ['', '', ''];

          if ($node->hasField("field_execution_tracks") && !$node->get('field_execution_tracks')->isEmpty()) {
            $execution_track_paras = $node->get('field_execution_tracks')->referencedEntities();

            foreach ($execution_track_paras as $index => $track) {
              if ($index > 2) {
                break;
              }
              $plans[$index] = $this->formatExecutionPlan($track);
            }
          }

          // Write row.
          fputcsv($handle, array_merge([$title], $plans));
          $exported_count++;
        }

        $logger->info("Successfully exported $exported_count projects");
      }

      fclose($handle);

      // Create file response.
      $response = new BinaryFileResponse($temp_file);
      $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'projects_export_' . date('Y-m-d_H-i-s') . '.csv'
        );
      $response->headers->set('Content-Type', 'text/csv');

      // Delete temporary file after response is sent.
      $response->deleteFileAfterSend(TRUE);

      return $response;

    }
    catch (\Exception $e) {
      $logger->error('Error during CSV export: ' . $e->getMessage());
      return new Response('Error: ' . $e->getMessage(), 500);
    }
  }

  /**
   * Format execution plan data for better structure and readability.
   *
   * @param object $track
   *   The execution track paragraph entity.
   *
   * @return string
   *   Formatted execution plan text.
   */
  protected function formatExecutionPlan($track) {
    if (!$track->hasField('field_execution_plan') || $track->get('field_execution_plan')->isEmpty()) {
      return '';
    }

    $track_plan = $track->get('field_execution_plan')->referencedEntities();
    $milestones = [];

    foreach ($track_plan as $step => $plan) {
      $milestone = $this->formatMilestone($plan, $step + 1);
      if (!empty($milestone)) {
        $milestones[] = $milestone;
      }
    }

    return implode("\n", $milestones);
  }

  /**
   * Format a single milestone with consistent structure.
   *
   * @param object $plan
   *   The milestone paragraph entity.
   * @param int $step_number
   *   The milestone step number.
   *
   * @return string
   *   Formatted milestone text.
   */
  protected function formatMilestone($plan, $step_number) {
    $name = $plan->get('field_milestone_name')->value ?? '';
    $details = $plan->get('field_milestone_details')->value ?? '';

    // Clean both fields but preserve original content.
    $name = $this->cleanText($name);
    $details = $this->cleanText($details);

    if (empty($name) && empty($details)) {
      return '';
    }

    // Keep original "Milestone" format.
    $formatted = "Milestone $step_number: ";

    if (!empty($name) && !empty($details)) {
      $formatted .= "$name - $details";
    }
    elseif (!empty($name)) {
      $formatted .= $name;
    }
    else {
      $formatted .= $details;
    }

    return $formatted;
  }

  /**
   * Clean text for better CSV formatting but preserve content.
   *
   * @param string $text
   *   Raw text to clean.
   *
   * @return string
   *   Cleaned text.
   */
  protected function cleanText($text) {
    if (empty($text)) {
      return '';
    }

    // Strip HTML tags only.
    $text = strip_tags($text);

    // Normalize excessive whitespace but keep single spaces and line breaks.
    $text = preg_replace('/[ \t]+/', ' ', $text);

    // Trim excess whitespace from start and end.
    $text = trim($text);

    return $text;
  }

  /**
   * Alternative endpoint that returns JSON with export status.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   JSON response with export information.
   */
  public function exportStatus() {
    try {
      // Get count of project nodes.
      $count = $this->entityTypeManager()->getStorage('node')->getQuery()
        ->condition('type', 'project')
        ->condition('status', 1)
        ->accessCheck(FALSE)
        ->count()
        ->execute();

      $data = [
            'status' => 'success',
            'total_projects' => $count,
            'message' => "Found $count project(s) available for export",
            'export_url' => '/admin/projects/export/csv',
            'timestamp' => date('c')
        ];

      return new Response(json_encode($data), 200, ['Content-Type' => 'application/json']);

    }
    catch (\Exception $e) {
      $data = [
            'status' => 'error',
            'message' => $e->getMessage(),
            'timestamp' => date('c')
        ];

      return new Response(json_encode($data), 500, ['Content-Type' => 'application/json']);
    }
  }

}
