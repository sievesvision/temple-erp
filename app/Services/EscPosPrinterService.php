<?php

namespace App\Services;

use Exception;
use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;

/**
 * Genuine ESC/POS raw printing to the one shared network thermal printer (mike42/escpos-php
 * over a raw TCP socket, port 9100 by default — the standard "direct JetDirect-style" port
 * every network thermal printer listens on). Replaces the old window.print() browser-popup
 * path (see sci-action-framework.js's printText() and ticket-print.blade.php's toolbar button)
 * for anything that should come out automatically with no dialog and no operator click.
 *
 * Every call opens its own socket and closes it when done — there's no persistent connection
 * to manage, and print jobs here are infrequent/one-off (one ticket order, one receipt) rather
 * than high-throughput.
 */
class EscPosPrinterService
{
    private const RECEIPT_WIDTH_CHARS = 32;

    /**
     * $ip/$port let the Admin > Settings "Test Print" button try values still sitting
     * unsaved in the form — every other caller leaves them null and gets the saved
     * ThermalPrinterSettings, which also enforces the "enabled" toggle.
     *
     * @throws Exception if not configured or the socket can't be opened — caller decides the
     *                    fallback (e.g. offer the browser-print view instead).
     */
    private function connect(?string $ip = null, ?int $port = null): Printer
    {
        if ($ip === null) {
            if (!ThermalPrinterSettings::isConfigured()) {
                throw new Exception('Thermal printer is not configured — set its IP address in Admin > Settings.');
            }
            $ip = ThermalPrinterSettings::ip();
            $port = ThermalPrinterSettings::port();
        }

        $connector = new NetworkPrintConnector($ip, (string) ($port ?: 9100), 5);

        return new Printer($connector);
    }

    /**
     * Prints mx51's own merchant/customer receipt text exactly as supplied — never reformatted
     * or re-laid-out, same "the gateway already laid this out, don't touch it" principle the
     * browser-popup path it replaces already followed.
     */
    public function printReceiptText(string $text): void
    {
        $printer = $this->connect();
        try {
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->text($text);
            if ($text === '' || !str_ends_with($text, "\n")) {
                $printer->feed(1);
            }
            $printer->cut();
        } finally {
            $printer->close();
        }
    }

    /**
     * Prints one ticket stub, mirroring ticket-print.blade.php's own layout (temple name,
     * ticket name, price, order/date, customer name, stub number) at raw 32-column ESC/POS
     * width instead of a browser page.
     *
     * @param array{temple_name:string,temple_subtitle:?string,ticket_name:string,price_text:string,order_meta:string,customer_name:?string,stub_number:string} $stub
     */
    public function printTicketStub(array $stub): void
    {
        $printer = $this->connect();
        try {
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->setEmphasis(true);
            $printer->text($this->wrapCentered(strtoupper($stub['temple_name'])));
            $printer->setEmphasis(false);
            if (!empty($stub['temple_subtitle'])) {
                $printer->text($stub['temple_subtitle'] . "\n");
            }
            $printer->text(str_repeat('-', self::RECEIPT_WIDTH_CHARS) . "\n");

            $printer->setEmphasis(true);
            $printer->text($this->wrapCentered(strtoupper($stub['ticket_name'])));
            $printer->setTextSize(2, 2);
            $printer->text($stub['price_text'] . "\n");
            $printer->setTextSize(1, 1);
            $printer->setEmphasis(false);

            $printer->text($stub['order_meta'] . "\n");
            if (!empty($stub['customer_name'])) {
                $printer->text($stub['customer_name'] . "\n");
            }
            $printer->text(str_repeat('-', self::RECEIPT_WIDTH_CHARS) . "\n");

            $printer->setEmphasis(true);
            $printer->text($stub['stub_number'] . "\n");
            $printer->setEmphasis(false);

            $printer->feed(1);
            $printer->cut();
        } finally {
            $printer->close();
        }
    }

    /**
     * Prints a short test line + cut, used by the "Test Print" button in Admin > Settings so
     * connection problems (wrong IP, printer off/offline) surface immediately, before relying
     * on it during a real transaction. $ip/$port override the saved setting (see connect()) so
     * this can be tried before the form is even saved.
     */
    public function printTestPage(?string $ip = null, ?int $port = null): void
    {
        $printer = $this->connect($ip, $port);
        try {
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->setEmphasis(true);
            $printer->text("PRINTER TEST\n");
            $printer->setEmphasis(false);
            $printer->text(now()->format('d M Y, g:i a') . "\n");
            $printer->text("Connection OK\n");
            $printer->feed(1);
            $printer->cut();
        } finally {
            $printer->close();
        }
    }

    /**
     * mike42/escpos-php has no built-in word-wrap — long titles need manual wrapping onto
     * multiple centered lines rather than being silently cut off mid-word by the printer itself.
     */
    private function wrapCentered(string $text): string
    {
        $wrapped = wordwrap($text, self::RECEIPT_WIDTH_CHARS, "\n", true);

        return $wrapped . "\n";
    }
}
