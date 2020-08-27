<?php

namespace Drupal\openy_gc_shared_content_server\Plugin\rest\resource;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Rest resource for generating new shared sources.
 *
 * @RestResource(
 *   id = "shared_source_create",
 *   label = @Translation("Shared Source Create"),
 *   uri_paths = {
 *     "https://www.drupal.org/link-relations/create" = "/virtual-y/shared-source/generate-token"
 *   }
 * )
 */
class SharedSourceCreate extends ResourceBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The entity type targeted by this resource.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a Drupal\jwt_pass_reset\Plugin\rest\resource\JwtPassReset.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, array $serializer_formats, LoggerInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function requestMethods() {
    return ['POST'];
  }

  /**
   * Responds to entity POST requests and saves the new entity.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request object.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The HTTP response object.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function post(Request $request) {
    $data_json = $request->getContent();
    $data = json_decode($data_json, TRUE);
    if (!isset($data['name']) || !isset($data['host'])) {
      return new ModifiedResourceResponse([
        'message' => 'Missed required params!',
        'status' => 'Bad Request',
      ], 400);
    }

    $storage = $this->entityTypeManager->getStorage('shared_content_source');
    $content_source = $storage->loadByProperties([
      'name' => $data['name'],
      'url' => $data['host'],
    ]);

    if (!empty($content_source)) {
      $content_source = reset($content_source);
    }
    else {
      $content_source = $storage->create([
        'name' => $data['name'],
        'url' => $data['host'],
      ]);
      $content_source->save();
    }

    return new ModifiedResourceResponse([
      'token' => $content_source->getToken(),
      'status' => 'ok',
    ], 200);
  }

}
