<?php

namespace Drupal\df_site_snippets\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for trusted header/footer HTML snippets.
 */
class SiteSnippetsSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'df_site_snippets_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['df_site_snippets.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('df_site_snippets.settings');

    $form['notice'] = [
      '#type' => 'markup',
      '#prefix' => '<div class="messages messages--warning">',
      '#suffix' => '</div>',
      '#markup' => $this->t('Snippets are output as entered. Treat this form like a code deployment: only highly trusted staff should edit it. Third-party scripts may require Content-Security-Policy updates (for example Seckit).'),
      '#weight' => -10,
    ];

    $form['header_snippets'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Header HTML'),
      '#description' => $this->t('Inserted near the end of the HTML head (after aggregated CSS/JS placeholders). Use for &lt;style&gt;, &lt;script&gt;, &lt;link&gt;, or &lt;meta&gt; tags.'),
      '#default_value' => $config->get('header_snippets') ?? '',
      '#rows' => 14,
    ];

    $form['footer_snippets'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Footer HTML'),
      '#description' => $this->t('Inserted near the end of the page, before Drupal’s bottom JavaScript placeholder. Use for deferred scripts or trailing markup.'),
      '#default_value' => $config->get('footer_snippets') ?? '',
      '#rows' => 14,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('df_site_snippets.settings')
      ->set('header_snippets', $form_state->getValue('header_snippets'))
      ->set('footer_snippets', $form_state->getValue('footer_snippets'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
