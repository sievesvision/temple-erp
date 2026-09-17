{{-- Google reCAPTCHA v2 checkbox — renders nothing at all unless RecaptchaService::enabled()
     (no site/secret key configured, or the Setting is off), so a page including this partial
     never breaks before reCAPTCHA is actually set up. See app/Services/RecaptchaService.php. --}}
@if(\App\Services\RecaptchaService::enabled())
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <div class="g-recaptcha mb-3" data-sitekey="{{ \App\Services\RecaptchaService::siteKey() }}"></div>
    @error('g-recaptcha-response')
        <div class="text-danger small mb-3">{{ $message }}</div>
    @enderror
@endif
