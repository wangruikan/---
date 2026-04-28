param(
    [string]$ConfigPath = ".vscode/sftp.json",
    [string]$LogDir = "upload_logs"
)

$ErrorActionPreference = "Stop"

function New-FtpDirectoryIfNeeded {
    param(
        [string]$RemoteDir,
        [string]$FtpHost,
        [string]$FtpUser,
        [string]$FtpPass
    )

    if ([string]::IsNullOrWhiteSpace($RemoteDir) -or $RemoteDir -eq "/") {
        return
    }

    $parts = $RemoteDir.Trim("/").Split("/")
    $current = ""

    foreach ($part in $parts) {
        $current += "/" + $part
        try {
            $request = [System.Net.FtpWebRequest]::Create("ftp://$FtpHost$current")
            $request.Method = [System.Net.WebRequestMethods+Ftp]::MakeDirectory
            $request.Credentials = New-Object System.Net.NetworkCredential($FtpUser, $FtpPass)
            $request.UseBinary = $true
            $request.UsePassive = $true
            $request.EnableSsl = $false
            $request.KeepAlive = $false

            $response = $request.GetResponse()
            $response.Close()
        } catch {
            # ignore if directory exists
        }
    }
}

function Upload-FtpFile {
    param(
        [string]$LocalPath,
        [string]$RemotePath,
        [string]$FtpHost,
        [string]$FtpUser,
        [string]$FtpPass
    )

    $remoteDir = [System.IO.Path]::GetDirectoryName($RemotePath).Replace("\", "/")
    if ($remoteDir -and $remoteDir -ne ".") {
        New-FtpDirectoryIfNeeded -RemoteDir $remoteDir -FtpHost $FtpHost -FtpUser $FtpUser -FtpPass $FtpPass
    }

    $uri = "ftp://$FtpHost$RemotePath"
    $request = [System.Net.FtpWebRequest]::Create($uri)
    $request.Method = [System.Net.WebRequestMethods+Ftp]::UploadFile
    $request.Credentials = New-Object System.Net.NetworkCredential($FtpUser, $FtpPass)
    $request.UseBinary = $true
    $request.UsePassive = $true
    $request.EnableSsl = $false
    $request.KeepAlive = $false

    $fileBytes = [System.IO.File]::ReadAllBytes($LocalPath)
    $request.ContentLength = $fileBytes.Length

    $stream = $request.GetRequestStream()
    $stream.Write($fileBytes, 0, $fileBytes.Length)
    $stream.Close()

    $response = $request.GetResponse()
    $status = $response.StatusDescription
    $response.Close()

    return $status
}

$projectRoot = (Resolve-Path ".").Path
$cfgAbs = Join-Path $projectRoot $ConfigPath

if (-not (Test-Path $cfgAbs)) {
    throw "Config not found: $cfgAbs"
}

$cfg = Get-Content -Raw $cfgAbs | ConvertFrom-Json
$ftpHost = [string]$cfg.host
$ftpUser = [string]$cfg.username
$ftpPass = [string]$cfg.password

$targets = New-Object System.Collections.Generic.List[object]

$indexPath = Join-Path $projectRoot "public/index.html"
if (Test-Path $indexPath) {
    $targets.Add([pscustomobject]@{
        Local  = $indexPath
        Remote = "/public/index.html"
    })
}

$assetsPath = Join-Path $projectRoot "public/assets"
if (-not (Test-Path $assetsPath)) {
    throw "Assets directory not found: $assetsPath"
}

Get-ChildItem -Path $assetsPath -Recurse -File | ForEach-Object {
    $rel = $_.FullName.Substring($projectRoot.Length).TrimStart("\").Replace("\", "/")
    $targets.Add([pscustomobject]@{
        Local  = $_.FullName
        Remote = "/" + $rel
    })
}

$total = $targets.Count
if ($total -eq 0) {
    Write-Output "No files to upload."
    exit 0
}

$logAbsDir = Join-Path $projectRoot $LogDir
if (-not (Test-Path $logAbsDir)) {
    New-Item -ItemType Directory -Path $logAbsDir | Out-Null
}

$ts = Get-Date -Format "yyyyMMdd_HHmmss"
$logPath = Join-Path $logAbsDir ("upload_frontend_" + $ts + ".csv")

$records = New-Object System.Collections.Generic.List[object]
$ok = 0
$fail = 0

Write-Output ("Start upload. Total files: {0}" -f $total)

for ($i = 0; $i -lt $total; $i++) {
    $item = $targets[$i]
    $seq = $i + 1
    $startTime = Get-Date
    $size = (Get-Item $item.Local).Length
    $status = "OK"
    $errorMsg = ""

    try {
        Upload-FtpFile -LocalPath $item.Local -RemotePath $item.Remote -FtpHost $ftpHost -FtpUser $ftpUser -FtpPass $ftpPass | Out-Null
        $ok++
        Write-Output ("[{0}/{1}] OK   {2}" -f $seq, $total, $item.Remote)
    } catch {
        $fail++
        $status = "FAIL"
        $errorMsg = $_.Exception.Message
        Write-Output ("[{0}/{1}] FAIL {2} :: {3}" -f $seq, $total, $item.Remote, $errorMsg)
    }

    $endTime = Get-Date
    $durationMs = [int](($endTime - $startTime).TotalMilliseconds)

    $records.Add([pscustomobject]@{
        seq         = $seq
        local       = $item.Local
        remote      = $item.Remote
        size        = $size
        status      = $status
        error       = $errorMsg
        started_at  = $startTime.ToString("yyyy-MM-dd HH:mm:ss.fff")
        finished_at = $endTime.ToString("yyyy-MM-dd HH:mm:ss.fff")
        duration_ms = $durationMs
    })
}

$records | Export-Csv -Path $logPath -NoTypeInformation -Encoding UTF8

Write-Output ("Upload finished. total={0} ok={1} fail={2}" -f $total, $ok, $fail)
Write-Output ("Upload list saved: {0}" -f $logPath)
