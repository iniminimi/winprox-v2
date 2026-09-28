# Handleiding-screenshots: wissen → capture → commit → push (altijd volledige pipeline).
$ErrorActionPreference = 'Stop'
$root = Resolve-Path (Join-Path $PSScriptRoot '..')
Set-Location $root

$manualRoot = Join-Path $root 'public\images\manual'
$locales = @('nl', 'en', 'fr', 'de', 'es', 'it')

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

function Clear-ManualCapturePngs {
    param([string]$BasePath, [string[]]$LocaleDirs)

    $removed = 0
    foreach ($locale in $LocaleDirs) {
        $dir = Join-Path $BasePath $locale
        if (-not (Test-Path $dir)) {
            New-Item -ItemType Directory -Path $dir -Force | Out-Null
            continue
        }

        Get-ChildItem -Path $dir -Filter '*.png' -File -ErrorAction SilentlyContinue | ForEach-Object {
            Remove-Item -LiteralPath $_.FullName -Force
            $removed++
        }
    }

    return $removed
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

if ($env:MANUAL_CAPTURE_ONLY) {
    Write-Host "Stap 1/5: MANUAL_CAPTURE_ONLY='$($env:MANUAL_CAPTURE_ONLY)' — subset-run: bestaande PNGs blijven staan, geen commit/push."
} else {
    Write-Host 'Stap 1/5: oude handleiding-PNGs verwijderen...'
    $removedCount = Clear-ManualCapturePngs -BasePath $manualRoot -LocaleDirs $locales
    Write-Host "  $removedCount bestaande PNG(s) verwijderd."
}

Write-Host 'Stap 2/5: Playwright Chromium controleren...'
$capturePkg = Join-Path $root 'scripts\capture-pkg'
Push-Location $capturePkg
if (-not (Test-Path 'node_modules\playwright')) {
    npm ci
}
npx playwright install chromium
Pop-Location

Write-Host 'Stap 2b/5: capture-tenant voorbereiden (trial/toegang, ESG, Time, IoT, Clock Point)...'
$prepareOutput = php artisan winprox:prepare-manual-capture 2>&1 | Out-String
if ($LASTEXITCODE -ne 0) {
    throw 'Voorbereiden capture-tenant mislukt (MANUAL_CAPTURE_EMAIL in .env?).'
}
[regex]::Matches($prepareOutput, '(MANUAL_CAPTURE_[A-Z0-9_]+)=(\S+)') | ForEach-Object {
    Set-Item -Path "env:$($_.Groups[1].Value)" -Value $_.Groups[2].Value
    Write-Host "  $($_.Groups[1].Value) uit prepare: $($_.Groups[2].Value)"
}

Write-Host "Stap 3/5: capture tegen $($env:MANUAL_CAPTURE_BASE_URL) ..."
node (Join-Path $root 'scripts\capture-manual-screenshots.mjs')
if ($LASTEXITCODE -ne 0) {
    throw "Capture mislukt (exit $LASTEXITCODE)."
}

$pngCount = (Get-ChildItem -Path $manualRoot -Filter '*.png' -Recurse -ErrorAction SilentlyContinue | Measure-Object).Count
if ($pngCount -eq 0) {
    throw 'Capture stopte zonder PNGs.'
}

Write-Host "  $pngCount nieuwe PNG(s) aangemaakt."

if ($env:MANUAL_CAPTURE_ONLY) {
    Write-Host 'Subset-run klaar — commit/push zelf doen na controle van de PNGs.'
    exit 0
}

Write-Host 'Stap 4/5: git commit...'
$pathsToAdd = $locales | ForEach-Object { "public/images/manual/$_" }
git add @pathsToAdd

$status = git status --porcelain -- public/images/manual/
if ($status -eq '') {
    Write-Host 'Geen wijzigingen om te committen (PNG''s identiek aan git?).'
    exit 0
}

$commitMessage = "docs: handleiding-screenshots bijgewerkt ($(Get-Date -Format 'yyyy-MM-dd'))"
git commit -m $commitMessage
if ($LASTEXITCODE -ne 0) {
    throw 'git commit mislukt.'
}

Write-Host 'Stap 5/5: git push...'
git push origin HEAD
if ($LASTEXITCODE -ne 0) {
    throw 'git push mislukt.'
}

Write-Host "Klaar. $pngCount PNG(s) gecommit en gepusht naar GitHub."
