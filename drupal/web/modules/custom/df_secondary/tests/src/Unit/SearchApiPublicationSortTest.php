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

  public function testPublicationSortIsAProcessorProperty(): void {
    $processor = file_get_contents(dirname(__DIR__, 3) . '/src/Plugin/search_api/processor/PublicationSort.php');
    $index = file_get_contents(dirname(__DIR__, 7) . '/config/sync/search_api.index.df_impact_content.yml');
    $module = file_get_contents(dirname(__DIR__, 3) . '/df_secondary.module');
    $this->assertIsString($processor);
    $this->assertIsString($index);
    $this->assertIsString($module);
    $this->assertStringContainsString("id: 'publication_sort'", $processor);
    $this->assertStringContainsString("'processor_id' => \$this->getPluginId()", $processor);
    $this->assertStringContainsString('plugin_id: publication_sort', $index);
    $this->assertStringNotContainsString('datasource_id: entity:node
    property_path: publication_sort', $index);
    $this->assertStringNotContainsString('function df_secondary_search_api_index_items_alter', $module);
  }

}
