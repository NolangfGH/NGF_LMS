[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$releaseId = 'release-' + [DateTime]::UtcNow.ToString('yyyyMMddTHHmmssfffZ')
$releaseRoot = Join-Path $projectRoot ('.codex\hostinger\' + $releaseId)
$appRoot = Join-Path $releaseRoot 'ngf_lms'
$webRoot = Join-Path $releaseRoot 'public_html'
$archivePath = Join-Path $releaseRoot 'source.zip'
$sourcePaths = @('app', 'artisan', 'bootstrap', 'composer.json', 'composer.lock', 'config', 'database', 'public', 'resources', 'routes', 'storage')

git -C $projectRoot diff --quiet HEAD -- @sourcePaths
if ($LASTEXITCODE -ne 0) { throw 'Commit application source changes before building a release.' }

New-Item -ItemType Directory -Path $appRoot -Force | Out-Null
New-Item -ItemType Directory -Path $webRoot -Force | Out-Null
git -C $projectRoot archive --format=zip ('--output=' + $archivePath) HEAD -- @sourcePaths
if ($LASTEXITCODE -ne 0) { throw 'Could not export the committed application source.' }
Expand-Archive -LiteralPath $archivePath -DestinationPath $appRoot
Copy-Item -LiteralPath (Join-Path $PSScriptRoot 'production.env.example') -Destination (Join-Path $appRoot '.env.example')

# Install in the isolated release, preserving the local development installation.
Push-Location $appRoot
try {
    composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-scripts
    if ($LASTEXITCODE -ne 0) { throw 'Production dependency installation failed.' }
    composer check-platform-reqs --no-dev
    if ($LASTEXITCODE -ne 0) { throw 'Production PHP platform requirements failed.' }
    php artisan package:discover --no-interaction
    if ($LASTEXITCODE -ne 0) { throw 'Production package discovery failed.' }
} finally {
    Pop-Location
}

Get-ChildItem -LiteralPath (Join-Path $appRoot 'public') -Force | ForEach-Object {
    Copy-Item -LiteralPath $_.FullName -Destination $webRoot -Recurse
}
Copy-Item -LiteralPath (Join-Path $PSScriptRoot 'public-index.php') -Destination (Join-Path $webRoot 'index.php') -Force

$sourceCommit = (git -C $projectRoot rev-parse HEAD).Trim()
$manifest = [ordered]@{
    project = 'NGF_C++CodeQuest'
    source_commit = $sourceCommit
    built_at_utc = [DateTime]::UtcNow.ToString('o')
    php_minimum = '8.4.1'
    php_recommended = '8.5'
    includes_local_environment = $false
    includes_classroom_database = $false
    includes_private_uploads = $false
}
$manifest | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $releaseRoot 'release.json') -Encoding utf8NoBOM
Copy-Item -LiteralPath (Join-Path $PSScriptRoot 'README.md') -Destination (Join-Path $releaseRoot 'DEPLOYMENT.md')

$unexpectedFiles = @(Get-ChildItem -LiteralPath $appRoot -Recurse -Force -File | Where-Object {
    $_.Name -eq '.env' -or $_.Name -match '\.(sqlite.*|db|sql|key|pem|pfx|p12|dump)$'
})
if ($unexpectedFiles.Count -gt 0) { throw 'Unexpected environment, database, or private-key file in the release.' }

$bundlePath = Join-Path $releaseRoot 'NGF_LMS-hostinger.zip'
tar -a -cf $bundlePath -C $releaseRoot ngf_lms public_html release.json DEPLOYMENT.md
if ($LASTEXITCODE -ne 0) { throw 'Could not create the Hostinger upload archive.' }
$bundleHash = (Get-FileHash -LiteralPath $bundlePath -Algorithm SHA256).Hash
$bundleHash | Set-Content -LiteralPath ($bundlePath + '.sha256') -Encoding ascii
$releaseRoot | Set-Content -LiteralPath (Join-Path $projectRoot '.codex\hostinger\latest-release.txt') -Encoding utf8NoBOM
[pscustomobject]@{Release=$releaseRoot;Archive=$bundlePath;Commit=$sourceCommit;SHA256=$bundleHash;SizeMB=[math]::Round((Get-Item -LiteralPath $bundlePath).Length / 1MB,2)} | ConvertTo-Json
