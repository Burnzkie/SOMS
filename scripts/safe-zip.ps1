# scripts/safe-zip.ps1
#
# Exports the project as a zip that can never contain .env, key.properties,
# the release keystore, vendor/, node_modules/, or anything else this repo's
# .gitignore files already exclude -- because it uses `git archive`, which
# only ever includes files git is actually tracking. There's no exclude
# list to maintain here; it just inherits .gitignore.
#
# Note: git archive exports the last COMMIT, not your working files --
# if you have uncommitted changes, this script warns and asks first
# rather than silently zipping stale content. Pass -Force to skip that
# prompt (e.g. for automation).
#
# This only helps for files that get INTO the repo folder in the first
# place. After running scripts/setup-secrets.ps1 once, .env and
# key.properties live outside D:\soms\ entirely, so even a plain
# right-click "Send to > Compressed folder" (which does NOT respect
# .gitignore) would already be safe too -- this script is the belt,
# physical separation is the suspenders.
#
# Usage:  .\scripts\safe-zip.ps1 [-OutFile soms-upload.zip] [-Force]

param(
    [string]$OutFile = "soms-upload.zip",
    [switch]$Force
)

$ErrorActionPreference = "Stop"
$repoRoot = Split-Path -Parent $PSScriptRoot
Set-Location $repoRoot

# git archive HEAD only exports the last COMMIT -- not your current
# working files. Edit something and run this without committing first,
# and you'd get a zip silently missing your latest changes with no
# warning. Catch that here instead.
$dirty = git status --porcelain
if ($dirty -and -not $Force) {
    Write-Host ""
    Write-Host "You have uncommitted changes -- git archive only exports the" -ForegroundColor Yellow
    Write-Host "last COMMIT, so none of these would be in the zip:" -ForegroundColor Yellow
    Write-Host ""
    $dirty | ForEach-Object { Write-Host "  $_" -ForegroundColor Yellow }
    Write-Host ""
    $answer = Read-Host "Commit first (recommended), or type 'yes' to export the last commit anyway"
    if ($answer -ne "yes") {
        Write-Host "Cancelled. Commit your changes, then re-run this script."
        exit 1
    }
}

if (Test-Path $OutFile) {
    Remove-Item $OutFile
}

Write-Host "Exporting tracked files from git HEAD to $OutFile ..."
git archive -o $OutFile HEAD

Write-Host ""
Write-Host "Scanning the result for anything that shouldn't be there..."

$suspicious = @(
    '\.env$', '\.env\.', 'key\.properties$', '\.jks$', '\.keystore$',
    'google-services\.json$', 'GoogleService-Info\.plist$', '\.pem$', '\.p12$'
)

# List zip contents without extracting (uses .NET, no external tool needed).
Add-Type -AssemblyName System.IO.Compression.FileSystem
$zip = [System.IO.Compression.ZipFile]::OpenRead((Resolve-Path $OutFile))
$hits = $zip.Entries | Where-Object {
    $name = $_.FullName
    $suspicious | Where-Object { $name -match $_ }
}
$zip.Dispose()

if ($hits) {
    Write-Host ""
    Write-Host "STOP -- found files that look sensitive in $OutFile :" -ForegroundColor Red
    $hits | ForEach-Object { Write-Host "  $($_.FullName)" -ForegroundColor Red }
    Write-Host ""
    Write-Host "Do not upload/share this zip. Check whether these were" -ForegroundColor Red
    Write-Host "accidentally committed to git (they shouldn't match any" -ForegroundColor Red
    Write-Host "current .gitignore rule if they were)." -ForegroundColor Red
    exit 1
}

Write-Host "Clean -- no sensitive filenames found in $OutFile." -ForegroundColor Green
