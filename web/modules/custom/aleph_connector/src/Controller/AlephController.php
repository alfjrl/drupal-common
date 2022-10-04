<?php

/**
 * @file
 * Definition of Drupal\aleph_connector\Controller\AlephController
 */

namespace Drupal\aleph_connector\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Controller class for Aleph services.
 */
class AlephController extends ControllerBase implements TrustedCallbackInterface {

  protected $config;

  public function __construct() {
    $this->config = \Drupal::config('aleph_connector.settings');
  }

  public function getEquipmentData($bibnums) {
    $post_data = [];
    $i = 0;
    foreach ($bibnums as $bibnum) {
      $post_data[] = "bib=" . $bibnum . "|" . "Equipment" . $i;
      $i++;
    }
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $this->getAlephBase() . $this->getEquipmentEndpoint());
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, implode('&', $post_data));
    $output = curl_exec($curl);
    if (!curl_errno($curl)) {
      if (curl_getinfo($curl, CURLINFO_HTTP_CODE) != 200) {
        \Drupal::messenger()->addError(t('Aleph connection error. Non-200 response.'));
        return FALSE; 
      }
    } else {
      \Drupal::messenger()->addError(t('Aleph connection error. Non-200 response.'));
      return FALSE;
    }

    $xml = simplexml_load_string($output, "SimpleXMLElement", LIBXML_NOCDATA);
    $rows = $xml->xpath('//tr');
    $aleph_data = [];
    foreach ($rows as $row) {
      $vals = $row->xpath('//td[@class]');
      $row_data = [];
      $tmp_sysnum = null;
      foreach ($row->td as $td) {
        if ((string)$td->attributes()['class'] == 'sysnum') {
          $tmp_sysnum = (string)$td;
        }
        if ((string)$td->attributes()['class'] == 'available') {
          $row_data['available'] = (int)$td;
        }
        if ((string)$td->attributes()['class'] == 'mindue') {
          $row_data['mindue'] = (string)$td;
        }
      }
      if ($tmp_sysnum != null) {
        $aleph_data[$tmp_sysnum] = $row_data;
      }
    }
    curl_close($curl);
    if (count($aleph_data) > 0) {
      return $aleph_data;
    }
    return FALSE;
  }

  public function getBibnumData($bibnums) {
    if (!$equipment_data = $this->getEquipmentData($bibnums)) {
      // Log this
      return FALSE;
    }
    return $equipment_data;
  }

  /**
   * Getters
   *
   * Consider spawning this off into a util class
   */
  public function getQueryField() {
    return $this->config->get('equipment_query_field');
  }

  public function getAlephBase() {
    return $this->config->get('aleph_base');
  }

  public function getEquipmentEndpoint() {
    return $this->config->get('equipment_endpoint');
  }

  /**
   * {@inheritDoc}
   */
  public static function trustedCallbacks() {
    return ['getBibnumData'];
  }
}