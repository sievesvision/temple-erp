// SSVK Print Agent — a small standalone background program that runs on each POS computer,
// on the same local network as its thermal receipt printer. The Laravel app itself runs on
// remote hosting (test.hasq.org / hasq.org) and has no network path to a printer on the
// temple's private LAN, so the browser talks to THIS agent instead (http://127.0.0.1:9191,
// always reachable from the same machine) for the actual ESC/POS print job, and this agent
// opens the raw TCP socket to the printer on the LAN.
//
// Deliberately a dumb relay: it has no built-in printer address of its own. Every request
// carries the IP/port of the printer to use, which the browser reads from this computer's own
// "This Computer's Thermal Printer" setting (public/js/print-agent.js, stored in localStorage,
// same pattern already used for "This Computer's EFT Terminal" — see ticket-console.blade.php).
// That keeps one agent build working for every station, whatever printer it's actually wired to.
//
// No external dependencies — pure standard library, so `go build` produces one self-contained
// .exe that needs nothing pre-installed on the POS computer.
package main

import (
	"encoding/json"
	"flag"
	"fmt"
	"log"
	"net"
	"net/http"
	"strings"
	"time"
)

const (
	escInit    = "\x1b\x40"       // ESC @  — initialize
	escCenter  = "\x1b\x61\x01"   // ESC a 1 — center justification
	escLeft    = "\x1b\x61\x00"   // ESC a 0 — left justification
	escBoldOn  = "\x1b\x45\x01"   // ESC E 1 — emphasis on
	escBoldOff = "\x1b\x45\x00"   // ESC E 0 — emphasis off
	gsSizeBig  = "\x1d\x21\x11"   // GS ! 0x11 — double width + double height
	gsSizeNorm = "\x1d\x21\x00"   // GS ! 0x00 — normal size
	gsCutFull  = "\x1d\x56\x00\x03" // GS V 0 3 — full cut, feed 3 lines first (mirrors mike42/escpos-php's own Printer::cut() default)
	receiptWidthChars = 32
)

// allowedOrigin restricts which pages may ask this agent to print — without this, any website
// a POS browser happens to load (while the agent is running) could silently send print jobs to
// the temple's receipt printer. Only the app's own known hosts, plus localhost for local
// development, are trusted.
func allowedOrigin(origin string) bool {
	if origin == "" {
		return false
	}
	trusted := []string{
		"https://hasq.org",
		"https://www.hasq.org",
		"https://test.hasq.org",
	}
	for _, t := range trusted {
		if origin == t {
			return true
		}
	}
	return strings.HasPrefix(origin, "http://localhost") || strings.HasPrefix(origin, "http://127.0.0.1")
}

func withCors(handler http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		origin := r.Header.Get("Origin")
		if allowedOrigin(origin) {
			w.Header().Set("Access-Control-Allow-Origin", origin)
			w.Header().Set("Access-Control-Allow-Methods", "POST, GET, OPTIONS")
			w.Header().Set("Access-Control-Allow-Headers", "Content-Type")
		}
		if r.Method == http.MethodOptions {
			w.WriteHeader(http.StatusNoContent)
			return
		}
		if !allowedOrigin(origin) {
			http.Error(w, `{"success":false,"message":"origin not allowed"}`, http.StatusForbidden)
			return
		}
		handler(w, r)
	}
}

func writeJSON(w http.ResponseWriter, status int, payload map[string]interface{}) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	_ = json.NewEncoder(w).Encode(payload)
}

// openPrinter connects to the printer over the LAN with a short timeout — a misconfigured or
// powered-off printer must fail fast, not hang the POS page's print attempt.
func openPrinter(ip string, port int) (net.Conn, error) {
	if port == 0 {
		port = 9100
	}
	addr := fmt.Sprintf("%s:%d", ip, port)
	return net.DialTimeout("tcp", addr, 5*time.Second)
}

// wrapCentered manually word-wraps onto multiple lines (Go has no built-in wordwrap) so a long
// ticket/temple name doesn't get cut off mid-word by the printer itself.
func wrapCentered(text string) string {
	words := strings.Fields(text)
	var lines []string
	line := ""
	for _, word := range words {
		candidate := word
		if line != "" {
			candidate = line + " " + word
		}
		if len(candidate) > receiptWidthChars && line != "" {
			lines = append(lines, line)
			line = word
		} else {
			line = candidate
		}
	}
	if line != "" {
		lines = append(lines, line)
	}
	return strings.Join(lines, "\n") + "\n"
}

type printReceiptRequest struct {
	IP   string `json:"ip"`
	Port int    `json:"port"`
	Text string `json:"text"`
}

// handlePrintReceipt prints mx51's own merchant/customer receipt text exactly as supplied —
// never reformatted — same principle the server-side EscPosPrinterService::printReceiptText()
// already follows (see app/Services/EscPosPrinterService.php).
func handlePrintReceipt(w http.ResponseWriter, r *http.Request) {
	var req printReceiptRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || req.IP == "" || req.Text == "" {
		writeJSON(w, http.StatusBadRequest, map[string]interface{}{"success": false, "message": "ip and text are required"})
		return
	}

	conn, err := openPrinter(req.IP, req.Port)
	if err != nil {
		writeJSON(w, http.StatusOK, map[string]interface{}{"success": false, "message": err.Error()})
		return
	}
	defer conn.Close()

	body := req.Text
	if !strings.HasSuffix(body, "\n") {
		body += "\n"
	}
	_, _ = conn.Write([]byte(escInit + escLeft + body + gsCutFull))
	writeJSON(w, http.StatusOK, map[string]interface{}{"success": true})
}

