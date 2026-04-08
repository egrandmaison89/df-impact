<?php
use Drupal\views\Entity\View;

$view = View::load('df_impact_search');
$display = &$view->getDisplay('default');

// The SearchApiRow plugin uses 'view_modes' (plural), not 'view_mode'
// Structure: [datasource_id][bundle] => view_mode
$display['display_options']['row'] = [
  'type' => 'search_api',
  'options' => [
    'view_modes' => [
      'entity:node' => [
        'article' => 'teaser',
        'issue' => 'teaser',
        'in_brief' => 'teaser',
      ],
    ],
  ],
];

$view->save();
echo "View row updated to use teaser view modes per bundle.\n";
