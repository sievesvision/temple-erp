<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QrLink extends Model
{
    protected $fillable = [
        'slug',
        'label',
        'target_url',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * The full public short URL for this link, e.g. https://hasq.org/qr-vinyagar-chathurthi.
     */
    public function shortUrl(): string
    {
        return url('/qr-' . $this->slug);
    }

    /**
     * A scannable QR code image for the short URL, rendered via a free public QR API
     * (same service already used elsewhere in this app for UPI payment QR codes).
     */
    public function qrImageUrl(int $size = 220): string
    {
        return 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size . '&data=' . urlencode($this->shortUrl());
    }

    /**
     * Resolve the target into an absolute URL — a relative path (e.g. "/events/foo") is
     * resolved against this app's own domain; anything already starting with http(s) is
     * used as-is (useful if the target is ever an external link).
     */
    public function resolvedTargetUrl(): string
    {
        $target = trim($this->target_url);
        if (preg_match('/^https?:\/\//i', $target)) {
            return $target;
        }
        return url('/' . ltrim($target, '/'));
    }
}
