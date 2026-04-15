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
          const section = button.closest('.in-brief-page__issue-section');
          if (!section) {
            return;
          }
          section.querySelectorAll('.in-brief-page__item.is-collapsed').forEach((el) => {
            el.classList.remove('is-collapsed');
          });
          button.remove();
        });
      });
    },
  };
}(Drupal, once));
