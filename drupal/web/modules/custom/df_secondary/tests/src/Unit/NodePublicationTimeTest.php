<?php

declare(strict_types=1);

namespace Drupal\Tests\df_secondary\Unit;

use Drupal\df_secondary\NodePublicationTime;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * @group df_secondary
 */
#[Group('df_secondary')]
final class NodePublicationTimeTest extends TestCase {

  public function testParseIsoDateUsesWordPressPostDate(): void {
    $parsed = NodePublicationTime::parseIsoDate('2022-11-01T12:00:00');
    $this->assertNotNull($parsed);
    $this->assertSame('2022', gmdate('Y', $parsed));
  }

  public function testParseIsoDateOrdersNewerExportsFirst(): void {
    $older = NodePublicationTime::parseIsoDate('2022-01-01T00:00:00');
    $newer = NodePublicationTime::parseIsoDate('2024-06-15T10:00:00');
    $this->assertGreaterThan($older, $newer);
  }

  public function testBlankSearchSortFieldIsWiredInModule(): void {
    $module = file_get_contents(dirname(__DIR__, 3) . '/df_secondary.module');
    $this->assertIsString($module);
    $this->assertStringContainsString("sort('publication_sort'", $module);
    $this->assertStringContainsString("setField('publication_sort'", $module);
  }

}
