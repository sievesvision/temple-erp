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
            'donation_account_name' => self::get('donation_account_name', 'HINDU AHLAYA SANGAM QLD INC'),
            'donation_bank_name' => self::get('donation_bank_name', 'Commonwealth Bank'),
            'donation_bsb' => self::get('donation_bsb', '064 000'),
            'donation_account_number' => self::get('donation_account_number', '00906257'),
            'donation_receipt_email' => self::get('donation_receipt_email', 'hasq.president@gmail.com'),
            'currency' => self::get('currency_code', 'AUD'),
            'logo' => self::get('temple_logo', asset('images/logo.gif')),
            'admin_logo_icon' => self::get('admin_logo_icon', asset('images/logo.gif')),
            'admin_logo_text' => self::get('admin_logo_text', 'SSVK ERP'),
            'hero_image' => self::get('temple_hero_image', asset('images/temple_landing.jpg')),
            'story_image' => self::get('temple_story_image', asset('images/about/ssvk.jpg')),
            'worship_image' => self::get('temple_worship_image', asset('images/about/SELVA VINAYAHAR TEMPLE.jpg')),
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
