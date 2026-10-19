[CmdletBinding()]
param(
    [ValidateSet('dev', 'build', 'setup', 'test')]
    [string]$Action = 'dev'
)

$ErrorActionPreference = 'Stop'
$taskToolsRoot = Join-Path (Split-Path $PSScriptRoot -Parent) '.tools'
$taskOriginalPath = $env:Path
$taskExitCode = 0

Push-Location $PSScriptRoot
try {
    if ($Action -ne 'test') {
        $taskLocalNode = Get-ChildItem -LiteralPath $taskToolsRoot -Directory -Filter 'node-v24.*-win-x64' -ErrorAction SilentlyContinue |
            Where-Object { Test-Path -LiteralPath (Join-Path $_.FullName 'node.exe') } |
            Sort-Object LastWriteTime -Descending |
            Select-Object -First 1

        if ($taskLocalNode) {
            $env:Path = $taskLocalNode.FullName + ';' + $env:Path
        }

        if (-not (Get-Command node -ErrorAction SilentlyContinue)) {
            throw 'Node.js 24 LTS is required. Install it, then run this command again.'
        }

        $taskNodeMajor = & node -p 'Number.parseInt(process.versions.node)'
        if ($LASTEXITCODE -ne 0 -or [int]$taskNodeMajor -ne 24) {
            throw 'Node.js 24 LTS is required. Install it or restore the shared ../.tools runtime.'
        }
    }

    switch ($Action) {
        'build' { & npm.cmd run build }
        'test' { & php artisan test }
        default {
            $taskComposer = Join-Path $taskToolsRoot 'composer.phar'
            if (Test-Path -LiteralPath $taskComposer) {
                & php $taskComposer $Action
            }
            else {
                & composer $Action
            }
        }
    }

    $taskExitCode = $LASTEXITCODE
}
finally {
    $env:Path = $taskOriginalPath
    Pop-Location
}

exit $taskExitCode
