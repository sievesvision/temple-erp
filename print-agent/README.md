# SSVK Print Agent

A small standalone program that runs on each POS computer and relays ticket/receipt print
jobs to that computer's local thermal printer.

## Why this exists

The ticket/event POS pages run against the remote hosted site (test.hasq.org / hasq.org).
The receipt printer sits on the temple's own local network. The remote server has no network
path to a printer on that private LAN — so the actual print job has to come from a program
running *on the POS computer itself*, which this is. The browser talks to it at
`http://127.0.0.1:9191` (always reachable — same machine), and it opens the real connection to
the printer over the local network.

It has no printer address built in — every print job carries the IP/port to use, which the
browser reads from this computer's own "This Computer's Thermal Printer" setting (set from the
Ticket Console's Settings pane, saved only in that browser's `localStorage` — see
`public/js/print-agent.js`). One agent build works for every POS computer, whatever printer
each one is actually wired to.

## Setup (per POS computer)

1. Copy `print-agent.exe` onto the POS computer (no installer, no dependencies — it's one
   self-contained file).
2. Run `install-startup.ps1` once (right-click → **Run with PowerShell**). This adds a
   shortcut to the Windows Startup folder so the agent launches automatically every time this
   computer is turned on or logged into.
3. In the browser, open **Ticket Console → Settings → "This Computer's Thermal Printer"**,
   enter this computer's printer's IP address (and port, if it isn't the standard 9100), and
   click **Save for This Computer**. Use **Test Print** to confirm it actually reaches the
   printer before relying on it for a real sale.

That's it — ticket stub printing and mx51 merchant/customer receipt printing on the Ticket POS
and Event POS pages will now print directly to that printer with no popup, as long as this
agent is running. If the agent isn't running (or the printer's unreachable), those pages fall
back to the old browser "Print" popup automatically — nothing is ever silently lost.

## Rebuilding from source

Only needed if `main.go` changes. Requires Go (https://go.dev/dl/) on the machine doing the
build — not on the POS computers that run the resulting `.exe`.

```
build.bat
```

## Endpoints (for reference — the POS pages call these automatically)

- `GET  /health` — `{ok:true}` if the agent is running.
- `POST /print` — `{ip, port, text}` → prints a block of raw receipt text verbatim (mx51
  merchant/customer receipts).
- `POST /print-stub` — `{ip, port, stub:{...}}` → prints one formatted ticket stub.
- `POST /test` — `{ip, port}` → prints a short diagnostic line, used by the Settings page's
  Test Print button.

All requests must carry an `Origin` header matching `hasq.org`, `test.hasq.org`, or
`localhost`/`127.0.0.1` — the agent rejects anything else, so an unrelated website a POS
browser happens to visit can't silently trigger prints to the temple's receipt printer.
