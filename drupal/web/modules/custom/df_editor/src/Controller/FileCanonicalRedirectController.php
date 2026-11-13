<?php

declare(strict_types=1);

namespace Drupal\df_editor\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\file\FileInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Files have no core canonical page; delete-form cancel links need one.
 */
final class FileCanonicalRedirectController extends ControllerBase {

  public function toFileList(FileInterface $file): RedirectResponse {
    return new RedirectResponse(Url::fromRoute('view.files.page_1')->toString());
  }

}
