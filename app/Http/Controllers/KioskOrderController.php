<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\Ticket;
use Illuminate\Http\Request;

/**
 * The kiosk's own customer-facing bootstrap endpoint — read-only, device-auth only (no
 * session-login needed, see routes/web.php's kiosk.device-only group). Everything that
 * actually creates an order/donation reuses the existing TicketController/DonationController/
 * CbaSciController methods directly (new routes pointed at them — see routes/web.php) rather
 * than going through this controller at all.
 */
class KioskOrderController extends Controller
{
    public function bootstrap(Request $request)
    {
        $device = $request->attributes->get('kiosk_device');
        $temple = Setting::templeBranding();

        // Which EFT provider (Linkly vs mx51/CBA SCI) this device's card payments use — the
        // frontend needs this to know whether to drive the Action Framework (mx51) flow or
        // the fixed-key (Linkly) flow when "EFT Terminal" is picked at checkout.
        $terminal = $device->effectiveEftTerminal();
        $eft = $terminal ? ['terminal_id' => $terminal->id, 'provider' => $terminal->provider] : null;

        if ($device->module === 'tickets') {
            return response()->json([
                'module' => 'tickets',
                'temple' => $temple,
                'payment_methods' => $device->effectivePaymentMethods(),
                'eft' => $eft,
                'catalog' => Ticket::active()->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'price', 'image', 'background_color']),
            ]);
        }

        if ($device->module === 'donations') {
            $event = $device->event;
            // Neither storeGuestDonation() nor startEftCharge() enforce this themselves
            // (confirmed by reading both) — this is the one place a donations-module kiosk's
            // own event-closed state is actually checked, without touching either method.
            if (!$event || $event->isClosedForDonations()) {
                return response()->json(['module' => 'donations', 'closed' => true, 'temple' => $temple]);
            }

            return response()->json([
                'module' => 'donations',
                'temple' => $temple,
                'payment_methods' => $device->effectivePaymentMethods(),
                'eft' => $eft,
                'event' => [
                    'id' => $event->event_id,
                    'name' => $event->event_name,
                    'require_email' => (bool) $event->require_donor_email,
                    'require_mobile' => (bool) $event->require_donor_mobile,
                ],
                'donation_options' => $event->donationOptions()->get(),
            ]);
        }

        // No module assigned yet — nothing for a customer to do here (only an Admin can
        // configure this device's module, see App\Services\KioskAccess::canConfigure()).
        return response()->json(['module' => null, 'temple' => $temple]);
    }
}
