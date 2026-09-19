<?php

namespace Tests;

use App\Models\EftTerminal;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Setting::$cache is a request-scoped memoization that never resets on its own
        // within PHPUnit's single-process test run, unlike RefreshDatabase which does reset
        // the underlying table before every test — without this, a Setting::set() call in
        // one test can silently leak into an unrelated later test.
        Setting::clearCache();
    }

    /**
     * Sandbox-pairs the default EftTerminal — the multi-terminal equivalent of the old
     * "Setting::set('linkly_secret_sandbox', ...)" shortcut every Linkly-touching test used
     * before terminals became their own registry (see App\Models\EftTerminal). The
     * migration that created that table already seeds one 'main' row (is_default=true) on
     * every fresh RefreshDatabase run — this pairs THAT row rather than creating a second
     * one, so every call site that resolves via EftTerminal::default()/resolveOrDefault()
     * without an explicit terminal_id keeps working exactly as it did with one global secret.
     */
    protected function defaultEftTerminal(string $secret = 'test-secret'): EftTerminal
    {
        $terminal = EftTerminal::default() ?? EftTerminal::factory()->create(['is_default' => true]);
        $terminal->update(['secret_sandbox' => $secret]);
        return $terminal->fresh();
    }
}
