# generate-token.ps1
# Run this on your Windows machine to produce a strong 64-char token,
# then paste the value into Render dashboard's INTERNAL_ARTISAN_TOKEN
# env var.

$bytes = New-Object byte[] 32
[System.Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes)
$token = -join ($bytes | ForEach-Object { $_.ToString('x2') })

Write-Host ""
Write-Host "Copy this entire string into Render → appointment-module-api → Environment → INTERNAL_ARTISAN_TOKEN:" -ForegroundColor Cyan
Write-Host ""
Write-Host $token -ForegroundColor Yellow
Write-Host ""
Write-Host "Length: $($token.Length) chars" -ForegroundColor Gray
Write-Host ""
