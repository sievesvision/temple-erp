<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Event extends Model
{
    protected $table = 'events';
    protected $primaryKey = 'event_id';

    protected $fillable = [
        'event_name',
        'description',
        'event_date',
        'start_time',
        'end_time',
        'location',
        'status',
        'header_image',
        'flyer_image',
        'show_donation_summary',
    ];

    protected $casts = [
        'show_donation_summary' => 'boolean',
    ];

    public function donationOptions()
    {
        return $this->hasMany(EventDonationOption::class, 'event_id', 'event_id')->orderBy('sort_order');
    }

    /**
     * Build a readable "event-name-date-id" URL slug so repeating events
     * (yearly/monthly/weekly) are distinguishable at a glance. Works for
     * both Event models and plain stdClass rows (e.g. from DB::table()).
     * The trailing event_id keeps the URL unique and resolvable.
     */
    public static function buildSlug($event): string
    {
        $datePart = $event->event_date ? date('Y-m-d', strtotime($event->event_date)) : '';
        return Str::slug(trim($event->event_name . ' ' . $datePart)) . '-' . $event->event_id;
    }

    /**
     * Extract the numeric event_id from a "event-name-date-id" slug.
     */
    public static function idFromSlug(string $slug): ?int
    {
        if (preg_match('/-(\d+)$/', $slug, $matches)) {
            return (int) $matches[1];
        }
        return ctype_digit($slug) ? (int) $slug : null;
    }
}
