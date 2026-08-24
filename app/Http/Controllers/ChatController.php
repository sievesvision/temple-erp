<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\Setting;
use App\Services\AuditLogService;
use App\Services\NotificationService;

class ChatController extends Controller
{
    /**
     * Get or create the active chat session for the logged-in devotee.
     */
    public function getSession()
    {
        $userId = Auth::id();
        
        $session = DB::table('chat_sessions')
            ->where('devotee_id', $userId)
            ->where('status', 'active')
            ->first();

        if (!$session) {
            $sessionId = DB::table('chat_sessions')->insertGetId([
                'devotee_id' => $userId,
                'status' => 'active',
                'mode' => 'bot',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Save welcome message
            DB::table('chat_messages')->insert([
                'session_id' => $sessionId,
                'sender_type' => 'bot',
                'sender_id' => null,
                'message_text' => "Namaste! Welcome to the Shree Mandir Assistant. 🪔 How can I assist you today?",
                'metadata' => json_encode([
                    'options' => [
                        ['label' => '🛕 Temple Timings', 'value' => 'Temple Timings'],
                        ['label' => '📅 Next Event', 'value' => 'Next Event'],
                        ['label' => '🙏 Book Pooja', 'value' => 'Book Pooja'],
                        ['label' => '📞 Contact Team', 'value' => 'Contact Team'],
                    ]
                ]),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            $session = DB::table('chat_sessions')->where('session_id', $sessionId)->first();
        }

        return response()->json(['success' => true, 'session' => $session]);
    }

    /**
     * Get chat messages for the active session.
     */
    public function getMessages(Request $request)
    {
        $userId = Auth::id();
        
        $session = DB::table('chat_sessions')
            ->where('devotee_id', $userId)
            ->where('status', 'active')
            ->first();

        if (!$session) {
            return response()->json(['success' => true, 'messages' => []]);
        }

        $messages = DB::table('chat_messages')
            ->where('session_id', $session->session_id)
            ->orderBy('created_at', 'asc')
            ->orderBy('message_id', 'asc')
            ->get()
            ->map(function($msg) {
                $msg->metadata = json_decode($msg->metadata, true);
                return $msg;
            });

        return response()->json(['success' => true, 'messages' => $messages, 'mode' => $session->mode]);
    }

    /**
     * Send a message from the devotee.
     */
    public function sendMessage(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:1000'
        ]);

        $userId = Auth::id();
        $messageText = trim($request->message);

        // Get active session
        $session = DB::table('chat_sessions')
            ->where('devotee_id', $userId)
            ->where('status', 'active')
            ->first();

        if (!$session) {
            return response()->json(['success' => false, 'message' => 'No active session found.']);
        }

        // Save devotee message
        DB::table('chat_messages')->insert([
            'session_id' => $session->session_id,
            'sender_type' => 'devotee',
            'sender_id' => $userId,
            'message_text' => $messageText,
            'metadata' => null,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // If session mode is agent, we do not respond auto-actively
        if ($session->mode === 'agent') {
            // Notify staff about new message
            NotificationService::notifyAdmin("Support Chat: New message from devotee.");
            return response()->json(['success' => true, 'status' => 'waiting_agent']);
        }

        // Process Bot Logic
        $this->processBotResponse($session->session_id, $messageText);

        return response()->json(['success' => true]);
    }

    /**
     * Devotee closes/ends the active conversation.
     */
    public function endSession()
    {
        $userId = Auth::id();
        $session = DB::table('chat_sessions')
            ->where('devotee_id', $userId)
            ->where('status', 'active')
            ->first();

        if ($session) {
            DB::table('chat_sessions')
                ->where('session_id', $session->session_id)
                ->update([
                    'status' => 'ended',
                    'updated_at' => now()
                ]);

            session()->forget('chatbot_booking_state');
            AuditLogService::log("Devotee ended support chat session #{$session->session_id}");
        }

        return response()->json(['success' => true]);
    }

    /**
     * Staff APIs: List active chat sessions.
     */
    public function staffGetActiveSessions()
    {
        $sessions = DB::table('chat_sessions')
            ->join('users', 'chat_sessions.devotee_id', '=', 'users.id')
            ->where('chat_sessions.status', 'active')
            ->where('chat_sessions.mode', 'agent')
            ->select('chat_sessions.*', 'users.name as devotee_name', 'users.email as devotee_email')
            ->orderBy('chat_sessions.updated_at', 'desc')
            ->get();

        foreach ($sessions as $session) {
            $lastMessage = DB::table('chat_messages')
                ->where('session_id', $session->session_id)
                ->orderBy('created_at', 'desc')
                ->orderBy('message_id', 'desc')
                ->first();

            $session->last_sender_type = $lastMessage ? $lastMessage->sender_type : null;
            $session->last_message_text = $lastMessage ? $lastMessage->message_text : '';
        }

        return response()->json(['success' => true, 'sessions' => $sessions]);
    }

    /**
     * Staff APIs: List ended chat sessions (History).
     */
    public function staffGetEndedSessions()
    {
        $sessions = DB::table('chat_sessions')
            ->join('users', 'chat_sessions.devotee_id', '=', 'users.id')
            ->where('chat_sessions.status', 'ended')
            ->select('chat_sessions.*', 'users.name as devotee_name', 'users.email as devotee_email')
            ->orderBy('chat_sessions.updated_at', 'desc')
            ->get();

        foreach ($sessions as $session) {
            $lastMessage = DB::table('chat_messages')
                ->where('session_id', $session->session_id)
                ->orderBy('created_at', 'desc')
                ->orderBy('message_id', 'desc')
                ->first();

            $session->last_sender_type = $lastMessage ? $lastMessage->sender_type : null;
            $session->last_message_text = $lastMessage ? $lastMessage->message_text : '';
        }

        return response()->json(['success' => true, 'sessions' => $sessions]);
    }

    /**
     * Staff APIs: Get messages of a specific session.
     */
    public function staffGetMessages($sessionId)
    {
        $session = DB::table('chat_sessions')
            ->where('session_id', $sessionId)
            ->first();

        if (!$session) {
            return response()->json(['success' => false, 'message' => 'Session not found.']);
        }

        $messages = DB::table('chat_messages')
            ->where('session_id', $sessionId)
            ->orderBy('created_at', 'asc')
            ->orderBy('message_id', 'asc')
            ->get()
            ->map(function($msg) {
                $msg->metadata = json_decode($msg->metadata, true);
                return $msg;
            });

        return response()->json([
            'success' => true, 
            'messages' => $messages,
            'session_status' => $session->status
        ]);
    }

    /**
     * Staff APIs: Send reply to devotee.
     */
    public function staffSendReply(Request $request, $sessionId)
    {
        $request->validate([
            'message' => 'required|string|max:1000'
        ]);

        $session = DB::table('chat_sessions')
            ->where('session_id', $sessionId)
            ->where('status', 'active')
            ->first();

        if (!$session) {
            return response()->json(['success' => false, 'message' => 'Active session not found.']);
        }

        // Save staff message
        DB::table('chat_messages')->insert([
            'session_id' => $sessionId,
            'sender_type' => 'staff',
            'sender_id' => Auth::id(),
            'message_text' => trim($request->message),
            'metadata' => null,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Update session timestamp
        DB::table('chat_sessions')
            ->where('session_id', $sessionId)
            ->update(['updated_at' => now()]);

        return response()->json(['success' => true]);
    }

    /**
     * Staff APIs: Close/End devotee session.
     */
    public function staffEndSession($sessionId)
    {
        DB::table('chat_sessions')
            ->where('session_id', $sessionId)
            ->update([
                'status' => 'ended',
                'updated_at' => now()
            ]);

        return response()->json(['success' => true]);
    }

    /* ---------------- BOT RESPONSES FLOW STATE MACHINE ---------------- */

    private function processBotResponse($sessionId, $input)
    {
        $state = session('chatbot_booking_state', ['step' => 'idle']);
        $normalizedInput = strtolower(trim($input));

        // If user wants to cancel booking
        if ($normalizedInput === 'cancel booking' || $normalizedInput === 'cancel pooja') {
            session()->forget('chatbot_booking_state');
            $this->sendBotMessage($sessionId, "Booking cancelled. How else can I help you?", [
                'options' => [
                    ['label' => '🛕 Temple Timings', 'value' => 'Temple Timings'],
                    ['label' => '📅 Next Event', 'value' => 'Next Event'],
                    ['label' => '🙏 Book Pooja', 'value' => 'Book Pooja'],
                    ['label' => '📞 Contact Team', 'value' => 'Contact Team'],
                ]
            ]);
            return;
        }

        // Idle state handler
        if ($state['step'] === 'idle') {
            if (str_contains($normalizedInput, 'time') || str_contains($normalizedInput, 'timings') || str_contains($normalizedInput, 'open') || str_contains($normalizedInput, 'close')) {
                $opening = Setting::get('temple_opening_time', '06:00');
                $closing = Setting::get('temple_closing_time', '21:00');
                $templeName = Setting::get('temple_name', 'Golden Temple');
                
                $msg = "🛕 **{$templeName} Timings**:\nDaily Opening: **{$opening}**\nDaily Closing: **{$closing}**\n\nYou are welcome to visit and seek blessings!";
                $this->sendBotMessage($sessionId, $msg, [
                    'options' => [
                        ['label' => '📅 Next Event', 'value' => 'Next Event'],
                        ['label' => '🙏 Book Pooja', 'value' => 'Book Pooja'],
                        ['label' => '📞 Contact Team', 'value' => 'Contact Team']
                    ]
                ]);
            }
            elseif (str_contains($normalizedInput, 'event') || str_contains($normalizedInput, 'upcoming') || str_contains($normalizedInput, 'next event')) {
                $event = DB::table('events')
                    ->where('status', 'Upcoming')
                    ->orderBy('event_date', 'asc')
                    ->first();

                if ($event) {
                    $msg = "📅 **Upcoming Event**:\nName: *{$event->event_name}*\nDate: *{$event->event_date}*\nTime: *{$event->start_time} - {$event->end_time}*\nLocation: *{$event->location}*\n\nDescription: {$event->description}";
                } else {
                    $msg = "Currently, there are no upcoming public events scheduled. Please check back later!";
                }

                $this->sendBotMessage($sessionId, $msg, [
                    'options' => [
                        ['label' => '🛕 Temple Timings', 'value' => 'Temple Timings'],
                        ['label' => '🙏 Book Pooja', 'value' => 'Book Pooja'],
                        ['label' => '📞 Contact Team', 'value' => 'Contact Team']
                    ]
                ]);
            }
            elseif (str_contains($normalizedInput, 'book') || str_contains($normalizedInput, 'pooja')) {
                // Get active poojas
                $poojas = DB::table('poojas')->where('status', 'Active')->get();
                
                if ($poojas->isEmpty()) {
                    $this->sendBotMessage($sessionId, "We apologize, but there are no active Poojas available for booking at the moment.");
                    return;
                }

                $options = [];
                foreach ($poojas as $p) {
                    $options[] = ['label' => "🙏 {$p->pooja_name} (₹{$p->pooja_fee})", 'value' => $p->pooja_id];
                }
                
                session(['chatbot_booking_state' => ['step' => 'select_pooja']]);

                $this->sendBotMessage($sessionId, "Please select the Pooja you would like to book:", [
                    'options' => $options
                ]);
            }
            elseif (str_contains($normalizedInput, 'call')) {
                $this->sendBotMessage($sessionId, "You can call us directly at **9901476678**.\nOur office hours are from **9:00 AM to 6:00 PM** (Monday to Sunday).");
            }
            elseif (str_contains($normalizedInput, 'chat') || str_contains($normalizedInput, 'agent')) {
                // Switch mode to agent
                DB::table('chat_sessions')
                    ->where('session_id', $sessionId)
                    ->update(['mode' => 'agent', 'updated_at' => now()]);

                $this->sendBotMessage($sessionId, "Connecting you to a staff agent. Please type your query, and a team member will reply shortly! 💬");
                NotificationService::notifyAdmin("Devotee requesting live chat support.");
            }
            elseif (str_contains($normalizedInput, 'contact') || str_contains($normalizedInput, 'team') || str_contains($normalizedInput, 'support')) {
                $this->sendBotMessage($sessionId, "You can contact our team using one of the following methods:", [
                    'options' => [
                        ['label' => '📞 Call Support', 'value' => 'Call Support'],
                        ['label' => '💬 Chat with Agent', 'value' => 'Chat with Agent']
                    ]
                ]);
            }
            else {
                $this->sendBotMessage($sessionId, "I didn't quite understand that. You can ask me about temple timings, next event, or booking a pooja. You can also contact our team.", [
                    'options' => [
                        ['label' => '🛕 Temple Timings', 'value' => 'Temple Timings'],
                        ['label' => '📅 Next Event', 'value' => 'Next Event'],
                        ['label' => '🙏 Book Pooja', 'value' => 'Book Pooja'],
                        ['label' => '📞 Contact Team', 'value' => 'Contact Team']
                    ]
                ]);
            }
            return;
        }

        // Select Pooja Step
        if ($state['step'] === 'select_pooja') {
            $pooja = DB::table('poojas')
                ->where('pooja_id', $input)
                ->orWhere('pooja_name', 'like', "%{$input}%")
                ->first();

            if (!$pooja) {
                $poojas = DB::table('poojas')->where('status', 'Active')->get();
                $options = [];
                foreach ($poojas as $p) {
                    $options[] = ['label' => "🙏 {$p->pooja_name} (₹{$p->pooja_fee})", 'value' => $p->pooja_id];
                }
                $options[] = ['label' => '❌ Cancel Booking', 'value' => 'Cancel Booking'];
                $this->sendBotMessage($sessionId, "Pooja not found. Please select from the list below:", [
                    'options' => $options
                ]);
                return;
            }

            session(['chatbot_booking_state' => [
                'step' => 'enter_date',
                'pooja_id' => $pooja->pooja_id,
                'pooja_name' => $pooja->pooja_name,
                'pooja_fee' => $pooja->pooja_fee,
                'online_allowed' => $pooja->online_allowed
            ]]);

            $this->sendBotMessage($sessionId, "Excellent selection: **{$pooja->pooja_name}**.\n\nPlease enter the date for the Pooja in **YYYY-MM-DD** format (e.g., " . date('Y-m-d', strtotime('+1 day')) . "):", [
                'options' => [
                    ['label' => '❌ Cancel Booking', 'value' => 'Cancel Booking']
                ]
            ]);
            return;
        }

        // Enter Date Step
        if ($state['step'] === 'enter_date') {
            $date = date('Y-m-d', strtotime($input));
            if ($date === '1970-01-01' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $input)) {
                $this->sendBotMessage($sessionId, "Invalid date format. Please enter in **YYYY-MM-DD** format (e.g. 2026-08-15):", [
                    'options' => [
                        ['label' => '❌ Cancel Booking', 'value' => 'Cancel Booking']
                    ]
                ]);
                return;
            }

            if (strtotime($date) < strtotime(date('Y-m-d'))) {
                $this->sendBotMessage($sessionId, "You cannot book a Pooja for a past date. Please enter a valid upcoming date:", [
                    'options' => [
                        ['label' => '❌ Cancel Booking', 'value' => 'Cancel Booking']
                    ]
                ]);
                return;
            }

            $state['booking_date'] = $date;
            $state['step'] = 'enter_time';
            session(['chatbot_booking_state' => $state]);

            $this->sendBotMessage($sessionId, "Date confirmed: **{$date}**.\n\nPlease choose or type a time slot (e.g., 08:00, 10:00, 16:00, 18:00):", [
                'options' => [
                    ['label' => '🌅 08:00 AM', 'value' => '08:00'],
                    ['label' => '☀️ 10:00 AM', 'value' => '10:00'],
                    ['label' => '🌆 04:00 PM', 'value' => '16:00'],
                    ['label' => '🌙 06:00 PM', 'value' => '18:00'],
                    ['label' => '❌ Cancel Booking', 'value' => 'Cancel Booking']
                ]
            ]);
            return;
        }

        // Enter Time Step
        if ($state['step'] === 'enter_time') {
            $time = trim($input);
            if (!preg_match('/^(?:2[0-3]|[01][0-9]):[0-5][0-9]$/', $time) && !preg_match('/^(?:[0-9]):[0-5][0-9]$/', $time)) {
                $this->sendBotMessage($sessionId, "Invalid time slot. Please use HH:MM format (e.g. 10:30, 18:00):", [
                    'options' => [
                        ['label' => '❌ Cancel Booking', 'value' => 'Cancel Booking']
                    ]
                ]);
                return;
            }

            $state['booking_time'] = $time;
            $state['step'] = 'enter_type';
            session(['chatbot_booking_state' => $state]);

            $options = [['label' => '🛕 Offline (At Temple)', 'value' => 'Offline']];
            if ($state['online_allowed']) {
                $options[] = ['label' => '🌐 Online (With Prasadam Delivery)', 'value' => 'Online'];
            }
            $options[] = ['label' => '❌ Cancel Booking', 'value' => 'Cancel Booking'];

            $this->sendBotMessage($sessionId, "Time slot confirmed: **{$time}**.\n\nIs this booking Online or Offline?", [
                'options' => $options
            ]);
            return;
        }

        // Enter Type Step
        if ($state['step'] === 'enter_type') {
            $type = ucfirst(strtolower(trim($input)));
            if ($type !== 'Online' && $type !== 'Offline') {
                $options = [['label' => '🛕 Offline (At Temple)', 'value' => 'Offline']];
                if ($state['online_allowed']) {
                    $options[] = ['label' => '🌐 Online (With Prasadam Delivery)', 'value' => 'Online'];
                }
                $options[] = ['label' => '❌ Cancel Booking', 'value' => 'Cancel Booking'];
                $this->sendBotMessage($sessionId, "Please select Online or Offline:", [
                    'options' => $options
                ]);
                return;
            }

            if ($type === 'Online' && !$state['online_allowed']) {
                $this->sendBotMessage($sessionId, "Online bookings are not supported for this Pooja. Please select Offline:", [
                    'options' => [
                        ['label' => '🛕 Offline (At Temple)', 'value' => 'Offline'],
                        ['label' => '❌ Cancel Booking', 'value' => 'Cancel Booking']
                    ]
                ]);
                return;
            }

            $state['booking_type'] = $type;
            if ($type === 'Online') {
                $state['step'] = 'enter_address';
                session(['chatbot_booking_state' => $state]);
                $this->sendBotMessage($sessionId, "Please provide the delivery address for Prasadam:", [
                    'options' => [
                        ['label' => '❌ Cancel Booking', 'value' => 'Cancel Booking']
                    ]
                ]);
            } else {
                $state['delivery_address'] = null;
                $this->showPoojaPaymentStep($sessionId, $state);
            }
            return;
        }

        // Enter Address Step
        if ($state['step'] === 'enter_address') {
            $address = trim($input);
            if (empty($address)) {
                $this->sendBotMessage($sessionId, "Address cannot be empty. Please enter your delivery address:", [
                    'options' => [
                        ['label' => '❌ Cancel Booking', 'value' => 'Cancel Booking']
                    ]
                ]);
                return;
            }

            $state['delivery_address'] = $address;
            $this->showPoojaPaymentStep($sessionId, $state);
            return;
        }

        // Confirm / Complete Payment Step
        if ($state['step'] === 'payment') {
            if ($normalizedInput === 'completed' || $normalizedInput === 'payment completed') {
                $this->completePoojaBooking($sessionId, $state);
            } else {
                $this->sendBotMessage($sessionId, "Payment status not confirmed. Would you like to confirm payment or cancel booking?", [
                    'options' => [
                        ['label' => '✅ Payment Completed', 'value' => 'Completed'],
                        ['label' => '❌ Cancel Booking', 'value' => 'Cancel Booking']
                    ]
                ]);
            }
        }
    }

    /**
     * Show Payment QR and details.
     */
    private function showPoojaPaymentStep($sessionId, $state)
    {
        $poojaFee = (float)$state['pooja_fee'];
        $shipping = ($state['booking_type'] === 'Online') ? (float)Setting::get('online_pooja_shipping_charge', '50.00') : 0.00;
        $totalAmount = $poojaFee + $shipping;

        // Save calculated values in state
        $state['shipping_charge'] = $shipping;
        $state['total_amount'] = $totalAmount;
        $state['step'] = 'payment';
        session(['chatbot_booking_state' => $state]);

        $summary = "📋 **Booking Summary**:\nPooja: **{$state['pooja_name']}**\nDate: **{$state['booking_date']}**\nTime: **{$state['booking_time']}**\nType: **{$state['booking_type']}**\n";
        if ($state['booking_type'] === 'Online') {
            $summary .= "Address: *{$state['delivery_address']}*\n";
        }
        $summary .= "\n**Payment Details**:\nPooja Fee: ₹" . number_format($poojaFee, 2) . "\nShipping Fee: ₹" . number_format($shipping, 2) . "\n**Total Amount: ₹" . number_format($totalAmount, 2) . "**\n\nPlease scan the QR code to complete the UPI payment:";

        // Send payment details and QR trigger metadata
        $this->sendBotMessage($sessionId, $summary, [
            'payment_qr' => true,
            'qr_amount' => $totalAmount,
            'qr_payee' => Setting::get('temple_name', 'Golden Temple'),
            'options' => [
                ['label' => '✅ Payment Completed', 'value' => 'Completed'],
                ['label' => '❌ Cancel Booking', 'value' => 'Cancel Booking']
            ]
        ]);
    }

    /**
     * Create the real Pooja Booking in database.
     */
    private function completePoojaBooking($sessionId, $state)
    {
        $userId = Auth::id();

        // Get devotee record
        $devotee = DB::table('devotees')->where('user_id', $userId)->first();
        if (!$devotee) {
            $this->sendBotMessage($sessionId, "An error occurred: Devotee profile not found. Please contact support.");
            session()->forget('chatbot_booking_state');
            return;
        }

        DB::beginTransaction();
        try {
            // Priest Assignment
            $assignedPriestId = $this->autoAssignPriest($state['pooja_id'], $state['booking_date'], $state['booking_time']);
            
            // Create Pooja Booking record
            $bookingId = DB::table('pooja_bookings')->insertGetId([
                'devotee_id' => $devotee->devotee_id,
                'pooja_id' => $state['pooja_id'],
                'priest_id' => $assignedPriestId,
                'booking_date' => $state['booking_date'],
                'booking_time' => $state['booking_time'],
                'booking_type' => $state['booking_type'],
                'delivery_address' => $state['delivery_address'],
                'shipping_charge' => $state['shipping_charge'],
                'amount' => $state['pooja_fee'],
                'discount_amount' => 0.00,
                'total_amount' => $state['total_amount'],
                'payment_method' => 'UPI',
                'payment_status' => 'Paid',
                'booking_status' => 'Confirmed',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Create Status Log
            DB::table('booking_status_logs')->insert([
                'booking_id' => $bookingId,
                'status_from' => null,
                'status_to' => 'Confirmed',
                'changed_by' => $userId,
                'remarks' => 'Booking created and paid via AI Chatbot.',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Create Payment record
            DB::table('booking_payments')->insert([
                'booking_id' => $bookingId,
                'payment_method' => 'UPI',
                'transaction_id' => 'BOT' . strtoupper(uniqid()),
                'amount' => $state['total_amount'],
                'status' => 'Paid',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            DB::commit();

            NotificationService::notify($userId, "Pooja Booking Confirmed! Your booking for {$state['pooja_name']} on {$state['booking_date']} has been successfully confirmed. Booking ID: #{$bookingId}.");
            NotificationService::notifyAdmin("New booking created via Chatbot (Booking ID #{$bookingId}).");
            AuditLogService::log("Pooja booking created via chatbot for Devotee ID {$devotee->devotee_id} (Booking ID #{$bookingId})");

            $successMsg = "🎉 **Booking Confirmed!**\n\nYour Pooja booking has been successfully recorded!\n\nBooking ID: **#{$bookingId}**\nStatus: **Confirmed**\nPayment: **Paid**\n\nThank you for choosing Shree Mandir! seek blessings! 🙏";
            
            session()->forget('chatbot_booking_state');

            $this->sendBotMessage($sessionId, $successMsg, [
                'options' => [
                    ['label' => '🛕 Temple Timings', 'value' => 'Temple Timings'],
                    ['label' => '📅 Next Event', 'value' => 'Next Event'],
                    ['label' => '📞 Contact Team', 'value' => 'Contact Team'],
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            $this->sendBotMessage($sessionId, "An error occurred while confirming your booking: " . $e->getMessage() . ". Please try again.");
        }
    }

    /**
     * Auto priest assignment helper.
     */
    private function autoAssignPriest($poojaId, $date, $time)
    {
        $priests = DB::table('priests')
            ->where('employment_status', 'Active')
            ->get();
            
        foreach ($priests as $priest) {
            $onLeave = DB::table('leave_requests')
                ->where('priest_id', $priest->priest_id)
                ->where('status', 'Approved')
                ->where('start_date', '<=', $date)
                ->where('end_date', '>=', $date)
                ->exists();
            if (!$onLeave) {
                return $priest->priest_id;
            }
        }
        
        // Return first priest as fallback
        $firstPriest = DB::table('priests')->first();
        return $firstPriest ? $firstPriest->priest_id : null;
    }

    /**
     * Send bot reply helper.
     */
    private function sendBotMessage($sessionId, $text, $metadata = null)
    {
        DB::table('chat_messages')->insert([
            'session_id' => $sessionId,
            'sender_type' => 'bot',
            'sender_id' => null,
            'message_text' => $text,
            'metadata' => $metadata ? json_encode($metadata) : null,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        DB::table('chat_sessions')
            ->where('session_id', $sessionId)
            ->update(['updated_at' => now()]);
    }
}
