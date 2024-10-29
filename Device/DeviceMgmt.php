<?php
session_start();

// 防止页面被缓存
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1
header("Pragma: no-cache"); // HTTP 1.0
header("Expires: 0"); // Proxies

// 3600秒登出
$timeout_duration = 3600;

// 檢查是否有上次活動時間記錄
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $timeout_duration) {
    // 如果距離上次活動超過30分鐘，則清空會話並重定向到登入頁面
    session_unset();
    session_destroy();
    header('Location: /');
    exit;
}

// 更新上次活動時間
$_SESSION['LAST_ACTIVITY'] = time();

// 檢查用戶是否已經登入
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: /Login/Login.php');
    exit;
}


// 定義伺服器和類型文件
$servers_file = 'DeviceInfo.php';
$categories_file = 'Categories.php';  // 設備的總類文件
$models_file = 'Models.php';  // 設備型號文件（需綁定設備的總類）

$servers = include($servers_file);
$categories = include($categories_file);
$models = include($models_file);

if (!is_array($categories)) {
    $categories = [];
}

if (!is_array($models)) {
    $models = [];
}

if (!is_array($servers)) {
    $servers = [];
}

// 驗證名稱是否符合規則
function validate_name($name)
{
    return preg_match('/^[a-zA-Z0-9_-]{2,20}$/', $name);
}

// 驗證序號是否重複
function is_serial_duplicate($serial, $servers)
{
    foreach ($servers as $server) {
        if ($server['machineSerial'] === $serial) {
            return true; // 序號重複
        }
    }
    return false; // 序號不重複
}

