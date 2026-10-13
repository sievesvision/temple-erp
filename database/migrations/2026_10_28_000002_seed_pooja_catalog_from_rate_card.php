<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the Pooja Catalog (the `poojas` table — what a booking is made *against*, see the
 * Pooja Bookings plan) from the temple's own rate card: "SSVK Archana information Revised
 * in November 2017.pdf" (guides/). Archanas themselves are NOT seeded here — the PDF's own
 * "pay on arrival, no booking needed" instruction for them is exactly what the Tickets
 * module already models (see 2026_10_05_000000_seed_archana_ticket_types.php and
 * 2026_10_13_000003_categorise_archana_ticket_types.php, which groups them as the
 * 'Archana' ticket category). Everything below needs an actual date/time/priest, which is
 * what a pooja *booking* is for.
 *
 * Five categories, matching the rate card's own section headings exactly:
 * Sponsored Poojas, Sponsored Abishekams, Festivals, Other Functions, Functions Outside
 * the Temple. Each pooja's `description` carries that item's own PDF notes (what to
 * bring, ordering variants, prasadam entitlement) — not duplicated across every row is
 * the shared policy text from the PDF, reproduced once here since it applies to
 * Sponsored Poojas/Abishekams as a whole: "Performed by prior arrangement with the
 * temple priest, during normal temple opening hours. Payment in advance confirms the
 * date and time; show your receipt to the priest before the pooja begins. The temple
 * provides all materials and neivethyam. Dakshina to the priest is at the devotee's own
 * discretion." And for Functions Outside the Temple as a whole: "The priest brings brass
 * utensils (pots, lamps), navathaniyam and homa thiraviyam. The devotee provides flowers,
 * fruits, coconuts, rice, camphor, ghee, mango leaves and dried mango twigs (for homam).
 * Contact the HASQ Committee Representative or the priest to arrange."
 *
 * Two PDF line items are deliberately NOT seeded as poojas: "Hall Hire" ($20/hr) and
 * "Manavarai Hire" ($501) are facility/furniture rentals, not a priest-led ritual with a
 * date/time/priest assignment — they don't fit this booking model at all (and Hall Hire's
 * hourly rate doesn't fit the flat per-booking fee column either). An admin wanting to
 * track those can add them later as their own catalog entries if a rental-booking flow is
 * ever built; forcing them through the priest-availability/auto-assignment logic today
 * would be actively wrong.
 *
 * `duration` (minutes) is set close to each ceremony's real length so
 * PriestAvailabilityService's overlap/conflict checking is meaningful; anything with
 * "Homam" or "Satyanarayana" in the name also gets that service's existing half-day-slot
 * special-casing automatically (string-matched on the name), regardless of the duration
 * value here.
 */
