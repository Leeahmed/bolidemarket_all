param(
    [ValidateSet('doctor', 'devices', 'build', 'run')]
    [string]$Action = 'doctor',
    [string]$ApiBaseUrl = 'http://10.0.2.2:8000/api/v1',
    [string]$DeviceId,
    [string]$ReverbAppKey = '',
    [string]$ReverbHost = '',
    [int]$ReverbPort = 8080,
    [switch]$ReverbTls
)

$ErrorActionPreference = 'Stop'
$mobileProject = Split-Path -Parent $PSScriptRoot
$mobileWorkspace = Split-Path -Parent $mobileProject
$mobileTools = Join-Path $mobileWorkspace '.tools/mobile-android'
$mobileFlutter = Join-Path $mobileWorkspace '.tools/flutter/bin/flutter.bat'
$mobileSdk = Join-Path $mobileTools 'sdk'
$mobileJava = Get-ChildItem -LiteralPath (Join-Path $mobileTools 'java') -Directory |
    Where-Object { Test-Path -LiteralPath (Join-Path $_.FullName 'bin/java.exe') } |
    Select-Object -First 1

if (-not $mobileJava) { throw 'JDK portable absent dans .tools/mobile-android/java.' }
if (-not (Test-Path -LiteralPath $mobileSdk)) { throw 'SDK Android absent dans .tools/mobile-android/sdk.' }
if (-not (Test-Path -LiteralPath $mobileFlutter)) { throw 'SDK Flutter local absent.' }

# Variables de ce processus uniquement ; aucun changement de configuration Windows.
$env:JAVA_HOME = $mobileJava.FullName
$env:ANDROID_HOME = $mobileSdk
$env:ANDROID_SDK_ROOT = $mobileSdk
$env:GRADLE_USER_HOME = Join-Path $mobileTools 'gradle'
$env:Path = "$($mobileJava.FullName)/bin;$mobileSdk/platform-tools;$mobileSdk/cmdline-tools/latest/bin;$env:Path"

$mobileDefines = @("--dart-define=API_BASE_URL=$ApiBaseUrl", "--dart-define=REVERB_APP_KEY=$ReverbAppKey", "--dart-define=REVERB_HOST=$ReverbHost", "--dart-define=REVERB_PORT=$ReverbPort", "--dart-define=REVERB_TLS=$($ReverbTls.IsPresent.ToString().ToLowerInvariant())")

Push-Location $mobileProject
try {
    switch ($Action) {
        'doctor' { & $mobileFlutter doctor -v }
        'devices' { & $mobileFlutter devices }
        'build' { & $mobileFlutter build apk --debug @mobileDefines }
        'run' {
            $mobileRunArgs = @('run') + $mobileDefines
            if ($DeviceId) { $mobileRunArgs += @('-d', $DeviceId) }
            & $mobileFlutter @mobileRunArgs
        }
    }
    $mobileExitCode = $LASTEXITCODE
} finally {
    Pop-Location
}
exit $mobileExitCode
