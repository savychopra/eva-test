<?php

namespace Drupal\zoomapi\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\ConfigFormBase as DrupalConfigFormBase;

/**
 * Config form base for our config form.
 */
abstract class ConfigFormBase extends DrupalConfigFormBase {

  const CONFIG_NAME = 'zoomapi.settings';

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames() {
    return [self::CONFIG_NAME];
  }

  /**
   * Returns this modules configuration object.
   */
  protected function getConfig() {
    return $this->config(self::CONFIG_NAME);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->getConfig();
    $values = $form_state->getValues();
    $config->set('base_uri', $values['base_uri']);
    $config->set('api_key', $values['api_key']);
    $config->set('api_secret', $values['api_secret']);
    $config->set('webhook_verification_token', $values['webhook_verification_token']);
    $config->save();
  }

}