// 處理新增、編輯、刪除設備總類和設備型號的請求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_server') {
        $category = $_POST['category'] ?? '';
        $model = $_POST['model'] ?? '';
        $machineSerial = $_POST['machineSerial'] ?? '';

        if (!validate_name($machineSerial)) {
            echo json_encode(['status' => 'error', 'message' => '機器序號無效']);
            exit;
        }

        // 檢查序號是否重複
        if (is_serial_duplicate($machineSerial, $servers)) {
            echo json_encode(['status' => 'error', 'message' => '該序號已存在']);
            exit;
        }

        $servers[] = [
            'category' => $category,
            'model' => $model,
            'machineSerial' => $machineSerial,
            'status' => ''
        ];

        file_put_contents($servers_file, "<?php\nreturn " . var_export($servers, true) . ";\n");
        echo json_encode(['status' => 'success', 'message' => '設備新增成功']);
        exit;
    }


    // 處理設備的總類新增
    if ($action === 'add_category') {
        $new_category = $_POST['category'] ?? '';

        if (!validate_name($new_category)) {
            echo json_encode(['status' => 'error', 'message' => '設備的總類名稱無效']);
            exit;
        }

        if (in_array($new_category, $categories)) {
            echo json_encode(['status' => 'error', 'message' => '設備的總類已存在']);
            exit;
        }

        $categories[] = $new_category;
        file_put_contents($categories_file, "<?php\nreturn " . var_export($categories, true) . ";\n");
        echo json_encode(['status' => 'success', 'message' => '設備的總類新增成功']);
        exit;
    }

    // 處理設備型號新增，需綁定設備總類
    if ($action === 'add_model') {
        $new_model = $_POST['model'] ?? '';
        $category = $_POST['category'] ?? '';

        // 檢查總類是否選擇
        if (empty($category)) {
            echo json_encode(['status' => 'error', 'message' => '請選擇設備的總類']);
            exit;
        }

        if (!validate_name($new_model)) {
            echo json_encode(['status' => 'error', 'message' => '設備型號名稱無效']);
            exit;
        }

        if (!isset($models[$category])) {
            $models[$category] = [];
        }

        if (in_array($new_model, $models[$category])) {
            echo json_encode(['status' => 'error', 'message' => '設備型號已存在於該總類']);
            exit;
        }

        $models[$category][] = $new_model;
        file_put_contents($models_file, "<?php\nreturn " . var_export($models, true) . ";\n");
        echo json_encode(['status' => 'success', 'message' => '設備型號新增成功']);
        exit;
    }


    // 處理設備的總類移除
    if ($action === 'delete_category') {
        $category = $_POST['category'] ?? '';

        if (!in_array($category, $categories)) {
            echo json_encode(['status' => 'error', 'message' => '設備的總類不存在']);
            exit;
        }

        // 檢查該總類是否還有型號
        if (isset($models[$category]) && count($models[$category]) > 0) {
            echo json_encode(['status' => 'error', 'message' => '無法刪除該總類，因為該總類下還有型號存在']);
            exit;
        }

        // 檢查是否有設備使用該設備總類
        foreach ($servers as $server) {
            if ($server['category'] === $category) {
                echo json_encode(['status' => 'error', 'message' => '無法刪除該總類，因為它被使用中']);
                exit;
            }
        }

        // 移除該設備總類
        unset($categories[array_search($category, $categories)]);
        $categories = array_values($categories); // 重新索引
        file_put_contents($categories_file, "<?php\nreturn " . var_export($categories, true) . ";\n");

        // 也移除該設備總類下的所有型號
        unset($models[$category]);
        file_put_contents($models_file, "<?php\nreturn " . var_export($models, true) . ";\n");

        echo json_encode(['status' => 'success', 'message' => '設備的總類及其所有型號已移除']);
        exit;
    }


    // 處理伺服器移除請求
    if ($action === 'delete_server') {
        $machineSerial = $_POST['machineSerial'] ?? '';

        foreach ($servers as $key => $server) {
            if ($server['machineSerial'] === $machineSerial) {
                unset($servers[$key]);
                $servers = array_values($servers); // 重新索引
                file_put_contents($servers_file, "<?php\nreturn " . var_export($servers, true) . ";\n");
                echo json_encode(['status' => 'success', 'message' => '伺服器已移除']);
                exit;
            }
        }

        echo json_encode(['status' => 'error', 'message' => '找不到該伺服器']);
        exit;
    }


    // 處理設備型號移除
    if ($action === 'delete_model') {
        $category = $_POST['category'] ?? '';
        $model = $_POST['model'] ?? '';

        if (!isset($models[$category])) {
            echo json_encode(['status' => 'error', 'message' => '設備的總類不存在']);
            exit;
        }

        if (!in_array($model, $models[$category])) {
            echo json_encode(['status' => 'error', 'message' => '設備型號不存在']);
            exit;
        }

        // 檢查是否有設備使用該型號
        foreach ($servers as $server) {
            if ($server['category'] === $category && $server['model'] === $model) {
                echo json_encode(['status' => 'error', 'message' => '無法刪除該型號，因為它被使用中']);
                exit;
            }
        }

        // 移除該設備型號
        unset($models[$category][array_search($model, $models[$category])]);
        $models[$category] = array_values($models[$category]); // 重新索引
        file_put_contents($models_file, "<?php\nreturn " . var_export($models, true) . ";\n");

        echo json_encode(['status' => 'success', 'message' => '設備型號已移除']);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="zh-Hant">

<head>
    <meta charset="UTF-8">
    <title>設備管理系統</title>
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
            width: 100%;
            max-width: 700px;
            background-color: white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            border-radius: 8px;
        }

        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
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

        button {
            padding: 10px 15px;
            margin: 5px;
            background-color: #007B8F;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 5px;
        }

        button:hover {
            background-color: #005f6b;
        }

        .button-container {
            display: flex;
            justify-content: center;
        }

        .all-devices-btn {
            background-color: #6c757d;
            color: white;
            /* 設置白色文字 */
            font-weight: bold;
        }

        .all-devices-btn:hover {
            background-color: #4F4F4F;
            /* 當滑鼠懸停時設置深紅色背景 */
            font-weight: bold;
        }


        .btn-add,
        .btn-group-manage {
            padding: 10px 15px;
            background-color: #007B8F;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 5px;
        }

        .btn-add:hover,
        .btn-group-manage:hover {
            background-color: #005f6b;
        }

        /* 模態框樣式 */
        .modal {
            display: none;
            position: fixed;
            align-items: center;
            justify-content: center;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.4);
            overflow-y: auto;
            /* 當模態框內的內容超過視口高度時，啟用滾動 */
        }

        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: auto;
            max-width: 35%;
            min-width: 200px;
            border-radius: 8px;
            text-align: center;
            max-height: 90vh;
            /* 設置模態框的最大高度 */
            overflow-y: auto;
            /* 當內容超過最大高度時啟用滾動條 */
        }


        .modal-url {
            background-color: #f9f9f9;
            border: 1px solid #ccc;
            padding: 10px;
            word-break: break-all;
            font-size: 16px;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }

        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }

        .modal-buttons {
            margin-top: 20px;
        }

        .modal-buttons button {
            padding: 10px 20px;
            margin: 0 10px;
            border-radius: 5px;
            cursor: pointer;
        }

        .modal-buttons .confirm {
            background-color: #007B8F;
            color: white;
        }

        .modal-buttons .cancel {
            background-color: #ccc;
        }

        .modal-buttons .confirm:hover {
            background-color: #005f6b;
        }

        .modal-buttons .cancel:hover {
            background-color: #bbb;
        }

        ul {
            list-style-type: none;
            padding-left: 0;
        }

        .group-list-container {
            max-height: 300px;
            max-width: 75%;
            background-color: #EBEBEB;
            overflow-y: scroll;
            padding-left: 0;
            list-style-type: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            margin: 0 auto;
            border-radius: 5px;
        }

        .group-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }

        .group-item ul {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            list-style: none;
            padding-left: 0;
            margin-bottom: 0;
        }

        .group-item li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            padding: 5px 0;
        }

        .group-item:hover {
            background-color: #f0f8ff;
            box-shadow: 0 0 8px rgba(0, 0, 0, 0.2);
            border-color: #007B8F;
        }

        .group-item button {
            background-color: #007B8F;
            border-radius: 5px;
            color: white;
            padding: 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .group-item button:hover {
            background-color: #005f6b;
        }

        .group-list-container::-webkit-scrollbar {
            width: 10px;
        }

        .group-list-container::-webkit-scrollbar-thumb {
            background-color: #007B8F;
            border-radius: 10px;
        }

        .flex-container {
            display: flex;
            justify-content: space-between;
            gap: 20px;
        }

        .form-item {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .box {
            margin-bottom: 15px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.1);
            background-color: #f9f9f9;
        }

        .box:hover {
            background-color: #f0f8ff;
            box-shadow: 0 0 8px rgba(0, 0, 0, 0.2);
            border-color: #007B8F;
        }

        select {
            padding: 5px;
            font-size: 16px;
            border-radius: 5px;
        }

        select option {
            padding: 10px;
            font-size: 16px;
        }

        #resetLink {
            white-space: nowrap;
            overflow-x: auto;
            display: block;
            max-width: 100%;
        }

        #resetLink:hover {
            background-color: #f0f8ff;
            box-shadow: 0 0 8px rgba(0, 0, 0, 0.2);
            border-color: #007B8F;
        }

        #groupName:hover {
            background-color: #f0f8ff;
            box-shadow: 0 0 8px rgba(0, 0, 0, 0.2);
            border-color: #007B8F;
            border-radius: 5px;
        }

        .machine-types-list {
            display: flex;
            flex-direction: column;
            /* 保持卡片垂直堆疊 */
            align-items: center;
            /* 將卡片水平置中 */
            gap: 15px;
            max-height: 300px;
            /* 設置最大高度 */
            overflow-y: auto;
            /* 啟用垂直滾動 */
            background-color: #f9f9f9;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #ddd;
        }


        .machine-types-list::-webkit-scrollbar {
            width: 10px;
        }

        .machine-types-list::-webkit-scrollbar-thumb {
            background-color: #007B8F;
            border-radius: 10px;
        }

        .category-card {
            background-color: #f0f8ff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            width: 100%;
            /* 設置卡片寬度為父容器的100% */
            max-width: 300px;
            /* 設置卡片最大寬度 */
            box-shadow: 0 0 8px rgba(0, 0, 0, 0.1);
        }


        .category-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .category-header h4 {
            margin: 0;
            font-size: 1.8em;
            color: #007B8F;
        }

        .delete-category-btn {
            padding: 6px 12px;
            background-color: #FF5252;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 4px;
            font-size: 0.9em;
        }

        .delete-category-btn:hover {
            background-color: #FF1744;
        }

        .model-list {
            list-style-type: none;
            padding: 0;
            margin: 0;
        }

        .model-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            margin-bottom: 15px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.1);
            background-color: #f9f9f9;
        }

        .model-item:hover {
            background-color: #f0f8ff;
            box-shadow: 0 0 8px rgba(0, 0, 0, 0.2);
            border-color: #007B8F;
            border-radius: 4px;
        }


        .model-item span {
            font-size: 1em;
        }

        .delete-model-btn {
            padding: 10px 10px;
            background-color: #007B8F;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 4px;
            font-size: 0.85em;
        }

        .delete-model-btn:hover {
            background-color: #005f6b;
        }

        #categoryName,
        #modelName,
        #machineSerial,
        #editMachineSerial {
            font-size: 20px;
            /* 調整字體大小為 16px */
        }

        #editMachineSerial {
            width: 150px;
            /* 調整輸入框的寬度 */
        }

        .boxmgmtdiv {
            border: 2px solid #D0D0D0;
            /* 紅色邊框，2px粗，實線 */
            border-radius: 5px;
            /* 邊框圓角 */
            padding: 10px;
            /* 內部填充 */
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            /* 增加陰影使框架更具立體感 */
            background-color: #f9f9f9;
            /* 淡色背景，與原來樣式相容 */

        }
    </style>
