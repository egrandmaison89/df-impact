<?php

declare(strict_types=1);

namespace Drupal\Tests\df_secondary\Unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * @group df_secondary
 */
#[Group('df_secondary')]
final class SearchApiPublicationSortTest extends TestCase {

  public function testPublicationSortUsesSetValuesOnField(): void {
    $module = file_get_contents(dirname(__DIR__, 3) . '/df_secondary.module');
    $this->assertIsString($module);
    $this->assertStringContainsString("getField('publication_sort', FALSE)", $module);
    $this->assertStringContainsString('->setValues([NodePublicationTime::sortValue($entity)])', $module);
    $this->assertStringNotContainsString("setField('publication_sort', NodePublicationTime", $module);
  }

}
