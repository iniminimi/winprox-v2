# Genereer handleiding-screenshots op je Windows-devmachine tegen productie.
# Vereist: node, npm. Playwright Chromium wordt automatisch geïnstalleerd.
$ErrorActionPreference = 'Stop'
$root = Resolve-Path (Join-Path $PSScriptRoot '..')
Set-Location $root

function Import-ManualCaptureEnv {
    param([string]$EnvPath)
    if (-not (Test-Path $EnvPath)) {
        return
    }

    Get-Content $EnvPath | ForEach-Object {
        $line = $_.Trim()
        if ($line -eq '' -or $line.StartsWith('#')) {
            return
        }

        $hash = $line.IndexOf('#')
        if ($hash -ge 0) {
            $line = $line.Substring(0, $hash).TrimEnd()
        }

        if ($line -notmatch '^MANUAL_CAPTURE_[A-Z0-9_]+=(.*)$') {
            return
        }

        $name, $value = $line -split '=', 2
        $value = $value.Trim().Trim('"').Trim("'")
        if ($value -ne '') {
            Set-Item -Path "env:$name" -Value $value
        }
    }
}

Import-ManualCaptureEnv (Join-Path $root '.env')

if (-not $env:MANUAL_CAPTURE_BASE_URL) {
    $env:MANUAL_CAPTURE_BASE_URL = 'https://winprox.app'
}

Remove-Item Env:MANUAL_CAPTURE_HOST -ErrorAction SilentlyContinue
Remove-Item Env:MANUAL_CAPTURE_NODE_BIN -ErrorAction SilentlyContinue
Remove-Item Env:PLAYWRIGHT_BROWSERS_PATH -ErrorAction SilentlyContinue
Remove-Item Env:MANUAL_CAPTURE_PLAYWRIGHT_BROWSERS_PATH -ErrorAction SilentlyContinue

if (-not $env:MANUAL_CAPTURE_EMAIL -or -not $env:MANUAL_CAPTURE_PASSWORD) {
    Write-Error 'Zet MANUAL_CAPTURE_EMAIL en MANUAL_CAPTURE_PASSWORD in .env'
}

$capturePkg = Join-Path $root 'scripts\capture-pkg'
Push-Location $capturePkg
if (-not (Test-Path 'node_modules\playwright')) {
    npm ci
}
npx playwright install chromium
Pop-Location

Write-Host "Capture tegen $($env:MANUAL_CAPTURE_BASE_URL) ..."
node (Join-Path $root 'scripts\capture-manual-screenshots.mjs')
Write-Host 'Klaar. PNGs in public/images/manual/{nl,en,fr,de}/'
