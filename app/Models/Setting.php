<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'settings';
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['key', 'value'];

    /**
     * All settings, loaded once per request. templeBranding() alone calls get() ~26 times
     * (once per branding field) — without this, that's 26 separate DB round-trips on every
     * single page load. Loading the whole table in one query and memoizing it here collapses
     * that to exactly one query per request, no matter how many times get() is called.
     */
    protected static ?array $cache = null;

    /**
     * Get a setting by key.
     */
    public static function get($key, $default = null)
    {
        if (self::$cache === null) {
            self::$cache = self::query()->pluck('value', 'key')->all();
        }

        return self::$cache[$key] ?? $default;
    }

    /**
     * Set a setting key-value pair.
     */
    public static function set($key, $value)
    {
        self::$cache = null;

        return self::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /**
     * Test-only escape hatch: PHPUnit runs many tests in one PHP process (unlike a real web
     * request, where this cache naturally starts empty), so without this a test that never
     * touches Setting can silently read a previous test's in-memory snapshot even after
     * RefreshDatabase has wiped the settings table out from under it. Called from
     * Tests\TestCase::setUp().
     */
    public static function clearCache(): void
    {
        self::$cache = null;
    }

    /**
     * Resolves a stored image-path setting the same way its own asset()-wrapped default
     * already does — without this, a DB value like the seeded '/images/logo.gif' (a bare
     * root-relative path, not run through asset()) 404s on any install served from a
     * subdirectory (e.g. local XAMPP's /ssvk/public), even though the untouched default for
     * the same key works fine there. Only a genuinely relative path is rewritten; an already-
     * absolute URL (a real uploaded logo on S3/another host, for instance) passes through as-is.
     */
    private static function assetPath(string $key, string $defaultRelativePath): ?string
    {
        $value = self::get($key);
        if (!$value) {
            return asset($defaultRelativePath);
        }

        if (preg_match('#^(https?:)?//#i', $value)) {
            return $value;
        }

        return asset(ltrim($value, '/'));
    }

    /**
     * The ticket system's own bank account reference, falling back to the temple's global
     * donation account (same `?:` pattern as Event::effectiveDonationAccountName() etc.) —
     * tickets had no bank account configuration of their own before this.
     */
    public static function effectiveTicketBankAccount(): array
    {
        return [
            'account_name' => self::get('ticket_donation_account_name', '') ?: self::get('donation_account_name', ''),
            'bank_name' => self::get('ticket_donation_bank_name', '') ?: self::get('donation_bank_name', ''),
            'bsb' => self::get('ticket_donation_bsb', '') ?: self::get('donation_bsb', ''),
            'account_number' => self::get('ticket_donation_account_number', '') ?: self::get('donation_account_number', ''),
        ];
    }

    /**
     * The canonical temple branding/currency context used across the site —
     * public pages, every role dashboard, and the auth screens.
     */
    public static function templeBranding(): array
    {
        return [
            'name' => self::get('temple_name', 'SRI SELVA VINAYAKAR KOYIL (GANESHA TEMPLE)'),
            'subtitle' => self::get('temple_subtitle', 'South Maclean'),
            'eyebrow' => self::get('temple_eyebrow', 'A place for prayer, community and belonging'),
            'description' => self::get('temple_description', 'A Tamil Hindu temple in South Maclean, Queensland, welcoming devotees to seek the blessings of Sri Selva Vinayakar.'),
            'address' => self::get('temple_address', '4915-4923 Mount Lindesay Hwy, South Maclean QLD 4280'),
            'phone' => self::get('temple_phone', '+61 7 5547 8064'),
            'email' => self::get('temple_email', 'hasq.president@gmail.com'),
            'abn' => self::get('temple_abn', '42 694 249 621'),
            'website' => self::get('temple_website', 'http://www.sriselvavinayakar.org'),
            'legal_name' => self::get('temple_legal_name', 'Hindu Ahlaya Sangam (QLD) Inc.'),
            'donation_account_name' => self::get('donation_account_name', 'HINDU AHLAYA SANGAM QLD INC'),
            'donation_bank_name' => self::get('donation_bank_name', 'Commonwealth Bank'),
            'donation_bsb' => self::get('donation_bsb', '064 000'),
            'donation_account_number' => self::get('donation_account_number', '00906257'),
            'donation_receipt_email' => self::get('donation_receipt_email', 'hasq.president@gmail.com'),
            'currency' => self::get('currency_code', 'AUD'),
            'logo' => self::assetPath('temple_logo', 'images/logo.gif'),
            'admin_logo_icon' => self::assetPath('admin_logo_icon', 'images/logo.gif'),
            'admin_logo_text' => self::get('admin_logo_text', 'SSVK ERP'),
            'hero_image' => self::assetPath('temple_hero_image', 'images/temple_landing.jpg'),
            'story_image' => self::assetPath('temple_story_image', 'images/about/ssvk.jpg'),
            'worship_image' => self::assetPath('temple_worship_image', 'images/about/SELVA VINAYAHAR TEMPLE.jpg'),
            'primary_color' => self::get('theme_primary_color', '#c45b2c'),
            'accent_color' => self::get('theme_accent_color', '#e5ad45'),
            'dark_color' => self::get('theme_dark_color', '#24382f'),
            'theme_preset' => self::get('theme_preset', 'saffron-garden'),
            'brand_title' => self::get('brand_title', 'SSVK'),
            'brand_subtitle' => self::get('brand_subtitle', ''),
            'hours_weekday_morning' => self::get('hours_weekday_morning', '7:30 am - 12:00 noon'),
            'hours_weekday_morning_pooja' => self::get('hours_weekday_morning_pooja', '9:00 am - 9:30 am'),
            'hours_weekday_evening' => self::get('hours_weekday_evening', '5:00 pm - 8:30 pm'),
            'hours_weekday_evening_pooja' => self::get('hours_weekday_evening_pooja', '7:00 pm - 7:30 pm'),
            'hours_weekend' => self::get('hours_weekend', '7:30 am - 1:00 pm'),
            'hours_weekend_pooja' => self::get('hours_weekend_pooja', '9:00 am - 9:30 am'),
        ];
    }
}
