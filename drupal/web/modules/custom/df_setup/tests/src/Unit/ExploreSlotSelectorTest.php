<?php

declare(strict_types=1);

namespace Drupal\Tests\df_setup\Unit;

use Drupal\df_setup\ExploreSlotSelector;
use Drupal\df_setup\IssueStoryOrder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * @group df_setup
 */
#[Group('df_setup')]
final class ExploreSlotSelectorTest extends TestCase {

  public function testFeaturedSlotsKeepEditorOrder(): void {
    $rows = [
      $this->row(1, 'featured', 2, 100),
      $this->row(2, 'featured', 1, 50),
      $this->row(3, 'featured', 3, 10),
    ];
    $this->assertSame([2, 1, 3], ExploreSlotSelector::select($rows));
  }

  public function testUnpublishedFeaturedSlotBackfillsByPublicationDate(): void {
    $rows = [
      $this->row(1, 'featured', 1, 500, published: FALSE),
      $this->row(2, 'none', 0, 300),
      $this->row(3, 'none', 0, 100),
      $this->row(4, 'featured', 2, 50),
    ];
    $this->assertSame([2, 4, 3], ExploreSlotSelector::select($rows));
  }

  public function testDigitalExclusivesAreNotFeaturedOrBackfill(): void {
    $rows = [
      $this->row(1, 'featured', 1, 900, digital_exclusive: TRUE),
      $this->row(2, 'none', 0, 100),
    ];
    $this->assertSame([2], ExploreSlotSelector::select($rows));
  }

  public function testLaterSaveReplacesTheSameSlot(): void {
    $rows = [
      $this->row(1, 'featured', 1, 10, changed: 1),
      $this->row(2, 'featured', 1, 10, changed: 5),
    ];
    $this->assertSame([2, 1], ExploreSlotSelector::select($rows));
  }

  public function testIssueStoryOrderPinsThenDateThenDemotes(): void {
    $this->assertSame(-1, IssueStoryOrder::compare(
      IssueStoryOrder::rankValue('pinned'),
      10,
      IssueStoryOrder::rankValue('normal'),
      999,
    ));
    $this->assertSame(1, IssueStoryOrder::compare(
      IssueStoryOrder::rankValue('normal'),
      10,
      IssueStoryOrder::rankValue('normal'),
      50,
    ));
    $this->assertSame(1, IssueStoryOrder::compare(
      IssueStoryOrder::rankValue('demoted'),
      9999,
      IssueStoryOrder::rankValue('normal'),
      1,
    ));
  }

  /**
   * @return array{nid:int,placement:string,order:int,published:bool,digital_exclusive:bool,sort:int,changed:int}
   */
  private function row(int $nid, string $placement, int $order, int $sort, bool $published = TRUE, bool $digital_exclusive = FALSE, int $changed = 1): array {
    return [
      'nid' => $nid,
      'placement' => $placement,
      'order' => $order,
      'published' => $published,
      'digital_exclusive' => $digital_exclusive,
      'sort' => $sort,
      'changed' => $changed,
    ];
  }

}
