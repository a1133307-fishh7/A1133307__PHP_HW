<?php
session_start();
require_once 'config.php';

// 檢查登入狀態
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// 建立一個空陣列來存放排行榜資料
$leaderboard_data = [];

// 使用 try...catch 保護網來進行 PDO 查詢
try {
    // 撈取全站一般玩家，先按等級(level)排序，等級一樣則按經驗值(exp)排序，只取前 10 名
    // 因為這裡沒有來自使用者的動態輸入變數，所以直接 execute() 即可
    $sql = "SELECT name, level, exp
            FROM users
            WHERE role = 'user'
            ORDER BY level DESC, exp DESC
            LIMIT 10";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    // 將撈取到的所有資料一次性存入陣列中
    $leaderboard_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // 捕捉錯誤，避免敏感資訊外洩，並停止執行
    die("資料庫讀取失敗，請稍後再試或聯繫管理員。" . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>英雄排行榜 - 綠色冒險家公會</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f9f4; padding: 40px; }
        .container { max-width: 600px; margin: auto; background: white; padding: 30px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); text-align: center; }
        .btn-back { display: inline-block; margin-bottom: 20px; padding: 10px 15px; background: #95a5a6; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; float: left; }
        .btn-back:hover { background: #7f8c8d; }
        
        /* 排行榜列表樣式 */
        .rank-list { list-style: none; padding: 0; margin-top: 20px; text-align: left; }
        .rank-item {
            display: flex; justify-content: space-between; align-items: center;
            background: #fafafa; padding: 15px 20px; margin-bottom: 10px;
            border-radius: 10px; border: 1px solid #eee;
            transition: transform 0.2s;
        }
        .rank-item:hover { transform: scale(1.02); box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        
        .rank-num { font-size: 20px; font-weight: bold; width: 40px; }
        .player-info { flex-grow: 1; font-size: 18px; font-weight: bold; color: #333; }
        .player-stats { text-align: right; }
        .level-badge { background: #2e8b57; color: white; padding: 3px 8px; border-radius: 12px; font-size: 14px; margin-bottom: 5px; display: inline-block; }
        .exp-text { font-size: 12px; color: #777; font-weight: normal; }

        /* 前三名的專屬樣式 */
        .rank-1 { background: linear-gradient(135deg, #fff9c4, #fff59d); border-color: #f1c40f; }
        .rank-1 .rank-num { color: #f1c40f; font-size: 28px; }
        .rank-1 .player-info { color: #d35400; font-size: 22px; }
        
        .rank-2 { background: linear-gradient(135deg, #f5f6fa, #dcdde1); border-color: #bdc3c7; }
        .rank-2 .rank-num { color: #7f8c8d; font-size: 24px; }
        
        .rank-3 { background: linear-gradient(135deg, #fbeee6, #edbb99); border-color: #e67e22; }
        .rank-3 .rank-num { color: #d35400; font-size: 22px; }
    </style>
</head>
<body>

<div class="container">
    <div style="overflow: hidden;">
        <a href="dashboard.php" class="btn-back">⬅ 返回大廳</a>
    </div>
    
    <h2 style="color: #2e8b57; margin-top: 0;">🏆 減碳英雄排行榜 🏆</h2>
    <p style="color: #666; font-size: 14px;">全站前 10 名最活躍的綠色冒險家，看看誰是地球守護者！</p>

    <ul class="rank-list">
        <?php
        $rank = 1;
        // 將原本的 mysqli_num_rows 改用 count() 來計算陣列數量
        if (count($leaderboard_data) > 0):
            // 將原本的 mysqli_fetch_assoc 替換為乾淨的 foreach 迴圈
            foreach ($leaderboard_data as $row):
                // 判斷前三名的專屬 class 與獎牌圖示
                $rank_class = '';
                $medal = $rank;
                if ($rank == 1) { $rank_class = 'rank-1'; $medal = '🥇'; }
                if ($rank == 2) { $rank_class = 'rank-2'; $medal = '🥈'; }
                if ($rank == 3) { $rank_class = 'rank-3'; $medal = '🥉'; }
        ?>
            <li class="rank-item <?php echo $rank_class; ?>">
                <div class="rank-num"><?php echo $medal; ?></div>
                <div class="player-info"><?php echo htmlspecialchars($row['name']); ?></div>
                <div class="player-stats">
                    <div class="level-badge">Lv. <?php echo $row['level']; ?></div>
                    <div class="exp-text"><?php echo $row['exp']; ?> EXP</div>
                </div>
            </li>
        <?php
            $rank++;
            endforeach;
        else:
        ?>
            <li style="text-align: center; color: #999; padding: 20px;">目前還沒有冒險者踏上旅程喔！</li>
        <?php endif; ?>
    </ul>
</div>

</body>
</html>
<?php 
// PDO 會在腳本結束時自動關閉連線，如果想要嚴謹一點，可以手動將連線釋放
$pdo = null; 
?>