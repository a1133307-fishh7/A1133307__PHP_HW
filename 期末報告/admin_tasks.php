<?php
session_start();
require_once 'config.php';

// 1. 檢查登入狀態
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

try {
    // 驗證是否為管理員
    $user_id = $_SESSION['user_id'];
    $stmt_role = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt_role->execute([$user_id]);
    $user_data = $stmt_role->fetch(PDO::FETCH_ASSOC);

    if (!$user_data || $user_data['role'] !== 'admin') {
        echo "<script>alert('您沒有權限訪問此頁面！'); window.location.href='dashboard.php';</script>";
        exit;
    }

    // 2. 處理「新增任務」的表單送出
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_task'])) {
        // PDO 會自動處理跳脫，只需移除前後空白
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $difficulty = $_POST['difficulty'];
        $reward_points = (int)$_POST['reward_points'];
        $task_type = $_POST['task_type'];
        $deadline = $_POST['deadline'];

        // 使用預處理語句與具名參數綁定
        $sql_insert = "INSERT INTO challenge_tasks (title, description, difficulty, reward_points, task_type, deadline) 
                       VALUES (:title, :desc, :diff, :reward, :type, :deadline)";
        $stmt_insert = $pdo->prepare($sql_insert);
        
        $insert_success = $stmt_insert->execute([
            ':title' => $title,
            ':desc' => $description,
            ':diff' => $difficulty,
            ':reward' => $reward_points,
            ':type' => $task_type,
            ':deadline' => $deadline
        ]);

        if ($insert_success) {
            echo "<script>alert('🎯 挑戰任務新增成功！'); window.location.href='admin_tasks.php';</script>";
            exit;
        } else {
            echo "<script>alert('新增失敗，請檢查資料格式。');</script>";
        }
    }

    // 3. 處理「刪除任務」的請求
    if (isset($_GET['delete_id'])) {
        $del_id = (int)$_GET['delete_id'];
        $stmt_del = $pdo->prepare("DELETE FROM challenge_tasks WHERE id = ?");
        $stmt_del->execute([$del_id]);
        
        echo "<script>alert('🗑️ 任務已刪除！'); window.location.href='admin_tasks.php';</script>";
        exit;
    }

    // 4. 撈取目前所有任務
    $stmt_tasks = $pdo->prepare("SELECT * FROM challenge_tasks ORDER BY deadline ASC, created_at DESC");
    $stmt_tasks->execute();
    $tasks_data = $stmt_tasks->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("資料庫操作失敗：" . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>任務管理 - 綠色冒險家公會</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #ecf0f1; padding: 20px; }
        .container { max-width: 900px; margin: auto; background: white; padding: 30px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .btn-back { display: inline-block; margin-bottom: 20px; padding: 10px 15px; background: #95a5a6; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; }
        
        /* 表單樣式 */
        .form-card { background: #fafafa; padding: 20px; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 30px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 5px; color: #333; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .btn-submit { background-color: #f39c12; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-weight: bold; font-size: 16px; width: 100%; }
        
        /* 表格樣式 */
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px; border-bottom: 1px solid #eee; text-align: left; }
        th { background-color: #34495e; color: white; }
        tr:hover { background-color: #f9f9f9; }
        .badge { padding: 4px 8px; border-radius: 12px; font-size: 12px; color: white; font-weight: bold; }
        .bg-daily { background-color: #3498db; }
        .bg-weekly { background-color: #9b59b6; }
        .bg-easy { background-color: #2ecc71; }
        .bg-mid { background-color: #f1c40f; color: #333; }
        .bg-hard { background-color: #e74c3c; }
        .btn-del { color: white; background: #e74c3c; padding: 5px 10px; text-decoration: none; border-radius: 3px; font-size: 13px; }
    </style>
</head>
<body>

<div class="container">
    <a href="dashboard.php" class="btn-back">⬅ 返回大廳</a>
    <h2>🎯 挑戰任務管理中心</h2>
    <p style="color: #666;">您可以在此發布新的每日或每週任務，激勵冒險者們參與減碳行動。</p>

    <div class="form-card">
        <h3>➕ 發布新任務</h3>
        <form method="POST" action="admin_tasks.php">
            <div style="display: flex; gap: 15px;">
                <div class="form-group" style="flex: 2;">
                    <label>任務標題</label>
                    <input type="text" name="title" placeholder="例如：連續三天搭乘捷運" required>
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>任務類型</label>
                    <select name="task_type" required>
                        <option value="daily">每日挑戰</option>
                        <option value="weekly">每週挑戰</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>任務描述與條件</label>
                <textarea name="description" rows="3" placeholder="請詳細說明任務完成的條件..." required></textarea>
            </div>

            <div style="display: flex; gap: 15px;">
                <div class="form-group" style="flex: 1;">
                    <label>難度</label>
                    <select name="difficulty" required>
                        <option value="簡單">簡單</option>
                        <option value="中等">中等</option>
                        <option value="困難">困難</option>
                    </select>
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>獎勵碳幣</label>
                    <input type="number" name="reward_points" value="10" min="1" max="500" required>
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>截止日期</label>
                    <input type="date" name="deadline" required>
                </div>
            </div>

            <button type="submit" name="add_task" class="btn-submit">🚀 正式發布任務</button>
        </form>
    </div>

    <h3>📋 現有任務列表</h3>
    <table>
        <thead>
            <tr>
                <th>類型</th>
                <th>任務標題</th>
                <th>難度</th>
                <th>獎勵</th>
                <th>截止日期</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($tasks_data) > 0): ?>
                <?php foreach ($tasks_data as $row): ?>
                    <tr>
                        <td>
                            <?php if($row['task_type'] == 'daily'): ?>
                                <span class="badge bg-daily">每日</span>
                            <?php else: ?>
                                <span class="badge bg-weekly">每週</span>
                            <?php endif; ?>
                        </td>
                        <td><strong><?php echo htmlspecialchars($row['title']); ?></strong></td>
                        <td>
                            <?php
                                $diff_class = '';
                                if($row['difficulty'] == '簡單') $diff_class = 'bg-easy';
                                if($row['difficulty'] == '中等') $diff_class = 'bg-mid';
                                if($row['difficulty'] == '困難') $diff_class = 'bg-hard';
                            ?>
                            <span class="badge <?php echo $diff_class; ?>"><?php echo htmlspecialchars($row['difficulty']); ?></span>
                        </td>
                        <td style="color: #e67e22; font-weight: bold;">💰 <?php echo htmlspecialchars($row['reward_points']); ?></td>
                        <td><?php echo htmlspecialchars($row['deadline']); ?></td>
                        <td>
                            <a href="admin_tasks.php?delete_id=<?php echo $row['id']; ?>" class="btn-del" onclick="return confirm('確定要刪除這個任務嗎？');">刪除</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" style="text-align: center; color: #999; padding: 20px;">目前還沒有發布任何任務喔！</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>
<?php $pdo = null; ?>