@echo off
REM Rebuilds print-agent.exe from main.go. Requires Go (https://go.dev/dl/) on whichever
REM machine builds it — the POS computers that RUN the resulting .exe need nothing installed.
set GOOS=windows
set GOARCH=amd64
go build -ldflags="-s -w" -o print-agent.exe main.go
echo Built print-agent.exe
