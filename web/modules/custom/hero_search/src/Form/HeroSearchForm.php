<?php
/**
 * @file
 * Definition of HeroSearchForm
 */

namespace Drupal\hero_search\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Drupal\hero_search\Helper\HeroSearchSettingsHelper;

/**
 * Implement HeroSearchForm
*/
class HeroSearchForm extends FormBase {

  protected $configHelper;

  public function __construct() {
    $this->configHelper = HeroSearchSettingsHelper::getInstance();
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
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['search_query'] = [
      '#type' => 'textfield',
      '#name' => 'search_query',
      '#placeholder' => $this->configHelper->getSearchPlaceholder(),
      '#size' => 50,
      '#maxlength' => 60,
      '#required' => TRUE,
    ];
    $form['search_target'] = array(
      '#type' => 'radios',
      '#name' => 'search_target',
      '#default_value' => array_key_first($this->configHelper->getSearchTargetOptions()),
      '#options' => $this->configHelper->getSearchTargetOptions(),
    );
    $adv_search_link = $this->configHelper->getLinkField('advanced_search');
    if ($adv_search_link != null) {
      $form['advanced_search'] = [
        '#type' => 'markup',
        '#markup' => "<a href='{$adv_search_link['url']}' title='{$this->t($adv_search_link['title'])}'>{$this->t($adv_search_link['text'])}</a>",
        '#url' => $adv_search_link['url'],
      ];
    }
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
    ];
   
    $form['#theme'] = 'hero_search_form';
    return $form;
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
    $query = $form_state->getValue('search_query');
    $target = $form_state->getValue('search_target');
    $target_base_url = $this->configHelper->getSearchTargetUrl($target);
    $url = '/';
    if ($target_base_url == null) {
      \Drupal::logger('hero_search')->notice("The base search Url configuration for '$target' is missing!");
    } else {
      $url = Url::fromUri($target_base_url . $query)->toString();
    }
    $response = new TrustedRedirectResponse($url);
    $form_state->setResponse($response);
  }
}