<?php
session_start();
require_once 'config.php';

// 1. 檢查登入狀態與權限
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

try {
    // 驗證是否為管理員
    $admin_id = $_SESSION['user_id'];
    $stmt_role = $pdo->prepare("SELECT role FROM users WHERE id = :id");
    $stmt_role->execute([':id' => $admin_id]);
    $user_data = $stmt_role->fetch(PDO::FETCH_ASSOC);

    if (!$user_data || $user_data['role'] !== 'admin') {
        echo "<script>alert('您沒有權限訪問此頁面！'); window.location.href='dashboard.php';</script>";
        exit;
    }

    // 2. 處理「儲存修改」的動作 (只修改積分，移除權限修改)
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_user'])) {
        $target_id = (int)$_POST['target_id'];
        $new_points = (int)$_POST['new_points'];

        $stmt_update = $pdo->prepare("UPDATE users SET points = :points WHERE id = :id");
        if ($stmt_update->execute([':points' => $new_points, ':id' => $target_id])) {
            echo "<script>alert('✅ 碳幣餘額更新成功！'); window.location.href='admin_users.php';</script>";
            exit;
        } else {
            echo "<script>alert('更新失敗，請檢查資料庫狀態。');</script>";
        }
    }

    // 3. 處理「停權/復權」的切換動作
    if (isset($_GET['toggle_status']) && isset($_GET['id'])) {
        $target_id = (int)$_GET['id'];
        $current_status = $_GET['toggle_status'];
        
        // 如果現在是 active，就改成 suspended；反之亦然
        $new_status = ($current_status === 'active') ? 'suspended' : 'active';
        
        $stmt_status = $pdo->prepare("UPDATE users SET status = :status WHERE id = :id");
        if ($stmt_status->execute([':status' => $new_status, ':id' => $target_id])) {
            echo "<script>alert('🔄 帳號狀態已更新！'); window.location.href='admin_users.php';</script>";
            exit;
        }
    }

    // 4. 💡 關鍵修改：只撈取 role 為 'user' (一般玩家) 的資料，過濾掉管理員
    $stmt_users = $pdo->prepare("SELECT * FROM users WHERE role = 'user' ORDER BY id DESC");
    $stmt_users->execute();
    $users_data = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("資料庫操作失敗：" . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>使用者管理 - 綠色冒險家公會</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #ecf0f1; padding: 20px; }
        .container { max-width: 1000px; margin: auto; background: white; padding: 30px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .btn-back { display: inline-block; margin-bottom: 20px; padding: 10px 15px; background: #95a5a6; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; }
        
        /* 表格樣式 */
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 14px; }
        th, td { padding: 12px; border-bottom: 1px solid #eee; text-align: left; vertical-align: middle; }
        th { background-color: #8e44ad; color: white; }
        tr:hover { background-color: #f9f9f9; }
        
        /* 輸入框與按鈕 */
        input[type="number"] { padding: 6px; border: 1px solid #ccc; border-radius: 4px; }
        .btn-save { background-color: #27ae60; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-suspend { background-color: #e74c3c; color: white; padding: 6px 12px; text-decoration: none; border-radius: 4px; font-weight: bold; }
        .btn-active { background-color: #3498db; color: white; padding: 6px 12px; text-decoration: none; border-radius: 4px; font-weight: bold; }
        
        .status-bad { color: #e74c3c; font-weight: bold; }
    </style>
</head>
<body>

<div class="container">
    <a href="dashboard.php" class="btn-back">⬅ 返回大廳</a>
    <h2>👥 一般玩家帳號管理</h2>
    <p style="color: #666;">在此頁面，您可以強制修正玩家的碳幣餘額，或是將違規帳號停權。</p>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>姓名 / 信箱</th>
                <th>身分</th>
                <th>狀態</th>
                <th>修改碳幣餘額</th>
                <th>快速操作</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($users_data) > 0): ?>
                <?php foreach ($users_data as $row): ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($row['name']); ?></strong><br>
                            <span style="color: #777; font-size: 12px;"><?php echo htmlspecialchars($row['email']); ?></span>
                        </td>
                        <td>
                            <span style="color: #8e44ad; font-weight: bold;">一般玩家</span>
                        </td>
                        <td>
                            <?php if (!isset($row['status']) || $row['status'] === 'active'): ?>
                                <span style="color: #27ae60; font-weight: bold;">🟢 正常</span>
                            <?php else: ?>
                                <span class="status-bad">🔴 已停權</span>
                            <?php endif; ?>
                        </td>
                        
                        <td>
                            <form method="POST" action="admin_users.php" style="margin: 0; display: flex; gap: 8px; align-items: center;">
                                <input type="hidden" name="target_id" value="<?php echo $row['id']; ?>">
                                
                                💰 <input type="number" name="new_points" value="<?php echo htmlspecialchars($row['points']); ?>" style="width: 80px;" required>
                                
                                <button type="submit" name="update_user" class="btn-save">更新</button>
                            </form>
                        </td>

                        <td>
                            <?php
                                $current_status = isset($row['status']) ? $row['status'] : 'active';
                            ?>
                            <?php if ($current_status === 'active'): ?>
                                <a href="admin_users.php?toggle_status=active&id=<?php echo $row['id']; ?>" class="btn-suspend" onclick="return confirm('確定要將此玩家停權嗎？停權後他將無法登入！');">🚫 停權</a>
                            <?php else: ?>
                                <a href="admin_users.php?toggle_status=suspended&id=<?php echo $row['id']; ?>" class="btn-active" onclick="return confirm('確定要恢復此玩家的帳號嗎？');">✅ 復權</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" style="text-align: center; color: #999; padding: 20px;">目前沒有任何玩家資料。</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>
<?php 
// 釋放資料庫連線
$pdo = null; 
?>