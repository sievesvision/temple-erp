<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Devotee;
use App\Models\Pooja;
use App\Models\RolePermission;
use App\Models\User;
use Tests\TestCase;

/**
 * The Pooja Catalog admin CRUD (PoojaController) — lets prices/descriptions/categories
 * be altered later, per the temple's "SSVK Archana information" rate card being organised
 * into the catalog. Mirrors TicketController's own Ticket CRUD test shape.
 */
class PoojaCatalogManagementTest extends TestCase
{
    private function admin(): User
    {
        return User::factory()->create(['role' => 'Admin']);
    }

    public function test_admin_can_view_the_pooja_catalog_grouped_by_category(): void
    {
        Pooja::create(['pooja_name' => 'Festival', 'category' => 'Festivals', 'pooja_fee' => 501, 'status' => 'Active']);
        Pooja::create(['pooja_name' => 'Paal Abishekam', 'category' => 'Sponsored Abishekams', 'pooja_fee' => 51, 'status' => 'Active']);

        $response = $this->actingAs($this->admin())->get(route('admin.poojas.index'));

        $response->assertOk();
        $response->assertSee('Festivals');
        $response->assertSee('Sponsored Abishekams');
        $response->assertSee('Festival');
        $response->assertSee('Paal Abishekam');
    }

    public function test_admin_can_add_a_new_pooja_to_the_catalog(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('admin.poojas.store'), [
            'pooja_name' => 'Test New Pooja',
            'category' => 'Sponsored Poojas',
            'description' => 'A test note.',
            'pooja_fee' => 99.50,
            'duration' => '30',
            'status' => 'Active',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('poojas', ['pooja_name' => 'Test New Pooja', 'pooja_fee' => 99.50]);
    }

    public function test_price_and_description_can_be_edited_later(): void
    {
        $admin = $this->admin();
        $pooja = Pooja::create(['pooja_name' => 'Editable Pooja', 'category' => 'Festivals', 'pooja_fee' => 100, 'status' => 'Active']);

        $response = $this->actingAs($admin)->post(route('admin.poojas.update', $pooja->pooja_id), [
            'pooja_name' => 'Editable Pooja',
            'category' => 'Festivals',
            'description' => 'Updated note.',
            'pooja_fee' => 150.00,
            'status' => 'Active',
        ]);

        $response->assertRedirect();
        $this->assertSame(150.00, (float) $pooja->fresh()->pooja_fee);
        $this->assertSame('Updated note.', $pooja->fresh()->description);
    }

    public function test_a_pooja_with_past_bookings_is_deactivated_instead_of_deleted(): void
    {
        $admin = $this->admin();
        $pooja = Pooja::create(['pooja_name' => 'Booked Pooja', 'category' => 'Festivals', 'pooja_fee' => 100, 'status' => 'Active']);
        $devotee = Devotee::create(['user_id' => User::factory()->create()->id, 'verified' => 1]);
        Booking::create([
            'devotee_id' => $devotee->devotee_id,
            'pooja_id' => $pooja->pooja_id,
            'booking_date' => now()->addDay()->toDateString(),
            'booking_time' => '10:00:00',
            'amount' => 100,
            'total_amount' => 100,
            'payment_status' => 'Pending',
            'booking_status' => 'Pending',
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.poojas.delete', $pooja->pooja_id));

        $response->assertRedirect();
        $this->assertSame('Inactive', $pooja->fresh()->status);
        $this->assertDatabaseHas('poojas', ['pooja_id' => $pooja->pooja_id]);
    }

    public function test_a_pooja_with_no_bookings_is_hard_deleted(): void
    {
        $admin = $this->admin();
        $pooja = Pooja::create(['pooja_name' => 'Unbooked Pooja', 'category' => 'Festivals', 'pooja_fee' => 100, 'status' => 'Active']);

        $response = $this->actingAs($admin)->delete(route('admin.poojas.delete', $pooja->pooja_id));

        $response->assertRedirect();
        $this->assertDatabaseMissing('poojas', ['pooja_id' => $pooja->pooja_id]);
    }

    public function test_a_role_without_poojas_permission_cannot_manage_the_catalog(): void
    {
        // The route itself is Admin/Committee-only (same group as admin.bookings.index);
        // revoke Committee's own default grant to exercise the "no view permission" 403
        // the controller's own RolePermission::can() check guards.
        RolePermission::where('role', 'Committee')->where('resource', 'poojas')->update(['can_view' => false]);
        $committee = User::factory()->create(['role' => 'Committee']);

        $response = $this->actingAs($committee)->get(route('admin.poojas.index'));

        $response->assertForbidden();
    }

    public function test_committee_has_full_access_to_the_pooja_catalog_by_default(): void
    {
        $committee = User::factory()->create(['role' => 'Committee']);

        $response = $this->actingAs($committee)->get(route('admin.poojas.index'));

        $response->assertOk();
    }

    public function test_the_poojas_resource_appears_on_the_role_permissions_grid(): void
    {
        $this->assertArrayHasKey('poojas', RolePermission::resources());
    }
}
