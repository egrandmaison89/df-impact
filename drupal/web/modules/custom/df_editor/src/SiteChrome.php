<?php

declare(strict_types=1);

namespace Drupal\df_editor;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Url;

/**
 * Builds editable header/footer variables for the public theme.
 */
final class SiteChrome {

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly MenuLinkTreeInterface $menuLinkTree,
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
    return $out;
  }

}
