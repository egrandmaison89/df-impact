<?php

declare(strict_types=1);

namespace Drupal\df_editor\EventSubscriber;

use Drupal\Core\File\Event\FileUploadSanitizeNameEvent;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Gives each CKEditor inline upload its own filename.
 *
 * Consecutive inserts of the same name (image.jpg, image.jpg) used to land on
 * one file URI, so the second upload replaced the first in the body.
 */
final class EditorUploadUniqueNameSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly RouteMatchInterface $routeMatch,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      FileUploadSanitizeNameEvent::class => ['uniqueInlineUploadName', -100],
    ];
  }

  public function uniqueInlineUploadName(FileUploadSanitizeNameEvent $event): void {
    if ($this->routeMatch->getRouteName() !== 'ckeditor5.upload_image') {
      return;
    }
    $filename = $event->getFilename();
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    $basename = pathinfo($filename, PATHINFO_FILENAME);
    if ($basename === '') {
      return;
    }
    $suffix = bin2hex(random_bytes(4));
    $renamed = $extension !== '' ? $basename . '-' . $suffix . '.' . $extension : $basename . '-' . $suffix;
    $event->setFilename($renamed);
  }

}
