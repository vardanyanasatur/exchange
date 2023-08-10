<?php

namespace Drupal\currency_converter\Entity;

use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\RevisionableContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\currency_converter\CurrencyInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the currency entity class.
 *
 * @ContentEntityType(
 *   id = "currency",
 *   label = @Translation("Currency"),
 *   label_collection = @Translation("Currencies"),
 *   label_singular = @Translation("currency"),
 *   label_plural = @Translation("currencies"),
 *   label_count = @PluralTranslation(
 *     singular = "@count currencies",
 *     plural = "@count currencies",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\currency_converter\CurrencyListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "add" = "Drupal\currency_converter\Form\CurrencyForm",
 *       "edit" = "Drupal\currency_converter\Form\CurrencyForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "currency",
 *   revision_table = "currency_revision",
 *   show_revision_ui = TRUE,
 *   admin_permission = "administer currency",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "revision_id",
 *     "label" = "id",
 *     "uuid" = "uuid",
 *     "owner" = "uid",
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_uid",
 *     "revision_created" = "revision_timestamp",
 *     "revision_log_message" = "revision_log",
 *   },
 *   links = {
 *     "collection" = "/admin/content/currency",
 *     "add-form" = "/currency/add",
 *     "canonical" = "/currency/{currency}",
 *     "edit-form" = "/currency/{currency}/edit",
 *     "delete-form" = "/currency/{currency}/delete",
 *   },
 *   field_ui_base_route = "entity.currency.settings",
 * )
 */
class Currency extends RevisionableContentEntityBase implements CurrencyInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);
    if (!$this->getOwnerId()) {
      // If no owner has been set explicitly, make the anonymous user the owner.
      $this->setOwnerId(0);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['currency'] = BaseFieldDefinition::create('string')
      ->setRevisionable(TRUE)
      ->setLabel(t('Currency'));

    $fields['base_currency'] = BaseFieldDefinition::create('string')
      ->setRevisionable(TRUE)
      ->setLabel(t('Base Currency'));

    $fields['rate'] = BaseFieldDefinition::create('decimal')
      ->setRevisionable(TRUE)
      ->setLabel(t('rate'));


    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setRevisionable(TRUE)
      ->setLabel(t('Status'))
      ->setDefaultValue(TRUE)
      ->setSetting('on_label', 'Enabled')
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => FALSE,
        ],
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'type' => 'boolean',
        'label' => 'above',
        'weight' => 0,
        'settings' => [
          'format' => 'enabled-disabled',
        ],
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setRevisionable(TRUE)
      ->setLabel(t('Author'))
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback(static::class . '::getDefaultEntityOwner')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'placeholder' => '',
        ],
        'weight' => 15,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'author',
        'weight' => 15,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setDescription(t('The time that the currency was created.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the currency was last edited.'));

    return $fields;
  }

}