type ticketStub struct {
	TempleName     string `json:"temple_name"`
	TempleSubtitle string `json:"temple_subtitle"`
	TicketName     string `json:"ticket_name"`
	PriceText      string `json:"price_text"`
	OrderMeta      string `json:"order_meta"`
	CustomerName   string `json:"customer_name"`
	StubNumber     string `json:"stub_number"`
}

type printStubRequest struct {
	IP   string     `json:"ip"`
	Port int        `json:"port"`
	Stub ticketStub `json:"stub"`
}

// handlePrintStub mirrors ticket-print.blade.php's / EscPosPrinterService::printTicketStub()'s
// own stub layout (temple name, ticket name, price, order/date, customer name, stub number) at
// raw 32-column ESC/POS width instead of a browser page.
func handlePrintStub(w http.ResponseWriter, r *http.Request) {
	var req printStubRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || req.IP == "" {
		writeJSON(w, http.StatusBadRequest, map[string]interface{}{"success": false, "message": "ip and stub are required"})
		return
	}

	conn, err := openPrinter(req.IP, req.Port)
	if err != nil {
		writeJSON(w, http.StatusOK, map[string]interface{}{"success": false, "message": err.Error()})
		return
	}
	defer conn.Close()

	s := req.Stub
	var b strings.Builder
	b.WriteString(escInit)
	b.WriteString(escCenter)
	b.WriteString(escBoldOn)
	b.WriteString(wrapCentered(strings.ToUpper(s.TempleName)))
	b.WriteString(escBoldOff)
	if s.TempleSubtitle != "" {
		b.WriteString(s.TempleSubtitle + "\n")
	}
	b.WriteString(strings.Repeat("-", receiptWidthChars) + "\n")
	b.WriteString(escBoldOn)
	b.WriteString(wrapCentered(strings.ToUpper(s.TicketName)))
	b.WriteString(gsSizeBig)
	b.WriteString(s.PriceText + "\n")
	b.WriteString(gsSizeNorm)
	b.WriteString(escBoldOff)
	b.WriteString(s.OrderMeta + "\n")
	if s.CustomerName != "" {
		b.WriteString(s.CustomerName + "\n")
	}
	b.WriteString(strings.Repeat("-", receiptWidthChars) + "\n")
	b.WriteString(escBoldOn)
	b.WriteString(s.StubNumber + "\n")
	b.WriteString(escBoldOff)
	b.WriteString(gsCutFull)

	_, _ = conn.Write([]byte(b.String()))
	writeJSON(w, http.StatusOK, map[string]interface{}{"success": true})
}

type testPrintRequest struct {
	IP   string `json:"ip"`
	Port int    `json:"port"`
}

// handleTest prints a short diagnostic line — used by the "This Computer's Thermal Printer"
// control's own Test button so a wrong IP/powered-off printer surfaces immediately.
func handleTest(w http.ResponseWriter, r *http.Request) {
	var req testPrintRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || req.IP == "" {
		writeJSON(w, http.StatusBadRequest, map[string]interface{}{"success": false, "message": "ip is required"})
		return
	}

	conn, err := openPrinter(req.IP, req.Port)
	if err != nil {
		writeJSON(w, http.StatusOK, map[string]interface{}{"success": false, "message": err.Error()})
		return
	}
	defer conn.Close()

	body := escInit + escCenter + escBoldOn + "PRINTER AGENT TEST\n" + escBoldOff +
		time.Now().Format("02 Jan 2006, 3:04 pm") + "\n" + "Connection OK\n" + gsCutFull
	_, _ = conn.Write([]byte(body))
	writeJSON(w, http.StatusOK, map[string]interface{}{"success": true})
}

func handleHealth(w http.ResponseWriter, r *http.Request) {
	writeJSON(w, http.StatusOK, map[string]interface{}{"ok": true, "agent": "ssvk-print-agent", "version": "1.0"})
}

func main() {
	port := flag.Int("port", 9191, "local port to listen on")
	flag.Parse()

	http.HandleFunc("/health", withCors(handleHealth))
	http.HandleFunc("/print", withCors(handlePrintReceipt))
	http.HandleFunc("/print-stub", withCors(handlePrintStub))
	http.HandleFunc("/test", withCors(handleTest))

	addr := fmt.Sprintf("127.0.0.1:%d", *port)
	fmt.Println("SSVK Print Agent")
	fmt.Println("Listening on http://" + addr)
	fmt.Println("Leave this window open — it relays print jobs from the POS page to the receipt printer on this network.")
	fmt.Println("Press Ctrl+C to stop.")
	log.Fatal(http.ListenAndServe(addr, nil))
}
