<?php

namespace App\Http\Controllers;

use App\Models\RolePermission;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketOrderItem;
use App\Models\TicketStub;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * A standalone ticket-selling module (not tied to any Event) — a temple-wide catalog of
 * sellable ticket types, a dedicated kiosk page to sell them, and a management view of past
 * orders. Mirrors the shape of the donations module (Event -> EventDonationOption ->
 * donation_selections becomes here just Ticket -> TicketOrder -> TicketOrderItem ->
 * TicketStub) but is entirely separate from it. Permission resource: 'tickets' (see
 * RolePermission::resources()).
 */
class TicketController extends Controller
{
    private function activeRole()
    {
        $user = Auth::user();
        return $user ? session('active_role', $user->role) : null;
    }

    /**
     * Manage Tickets — the catalog admin screen (add/edit/deactivate ticket types).
     */
    public function manageTickets(Request $request)
    {
        $activeRole = $this->activeRole();
        if (!RolePermission::can($activeRole, 'tickets', 'view')) {
            abort(403, 'Unauthorized access.');
        }

        $tickets = Ticket::orderBy('sort_order')->orderBy('name')->get();
        $canEdit = RolePermission::can($activeRole, 'tickets', 'edit');
        $canAdd = RolePermission::can($activeRole, 'tickets', 'add');
        $canDelete = RolePermission::can($activeRole, 'tickets', 'delete');

        return view('admin.manage-tickets', compact('tickets', 'canEdit', 'canAdd', 'canDelete'));
    }

