<?php

declare(strict_types=1);

namespace Drupal\df_editor\Form;

use Drupal\Core\Form\FormErrorHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Keeps node-form validation errors out of the page status banner.
 *
 * Inline field errors and the form summary still render. Other messages,
 * including admin security warnings, stay in the banner.
 */
final class EditorFormErrorHandler implements FormErrorHandlerInterface {

  public function __construct(
    private readonly FormErrorHandlerInterface $inner,
    private readonly MessengerInterface $messenger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function handleFormErrors(array &$form, FormStateInterface $form_state) {
    $this->inner->handleFormErrors($form, $form_state);
    if (empty($form['#df_editor_inline_errors']) || !$form_state->getErrors()) {
      return $this;
    }
    $validation = [];
    foreach ($form_state->getErrors() as $error) {
      $validation[] = trim((string) $error);
    }
    $kept = [];
    foreach ($this->messenger->messagesByType(MessengerInterface::TYPE_ERROR) as $message) {
      $text = trim(strip_tags((string) $message));
      if (!in_array($text, $validation, TRUE) && !in_array(trim((string) $message), $validation, TRUE)) {
        $kept[] = $message;
      }
    }
    $this->messenger->deleteByType(MessengerInterface::TYPE_ERROR);
    foreach ($kept as $message) {
      $this->messenger->addError($message);
    }
    return $this;
  }

}
