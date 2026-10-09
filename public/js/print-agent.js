/**
 * Client for the local SSVK Print Agent (see print-agent/main.go) — a small standalone
 * program each POS computer runs on its own local network, alongside its thermal receipt
 * printer. The Laravel app itself runs on remote hosting (test.hasq.org / hasq.org) with no
 * network path to a printer on the temple's private LAN, so genuine ESC/POS printing has to
 * happen from THIS computer instead — hence talking to http://127.0.0.1:9191 (always
 * reachable from the same machine the browser is running on) rather than the Laravel backend.
 *
 * The agent is a dumb relay with no printer address of its own — every call here supplies the
 * IP/port of the printer THIS computer should use, read from localStorage (same "saved only in
 * this browser, not on the server" pattern as "This Computer's EFT Terminal" — see
 * ticket-console.blade.php / event-console.blade.php's own picker for that).
 *
 * Every method resolves (never rejects) to { success, message } — callers fall back to the
 * existing browser-print popup on any failure, so a misconfigured/offline agent or printer
 * never leaves the operator with nothing printed at all.
 */
(function (global) {
    'use strict';

    var AGENT_BASE = 'http://127.0.0.1:9191';
    var STORAGE_KEY = 'ssvkThermalPrinterConfig';

    function getConfig() {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) { return null; }
            var cfg = JSON.parse(raw);
            if (!cfg || !cfg.ip) { return null; }
            return { ip: cfg.ip, port: cfg.port ? parseInt(cfg.port, 10) : 9100 };
        } catch (e) {
            return null;
        }
    }

    function setConfig(ip, port) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify({ ip: ip, port: port ? parseInt(port, 10) : 9100 }));
            return true;
        } catch (e) {
            return false;
        }
    }

    function isConfigured() {
        return getConfig() !== null;
    }

    // Short timeout — this is a loopback call to a program on the SAME machine, so a slow
    // response means the agent isn't running at all, not that it's merely busy.
    function fetchWithTimeout(url, options, timeoutMs) {
        var controller = new AbortController();
        var timer = setTimeout(function () { controller.abort(); }, timeoutMs);
        options = options || {};
        options.signal = controller.signal;
        return fetch(url, options).finally(function () { clearTimeout(timer); });
    }

    function postJson(path, body, timeoutMs) {
        return fetchWithTimeout(AGENT_BASE + path, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        }, timeoutMs)
            .then(function (res) { return res.json(); })
            .catch(function (e) { return { success: false, message: e && e.message ? e.message : 'Print agent unreachable — is it running on this computer?' }; });
    }

    function printReceiptText(text) {
        var cfg = getConfig();
        if (!cfg) { return Promise.resolve({ success: false, message: 'No printer configured for this computer.' }); }
        return postJson('/print', { ip: cfg.ip, port: cfg.port, text: text }, 8000);
    }

    function printTicketStub(stub) {
        var cfg = getConfig();
        if (!cfg) { return Promise.resolve({ success: false, message: 'No printer configured for this computer.' }); }
        return postJson('/print-stub', { ip: cfg.ip, port: cfg.port, stub: stub }, 8000);
    }

    // Used by the "This Computer's Thermal Printer" picker's own Test button — tries whatever
    // ip/port is currently in the form, even if not saved yet.
    function testPrint(ip, port) {
        return postJson('/test', { ip: ip, port: port ? parseInt(port, 10) : 9100 }, 8000);
    }

    global.PrintAgent = {
        getConfig: getConfig,
        setConfig: setConfig,
        isConfigured: isConfigured,
        printReceiptText: printReceiptText,
        printTicketStub: printTicketStub,
        testPrint: testPrint,
    };
})(window);
