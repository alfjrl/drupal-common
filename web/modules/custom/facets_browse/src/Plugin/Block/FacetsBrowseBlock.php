<?php

namespace Drupal\facets_browse\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Routing;
use Drupal\Core\Url;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Form\FormBase;
// use Drupal\search_api\ServerInterface;
use Drupal\search_api\Entity\Index;
use Drupal\Core\Link;

/**
 * Provides a browse facets block with config
 *
 * @Block(
 *   id = "facets_browse",
 *   admin_label = @Translation("Facets Browse"),
 *   category = @Translation("Search"),
 * )
 */
class FacetsBrowseBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Form builder service.
   *
   * @var Drupal\Core\Plugin\ContainerFactoryPluginInterface
   */
  protected $formBuilder;

  /**
   * Constructor.
   *
   * @param array $configuration
   *   Configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The id for the plugin.
   * @param mixed $plugin_definition
   *   The definition of the plugin implementaton.
   * @param Drupal\Core\Form\FormBuilderInterface $formBuilder
   *   The "form_builder" service instance to use.
   */
  public function __construct(
      array $configuration,
      $plugin_id,
      $plugin_definition,
      FormBuilderInterface $formBuilder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->formBuilder = $formBuilder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('form_builder'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $blockConfig = $this->getConfiguration();
    $facets_path = $blockConfig['facets_path'];
    $search_index = $blockConfig['search_index'];
    $facet_field = $blockConfig['facet_field'];
    $block_title = !empty($blockConfig['block_title']) ? $blockConfig['block_title'] : null;
    $show_counts = !empty($blockConfig['show_counts']) ? $blockConfig['show_counts'] : null;

    $index = Index::load($search_index);
    $backend = $index->getServerInstance()->getBackend();
    $connector = $backend->getSolrConnector();
    $query = $connector->getSelectQuery();

    $facetSet = $query->getFacetSet();
    $facetSet->createFacetField($facet_field)->setField($facet_field);

    $results = $connector->execute($query);

    $facets = [];
    if ($results->count() > 0) {
      $facets_raw = $results->getFacetSet()->getFacet($facet_field);
      foreach($facets_raw as $value => $count) {
        $facet_link = '<a href="' . $facets_path . $facet_field . ':' . $value . '" title="browse facets for ' . $value . '">' . $value . '</a>';
        if (!empty($show_counts)) {
          $facet_link .= ' (' . $count . ')';
        }
        $facets[] = ['#markup' => $facet_link];
      }
    }

    sort($facets);

    return [
      '#theme' => 'facets_browse',
      '#facets_path' => $facets_path,
      '#facets' => $facets,
      '#block_title' => $block_title,
      '#search_index' => $search_index,
      '#facet_name' => $facet_field,
      '#cache' => [
        'max-age' => 9999,
      ],
    ];
  }

  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $config = $this->getConfiguration();

    $index_options = [];
    foreach (search_api_solr_get_servers() as $server) {
      foreach ($server->getIndexes() as $index) {
        $index_options[$index->id()] = $index->id();
      }
    }

    $form['search_index'] = [
      '#type' => 'select',
      '#title' => t('Index'),
      '#options' => $index_options,
      '#required' => TRUE,
      '#default_value' =>  !empty($config['search_index']) ? $config['search_index'] : null,
    ];
    $form['facets_path'] = [
      '#type' => 'textfield',
      '#title' => t('Facets Path'),
      '#default_value' =>  !empty($config['facets_path']) ? $config['facets_path'] : null,
      '#description' => t('Relative path to search results with an empty facet at the end to be filled dynamically with facet. E.g., /searchnew?f[0]=collection:Diamondback Photos&f[1]='),
      '#required' => TRUE,
    ];
    $form['facet_field'] = [
      '#type' => 'textfield',
      '#title' => t('Facet Field'),
      '#default_value' =>  !empty($config['facet_field']) ? $config['facet_field'] : null,
      '#description' => t('Facet field to display.'),
      '#required' => TRUE,
    ];
    $form['block_title'] = [
      '#type' => 'textfield',
      '#title' => t('Block Title'),
      '#default_value' =>  !empty($config['block_title']) ? $config['block_title'] : null,
    ];
    $form['show_counts'] = [
      '#type' => 'checkbox',
      '#title' => t('Show facet counts'),
      '#default_value' =>  !empty($config['show_counts']) ? $config['show_counts'] : null,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->setConfigurationValue('facets_path', $form_state->getValue('facets_path'));
    $this->setConfigurationValue('block_title', $form_state->getValue('block_title'));
    $this->setConfigurationValue('facet_field', $form_state->getValue('facet_field'));
    $this->setConfigurationValue('search_index', $form_state->getValue('search_index'));
    $this->setConfigurationValue('show_counts', $form_state->getValue('show_counts'));
  }
}