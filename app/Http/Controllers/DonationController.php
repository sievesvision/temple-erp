<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\Setting;
use App\Models\RolePermission;
use App\Services\DonationReceiptService;
use Stripe\StripeClient;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

class DonationController extends Controller
{
    /**
     * Display the donations management panel.
     */
    public function manageDonations(Request $request)
    {
        $user = Auth::user();
        if (!$user || !RolePermission::can($user->role, 'donations', 'view')) {
            abort(403, 'Unauthorized access.');
        }

        $canAddDonation = RolePermission::can($user->role, 'donations', 'add');
        $canEditDonation = RolePermission::can($user->role, 'donations', 'edit');
        $canDeleteDonation = RolePermission::can($user->role, 'donations', 'delete');

        // Fetch Devotee Donations
        $devoteeDonations = DB::table('donations')
            ->join('devotees', 'donations.devotee_id', '=', 'devotees.devotee_id')
            ->join('users', 'devotees.user_id', '=', 'users.id')
            ->leftJoin('events', 'donations.event_id', '=', 'events.event_id')
            ->select('donations.*', 'users.name as devotee_name', 'users.email', 'users.mobile', 'events.event_name')
            ->orderBy('donation_date', 'desc')
            ->orderBy('donations.created_at', 'desc')
            ->get();

        // Fetch Guest Donations
        $guestDonations = DB::table('donations_without_logins')
            ->leftJoin('events', 'donations_without_logins.event_id', '=', 'events.event_id')
            ->select('donations_without_logins.*', 'events.event_name')
            ->orderBy('donation_date', 'desc')
            ->orderBy('donations_without_logins.created_at', 'desc')
            ->get();

        // Calculate Totals (guest total only counts settled donations — Stripe attempts that
        // are still Pending, or ended up Cancelled/Failed, don't count as money received)
        $devoteeTotal = $devoteeDonations->sum('amount');
        $guestTotal = $guestDonations->where('payment_status', 'Paid')->sum('amount');
        $ehundiTotal = DB::table('ehundis')->sum('amount') ?: 0;
        $grandTotal = $devoteeTotal + $guestTotal + $ehundiTotal;

        // Fetch e-Hundi Donations
        $ehundiDonations = DB::table('ehundis')
            ->leftJoin('devotees', 'ehundis.devotee_id', '=', 'devotees.devotee_id')
            ->leftJoin('users', 'devotees.user_id', '=', 'users.id')
            ->select('ehundis.*', 'users.name as devotee_name', 'users.email', 'users.mobile')
            ->orderBy('created_at', 'desc')
            ->get();

        // Fetch Devotees list for the dropdown
        $devotees = DB::table('devotees')
            ->join('users', 'devotees.user_id', '=', 'users.id')
            ->select('devotees.devotee_id', 'users.name', 'users.email')
            ->orderBy('users.name', 'asc')
            ->get();

        // Fetch Events list for the donation-linking dropdown
        $events = DB::table('events')
            ->orderBy('event_date', 'desc')
            ->get();

        return view('admin.manage-donations', compact(
            'devoteeDonations',
            'guestDonations',
            'ehundiDonations',
            'devoteeTotal',
            'guestTotal',
            'ehundiTotal',
            'grandTotal',
            'devotees',
            'events',
            'canAddDonation',
            'canEditDonation',
            'canDeleteDonation'
        ));
    }

