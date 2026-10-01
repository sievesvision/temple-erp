<?php

namespace Tests\Feature;

use Illuminate\Mail\Events\MessageSending;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * The "TEST ENVIRONMENT" ribbon (resources/views/partials/test-banner.blade.php) and the
 * [TEST] email subject prefix (AppServiceProvider::boot()) are both driven by the single
 * config('app.show_test_banner') flag — true only on test.hasq.org's own .env (SHOW_TEST_
 * BANNER=true), never set on production. Covering both behaviours here rather than per-page,
 * since the flag — not any one page's markup — is the thing that actually matters.
 */
class TestEnvironmentBannerTest extends TestCase
{
    public function test_banner_partial_renders_when_the_flag_is_on(): void
    {
        config(['app.show_test_banner' => true]);

        $html = view('partials.test-banner')->render();

        $this->assertStringContainsString('Test Environment', $html);
    }

    public function test_banner_partial_renders_nothing_when_the_flag_is_off(): void
    {
        config(['app.show_test_banner' => false]);

        $html = trim(view('partials.test-banner')->render());

        $this->assertSame('', $html);
    }

    public function test_outgoing_email_subject_is_prefixed_when_the_flag_is_on(): void
    {
        config(['app.show_test_banner' => true]);

        $message = new Email();
        $message->subject('Your OTP Code');
        event(new MessageSending($message));

        $this->assertSame('[TEST] Your OTP Code', $message->getSubject());
    }

    public function test_outgoing_email_subject_is_untouched_when_the_flag_is_off(): void
    {
        config(['app.show_test_banner' => false]);

        $message = new Email();
        $message->subject('Your OTP Code');
        event(new MessageSending($message));

        $this->assertSame('Your OTP Code', $message->getSubject());
    }

    public function test_the_prefix_is_not_doubled_if_a_subject_already_carries_it(): void
    {
        config(['app.show_test_banner' => true]);

        $message = new Email();
        $message->subject('[TEST] Already Prefixed');
        event(new MessageSending($message));

        $this->assertSame('[TEST] Already Prefixed', $message->getSubject());
    }
}
