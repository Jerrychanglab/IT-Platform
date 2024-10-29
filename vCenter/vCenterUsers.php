<?php
session_start();

// 防止页面被缓存
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1
header("Pragma: no-cache"); // HTTP 1.0
header("Expires: 0"); // Proxies

// 3600 秒登出
$timeout_duration = 3600;

// 檢查是否有上次活動時間記錄
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $timeout_duration) {
    // 如果距離上次活動超過30分鐘，則清空會話並重定向到登入頁面
    session_unset();
    session_destroy();
    header('Location: /Login/Login.php');
    exit;
}

// 更新上次活動時間
$_SESSION['LAST_ACTIVITY'] = time();

// 檢查用戶是否已經登入
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: /Login/Login.php');
    exit;
}

// 指定要自動加載的目錄
$json_dir = '/var/www/html/vCenter/list';
$data = [];

// 檢查目錄是否存在並可讀取
if (!is_dir($json_dir) || !is_readable($json_dir)) {
    die("無法讀取目錄：$json_dir");
}

// 瀏覽目錄中的所有 JSON 檔案
$json_files = scandir($json_dir);
foreach ($json_files as $json_file) {
    if (pathinfo($json_file, PATHINFO_EXTENSION) === 'json') {
        $filePath = "$json_dir/$json_file";
        $jsonData = trim(file_get_contents($filePath));

        // 確保 JSON 檔案內容不為空
        if ($jsonData === '') {
            continue; // 跳過空的檔案
        }

        // 去除非 JSON 部分
        $jsonData = preg_replace('/^[^{\[]*/', '', $jsonData);
        $fileData = json_decode($jsonData, true);

        // 檢查 JSON 是否解析成功，且檔案資料非空
        if (json_last_error() === JSON_ERROR_NONE && !empty($fileData)) {
            $data = array_merge($data, $fileData);
        } else {
            // echo "無法解析 JSON 檔案：$filePath - 錯誤訊息：" . json_last_error_msg() . "\n";
        }
    }
}

if (empty($data)) {
    die('無法解析任何 JSON 檔案');
}

// 分組 vCenter 和 Category
$vcenters = [];
$allCategories = [];
foreach ($data as $vm) {
    $vcenter = $vm['vcenter'] ?? '未指定';
    $category = $vm['category'] ?? '未分類';

    $vcenters[$vcenter]['vms'][] = $vm;
    $vcenters[$vcenter]['categories'][$category][] = $vm;

    // 收集所有 vCenter 的類別
    $allCategories[$category][] = $vm;
}
?>

<!DOCTYPE html>
<html lang="zh">

