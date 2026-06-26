<?php
session_start();
require_once 'config.php'; 

// 檢查登入與權限
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo "<script>alert('權限不足！此區域僅限地球守護者進入。'); window.location.href='dashboard.php';</script>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // PDO 會自動處理特殊字元跳脫，只需移除前後空白即可
        $item_name = trim($_POST['item_name']);
        $store_name = trim($_POST['store_name']);
        $cost_points = (int)$_POST['cost_points'];

        // 使用 PDO 預處理語句與具名參數綁定
        $sql = "INSERT INTO mall_items (item_name, store_name, cost_points) VALUES (:item_name, :store_name, :cost_points)";
        $stmt = $pdo->prepare($sql);
        
        $insert_success = $stmt->execute([
            ':item_name' => $item_name,
            ':store_name' => $store_name,
            ':cost_points' => $cost_points
        ]);
        
        if ($insert_success) {
            echo "<script>alert('🎉 新商品/店家已成功上架！'); window.location.href='dashboard.php';</script>";
            exit; // 成功後直接導向並停止執行
        } else {
            echo "<script>alert('上架失敗，請檢查資料格式。');</script>";
        }
    } catch (PDOException $e) {
        die("資料庫操作失敗：" . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>新增商城商品 - 綠色冒險家公會</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #ecf0f1; padding: 40px; }
        .form-container { background: white; max-width: 400px; margin: auto; padding: 30px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        label { display: block; font-weight: bold; margin-bottom: 8px; color: #333; }
        input[type="text"], input[type="number"] { width: 100%; padding: 10px; margin-bottom: 20px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; }
        .btn-submit { background-color: #2e8b57; color: white; border: none; padding: 12px; border-radius: 5px; cursor: pointer; font-size: 16px; width: 100%; font-weight: bold; }
        .btn-submit:hover { background-color: #246b43; }
        .back-link { display: block; text-align: center; margin-top: 15px; color: #777; text-decoration: none; }
    </style>
</head>
<body>
    
    <div class="form-container">
        <h2 style="color: #2e8b57; text-align: center;">🛒 上架新折價券 / 商品</h2>
        
        <form method="POST" action="admin_mall_add.php">
            <label>票券或商品名稱：</label>
            <input type="text" name="item_name" placeholder="例如：有機咖啡買一送一券" required>

            <label>合作店家名稱：</label>
            <input type="text" name="store_name" placeholder="例如：路易莎環保門市" required>

            <label>兌換所需碳幣：</label>
            <input type="number" name="cost_points" min="1" placeholder="例如：50" required>

            <button type="submit" class="btn-submit">✅ 確定上架</button>
        </form>
        
        <a href="dashboard.php" class="back-link">🔙 取消並返回大廳</a>
    </div>

</body>
</html>
<?php 
// 釋放資源
$pdo = null; 
?>