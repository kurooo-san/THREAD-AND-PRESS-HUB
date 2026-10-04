<?php

declare(strict_types=1);

/**
 * The one password rule, used by sign-up, password reset and both profile
 * pages (customer and admin). The register page's live checklist shows the
 * same five rules in the browser; this is the check that counts.
 *
 * No DB or session here, so tests/PasswordRulesTest.php loads it directly.
 */

const PASSWORD_RULE_MESSAGE = 'Password must be at least 8 characters and contain uppercase, lowercase, number, and a special character.';

function passwordMeetsRules(string $password): bool
{
    return strlen($password) >= 8
        && preg_match('/[A-Z]/', $password)
        && preg_match('/[a-z]/', $password)
        && preg_match('/\d/', $password)
        && preg_match('/[^A-Za-z0-9]/', $password);
}
