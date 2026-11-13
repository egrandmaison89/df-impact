<?php

declare(strict_types=1);

namespace Drupal\df_editor\Form;

use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Trusted render callback for node-form validation messages.
 */
final class EditorFormValidation implements TrustedCallbackInterface {

  /**
   * Shows required-field failures on the form.
   */
  public static function preRender(array $element): array {
    if (empty($element['#df_editor_inline_errors'])) {
      return $element;
    }
    df_editor_validation_prepare($element);
    $errors = df_editor_validation_error_list($element);
    if ($errors === []) {
      return $element;
    }
    $element['df_editor_validation_errors'] = [
      '#type' => 'container',
      '#weight' => -1000,
      '#attributes' => [
        'class' => ['df-editor-validation-errors', 'messages', 'messages--error'],
        'role' => 'alert',
      ],
      'heading' => [
        '#markup' => '<h2 class="df-editor-validation-errors__title">' . t('This page was not saved') . '</h2>',
      ],
      'list' => [
        '#theme' => 'item_list',
        '#items' => $errors,
      ],
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['preRender'];
  }

}
