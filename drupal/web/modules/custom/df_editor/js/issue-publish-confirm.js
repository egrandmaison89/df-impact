/**
 * Confirm publishing an issue when no articles are attached.
 */
(function (Drupal, drupalSettings, once) {
  'use strict';

  Drupal.behaviors.dfEditorIssuePublishConfirm = {
    attach(context) {
      const count = drupalSettings.dfEditor?.issueArticleCount;
      if (typeof count !== 'number') {
        return;
      }
      once('dfEditorIssuePublishConfirm', '.js-df-issue-publish-bundle', context).forEach((button) => {
        button.addEventListener('click', (event) => {
          if (count > 0) {
            return;
          }
          const message = Drupal.t(
            'This issue has no attached articles. Publish it anyway?',
          );
          if (!window.confirm(message)) {
            event.preventDefault();
            event.stopImmediatePropagation();
          }
        });
      });
    },
  };
})(Drupal, drupalSettings, once);
