param([ValidateRange(1, 65535)][int]$Port = 8000)

# Process-only PHP settings; the system php.ini is unchanged.
Push-Location (Join-Path $PSScriptRoot 'public')
try {
    & php -d upload_max_filesize=20M -d post_max_size=24M -d display_errors=0 -d log_errors=1 -S "127.0.0.1:$Port" '../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'
}
finally {
    Pop-Location
}
