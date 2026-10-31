<?php

declare(strict_types=1);

namespace Drupal\df_editor;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Url;
use Drupal\media\MediaInterface;

/**
 * Builds editable header/footer variables for the public theme.
 */
final class SiteChrome {

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly MenuLinkTreeInterface $menuLinkTree,
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
  ) {}

  /**
   * Overrides df_impact page preprocess variables from config and menus.
   *
   * @param array<string, mixed> $variables
   */
  public function applyPreprocessPage(array &$variables): void {
    $config = $this->configFactory->get('df_editor.settings');
    $donate = trim((string) $config->get('donate_url'));
    if ($donate !== '') {
      $variables['donate_url'] = $donate;
    }
    $donate_label = trim((string) $config->get('donate_button_label'));
    if ($donate_label !== '') {
      $variables['donate_button_label'] = $donate_label;
    }
    $see_all = trim((string) $config->get('header_issues_see_all_title'));
    if ($see_all !== '') {
      $variables['header_issues_see_all_title'] = $see_all;
    }
    $disclaimer = trim((string) $config->get('gtranslate_disclaimer_text'));
    if ($disclaimer !== '') {
      $variables['gtranslate_disclaimer_text'] = $disclaimer;
    }

    $logo = trim((string) $config->get('logo_url'));
    if ($logo !== '') {
      $variables['wp_logo_url'] = $logo;
      $srcset = trim((string) $config->get('logo_srcset'));
      if ($srcset !== '') {
        $variables['wp_logo_srcset'] = $srcset;
      }
    }

    $menu_id = (string) ($config->get('header_primary_menu') ?: 'header-primary');
    $links = $this->buildMenuLinks($menu_id);
    if ($links !== []) {
      $variables['header_primary_links'] = $links;
    }

    $footer = $this->buildFooterVariables($config);
    foreach ($footer as $key => $value) {
      $variables[$key] = $value;
    }
    $uploads_base = (string) ($variables['wp_uploads_base'] ?? '');
    $badges = $this->buildFooterBadges($config, $uploads_base);
    if ($badges !== NULL) {
      $variables['footer_badges'] = $badges;
    }
  }

  /**
   * @return array<int, array{title: string, url: string}>
   */
  private function buildMenuLinks(string $menu_id): array {
    $parameters = new MenuTreeParameters();
    $parameters->setMaxDepth(1);
    $tree = $this->menuLinkTree->load($menu_id, $parameters);
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $tree = $this->menuLinkTree->transform($tree, $manipulators);
    $links = [];
    foreach ($tree as $element) {
      if (empty($element->link)) {
        continue;
      }
      $url = $element->link->getUrlObject();
      if (!$url instanceof Url) {
        continue;
      }
      $links[] = [
        'title' => (string) $element->link->getTitle(),
        'url' => $url->toString(),
      ];
    }
    return $links;
  }

  /**
   * @return array<string, mixed>
   */
  private function buildFooterVariables(\Drupal\Core\Config\Config $config): array {
    $out = [];
    $about_links = [];
    foreach (['about_impact', 'browse_issues', 'jimmy_fund_events'] as $key) {
      $title = trim((string) $config->get("footer_link_{$key}_title"));
      $url = trim((string) $config->get("footer_link_{$key}_url"));
      if ($title !== '' && $url !== '') {
        $about_links[] = ['title' => $title, 'url' => $url];
      }
    }
    if ($about_links !== []) {
      $out['footer_about_links'] = $about_links;
    }
    $address = trim((string) $config->get('footer_address_html'));
    if ($address !== '') {
      $out['footer_address_html'] = $address;
    }
    $disclaimer = trim((string) $config->get('footer_disclaimer_html'));
    if ($disclaimer !== '') {
      $out['footer_disclaimer_html'] = $disclaimer;
    }
    $social = [];
    foreach (['facebook', 'twitter', 'instagram'] as $network) {
      $url = trim((string) $config->get("social_{$network}_url"));
      if ($url !== '') {
        $social[$network] = $url;
      }
    }
    if ($social !== []) {
      $out['footer_social'] = $social;
    }
    $dei = trim((string) $config->get('footer_dei_html'));
    if ($dei !== '') {
      $out['footer_dei_html'] = $dei;
    }
    $copyright = trim((string) $config->get('footer_copyright_html'));
    if ($copyright !== '') {
      $out['footer_copyright_html'] = $copyright;
    }
    return $out;
  }

  /**
   * Footer trust badges; only returned when at least one custom media is configured.
   *
   * @return array<int, array{src: string, width: int, height: int, alt: string, link: ?string}>|null
   */
  private function buildFooterBadges(\Drupal\Core\Config\Config $config, string $uploads_base): ?array {
    $specs = [
      [
        'config_key' => 'footer_badge_us_news_media_id',
        'file' => 'USNewsBadge2627_146.png',
        'width' => 146,
        'height' => 143,
        'alt' => 'U.S. News & World Report’s Best Hospital’s badge',
        'link' => 'https://health.usnews.com/best-hospitals/area/ma/dana-farber-cancer-institute-6140583',
        'img_style' => 'width:73px;height:auto',
      ],
      [
        'config_key' => 'footer_badge_us_news_children_media_id',
        'file' => 'USNewsChildrens_Footer.png',
        'width' => 834,
        'height' => 967,
        'alt' => '',
        'link' => NULL,
        'img_style' => 'width:73px;height:auto',
      ],
      [
        'config_key' => 'footer_badge_charity_navigator_media_id',
        'file' => 'Charity-Navigator-Logo-2024.png',
        'width' => 140,
        'height' => 140,
        'alt' => 'Charity Navigator Four-Star Hospital',
        'link' => 'https://www.charitynavigator.org/ein/042263040',
        'img_style' => 'width:70px;height:auto',
      ],
    ];
    $any_custom = FALSE;
    foreach ($specs as $spec) {
      if ((int) $config->get($spec['config_key']) > 0) {
        $any_custom = TRUE;
        break;
      }
    }
    if (!$any_custom) {
      return NULL;
    }
    $badges = [];
    foreach ($specs as $spec) {
      $media_id = (int) $config->get($spec['config_key']);
      $src = $uploads_base !== '' ? $uploads_base . $spec['file'] : '/sites/default/files/wp-images/' . $spec['file'];
      $alt = $spec['alt'];
      if ($media_id > 0) {
        $resolved = $this->resolveImageMedia($media_id);
        if ($resolved !== NULL) {
          $src = $resolved['src'];
          if ($resolved['alt'] !== '') {
            $alt = $resolved['alt'];
          }
        }
      }
      $badges[] = [
        'src' => $src,
        'width' => $spec['width'],
        'height' => $spec['height'],
        'alt' => $alt,
        'link' => $spec['link'],
        'img_style' => $spec['img_style'],
      ];
    }
    return $badges;
  }

  /**
   * @return array{src: string, alt: string}|null
   */
  private function resolveImageMedia(int $media_id): ?array {
    $media = $this->entityTypeManager->getStorage('media')->load($media_id);
    if (!$media instanceof MediaInterface || $media->bundle() !== 'image') {
      return NULL;
    }
    if (!$media->hasField('field_media_image') || $media->get('field_media_image')->isEmpty()) {
      return NULL;
    }
    $file = $media->get('field_media_image')->entity;
    if (!$file) {
      return NULL;
    }
    $alt = (string) $media->get('field_media_image')->alt;
    return [
      'src' => $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri()),
      'alt' => $alt,
    ];
  }

}
