<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotificationService
{
    public static function notify($userId, $message)
    {
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'app',
            'notifiable_type' => 'App\\Models\\User',
            'notifiable_id' => $userId,
            'user_id' => $userId,
            'message' => $message,
            'is_read' => false,
            'data' => json_encode(['message' => $message]),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    public static function notifyAdmin($message)
    {
        // Get all Admin user ids
        $adminIds = DB::table('users')->where('role', 'Admin')->pluck('id');
        foreach ($adminIds as $id) {
            self::notify($id, $message);
        }
    }
}