    /**
     * Store a manually recorded Devotee donation.
     */
    public function storeDevoteeDonation(Request $request)
    {
        $user = Auth::user();
        if (!$user || !RolePermission::can($user->role, 'donations', 'add')) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $validated = $request->validate([
            'devotee_id' => 'required|exists:devotees,devotee_id',
            'event_id' => 'nullable|exists:events,event_id',
            'amount' => 'required|numeric|min:1',
            'payment_mode' => 'required|string|in:Cash,UPI,Bank Transfer,Cheque',
            'transaction_id' => 'nullable|string|max:100',
            'remarks' => 'nullable|string|max:255',
            'donation_date' => 'required|date',
        ]);

        try {
            DB::table('donations')->insert([
                'devotee_id' => $validated['devotee_id'],
                'event_id' => $validated['event_id'] ?? null,
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_mode'],
                'payment_status' => 'Paid',
                'transaction_id' => $validated['transaction_id'] ?? 'OFFLINE-' . strtoupper(uniqid()),
                'remarks' => $validated['remarks'] ?? 'Manually recorded donation',
                'donation_date' => $validated['donation_date'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $devoteeUser = DB::table('devotees')
                ->join('users', 'devotees.user_id', '=', 'users.id')
                ->where('devotees.devotee_id', $validated['devotee_id'])
                ->select('users.name', 'users.email')
                ->first();

            DonationReceiptService::send([
                'donor_name' => $devoteeUser->name ?? 'Devotee',
                'donor_email' => $devoteeUser->email ?? null,
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_mode'],
                'purpose' => $validated['remarks'] ?? 'General Temple Fund',
                'event_id' => $validated['event_id'] ?? null,
                'donation_date' => $validated['donation_date'],
                'transaction_id' => $validated['transaction_id'] ?? null,
            ]);

            return redirect()->back()->with('success', 'Devotee donation recorded successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to record donation: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Store a manually recorded Guest donation.
     */
    public function storeGuestDonation(Request $request)
    {
        $user = Auth::user();
        if (!$user || !RolePermission::can($user->role, 'donations', 'add')) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $validated = $request->validate([
            'donor_name' => 'required|string|max:255',
            'event_id' => 'nullable|exists:events,event_id',
            'email' => 'nullable|email|max:255',
            'mobile' => 'nullable|string|max:20',
            'amount' => 'required|numeric|min:1',
            'purpose' => 'required|string|max:100',
            'purpose_details' => 'nullable|string|max:255',
            'payment_method' => 'required|string|in:Cash,UPI,Bank',
            'transaction_id' => 'nullable|string|max:100',
            'bank_name' => 'required_if:payment_method,Bank|nullable|string|max:100',
            'bank_account_no' => 'required_if:payment_method,Bank|nullable|string|max:50',
            'bank_ifsc' => 'required_if:payment_method,Bank|nullable|string|max:20',
            'bank_branch' => 'required_if:payment_method,Bank|nullable|string|max:100',
            'donation_date' => 'required|date',
        ]);

        try {
            DB::table('donations_without_logins')->insert([
                'donor_name' => $validated['donor_name'],
                'event_id' => $validated['event_id'] ?? null,
                'email' => $validated['email'] ?? null,
                'mobile' => $validated['mobile'] ?? null,
                'amount' => $validated['amount'],
                'purpose' => $validated['purpose'],
                'purpose_details' => $validated['purpose_details'] ?? null,
                'payment_method' => $validated['payment_method'],
                'transaction_id' => $validated['transaction_id'] ?? 'GUEST-' . strtoupper(uniqid()),
                'bank_name' => $validated['bank_name'] ?? null,
                'bank_account_no' => $validated['bank_account_no'] ?? null,
                'bank_ifsc' => $validated['bank_ifsc'] ?? null,
                'bank_branch' => $validated['bank_branch'] ?? null,
                'donation_date' => $validated['donation_date'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DonationReceiptService::send([
                'donor_name' => $validated['donor_name'],
                'donor_email' => $validated['email'] ?? null,
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_method'],
                'purpose' => $validated['purpose_details'] ?? $validated['purpose'],
                'event_id' => $validated['event_id'] ?? null,
                'donation_date' => $validated['donation_date'],
                'transaction_id' => $validated['transaction_id'] ?? null,
            ]);

            return redirect()->back()->with('success', 'Guest donation recorded successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to record guest donation: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Update a devotee-linked donation record.
     */
    public function updateDevoteeDonation(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user || !RolePermission::can($user->role, 'donations', 'edit')) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $validated = $request->validate([
            'event_id' => 'nullable|exists:events,event_id',
            'amount' => 'required|numeric|min:1',
            'payment_mode' => 'required|string|in:Cash,UPI,Bank Transfer,Cheque',
            'transaction_id' => 'nullable|string|max:100',
            'remarks' => 'nullable|string|max:255',
            'donation_date' => 'required|date',
        ]);

        $donation = DB::table('donations')->where('id', $id)->first();
        if (!$donation) {
            return redirect()->back()->with('error', 'Donation not found.');
        }

        DB::table('donations')->where('id', $id)->update([
            'event_id' => $validated['event_id'] ?? null,
            'amount' => $validated['amount'],
            'payment_method' => $validated['payment_mode'],
            'transaction_id' => $validated['transaction_id'] ?? $donation->transaction_id,
            'remarks' => $validated['remarks'] ?? null,
            'donation_date' => $validated['donation_date'],
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Devotee donation updated successfully.');
    }

    /**
     * Delete a devotee-linked donation record.
     */
    public function deleteDevoteeDonation($id)
    {
        $user = Auth::user();
        if (!$user || !RolePermission::can($user->role, 'donations', 'delete')) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $donation = DB::table('donations')->where('id', $id)->first();
        if (!$donation) {
            return redirect()->back()->with('error', 'Donation not found.');
        }

        DB::table('donations')->where('id', $id)->delete();

        return redirect()->back()->with('success', 'Devotee donation deleted successfully.');
    }

    /**
     * Update a guest donation record.
     */
    public function updateGuestDonation(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user || !RolePermission::can($user->role, 'donations', 'edit')) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $validated = $request->validate([
            'donor_name' => 'required|string|max:255',
            'event_id' => 'nullable|exists:events,event_id',
            'email' => 'nullable|email|max:255',
            'mobile' => 'nullable|string|max:20',
            'amount' => 'required|numeric|min:1',
            'purpose' => 'required|string|max:100',
            'purpose_details' => 'nullable|string|max:255',
            'payment_method' => 'required|string|in:Cash,UPI,Bank,Stripe',
            'payment_status' => 'required|string|in:Paid,Pending,Cancelled,Failed',
            'transaction_id' => 'nullable|string|max:100',
            'donation_date' => 'required|date',
        ]);

        $donation = DB::table('donations_without_logins')->where('id', $id)->first();
        if (!$donation) {
            return redirect()->back()->with('error', 'Donation not found.');
        }

        DB::table('donations_without_logins')->where('id', $id)->update([
            'donor_name' => $validated['donor_name'],
            'event_id' => $validated['event_id'] ?? null,
            'email' => $validated['email'] ?? null,
            'mobile' => $validated['mobile'] ?? null,
            'amount' => $validated['amount'],
            'purpose' => $validated['purpose'],
            'purpose_details' => $validated['purpose_details'] ?? null,
            'payment_method' => $validated['payment_method'],
            'payment_status' => $validated['payment_status'],
            'transaction_id' => $validated['transaction_id'] ?? $donation->transaction_id,
            'donation_date' => $validated['donation_date'],
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Guest donation updated successfully.');
    }

    /**
     * Delete a guest donation record.
     */
    public function deleteGuestDonation($id)
    {
        $user = Auth::user();
        if (!$user || !RolePermission::can($user->role, 'donations', 'delete')) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $donation = DB::table('donations_without_logins')->where('id', $id)->first();
        if (!$donation) {
            return redirect()->back()->with('error', 'Donation not found.');
        }

        DB::table('donations_without_logins')->where('id', $id)->delete();

        return redirect()->back()->with('success', 'Guest donation deleted successfully.');
    }

    /**
     * Manually resend the donation receipt email for an existing donation record
     * (devotee-linked or guest) — e.g. if the original send failed or the donor
     * asks for another copy.
     */
    public function resendReceipt(Request $request, $type, $id)
    {
        $user = Auth::user();
        if (!$user || !RolePermission::can($user->role, 'donations', 'view')) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        if ($type === 'devotee') {
            $donation = DB::table('donations')
                ->join('devotees', 'donations.devotee_id', '=', 'devotees.devotee_id')
                ->join('users', 'devotees.user_id', '=', 'users.id')
                ->where('donations.id', $id)
                ->select('donations.*', 'users.name as donor_name', 'users.email')
                ->first();

            if (!$donation) {
                return redirect()->back()->with('error', 'Donation not found.');
            }

            if (!$donation->email) {
                return redirect()->back()->with('error', 'This devotee has no email address on file — cannot resend a receipt.');
            }

            DonationReceiptService::send([
                'donor_name' => $donation->donor_name,
                'donor_email' => $donation->email,
                'amount' => $donation->amount,
                'payment_method' => $donation->payment_method,
                'purpose' => $donation->remarks ?? 'General Temple Fund',
                'event_id' => $donation->event_id,
                'donation_date' => $donation->donation_date,
                'transaction_id' => $donation->transaction_id,
            ]);
        } elseif ($type === 'guest') {
            $donation = DB::table('donations_without_logins')->where('id', $id)->first();

            if (!$donation) {
                return redirect()->back()->with('error', 'Donation not found.');
            }

            if (!$donation->email) {
                return redirect()->back()->with('error', 'This donor has no email address on file — cannot resend a receipt.');
            }

            DonationReceiptService::send([
                'donor_name' => $donation->donor_name,
                'donor_email' => $donation->email,
                'amount' => $donation->amount,
                'payment_method' => $donation->payment_method,
                'purpose' => $donation->purpose_details ?? $donation->purpose,
                'event_id' => $donation->event_id,
                'donation_date' => $donation->donation_date,
                'transaction_id' => $donation->transaction_id,
            ]);
        } else {
            return redirect()->back()->with('error', 'Invalid donation type.');
        }

        return redirect()->back()->with('success', 'Receipt email resent successfully.');
    }

    /**
     * Approve/verify a self-service guest donation that was submitted as Bank Transfer or
     * Cash at Temple — these sit as 'Pending' until an admin confirms the money was actually
     * received, unlike Online Payment (Stripe) which is auto-verified via the payment gateway.
     * Approving flips it to 'Paid' (so it counts in the totals) and sends the donation
     * receipt for the first time, since it wasn't sent while still unconfirmed.
     */
    public function approveGuestDonation($id)
    {
        $user = Auth::user();
        if (!$user || !RolePermission::can($user->role, 'donations', 'edit')) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $donation = DB::table('donations_without_logins')->where('id', $id)->first();
        if (!$donation) {
            return redirect()->back()->with('error', 'Donation not found.');
        }

        if ($donation->payment_status !== 'Pending') {
            return redirect()->back()->with('error', 'Only pending donations can be approved.');
        }

        DB::table('donations_without_logins')->where('id', $id)->update([
            'payment_status' => 'Paid',
            'updated_at' => now(),
        ]);

        DonationReceiptService::send([
            'donor_name' => $donation->donor_name,
            'donor_email' => $donation->email,
            'amount' => $donation->amount,
            'payment_method' => $donation->payment_method,
            'purpose' => $donation->purpose_details ?? $donation->purpose,
            'event_id' => $donation->event_id,
            'donation_date' => $donation->donation_date,
            'transaction_id' => $donation->transaction_id,
        ]);

        return redirect()->back()->with('success', 'Donation approved and marked as received.');
    }

    /**
     * Store a public donation (no login required) — general fund or tied to a specific event.
     * Covers all three donor-facing options: Bank Transfer, Cash at Temple, and Online Payment.
     */
    public function storePublic(Request $request)
    {
        $validated = $request->validate([
            'donor_name' => 'required|string|max:255',
            'event_id' => 'nullable|exists:events,event_id',
            'email' => 'nullable|email|max:255',
            'mobile' => 'nullable|string|max:20',
            'amount' => 'required|numeric|min:1',
            'purpose' => 'required|string|max:255',
            'purpose_details' => 'nullable|string|max:255',
            'payment_method' => 'required|in:Bank,Cash,Stripe',
            'transaction_id' => 'nullable|string|max:100',
        ]);

        if ($validated['payment_method'] === 'Stripe') {
            return $this->startStripeCheckout($validated);
        }

        // Bank Transfer and Cash at Temple are self-reported by the donor — nobody has
        // actually confirmed the money changed hands yet, so these sit as 'Pending' until
        // an admin approves them (see approveGuestDonation()). Online Payment (Stripe) is
        // the only method that's auto-verified, since Stripe's own API confirms the charge.
        DB::table('donations_without_logins')->insert([
            'donor_name' => $validated['donor_name'],
            'event_id' => $validated['event_id'] ?? null,
            'email' => $validated['email'] ?? null,
            'mobile' => $validated['mobile'] ?? null,
            'amount' => $validated['amount'],
            'purpose' => $validated['purpose'],
            'purpose_details' => $validated['purpose_details'] ?? null,
            'payment_method' => $validated['payment_method'],
            'payment_status' => 'Pending',
            'transaction_id' => $validated['transaction_id'] ?? strtoupper($validated['payment_method']) . '-' . strtoupper(uniqid()),
            'bank_name' => null,
            'bank_account_no' => null,
            'bank_ifsc' => null,
            'bank_branch' => null,
            'donation_date' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $currency = Setting::get('currency_code', 'AUD');
        $message = 'Thank you! Your donation of ' . $currency . ' ' . number_format($validated['amount'], 2) . ' has been recorded and is pending confirmation.';

        if ($validated['payment_method'] === 'Bank') {
            $message = 'Thank you! Please complete your bank transfer using the details shown. Your donation of ' . $currency . ' ' . number_format($validated['amount'], 2) . ' will be confirmed once we verify the transfer, and a receipt will be emailed to you then.';
        } elseif ($validated['payment_method'] === 'Cash') {
            $message = 'Thank you! Your pledge of ' . $currency . ' ' . number_format($validated['amount'], 2) . ' has been recorded. Please hand your offering to the temple counter — a receipt will be emailed to you once it is confirmed.';
        }

        // No receipt is sent here — Bank/Cash donations are still unverified at this point.
        // The receipt goes out from approveGuestDonation() once an admin confirms it.

        return redirect()->back()->with('success_donation', $message);
    }

    /**
     * Create a Stripe Checkout Session for an online donation and redirect the donor to it.
     * A 'Pending' row is inserted first (keyed by the Checkout Session id) so the success
     * callback and the webhook both have something to flip to 'Paid' — neither one inserts.
     */
    private function startStripeCheckout(array $validated)
    {
        if (!Setting::get('stripe_enabled', true) || !config('services.stripe.secret')) {
            return redirect()->back()->with('error', 'Online payment is currently unavailable. Please choose Bank Transfer or Cash at Temple instead.')->withInput();
        }

        $donationId = DB::table('donations_without_logins')->insertGetId([
            'donor_name' => $validated['donor_name'],
            'event_id' => $validated['event_id'] ?? null,
            'email' => $validated['email'] ?? null,
            'mobile' => $validated['mobile'] ?? null,
            'amount' => $validated['amount'],
            'purpose' => $validated['purpose'],
            'purpose_details' => $validated['purpose_details'] ?? null,
            'payment_method' => 'Stripe',
            'payment_status' => 'Pending',
            'transaction_id' => null,
            'bank_name' => null,
            'bank_account_no' => null,
            'bank_ifsc' => null,
            'bank_branch' => null,
            'donation_date' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $currency = strtolower(Setting::get('currency_code', 'AUD'));
        $templeName = Setting::get('temple_name', 'Temple Donation');

        try {
            $stripe = new StripeClient(config('services.stripe.secret'));
            $session = $stripe->checkout->sessions->create([
                'mode' => 'payment',
                'payment_method_types' => ['card'],
                'customer_email' => $validated['email'] ?? null,
                'line_items' => [[
                    'price_data' => [
                        'currency' => $currency,
                        'unit_amount' => (int) round($validated['amount'] * 100),
                        'product_data' => [
                            'name' => $validated['purpose'] . ' — ' . $templeName,
                        ],
                    ],
                    'quantity' => 1,
                ]],
                'success_url' => route('donate.stripe.success') . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('donate.stripe.cancel', ['donation' => $donationId]),
                'metadata' => ['donation_id' => (string) $donationId],
            ]);
        } catch (\Exception $e) {
            DB::table('donations_without_logins')->where('id', $donationId)->update(['payment_status' => 'Failed', 'updated_at' => now()]);
            Log::error('Stripe checkout session creation failed: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Unable to start the online payment right now. Please try Bank Transfer or Cash at Temple instead.')->withInput();
        }

        DB::table('donations_without_logins')->where('id', $donationId)->update([
            'transaction_id' => $session->id,
            'updated_at' => now(),
        ]);

        return redirect($session->url);
    }

    /**
     * Stripe redirects the donor here after a successful Checkout Session.
     */
    public function stripeSuccess(Request $request)
    {
        $sessionId = $request->query('session_id');
        if (!$sessionId) {
            return redirect()->route('home')->with('error', 'Missing payment session.');
        }

        $donation = DB::table('donations_without_logins')->where('transaction_id', $sessionId)->first();
        if (!$donation) {
            return redirect()->route('home')->with('error', 'We could not find that donation.');
        }

        // The webhook may have already confirmed it — treat that as equally valid.
        if ($donation->payment_status !== 'Paid') {
            try {
                $stripe = new StripeClient(config('services.stripe.secret'));
                $session = $stripe->checkout->sessions->retrieve($sessionId);
            } catch (\Exception $e) {
                Log::error('Stripe session verification failed: ' . $e->getMessage());
                return redirect()->route('home')->with('error', 'Could not verify your payment. Please contact the temple office if you were charged.');
            }

            if ($session->payment_status === 'paid') {
                // Guarded by payment_status != 'Paid' so that if the webhook already flipped
                // this donation to Paid a moment earlier, this update (and therefore the
                // receipt email below) is skipped — avoids sending the receipt twice.
                $justConfirmed = DB::table('donations_without_logins')
                    ->where('id', $donation->id)
                    ->where('payment_status', '!=', 'Paid')
                    ->update(['payment_status' => 'Paid', 'updated_at' => now()]);

                if ($justConfirmed) {
                    DonationReceiptService::send([
                        'donor_name' => $donation->donor_name,
                        'donor_email' => $donation->email,
                        'amount' => $donation->amount,
                        'payment_method' => 'Stripe',
                        'purpose' => $donation->purpose_details ?? $donation->purpose,
                        'event_id' => $donation->event_id,
                        'donation_date' => $donation->donation_date,
                        'transaction_id' => $donation->transaction_id,
                    ]);
                }
            } else {
                return redirect()->route('home')->with('error', 'Payment was not completed.');
            }
        }

        $currency = Setting::get('currency_code', 'AUD');
        return redirect()->route('home')->with('success_donation', 'Thank you! Your donation of ' . $currency . ' ' . number_format($donation->amount, 2) . ' was received successfully via Stripe.');
    }

    /**
     * Stripe redirects the donor here if they abandon Checkout without paying.
     */
    public function stripeCancel(Request $request)
    {
        $donationId = $request->query('donation');
        if ($donationId) {
            DB::table('donations_without_logins')
                ->where('id', $donationId)
                ->where('payment_status', 'Pending')
                ->update(['payment_status' => 'Cancelled', 'updated_at' => now()]);
        }

        return redirect()->route('home')->with('error', 'Your donation was cancelled — no payment was taken.');
    }

    /**
     * Authoritative confirmation from Stripe, independent of whether the donor's browser
     * ever made it back to the success page. Excluded from CSRF verification (see
     * bootstrap/app.php) since Stripe posts here directly, not via a browser form.
     */
    public function stripeWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook_secret');

        try {
            if ($webhookSecret) {
                $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
            } else {
                $event = json_decode($payload);
                Log::warning('Stripe webhook received without STRIPE_WEBHOOK_SECRET configured — signature was not verified.');
            }
        } catch (SignatureVerificationException | \UnexpectedValueException $e) {
            Log::warning('Stripe webhook signature verification failed: ' . $e->getMessage());
            return response('Invalid signature', 400);
        }

        $sessionId = $event->data->object->id ?? null;
        if (($event->type ?? null) === 'checkout.session.completed' && $sessionId) {
            // Guarded by payment_status != 'Paid' so that if the donor's own browser already
            // confirmed via stripeSuccess(), this update (and the receipt email) is skipped.
            $justConfirmed = DB::table('donations_without_logins')
                ->where('transaction_id', $sessionId)
                ->where('payment_status', '!=', 'Paid')
                ->update(['payment_status' => 'Paid', 'updated_at' => now()]);

            if ($justConfirmed) {
                $donation = DB::table('donations_without_logins')->where('transaction_id', $sessionId)->first();
                if ($donation) {
                    DonationReceiptService::send([
                        'donor_name' => $donation->donor_name,
                        'donor_email' => $donation->email,
                        'amount' => $donation->amount,
                        'payment_method' => 'Stripe',
                        'purpose' => $donation->purpose_details ?? $donation->purpose,
                        'event_id' => $donation->event_id,
                        'donation_date' => $donation->donation_date,
                        'transaction_id' => $donation->transaction_id,
                    ]);
                }
            }
        }

        return response('OK', 200);
    }
}
