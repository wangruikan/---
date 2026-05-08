$webClient = New-Object System.Net.WebClient
$webClient.Credentials = New-Object System.Net.NetworkCredential("upload", "upload")
try {
    $webClient.UploadFile("ftp://8.134.75.171/home/wwwroot/default/re_li_zi_yuan/src/views/ProcessManagement/index.vue", "E:\project\re_li_zi_yuan\re_li_zi_yuan(1)\re_li_zi_yuan\src\views\ProcessManagement\index.vue")
    Write-Host "Upload successful"
} catch {
    Write-Host ("Error: " + $_.Exception.Message)
}