<?php

declare(strict_types=1);

namespace Drupal\df_editor\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\df_editor\CurrentIssueNav;

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
    $form['donate_button_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Donate button label'),
      '#default_value' => $config->get('donate_button_label') ?: 'Donate',
      '#required' => TRUE,
    ];
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
      '#description' => $this->t('Menu used for top-level header links (excluding the Issues flyout). The link titled “@label” always resolves to the newest published issue automatically—its menu URL is ignored on the public site.', ['@label' => CurrentIssueNav::DEFAULT_LABEL]),
      '#default_value' => $config->get('header_primary_menu') ?: 'header-primary',
      '#required' => TRUE,
    ];
    /** @var \Drupal\df_editor\CurrentIssueNav $nav */
    $nav = \Drupal::service('df_editor.current_issue_nav');
    $hero_edit = $nav->homepageHeroEditUrl();
    $latest_nid = function_exists('df_setup_get_latest_issue_nid') ? df_setup_get_latest_issue_nid() : NULL;
    $hero_markup = $hero_edit
      ? Link::fromTextAndUrl($this->t('Edit homepage hero on the current issue'), $hero_edit)->toString()
      : $this->t('No published issue yet—publish an issue first.');
    $form['homepage_hero'] = [
      '#type' => 'details',
      '#title' => $this->t('Homepage hero'),
      '#open' => TRUE,
      '#description' => $this->t('The homepage cover video, poster, and heading come from the newest published issue (currently node @nid).', [
        '@nid' => $latest_nid ?? $this->t('none'),
      ]),
    ];
    $form['homepage_hero']['link'] = [
      '#markup' => $hero_markup,
    ];
    $form['homepage_hero']['shortcut'] = [
      '#type' => 'link',
      '#title' => $this->t('Open homepage hero fields'),
      '#url' => Url::fromRoute('df_editor.homepage_hero'),
      '#attributes' => ['class' => ['button', 'button--primary']],
    ];
    $form['header_issues'] = [
      '#type' => 'details',
      '#title' => $this->t('Issues menu (header)'),
      '#open' => FALSE,
      '#description' => $this->t('Individual issue links are driven by published issues. You can edit the “see all issues” label below.'),
    ];
    $form['header_issues']['header_issues_see_all_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('“See all issues” link label'),
      '#default_value' => $config->get('header_issues_see_all_title') ?: 'See All Issues',
      '#required' => TRUE,
    ];
    $form['gtranslate_disclaimer_text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Translate widget disclaimer'),
      '#description' => $this->t('Info icon popup next to the language selector in the header (desktop and mobile).'),
      '#default_value' => $config->get('gtranslate_disclaimer_text'),
      '#rows' => 4,
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('df_editor.settings')
      ->set('donate_button_label', trim((string) $form_state->getValue('donate_button_label')))
      ->set('donate_url', $form_state->getValue('donate_url'))
      ->set('logo_url', $form_state->getValue('logo_url'))
      ->set('logo_srcset', $form_state->getValue('logo_srcset'))
      ->set('header_primary_menu', trim((string) $form_state->getValue('header_primary_menu')))
      ->set('header_issues_see_all_title', trim((string) $form_state->getValue('header_issues_see_all_title')))
      ->set('gtranslate_disclaimer_text', $form_state->getValue('gtranslate_disclaimer_text'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
