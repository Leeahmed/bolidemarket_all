param([string]$Php = 'C:\xampp\php\php.exe', [int]$Port = 8000)
$ErrorActionPreference = 'Stop'
if (Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue) { throw "Le port $Port est déjà utilisé. Arrêtez le serveur existant avant de relancer." }
$projectPath = $PSScriptRoot
$tempPath = Join-Path $projectPath 'storage\app\upload-tmp'
$logPath = Join-Path $projectPath 'storage\logs'
$routerPath = Join-Path $projectPath 'vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php'
New-Item -ItemType Directory -Path $tempPath -Force | Out-Null
# PHP needs a writable upload directory before Laravel starts handling the request.
$arguments = @('-d', ('upload_tmp_dir="' + $tempPath + '"'), '-S', ('127.0.0.1:' + $Port), ('"' + $routerPath + '"'))
$server = Start-Process -FilePath $Php -ArgumentList $arguments -WorkingDirectory (Join-Path $projectPath 'public') -WindowStyle Hidden -RedirectStandardOutput (Join-Path $logPath 'local-server.log') -RedirectStandardError (Join-Path $logPath 'local-server-error.log') -PassThru
Start-Sleep -Milliseconds 300
if ($server.HasExited) { throw 'Le serveur PHP a quitté : consulter storage/logs/local-server-error.log.' }
$server.Id | Set-Content -LiteralPath (Join-Path $logPath 'local-server.pid')
Write-Output "API locale : http://127.0.0.1:$Port (PID $($server.Id))"
