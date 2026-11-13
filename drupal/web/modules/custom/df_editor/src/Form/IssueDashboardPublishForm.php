<?php

declare(strict_types=1);

namespace Drupal\df_editor\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\df_editor\IssuePublisher;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Publish issue and attached articles from the issue dashboard.
 */
final class IssueDashboardPublishForm extends ConfirmFormBase {

  private ?int $issueNid = NULL;

  public function __construct(
    private readonly IssuePublisher $issuePublisher,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self($container->get('df_editor.issue_publisher'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'df_editor_issue_dashboard_publish';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?int $issue_nid = NULL): array {
    $this->issueNid = $issue_nid;
    $issue = $this->loadIssue();
    if (!$issue) {
      $form['message'] = ['#markup' => $this->t('Issue not found.')];
      return $form;
    }
    $form = parent::buildForm($form, $form_state);
    $form['#attributes']['class'][] = 'df-issue-dashboard-publish';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    $issue = $this->loadIssue();
    return $this->t('Publish %issue and all attached articles now?', [
      '%issue' => $issue ? $issue->label() : $this->t('this issue'),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('df_editor.issue_dashboard');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $issue = $this->loadIssue();
    if (!$issue) {
      return;
    }
    $articles = $this->issuePublisher->publishIssueWithArticles($issue);
    $this->messenger()->addStatus($this->t('Published @issue and @count article(s).', [
      '@issue' => $issue->label(),
      '@count' => count($articles),
    ]));
    $form_state->setRedirect('df_editor.issue_dashboard');
  }

  private function loadIssue(): ?NodeInterface {
    if ($this->issueNid === NULL) {
      return NULL;
    }
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($this->issueNid);
    return $node instanceof NodeInterface ? $node : NULL;
  }

}
