<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown when a user with valid credentials attempts to log in before verifying
 * their email address.
 */
class EmailNotVerifiedException extends Exception
{
    protected $message = 'Please verify your email address before logging in.';
}
