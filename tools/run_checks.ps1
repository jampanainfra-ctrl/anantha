param([string]$PhpBinary='D:\xampp\php\php.exe',[string]$NodeBinary='C:\Program Files\nodejs\node.exe',[switch]$SkipHttp)
$ErrorActionPreference='Stop'
$projectRoot=Split-Path -Parent $PSScriptRoot
Push-Location -LiteralPath $projectRoot
try {
    $tests=@('test_discounts.php','test_referrals.php','test_required_spacing.php','test_returning_customers.php','test_package_bulk.php','test_admin_recovery.php','test_google_reviews.php','test_routes.php','test_admin_management.php')
    foreach($test in $tests){ & $PhpBinary (Join-Path $PSScriptRoot $test); if($LASTEXITCODE -ne 0){throw "Failed: $test"} }
    & $NodeBinary (Join-Path $PSScriptRoot 'test_mail_transports.cjs') $PhpBinary
    if($LASTEXITCODE -ne 0){throw 'Mail transport tests failed.'}
    $phpFiles=Get-ChildItem -LiteralPath $projectRoot -Filter '*.php' -File -Recurse | Where-Object { $_.FullName -notmatch '[\\/](vendor|storage|\.git)[\\/]' }
    foreach($file in $phpFiles){$result=& $PhpBinary -l $file.FullName 2>&1; if($LASTEXITCODE -ne 0){throw ($result -join "`n")} }
    Write-Output ('PHP syntax passed: '+$phpFiles.Count+' files.')
    $jsFiles=Get-ChildItem -LiteralPath (Join-Path $projectRoot 'assets') -Filter '*.js' -File
    foreach($file in $jsFiles){ & $NodeBinary --check $file.FullName; if($LASTEXITCODE -ne 0){throw ('JavaScript syntax failed: '+$file.Name)} }
    Write-Output ('JavaScript syntax passed: '+$jsFiles.Count+' asset files.')
    if(!$SkipHttp){ & $NodeBinary (Join-Path $PSScriptRoot 'test_http_routes.cjs'); if($LASTEXITCODE -ne 0){throw 'HTTP checks failed. Ensure Apache is serving this checkout.'} }
} finally { Pop-Location }
