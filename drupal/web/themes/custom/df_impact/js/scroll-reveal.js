/**
 * @file
 * Scroll-reveal animations (PostX / Ultp front-page parity).
 *
 * Respects prefers-reduced-motion (matches WP accessibility pattern).
 */
(function (Drupal, once) {
  'use strict';

  var REVEAL_SELECTOR = '.ultp-animation, .homepage-section, .view-content .views-row, .node--view-mode-teaser';

  Drupal.behaviors.dfImpactScrollReveal = {
    attach: function (context) {
      if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        once('df-reveal-static', REVEAL_SELECTOR, context).forEach(function (el) {
          el.classList.add('df-reveal--visible');
        });
        return;
      }

      once('df-reveal', REVEAL_SELECTOR, context).forEach(function (el) {
        el.classList.add('df-reveal');
      });

      var pending = context.querySelectorAll('.df-reveal:not(.df-reveal--visible)');
      if (!pending.length) {
        return;
      }

      var observer = new IntersectionObserver(
        function (entries) {
          entries.forEach(function (entry) {
            if (entry.isIntersecting) {
              entry.target.classList.add('df-reveal--visible');
              observer.unobserve(entry.target);
            }
          });
        },
        { rootMargin: '0px 0px -8% 0px', threshold: 0.08 }
      );

      pending.forEach(function (el) {
        observer.observe(el);
      });
    },
  };
})(Drupal, once);
