# Installs a shortcut to print-agent.exe in this Windows user's Startup folder, so the agent
# launches automatically every time this POS computer is turned on/logged into — staff never
# have to remember to start it before the first sale of the day.
#
# Run this once per POS computer, from this folder (double-click, or right-click > Run with
# PowerShell). It only needs re-running if print-agent.exe is ever moved to a different folder.

$exePath = Join-Path $PSScriptRoot "print-agent.exe"
if (-not (Test-Path $exePath)) {
    Write-Host "print-agent.exe not found in this folder — build it first (see README.md)." -ForegroundColor Red
    exit 1
}

$startupFolder = [Environment]::GetFolderPath("Startup")
$shortcutPath = Join-Path $startupFolder "SSVK Print Agent.lnk"

$shell = New-Object -ComObject WScript.Shell
$shortcut = $shell.CreateShortcut($shortcutPath)
$shortcut.TargetPath = $exePath
$shortcut.WorkingDirectory = $PSScriptRoot
$shortcut.WindowStyle = 7  # Minimized — stays running, out of the way
$shortcut.Description = "SSVK Print Agent — relays ticket/receipt print jobs to the local thermal printer"
$shortcut.Save()

Write-Host "Installed. The Print Agent will start automatically next time this computer logs in." -ForegroundColor Green
Write-Host "To start it right now without logging out, double-click print-agent.exe in this folder."
