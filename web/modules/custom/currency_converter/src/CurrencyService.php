<?php

namespace Drupal\currency_converter;

use Drupal;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Http\ClientFactory;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Session\AccountProxyInterface;
use Exception;
use GuzzleHttp\Exception\RequestException;
use League\Container\Exception\NotFoundException;

/**
 * Service description.
 */
class CurrencyService {

  protected ConfigFactoryInterface $configFactory;

  /**
   * @var array|mixed
   */
  protected mixed $allowedRates;

  protected Drupal\Core\Session\AccountProxyInterface $accountProxy;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected EntityTypeManager $entityTypeManager;

  private $accessKey;

  private mixed $apiHost;

  /**
   * @var \Drupal\Core\Http\ClientFactory
   */
  private ClientFactory $clientFactory;

  /**
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  private LoggerChannelFactory $loggerFactory;

  public function __construct(ConfigFactory $configFactory, ClientFactory $clientFactory, EntityTypeManager $entityTypeManager, AccountProxyInterface $accountProxy, LoggerChannelFactory $loggerFactory) {
    $this->clientFactory = $clientFactory;
    $this->configFactory = $configFactory;
    $exchange_settings = $this->configFactory->getEditable('currency_converter.settings');
    $this->accessKey = $exchange_settings->get('access_key') ?? NULL;
    $this->apiHost = $exchange_settings->get('api_host') ?? NULL;
    $this->allowedRates = $exchange_settings->get('allowed_rates') ?? [];
    $this->entityTypeManager = $entityTypeManager;
    $this->accountProxy = $accountProxy;
    $this->loggerFactory = $loggerFactory;
  }

  /**
   * Method description.
   */
  public function getRates() {
    return $this->fixerClient('latest') ?? [];
  }

  public function fixerClient($endpoint = 'latest', $query_params = []) {
    $client = $this->clientFactory->fromOptions();

    if (!$this->accessKey or !$this->apiHost or empty($endpoint)) {
      $this->loggerFactory->get('currency_converter')
        ->error('Please add API access_key and api_host.');
      return [];
    }

    $query_params = array_merge(['access_key' => $this->accessKey], $query_params);
    $queryString = http_build_query($query_params);

    $full_url = $this->apiHost . $endpoint . '?' . $queryString;
    try {
      $response = $client->get($full_url);
      $statusCode = $response->getStatusCode();

      if ($statusCode === 200) {
        $content = $response->getBody();
        return json_decode($content, TRUE);
      }
      else {
        $this->loggerFactory->get('currency_converter')
          ->error('Please contact with administrator. Status code is @code', ['@code' => $statusCode]);
        return [];
      }
    }
    catch (RequestException $e) {
      $this->loggerFactory->get('currency_converter')->error($e);
      return [];
    }
  }

  /**
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function importCurrency($endpoint = 'latest'): void {
    $result = $this->fixerClient($endpoint);

    if (!empty($result)) {
      $base = $result['base'] ?? 'USD';
      $date = $result['date'];
      $rates = $result['rates'];

      $currencyStorage = $this->entityTypeManager->getStorage('currency');
      foreach ($this->allowedRates as $currency_name) {
        $currency = $currencyStorage->loadByProperties(['currency' => $currency_name]);
        if (empty($currency)) {
          /** @var \Drupal\currency_converter\Entity\Currency $currency */
          $currency = $currencyStorage->create([
            'base_currency' => $base,
            'currency' => $currency_name,
            'rate' => $rates[$currency_name],
            'status' => 1,
            'uid' => 1,
          ]);
          $currency->enforceIsNew();
          $currency->save();
          $this->loggerFactory->get('currency_converter')
            ->notice('Successfully added @currency rate at @date', [
              '@currency' => $currency_name,
              '@date' => $date,
            ]);
        }
        else {
          $currency = reset($currency);
          /** @var \Drupal\currency_converter\Entity\Currency $currency */
          $currency->set('base_currency', $base);
          $currency->set('currency', $currency_name);
          $currency->set('rate', $rates[$currency_name]);
          $currency->set('status', 1);
          $currency->set('uid', 1);
          $currency->setNewRevision();
          $currency->save();
          $this->loggerFactory->get('currency_converter')
            ->notice('Imported @currency rate at @date', [
              '@currency' => $currency_name,
              '@date' => $date,
            ]);
        }
      }
    }
    else {
      $this->loggerFactory->get('currency_converter')
        ->warning('Noting to import or update');
    }
  }

  public function convert($amount, $convert_from, $convert_to) {
    $currencyStorage = $this->entityTypeManager->getStorage('currency');
    $rate_from = $currencyStorage->loadByProperties(['currency' => $convert_from]);
    if (empty($rate_from)) {
      return new NotFoundException($convert_from . 'not found in allowed exchange rates.');
    }

    $rate_to = $currencyStorage->loadByProperties(['currency' => $convert_to]);
    if (empty($rate_to)) {
      return new NotFoundException($convert_to . ' not found in allowed exchange rates.');
    }

    if ($amount <= 0) {
      return new Exception('Amount should be big than 0.');
    }

    if ($convert_from === $convert_to) {
      return $amount;
    }

    $rate_from = reset($rate_from);
    $rate_to = reset($rate_to);

    return round($amount * $rate_to->get('rate')->value / $rate_from->get('rate')->value, 4);
  }

}
