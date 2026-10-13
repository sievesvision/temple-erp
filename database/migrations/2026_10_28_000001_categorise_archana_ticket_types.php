<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Groups the standard Archana ticket types (seeded by
 * 2026_10_05_000000_seed_archana_ticket_types.php) under a single 'Archana' category —
 * they had none before, so they never got their own tab on the Ticket POS's category
 * picker (built from distinct ticket.category values). Also fills in the handful of
 * descriptions the original seed left null, taken from "SSVK Archana information
 * Revised in November 2017.pdf". Only updates rows that still have their original
 * (null/placeholder) values, so it never overwrites something an admin already edited.
 */
return new class extends Migration
{
    public function up(): void
    {
        $updates = [
            'Standard Archana' => 'Standard archana performed on request — pay on arrival and get a receipt.',
            'Fruit Archana' => 'Archana performed with a fruit offering.',
            'Oil Lamp/Ghee Lamp' => 'Oil lamp or ghee lamp offering.',
            'Archana Basket' => 'Basket with fruits and incense sticks.',
            'Moksha Archana' => 'For departed souls, with ghee lamp.',
            'Special Archana' => 'Ganesh coin/statue, fruits, and incense sticks.',
            'Vehicle Pooja' => 'Blessing pooja performed for a vehicle.',
        ];

        foreach ($updates as $name => $description) {
            DB::table('tickets')->where('name', $name)->whereNull('category')->update([
                'category' => 'Archana',
                'description' => $description,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('tickets')->where('category', 'Archana')->whereIn('name', [
            'Standard Archana', 'Fruit Archana', 'Oil Lamp/Ghee Lamp', 'Archana Basket',
            'Moksha Archana', 'Special Archana', 'Vehicle Pooja',
        ])->update(['category' => null]);
    }
};
