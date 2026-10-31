/**
 * @file
 * Reveal additional In Brief items per issue (matches legacy WordPress UX).
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.dfImpactInBriefLoadMore = {
    attach(context) {
      once('df-impact-in-brief-load-more', '.js-in-brief-load-more', context).forEach((button) => {
        button.addEventListener('click', () => {
          const block = button.closest('.ultp-block-wrapper');
          if (!block) {
            return;
          }
          block.querySelectorAll('.ultp-block-item.is-collapsed').forEach((el) => {
            el.classList.remove('is-collapsed');
          });
          button.closest('.ultp-loadmore')?.remove();
        });
      });
    },
  };
}(Drupal, once));
