param([string]$OutputDirectory = (Join-Path (Split-Path -Parent $PSScriptRoot) 'dist'))
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$slug = 'cost-importer-for-woocommerce'
$stage = Join-Path $OutputDirectory 'stage'
$zip = Join-Path $OutputDirectory "$slug.zip"
if (Test-Path -LiteralPath $stage) { Remove-Item -LiteralPath $stage -Recurse -Force }
if (Test-Path -LiteralPath $zip) { Remove-Item -LiteralPath $zip -Force }
New-Item -ItemType Directory -Force -Path (Join-Path $stage $slug) | Out-Null
$allow = @('assets','includes','cost-importer-for-woocommerce.php','README.md','CHANGELOG.md','LICENSE','PRIVACY.md','SUPPORT.md')
foreach ($item in $allow) { Copy-Item -LiteralPath (Join-Path $projectRoot $item) -Destination (Join-Path $stage $slug) -Recurse -Force }
Compress-Archive -LiteralPath (Join-Path $stage $slug) -DestinationPath $zip -CompressionLevel Optimal
Remove-Item -LiteralPath $stage -Recurse -Force
$hash = (Get-FileHash -Algorithm SHA256 -LiteralPath $zip).Hash.ToLowerInvariant()
Set-Content -LiteralPath "$zip.sha256" -Value "$hash  $slug.zip" -Encoding ascii
Write-Output "ZIP=$zip"
Write-Output "SHA256=$hash"
