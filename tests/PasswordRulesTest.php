<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/password-rules.php';

final class PasswordRulesTest extends TestCase
{
    public function testAcceptsPasswordMeetingEveryRule(): void
    {
        $this->assertTrue(passwordMeetsRules('Thread#2026'));
        $this->assertTrue(passwordMeetsRules('Aa1!aaaa')); // exactly 8
    }

    public function testRejectsEachMissingRule(): void
    {
        $this->assertFalse(passwordMeetsRules('Aa1!aaa'));      // 7 characters
        $this->assertFalse(passwordMeetsRules('thread#2026'));  // no uppercase
        $this->assertFalse(passwordMeetsRules('THREAD#2026'));  // no lowercase
        $this->assertFalse(passwordMeetsRules('Thread#Press')); // no number
        $this->assertFalse(passwordMeetsRules('Thread2026'));   // no special character
        $this->assertFalse(passwordMeetsRules(''));
    }
}
