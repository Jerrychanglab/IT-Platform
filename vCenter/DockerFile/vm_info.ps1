FROM mcr.microsoft.com/powershell:7.1.4-ubuntu-20.04

RUN apt-get update && \
    apt-get install -y apache2 curl supervisor vim tzdata &&\
    rm -rf /var/lib/apt/lists/*

RUN pwsh -c "Install-Module -Name VMware.PowerCLI -AllowClobber -Force"

ENV TZ="Asia/Taipei"

WORKDIR /workspace

COPY vm_info.ps1 /workspace

ENTRYPOINT ["pwsh"]
[root@master-1 vcenter_list]# cat vm_info.ps1
Get-Module VMware.* -ListAvailable > $null
Set-PowerCLIConfiguration -InvalidCertificateAction Ignore -Confirm:$false > $null
Connect-VIServer -Server $env:vCenterIP -User $env:UserName -Password $env:Password > $null

# 定義要保存的數據列表
$dataList = @()

# 獲取 vCenter 內所有虛擬機
Get-VM | ForEach-Object {
    $vm = $_
    $esxiHost = $vm.VMHost

    # 組合 IP 和 MAC 位址
    $networkInfo = @()
    $networkAdapters = $vm.ExtensionData.Guest.Net
    foreach ($adapter in $networkAdapters) {
        $networkInfo += [PSCustomObject]@{
            ip_address  = ($adapter.IpAddress | Where-Object { $_ -match '^\d{1,3}(\.\d{1,3}){3}$' }) -join ", "
            mac_address = $adapter.MacAddress
        }
    }

    # 收集數據，根據虛擬機屬性填充數據
    $data = [PSCustomObject]@{
        vcenter       = $env:vCenterIP
        esxi_ip       = ($esxiHost | Get-VMHostNetworkAdapter | Where-Object { $_.ManagementTrafficEnabled -eq $true } | Select-Object -ExpandProperty IP)
        machineSerial = $vm.ExtensionData.Config.Uuid
        category      = $vm.Guest.OSFullName
        vm_name       = $vm.Name
        cpu           = "$($vm.NumCpu)"
        ram           = "$([int]($vm.MemoryMB / 1024))"
        network_info  = $networkInfo
    }
    
    # 添加數據到數據列表
    $dataList += $data
}

# 將數據列表轉為 JSON 格式並輸出到控制台
$jsonOutput = $dataList | ConvertTo-Json -Depth 5
Write-Output $jsonOutput

# 完成後斷開連線
Disconnect-VIServer -Confirm:$false
