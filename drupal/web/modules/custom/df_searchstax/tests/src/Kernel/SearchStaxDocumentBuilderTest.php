<?php

declare(strict_types=1);

namespace Drupal\Tests\df_searchstax\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Tests SearchStax article document construction.
 *
 * @group df_searchstax
 */
final class SearchStaxDocumentBuilderTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'df_searchstax',
    'field',
    'filter',
    'node',
    'system',
    'taxonomy',
    'text',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installSchema('node', ['node_access']);

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    NodeType::create(['type' => 'issue', 'name' => 'Issue'])->save();
    Vocabulary::create(['vid' => 'content_type', 'name' => 'Content Type'])->save();

    $this->createNodeField('field_issue', 'entity_reference', [
      'target_type' => 'node',
    ], [
      'handler' => 'default:node',
      'handler_settings' => [
        'target_bundles' => ['issue' => 'issue'],
      ],
    ]);
    $this->createNodeField('field_content_type', 'entity_reference', [
      'target_type' => 'taxonomy_term',
    ], [
      'handler' => 'default:taxonomy_term',
      'handler_settings' => [
        'target_bundles' => ['content_type' => 'content_type'],
      ],
    ]);
    $this->createNodeField('field_byline', 'string_long');
  }

  /**
   * Article documents contain the fields needed for SearchStax facets.
   */
  public function testBuildsArticleDocumentWithIssueAndCategoryFacets(): void {
    $issue = Node::create([
      'type' => 'issue',
      'title' => 'Spring 2026',
      'status' => 1,
    ]);
    $issue->save();

    $category = Term::create([
      'vid' => 'content_type',
      'name' => 'Feature Story',
    ]);
    $category->save();

    $article = Node::create([
      'type' => 'article',
      'title' => 'Research Breakthrough',
      'status' => 1,
      'field_issue' => ['target_id' => $issue->id()],
      'field_content_type' => ['target_id' => $category->id()],
      'field_byline' => 'Test Author',
    ]);
    $article->save();

    $doc = $this->container
      ->get('df_searchstax.document_builder')
      ->build($article, 'https://impact.example.org');

    $this->assertIsArray($doc);
    $this->assertSame('https://impact.example.org/node/' . $article->id(), $doc['id']);
    $this->assertSame('drupal', $doc['index_source_s']);
    $this->assertSame('articles', $doc['search_scope_s']);
    $this->assertSame('Article', $doc['content_type_s']);
    $this->assertSame((string) $issue->id(), $doc['issue_id_s']);
    $this->assertSame('Spring 2026', $doc['issue_title_s']);
    $this->assertSame('Feature Story', $doc['article_category_s']);
    $this->assertSame('Test Author', $doc['author_t']);
    $this->assertStringContainsString('Spring 2026', $doc['tcngramm_X3b_en_field_story_teaser']);
  }

  /**
   * Non-article bundles are excluded from article search.
   */
  public function testExcludesNonArticleBundles(): void {
    $issue = Node::create([
      'type' => 'issue',
      'title' => 'Spring 2026',
      'status' => 1,
    ]);
    $issue->save();

    $doc = $this->container
      ->get('df_searchstax.document_builder')
      ->build($issue, 'https://impact.example.org');

    $this->assertNull($doc);
  }

  /**
   * Unpublished articles are excluded from article search.
   */
  public function testExcludesUnpublishedArticles(): void {
    $article = Node::create([
      'type' => 'article',
      'title' => 'Draft Article',
      'status' => 0,
    ]);
    $article->save();

    $doc = $this->container
      ->get('df_searchstax.document_builder')
      ->build($article, 'https://impact.example.org');

    $this->assertNull($doc);
  }

  /**
   * Creates a node field on the article bundle.
   *
   * @param array<string, mixed> $storageSettings
   *   Field storage settings.
   * @param array<string, mixed> $fieldSettings
   *   Field instance settings.
   */
  private function createNodeField(string $fieldName, string $type, array $storageSettings = [], array $fieldSettings = []): void {
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => $fieldName,
      'type' => $type,
      'settings' => $storageSettings,
    ])->save();

    FieldConfig::create([
      'entity_type' => 'node',
      'bundle' => 'article',
      'field_name' => $fieldName,
      'label' => $fieldName,
      'settings' => $fieldSettings,
    ])->save();
  }

}
