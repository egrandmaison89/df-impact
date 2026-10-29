<?php

declare(strict_types=1);

namespace Drupal\df_editor\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\df_editor\IssuePreview;
use Drupal\df_editor\IssuePublisher;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Issue launch dashboard for web editors.
 */
final class IssueDashboardController extends ControllerBase {

  public function __construct(
    private readonly IssuePublisher $issuePublisher,
    private readonly IssuePreview $issuePreview,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('df_editor.issue_publisher'),
      $container->get('df_editor.issue_preview'),
    );
  }

  /**
   * Lists the next draft issue, attached articles, preview, and publish action.
   */
  public function dashboard(): array {
    $issue = $this->loadNextDraftIssue();
    $build = [
      '#title' => $this->t('Issue dashboard'),
      '#attached' => ['library' => ['df_editor/issue_dashboard']],
    ];

    if (!$issue instanceof NodeInterface) {
      $build['empty'] = [
        '#markup' => '<p>' . $this->t('No draft issue found. <a href=":create">Create an issue</a> and assign articles to it.', [
          ':create' => Url::fromRoute('node.add', ['node_type' => 'issue'])->toString(),
        ]) . '</p>',
      ];
      return $build;
    }

    $articles = $this->loadAttachedArticles($issue);
    $preview_url = $this->issuePreview->frontPreviewUrl($issue);
    $live_latest = function_exists('df_setup_get_latest_issue_nid') ? df_setup_get_latest_issue_nid() : NULL;

    $build['summary'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['df-issue-dashboard__summary']],
      'issue' => [
        '#markup' => '<h2>' . $issue->toLink($issue->label(), 'edit-form')->toString() . '</h2>',
      ],
      'meta' => [
        '#markup' => '<p>' . $this->t('Moderation: @state · Currently live latest issue nid: @live', [
          '@state' => $issue->hasField('moderation_state') ? (string) $issue->get('moderation_state')->value : ($issue->isPublished() ? 'published' : 'draft'),
          '@live' => $live_latest ?? $this->t('none'),
        ]) . '</p>',
      ],
    ];

    $rows = [];
    foreach ($articles as $article) {
      $rows[] = [
        Link::fromTextAndUrl($article->label(), $article->toUrl('edit-form'))->toString(),
        $article->hasField('moderation_state') ? (string) $article->get('moderation_state')->value : ($article->isPublished() ? 'published' : 'draft'),
        $article->isPublished() ? $this->t('Yes') : $this->t('No'),
      ];
    }

    $build['articles'] = [
      '#type' => 'table',
      '#header' => [$this->t('Article'), $this->t('Moderation'), $this->t('Published')],
      '#rows' => $rows,
      '#empty' => $this->t('No articles assigned to this issue yet.'),
    ];

    $nav_preview = $this->buildNavPreviewMarkup($issue);
    $build['preview'] = [
      '#type' => 'details',
      '#title' => $this->t('Preview before publish'),
      '#open' => TRUE,
      'help' => [
        '#markup' => '<p>' . $this->t('The preview treats this draft issue as the newest issue for the homepage hero and Issues navigation (only for you while preview is active).') . '</p>',
      ],
      'nav' => ['#markup' => $nav_preview],
    ];

    if ($preview_url !== NULL) {
      $build['preview']['links'] = [
        '#type' => 'container',
        'front' => Link::createFromRoute($this->t('Open homepage preview'), '<front>', [], [
          'query' => [IssuePreview::QUERY_PARAMETER => $issue->id()],
          'attributes' => ['class' => ['button', 'button--primary'], 'target' => '_blank'],
        ])->toRenderable(),
        'browse' => Link::createFromRoute($this->t('Browse issues (live)'), 'view.browse_issues.page_1', [], [
          'attributes' => ['class' => ['button']],
        ])->toRenderable(),
        'clear' => Link::createFromRoute($this->t('Clear preview session'), 'df_editor.issue_dashboard_clear_preview', [], [
          'attributes' => ['class' => ['button']],
        ])->toRenderable(),
      ];
    }

    if ($this->currentUser()->hasPermission('publish issue with articles')) {
      $build['publish'] = [
        '#type' => 'container',
        'form' => \Drupal::formBuilder()->getForm(\Drupal\df_editor\Form\IssueDashboardPublishForm::class, $issue->id()),
      ];
    }

    return $build;
  }

  /**
   * Clears stored preview issue from session.
   */
  public function clearPreview(): RedirectResponse {
    $this->issuePreview->clearPreview();
    $this->messenger()->addStatus($this->t('Homepage preview session cleared.'));
    return $this->redirect('df_editor.issue_dashboard');
  }

  private function loadNextDraftIssue(): ?NodeInterface {
    $storage = $this->entityTypeManager()->getStorage('node');
    $nids = $storage->getQuery()
      ->condition('type', 'issue')
      ->condition('status', 0)
      ->accessCheck(TRUE)
      ->sort('changed', 'DESC')
      ->range(0, 20)
      ->execute();
    if (!$nids) {
      return NULL;
    }
    $candidates = $storage->loadMultiple($nids);
    $issues = [];
    foreach ($candidates as $node) {
      if ($node instanceof NodeInterface) {
        $issues[] = $node;
      }
    }
    usort($issues, static function (NodeInterface $a, NodeInterface $b): int {
      $year_a = (int) ($a->get('field_year')->value ?? 0);
      $year_b = (int) ($b->get('field_year')->value ?? 0);
      if ($year_a !== $year_b) {
        return $year_b <=> $year_a;
      }
      return $b->getChangedTime() <=> $a->getChangedTime();
    });
    $first = reset($issues);
    return $first instanceof NodeInterface ? $first : NULL;
  }

  /**
   * @return \Drupal\node\NodeInterface[]
   */
  private function loadAttachedArticles(NodeInterface $issue): array {
    $storage = $this->entityTypeManager()->getStorage('node');
    $nids = $storage->getQuery()
      ->condition('type', 'article')
      ->condition('field_issue.target_id', $issue->id())
      ->accessCheck(TRUE)
      ->sort('title', 'ASC')
      ->execute();
    $out = [];
    foreach ($storage->loadMultiple($nids) as $node) {
      if ($node instanceof NodeInterface) {
        $out[] = $node;
      }
    }
    return $out;
  }

  private function buildNavPreviewMarkup(NodeInterface $draft): string {
    $label = function_exists('df_setup_format_issue_nav_label')
      ? df_setup_format_issue_nav_label($draft)
      : $draft->label();
    $current = $this->t('Current Issue → @url', ['@url' => $draft->toUrl()->toString()]);
    $items = [$this->t('Issues flyout (first item): @label', ['@label' => $label])];
    if ($draft->hasField('field_hero_heading') && !$draft->get('field_hero_heading')->isEmpty()) {
      $items[] = $this->t('Homepage hero heading: @h', ['@h' => $draft->get('field_hero_heading')->value]);
    }
    $items[] = (string) $current;
    return '<ul><li>' . implode('</li><li>', $items) . '</li></ul>';
  }

}
