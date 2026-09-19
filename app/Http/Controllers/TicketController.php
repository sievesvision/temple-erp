<?php

namespace App\Http\Controllers;

use App\Models\LinklyTransaction;
use App\Models\RolePermission;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketOrderItem;
use App\Models\TicketStub;
use App\Services\AuditLogService;
use App\Services\LinklyEftService;
use App\Services\TicketControllerLevel;
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
     * A Ticket Controller's tier for the current user — null if the active role isn't
     * Ticket Controller at all (e.g. an Admin/Committee user viewing the console).
     */
    private function controllerLevel($user, ?string $activeRole): ?string
    {
        return $activeRole === 'Ticket Controller' ? TicketControllerLevel::of($user->id) : null;
    }

    /**
     * The full Ticket Console (Settings/catalog, Sales, EFTPOS, Ticket Controllers) —
     * reachable by Admin, by any role RolePermission grants 'tickets' view to (e.g.
     * Committee), or by a Ticket Controller at 'admin' level. A view/entry-level Ticket
     * Controller never sees the console at all — they land straight on the kiosk instead,
     * mirroring EventConsoleController::show()'s pos-level redirect for Event Coordinator.
     */
    private function canManageTicketConsole($user, ?string $activeRole, ?string $controllerLevel = null): bool
    {
        if ($activeRole === 'Admin' || RolePermission::can($activeRole, 'tickets', 'edit')) {
            return true;
        }

        return TicketControllerLevel::atLeast($controllerLevel ?? $this->controllerLevel($user, $activeRole), 'admin');
    }

    /**
     * Manage Tickets — the Ticket Console (catalog, sales, EFTPOS, controller assignment).
     * A view/entry-level Ticket Controller is redirected straight to the kiosk (see
     * TicketControllerLevel) since the console itself is an admin-tier concern.
     */
    public function manageTickets(Request $request)
    {
        $user = Auth::user();
        $activeRole = $this->activeRole();
        $controllerLevel = $this->controllerLevel($user, $activeRole);

        if (in_array($controllerLevel, ['view', 'entry'], true)) {
            return redirect()->route('admin.tickets.pos');
        }

        if (!$activeRole || !(RolePermission::can($activeRole, 'tickets', 'view') || $this->canManageTicketConsole($user, $activeRole, $controllerLevel))) {
            abort(403, 'Unauthorized access.');
        }

        $tickets = Ticket::orderBy('sort_order')->orderBy('name')->get();
        $canManageConsole = $this->canManageTicketConsole($user, $activeRole, $controllerLevel);
        $canEdit = RolePermission::can($activeRole, 'tickets', 'edit') || $canManageConsole;
        $canAdd = RolePermission::can($activeRole, 'tickets', 'add') || $canManageConsole;
        $canDelete = RolePermission::can($activeRole, 'tickets', 'delete') || $canManageConsole;

        $orders = TicketOrder::with(['items', 'seller'])->orderByDesc('created_at')->get();
        $totalSold = $orders->where('payment_status', 'Paid')->sum('total_amount');

        $todayTotal = $orders->where('payment_status', 'Paid')
            ->where('order_date', now()->toDateString())
            ->sum('total_amount');

        $linklyPaired = \App\Services\LinklyConfigService::isPaired();
        $linklyMode = \App\Services\LinklyConfigService::mode();
        $linklyPosId = \App\Services\LinklyConfigService::posId();

        // Ticket-related Linkly transactions only — this is the shared terminal, but the
        // console should only ever show what's relevant to ticket sales (event_id is
        // always null for these, since Tickets isn't event-scoped).
        $linklyTransactions = LinklyTransaction::whereNull('event_id')
            ->where(function ($q) {
                $q->where('donation_type', 'ticket_order')
                    ->orWhere('txn_type', 'logon');
            })
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        $ticketControllers = DB::table('ticket_controllers')
            ->join('users', 'ticket_controllers.user_id', '=', 'users.id')
            ->select('users.id', 'users.name', 'users.email', 'users.status', 'users.last_login_at', 'users.last_reset_email_sent_at', 'ticket_controllers.level')
            ->orderBy('users.name')
            ->get();

        $allUsersForControllers = DB::table('users')->orderBy('name')->get(['id', 'name', 'email']);

        return view('admin.ticket-console', compact(
            'tickets',
            'canEdit',
            'canAdd',
            'canDelete',
            'canManageConsole',
            'activeRole',
            'orders',
            'totalSold',
            'todayTotal',
            'linklyPaired',
            'linklyMode',
            'linklyPosId',
            'linklyTransactions',
            'ticketControllers',
            'allUsersForControllers'
        ));
    }

    public function storeTicket(Request $request)
    {
        $user = Auth::user();
        $activeRole = $this->activeRole();
        if (!RolePermission::can($activeRole, 'tickets', 'add') && !$this->canManageTicketConsole($user, $activeRole)) {
            abort(403, 'Unauthorized access.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'price' => 'required|numeric|min:0.01',
            'status' => 'required|in:Active,Inactive',
            'sort_order' => 'nullable|integer|min:0',
            'background_color' => 'nullable|string|max:20',
            'image' => 'nullable|string|max:255',
        ]);

        Ticket::create($validated);
        AuditLogService::log("Created ticket type '{$validated['name']}' ({$validated['price']})");

        return redirect()->back()->with('success', 'Ticket type added.');
    }

    public function updateTicket(Request $request, $id)
    {
        $user = Auth::user();
        $activeRole = $this->activeRole();
        if (!RolePermission::can($activeRole, 'tickets', 'edit') && !$this->canManageTicketConsole($user, $activeRole)) {
            abort(403, 'Unauthorized access.');
        }

        $ticket = Ticket::findOrFail($id);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'price' => 'required|numeric|min:0.01',
            'status' => 'required|in:Active,Inactive',
            'sort_order' => 'nullable|integer|min:0',
            'background_color' => 'nullable|string|max:20',
            'image' => 'nullable|string|max:255',
        ]);

        $ticket->update($validated);
        AuditLogService::log("Updated ticket type '{$ticket->name}'");

        return redirect()->back()->with('success', 'Ticket type updated.');
    }

    /**
     * Whether the given user/active-role may open the kiosk and sell tickets — the normal
     * RolePermission grid (Admin/Committee/etc.), or a Ticket Controller at 'entry' level or
     * above (never 'view' alone, which lands on the kiosk per manageTickets()'s redirect but
     * can't actually transact — same read-only-vs-entry split as EventCoordinatorLevel).
     */
    private function canSellTickets($user, ?string $activeRole): bool
    {
        if (RolePermission::can($activeRole, 'tickets', 'add') || $this->canManageTicketConsole($user, $activeRole)) {
            return true;
        }

        return TicketControllerLevel::atLeast($this->controllerLevel($user, $activeRole), 'entry');
    }

    public function deleteTicket($id)
    {
        $user = Auth::user();
        $activeRole = $this->activeRole();
        if (!RolePermission::can($activeRole, 'tickets', 'delete') && !$this->canManageTicketConsole($user, $activeRole)) {
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
        $user = Auth::user();
        $activeRole = $this->activeRole();
        $controllerLevel = $this->controllerLevel($user, $activeRole);
        // A view-level Ticket Controller still lands here (see manageTickets()'s redirect)
        // even though they can't complete a sale — the "Complete Sale" action itself is
        // gated by canSellTickets() separately, same read-only-landing pattern the console
        // uses for a view-level coordinator on the donations table.
        if (!$this->canSellTickets($user, $activeRole) && $controllerLevel === null) {
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

        $canSell = $this->canSellTickets($user, $activeRole);
        $canManageConsole = $this->canManageTicketConsole($user, $activeRole, $controllerLevel);

        return view('admin.ticket-pos', compact('tickets', 'paymentMethods', 'temple', 'pendingEftRecovery', 'canSell', 'canManageConsole'));
    }

    /**
     * Records a ticket order for a synchronous payment method (Cash/UPI/Bank Transfer) —
     * the EFT Terminal path instead goes through DonationController::startEftCharge()/
     * pollEftCharge(), which calls createOrderFromCart() below once Linkly confirms approval.
     */
    public function storeOrder(Request $request)
    {
        $user = Auth::user();
        $activeRole = $this->activeRole();
        if (!$this->canSellTickets($user, $activeRole)) {
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
     * Ticket Sales now lives as a pane inside the Ticket Console — this route is kept only
     * so an old bookmark/link still lands somewhere sensible.
     */
    public function manageOrders(Request $request)
    {
        return redirect()->route('admin.tickets.index');
    }

    /**
     * A print-ready page listing every stub for one order — one stub per physical ticket
     * (quantity 5 of a $5 ticket produces 5 separately-printable stubs here), so the browser's
     * own print dialog can send them straight to a receipt/label printer.
     */
    public function printOrder($orderId)
    {
        $user = Auth::user();
        $activeRole = $this->activeRole();
        if (!RolePermission::can($activeRole, 'tickets', 'view') && !$this->canSellTickets($user, $activeRole)) {
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

    /**
     * ---------- EFTPOS console actions ----------
     * These mirror DonationController's refund/logon/reprint/pair actions but for the
     * shared terminal's ticket-related transactions specifically (event_id always null).
     * Gated the same "admin tier" way as DonationController::canManageEftForEvent() — Admin/
     * Committee-equivalent via the 'tickets' edit grant, or a Ticket Controller at 'admin'
     * level.
     */
    private function eftNotificationUri(): string
    {
        return url('/admin/eft/webhook/{{type}}');
    }

    public function pairEft(Request $request)
    {
        $user = Auth::user();
        $activeRole = $this->activeRole();
        if (!$this->canManageTicketConsole($user, $activeRole)) {
            abort(403, 'Unauthorized access.');
        }

        $validated = $request->validate(['pair_code' => 'required|string|max:10']);

        $result = LinklyEftService::pair($validated['pair_code']);
        AuditLogService::log('EFT terminal pairing ' . ($result['success'] ? 'succeeded' : 'failed') . ' (from ticket console)');

        return redirect()->back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function logonEft(Request $request)
    {
        $user = Auth::user();
        $activeRole = $this->activeRole();
        if (!$this->canManageTicketConsole($user, $activeRole)) {
            abort(403, 'Unauthorized access.');
        }

        $result = LinklyEftService::logon();

        LinklyTransaction::create([
            'pos_txn_ref' => 'LGN' . now()->format('mdHis') . rand(100, 999),
            'txn_type' => 'logon',
            'status' => $result['success'] ? 'approved' : 'failed',
            'response_text' => $result['message'],
            'initiated_by' => $user->id,
            'authorised_by' => $user->id,
        ]);

        AuditLogService::log('Linkly terminal Logon: ' . $result['message']);

        return redirect()->back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function reprintEftReceipt(Request $request, string $sessionId)
    {
        $user = Auth::user();
        $activeRole = $this->activeRole();
        if (!$this->canManageTicketConsole($user, $activeRole)) {
            abort(403, 'Unauthorized access.');
        }

        $result = LinklyEftService::reprintReceipt($sessionId);
        AuditLogService::log('Linkly receipt reprint requested for session ' . $sessionId . ': ' . $result['message']);

        return redirect()->back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function refundEftCharge(Request $request, int $transactionId)
    {
        $user = Auth::user();
        $activeRole = $this->activeRole();
        if (!$this->canManageTicketConsole($user, $activeRole)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
        }

        $original = LinklyTransaction::find($transactionId);
        if (!$original || $original->txn_type !== 'purchase') {
            return response()->json(['success' => false, 'message' => 'Original transaction not found.'], 404);
        }

        if ($original->status !== 'approved') {
            return response()->json(['success' => false, 'message' => 'Only an approved purchase can be refunded.'], 422);
        }

        $alreadyRefunded = LinklyTransaction::where('original_transaction_id', $original->id)
            ->whereIn('status', ['initiated', 'in_progress', 'approved'])
            ->exists();
        if ($alreadyRefunded) {
            return response()->json(['success' => false, 'message' => 'This transaction has already been refunded, or a refund is already in progress.'], 422);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:' . (float) $original->amount,
            'client_ref' => 'required|string|max:64',
        ]);

        $refundTxnRef = 'RFD' . now()->format('mdHis') . rand(100, 999);

        $result = LinklyEftService::startRefund(
            (float) $validated['amount'],
            $refundTxnRef,
            $original->pos_txn_ref,
            $original->currency_code ?? Setting::get('currency_code', 'AUD'),
            $this->eftNotificationUri(),
            $user->id,
            $user->name
        );

        if ($result['success']) {
            LinklyTransaction::create([
                'pos_txn_ref' => $refundTxnRef,
                'client_ref' => $validated['client_ref'],
                'linkly_session_id' => $result['session_id'],
                'txn_type' => 'refund',
                'donation_type' => $original->donation_type,
                'donation_id' => $original->donation_id,
                'amount' => $validated['amount'],
                'currency_code' => $original->currency_code,
                'status' => 'initiated',
                'original_transaction_id' => $original->id,
                'initiated_by' => $user->id,
                'authorised_by' => $user->id,
            ]);
        }

        return response()->json($result, $result['success'] ? 200 : 422);
    }
}
