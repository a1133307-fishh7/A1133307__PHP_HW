<?php
session_start();

// 💡 引入機密設定檔 (已包含 $pdo 連線)
require_once 'config.php';

// 門禁系統：如果沒有登入，或是身分不是 admin，直接踢回大廳
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo "<script>alert('權限不足！此區域僅限地球守護者進入。'); window.location.href='dashboard.php';</script>";
    exit;
}

// ==========================================
// 處理管理員的「審核與退件動作」
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $record_id = (int)$_POST['record_id'];

    try {
        if ($action === 'approve') {
            $target_user_id = (int)$_POST['user_id'];
            $reward_points = (int)$_POST['reward_points'];
            
            // 1. 任務狀態改為已通過，並記錄發放了多少獎勵點數！
            $stmt_task = $pdo->prepare("UPDATE task_records SET status = 'approved', reward_earned = :reward WHERE id = :id");
            $stmt_task->execute([':reward' => $reward_points, ':id' => $record_id]);

            // 2. 把獎勵同時加進「碳幣(points)」和「經驗值(exp)」
            $stmt_user = $pdo->prepare("UPDATE users SET points = points + :points, exp = exp + :exp WHERE id = :uid");
            $stmt_user->execute([':points' => $reward_points, ':exp' => $reward_points, ':uid' => $target_user_id]);

            // 3. 自動升級系統：每 50 經驗值升 1 級
            $stmt_level = $pdo->prepare("UPDATE users SET level = FLOOR(exp / 50) + 1 WHERE id = :uid");
            $stmt_level->execute([':uid' => $target_user_id]);

            // 使用 JS 導頁避免使用者重整頁面時重複送出表單
            echo "<script>alert('審核通過！已發放 {$reward_points} 點碳幣與經驗值給冒險者。'); window.location.href='admin_review.php';</script>";
            exit;

        } elseif ($action === 'reject') {
            // 接收剛剛 JavaScript 塞進來的失敗原因
            $reason = isset($_POST['reject_reason']) ? trim($_POST['reject_reason']) : '';
            
            // 準備 SQL 語法：把狀態改成 rejected，並把原因寫進 reject_reason 欄位裡
            $stmt_reject = $pdo->prepare("UPDATE task_records SET status = 'rejected', reject_reason = :reason WHERE id = :id");
            $stmt_reject->execute([':reason' => $reason, ':id' => $record_id]);

            echo "<script>alert('已退回該項任務。'); window.location.href='admin_review.php';</script>";
            exit;
        }
    } catch (PDOException $e) {
        die("處理失敗，請稍後再試或聯繫管理員：" . $e->getMessage());
    }
}

// ==========================================
// 抓取所有「待審核 (pending)」的任務清單
// ==========================================
$pending_tasks = [];
try {
    $sql_pending = "SELECT tr.*, u.name AS player_name 
                    FROM task_records tr 
                    JOIN users u ON tr.user_id = u.id 
                    WHERE tr.status = 'pending' 
                    ORDER BY tr.created_at DESC";
    $stmt = $pdo->prepare($sql_pending);
    $stmt->execute();
    $pending_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("清單讀取失敗：" . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>任務審核中心 - 地球守護者</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f0f8ff; padding: 20px; }
        .container { max-width: 1000px; margin: auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background-color: #2980b9; color: white; }
        .photo { max-width: 150px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
        .btn-approve { background-color: #2e8b57; color: white; border: none; padding: 8px 12px; border-radius: 5px; cursor: pointer; }
        .btn-reject { background-color: #e74c3c; color: white; border: none; padding: 8px 12px; border-radius: 5px; cursor: pointer; margin-top: 5px; }
        .nav-links a { color: #2980b9; text-decoration: none; font-weight: bold; margin-right: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <h2 style="color: #2980b9;">🛡️ 任務審核中心</h2>
        <div class="nav-links">
            <a href="dashboard.php">🔙 返回大廳</a>
            <a href="logout.php" style="color: red;">登出系統</a>
        </div>

        <table>
            <tr>
                <th>提交時間</th>
                <th>冒險者暱稱</th>
                <th>環保行為</th>
                <th>玩家描述</th>
                <th>證明照片</th>
                <th>審核動作</th>
            </tr>

            <?php if (count($pending_tasks) > 0): ?>
                <?php foreach ($pending_tasks as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                        <td><?php echo htmlspecialchars($row['player_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['action_type']); ?></td>
                        <td><?php echo htmlspecialchars($row['description']); ?></td>
                        <td>
                            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                <?php
                                if (!empty($row['photo_path'])) {
                                    $photos = explode(',', $row['photo_path']);
                                    foreach ($photos as $path) {
                                        if (!empty(trim($path))) {
                                            $clean_path = htmlspecialchars(trim($path));
                                            echo "<a href='{$clean_path}' target='_blank'>";
                                            echo "<img src='{$clean_path}' alt='證明照片' style='width: 80px; height: 80px; object-fit: cover; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); transition: 0.2s;' onmouseover='this.style.transform=\"scale(1.1)\"' onmouseout='this.style.transform=\"scale(1)\"'>";
                                            echo "</a>";
                                        }
                                    }
                                } else {
                                    echo "<span style='color: #999;'>無照片</span>";
                                }
                                ?>
                            </div>
                        </td>
                        <td>
                            <form method="POST" action="admin_review.php">
                                <input type="hidden" name="record_id" value="<?php echo $row['id']; ?>">
                                <input type="hidden" name="user_id" value="<?php echo $row['user_id']; ?>">
                                
                                <input type="hidden" name="reject_reason" id="reason_<?php echo $row['id']; ?>" value="">

                                <label>給予點數：</label>
                                <input type="number" name="reward_points" value="10" style="width: 50px;" min="1" max="100"><br><br>
                                
                                <button type="submit" name="action" value="approve" class="btn-approve">✅ 通過並給分</button>
                                
                                <button type="submit" name="action" value="reject" class="btn-reject" onclick="return askReason(<?php echo $row['id']; ?>);">❌ 不符退回</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan='6' style='text-align:center;'>太棒了！目前沒有待審核的任務。</td></tr>
            <?php endif; ?>
        </table>
    </div>

    <script>
        function askReason(recordId) {
            // 1. 彈出對話框，請管理員輸入原因
            let reason = prompt("請輸入退件原因（例如：照片模糊、非減碳行為）：");
            
            // 2. 如果管理員按了「取消」
            if (reason === null) {
                return false; // 取消送出表單
            }
            
            // 3. 如果管理員什麼都沒寫就按確定
            if (reason.trim() === "") {
                alert("退件原因不能為空白喔！");
                return false; // 擋下來，不送出表單
            }
            
            // 4. 把輸入好的原因，精準地塞進對應 id 的那個隱藏盒子裡
            document.getElementById('reason_' + recordId).value = reason;
            
            return true; // 檢查通過，正式送出！
        }
    </script>
</body>
</html>
<?php 
// 釋放資料庫連線
$pdo = null; 
?>