/**
 * @file
 * Dana-Farber Impact Magazine — main JavaScript.
 */

(function (Drupal, once) {
  'use strict';

  /**
   * Sticky header scroll behavior.
   */
  Drupal.behaviors.dfImpactHeader = {
    attach: function (context) {
      once('df-header', '.header', context).forEach(function (header) {
        var scrollThreshold = 50;
        var logo = header.querySelector('.header-logo');

        function onScroll() {
          var scrolled = window.scrollY > scrollThreshold;
          header.classList.toggle('header--scrolled', scrolled);
          if (logo) {
            logo.classList.toggle('scrolled', scrolled);
          }
        }

        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
      });
    }
  };

  /**
   * Mobile menu toggle.
   */
  Drupal.behaviors.dfImpactMobileMenu = {
    attach: function (context) {
      once('df-mobile-menu', '.header__menu-toggle', context).forEach(function (toggle) {
        var drawer = document.getElementById('header-drawer');
        if (!drawer) return;

        toggle.addEventListener('click', function () {
          var isOpen = drawer.classList.toggle('header__drawer--open');
          toggle.setAttribute('aria-expanded', isOpen);
          document.body.classList.toggle('header-drawer-open', isOpen);
        });

        // Close on escape key
        document.addEventListener('keydown', function (e) {
          if (e.key === 'Escape' && drawer.classList.contains('header__drawer--open')) {
            drawer.classList.remove('header__drawer--open');
            document.body.classList.remove('header-drawer-open');
            toggle.setAttribute('aria-expanded', 'false');
            toggle.focus();
          }
        });
      });
    }
  };

})(Drupal, once);