</head>

<body>
    <div class="container">
        <h1>設備管理系統</h1>

        <!-- 動態生成設備類型的按鈕 -->
        <div class="button-container">
            <button class="all-devices-btn" onclick="filterByType('all')">顯示所有設備</button>
            <?php foreach ($categories as $category) : ?>
                <?php if (isset($models[$category]) && count($models[$category]) > 0) : ?>
                    <button class="all-devices-btn" onclick="filterByType('<?php echo $category; ?>')"><?php echo $category; ?></button>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <div class="boxmgmtdiv">
            <div class="button-container">
                <button class="btn-add" onclick="openAddModal()">新增設備</button>
                <button class="btn-manage-types" onclick="openManageTypesModal()">管理設備類型</button>
            </div>

            <table id="serverTable">
                <tr>
                    <th>設備類型</th>
                    <th>設備型號</th>
                    <th>設備序號</th>
                    <th>操作</th>
                </tr>
                <?php foreach ($servers as $server) : ?>
                    <tr class="device-row" data-category="<?php echo $server['category']; ?>">
                        <td><?php echo $server['category']; ?></td>
                        <td><?php echo $server['model']; ?></td>
                        <td><?php echo $server['machineSerial']; ?></td>
                        <td>
                            <button onclick="deleteServer('<?php echo $server['machineSerial']; ?>')">移除</button>
                        </td>
                    </tr>
                <?php endforeach; ?>

            </table>
        </div>
        <!-- 新增設備模態框 -->
        <div id="addModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeAddModal()">&times;</span>
                <h2>新增設備</h2>
                <form id="addForm">
                    <div class="box flex-container">
                        <div class="form-item">
                            <label for="category">設備類型:</label>
                            <select id="category" name="category" required onchange="updateModels('category', 'model')">
                                <option value="">選擇</option>
                                <?php foreach ($categories as $category) : ?>
                                    <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-item">
                            <label for="model">設備型號:</label>
                            <select id="model" name="model" required>
                                <option value="">選擇</option>
                            </select>
                        </div>

                        <div class="form-item">
                            <label for="machineSerial">設備序號:</label>
                            <input type="text" id="machineSerial" name="machineSerial" placeholder="填寫" required>
                        </div>
                    </div>
                    <div class="modal-buttons">
                        <button type="button" class="confirm" onclick="submitAddForm()">確定</button>
                        <button type="button" class="cancel" onclick="resetForm(); closeAddModal();">取消</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 管理設備類型模態框 -->
        <div id="manageTypesModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeManageTypesModal()">&times;</span>
                <h2>管理設備類型</h2>

                <!-- 新增設備總類 -->
                <div class="box">
                    <form id="addCategoryForm">
                        <div class="form-item">
                            <label for="categoryName">新增設備的總類:</label>
                            <input type="text" id="categoryName" name="category" required>
                        </div>
                        <div class="modal-buttons">
                            <button type="button" class="confirm" onclick="submitAddCategory()">新增總類</button>
                            <button type="button" class="cancel" onclick="closeManageTypesModal()">取消</button>
                        </div>
                    </form>
                </div>

                <!-- 選擇設備總類 + 新增設備型號 -->
                <div class="box">
                    <form id="addModelForm" class="flex-container">
                        <div class="form-item">
                            <label for="categoryForModel">設備的總類:</label>
                            <select id="categoryForModel" name="category" required>
                                <option value="">選擇</option>
                                <?php foreach ($categories as $category) : ?>
                                    <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-item">
                            <label for="modelName">新增設備型號:</label>
                            <input type="text" id="modelName" name="model" required>
                        </div>
                        <div class="modal-buttons">
                            <button type="button" class="confirm" onclick="submitAddModel()">新增型號</button>
                            <button type="button" class="cancel" onclick="closeManageTypesModal()">取消</button>
                        </div>
                    </form>
                </div>

                <!-- 現有設備類型列表 -->
                <h3>現有設備類型</h3>
                <div class="machine-types-list">
                    <?php foreach ($models as $category => $categoryModels) : ?>
                        <div class="category-card">
                            <div class="category-header">
                                <h4><?php echo htmlspecialchars($category); ?></h4>
                                <button class="delete-category-btn" onclick="deleteCategory('<?php echo htmlspecialchars($category); ?>')">刪除總類</button>
                            </div>
                            <ul class="model-list">
                                <?php foreach ($categoryModels as $model) : ?>
                                    <li class="model-item">
                                        <span><?php echo htmlspecialchars($model); ?></span>
                                        <button class="delete-model-btn" onclick="deleteModel('<?php echo htmlspecialchars($category); ?>', '<?php echo htmlspecialchars($model); ?>')">刪除型號</button>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- 管理設備類型模態框 -->
                <div id="manageTypesModal" class="modal">
                    <div class="modal-content">
                        <span class="close" onclick="closeManageTypesModal()">&times;</span>
                        <h2>管理設備類型</h2>

                        <!-- 新增設備總類 -->
                        <div class="box">
                            <form id="addCategoryForm">
                                <div class="form-item">
                                    <label for="categoryName">新增設備的總類:</label>
                                    <input type="text" id="categoryName" name="category" required>
                                </div>
                                <div class="modal-buttons">
                                    <button type="button" class="confirm" onclick="submitAddCategory()">新增總類</button>
                                    <button type="button" class="cancel" onclick="closeManageTypesModal()">取消</button>
                                </div>
                            </form>
                        </div>

                        <!-- 選擇設備總類 + 新增設備型號 -->
                        <div class="box">
                            <form id="addModelForm" class="flex-container">
                                <div class="form-item">
                                    <label for="categoryForModel">選擇設備的總類:</label>
                                    <select id="categoryForModel" name="category" required>
                                        <option value="">選擇</option>
                                        <?php foreach ($categories as $category) : ?>
                                            <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-item">
                                    <label for="modelName">新增設備型號:</label>
                                    <input type="text" id="modelName" name="model" required>
                                </div>
                                <div class="modal-buttons">
                                    <button type="button" class="confirm" onclick="submitAddModel()">新增型號</button>
                                    <button type="button" class="cancel" onclick="closeManageTypesModal()">取消</button>
                                </div>
                            </form>
                        </div>

                        <!-- 現有設備類型列表 -->
                        <h3>現有設備類型</h3>
                        <div class="machine-types-list">
                            <?php foreach ($models as $category => $categoryModels) : ?>
                                <div class="box group-item">
                                    <h4><?php echo htmlspecialchars($category); ?></h4>
                                    <ul>
                                        <?php foreach ($categoryModels as $model) : ?>
                                            <li>
                                                <span><?php echo htmlspecialchars($category); ?> - <?php echo htmlspecialchars($model); ?></span>
                                                <button onclick="deleteModel('<?php echo htmlspecialchars($category); ?>', '<?php echo htmlspecialchars($model); ?>')">刪除型號</button>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <button onclick="deleteCategory('<?php echo htmlspecialchars($category); ?>')">刪除總類</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <script>
                    function closeAddModal() {
                        document.getElementById('addModal').style.display = 'none';
                    }

                    // 更新設備型號下拉選單根據選擇的設備總類
                    function updateModels(categoryId, modelId) {
                        const category = document.getElementById(categoryId).value;
                        const modelsSelect = document.getElementById(modelId);

                        modelsSelect.innerHTML = '<option value="">請先選擇設備的總類</option>'; // 預設提示

                        const models = <?php echo json_encode($models); ?>;

                        if (models[category]) {
                            models[category].forEach(function(model) {
                                const option = document.createElement('option');
                                option.value = model;
                                option.textContent = model;
                                modelsSelect.appendChild(option);
                            });
                        }
                    }

                    // 確認按鈕綁定並執行
                    function openAddModal() {
                        console.log("Add modal opened");
                        document.getElementById('addModal').style.display = 'block';
                        resetForm();
                    }

                    function deleteServer(machineSerial) {
                        if (!confirm(`確定要刪除伺服器 "${machineSerial}" 嗎？`)) {
                            return;
                        }

                        console.log(`Sending delete request for machine serial: ${machineSerial}`);

                        const xhr = new XMLHttpRequest();
                        xhr.open('POST', '', true);
                        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                        xhr.onreadystatechange = function() {
                            if (xhr.readyState === 4 && xhr.status === 200) {
                                const response = JSON.parse(xhr.responseText);
                                if (response.status === 'success') {
                                    console.log('Server deleted successfully');
                                    location.reload();
                                } else {
                                    alert(response.message || '操作失敗，請重試！');
                                }
                            }
                        };

                        const data = 'action=delete_server&machineSerial=' + encodeURIComponent(machineSerial);
                        xhr.send(data);
                    }


                    // 編輯伺服器模態框控制
                    function openEditModal(category, model, machineSerial) {
                        document.getElementById('editCategory').value = category;
                        updateModels('editCategory', 'editModel');
                        setTimeout(function() {
                            document.getElementById('editModel').value = model;
                        }, 100); // 保證型號下拉選單已更新
                        document.getElementById('editMachineSerial').value = machineSerial;
                        document.getElementById('originalSerial').value = machineSerial;
                        document.getElementById('editModal').style.display = 'block';
                    }

                    // 移除伺服器
                    function deleteServer(machineSerial) {
                        if (!confirm(`確定要刪除伺服器 "${machineSerial}" 嗎？`)) {
                            return;
                        }

                        const xhr = new XMLHttpRequest();
                        xhr.open('POST', '', true);
                        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                        xhr.onreadystatechange = function() {
                            if (xhr.readyState === 4 && xhr.status === 200) {
                                const response = JSON.parse(xhr.responseText);
                                if (response.status === 'success') {
                                    location.reload();
                                } else {
                                    alert(response.message || '操作失敗，請重試！');
                                }
                            }
                        };

                        const data = 'action=delete_server&machineSerial=' + encodeURIComponent(machineSerial);
                        xhr.send(data);
                    }

                    function submitAddForm() {
                        const category = document.getElementById('category').value;
                        const model = document.getElementById('model').value;
                        const machineSerial = document.getElementById('machineSerial').value.trim();

                        if (!category || !model || !machineSerial) {
                            alert('所有欄位為必填');
                            return;
                        }

                        const xhr = new XMLHttpRequest();
                        xhr.open('POST', '', true);
                        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                        xhr.onreadystatechange = function() {
                            if (xhr.readyState === 4 && xhr.status === 200) {
                                const response = JSON.parse(xhr.responseText);
                                if (response.status === 'success') {
                                    location.reload(); // 刷新頁面以顯示新設備
                                } else {
                                    alert(response.message || '操作失敗，請重試！');
                                }
                            }
                        };

                        const data = 'action=add_server&category=' + encodeURIComponent(category) +
                            '&model=' + encodeURIComponent(model) +
                            '&machineSerial=' + encodeURIComponent(machineSerial);
                        xhr.send(data);
                    }

                    // 管理設備類型模態框控制
                    function openManageTypesModal() {
                        document.getElementById('manageTypesModal').style.display = 'flex';
                    }

                    function closeManageTypesModal() {
                        document.getElementById('manageTypesModal').style.display = 'none';
                    }

                    // 提交新增設備的總類表單
                    function submitAddCategory() {
                        const categoryName = document.getElementById('categoryName').value.trim();

                        if (!categoryName) {
                            alert('設備的總類名稱不可為空');
                            return;
                        }

                        const xhr = new XMLHttpRequest();
                        xhr.open('POST', '', true);
                        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                        xhr.onreadystatechange = function() {
                            if (xhr.readyState === 4 && xhr.status === 200) {
                                const response = JSON.parse(xhr.responseText);
                                if (response.status === 'success') {
                                    location.reload(); // 刷新頁面顯示新總類
                                } else {
                                    alert(response.message || '操作失敗，請重試！');
                                }
                            }
                        };

                        const data = 'action=add_category&category=' + encodeURIComponent(categoryName);
                        xhr.send(data);
                    }

                    // 提交新增設備型號表單
                    function submitAddModel() {
                        const category = document.getElementById('categoryForModel').value;
                        const modelName = document.getElementById('modelName').value.trim();

                        if (!category) {
                            alert('請選擇設備的總類');
                            return;
                        }

                        if (!modelName) {
                            alert('設備型號名稱不可為空');
                            return;
                        }

                        const xhr = new XMLHttpRequest();
                        xhr.open('POST', '', true);
                        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                        xhr.onreadystatechange = function() {
                            if (xhr.readyState === 4 && xhr.status === 200) {
                                const response = JSON.parse(xhr.responseText);
                                if (response.status === 'success') {
                                    location.reload(); // 刷新頁面顯示新型號
                                } else {
                                    alert(response.message || '操作失敗，請重試！');
                                }
                            }
                        };

                        const data = 'action=add_model&category=' + encodeURIComponent(category) + '&model=' + encodeURIComponent(modelName);
                        xhr.send(data);
                    }


                    // 移除設備的總類
                    function deleteCategory(category) {
                        if (!confirm(`確定要刪除設備的總類 "${category}" 及其所有型號嗎？`)) {
                            return;
                        }

                        const xhr = new XMLHttpRequest();
                        xhr.open('POST', '', true);
                        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                        xhr.onreadystatechange = function() {
                            if (xhr.readyState === 4 && xhr.status === 200) {
                                const response = JSON.parse(xhr.responseText);
                                if (response.status === 'success') {
                                    location.reload();
                                } else {
                                    alert(response.message || '操作失敗，請重試！');
                                }
                            }
                        };

                        const data = 'action=delete_category&category=' + encodeURIComponent(category);
                        xhr.send(data);
                    }

                    // 移除設備型號
                    function deleteModel(category, model) {
                        if (!confirm(`確定要刪除設備型號 "${model}" 嗎？`)) {
                            return;
                        }

                        const xhr = new XMLHttpRequest();
                        xhr.open('POST', '', true);
                        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                        xhr.onreadystatechange = function() {
                            if (xhr.readyState === 4 && xhr.status === 200) {
                                const response = JSON.parse(xhr.responseText);
                                if (response.status === 'success') {
                                    location.reload();
                                } else {
                                    alert(response.message || '操作失敗，請重試！');
                                }
                            }
                        };

                        const data = 'action=delete_model&category=' + encodeURIComponent(category) + '&model=' + encodeURIComponent(model);
                        xhr.send(data);
                    }

                    function resetForm() {
                        document.getElementById('addForm').reset();
                    }

                    // 過濾設備類型的函數
                    function filterByType(type) {
                        const rows = document.querySelectorAll('.device-row');
                        rows.forEach(row => {
                            if (type === 'all' || row.dataset.category === type) {
                                row.style.display = '';
                            } else {
                                row.style.display = 'none';
                            }
                        });
                    }

                    // AJAX 刪除伺服器
                    function deleteServer(serial) {
                        if (confirm(`確定要刪除設備序號為 "${serial}" 的設備嗎？`)) {
                            const xhr = new XMLHttpRequest();
                            xhr.open('POST', '', true);
                            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                            xhr.onreadystatechange = function() {
                                if (xhr.readyState === 4 && xhr.status === 200) {
                                    const response = JSON.parse(xhr.responseText);
                                    if (response.status === 'success') {
                                        alert(response.message);
                                        location.reload(); // 刷新頁面
                                    } else {
                                        alert(response.message);
                                    }
                                }
                            };
                            xhr.send('action=delete_server&machineSerial=' + encodeURIComponent(serial));
                        }
                    }
                </script>

</body>

</html>
