<?php

declare(strict_types=1);

namespace Drupal\Tests\df_metatag\Unit;

use Drupal\df_metatag\PublicUrl;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\df_metatag\PublicUrl
 */
final class PublicUrlTest extends TestCase {

  public function testWordpressHosts(): void {
    $this->assertTrue(PublicUrl::isWordpressHost('danafarberimpact.org'));
    $this->assertTrue(PublicUrl::isWordpressHost('i' . '0.wp.com'));
    $this->assertTrue(PublicUrl::isWordpressHost('stats.wp.com'));
    $this->assertFalse(PublicUrl::isWordpressHost('cdn.gtranslate.net'));
    $this->assertFalse(PublicUrl::isWordpressHost('danafarber.jimmyfund.org'));
  }

  public function testRewriteKeepsThePathOnThePublicOrigin(): void {
    $url = 'https://danafarberimpact.org/homepage/';
    $this->assertSame(
      'https://impact.example/homepage/',
      PublicUrl::rewrite($url, 'https://impact.example'),
    );
  }

  public function testPhotonUrlDropsTheWordpressHost(): void {
    $url = 'https://i' . '0.wp.com/danafarberimpact.org/wp-content/uploads/2024/01/a.jpg';
    $this->assertSame(
      'https://impact.example/wp-content/uploads/2024/01/a.jpg',
      PublicUrl::rewrite($url, 'https://impact.example/'),
    );
  }

  public function testThirdPartyHostsStay(): void {
    $url = 'https://cdn.gtranslate.net/flags/svg/en.svg';
    $this->assertSame($url, PublicUrl::rewrite($url, 'https://impact.example'));
  }

}
