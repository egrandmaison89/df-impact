<?php

declare(strict_types=1);

namespace Drupal\df_editor\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\df_editor\CurrentIssueNav;
use Drupal\df_editor\IssuePublisher;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Site-admin homepage hero: optional title/link override + issue video shortcut.
 */
final class HomepageHeroSettingsForm extends ConfigFormBase {

  public function __construct(
    private readonly CurrentIssueNav $currentIssueNav,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('df_editor.current_issue_nav'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'df_editor_homepage_hero_settings';
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
    $latest_nid = function_exists('df_setup_get_latest_issue_nid') ? df_setup_get_latest_issue_nid() : NULL;

    $form['default'] = [
      '#type' => 'details',
      '#title' => $this->t('Default from current issue'),
      '#open' => TRUE,
      '#description' => $this->t('When no override title is set below, the homepage hero heading comes from the newest published issue’s <em>Homepage hero heading</em> field (updates automatically when a new issue is published).'),
    ];
    $issue_edit = $this->currentIssueNav->homepageHeroEditUrl();
    if ($issue_edit) {
      $form['default']['issue_link'] = [
        '#markup' => Link::fromTextAndUrl(
          $this->t('Edit video, poster, and default heading on the current issue (node @nid)', ['@nid' => $latest_nid ?? '']),
          $issue_edit,
        )->toString(),
      ];
    }
    else {
      $form['default']['issue_link'] = [
        '#markup' => $this->t('No published issue yet—publish an issue to set the default hero heading and video.'),
      ];
    }

    $form['override'] = [
      '#type' => 'details',
      '#title' => $this->t('Site-wide override (optional)'),
      '#open' => TRUE,
      '#description' => $this->t('When override title is filled in, it replaces the issue heading on the public homepage until cleared.'),
    ];
    $form['override']['homepage_hero_override_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Override heading'),
      '#maxlength' => 255,
      '#default_value' => $config->get('homepage_hero_override_title'),
    ];
    $form['override']['homepage_hero_override_link'] = [
      '#type' => 'url',
      '#title' => $this->t('Optional heading link'),
      '#description' => $this->t('When set together with an override heading, the homepage hero title becomes a link to this URL.'),
      '#default_value' => $config->get('homepage_hero_override_link'),
      '#maxlength' => 2048,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('df_editor.settings')
      ->set('homepage_hero_override_title', trim((string) $form_state->getValue('homepage_hero_override_title')))
      ->set('homepage_hero_override_link', trim((string) $form_state->getValue('homepage_hero_override_link')))
      ->save();
    df_editor_invalidate_issue_nav_cache();
    parent::submitForm($form, $form_state);
  }

}
