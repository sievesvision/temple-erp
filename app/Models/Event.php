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
        'date_tbc',
        'start_time',
        'end_time',
        'location',
        'status',
        'header_image',
        'flyer_image',
        'qr_code_image',
        'gallery_images',
        'enabled_payment_methods',
        'show_donation_summary',
        'require_donor_email',
        'require_donor_mobile',
        'coordinator_emails',
        'contacts',
    ];

    protected $casts = [
        'show_donation_summary' => 'boolean',
        'require_donor_email' => 'boolean',
        'require_donor_mobile' => 'boolean',
        'date_tbc' => 'boolean',
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
     * Completed and Cancelled events are done/off — the public donation page should stop
     * collecting new pledges for them rather than silently keep accepting money for
     * something that already happened or was called off.
     */
    public function isClosedForDonations(): bool
    {
        return in_array($this->status, ['Completed', 'Cancelled'], true);
    }

    /**
     * Parse the contacts JSON column ([{name, phone}, ...]) into a clean array, dropping
     * any row missing a name (a phone-only or fully blank row from an unused repeater slot).
     */
    public function contactList(): array
    {
        if (!$this->contacts) {
            return [];
        }

        $decoded = json_decode($this->contacts, true);
        if (!is_array($decoded)) {
            return [];
        }

        return collect($decoded)
            ->map(fn ($c) => ['name' => trim($c['name'] ?? ''), 'phone' => trim($c['phone'] ?? '')])
            ->filter(fn ($c) => $c['name'] !== '')
            ->values()
            ->all();
    }

    /**
     * Parse the gallery_images JSON column (a plain array of manually-typed image paths,
     * same convention as header_image/flyer_image/qr_code_image) into a clean list of
     * non-blank paths.
     */
    public function galleryImages(): array
    {
        if (!$this->gallery_images) {
            return [];
        }

        $decoded = json_decode($this->gallery_images, true);
        if (!is_array($decoded)) {
            return [];
        }

        return collect($decoded)
            ->map(fn ($path) => trim((string) $path))
            ->filter(fn ($path) => $path !== '')
            ->values()
            ->all();
    }

    /**
     * This event's own payment-method override, or null to inherit whatever is configured
     * globally (Settings::get('enabled_payment_methods') + the global Stripe toggle).
     */
    public function paymentMethodsOverride(): ?array
    {
        if ($this->enabled_payment_methods === null || $this->enabled_payment_methods === '') {
            return null;
        }

        $decoded = json_decode($this->enabled_payment_methods, true);
        return is_array($decoded) ? array_values($decoded) : null;
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
