<?php
 
/**
 * @file
 * Definition of Drupal\mirador_viewer\Plugin\views\field\MiradorViewer
 */
 
namespace Drupal\mirador_viewer\Plugin\views\field;
 
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\NodeType;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\mirador_viewer\Controller\DisplayMiradorController;
 
/**
 * Field handler to flag the node type.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("mirador_viewer")
 */
class MiradorViewer extends FieldPluginBase {
 
  /**
   * @{inheritdoc}
   */
  public function query() {
    // Leave empty to avoid a query on this field.
  }
 
  /**
   * Define the available options
   * @return array
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['id_field'] = array('default' => 'id');
 
    return $options;
  }
 
  /**
   * Provide the options form.
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $form['unused'] = array(
      '#title' => $this->t('Temporarily Unused'),
      '#type' => 'textfield',
    );
 
    parent::buildOptionsForm($form, $form_state);
  }
 
  /**
   * @{inheritdoc}
   */
  public function render(ResultRow $values) {
    $entity = $values->_item;
    $id = $entity->getId();
dsm($id);
$param = \Drupal::routeMatch()->getParameters();
dsm($parameters);

    $raw_param = \Drupal::routeMatch()->getParameter('arg_0');

    parse_str($raw_param, $url_array);
    if (!empty($url_array['relpath'])) {
      // $id = array_key_first($url_array);
      $collection_prefix = $url_array['relpath'];
    } else {
      // $collection_prefix = $this->getCollectionPrefix();
    }



    $c = new DisplayMiradorController();
    $render = $c->viewMiradorObject($id, $collection_prefix);
    if (!empty($render)) {
      return $render;
    }
  }
}