<head>
    <meta charset="UTF-8">
    <title>虛擬機清單</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .container {
            width: auto;
            min-width: 850px;
            background-color: white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            border-radius: 8px;
            margin: 0 auto;
        }

        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 20px;
        }

        .button-container {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        button {
            padding: 10px 15px;
            background-color: #007B8F;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 5px;
            transition: background-color 0.3s;
            margin: 5px;
        }

        button:hover {
            background-color: #005f6b;
        }

        .statistics-container {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            margin-top: 20px;
            padding: 15px;
            background-color: #e0f7fa;
            border-radius: 8px;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.1);
            flex-wrap: wrap;
        }

        .stat-item {
            font-size: 18px;
            color: #007B8F;
            text-align: center;
            flex: 1;
            min-width: 150px;
        }

        .stat-item p {
            margin: 0;
        }

        .stat-number {
            font-size: 24px;
            font-weight: bold;
        }

        .category-content,
        .search-results {
            display: none;
            margin-top: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            padding: 10px;
            text-align: center;
            border: 1px solid #ddd;
        }

        th {
            background-color: #007B8F;
            color: white;
        }

        td {
            background-color: #f9f9f9;
        }

        #search-input {
            width: 80%;
            padding: 10px;
            margin: 0 auto 20px auto;
            border-radius: 5px;
            border: 2px solid #9D9D9D;
            background-color: #FFFFFF;
            box-shadow: 0 0 8px rgba(0, 123, 143, 0.2);
            font-size: 16px;
            color: #333;
            display: block;
            transition: box-shadow 0.3s, border-color 0.3s;
        }

        #search-input:focus {
            outline: none;
            border-color: #005f6b;
            box-shadow: 0 0 12px rgba(0, 95, 107, 0.4);
        }

        .highlight {
            color: #930000;
            font-weight: bold;
        }

        .second-foor {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 10px;
        }

        .vcenter-button {
            padding: 10px 15px;
            background-color: #D26900;
            /* 上方 vCenter 按鈕顏色 */
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 5px;
            transition: background-color 0.3s;
            margin: 5px;
        }

        .vcenter-button:hover {
            background-color: #9F5000;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>虛擬機清單</h1>

        <!-- 搜尋框 -->
        <input type="text" id="search-input" placeholder="搜尋 vCenter、ESXi IP、序號、類別、名稱、IP 或 MAC">

        <!-- 搜尋結果顯示區域 -->
        <div id="search-results" class="search-results">
            <h2>搜尋結果</h2>
            <table>
                <thead>
                    <tr>
                        <th>vCenter</th>
                        <th>ESXi IP</th>
                        <th>序號</th>
                        <th>類別</th>
                        <th>名稱</th>
                        <th>CPU</th>
                        <th>記憶體</th>
                        <th>設備 IP & MAC</th>
                    </tr>
                </thead>
                <tbody id="search-results-body">
                </tbody>
            </table>
        </div>

        <!-- 框架 -->
        <div class="second-foor">
            <!-- 顯示 vCenter 分類按鈕 -->
            <div class="button-container">
                <button class="vcenter-button" onclick="showVcenter('ALL')">ALL</button>
                <?php foreach ($vcenters as $vcenter => $info) : ?>
                    <button class="vcenter-button" onclick="showVcenter('<?php echo htmlspecialchars($vcenter); ?>')"><?php echo htmlspecialchars($vcenter); ?></button>
                <?php endforeach; ?>
            </div>

            <!-- 統計顯示區 -->
            <div id="vcenter-statistics" class="statistics-container"></div>

            <!-- 顯示 Category 分類按鈕 -->
            <div id="category-buttons"></div>

            <!-- 顯示清單 -->
            <div id="category-content-area"></div>
        </div>

        <script>
            const data = <?php echo json_encode($data); ?>;
            const vcenterData = <?php echo json_encode($vcenters); ?>;
            const allCategories = <?php echo json_encode($allCategories); ?>;

            document.addEventListener('DOMContentLoaded', function() {
                showVcenter('ALL');
            });

            document.getElementById('search-input').addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const resultsBody = document.getElementById('search-results-body');
                const resultsContainer = document.getElementById('search-results');
                resultsBody.innerHTML = '';

                if (searchTerm) {
                    let found = false;
                    data.forEach(vm => {
                        const matches = [
                            vm.vcenter,
                            vm.esxi_ip,
                            vm.machineSerial,
                            vm.category,
                            vm.vm_name,
                            ...vm.network_info.map(info => info.ip_address),
                            ...vm.network_info.map(info => info.mac_address)
                        ].filter(field => field !== null && field !== undefined); // 過濾掉 null 和 undefined 值

                        const exactMatches = matches.some(field => {
                            const fieldStr = typeof field === 'number' ? field.toString() : field;
                            // 完整收尋
                            return typeof fieldStr === 'string' && fieldStr.toLowerCase() === searchTerm;
                            // 模糊收尋
                            //return typeof fieldStr === 'string' && fieldStr.toLowerCase().includes(searchTerm);
                        });

                        if (exactMatches) {
                            found = true;
                            const row = document.createElement('tr');
                            row.innerHTML = `
                    <td>${highlightMatch(vm.vcenter, searchTerm)}</td>
                    <td>${highlightMatch(vm.esxi_ip, searchTerm)}</td>
                    <td>${highlightMatch(vm.machineSerial, searchTerm)}</td>
                    <td>${highlightMatch(vm.category || '未分類', searchTerm)}</td>
                    <td>${highlightMatch(vm.vm_name, searchTerm)}</td>
                    <td>${highlightMatch(vm.cpu.toString(), searchTerm)}</td>
                    <td>${highlightMatch(vm.ram.toString(), searchTerm)}</td>
                    <td>${vm.network_info.map(info => `${highlightMatch(info.ip_address, searchTerm)} ${highlightMatch(info.mac_address, searchTerm)}`).join('<br>')}</td>
                `;
                            resultsBody.appendChild(row);
                        }
                    });
                    resultsContainer.style.display = found ? 'block' : 'none';
                } else {
                    resultsContainer.style.display = 'none';
                }
            });

            function highlightMatch(text, searchTerm) {
                text = text != null ? text.toString() : ''; // 確保 text 為字串
                const regex = new RegExp(`(${searchTerm})`, 'gi');
                return text.replace(regex, `<span class="highlight">$1</span>`);
            }

            function showVcenter(vcenter) {
                let vms = [];
                let categories = {};

                if (vcenter === 'ALL') {
                    vms = data;
                    categories = allCategories;
                } else {
                    vms = vcenterData[vcenter].vms || [];
                    categories = vcenterData[vcenter].categories || {};
                }

                const vmCount = vms.length;
                const totalCpu = vms.reduce((sum, vm) => sum + parseInt(vm.cpu), 0);
                const totalRam = vms.reduce((sum, vm) => sum + parseInt(vm.ram), 0);

                document.getElementById('vcenter-statistics').innerHTML = `
        <div class="stat-item">
            <p>虛擬機台數</p>
            <p class="stat-number">${vmCount} 台</p>
        </div>
        <div class="stat-item">
            <p>CPU 總數</p>
            <p class="stat-number">${totalCpu}</p>
        </div>
        <div class="stat-item">
            <p>RAM 總數</p>
            <p class="stat-number">${totalRam} GB</p>
        </div>
    `;

                const categoryButtons = Object.keys(categories).map(category => {
                    const categoryVmCount = categories[category].length;
                    return `<button class="category-button" onclick="showCategory('${vcenter}', '${category}')">
                    ${category} (${categoryVmCount})
                </button>`;
                }).join('');

                document.getElementById('category-buttons').innerHTML = categoryButtons;
                document.getElementById('category-content-area').innerHTML = '';
            }


            function showCategory(vcenter, category) {
                const vms = (vcenter === 'ALL' ? allCategories[category] : vcenterData[vcenter].categories[category]) || [];

                const tableRows = vms.map(vm => `
                <tr>
                    <td>${vm.vcenter}</td>
                    <td>${vm.esxi_ip}</td>
                    <td>${vm.machineSerial}</td>
                    <td>${vm.category || '未分類'}</td>
                    <td>${vm.vm_name}</td>
                    <td>${vm.cpu}</td>
                    <td>${vm.ram}</td>
                    <td>${vm.network_info.map(info => `${info.ip_address} ${info.mac_address}`).join('<br>')}</td>
                </tr>
            `).join('');

                document.getElementById('category-content-area').innerHTML = `
                <h2>${category}</h2>
                <table>
                    <thead>
                        <tr>
                            <th>vCenter</th>
                            <th>ESXi IP</th>
                            <th>序號</th>
                            <th>類別</th>
                            <th>名稱</th>
                            <th>CPU</th>
                            <th>記憶體</th>
                            <th>設備 IP & MAC</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${tableRows}
                    </tbody>
                </table>
            `;
            }
        </script>
</body>

</html>