    public function storeTicket(Request $request)
    {
        $activeRole = $this->activeRole();
        if (!RolePermission::can($activeRole, 'tickets', 'add')) {
            abort(403, 'Unauthorized access.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'price' => 'required|numeric|min:0.01',
            'status' => 'required|in:Active,Inactive',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        Ticket::create($validated);
        AuditLogService::log("Created ticket type '{$validated['name']}' ({$validated['price']})");

        return redirect()->back()->with('success', 'Ticket type added.');
    }

    public function updateTicket(Request $request, $id)
    {
        $activeRole = $this->activeRole();
        if (!RolePermission::can($activeRole, 'tickets', 'edit')) {
            abort(403, 'Unauthorized access.');
        }

        $ticket = Ticket::findOrFail($id);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'price' => 'required|numeric|min:0.01',
            'status' => 'required|in:Active,Inactive',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $ticket->update($validated);
        AuditLogService::log("Updated ticket type '{$ticket->name}'");

        return redirect()->back()->with('success', 'Ticket type updated.');
    }

    public function deleteTicket($id)
    {
        $activeRole = $this->activeRole();
        if (!RolePermission::can($activeRole, 'tickets', 'delete')) {
            abort(403, 'Unauthorized access.');
        }

        $ticket = Ticket::findOrFail($id);
        // Never actually deleted once it has sales history (TicketOrderItem keeps its own
        // name/price snapshot regardless, but the catalog entry itself should stay
        // referenceable) — deactivating is the safe default; only remove a never-sold entry.
        if (TicketOrderItem::where('ticket_id', $id)->exists()) {
            $ticket->update(['status' => 'Inactive']);
            return redirect()->back()->with('success', 'Ticket type has past sales, so it was deactivated instead of deleted.');
        }

        $ticket->delete();
        AuditLogService::log("Deleted ticket type '{$ticket->name}'");

        return redirect()->back()->with('success', 'Ticket type deleted.');
    }

    /**
     * The dedicated kiosk page — a cart-style "add tickets, adjust quantities, take payment"
     * screen, separate from the donation POS page per this being its own standalone module.
     */
    public function posShow()
    {
        $activeRole = $this->activeRole();
        if (!RolePermission::can($activeRole, 'tickets', 'add')) {
            abort(403, 'Unauthorized access.');
        }

        $tickets = Ticket::active()->orderBy('sort_order')->orderBy('name')->get();
        $enabledPaymentMethods = json_decode(\App\Models\Setting::get('enabled_payment_methods', '["Cash","Bank Transfer","Cheque"]'), true) ?: [];
        // EFT Terminal is offered on the ticket kiosk whenever it's paired, the same way the
        // donation POS page offers it — not gated behind the global enabled_payment_methods
        // list (which predates EFT Terminal and is about the *manual* Log Donation forms).
        $paymentMethods = array_values(array_unique(array_merge(
            array_intersect($enabledPaymentMethods, ['Cash', 'UPI', 'Bank Transfer']),
            ['EFT Terminal']
        )));
        $temple = \App\Models\Setting::templeBranding();

        // Power Fail recovery (same mechanism as PosDonationController::show()): any
        // non-terminal Purchase whose ledger meta marks it as a ticket order, recent enough
        // to still plausibly be sitting on the terminal. Ticket orders never carry an
        // event_id (the module isn't tied to events), so this is scoped by record_type
        // instead of event_id.
        $pendingEftRecovery = \App\Models\LinklyTransaction::where('txn_type', 'purchase')
            ->whereNotIn('status', \App\Models\LinklyTransaction::TERMINAL_STATUSES)
            ->where('created_at', '>=', now()->subMinutes(30))
            ->get()
            ->first(fn ($txn) => ($txn->meta['record_type'] ?? null) === 'ticket_order');

        return view('admin.ticket-pos', compact('tickets', 'paymentMethods', 'temple', 'pendingEftRecovery'));
    }

    /**
     * Records a ticket order for a synchronous payment method (Cash/UPI/Bank Transfer) —
     * the EFT Terminal path instead goes through DonationController::startEftCharge()/
     * pollEftCharge(), which calls createOrderFromCart() below once Linkly confirms approval.
     */
    public function storeOrder(Request $request)
    {
        $activeRole = $this->activeRole();
        if (!RolePermission::can($activeRole, 'tickets', 'add')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
        }

        $validated = $request->validate([
            'customer_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'mobile' => 'nullable|string|max:20',
            'cart_json' => 'required|string',
            'payment_method' => 'required|string|in:Cash,UPI,Bank Transfer',
        ]);

        $cart = $this->decodeCart($validated['cart_json']);
        if (empty($cart)) {
            return response()->json(['success' => false, 'message' => 'The order is empty.'], 422);
        }

        $orderId = $this->createOrderFromCart(
            $cart,
            $validated['customer_name'] ?? null,
            $validated['email'] ?? null,
            $validated['mobile'] ?? null,
            $validated['payment_method'],
            'Paid',
            'TKTORD-' . strtoupper(uniqid())
        );

        return response()->json(['success' => true, 'message' => 'Order recorded.', 'order_id' => $orderId, 'print_url' => route('admin.tickets.print', $orderId)]);
    }

    /**
     * Shared by storeOrder() (Cash/UPI/Bank, called directly) and
     * DonationController::createDonationIfApprovedPurchaseUnrecorded() (EFT Terminal, called
     * once Linkly confirms approval) — the one place a TicketOrder + its TicketOrderItems +
     * TicketStubs are actually created, so both payment paths can never drift into building
     * an order differently.
     *
     * @param array<int, array{ticket_id: ?int, name: string, price: float, quantity: int}> $cart
     */
    public function createOrderFromCart(
        array $cart,
        ?string $customerName,
        ?string $email,
        ?string $mobile,
        string $paymentMethod,
        string $paymentStatus,
        ?string $transactionId
    ): int {
        $total = 0;
        foreach ($cart as $line) {
            $total += (float) $line['price'] * (int) $line['quantity'];
        }

        $order = TicketOrder::create([
            'customer_name' => $customerName,
            'email' => $email,
            'mobile' => $mobile,
            'total_amount' => round($total, 2),
            'payment_method' => $paymentMethod,
            'payment_status' => $paymentStatus,
            'transaction_id' => $transactionId,
            'order_date' => now()->toDateString(),
            'sold_by' => Auth::id(),
        ]);

        foreach ($cart as $line) {
            $quantity = (int) $line['quantity'];
            if ($quantity < 1) {
                continue;
            }
            $item = TicketOrderItem::create([
                'ticket_order_id' => $order->id,
                'ticket_id' => $line['ticket_id'] ?? null,
                'ticket_name' => $line['name'],
                'unit_price' => $line['price'],
                'quantity' => $quantity,
                'line_total' => round((float) $line['price'] * $quantity, 2),
            ]);
            TicketStub::createForItem($item, $quantity);
        }

        AuditLogService::log("Sold ticket order #{$order->id} for {$order->total_amount} ({$paymentMethod})");

        return $order->id;
    }

    /**
     * Called by DonationController's EFT auto-create fallback (see
     * createDonationIfApprovedPurchaseUnrecorded()) once a ticket-order Purchase is confirmed
     * approved — the cart was captured in the ledger row's meta at startEftCharge() time,
     * the same way donor details are for a donation.
     */
    public function createOrderFromLedgerMeta(array $meta, string $transactionId): ?int
    {
        $cart = $meta['cart'] ?? [];
        if (empty($cart)) {
            return null;
        }

        return $this->createOrderFromCart(
            $cart,
            $meta['customer_name'] ?? null,
            $meta['email'] ?? null,
            $meta['mobile'] ?? null,
            'EFT Terminal',
            'Paid',
            $transactionId
        );
    }

    /**
     * @return array<int, array{ticket_id: ?int, name: string, price: float, quantity: int}>
     */
    private function decodeCart(string $cartJson): array
    {
        $cart = json_decode($cartJson, true);
        if (!is_array($cart)) {
            return [];
        }

        return array_values(array_filter(array_map(function ($line) {
            if (empty($line['name']) || !isset($line['price']) || !isset($line['quantity']) || (int) $line['quantity'] < 1) {
                return null;
            }
            return [
                'ticket_id' => isset($line['ticket_id']) ? (int) $line['ticket_id'] : null,
                'name' => (string) $line['name'],
                'price' => (float) $line['price'],
                'quantity' => (int) $line['quantity'],
            ];
        }, $cart)));
    }

    /**
     * Ticket Sales — the "view" section, listing past orders (mirrors Manage Donations).
     */
    public function manageOrders(Request $request)
    {
        $activeRole = $this->activeRole();
        if (!RolePermission::can($activeRole, 'tickets', 'view')) {
            abort(403, 'Unauthorized access.');
        }

        $orders = TicketOrder::with(['items', 'seller'])->orderByDesc('created_at')->get();
        $totalSold = $orders->where('payment_status', 'Paid')->sum('total_amount');

        return view('admin.manage-ticket-orders', compact('orders', 'totalSold'));
    }

    /**
     * A print-ready page listing every stub for one order — one stub per physical ticket
     * (quantity 5 of a $5 ticket produces 5 separately-printable stubs here), so the browser's
     * own print dialog can send them straight to a receipt/label printer.
     */
    public function printOrder($orderId)
    {
        $activeRole = $this->activeRole();
        if (!RolePermission::can($activeRole, 'tickets', 'view') && !RolePermission::can($activeRole, 'tickets', 'add')) {
            abort(403, 'Unauthorized access.');
        }

        $order = TicketOrder::with(['items.stubs'])->findOrFail($orderId);
        $temple = \App\Models\Setting::templeBranding();

        DB::table('ticket_stubs')
            ->whereIn('id', $order->items->flatMap->stubs->pluck('id'))
            ->whereNull('printed_at')
            ->update(['printed_at' => now()]);

        return view('admin.ticket-print', compact('order', 'temple'));
    }
}
