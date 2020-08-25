<?php

namespace Drupal\openy_gated_content\Controller;

use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class CategoriesResource Controller.
 */
class CategoriesController implements ContainerInjectionInterface {

  /**
   * The current active database's master connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a CustomFormattersController object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The current active database's master connection.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database')
    );
  }

  /**
   * Provides a list of categories uuid's that contains videos.
   */
  public function list($parentCategory) {
    $parentCatName = '';

    if (empty($parentCategory)) {
      $parentCategory = ['tid' => 0];
    } else {
      $query = $this->database->select('taxonomy_term_data', 't');
      $query->leftJoin('taxonomy_term_field_data', 'tf', 't.tid = tf.tid');
      $query->fields('t', ['tid'])
        ->fields('tf', ['name'])
        ->condition('uuid', $parentCategory);
      $parentCategory = $query->execute()->fetchAssoc();

      if (empty($parentCategory)) {
        return new JsonResponse('Not found', 404);
      }

      $parentCatName = $parentCategory['name'];
    }

    $query = $this->database->select('node__field_gc_video_category', 'n');
    $query->leftJoin('taxonomy_term_data', 't', 't.tid = n.field_gc_video_category_target_id');
    $query->leftJoin('taxonomy_term_field_data', 'tf', 't.tid = tf.tid');
    $query->leftJoin('taxonomy_term__parent', 'tp', 't.tid = tp.entity_id');
    $query->condition('t.vid', 'gc_category');
    $query->condition('tf.status', 1);
    $query->condition('tp.parent_target_id', $parentCategory['tid']);
    $query->fields('t', ['uuid']);
    $query->addExpression('COUNT(n.field_gc_video_category_target_id)', 'videosCount');
    $query->groupBy('t.uuid');
    $query->groupBy('n.field_gc_video_category_target_id');
    $query->having('COUNT(n.field_gc_video_category_target_id) > 0');
    $result = $query->execute()->fetchAll();

    return new JsonResponse([
      'parent_category_name' => $parentCatName,
      'categories' => $result
    ]);
  }

}
