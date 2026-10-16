# =============================================================================
# PERMANENT PRODUCTION DEPLOY SCRIPT FOR CRICKETBOARD.SITE.JE
# Target Remote Directory: /cricketboard.site.je/htdocs/ ONLY
# NOTE: DO NOT upload to /htdocs/ (that is bapsyuvak.site.je root!)
# =============================================================================

$ftpHost = "ftpupload.net"
$ftpUser = "if0_42865777"
$ftpPass = "Jai2195cha"

function Upload-FileToFtp {
    param(
        [string]$localPath,
        [string]$remotePath
    )
    
    $uri = "ftp://${ftpHost}${remotePath}"
    Write-Host "Uploading $(Split-Path $localPath -Leaf) -> $uri..." -ForegroundColor Cyan
    
    try {
        $req = [System.Net.FtpWebRequest]::Create($uri)
        $req.Method = [System.Net.WebRequestMethods+Ftp]::UploadFile
        $req.Credentials = New-Object System.Net.NetworkCredential($ftpUser, $ftpPass)
        $req.UsePassive = $true
        $req.UseBinary = $true
        $req.KeepAlive = $false
        
        $bytes = [System.IO.File]::ReadAllBytes($localPath)
        $req.ContentLength = $bytes.Length
        
        $stream = $req.GetRequestStream()
        $stream.Write($bytes, 0, $bytes.Length)
        $stream.Close()
        
        $resp = $req.GetResponse()
        Write-Host " [OK] Uploaded: $remotePath" -ForegroundColor Green
        $resp.Close()
    } catch {
        Write-Host " [FAILED] $remotePath : $($_.Exception.Message)" -ForegroundColor Red
    }
}

$filesToDeploy = @(
    "index.html",
    "cricket_score.HTML",
    "api.php",
    ".htaccess",
    "logo.png",
    "logo-512.png",
    "favicon.png",
    "favicon.ico",
    "favicon-32x32.png",
    "favicon-16x16.png",
    "apple-touch-icon.png"
)

$localDir = "d:\MY project\cricket_score"
$remoteDir = "/cricketboard.site.je/htdocs"

Write-Host "Starting deployment to https://cricketboard.site.je ($remoteDir)..." -ForegroundColor Yellow

foreach ($fileName in $filesToDeploy) {
    $src = Join-Path $localDir $fileName
    if (Test-Path $src) {
        $dest = "$remoteDir/$fileName"
        Upload-FileToFtp -localPath $src -remotePath $dest
    } else {
        Write-Host "Warning: $src not found locally" -ForegroundColor Yellow
    }
}

Write-Host "`n Deployment to https://cricketboard.site.je complete!" -ForegroundColor Green
