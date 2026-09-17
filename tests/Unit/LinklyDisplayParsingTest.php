<?php

namespace Tests\Unit;

use App\Services\LinklyEftService;
use Tests\TestCase;

/**
 * LinklyEftService::cleanDisplayLines() is the pure-function piece of the EFT terminal
 * live-status feature: Linkly's PIN pad display text arrives fixed-width and space-padded
 * (e.g. "     SWIPE CARD     "), and a second line is often present but entirely blank.
 */
class LinklyDisplayParsingTest extends TestCase
{
    public function test_single_meaningful_line_is_trimmed(): void
    {
        $lines = ['     SWIPE CARD     ', '                    '];

        $this->assertSame(['SWIPE CARD'], LinklyEftService::cleanDisplayLines($lines));
    }

    public function test_two_meaningful_lines_are_both_preserved(): void
    {
        $lines = ['SELECT ACCOUNT', 'SAV CHQ CR'];

        $this->assertSame(['SELECT ACCOUNT', 'SAV CHQ CR'], LinklyEftService::cleanDisplayLines($lines));
    }

    public function test_blank_and_whitespace_only_lines_are_dropped(): void
    {
        $lines = ['', '   ', "\t", 'ENTER PIN'];

        $this->assertSame(['ENTER PIN'], LinklyEftService::cleanDisplayLines($lines));
    }

    public function test_completely_blank_display_yields_an_empty_array(): void
    {
        $this->assertSame([], LinklyEftService::cleanDisplayLines(['', '   ']));
    }

    public function test_non_string_line_values_are_coerced_safely(): void
    {
        // Defensive — a malformed postback shouldn't throw even if a line isn't a string.
        $this->assertSame(['1'], LinklyEftService::cleanDisplayLines([null, 1, '']));
    }
}
