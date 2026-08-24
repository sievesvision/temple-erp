<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class OfflineCounterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Point default database to mysql and override PHPUnit's overridden environment values
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => 'temple_management',
            'database.connections.mysql.username' => 'root',
            'database.connections.mysql.password' => '',
            'database.connections.mysql.host' => '127.0.0.1',
        ]);
        DB::purge('mysql');
        DB::reconnect('mysql');
        DB::connection('mysql')->beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::connection('mysql')->rollBack();
        parent::tearDown();
    }

    public function test_staff_can_book_pooja_offline(): void
    {
        // Find a staff user
        $staff = User::where('role', 'Staff')->first();
        if (!$staff) {
            // Create a temporary staff user if not found
            $staff = User::create([
                'name' => 'Temporary Test Staff',
                'email' => 'temp_staff_' . uniqid() . '@templeconnect.com',
                'mobile' => '9990001112',
                'password' => Hash::make('password123'),
                'role' => 'Staff',
                'status' => 'Active',
                'email_verified_at' => now(),
            ]);
        }

        // Find an active pooja
        $pooja = DB::connection('mysql')->table('poojas')->where('status', 'Active')->first();
        if (!$pooja) {
            // Create a temporary pooja if none exists
            $poojaId = DB::connection('mysql')->table('poojas')->insertGetId([
                'pooja_name' => 'Test Pooja Service',
                'description' => 'Test description',
                'pooja_fee' => 150.00,
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $pooja = DB::connection('mysql')->table('poojas')->where('pooja_id', $poojaId)->first();
        }

        // Ensure there is at least one active priest and a user for that priest
        $priest = DB::connection('mysql')->table('priests')->where('employment_status', 'Active')->first();
        if (!$priest) {
            // Create a temporary priest user
            $priestUser = User::create([
                'name' => 'Temporary Test Priest',
                'email' => 'temp_priest_' . uniqid() . '@templeconnect.com',
                'mobile' => '9990001113',
                'password' => Hash::make('password123'),
                'role' => 'Priest',
                'status' => 'Active',
                'email_verified_at' => now(),
            ]);

            DB::connection('mysql')->table('priests')->insert([
                'user_id' => $priestUser->id,
                'priest_id' => 'PRIEST9999',
                'employment_status' => 'Active',
                'current_status' => 'Offline',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $priest = DB::connection('mysql')->table('priests')->where('priest_id', 'PRIEST9999')->first();
        }

        $mobile = '9876543210';
        $devoteeName = 'Offline Test Devotee';
        $bookingDate = date('Y-m-d', strtotime('+1 day'));
        $bookingTime = '10:00:00';

        // Call the endpoint
        $response = $this->actingAs($staff)->postJson(route('staff.counter.book-pooja'), [
            'pooja_id' => $pooja->pooja_id,
            'devotee_name' => $devoteeName,
            'mobile' => $mobile,
            'booking_date' => $bookingDate,
            'booking_time' => $bookingTime,
        ]);

        // Assert success response
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Offline Pooja Booking registered successfully.'
        ]);

        $data = $response->json('booking_details');
        $this->assertEquals($devoteeName, $data['devotee_name']);
        $this->assertEquals($mobile, $data['mobile']);
        $this->assertEquals($pooja->pooja_name, $data['pooja_name']);

        // Check user creation
        $createdUser = User::where('mobile', $mobile)->first();
        $this->assertNotNull($createdUser);
        $this->assertEquals('Devotee', $createdUser->role);

        // Check database records
        $bookingId = $data['booking_id'];
        $bookingRecord = DB::connection('mysql')->table('pooja_bookings')->where('booking_id', $bookingId)->first();
        $this->assertNotNull($bookingRecord);
        $this->assertEquals('Offline', $bookingRecord->booking_type);
        $this->assertEquals('Paid', $bookingRecord->payment_status);
        $this->assertEquals('Confirmed', $bookingRecord->booking_status);
    }

    public function test_staff_can_record_donation_offline(): void
    {
        // Find a staff user
        $staff = User::where('role', 'Staff')->first();
        if (!$staff) {
            $staff = User::create([
                'name' => 'Temporary Test Staff',
                'email' => 'temp_staff_' . uniqid() . '@templeconnect.com',
                'mobile' => '9990001112',
                'password' => Hash::make('password123'),
                'role' => 'Staff',
                'status' => 'Active',
                'email_verified_at' => now(),
            ]);
        }

        $donorName = 'Offline Test Donor';
        $mobile = '9998887776';
        $amount = 1500;
        $purpose = 'Annadaan';

        $response = $this->actingAs($staff)->postJson(route('staff.counter.record-donation'), [
            'donor_name' => $donorName,
            'mobile' => $mobile,
            'amount' => $amount,
            'purpose' => $purpose,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Offline donation recorded successfully.'
        ]);

        $data = $response->json('donation_details');
        $this->assertEquals($donorName, $data['donor_name']);
        $this->assertEquals($mobile, $data['mobile']);
        $this->assertEquals(number_format($amount, 2), $data['amount']);
        $this->assertEquals($purpose, $data['purpose']);

        // Check DB entry in donations_without_logins
        $donationId = $data['donation_id'];
        $donationRecord = DB::connection('mysql')->table('donations_without_logins')->where('id', $donationId)->first();
        $this->assertNotNull($donationRecord);
        $this->assertEquals($donorName, $donationRecord->donor_name);
        $this->assertEquals($mobile, $donationRecord->mobile);
        $this->assertEquals($amount, $donationRecord->amount);
    }
}
