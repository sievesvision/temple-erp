<?php

namespace Tests\Feature;

use App\Models\EftTerminal;
use App\Models\Pooja;
use App\Models\SciTransaction;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pooja bookings can now be paid on a CBA Smart Terminal (mx51 SCI), not just Linkly —
 * every terminal configured on this installation is cba_sci, so the booking popup's Card
 * option was entirely unusable before this. Mirrors CbaSciTransactionTest's own donation/
 * ticket-order coverage, for the pooja_booking record_type instead.
 */
class CbaSciBookingPaymentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function adminUser(): User
    {
        return User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
    }

    private function pairedTerminal(): EftTerminal
    {
        return EftTerminal::create([
            'key' => 'sci-booking-' . uniqid(),
            'label' => 'SCI Booking Terminal',
            'provider' => 'cba_sci',
            'pos_id' => (string) Str::uuid(),
            'is_default' => true,
            'sci_pairing_id' => 'pid_booking',
            'sci_key_id' => 'kid_booking',
            'sci_signing_secret_part_b' => 'secret-b',
            'sci_api_base_url' => 'https://sci-api.tenant.example',
        ]);
    }

    public function test_starting_a_pooja_booking_purchase_persists_the_booking_meta(): void
    {
        $admin = $this->adminUser();
        $terminal = $this->pairedTerminal();
        $pooja = Pooja::create(['pooja_name' => 'SCI Booking Pooja', 'pooja_fee' => 60, 'status' => 'Active']);

        Http::fake(['sci-api.tenant.example/*' => Http::response(['data' => [
            'id' => 'txn_booking_1', 'version' => 1, 'status' => 'PENDING', 'message' => 'Processing',
        ]], 200)]);

        $response = $this->actingAs($admin)->postJson(route('admin.cba-sci.charge.start'), [
            'record_type' => 'pooja_booking',
            'amount' => 60,
            'client_ref' => 'booking-ref-1',
            'terminal_id' => $terminal->id,
            'pooja_id' => $pooja->pooja_id,
            'booking_date' => now()->addDay()->toDateString(),
            'booking_time' => '10:00:00',
            'devotee_name' => 'SCI Booking Devotee',
            'devotee_mobile' => '0433221100',
            'devotee_email' => 'sci.booking.devotee@example.com',
            'participant_details' => 'Lakshmi — Kashyapa gothram',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true, 'transaction_id' => 'txn_booking_1']);
        $txn = SciTransaction::where('client_ref', 'booking-ref-1')->first();
        $this->assertNotNull($txn);
        $this->assertSame('pooja_booking', $txn->meta['record_type']);
        $this->assertSame($pooja->pooja_id, $txn->meta['pooja_id']);
        $this->assertSame('SCI Booking Devotee', $txn->meta['devotee_name']);
        $this->assertSame('Lakshmi — Kashyapa gothram', $txn->meta['participant_details']);
    }

    public function test_starting_a_pooja_booking_purchase_requires_bookings_add_permission(): void
    {
        // Trustee has no role_permissions row for 'bookings' at all (can_add defaults to
        // false) — see PoojaBookingEftPaymentTest's identical reasoning for the Linkly path.
        $trustee = User::factory()->create(['role' => 'Trustee', 'mobile' => fake()->unique()->numerify('04########')]);
        $terminal = $this->pairedTerminal();
        $pooja = Pooja::create(['pooja_name' => 'SCI Perm Pooja', 'pooja_fee' => 40, 'status' => 'Active']);

        $response = $this->actingAs($trustee)->postJson(route('admin.cba-sci.charge.start'), [
            'record_type' => 'pooja_booking',
            'amount' => 40,
            'client_ref' => 'booking-ref-perm',
            'terminal_id' => $terminal->id,
            'pooja_id' => $pooja->pooja_id,
            'booking_date' => now()->addDay()->toDateString(),
            'booking_time' => '10:00:00',
            'devotee_name' => 'Perm Test',
            'devotee_mobile' => '0433221199',
            'devotee_email' => 'sci.perm.test@example.com',
        ]);

        $response->assertForbidden();
    }

    public function test_polling_to_finalised_approved_creates_the_booking(): void
    {
        $admin = $this->adminUser();
        $terminal = $this->pairedTerminal();
        $pooja = Pooja::create(['pooja_name' => 'SCI Approved Pooja', 'pooja_fee' => 55, 'status' => 'Active']);

        $txn = SciTransaction::create([
            'client_ref' => 'booking-ref-2', 'sci_transaction_id' => 'txn_booking_2', 'sci_version' => 1,
            'eft_terminal_id' => $terminal->id, 'amount' => 55, 'status' => 'PENDING',
            'meta' => [
                'record_type' => 'pooja_booking',
                'pooja_id' => $pooja->pooja_id,
                'custom_pooja_label' => null,
                'custom_fee' => null,
                'booking_date' => now()->addDay()->toDateString(),
                'booking_time' => '11:00:00',
                'priest_option' => 'auto',
                'preferred_priest_id' => null,
                'devotee_name' => 'Approved Booking Devotee',
                'devotee_mobile' => '0433221188',
                'devotee_email' => 'approved.booking.devotee@example.com',
                'participant_details' => null,
                'created_by_role' => 'Admin',
                'created_by_user_id' => $admin->id,
            ],
            'initiated_by' => $admin->id,
        ]);

        Http::fake(['sci-api.tenant.example/*' => Http::response(['data' => [
            'status' => 'FINALISED', 'version' => 2, 'result_financial_status' => 'APPROVED',
            'result_amounts' => ['purchase_amount' => 5500], 'merchant_receipt' => 'MERCHANT COPY', 'customer_receipt' => 'CUSTOMER COPY',
        ]], 200)]);

        $response = $this->actingAs($admin)->getJson(route('admin.cba-sci.charge.status', $txn->sci_transaction_id));

        $response->assertOk();
        $response->assertJson(['done' => true, 'success' => true, 'result_financial_status' => 'APPROVED']);
        $bookingId = $response->json('donation_id');
        $this->assertNotNull($bookingId);
        $this->assertDatabaseHas('pooja_bookings', [
            'booking_id' => $bookingId, 'payment_method' => 'EFT Terminal', 'payment_status' => 'Paid', 'booking_status' => 'Confirmed',
        ]);

        $txn->refresh();
        $this->assertSame('FINALISED', $txn->status);
        $this->assertSame($bookingId, $txn->donation_id);
        $this->assertSame('pooja_booking', $txn->donation_type);
    }
}
