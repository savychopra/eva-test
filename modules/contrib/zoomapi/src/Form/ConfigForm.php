<?php

namespace Drupal\zoomapi\Form;

use Drupal\zoomapi\Form\ConfigFormBase as ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * The config form.
 */
class ConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'zoomapi_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->getConfig();

    $form['base_uri'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Zoom API Base URL'),
      '#default_value' => $config->get('base_uri'),
      '#description' => $this->t('Do not inclue trailing slash. Ex. https://api.zoom.us/v2'),
      '#required' => TRUE,
      '#empty_option' => $this->t('- Select -'),
    ];

    $form['api_key'] = [
      '#type' => 'key_select',
      '#title' => $this->t('API Key'),
      '#default_value' => $config->get('api_key'),
      '#description' => $this->t('The Zoom API private key.'),
      '#required' => TRUE,
      '#empty_option' => $this->t('- Select -'),
    ];

    $form['api_secret'] = [
      '#type' => 'key_select',
      '#title' => $this->t('API Secret'),
      '#default_value' => $config->get('api_secret'),
      '#description' => $this->t('The Zoom API secret.'),
      '#required' => TRUE,
      '#empty_option' => $this->t('- Select -'),
    ];

    $form['webhook_verification_token'] = [
      '#type' => 'key_select',
      '#title' => $this->t('Webhook Verification Token'),
      '#empty_option' => $this->t('Not using webhooks'),
      '#default_value' => $config->get('webhook_verification_token'),
      '#description' => $this->t('Use this verification token to validate an event notification request from zoom.us for this app. Provided by Zoom.'),
    ];

    return parent::buildForm($form, $form_state);
  }

}
