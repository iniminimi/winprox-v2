param(
    [string]$Root = 'C:\winprox'
)

$ip = (
    Get-NetIPConfiguration |
    Where-Object { $_.IPv4DefaultGateway -ne $null -and $_.NetAdapter.Status -eq 'Up' } |
    Select-Object -First 1
).IPv4Address.IPAddress

if (-not $ip) {
    $ip = (
        Get-NetIPAddress -AddressFamily IPv4 |
        Where-Object { $_.IPAddress -notlike '127.*' -and $_.IPAddress -notlike '169.254*' } |
        Select-Object -First 1
    ).IPAddress
}

if ($ip) {
    $envFile = Join-Path $Root '.env'
    (Get-Content $envFile) -replace '^APP_URL=.*', "APP_URL=http://$ip" | Set-Content $envFile -Encoding UTF8
    & php (Join-Path $Root 'artisan') config:clear | Out-Null
}

Write-Output $ip
