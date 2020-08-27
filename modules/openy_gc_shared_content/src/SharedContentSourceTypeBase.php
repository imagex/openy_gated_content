<?php

namespace Drupal\openy_gc_shared_content;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Defines the base plugin for SharedContentSourceType classes.
 */
class SharedContentSourceTypeBase extends PluginBase implements SharedContentSourceTypeInterface, ContainerFactoryPluginInterface {

  use MessengerTrait;
  use StringTranslationTrait;

  /**
   * A guzzle http client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $client;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The JSON:API resource type repository.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface
   */
  protected $resourceTypeRepository;

  /**
   * The serializer.
   *
   * @var \Symfony\Component\Serializer\SerializerInterface
   */
  protected $serializer;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    Client $client,
    EntityTypeManagerInterface $entity_type_manager,
    ResourceTypeRepositoryInterface $resource_type_repository,
    SerializerInterface $serializer) {

    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->client = $client;
    $this->entityTypeManager = $entity_type_manager;
    $this->resourceTypeRepository = $resource_type_repository;
    $this->serializer = $serializer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    // Note that we ignore the plugin $configuration because mappers have
    // nothing to configure in themselves.
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('http_client'),
      $container->get('entity_type.manager'),
      $container->get('jsonapi.resource_type.repository'),
      $container->get('jsonapi.serializer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getId() {
    return $this->pluginDefinition['id'];
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityType() {
    return $this->pluginDefinition['entityType'];
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityBundle() {
    return $this->pluginDefinition['entityBundle'];
  }

  /**
   * {@inheritdoc}
   */
  public function getTeaserJsonApiQueryArgs() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getFullJsonApiQueryArgs() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getExcludedRelationships() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getIncludedRelationships() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function entityExists($uuid) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function processJsonApiData(array &$data) {

  }

  /**
   * {@inheritdoc}
   */
  public function getJsonApiEndpoint($uuid = NULL) {
    $url_parts = [
      'jsonapi',
      $this->getEntityType(),
      $this->getEntityBundle(),
    ];
    if ($uuid) {
      $url_parts[] = $uuid;
    }
    return implode('/', $url_parts);
  }

  /**
   * {@inheritdoc}
   */
  public function jsonApiCall($url, array $query_args = [], $uuid = NULL) {
    $request = $this->client->request(
      'GET',
      $url . '/' . $this->getJsonApiEndpoint($uuid),
      ['query' => $query_args]
    );

    if ($request->getStatusCode() != 200) {
      return FALSE;
    }

    return $this->serializer->decode($request->getBody()->getContents(), 'api_json');
  }

  /**
   * {@inheritdoc}
   */
  public function saveFromSource($url, $uuid) {
    if ($this->entityExists($uuid)) {
      $this->messenger()->addWarning($this->t('Entity with UUID "@uuid" already exists.', [
        '@uuid' => $uuid,
      ]));
      return FALSE;
    }
    $entity = NULL;
    $resource_type = $this->resourceTypeRepository->get($this->getEntityType(), $this->getEntityBundle());
    $query_args = $this->getFullJsonApiQueryArgs();
    $data = $this->jsonApiCall($url, $query_args, $uuid);
    if (!$data) {
      // TODO: or message with warning.
      return FALSE;
    }

    $this->processJsonApiData($data);
    // Delete relationships.
    foreach ($this->getExcludedRelationships() as $rel_name) {
      unset($data['data']['relationships'][$rel_name]);
    }

    try {
      $included_relationships = $this->getIncludedRelationships();
      $relationships_data = [];
      foreach ($included_relationships as $rel_name) {
        $rel_data = [];
        if (!isset($data['data']['relationships'][$rel_name])) {
          continue;
        }

        if (isset($data['data']['relationships'][$rel_name]['data'][0])) {
          // Can be multiple.
          $rel_data = $data['data']['relationships'][$rel_name]['data'];
        }
        else {
          // For single value save like multiple.
          $rel_data[0] = $data['data']['relationships'][$rel_name]['data'];
        }

        foreach ($rel_data as $seared_item) {
          foreach ($data['included'] as $item) {
            if ($item['type'] == $seared_item['type'] && $item['id'] == $seared_item['id']) {
              $relationships_data[$rel_name][] = $item;
              // Exit from both foreach when we find item.
              break 2;
            }
          }
        }

        foreach ($relationships_data as $field_name => $field_values) {
          foreach ($field_values as $delta => $value) {
            if (!isset($value['type'])) {
              continue;
            }
            $entity_context = explode("--", $value['type']);
            $entity_type = array_shift($entity_context);
            $entity_bundle = array_shift($entity_context);
            switch ($entity_type) {
              case 'taxonomy_term':
                $relationships_data[$field_name][$delta] = $this->saveTaxonomyFromSource($value, $entity_bundle);
                break;

              case 'media':
                $relationships_data[$field_name][$delta] = $this->saveMediaFromSource($data, $value, $entity_bundle, $url);
                break;
            }
          }
        }
      }
      $context = ['resource_type' => $resource_type];
      $data['data']['relationships'] = [];
      $entity = $this->serializer->denormalize($data, JsonApiDocumentTopLevel::class, 'api_json', $context);
      foreach ($included_relationships as $rel_name) {
        $entity->set($rel_name, $relationships_data[$rel_name]);
      }

      $entity->set('field_gc_origin', $url);
      $entity->save();
      $this->messenger()->addStatus($this->t('Entity {@type:@bundle} "@title" was fetched to site.', [
        '@type' => $this->getEntityType(),
        '@bundle' => $this->getEntityBundle(),
        '@title' => $entity->get('title')->value,
      ]));
      return TRUE;
    }
    catch (UnexpectedValueException $e) {
      throw new UnprocessableEntityHttpException($e->getMessage());
    }
    catch (InvalidArgumentException $e) {
      throw new UnprocessableEntityHttpException($e->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function saveMediaFromSource($parent_data, $data, $bundle, $url) {
    if (!in_array($bundle, ['video', 'image'])) {
      // Check for supported bundles.
      return [];
    }

    // Search existing media by uuid and bundle.
    $exists = $this->entityTypeManager->getStorage('media')
      ->getQuery()
      ->condition('bundle', $bundle)
      ->condition('uuid', $data['id'])
      ->execute();
    if (!empty($exists)) {
      // Return media ID.
      return ['target_id' => reset($exists)];
    }

    $file = NULL;
    if ($bundle == 'image') {
      if (!isset($data['relationships']['field_media_image'])) {
        return [];
      }

      $file_data = NULL;
      foreach ($parent_data['included'] as $included) {
        // Search image file in parent included items.
        if ($included['id'] == $data['relationships']['field_media_image']['data']['id'] && $included['type'] == $data['relationships']['field_media_image']['data']['type']) {
          $file_data = $included;
          break;
        }
      }
      if (!$file_data) {
        return [];
      }
      $file_temp = file_get_contents($url . $file_data['attributes']['uri']['url']);
      if (!$file_temp) {
        return [];
      }
      $file = file_save_data($file_temp, $file_data['attributes']['uri']['value']);
      if (!$file) {
        return [];
      }
    }

    unset($data['attributes']['drupal_internal__mid']);
    unset($data['attributes']['drupal_internal__vid']);
    unset($data['relationships']);

    $resource_type = $this->resourceTypeRepository->get('media', $bundle);
    $context = ['resource_type' => $resource_type];
    $media = $this->serializer->denormalize(['data' => $data], JsonApiDocumentTopLevel::class, 'api_json', $context);
    if ($bundle == 'image' && $file) {
      $media->set('field_media_image', ['target_id' => $file->id()]);
    }
    $media->save();

    return ['target_id' => $media->id()];
  }

  /**
   * {@inheritdoc}
   */
  public function saveTaxonomyFromSource($data, $bundle) {
    $exists = $this->entityTypeManager->getStorage('taxonomy_term')
      ->getQuery()
      ->condition('vid', $bundle)
      ->condition('name', $data['attributes']['name'])
      ->condition('status', 1)
      ->execute();

    if (!empty($exists)) {
      // Return term ID.
      return ['target_id' => reset($exists)];
    }

    unset($data['attributes']['drupal_internal__tid']);
    unset($data['attributes']['drupal_internal__revision_id']);
    unset($data['relationships']);
    $resource_type = $this->resourceTypeRepository->get('taxonomy_term', $bundle);
    $context = ['resource_type' => $resource_type];
    $term = $this->serializer->denormalize(['data' => $data], JsonApiDocumentTopLevel::class, 'api_json', $context);
    $term->save();
    $this->messenger()->addWarning($this->t('Taxonomy term "@name" not found in {@vid}, so it was created during data fetch.', [
      '@name' => $term->getName(),
      '@vid' => $bundle,
    ]));
    return ['target_id' => $term->id()];
  }

}
