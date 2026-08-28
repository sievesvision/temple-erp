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
        'slug',
        'description',
        'event_date',
        'start_time',
        'end_time',
        'location',
        'status',
        'header_image',
        'flyer_image',
        'show_donation_summary',
        'coordinator_emails',
    ];

    protected $casts = [
        'show_donation_summary' => 'boolean',
    ];

    public function donationOptions()
    {
        return $this->hasMany(EventDonationOption::class, 'event_id', 'event_id')->orderBy('sort_order');
    }

    /**
     * Parse the comma-separated coordinator_emails field into a clean array of valid
     * addresses — invalid entries and blanks are silently dropped rather than erroring,
     * since this is a free-text admin field.
     */
    public function coordinatorEmailList(): array
    {
        if (!$this->coordinator_emails) {
            return [];
        }

        return collect(explode(',', $this->coordinator_emails))
            ->map(fn ($email) => trim($email))
            ->filter(fn ($email) => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->values()
            ->all();
    }

    /**
     * Suggest a readable "event-name-date" slug base (no id) — used as the default
     * when an admin leaves the slug field blank when creating/editing an event.
     * The caller is responsible for resolving collisions against other events.
     */
    public static function suggestSlug(string $eventName, ?string $eventDate): string
    {
        $datePart = $eventDate ? date('Y-m-d', strtotime($eventDate)) : '';
        return Str::slug(trim($eventName . ' ' . $datePart));
    }

    /**
     * Resolve a slug for a create/update request: honour a manually supplied slug
     * (sanitised to be URL-safe), or fall back to suggestSlug(), then de-duplicate
     * against any other event's slug by appending -2, -3, etc.
     */
    public static function resolveSlug(?string $requestedSlug, string $eventName, ?string $eventDate, ?int $ignoreEventId = null): string
    {
        $base = $requestedSlug ? Str::slug($requestedSlug) : self::suggestSlug($eventName, $eventDate);
        if ($base === '') {
            $base = self::suggestSlug($eventName, $eventDate);
        }

        $slug = $base;
        $suffix = 2;
        while (
            self::where('slug', $slug)
                ->when($ignoreEventId, fn ($q) => $q->where('event_id', '!=', $ignoreEventId))
                ->exists()
        ) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }
}