return new class extends Migration
{
    public function up(): void
    {
        $poojas = [
            // ---------------- Sponsored Poojas ----------------
            ['pooja_name' => 'Vadai Malai', 'category' => 'Sponsored Poojas', 'pooja_fee' => 21.00, 'duration' => '20',
                'description' => 'A garland of vadai offered to the deity. Can also be ordered through the priest as 21 vadai for $20 or 51 vadai for $50.'],
            ['pooja_name' => 'Mothaka Poojai', 'category' => 'Sponsored Poojas', 'pooja_fee' => 21.00, 'duration' => '20',
                'description' => 'Mothakam (modak) offering. Can also be ordered through the priest as 21 mothakams for $20 or 51 for $50.'],
            ['pooja_name' => 'Poojai with Neivethiyam', 'category' => 'Sponsored Poojas', 'pooja_fee' => 21.00, 'duration' => '20',
                'description' => 'Includes Neivethiyam (rice prasadam). Neivethiyam can also be ordered separately through the priest for $20.'],
            ['pooja_name' => 'Astothra Kumkuma Archanai (108 names)', 'category' => 'Sponsored Poojas', 'pooja_fee' => 51.00, 'duration' => '30',
                'description' => '108-name kumkuma archana for Abirami Amman, Vishnu Durga or Siva Durga.'],
            ['pooja_name' => 'Thirisathy Kumkuma Archanai (300 names)', 'category' => 'Sponsored Poojas', 'pooja_fee' => 75.00, 'duration' => '45',
                'description' => '300-name kumkuma archana for Abirami Amman, Vishnu Durga or Siva Durga.'],
            ['pooja_name' => 'Astothra Archanai (108 names)', 'category' => 'Sponsored Poojas', 'pooja_fee' => 51.00, 'duration' => '30',
                'description' => '108-name archana for Pillayar, Sivan, Murugan, Anjaneyar, Maha Vishnu, Venkadeswarar or Bairavar.'],
            ['pooja_name' => 'Thirisathy Nama Archanai (300 names)', 'category' => 'Sponsored Poojas', 'pooja_fee' => 75.00, 'duration' => '45',
                'description' => '300-name archana for Pillayar, Sivan, Murugan, Anjaneyar, Maha Vishnu, Venkadeswarar or Bairavar.'],
            ['pooja_name' => 'Santhana Kaappu', 'category' => 'Sponsored Poojas', 'pooja_fee' => 75.00, 'duration' => '30',
                'description' => null],
            ['pooja_name' => 'Thiruvembavai', 'category' => 'Sponsored Poojas', 'pooja_fee' => 75.00, 'duration' => '30',
                'description' => 'Sponsor will receive one prasadam.'],
            ['pooja_name' => 'Kuru Poojai', 'category' => 'Sponsored Poojas', 'pooja_fee' => 75.00, 'duration' => '30',
                'description' => 'Sponsor will receive one prasadam.'],
            ['pooja_name' => 'Sathyanarayana Poojai', 'category' => 'Sponsored Poojas', 'pooja_fee' => 101.00, 'duration' => '90',
                'description' => 'Devotees to bring fruits, flowers and five coconuts. Sponsor will receive one prasadam.'],
            ['pooja_name' => 'Sahasranama (1008 names) Archanai', 'category' => 'Sponsored Poojas', 'pooja_fee' => 151.00, 'duration' => '60',
                'description' => 'Devotees to bring enough flowers, fruits and one coconut. Sponsor will receive one prasadam.'],

            // ---------------- Sponsored Abishekams ----------------
            ['pooja_name' => 'Paal Abishekam', 'category' => 'Sponsored Abishekams', 'pooja_fee' => 51.00, 'duration' => '30',
                'description' => 'Abishekam performed with milk.'],
            ['pooja_name' => 'Thiraviya Abishekam', 'category' => 'Sponsored Abishekams', 'pooja_fee' => 101.00, 'duration' => '45',
                'description' => 'Sponsor will receive one prasadam.'],
            ['pooja_name' => 'Nava Kalasa Abishekam (Regular Day)', 'category' => 'Sponsored Abishekams', 'pooja_fee' => 151.00, 'duration' => '60',
                'description' => 'Sponsor will receive one prasadam.'],
            ['pooja_name' => 'Nava Kalasa Abishekam (Special Day)', 'category' => 'Sponsored Abishekams', 'pooja_fee' => 201.00, 'duration' => '60',
                'description' => 'Performed on a special day. Sponsor will receive one prasadam.'],
            ['pooja_name' => 'Sankadahara Chathurthi', 'category' => 'Sponsored Abishekams', 'pooja_fee' => 201.00, 'duration' => '60',
                'description' => 'Sponsor will receive one prasadam.'],
            ['pooja_name' => 'Monthly Chathurthi (except Aavani Chathurthi)', 'category' => 'Sponsored Abishekams', 'pooja_fee' => 251.00, 'duration' => '60',
                'description' => 'Includes circumambulation of the inner sanctum. Sponsor will receive one prasadam.'],
            ['pooja_name' => 'Navagraha Homam and Abishekam', 'category' => 'Sponsored Abishekams', 'pooja_fee' => 251.00, 'duration' => '90',
                'description' => 'Sponsor will receive one prasadam.'],
            ['pooja_name' => 'Sangabishekam (108 Conch)', 'category' => 'Sponsored Abishekams', 'pooja_fee' => 501.00, 'duration' => '90',
                'description' => 'Sponsor will receive two prasadams. Additional prasadams can be ordered in advance through the priest at $20 each.'],

            // ---------------- Festivals ----------------
            ['pooja_name' => 'Festival', 'category' => 'Festivals', 'pooja_fee' => 501.00, 'duration' => '120',
                'description' => 'Abhishekam and Alankara Pujai of the Utsava Moorthy, plus circumambulation of the inner and outer sanctum. Sponsor will receive one prasadam. See "Special Days and Festivals of Sri Selva Vinayakar Temple" for festival dates.'],
            ['pooja_name' => 'Thiru Kalyanam', 'category' => 'Festivals', 'pooja_fee' => 501.00, 'duration' => '180',
                'description' => "Devotee to provide the 'Thiru Mangalyam'. Alankara Pujai of the Utsava Moorthy will take place. Sponsor will receive two prasadams."],

            // ---------------- Other Functions (at the temple) ----------------
            ['pooja_name' => 'Thivasam/Shradh (At Temple)', 'category' => 'Other Functions', 'pooja_fee' => 75.00, 'duration' => '30',
                'description' => "This fee is for the priest. Can be arranged directly with the priest."],
            ['pooja_name' => 'Offering New Born Baby to Deity (and Buying Back)', 'category' => 'Other Functions', 'pooja_fee' => 51.00, 'duration' => '20',
                'description' => 'Only after 41 days.'],
            ['pooja_name' => 'Vidyarambham', 'category' => 'Other Functions', 'pooja_fee' => 51.00, 'duration' => '30',
                'description' => 'Devotees to bring fruits, flowers, rice, coconut, candy sugar and a small bottle of honey. Sponsor will receive one prasadam.'],
            ['pooja_name' => 'Annaprasannam', 'category' => 'Other Functions', 'pooja_fee' => 51.00, 'duration' => '30',
                'description' => 'Sponsor will receive one prasadam.'],
            ['pooja_name' => 'Naamakaranam', 'category' => 'Other Functions', 'pooja_fee' => 51.00, 'duration' => '30',
                'description' => 'Sponsor will receive one prasadam.'],
            ['pooja_name' => 'Blessing of Vehicles', 'category' => 'Other Functions', 'pooja_fee' => 51.00, 'duration' => '15',
                'description' => null],
            ['pooja_name' => 'Karnavethanam (Ear Piercing)', 'category' => 'Other Functions', 'pooja_fee' => 51.00, 'duration' => '20',
                'description' => null],
            ['pooja_name' => 'Wedding: Short Ceremony', 'category' => 'Other Functions', 'pooja_fee' => 151.00, 'duration' => '60',
                'description' => 'Pillayar pooja and thaali tying only. Sponsor will receive one prasadam.'],
            ['pooja_name' => 'Auysh Homam (At Temple)', 'category' => 'Other Functions', 'pooja_fee' => 151.00, 'duration' => '90',
                'description' => null],

            // ---------------- Functions Outside the Temple ----------------
            ['pooja_name' => 'Wedding: Full Ceremony (Temple Hall)', 'category' => 'Functions Outside the Temple', 'pooja_fee' => 501.00, 'duration' => '240',
                'description' => null],
            ['pooja_name' => 'Wedding: Full Ceremony (Other Venues)', 'category' => 'Functions Outside the Temple', 'pooja_fee' => 501.00, 'duration' => '240',
                'description' => null],
            ['pooja_name' => 'Grahapravesam (Full Ceremony)', 'category' => 'Functions Outside the Temple', 'pooja_fee' => 251.00, 'duration' => '120',
                'description' => 'Includes Ganapathy Homam, Luxmi Homam and Navagraha Homam.'],
            ['pooja_name' => 'Grahapravesam (Ganapathy Homam Only)', 'category' => 'Functions Outside the Temple', 'pooja_fee' => 201.00, 'duration' => '90',
                'description' => 'Includes Ganapathy Homam, Punyaavachanam and Pada Poojai.'],
            ['pooja_name' => 'Grahapravesam (Basic Ceremony)', 'category' => 'Functions Outside the Temple', 'pooja_fee' => 151.00, 'duration' => '60',
                'description' => 'Ganapathy Poojai, Punyavachanam and Pada Poojai.'],
            ['pooja_name' => 'Foundation Ceremony', 'category' => 'Functions Outside the Temple', 'pooja_fee' => 151.00, 'duration' => '60',
                'description' => null],
            ['pooja_name' => 'Punyaha Vaachanam (Thudakku Kalivu)', 'category' => 'Functions Outside the Temple', 'pooja_fee' => 151.00, 'duration' => '45',
                'description' => null],
            ['pooja_name' => 'Thivasam (Outside Temple)', 'category' => 'Functions Outside the Temple', 'pooja_fee' => 151.00, 'duration' => '60',
                'description' => null],
            ['pooja_name' => 'Chabindikaranam (31st Day Ceremony After Death)', 'category' => 'Functions Outside the Temple', 'pooja_fee' => 151.00, 'duration' => '60',
                'description' => null],
            ['pooja_name' => 'Upanayanam', 'category' => 'Functions Outside the Temple', 'pooja_fee' => 201.00, 'duration' => '120',
                'description' => 'Sponsor will receive one prasadam.'],
            ['pooja_name' => 'Ayush Homam (Outside Temple)', 'category' => 'Functions Outside the Temple', 'pooja_fee' => 151.00, 'duration' => '90',
                'description' => 'Sponsor will receive one prasadam.'],
        ];

        foreach ($poojas as $pooja) {
            $exists = DB::table('poojas')->where('pooja_name', $pooja['pooja_name'])->exists();
            if ($exists) {
                continue;
            }

            DB::table('poojas')->insert(array_merge($pooja, [
                'online_allowed' => false,
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        DB::table('poojas')->whereIn('category', [
            'Sponsored Poojas', 'Sponsored Abishekams', 'Festivals', 'Other Functions', 'Functions Outside the Temple',
        ])->delete();
    }
};
