# artisan.ps1 — wrapper for the Render artisan bridge.
#
# Usage:
#   .\artisan.ps1 status                # app:status
#   .\artisan.ps1 migrate               # migrate --force
#   .\artisan.ps1 migrate-status        # read-only migration status
#   .\artisan.ps1 cache-clear           # flush application cache
#   .\artisan.ps1 dedupe-preview        # dedupe report (read-only)
#
# The token is read from $env:INTERNAL_ARTISAN_TOKEN. Set it via:
#   $env:INTERNAL_ARTISAN_TOKEN = "paste-the-token-here"
# or put it in $PROFILE with a long random value.

[CmdletBinding()]
param(
    [Parameter(Position=0)] [string]$Command = 'status',
    [string[]]$Flags = @(),
    [string[]]$Args = @(),
    [string]$BaseUrl = 'https://appointment-module-api.onrender.com'
)

$ErrorActionPreference = 'Stop'

$TOKEN = $env:INTERNAL_ARTISAN_TOKEN
if (-not $TOKEN) {
    Write-Host "INTERNAL_ARTISAN_TOKEN env var is empty." -ForegroundColor Red
    Write-Host ""
    Write-Host "Quickest path: open Render dashboard → appointment-module-api → Environment,"
    Write-Host "add the INTERNAL_ARTISAN_TOKEN env var with a random 32+ char string, then"
    Write-Host "from PowerShell:" -ForegroundColor Gray
    Write-Host ""
    Write-Host '    $env:INTERNAL_ARTISAN_TOKEN = "paste-the-token"' -ForegroundColor Yellow
    Write-Host ""
    Write-Host "Run .\scripts\generate-token.ps1 to generate a strong value automatically." -ForegroundColor Cyan
    exit 2
}

# Map a friendly alias to the bridge's public command key.
$map = @{
    'status'             = 'app-status'
    'app-status'         = 'app-status'
    'migrate'            = @{ key = 'migrate';            flags = @('--force') }
    'migrate-status'     = 'migrate-status'
    'migrate-fresh'      = @{ key = 'migrate-fresh';      flags = @('--force') }
    'migrate-rollback'   = 'migrate-rollback'
    'config-clear'       = 'config-clear'
    'cache-clear'        = 'cache-clear'
    'route-clear'        = 'route-clear'
    'view-clear'         = 'view-clear'
    'event-clear'        = 'event-clear'
    'optimize'           = 'optimize'
    'optimize-clear'     = 'optimize-clear'
    'storage-link'       = 'storage-link'
    'queue-work-once'    = 'queue-work-once'
    'dedupe-preview'     = @{ key = 'dedupe-preview';     flags = @('--dry-run') }
    'list'               = 'list'
}

if ($Command -eq 'list') {
    Write-Host "Allowed commands:" -ForegroundColor Cyan
    $map.Keys | Sort-Object | ForEach-Object { Write-Host "  $_" }
    exit 0
}

if (-not $map.ContainsKey($Command)) {
    Write-Host "Unknown command '$Command'. Run '.\artisan.ps1 list' to see the allowlist." -ForegroundColor Red
    exit 2
}

$entry = $map[$Command]
if ($entry -is [string]) {
    $publicKey = $entry
    $resolvedFlags = $Flags
} else {
    $publicKey = $entry.key
    if ($Flags.Count -gt 0) {
        $resolvedFlags = $Flags
    } else {
        $resolvedFlags = $entry.flags
    }
}

$body = @{ command = $publicKey }
if ($resolvedFlags.Count -gt 0) { $body.flags = $resolvedFlags }
if ($Args.Count -gt 0) { $body.args = $Args }

Write-Host "→ POST $BaseUrl/api/internal/artisan  cmd=$publicKey flags=$($resolvedFlags -join ' ') args=$($Args -join ' ')" -ForegroundColor DarkGray

$json = $body | ConvertTo-Json -Depth 4
$uri  = "$BaseUrl/api/internal/artisan"

try {
    $resp = Invoke-WebRequest -Uri $uri -Method POST `
        -Headers @{
            'X-Internal-Token' = $TOKEN
            'Content-Type'     = 'application/json'
        } `
        -Body $json `
        -UseBasicParsing `
        -TimeoutSec 120
} catch {
    $ex = $_.Exception
    if ($ex.Response) {
        Write-Host "HTTP $($ex.Response.StatusCode)" -ForegroundColor Yellow
        $stream = $ex.Response.GetResponseStream()
        $reader = New-Object System.IO.StreamReader($stream)
        Write-Host $reader.ReadToEnd()
        $reader.Close()
    } else {
        Write-Host "Network error: $($ex.Message)" -ForegroundColor Red
    }
    exit 3
}

Write-Host "HTTP $($resp.StatusCode)" -ForegroundColor Green
$responseBody = $resp.Content | ConvertFrom-Json

if ($responseBody.exit_code -ne $null -and $responseBody.exit_code -ne 0) {
    Write-Host "artisan exit_code=$($responseBody.exit_code)" -ForegroundColor Red
}
if ($responseBody.output) {
    Write-Host ""
    Write-Host "----- output -----" -ForegroundColor DarkGray
    Write-Host $responseBody.output
    Write-Host "------------------" -ForegroundColor DarkGray
}
if ($responseBody.exception) {
    Write-Host ""
    Write-Host "Exception:" -ForegroundColor Red
    Write-Host $responseBody.exception
}
