<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * An EFT Terminal donation IS the terminal transaction record (see SciTransaction/
 * LinklyTransaction's own donation_id link) — editing or deleting it locally would desync it
 * from what the gateway actually charged, with no way to reflect that back on the real
 * transaction. Refund (from the All Transactions list) is the only correct way to change the
 * outcome of one of these; view and resend-receipt stay available since they're read-only.
 */
class EftTerminalDonationImmutabilityTest extends TestCase
{
    private function adminUser(): User
    {
        return User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
    }

    private function eftGuestDonation(): int
    {
        return DB::table('donations_without_logins')->insertGetId([
            'donor_name' => 'EFT Guest Donor', 'amount' => 50, 'purpose' => 'General Donation',
            'payment_method' => 'EFT Terminal', 'payment_status' => 'Paid', 'transaction_id' => 'txn_immutable_1',
            'email' => 'eft-guest@example.test', 'donation_date' => now()->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function eftDevoteeDonation(): int
    {
        return DB::table('donations')->insertGetId([
            'amount' => 75, 'purpose' => 'General Donation', 'payment_method' => 'EFT Terminal',
            'payment_status' => 'Paid', 'transaction_id' => 'txn_immutable_2', 'donation_date' => now()->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_updating_a_guest_eft_terminal_donation_is_rejected(): void
    {
        $admin = $this->adminUser();
        $id = $this->eftGuestDonation();

        $response = $this->actingAs($admin)->post(route('admin.donations.updateGuest', $id), [
            'donor_name' => 'Changed Name', 'amount' => 999, 'purpose' => 'General Donation',
            'payment_method' => 'Cash', 'payment_status' => 'Paid', 'donation_date' => now()->toDateString(),
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('donations_without_logins', ['id' => $id, 'donor_name' => 'EFT Guest Donor', 'amount' => 50, 'payment_method' => 'EFT Terminal']);
    }

    public function test_updating_a_devotee_eft_terminal_donation_is_rejected(): void
    {
        $admin = $this->adminUser();
        $id = $this->eftDevoteeDonation();

        $response = $this->actingAs($admin)->post(route('admin.donations.updateDevotee', $id), [
            'amount' => 999, 'payment_mode' => 'Cash', 'payment_status' => 'Paid', 'donation_date' => now()->toDateString(),
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('donations', ['id' => $id, 'amount' => 75, 'payment_method' => 'EFT Terminal']);
    }

    public function test_deleting_a_guest_eft_terminal_donation_is_rejected(): void
    {
        $admin = $this->adminUser();
        $id = $this->eftGuestDonation();

        $response = $this->actingAs($admin)->delete(route('admin.donations.deleteGuest', $id));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('donations_without_logins', ['id' => $id]);
    }

    public function test_deleting_a_devotee_eft_terminal_donation_is_rejected(): void
    {
        $admin = $this->adminUser();
        $id = $this->eftDevoteeDonation();

        $response = $this->actingAs($admin)->delete(route('admin.donations.deleteDevotee', $id));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('donations', ['id' => $id]);
    }

    // A non-EFT donation must still be freely editable/deletable — this restriction is
    // specific to the terminal-gateway payment method, not a general lockdown.
    public function test_a_cash_donation_can_still_be_updated_and_deleted(): void
    {
        $admin = $this->adminUser();
        $id = DB::table('donations_without_logins')->insertGetId([
            'donor_name' => 'Cash Donor', 'amount' => 20, 'purpose' => 'General Donation',
            'payment_method' => 'Cash', 'payment_status' => 'Paid', 'donation_date' => now()->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $updateResponse = $this->actingAs($admin)->post(route('admin.donations.updateGuest', $id), [
            'donor_name' => 'Cash Donor Updated', 'amount' => 25, 'purpose' => 'General Donation',
            'payment_method' => 'Cash', 'payment_status' => 'Paid', 'donation_date' => now()->toDateString(),
        ]);
        $updateResponse->assertSessionHas('success');
        $this->assertDatabaseHas('donations_without_logins', ['id' => $id, 'donor_name' => 'Cash Donor Updated', 'amount' => 25]);

        $deleteResponse = $this->actingAs($admin)->delete(route('admin.donations.deleteGuest', $id));
        $deleteResponse->assertSessionHas('success');
        $this->assertDatabaseMissing('donations_without_logins', ['id' => $id]);
    }

    public function test_guest_donations_table_hides_edit_and_delete_for_eft_terminal_but_keeps_resend(): void
    {
        $admin = $this->adminUser();
        $id = $this->eftGuestDonation();

        $response = $this->actingAs($admin)->get('/admin/manage-donations');

        $response->assertOk();
        $response->assertSee('EFT Guest Donor');
        // This specific donation's own edit modal/delete form must not be on the page —
        // not a blanket check, since other (non-EFT) donations may legitimately have theirs.
        $response->assertDontSee('editGuestDonationModal' . $id, false);
        $response->assertDontSee(route('admin.donations.deleteGuest', $id), false);
        $response->assertSee(route('admin.donations.resendReceipt', ['type' => 'guest', 'id' => $id]), false);
    }
}
