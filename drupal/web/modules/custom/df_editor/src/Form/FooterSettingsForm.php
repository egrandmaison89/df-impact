<?php

declare(strict_types=1);

namespace Drupal\df_editor\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Footer copy and social links for site administrators.
 */
final class FooterSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'df_editor_footer_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['df_editor.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('df_editor.settings');
    $form['about'] = [
      '#type' => 'details',
      '#title' => $this->t('About column links'),
      '#open' => TRUE,
    ];
    $this->linkPair($form['about'], 'about_impact', $config, 'About Impact', '/about');
    $this->linkPair($form['about'], 'browse_issues', $config, 'See All Issues', '/browse-issues');
    $this->linkPair($form['about'], 'jimmy_fund_events', $config, 'Jimmy Fund Events', 'https://www.jimmyfund.org/get-involved/events');

    $form['footer_address_html'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Contact address (HTML allowed)'),
      '#description' => $this->t('Shown in the footer About column. Basic HTML tags such as <br> are supported.'),
      '#default_value' => $config->get('footer_address_html'),
      '#rows' => 4,
    ];
    $form['footer_disclaimer_html'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Patient stories disclaimer (HTML allowed)'),
      '#default_value' => $config->get('footer_disclaimer_html'),
      '#rows' => 4,
    ];
    $form['legal'] = [
      '#type' => 'details',
      '#title' => $this->t('Inclusion, equity, and legal bar'),
      '#open' => FALSE,
    ];
    $form['legal']['footer_dei_html'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Inclusion & equity paragraph (HTML allowed)'),
      '#default_value' => $config->get('footer_dei_html'),
      '#rows' => 4,
    ];
    $form['legal']['footer_copyright_html'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Copyright and legal links (HTML allowed)'),
      '#default_value' => $config->get('footer_copyright_html'),
      '#rows' => 3,
    ];
    $form['social'] = [
      '#type' => 'details',
      '#title' => $this->t('Social links'),
      '#open' => TRUE,
    ];
    foreach (['facebook', 'twitter', 'instagram'] as $network) {
      $form['social']["social_{$network}_url"] = [
        '#type' => 'url',
        '#title' => $this->t('@network URL', ['@network' => ucfirst($network)]),
        '#default_value' => $config->get("social_{$network}_url"),
        '#maxlength' => 2048,
      ];
    }
    return parent::buildForm($form, $form_state);
  }

  /**
   * @param \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig $config
   */
  private function linkPair(array &$container, string $key, $config, string $default_title, string $default_url): void {
    $container["footer_link_{$key}_title"] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link label'),
      '#default_value' => $config->get("footer_link_{$key}_title") ?: $default_title,
    ];
    $container["footer_link_{$key}_url"] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link URL'),
      '#default_value' => $config->get("footer_link_{$key}_url") ?: $default_url,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $editable = $this->config('df_editor.settings');
    foreach (['about_impact', 'browse_issues', 'jimmy_fund_events'] as $key) {
      $editable
        ->set("footer_link_{$key}_title", $form_state->getValue("footer_link_{$key}_title"))
        ->set("footer_link_{$key}_url", $form_state->getValue("footer_link_{$key}_url"));
    }
    $editable
      ->set('footer_address_html', $form_state->getValue('footer_address_html'))
      ->set('footer_disclaimer_html', $form_state->getValue('footer_disclaimer_html'))
      ->set('footer_dei_html', $form_state->getValue('footer_dei_html'))
      ->set('footer_copyright_html', $form_state->getValue('footer_copyright_html'))
      ->set('social_facebook_url', $form_state->getValue('social_facebook_url'))
      ->set('social_twitter_url', $form_state->getValue('social_twitter_url'))
      ->set('social_instagram_url', $form_state->getValue('social_instagram_url'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
