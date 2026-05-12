<?php

declare(strict_types=1);

namespace Drupal\df_searchstax\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for SearchStax frontend settings.
 */
final class SearchstaxSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'df_searchstax_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['df_searchstax.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('df_searchstax.settings');

    $form['help'] = [
      '#type' => 'item',
      '#markup' => $this->t('Use this form for non-sensitive defaults. For production secrets (tokens, API keys), prefer <code>$settings["df_searchstax"]</code> overrides in settings.php.'),
    ];

    $form['endpoints'] = [
      '#type' => 'details',
      '#title' => $this->t('Endpoints'),
      '#open' => TRUE,
    ];
    $form['endpoints']['search_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Select API URL'),
      '#default_value' => $config->get('search_url'),
      '#required' => TRUE,
    ];
    $form['endpoints']['suggester_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Autosuggest API URL'),
      '#default_value' => $config->get('suggester_url'),
      '#required' => TRUE,
    ];
    $form['endpoints']['related_searches_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Related searches API URL'),
      '#default_value' => $config->get('related_searches_url'),
    ];
    $form['endpoints']['popular_searches_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Popular searches API URL'),
      '#default_value' => $config->get('popular_searches_url'),
    ];
    $form['endpoints']['question_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Search AI answer API URL'),
      '#default_value' => $config->get('question_url'),
      '#description' => $this->t('Leave blank if Search AI answers are not enabled for this app.'),
    ];
    $form['endpoints']['analytics_base_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Analytics base URL'),
      '#default_value' => $config->get('analytics_base_url'),
      '#required' => TRUE,
    ];
    $form['endpoints']['analytics_src'] = [
      '#type' => 'url',
      '#title' => $this->t('Analytics script URL'),
      '#default_value' => $config->get('analytics_src'),
      '#required' => TRUE,
    ];
    $form['endpoints']['geocoding_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Geocoding base URL'),
      '#default_value' => $config->get('geocoding_url'),
    ];

    $form['auth'] = [
      '#type' => 'details',
      '#title' => $this->t('Auth and keys'),
      '#open' => TRUE,
    ];
    $form['auth']['auth_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Search auth type'),
      '#options' => [
        'token' => $this->t('Token'),
      ],
      '#default_value' => $config->get('auth_type') ?: 'token',
    ];
    $form['auth']['search_auth'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search auth token'),
      '#default_value' => $config->get('search_auth'),
    ];
    $form['auth']['track_api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Analytics tracking key'),
      '#default_value' => $config->get('track_api_key'),
    ];
    $form['auth']['related_searches_api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Discovery API key'),
      '#default_value' => $config->get('related_searches_api_key'),
      '#description' => $this->t('Used by related/popular/geocoding APIs.'),
    ];

    $form['app'] = [
      '#type' => 'details',
      '#title' => $this->t('App settings'),
      '#open' => TRUE,
    ];
    $form['app']['language'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Language'),
      '#default_value' => $config->get('language'),
      '#required' => TRUE,
      '#size' => 8,
    ];
    $form['app']['app_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('SearchStax app ID'),
      '#default_value' => $config->get('app_id'),
      '#required' => TRUE,
      '#size' => 12,
    ];
    $form['app']['country_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Country code'),
      '#default_value' => $config->get('country_code'),
      '#size' => 8,
    ];
    $form['app']['results_render_method'] = [
      '#type' => 'select',
      '#title' => $this->t('Results rendering'),
      '#options' => [
        'pagination' => $this->t('Pagination'),
        'infiniteScroll' => $this->t('Infinite scroll'),
      ],
      '#default_value' => $config->get('results_render_method'),
    ];
    $form['app']['faceting_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Faceting type'),
      '#options' => [
        'and' => $this->t('AND'),
        'or' => $this->t('OR'),
        'showUnavailable' => $this->t('Show unavailable'),
        'tabs' => $this->t('Tabs'),
      ],
      '#default_value' => $config->get('faceting_type'),
    ];
    $form['app']['facets_items_per_page_desktop'] = [
      '#type' => 'number',
      '#title' => $this->t('Facet items per page (desktop)'),
      '#default_value' => $config->get('facets_items_per_page_desktop'),
      '#min' => 1,
      '#step' => 1,
    ];
    $form['app']['facets_items_per_page_mobile'] = [
      '#type' => 'number',
      '#title' => $this->t('Facet items per page (mobile)'),
      '#default_value' => $config->get('facets_items_per_page_mobile'),
      '#min' => 1,
      '#step' => 1,
    ];

    $features = (array) $config->get('features');
    $form['features'] = [
      '#type' => 'details',
      '#title' => $this->t('Feature toggles'),
      '#open' => TRUE,
    ];
    $form['features']['features'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Enabled widgets'),
      '#options' => [
        'facets' => $this->t('Facets'),
        'sorting' => $this->t('Sorting'),
        'related_searches' => $this->t('Related searches'),
        'popular_searches' => $this->t('Popular searches'),
        'external_promotions' => $this->t('External promotions'),
        'answer_widget' => $this->t('Smart answers'),
        'search_feedback' => $this->t('Search feedback line'),
        'feedback_widget' => $this->t('Feedback modal widget'),
        'location_search' => $this->t('Location search'),
        'view_style_toggle' => $this->t('Grid/list style toggle'),
      ],
      '#default_value' => array_keys(array_filter($features)),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $selected_features = array_values(array_filter($form_state->getValue('features')));
    $feature_flags = [];
    foreach ([
      'facets',
      'sorting',
      'related_searches',
      'popular_searches',
      'external_promotions',
      'answer_widget',
      'search_feedback',
      'feedback_widget',
      'location_search',
      'view_style_toggle',
    ] as $feature) {
      $feature_flags[$feature] = in_array($feature, $selected_features, TRUE);
    }

    $this->configFactory()->getEditable('df_searchstax.settings')
      ->set('search_url', $form_state->getValue('search_url'))
      ->set('suggester_url', $form_state->getValue('suggester_url'))
      ->set('related_searches_url', $form_state->getValue('related_searches_url'))
      ->set('popular_searches_url', $form_state->getValue('popular_searches_url'))
      ->set('question_url', $form_state->getValue('question_url'))
      ->set('analytics_base_url', $form_state->getValue('analytics_base_url'))
      ->set('analytics_src', $form_state->getValue('analytics_src'))
      ->set('geocoding_url', $form_state->getValue('geocoding_url'))
      ->set('auth_type', $form_state->getValue('auth_type'))
      ->set('search_auth', $form_state->getValue('search_auth'))
      ->set('track_api_key', $form_state->getValue('track_api_key'))
      ->set('related_searches_api_key', $form_state->getValue('related_searches_api_key'))
      ->set('language', $form_state->getValue('language'))
      ->set('app_id', $form_state->getValue('app_id'))
      ->set('country_code', $form_state->getValue('country_code'))
      ->set('results_render_method', $form_state->getValue('results_render_method'))
      ->set('faceting_type', $form_state->getValue('faceting_type'))
      ->set('facets_items_per_page_desktop', (int) $form_state->getValue('facets_items_per_page_desktop'))
      ->set('facets_items_per_page_mobile', (int) $form_state->getValue('facets_items_per_page_mobile'))
      ->set('features', $feature_flags)
      ->save();

    parent::submitForm($form, $form_state);
  }

}
