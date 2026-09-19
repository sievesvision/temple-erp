<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the temple's standard Archana/Pooja ticket catalog — each given its own
 * background_color theme (used on the kiosk tile and, via color-mix(), the printed stub's
 * accent — see resources/views/admin/ticket-print.blade.php) chosen to fit what the offering
 * actually is: navy for the everyday Standard Archana, warm orange for the Fruit Archana,
 * amber/gold for the Oil/Ghee Lamp, green for the Archana Basket, a muted slate for the
 * solemn Moksha Archana (departed souls), royal purple for the premium Special Archana, and
 * deep red for Vehicle Pooja (the traditional protection colour). insertOrIgnore on `name`
 * (not a unique DB constraint — matched here in PHP first) so re-running this migration, or
 * a temple that already added one of these manually, never creates a duplicate.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tickets = [
            ['name' => 'Standard Archana', 'description' => null, 'price' => 5.00, 'background_color' => '#1B3A6B', 'sort_order' => 10],
            ['name' => 'Fruit Archana', 'description' => null, 'price' => 10.00, 'background_color' => '#C4622D', 'sort_order' => 20],
            ['name' => 'Oil Lamp/Ghee Lamp', 'description' => null, 'price' => 5.00, 'background_color' => '#B8860B', 'sort_order' => 30],
            ['name' => 'Archana Basket', 'description' => null, 'price' => 21.00, 'background_color' => '#2E7D32', 'sort_order' => 40],
            ['name' => 'Moksha Archana', 'description' => 'For departed souls with Ghee Lamp', 'price' => 21.00, 'background_color' => '#4A4A6A', 'sort_order' => 50],
            ['name' => 'Special Archana', 'description' => 'Fruit basket and Ganesha Idol', 'price' => 51.00, 'background_color' => '#6B21A8', 'sort_order' => 60],
            ['name' => 'Vehicle Pooja', 'description' => null, 'price' => 51.00, 'background_color' => '#B91C1C', 'sort_order' => 70],
        ];

        foreach ($tickets as $ticket) {
            $exists = DB::table('tickets')->where('name', $ticket['name'])->exists();
            if ($exists) {
                continue;
            }

            DB::table('tickets')->insert(array_merge($ticket, [
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        DB::table('tickets')->whereIn('name', [
            'Standard Archana', 'Fruit Archana', 'Oil Lamp/Ghee Lamp', 'Archana Basket',
            'Moksha Archana', 'Special Archana', 'Vehicle Pooja',
        ])->delete();
    }
};
