<?php
session_start();
require_once 'config.php'; 

// 檢查是否已登入
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$records = [];

// 使用 try...catch 保護網進行 PDO 資料庫操作
try {
    // 撈取該使用者的所有紀錄，並依照時間倒序排列（最新的在最上面）
    // 💡 升級重點：使用 :user_id 作為佔位符，徹底防範 SQL Injection
    $sql = "SELECT * FROM task_records WHERE user_id = :user_id ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    
    // 綁定參數並執行
    $stmt->execute([':user_id' => $user_id]);
    
    // 將結果全部轉為關聯式陣列
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("資料庫讀取失敗，請稍後再試或聯繫管理員。" . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>我的減碳紀錄 - 綠色冒險家公會</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            background-color: #e8f5e9; /* 淺綠色背景呼應主題 */
            padding: 20px;
        }
        .container { 
            max-width: 800px; 
            margin: auto; 
            background: white; 
            padding: 30px; 
            border-radius: 15px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.1); 
        }
        .btn-back { 
            display: inline-block; 
            margin-bottom: 20px; 
            padding: 10px 15px; 
            background: #95a5a6; 
            color: white; 
            text-decoration: none; 
            border-radius: 5px; 
            font-weight: bold;
        }
        .btn-back:hover { background: #7f8c8d; }
        
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 10px; 
        }
        th, td { 
            padding: 15px; 
            border-bottom: 1px solid #eee; 
            text-align: left; 
        }
        th { 
            background-color: #2e8b57; 
            color: white; 
        }
        tr:hover { background-color: #f9f9f9; }
        
        /* 狀態標籤的顏色 */
        .status-pending { color: #f39c12; font-weight: bold; } /* 橘色：審核中 */
        .status-approved { color: #27ae60; font-weight: bold; } /* 綠色：已通過 */
        .status-rejected { color: #e74c3c; font-weight: bold; } /* 紅色：未通過 */
        
        .photo-preview { max-width: 100px; border-radius: 5px; }
    </style>
</head>
<body>

<div class="container">
    <a href="dashboard.php" class="btn-back">⬅ 返回大廳</a>
    <h2>📜 我的減碳歷程</h2>
    <p style="color: #666;">這裡記錄了你所有的環保行動，包含審核中與已完成的任務。</p>

    <table>
        <thead>
            <tr>
                <th>提交時間</th>
                <th>任務類型</th>
                <th>減少碳排</th> 
                <th>證明照片</th> 
                <th>描述</th>
                <th>審核狀態</th>
                <th>獲得獎勵</th>
                <th>失敗原因</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            // 💡 升級重點：改用 count() 判斷陣列，並使用 foreach 迴圈印出資料
            if (count($records) > 0): 
            ?>
                <?php foreach ($records as $row): ?>
                    <tr>
                        <td><?php echo date('Y-m-d H:i', strtotime($row['created_at'])); ?></td>
                        <td><?php echo htmlspecialchars($row['action_type']); ?></td>
                        
                        <td>
                            <?php if ($row['action_type'] === '騎自行車/步行'): ?>
                                <strong style="color: #27ae60;">⬇ <?php echo htmlspecialchars($row['co2_saved']); ?> kg</strong>
                            <?php else: ?>
                                <span style="color: #ccc;">-</span>
                            <?php endif; ?>
                        </td>
                        
                        <td>
                            <?php 
                            if (!empty($row['photo_path'])) {
                                $photos = explode(',', $row['photo_path']);
                                foreach ($photos as $path) {
                                    if (!empty($path)) {
                                        echo "<img src='" . htmlspecialchars($path) . "' style='max-width: 60px; margin-right: 5px; border-radius: 5px;'>";
                                    }
                                }
                            } else {
                                echo "<span style='color: #999;'>無照片</span>";
                            }
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars($row['description']); ?></td>
                        <td>
                            <?php 
                                if ($row['status'] == 'pending') echo "<span class='status-pending'>⏳ 審核中</span>";
                                elseif ($row['status'] == 'approved') echo "<span class='status-approved'>✅ 已通過</span>";
                                elseif ($row['status'] == 'rejected') echo "<span class='status-rejected'>❌ 未通過</span>";
                                else echo htmlspecialchars($row['status']);
                            ?>
                        </td>
                        <td>
                            <?php
                                if ($row['status'] == 'approved') {
                                    // 審核通過，顯示金黃色的點數
                                    echo "<strong style='color: #e67e22; font-size: 16px;'>💰 +" . htmlspecialchars($row['reward_earned']) . "</strong>";
                                } elseif ($row['status'] == 'pending') {
                                    // 還沒審核，顯示結算中
                                    echo "<span style='color: #f39c12; font-size: 13px;'>結算中...</span>";
                                } else {
                                    // 被退件，當然是沒有分數
                                    echo "<span style='color: #ccc;'>-</span>";
                                }
                            ?>
                        </td>
                        <td>
                            <?php 
                                if ($row['status'] == 'rejected' && !empty($row['reject_reason'])) {
                                    echo "<span style='color: #e74c3c; font-size: 14px;'>" . nl2br(htmlspecialchars($row['reject_reason'])) . "</span>";
                                } else echo "<span style='color: #ccc;'>-</span>";
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" style="text-align: center; color: #999; padding: 30px;">
                        🌵 目前還沒有任何減碳紀錄喔！快回大廳去解任務吧！
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>
<?php 
// 釋放資源
$pdo = null; 
?>