<?php

declare(strict_types=1);

use Drupal\user\Entity\User;

foreach ([['df_test_web_editor', 'web_editor'], ['df_test_site_admin', 'site_admin']] as [$name, $role]) {
  $storage = \Drupal::entityTypeManager()->getStorage('user');
  $users = $storage->loadByProperties(['name' => $name]);
  $user = reset($users);
  if (!$user) {
    $user = User::create([
      'name' => $name,
      'mail' => $name . '@example.com',
      'pass' => 'test',
      'status' => 1,
      'roles' => [$role],
    ]);
    $user->save();
  }
}

print "OK\n";
