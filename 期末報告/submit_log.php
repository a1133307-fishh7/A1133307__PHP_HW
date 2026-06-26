<?php
session_start();

// 💡 引入機密設定檔 (抓取 PDO 資料庫物件 $pdo)
require_once 'config.php'; 

if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('請先登入才能填寫減碳日誌喔！'); window.location.href='login.php';</script>";
    exit;
}

// ==========================================
// 🕵️‍♂️ 偵測模式：判斷是「一般日誌」還是「挑戰任務」
// ==========================================
$is_challenge = false;
$challenge_title = "";

if (isset($_GET['task_id'])) {
    $task_id = (int)$_GET['task_id'];
    
    try {
        // 🔒 升級重點 1：使用 PDO 查詢任務名稱
        $stmt = $pdo->prepare("SELECT title FROM challenge_tasks WHERE id = :id");
        $stmt->execute([':id' => $task_id]);
        $task_data = $stmt->fetch();
        
        if ($task_data) {
            $is_challenge = true;
            $challenge_title = $task_data['title'];
        }
    } catch (PDOException $e) {
        die("撈取任務時發生錯誤：" . $e->getMessage());
    }
}

// ==========================================
// 處理表單送出邏輯
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_SESSION['user_id'];
    
    // 🔒 升級重點 2：PDO 不需要 mysqli_real_escape_string，直接接收即可！
    // 我們加上 trim() 去除前後不必要的空白
    $action_type = trim($_POST['action_type']);
    $description = trim($_POST['description']);
    
    $input_value = isset($_POST['input_value']) && $_POST['input_value'] !== '' ? (float)$_POST['input_value'] : 0;

    // 🧮 減碳計算機核心邏輯
    $co2_saved = 0;
    if ($action_type === '騎自行車/步行') {
        $co2_saved = $input_value * 0.15;
    } 
    $co2_saved = round($co2_saved, 2);

    // 處理多圖上傳邏輯
    $upload_dir = "uploads/";
    $uploaded_paths = [];
    
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    if (isset($_FILES['photos']['name']) && !empty($_FILES['photos']['name'][0])) {
        $total_files = count($_FILES['photos']['name']);
        for ($i = 0; $i < $total_files; $i++) {
            if ($_FILES['photos']['error'][$i] == 0) {
                $tmp_name = $_FILES['photos']['tmp_name'][$i];
                $new_filename = time() . "_" . basename($_FILES['photos']['name'][$i]);
                $target_file = $upload_dir . $new_filename;
                
                if (move_uploaded_file($tmp_name, $target_file)) {
                    $uploaded_paths[] = $target_file;
                }
            }
        }
    }

    if (count($uploaded_paths) > 0) {
        $final_photo_paths_string = implode(',', $uploaded_paths);

        try {
            // 🔒 升級重點 3：使用 PDO 安全寫入日誌紀錄
            $insert_stmt = $pdo->prepare("INSERT INTO task_records (user_id, action_type, description, co2_saved, photo_path) VALUES (:user_id, :action_type, :description, :co2_saved, :photo_path)");
            
            $is_success = $insert_stmt->execute([
                ':user_id' => $user_id,
                ':action_type' => $action_type,
                ':description' => $description,
                ':co2_saved' => $co2_saved,
                ':photo_path' => $final_photo_paths_string
            ]);

            if ($is_success) {
                if ($action_type === '騎自行車/步行') {
                    echo "<script>alert('🌍 日誌已成功送出！\\n\\n本次行動「預估」可減少 {$co2_saved} kg 的 CO2 排放。\\n請靜候管理員審核，通過後即可獲得碳幣獎勵喔！'); window.location.href='dashboard.php';</script>";
                } else {
                    echo "<script>alert('🌍 任務回報已成功送出！\\n\\n請靜候管理員審核，審核通過後系統將自動發放對應的碳幣與經驗值獎勵！'); window.location.href='dashboard.php';</script>";
                }
            } else {
                echo "<script>alert('資料庫寫入發生異常！');</script>";
            }
        } catch (PDOException $e) {
            die("系統發生錯誤：" . $e->getMessage());
        }

    } else {
        echo "<script>alert('圖片上傳發生錯誤，請確認檔案！');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title><?php echo $is_challenge ? "解鎖挑戰任務" : "填寫減碳日誌"; ?> - 綠色冒險家</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f9f4; padding: 40px; }
        .form-container { background: white; max-width: 500px; margin: auto; padding: 30px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        select, textarea, input[type="number"] { width: 100%; padding: 10px; margin: 10px 0 20px 0; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; }
        button.submit-btn { background-color: #2e8b57; color: white; border: none; padding: 12px 20px; border-radius: 5px; cursor: pointer; font-size: 16px; width: 100%; font-weight: bold; margin-top: 15px;}
        button.submit-btn:hover { background-color: #246b43; }
        
        /* 針對挑戰模式的按鈕設計 */
        button.challenge-btn { background-color: #f39c12; }
        button.challenge-btn:hover { background-color: #d35400; }
        
        .back-link { display: block; text-align: center; margin-top: 20px; color: #777; text-decoration: none; }
        .calc-box { background-color: #e8f5e9; padding: 15px; border-radius: 5px; margin-bottom: 20px; border-left: 5px solid #2e8b57; }
        
        /* 挑戰模式的提示框 */
        .challenge-box { background-color: #fff3cd; border-left: 5px solid #f39c12; }
        
        #dynamic_input_area { transition: all 0.3s ease; }

        .add-photo-btn {
            background-color: #f8f9fa; color: #2c3e50; border: 2px dashed #bdc3c7; 
            padding: 12px; border-radius: 5px; cursor: pointer; font-size: 15px; 
            width: 100%; text-align: center; font-weight: bold; transition: 0.2s;
        }
        .add-photo-btn:hover { background-color: #e2e6ea; border-color: #2e8b57; color: #2e8b57;}
        
        .photo-preview-container { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px; margin-bottom: 20px; }
        .photo-item { position: relative; width: 85px; height: 85px; border-radius: 8px; overflow: hidden; border: 1px solid #ddd; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .photo-item img { width: 100%; height: 100%; object-fit: cover; }
        .remove-btn { 
            position: absolute; top: 3px; right: 3px; background: rgba(231, 76, 60, 0.9); 
            color: white; border: none; border-radius: 50%; width: 22px; height: 22px; 
            font-size: 12px; cursor: pointer; display: flex; justify-content: center; 
            align-items: center; padding: 0; box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        .remove-btn:hover { background: #c0392b; }
    </style>
    <script>
        function updateInputLabel() {
            var actionTypeElement = document.getElementById("action_type");
            // 💡 防呆檢查：如果在挑戰模式下沒有這個下拉選單，就直接跳過
            if (!actionTypeElement) return; 
            
            var actionType = actionTypeElement.value;
            var dynamicArea = document.getElementById("dynamic_input_area");
            var inputField = document.getElementById("input_value");

            if (actionType === "騎自行車/步行") {
                dynamicArea.style.display = "block";
                inputField.required = true;
            } else {
                dynamicArea.style.display = "none";
                inputField.required = false;
                inputField.value = ""; 
            }
        }

        let selectedFiles = []; 

        function triggerFileInput() { document.getElementById('hidden_file_input').click(); }

        function handleFileSelect(event) {
            const files = event.target.files;
            for (let i = 0; i < files.length; i++) { selectedFiles.push(files[i]); }
            renderPreview();
            updateHiddenInput();
            event.target.value = ''; 
        }

        function removeFile(index) {
            selectedFiles.splice(index, 1); 
            renderPreview();
            updateHiddenInput();
        }

        function renderPreview() {
            const container = document.getElementById('preview_area');
            container.innerHTML = ''; 

            selectedFiles.forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const div = document.createElement('div');
                    div.className = 'photo-item';
                    div.innerHTML = `
                        <img src="${e.target.result}" alt="預覽圖">
                        <button type="button" class="remove-btn" onclick="removeFile(${index})" title="移除這張照片">✖</button>
                    `;
                    container.appendChild(div);
                }
                reader.readAsDataURL(file); 
            });
        }

        function updateHiddenInput() {
            const dataTransfer = new DataTransfer();
            selectedFiles.forEach(file => dataTransfer.items.add(file));
            document.getElementById('final_photos').files = dataTransfer.files;
        }

        function validateForm(event) {
            if (selectedFiles.length === 0) {
                alert('⚠️ 請至少上傳一張證明照片喔！');
                event.preventDefault(); 
                return false;
            }
            return true;
        }

        window.onload = function() {
            updateInputLabel();
        };
    </script>
</head>
<body>
    
    <div class="form-container">
        
        <?php if ($is_challenge): ?>
            <h2 style="color: #d35400;">🎯 正在解鎖任務：<br><?php echo htmlspecialchars($challenge_title); ?></h2>
            <div class="calc-box challenge-box">
                <small style="color: #d35400; font-weight: bold;">⚔️ 這是專屬挑戰任務！請填寫完成心得並附上照片，審核通過後即可獲得豐厚獎勵！</small>
            </div>
        <?php else: ?>
            <h2 style="color: #2e8b57;">📖 減碳日誌與計算器</h2>
            <div class="calc-box">
                <small style="color: #2e8b57; font-weight: bold;">💡 可量化的行動將自動試算減碳量；其他行動將由地球守護者人工審核並發放對應獎勵。</small>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="submit_log.php" enctype="multipart/form-data" onsubmit="return validateForm(event)">
            
            <?php if ($is_challenge): ?>
                <input type="hidden" name="action_type" value="挑戰任務：<?php echo htmlspecialchars($challenge_title); ?>">
            <?php else: ?>
                <label style="font-weight: bold;">本次環保行為：</label>
                <select name="action_type" id="action_type" onchange="updateInputLabel()" required>
                    <option value="騎自行車/步行">騎自行車/步行</option>
                    <option value="搭乘大眾運輸">搭乘大眾運輸</option>
                    <option value="買節能家電">買節能家電</option>
                    <option value="自備環保杯/餐具">自備環保杯/餐具</option>
                </select>

                <div id="dynamic_input_area">
                    <label id="input_label" style="font-weight: bold; color: #d35400;">📍 總共移動了多少公里？</label>
                    <input type="number" name="input_value" id="input_value" min="0.1" step="0.1" placeholder="請輸入公里數 (例如: 5.5)">
                </div>
            <?php endif; ?>

            <label style="font-weight: bold;">簡單描述您的冒險過程：</label>
            <textarea name="description" rows="3" placeholder="<?php echo $is_challenge ? '例如：我連續三天都改搭捷運通勤，真的比自己騎車涼爽多了！' : '例如：今天騎 Ubike 5公里通勤，流了滿身汗但心情很好！'; ?>" required></textarea>

            <label style="font-weight: bold;">上傳證明照片：</label>
            
            <input type="file" id="hidden_file_input" multiple accept="image/*" style="display: none;" onchange="handleFileSelect(event)">
            
            <button type="button" class="add-photo-btn" onclick="triggerFileInput()">➕ 點擊分批選擇照片</button>
            
            <div id="preview_area" class="photo-preview-container"></div>
            
            <input type="file" id="final_photos" name="photos[]" multiple style="display: none;">

            <button type="submit" class="submit-btn <?php echo $is_challenge ? 'challenge-btn' : ''; ?>">
                <?php echo $is_challenge ? '🚀 提交任務證明' : '送出日誌等待審核'; ?>
            </button>
        </form>
        
        <a href="dashboard.php" class="back-link">🔙 取消並返回大廳</a>
    </div>

</body>
</html>
<?php 
// 關閉 PDO 連線
$pdo = null; 
?>