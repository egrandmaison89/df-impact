<?php

declare(strict_types=1);

namespace weitzman\DrupalTestTraits\Tests;

use weitzman\DrupalTestTraits\EntityCrawlerTrait;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * @coversDefaultClass \weitzman\DrupalTestTraits\EntityCrawlerTrait
 */
class EntityCrawlerTraitTest extends ExistingSiteBase
{
    use EntityCrawlerTrait;

    /**
     * @covers ::getRenderedEntityCrawler
     */
    public function testGetRenderedEntityCrawler(): void
    {
        $entity = $this->createNode([
            'type' => 'article',
            'title' => 'My article',
            'body' => 'Article body goes here',
        ]);

        // Render the entity without HTTP requests.
        $crawler = $this->getRenderedEntityCrawler($entity);

        // Make assertions using symfony crawler.
        $this->assertStringContainsString('Article body goes here', $crawler->filter('div.node__content')->text(null, true));

        // You can filter the crawler down to select a container.
        $content = $crawler->filter('.node__content');
        $this->assertStringContainsString('Article body', $content->filter('.field--name-body')->text(null, true));

        // You can also access the build array if you like.
        $build = [];
        $crawler = $this->getRenderedEntityCrawler($this->createNode(), 'full', $build);
        $this->assertContains('config:filter.format.restricted_html', $build['#cache']['tags']);
    }
}
