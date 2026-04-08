<?php
/**
 * Configure Security Kit (seckit) module for production-ready security headers.
 */

$config = \Drupal::configFactory()->getEditable('seckit.settings');

$config->setData([
  'seckit_xss' => [
    'csp' => [
      'checkbox' => TRUE,
      'vendor-prefix' => FALSE,
      'report-only' => FALSE,
      'default-src' => "'self'",
      'script-src' => "'self' 'unsafe-inline' https://www.googletagmanager.com https://www.google-analytics.com https://www.clarity.ms",
      'object-src' => "'none'",
      'style-src' => "'self' 'unsafe-inline' https://fonts.googleapis.com",
      'img-src' => "'self' data: https://www.google-analytics.com https://www.googletagmanager.com",
      'media-src' => "'self'",
      'frame-src' => "'none'",
      'font-src' => "'self' https://fonts.gstatic.com",
      'connect-src' => "'self' https://www.google-analytics.com https://analytics.google.com",
      'report-uri' => '',
      'report-to' => '',
    ],
    'x-xss-protection' => [
      'checkbox' => TRUE,
    ],
  ],
  'seckit_clickjacking' => [
    'x-frame-options' => 'SAMEORIGIN',
  ],
  'seckit_ssl' => [
    'hsts' => FALSE,  // Enable on production with HTTPS
    'hsts-max-age' => 1000,
    'hsts-subdomains' => FALSE,
    'hsts-preload' => FALSE,
  ],
  'seckit_various' => [
    'from-origin' => FALSE,
    'referrer-policy' => TRUE,
    'referrer-policy-policy' => 'strict-origin-when-cross-origin',
  ],
])->save();

echo "Security Kit configured:\n";
echo "  - Content Security Policy enabled\n";
echo "  - X-XSS-Protection enabled\n";
echo "  - X-Frame-Options: SAMEORIGIN\n";
echo "  - Referrer-Policy: strict-origin-when-cross-origin\n";
echo "  - HSTS disabled (enable on production with HTTPS)\n";
