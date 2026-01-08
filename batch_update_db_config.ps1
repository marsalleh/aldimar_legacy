# Batch Update Script for Database Configuration
# This script updates all remaining PHP files to use centralized db_config.php

$files = @(
    @{Path="manage_inventory.php"; Line=10; Redirect="login.php"},
    @{Path="manage_supplier.php"; Line=9; Redirect="login.php"},
    @{Path="record_sales.php"; Line=9; Redirect="login.php"},
    @{Path="report.php"; Line=9; Redirect="login.php"},
    @{Path="admin_notification.php"; Line=9; Redirect=null},
    @{Path="employee_notification.php"; Line=10; Redirect=null}
)

Write-Host "Batch Database Configuration Update Script" -ForegroundColor Cyan
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host ""

foreach ($file in $files) {
    Write-Host "Processing: $($file.Path)" -ForegroundColor Yellow
    
    # Read file content
    $content = Get-Content $file.Path -Raw
    
    # Replace database connection
    $pattern = '\$conn = new mysqli\("localhost", "root", "", "aldimar_db"\);[\r\n]+if \(\$conn->connect_error\)[\r\n]+\s+die\("Connection failed: " \. \$conn->connect_error\);'
    $replacement = 'require_once ''db_config.php'';'
    
    $content = $content -replace $pattern, $replacement
    
    # Replace login.php redirects with index.php if specified
    if ($file.Redirect) {
        $content = $content -replace "window\.location\.href='login\.php'", "window.location.href='index.php'"
        $content = $content -replace 'window\.location\.href="login\.php"', 'window.location.href="index.php"'
    }
    
    # Write back
    Set-Content -Path $file.Path -Value $content -NoNewline
    
    Write-Host "  ✓ Updated database connection" -ForegroundColor Green
    if ($file.Redirect) {
        Write-Host "  ✓ Updated redirects to index.php" -ForegroundColor Green
    }
}

Write-Host ""
Write-Host "All files updated successfully!" -ForegroundColor Green
