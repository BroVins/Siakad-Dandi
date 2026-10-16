<?php

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable;

abstract class TestCase extends \Illuminate\Foundation\Testing\TestCase
{
    /** Switching identities in a test must represent a fresh browser session. */
    public function actingAs(Authenticatable $user, $guard = null)
    {
        if ($this->app['auth']->guard($guard)->id() !== $user->getAuthIdentifier()) {
            $this->flushSession();
        }

        return parent::actingAs($user, $guard);
    }
}
