<?php
/**
 * @file
 * Definition of Drupal\lib_cal\Plugin\Block\LibHoursTodayBlock
 */

namespace Drupal\lib_cal\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\lib_cal\Controller\LibHoursController;
use Drupal\lib_cal\Helper\LibCalSettingsHelper;
use Drupal\Core\Routing;
use Drupal\taxonomy\Entity\Term;

/**
 * Implements the LibHoursBlock
 * 
 * @Block(
 *   id = "lib_hours_today",
 *   admin_label = @Translation("Lib Cal Hours"),
 *   category = @Translation("custom"),
 * )
 */
class LibHoursTodayBlock extends BlockBase {

  private $configHelper;

  /**
   * {@inheritdoc}
   */
  public function build() {
    $debug_date = \Drupal::request()->query->get('debug_date');
    $blockConfig = $this->getConfiguration();
    $libHoursController = new LibHoursController();
    $is_mobile = false;

    if ($blockConfig['weekly_display']) {
      $template = 'lib_hours_range';
      $hours = $libHoursController->getThisWeek($blockConfig['libraries'], $debug_date);
    } else {
      switch ($blockConfig['display_type']) {
        case 'today':
          $template = 'lib_hours_today';
          $hours = $libHoursController->getToday($blockConfig['libraries'], $debug_date);
          $hours = $this->sortLocationsHeirarchy($hours);
          break;
        case 'weekly':
          $template = 'lib_hours_range';
          $hours = $libHoursController->getThisWeek($blockConfig['libraries'], $debug_date);
          break;
        case 'utility_nav':
          $template = 'lib_hours_today_util';
          $hours = $libHoursController->getToday($blockConfig['libraries'], $debug_date);
          $is_mobile = $blockConfig['is_mobile'];
          break;
         default:
          $template = 'lib_hours_today';
          break;
      }
    }

    $row_class = 'lib-hours-constrained';
    $grid_class = null;
    $current_date = null;
    if ($blockConfig['date_display']) {
      if ($debug_date != null) {
        $current_date = $debug_date;
      } else {
        $current_date = date("c");
      }
      $week_date = $hours['hours_from'];
    }

    unset($hours['hours_from']);

    $hours_class = 'hours-main-grid';
    if (count($hours) == 1) {
      $hours_class = 'hours-main';
    }

    return [
      '#theme' => $template,
      '#hours' => $hours,
      '#row_class' => $row_class,
      '#grid_class' => $grid_class,
      '#hours_class' => $hours_class,
      '#current_date' => $current_date,
      '#week_date' => $week_date,
      '#is_mobile' => $is_mobile,
      '#shady_grove_url' => $blockConfig['shady_grove_url'],
      '#all_libraries_url' => $blockConfig['all_libraries_url'],
      '#cache' => [
        'max-age' => 3600,
      ]
    ];
  }

  function sortLocationsHeirarchy($hours) {
    $children = [];
    foreach ($hours as $key => $loc) {
      if (!empty($loc['parent_lid'])) {
        $loc['name'] = '|chev| ' . $loc['name'];
        $children[$loc['parent_lid']][] = $loc;
        unset($hours[$key]);
      }
    }
    $output = [];
    foreach ($hours as $loc) {
      $plid = $loc['lid'];
      $output[] = $loc;
      if (!empty($children[$plid])) {
        foreach ($children[$plid] as $child) {
          $output[] = $child;
        }
      }
    }
    return $output;
  }

  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $config = $this->getConfiguration();
    $this->configHelper = LibCalSettingsHelper::getInstance();

    $form['libraries'] = [
      '#type' => 'select',
      '#title' => t('Libraries'),
      '#default_value' =>  isset($config['libraries']) ? explode(',',$config['libraries']) : array(),
      '#required' => TRUE,
      '#options' => $this->getLibrariesOptions(),
      '#multiple' => TRUE,
    ];
    $form['all_libraries_url'] = [
      '#type' => 'textfield',
      '#title' => t('All Libraries URL'),
      '#default_value' =>  isset($config['all_libraries_url']) ? $config['all_libraries_url'] : null,
    ];
    $form['shady_grove_url'] = [
      '#type' => 'textfield',
      '#title' => t('Shady Grove Hours URL'),
      '#default_value' =>  isset($config['shady_grove_url']) ? $config['shady_grove_url'] : null,
    ];
    $display_types = ['today' => t('Today'), 'weekly' => t('Weekly'), 'utility_nav' => t('Utility Nav')];
    $form['display_type'] = [
      '#type' => 'select',
      '#title' => t('Display Type'),
      '#default_value' => isset($config['display_type']) ? $config['display_type'] : null,
      '#required' => TRUE,
      '#options' => $display_types,
    ];
    $form['is_mobile'] = [
      '#type' => 'checkbox',
      '#title' => t('Is Mobile Block?'),
      '#description' => t('Note: Only affects Utility Nav displays. This option is otherwise ignored.'),
      '#default_value' => isset($config['is_mobile']) ? $config['is_mobile'] : NULL,
    ];
    $form['date_display'] = [
      '#type' => 'checkbox',
      '#title' => t('Show current/weekly date?'),
      '#default_value' => isset($config['date_display']) ? $config['date_display'] : NULL,
    ];
    return $form;
  }

  public function getLibrariesOptions() {
    $vid = 'library_locations';
    $terms =\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree($vid);
    $term_data = [];
    foreach ($terms as $t) {
      $term = Term::load($t->tid);
      if (!empty($term->get('field_libcal_location_id'))) {
        $libcal = $term->get('field_libcal_location_id')->value;
        if ($libcal != null) {
          $term_data[$libcal] = $term->getName();
        }
      }
    }
    dsm($term_data);
    return $term_data;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $libraries = $form_state->getValue('libraries');

    // the api wants a comma-seperated string.
    $libraries = implode(',', $libraries);
    $this->setConfigurationValue('libraries', $libraries);
    $this->setConfigurationValue('shady_grove_url', $form_state->getValue('shady_grove_url'));
    $this->setConfigurationValue('all_libraries_url', $form_state->getValue('all_libraries_url'));
    $this->setConfigurationValue('branch_suffix', $form_state->getValue('branch_suffix'));
    $this->setConfigurationValue('weekly_display', $form_state->getValue('weekly_display'));
    $this->setConfigurationValue('grid_display', $form_state->getValue('grid_display'));
    $this->setConfigurationValue('date_display', $form_state->getValue('date_display'));
    $this->setConfigurationValue('display_type', $form_state->getValue('display_type'));
    $this->setConfigurationValue('is_mobile', $form_state->getValue('is_mobile'));
  }
}
