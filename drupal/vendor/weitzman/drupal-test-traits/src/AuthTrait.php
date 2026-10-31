<?php

namespace weitzman\DrupalTestTraits;

use Drupal\Core\Url;

/**
 * An alternative logout that works with sites that hide the login form (e.g. OpenID).
 */
trait AuthTrait
{
    /**
     * Logout without asserting user/pass form fields.
     */
    protected function drupalLogout()
    {
        // Make a request to the logout page, and redirect to the user page, the
        // idea being if you were properly logged out you should be seeing a login
        // screen.
        $assert_session = $this->assertSession();
        $destination = Url::fromRoute('user.page')->toString();
        $this->drupalGet(Url::fromRoute('user.logout.confirm', options: ['query' => ['destination' => $destination]]));
        // Target the submit button using the name rather than the value to work
        // regardless of the user interface language.
        $this->submitForm([], 'op', 'user-logout-confirm');

        // These are the problematic assertions from the parent.
        // $assert_session->fieldExists('name');
        // $assert_session->fieldExists('pass');

        $this->drupalResetSession();
    }
}
