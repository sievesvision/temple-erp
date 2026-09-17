<?php

namespace Tests;

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
}
