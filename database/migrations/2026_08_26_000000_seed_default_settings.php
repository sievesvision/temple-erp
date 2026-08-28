<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfills every settings key the app reads with its in-code default value,
     * using insertOrIgnore (settings.key is the primary key) so this only ever
     * fills in rows that don't exist yet — an environment that already has a key
     * (e.g. production's real temple_address) keeps its existing value untouched.
     * Defaults here must stay in sync with App\Models\Setting::templeBranding()
     * and the other Setting::get(...) fallbacks used across the app.
     */
    public function up(): void
    {
        $now = now();

        $defaults = [
            'temple_name' => 'SRI SELVA VINAYAKAR KOYIL (GANESHA TEMPLE)',
            'temple_subtitle' => 'South Maclean',
            'temple_eyebrow' => 'A place for prayer, community and belonging',
            'temple_description' => 'A Tamil Hindu temple in South Maclean, Queensland, welcoming devotees to seek the blessings of Sri Selva Vinayakar.',
            'temple_address' => '4915-4923 Mount Lindesay Hwy, South Maclean QLD 4280',
            'temple_phone' => '+61 7 5547 8064',
            'temple_email' => 'hasq.president@gmail.com',
            'donation_account_name' => 'HINDU AHLAYA SANGAM QLD INC',
            'donation_bank_name' => 'Commonwealth Bank',
            'donation_bsb' => '064 000',
            'donation_account_number' => '00906257',
            'donation_receipt_email' => 'hasq.president@gmail.com',
            'currency_code' => 'AUD',
            'temple_logo' => '/images/logo.gif',
            'admin_logo_icon' => '/images/logo.gif',
            'admin_logo_text' => 'SSVK ERP',
            'temple_hero_image' => '/images/temple_landing.jpg',
            'temple_story_image' => '/images/about/ssvk.jpg',
            'temple_worship_image' => '/images/about/SELVA VINAYAHAR TEMPLE.jpg',
            'theme_primary_color' => '#c45b2c',
            'theme_accent_color' => '#e5ad45',
            'theme_dark_color' => '#24382f',
            'theme_preset' => 'saffron-garden',
            'brand_title' => 'SSVK',
            'brand_subtitle' => '',
            'hours_weekday_morning' => '7:30 am - 12:00 noon',
            'hours_weekday_morning_pooja' => '9:00 am - 9:30 am',
            'hours_weekday_evening' => '5:00 pm - 8:30 pm',
            'hours_weekday_evening_pooja' => '7:00 pm - 7:30 pm',
            'hours_weekend' => '7:30 am - 1:00 pm',
            'hours_weekend_pooja' => '9:00 am - 9:30 am',
            'temple_opening_time' => '06:00',
            'temple_closing_time' => '21:00',
            'stripe_enabled' => '1',
            'low_stock_threshold' => '10.00',
            'max_advance_booking_days' => '90',
            'online_pooja_shipping_charge' => '50.00',
            'hourly_penalty_amount' => '100.00',
            'system_mode' => 'Testing Mode',
            'testing_email_handling' => 'Do Not Send Emails',
        ];

        $rows = [];
        foreach ($defaults as $key => $value) {
            $rows[] = [
                'key' => $key,
                'value' => $value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('settings')->insertOrIgnore($rows);
    }

    /**
     * No-op: we can't tell which rows pre-existed vs. were inserted by this
     * migration, so rolling back must not delete settings data.
     */
    public function down(): void
    {
        //
    }
};
