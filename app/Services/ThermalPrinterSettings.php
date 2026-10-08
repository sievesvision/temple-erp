<?php

namespace App\Services;

use App\Models\Setting;

/**
 * Config for the one shared network/ESC-POS thermal receipt printer used for both ticket
 * stub printing (TicketController) and mx51 merchant/customer receipt auto-print
 * (sci-action-framework.js, via ThermalPrintController) — a single physical printer, so one
 * settings surface covers both call sites rather than configuring it twice.
 */
class ThermalPrinterSettings
{
    public static function enabled(): bool
    {
        return (bool) Setting::get('thermal_printer_enabled', false);
    }

    public static function ip(): string
    {
        return (string) Setting::get('thermal_printer_ip', '');
    }

    public static function port(): int
    {
        return (int) Setting::get('thermal_printer_port', 9100);
    }

    public static function isConfigured(): bool
    {
        return self::enabled() && self::ip() !== '';
    }
}
