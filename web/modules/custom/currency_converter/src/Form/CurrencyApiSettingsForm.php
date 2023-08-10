<?php

namespace Drupal\currency_converter\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\currency_converter\CurrencyService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Rate converter settings for this site.
 */
class CurrencyApiSettingsForm extends ConfigFormBase {

  /**
   * @var \Drupal\currency_converter\CurrencyService
   */
  protected CurrencyService $currencyService;

  public function __construct(ConfigFactoryInterface $config_factory, CurrencyService $currencyService) {
    parent::__construct($config_factory);
    $this->currencyService = $currencyService;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('config.factory'), $container->get('currency_converter.currency_service'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'currency_converter_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $rates = $this->currencyService->getRates()['rates'] ?? NULL;

    $form['access_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key'),
      '#default_value' => $this->config('currency_converter.settings')
        ->get('access_key'),
      '#required' => TRUE,
    ];

    $form['api_host'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API host'),
      '#default_value' => $this->config('currency_converter.settings')
        ->get('api_host'),
      '#required' => TRUE,
    ];

    if (!empty($rates)) {
      $rate_options = [];

      foreach ($rates as $name => $rate) {
        $rate_options[$name] = $name;
      }

      $default_exchanges = $this->config('currency_converter.settings')
        ->get('allowed_rates') ? array_intersect($this->config('currency_converter.settings')
        ->get('allowed_rates'), $rate_options) : [];
      $form['allowed_rates'] = [
        '#type' => 'checkboxes',
        '#options' => $rate_options,
        '#default_value' => $default_exchanges,
        '#title' => $this->t('Choose allowed exchanges.'),
      ];
    }
    else {
      $form['note'] = [
        '#markup' => '<p>Please fill API Key and API host to see available rates list provided by fixer.io</p>',
      ];
    }
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('currency_converter.settings')
      ->set('access_key', $form_state->getValue('access_key'))
      ->save();

    $this->config('currency_converter.settings')
      ->set('api_host', $form_state->getValue('api_host'))
      ->save();

    if (!empty($form_state->getValue('allowed_rates'))) {
      $allowed_rates = array_filter($form_state->getValue('allowed_rates'), function($value) {
        return $value != 0;
      });

      $this->config('currency_converter.settings')
        ->set('allowed_rates', $allowed_rates)
        ->save();
    }
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['currency_converter.settings'];
  }

}
