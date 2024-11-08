<?php

namespace Drupal\hero_search\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Drupal\hero_search\Helper\HeroSearchSettingsHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements search box displayed atop a hero image.
 */
class HeroSearchForm extends FormBase {

  /**
   * The logger instance.
   *
   * @var Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The HeroSearchSettingsHelper instance.
   *
   * @var Drupal\hero_search\Helper\HeroSearchSettingsHelper
   */
  protected $configHelper;

  /**
   * Constructor.
   *
   * @param Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger instance.
   */
  public function __construct(LoggerChannelInterface $logger) {
    $this->logger = $logger;
    $this->configHelper = HeroSearchSettingsHelper::getInstance();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    return new static(
      // Load the service required to construct this class.
      $container->get('logger.channel.hero_search')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'hero_search_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $defaults = array()) {
    $this->buildSearchQueryTextField($form, $defaults);

    $form['search_target'] = [
      '#type' => 'hidden',
      '#name' => 'search_target',
      '#default_value' => $defaults['search_target'],
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
    ];

    $form['#theme'] = 'hero_search_form';
    return $form;
  }

  /**
   * Constructs a search query textfield for the given configuration.
   *
   * @param array $form
   *   The form to add the search query textfield to.
   * @param array $search_target_config
   *   The search target configuration.
   */
  protected function buildSearchQueryTextField(array &$form, array $search_target_config) {
    $search_target_name = $search_target_config['search_target'];
    $id = Html::getId('search_query_input_' . $search_target_name);

    $form['search_query'][] = [
      '#type' => 'textfield',
      '#name' => 'search_query',
      '#placeholder' => $search_target_config['placeholder'] ?? '',
      '#size' => 50,
      '#maxlength' => 128,
      '#attributes' => [
        'id' => $id,
        'aria-label' => $search_target_config['placeholder'],
        'autocomplete' => 'off',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Nothing.
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Can't seem to retreive the query text from the "search_query" textbox via
    // the "getValue" or "getValues" method, so pulling the raw value using the
    // "getUserInput" method.
    $user_input = $form_state->getUserInput();
    $query = $user_input['search_query'];

    $target = $form_state->getValue('search_target');

    $target_base_url = $this->configHelper->getSearchTargetUrl($target);
    $url = '/';
    if ($target_base_url == NULL) {
      $this->logger->notice("The base search Url configuration for '$target' is missing!");
    }
    else {
      $encoded_query = urlencode($query);
      $encoded_query = trim($encoded_query);
      if (!empty($encoded_query) && str_contains($target_base_url, 'usmai-umcp.primo.exlibrisgroup')) {
        $encoded_query = 'any,contains,' . $encoded_query;
      }
      $url = Url::fromUri($target_base_url . $encoded_query)->toString();
    }
    $response = new TrustedRedirectResponse($url);
    $form_state->setResponse($response);
  }

}
