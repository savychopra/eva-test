<?php

namespace Drupal\gotowebinar_sync\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;

/**
 * Class GotoWebinarSyncAdminForm.
 */
class GotoWebinarSyncAdminForm extends ConfigFormBase {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\Core\Entity\EntityFieldManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Authorize link for gotowebinar api.
   *
   * @var string
   */
  private $authorizeLink = "https://api.getgo.com/oauth/v2/authorize";

  /**
   * Constructs a new GotoWebinarSyncAdminForm object.
   */
  public function __construct(
    ConfigFactoryInterface $config,
    EntityTypeManagerInterface $entity_type_manager,
    EntityFieldManagerInterface $entity_field_manager
    ) {

    parent::__construct($config);
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'gotowebinar_sync.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'gotowebinar_sync_admin_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('gotowebinar_sync.settings');
    $form['debug'] = [
      '#type' => 'details',
      '#title' => $this->t('Debug'),
    ];
    $form['debug']['debug_requests'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Debugging Information'),
      '#description' => $this->t('Turn this on if you need to debug GotoWebinar API requests.
        Note: this will log sensitive API information and should only be used during development.'),
      '#default_value' => $config->get('debug_requests'),
    ];
    $form['site_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Website URL'),
      '#description' => $this->t('The URL of your site to use for GoToWebinar webhooks (https is recommended).'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('site_url') ?? $this->getRequest()->getSchemeAndHttpHost(),
    ];

    if (empty($config->get('site_url'))) {
      return parent::buildForm($form, $form_state);
    }

    $form['create_app'] = [
      '#markup' => $this->t('You can now log into the GotoWebinar Developer site and create an app. Make sure to use <strong>@url</strong> for the application redirect url.',
        ['@url' => $config->get('site_url') . '/___gotowebinar_oauth']),
    ];
    $form['consumer_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('GotoWebinar Consumer Key'),
      '#description' => $this->t('Enter your GotoWebinar Consumer Key from the GotoWebinar developer website.'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('consumer_key'),
    ];
    $form['consumer_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('GotoWebinar Consumer Secret'),
      '#description' => $this->t('Enter your GotoWebinar Consumer Secret from the GotoWebinar developer website.'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('consumer_secret'),
    ];

    if (empty($config->get('consumer_key'))) {
      return parent::buildForm($form, $form_state);
    }
    elseif (empty($config->get('secret_key'))) {
      $auth_url = format_string("@authorize_link?client_id=@consumer_key&response_type=code&redirect_uri=@redirect",
        [
          '@authorize_link' => $this->authorizeLink,
          '@consumer_key' => $config->get('consumer_key'),
          '@redirect' => urlencode($config->get('site_url') . '/___gotowebinar_oauth'),
        ]);
      $form['auth'] = [
        '#markup' => $this->t('You need to <a href="@auth">Authenticate your GotoWebinar Account</a>', ['@auth' => $auth_url]),
      ];

      return parent::buildForm($form, $form_state);
    }

    $content_type = $config->get('sync_content_type');
    $form['sync_content_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Sync Content Type'),
      '#description' => $this->t('Select the content type to use to sync GotoWebinar webinar data.'),
      '#options' => $this->getNodeTypes(),
      '#size' => 1,
      '#default_value' => $content_type,
    ];

    if (!empty($content_type)) {
      $fields = $this->getContentEntityFields($content_type);
      $form['sync_fields'] = [
        '#type' => 'details',
        '#title' => $this->t('GotoWebinar Sync Fields'),
        '#description' => $this->t('Map the fields here'),
      ];
      $form['sync_fields']['group_webinars'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Group Webinar series into single content item'),
        '#description' => $this->t('This will attempt to group a webinar series into a single content item.
          It requires the webinar key and webinar date fields to allow multiple items.
          This is useful if you have other fields on the content type that you manage within Drupal.'),
        '#default_value' => $config->get('group_webinars'),
      ];
      $form['sync_fields']['published'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Immediately publish webinars'),
        '#description' => $this->t('Checking this will cause imported webinars to be published.
          Leave this unchecked to import the webinars in an unpublished status.'),
        '#default_value' => $config->get('published'),
      ];
      $form['sync_fields']['webinar_key'] = [
        '#type' => 'select',
        '#title' => $this->t('Webinar Key'),
        '#description' => $this->t('Select the field to store the Webinar Key.'),
        '#options' => $fields,
        '#default_value' => $config->get('webinar_key'),
        '#required' => TRUE,
      ];
      $form['sync_fields']['webinar_title'] = [
        '#type' => 'select',
        '#title' => $this->t('Webinar Title'),
        '#description' => $this->t('Select the field to store the Webinar Title.'),
        '#options' => $fields,
        '#default_value' => $config->get('webinar_title'),
        '#required' => TRUE,
      ];
      $form['sync_fields']['webinar_url'] = [
        '#type' => 'select',
        '#title' => $this->t('Webinar URL'),
        '#description' => $this->t('Select the Link field to store the Webinar URL.'),
        '#options' => $fields,
        '#default_value' => $config->get('webinar_url'),
        '#required' => TRUE,
      ];
      $form['sync_fields']['webinar_date'] = [
        '#type' => 'select',
        '#title' => $this->t('Webinar Date/Time field'),
        '#description' => $this->t('Select the field to store the Webinar date/time. This should be a multivalue date field with range.'),
        '#options' => $fields,
        '#default_value' => $config->get('webinar_date'),
        '#required' => TRUE,
      ];
      $form['sync_fields']['webinar_description'] = [
        '#type' => 'select',
        '#title' => $this->t('Webinar Description'),
        '#description' => $this->t('Select the field to store the Webinar Description.'),
        '#options' => $fields,
        '#default_value' => $config->get('webinar_description'),
      ];
      $form['sync_fields']['experience_type'] = [
        '#type' => 'select',
        '#title' => $this->t('Experience Type'),
        '#description' => $this->t('Select the field to store the Experience Type (CLASSIC, SIMULIVE, or BROADCAST).'),
        '#options' => $fields,
        '#default_value' => $config->get('experience_type'),
      ];
      $form['sync_fields']['recurrence_type'] = [
        '#type' => 'select',
        '#title' => $this->t('Recurrence Type'),
        '#description' => $this->t('Select the field to store the Recurrence Type (single_session, series or sequence).'),
        '#options' => $fields,
        '#default_value' => $config->get('recurrence_type'),
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('gotowebinar_sync.settings')
      ->set('debug_requests', $form_state->getValue('debug_requests'))
      ->set('consumer_key', $form_state->getValue('consumer_key'))
      ->set('consumer_secret', $form_state->getValue('consumer_secret'))
      ->set('site_url', $form_state->getValue('site_url'))
      ->set('sync_content_type', $form_state->getValue('sync_content_type'))
      ->set('group_webinars', $form_state->getValue('group_webinars'))
      ->set('published', $form_state->getValue('published'))
      ->set('webinar_key', $form_state->getValue('webinar_key'))
      ->set('webinar_title', $form_state->getValue('webinar_title'))
      ->set('webinar_url', $form_state->getValue('webinar_url'))
      ->set('webinar_description', $form_state->getValue('webinar_description'))
      ->set('experience_type', $form_state->getValue('experience_type'))
      ->set('recurrence_type', $form_state->getValue('recurrence_type'))
      ->set('webinar_date', $form_state->getValue('webinar_date'))
      ->save();
  }

  /**
   * Gets a list of all the defined node typees in the system.
   *
   * @return array
   *   An array of node type definitions.
   */
  private function getNodeTypes() {
    $node_type_list = [];

    $node_types = $this->entityTypeManager
      ->getStorage('node_type')
      ->loadMultiple();

    foreach ($node_types as $node_type) {
      $node_type_list[$node_type->id()] = $node_type->label();
    }
    return $node_type_list;
  }

  /**
   * Gets a list of all the defined fields for a content type.
   *
   * @param string $content_type
   *   The content type used to look up the fields.
   *
   * @return array
   *   An array of fields for a content type.
   */
  private function getContentEntityFields(string $content_type) {
    $content_entity_fields = [];
    $content_fields = $this->entityFieldManager->getFieldDefinitions('node', $content_type);

    foreach ($content_fields as $field_id => $field) {
      $content_entity_fields[$field_id] = $field->getLabel();
    }

    return ['' => $this->t('- Select -')] + $content_entity_fields;
  }

}
