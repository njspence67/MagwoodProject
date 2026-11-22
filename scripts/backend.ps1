param(
    [ValidateSet('setup', 'serve', 'test', 'lint', 'prototype')]
    [string]$Action = 'serve'
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent
$portablePhp = Join-Path $projectRoot '.tools\php\php.exe'
$portableComposer = Join-Path $projectRoot '.tools\composer.phar'
$phpExecutable = if (Test-Path -LiteralPath $portablePhp) { $portablePhp } else { (Get-Command php -ErrorAction Stop).Source }

function Invoke-Php {
    & $phpExecutable @args
    if ($LASTEXITCODE -ne 0) { throw "PHP command failed with exit code $LASTEXITCODE" }
}

Push-Location (Join-Path $projectRoot 'backend')
try {
    switch ($Action) {
        'setup' {
            if (Test-Path -LiteralPath $portableComposer) {
                Invoke-Php $portableComposer install --no-interaction --prefer-dist
            } else {
                & composer install --no-interaction --prefer-dist
                if ($LASTEXITCODE -ne 0) { throw 'Composer install failed' }
            }
            if (-not (Test-Path -LiteralPath '.env')) {
                Copy-Item -LiteralPath '.env.example' -Destination '.env'
            }
            # Never rotate an existing application's encryption key during setup.
            if (Select-String -LiteralPath '.env' -Pattern '^APP_KEY=\s*$' -Quiet) {
                Invoke-Php artisan key:generate
            }
        }
        'serve' { Invoke-Php artisan serve --host=127.0.0.1 --port=8000 }
        'test' { Invoke-Php vendor/phpunit/phpunit/phpunit }
        'lint' { Invoke-Php vendor/laravel/pint/builds/pint --test }
        'prototype' { Invoke-Php -S 127.0.0.1:4173 -t (Join-Path $projectRoot 'prototype') }
    }
} finally {
    Pop-Location
}
