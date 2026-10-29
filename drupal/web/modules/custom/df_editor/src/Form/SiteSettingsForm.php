<?php

declare(strict_types=1);

namespace Drupal\df_editor\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Header / donate / logo settings for site administrators.
 */
final class SiteSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'df_editor_site_settings';
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
    $form['donate_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Donate button URL'),
      '#description' => $this->t('Jimmy Fund donation link used in the header Donate button (opens in a new tab).'),
      '#default_value' => $config->get('donate_url'),
      '#maxlength' => 2048,
      '#required' => TRUE,
    ];
    $form['logo_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Header logo URL'),
      '#description' => $this->t('Primary logo image URL (WordPress CDN URLs are supported). Leave blank to keep the migrated default.'),
      '#default_value' => $config->get('logo_url'),
      '#maxlength' => 2048,
    ];
    $form['logo_srcset'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Header logo srcset'),
      '#description' => $this->t('Optional responsive srcset string for the logo <img>. One “url width” pair per line or comma-separated.'),
      '#default_value' => $config->get('logo_srcset'),
      '#rows' => 3,
    ];
    $form['header_primary_menu'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Main navigation menu machine name'),
      '#description' => $this->t('Menu used for top-level header links (excluding the Issues flyout). Manage links at Structure → Menus. Default: header-primary.'),
      '#default_value' => $config->get('header_primary_menu') ?: 'header-primary',
      '#required' => TRUE,
    ];
    $form['translate_note'] = [
      '#type' => 'item',
      '#title' => $this->t('Translate widget'),
      '#markup' => $this->t('The GTranslate dropdown matches WordPress markup and is fixed in the theme for visual parity. Contact engineering if languages or placement must change.'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('df_editor.settings')
      ->set('donate_url', $form_state->getValue('donate_url'))
      ->set('logo_url', $form_state->getValue('logo_url'))
      ->set('logo_srcset', $form_state->getValue('logo_srcset'))
      ->set('header_primary_menu', trim((string) $form_state->getValue('header_primary_menu')))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
