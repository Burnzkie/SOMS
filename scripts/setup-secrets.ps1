# scripts/setup-secrets.ps1
#
# One-time move: relocates soms/.env and soms_mobile_app/android/key.properties
# out of the D:\soms\ repo entirely, into a sibling D:\soms-secrets\ folder.
# bootstrap/app.php and android/app/build.gradle.kts have both been updated
# to read from that external location automatically once it exists.
#
# After running this once, D:\soms\ (and any zip made from it) will
# structurally never contain either file again — there's nothing left in
# the repo to accidentally include.
#
# Run from anywhere; it locates the repo root relative to this script's
# own location (scripts/ sits at the repo root).

$ErrorActionPreference = "Stop"

$repoRoot    = Split-Path -Parent $PSScriptRoot
$secretsRoot = Join-Path (Split-Path -Parent $repoRoot) "soms-secrets"

$moves = @(
    @{
        Source = Join-Path $repoRoot "soms\.env"
        Dest   = Join-Path $secretsRoot "soms\.env"
    },
    @{
        Source = Join-Path $repoRoot "soms_mobile_app\android\key.properties"
        Dest   = Join-Path $secretsRoot "soms_mobile_app\key.properties"
    }
)

Write-Host "Repo root:    $repoRoot"
Write-Host "Secrets root: $secretsRoot (sibling of the repo -- outside it)"
Write-Host ""

foreach ($move in $moves) {
    $src = $move.Source
    $dst = $move.Dest

    if (-not (Test-Path $src)) {
        Write-Host "SKIP  (not found): $src"
        continue
    }

    $dstDir = Split-Path -Parent $dst
    if (-not (Test-Path $dstDir)) {
        New-Item -ItemType Directory -Path $dstDir -Force | Out-Null
    }

    if (Test-Path $dst) {
        Write-Host "SKIP  (already exists at destination): $dst"
        continue
    }

    $srcSize = (Get-Item $src).Length
    Move-Item -Path $src -Destination $dst

    $dstSize = (Get-Item $dst).Length
    if ($dstSize -ne $srcSize) {
        Write-Host "MOVED: $src" -ForegroundColor Red
        Write-Host "   -> $dst" -ForegroundColor Red
        Write-Host "   SIZE MISMATCH: $srcSize bytes moved, $dstSize bytes at destination." -ForegroundColor Red
        Write-Host "   Do not delete any backup copy until you've checked this file." -ForegroundColor Red
        continue
    }

    Write-Host "MOVED: $src"
    Write-Host "   -> $dst  (verified: $dstSize bytes)"
}

Write-Host ""
Write-Host "Done. Verify the app still boots (php artisan serve / flutter build" -ForegroundColor Green
Write-Host "apk --release) before deleting any backup copy you may have made." -ForegroundColor Green
Write-Host ""
Write-Host "Note: the release keystore (.jks) was already external at" -ForegroundColor Yellow
Write-Host "D:\soms-backup\soms-release-key.jks and key.properties still points" -ForegroundColor Yellow
Write-Host "there directly -- this script does not touch the .jks file itself." -ForegroundColor Yellow
