<?php

namespace Drupal\currency_converter;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a currency entity type.
 */
interface CurrencyInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

}
