# artisan-profile.ps1
#
# Paste this whole file into your PowerShell prompt once to make an
# `artisan` function available *for the rest of that session* without
# needing to execute any .ps1 file from disk. Re-paste after closing
# the window — alternatively, append the `artisan` function block to
# your $PROFILE (`notepad $PROFILE`) so it's persistent.
#
# Usage after pasting:
#   $env:INTERNAL_ARTISAN_TOKEN = "paste-the-token"
#   artisan status
#   artisan migrate
#   artisan cache-clear
#   artisan dedupe-preview
#   artisan list

function artisan {
    [CmdletBinding()]
    param(
        [Parameter(Position = 0)] [string] $Command = 'status',
        [string[]] $Flags = @(),
        [string[]] $Args = @(),
        [string]   $BaseUrl = 'https://appointment-module-api.onrender.com'
    )

    $ErrorActionPreference = 'Stop'
    $TOKEN = $env:INTERNAL_ARTISAN_TOKEN

    if (-not $TOKEN) {
        Write-Host 'INTERNAL_ARTISAN_TOKEN env var is empty.' -ForegroundColor Red
        Write-Host 'Run:  $env:INTERNAL_ARTISAN_TOKEN = "paste-the-token"' -ForegroundColor Yellow
        return
    }

    $map = @{
        'status'           = @{ key = 'app-status' }
        'app-status'       = @{ key = 'app-status' }
        'migrate'          = @{ key = 'migrate';        flags = @('--force') }
        'migrate-status'   = @{ key = 'migrate-status' }
        'migrate-fresh'    = @{ key = 'migrate-fresh';  flags = @('--force') }
        'migrate-rollback' = @{ key = 'migrate-rollback' }
        'config-clear'     = @{ key = 'config-clear' }
        'cache-clear'      = @{ key = 'cache-clear' }
        'route-clear'      = @{ key = 'route-clear' }
        'view-clear'       = @{ key = 'view-clear' }
        'event-clear'      = @{ key = 'event-clear' }
        'optimize'         = @{ key = 'optimize' }
        'optimize-clear'   = @{ key = 'optimize-clear' }
        'storage-link'     = @{ key = 'storage-link' }
        'queue-work-once'  = @{ key = 'queue-work-once' }
        'dedupe-preview'   = @{ key = 'dedupe-preview'; flags = @('--dry-run') }
    }

    if ($Command -eq 'list') {
        Write-Host 'Allowed commands:' -ForegroundColor Cyan
        $map.Keys | Sort-Object | ForEach-Object { Write-Host "  $_" }
        return
    }

    if (-not $map.ContainsKey($Command)) {
        Write-Host "Unknown command '$Command'. Run 'artisan list' to see the allowlist." -ForegroundColor Red
        return
    }

    $entry = $map[$Command]
    $publicKey = $entry.key
    $resolvedFlags = if ($Flags.Count -gt 0) { $Flags } else { $entry.flags }

    $body = @{ command = $publicKey }
    if ($resolvedFlags) { $body.flags = $resolvedFlags }
    if ($Args) { $body.args = $Args }

    Write-Host "→ POST $BaseUrl/api/internal/artisan cmd=$publicKey flags=$($resolvedFlags -join ' ')" -ForegroundColor DarkGray

    $headers = @{
        'X-Internal-Token' = $TOKEN
        'Content-Type'     = 'application/json'
    }
    $params = @{
        Uri         = "$BaseUrl/api/internal/artisan"
        Method      = 'POST'
        Headers     = $headers
        Body        = ($body | ConvertTo-Json -Depth 4)
        TimeoutSec  = 120
        ErrorAction  = 'Stop'
    }

    try {
        $resp = Invoke-WebRequest @params -UseBasicParsing
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
        return
    }

    Write-Host "HTTP $($resp.StatusCode)" -ForegroundColor Green
    try {
        $body_out = $resp.Content | ConvertFrom-Json
    } catch {
        Write-Host $resp.Content
        return
    }

    if ($null -ne $body_out.exit_code -and $body_out.exit_code -ne 0) {
        Write-Host "artisan exit_code=$($body_out.exit_code)" -ForegroundColor Red
    }
    if ($body_out.output) {
        Write-Host ''
        Write-Host '----- output -----' -ForegroundColor DarkGray
        Write-Host $body_out.output
        Write-Host '------------------' -ForegroundColor DarkGray
    }
    if ($body_out.exception) {
        Write-Host ''
        Write-Host 'Exception:' -ForegroundColor Red
        Write-Host $body_out.exception
    }
}

Write-Host '`artisan` function loaded. Run `artisan list` for commands.' -ForegroundColor Cyan
