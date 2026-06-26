<?php
session_start();
require_once 'config.php'; 

// 檢查是否為管理員
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    exit('權限不足');
}

$item_id = 0;
$item_name = '';
$store_name = '';
$cost_points = 0;

try {
    // ==========================================
    // 狀況一：管理員修改完畢，按下「儲存修改」送出表單 (POST)
    // ==========================================
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_item'])) {
        $id = (int)$_POST['id'];
        $item_name = trim($_POST['item_name']);
        $store_name = trim($_POST['store_name']);
        $cost_points = (int)$_POST['cost_points'];

        // 使用 PDO 預處理語法進行 UPDATE
        $update_sql = "UPDATE mall_items SET item_name = :item_name, store_name = :store_name, cost_points = :cost_points WHERE id = :id";
        $stmt = $pdo->prepare($update_sql);
        
        $update_success = $stmt->execute([
            ':item_name' => $item_name,
            ':store_name' => $store_name,
            ':cost_points' => $cost_points,
            ':id' => $id
        ]);

        if ($update_success) {
            echo "<script>alert('✏️ 商品資料已成功更新！'); window.location.href='dashboard.php';</script>";
            exit;
        } else {
            echo "<script>alert('更新失敗！請檢查資料格式。');</script>";
        }
    }

    // ==========================================
    // 狀況二：管理員從大廳點擊「修改」進來，準備編輯 (GET)
    // ==========================================
    // 檢查網址是否有帶 id 過來 (例如: admin_mall_edit.php?id=1)
    if (isset($_GET['id'])) {
        $item_id = (int)$_GET['id'];
        
        // 使用 PDO 撈取特定 ID 的商品資料
        $stmt = $pdo->prepare("SELECT * FROM mall_items WHERE id = :id");
        $stmt->execute([':id' => $item_id]);
        
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($item) {
            // 把舊資料撈出來，等一下塞進 HTML 表單裡
            $item_name = $item['item_name'];
            $store_name = $item['store_name'];
            $cost_points = $item['cost_points'];
        } else {
            echo "<script>alert('找不到該商品！'); window.location.href='dashboard.php';</script>";
            exit;
        }
    } elseif ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        // 如果網址沒有 id，也不是來送出表單的，就把他踢回大廳
        header("Location: dashboard.php");
        exit;
    }

} catch (PDOException $e) {
    die("資料庫操作失敗：" . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>修改商城商品 - 綠色冒險家公會</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #ecf0f1; padding: 40px; }
        .form-container { background: white; max-width: 400px; margin: auto; padding: 30px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        label { display: block; font-weight: bold; margin-bottom: 8px; color: #333; }
        input[type="text"], input[type="number"] { width: 100%; padding: 10px; margin-bottom: 20px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; }
        .btn-submit { background-color: #2980b9; color: white; border: none; padding: 12px; border-radius: 5px; cursor: pointer; font-size: 16px; width: 100%; font-weight: bold; }
        .btn-submit:hover { background-color: #21618c; }
        .back-link { display: block; text-align: center; margin-top: 15px; color: #777; text-decoration: none; }
    </style>
</head>
<body>

    <div class="form-container">
        <h2 style="color: #2980b9; text-align: center;">✏️ 修改商品資料</h2>
        
        <form method="POST" action="admin_mall_edit.php">
            <input type="hidden" name="id" value="<?php echo $item_id; ?>">

            <label>票券或商品名稱：</label>
            <input type="text" name="item_name" value="<?php echo htmlspecialchars($item_name); ?>" required>

            <label>合作店家名稱：</label>
            <input type="text" name="store_name" value="<?php echo htmlspecialchars($store_name); ?>" required>

            <label>兌換所需碳幣：</label>
            <input type="number" name="cost_points" min="1" value="<?php echo $cost_points; ?>" required>

            <button type="submit" name="update_item" class="btn-submit">💾 儲存修改</button>
        </form>
        
        <a href="dashboard.php" class="back-link">🔙 取消並返回大廳</a>
    </div>

</body>
</html>
<?php 
// 釋放 PDO 資源
$pdo = null; 
?>