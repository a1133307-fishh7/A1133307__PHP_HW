<?php
session_start();
require_once 'config.php';

// 檢查登入狀態
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // ==========================================
    // 🔒 1. 撈取使用者基本資料 (PDO 預處理)
    // ==========================================
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        session_destroy();
        header("Location: login.php");
        exit;
    }

    $current_points = $user['points'];
    $player_name = $user['name'];    
    $player_role = $user['role'];    

    $player_exp = isset($user['exp']) ? (int)$user['exp'] : 0;
    $player_level = isset($user['level']) ? (int)$user['level'] : 1;

    $bg_color = "#f4f9f4";

    // ==========================================
    // 介面分流邏輯
    // ==========================================
    if ($player_role !== 'admin') {
        // 🧮 計算經驗進度條
        $current_level_base = ($player_level - 1) * 50; 
        $next_level_exp = $player_level * 50;           
        $exp_in_level = $player_exp - $current_level_base; 
        
        $progress_percent = ($exp_in_level / 50) * 100;
        if ($progress_percent > 100) $progress_percent = 100;

        $title = ($player_level >= 5) ? '🌎 減碳大師' : '🌱 實習勇者';

        // 🌿 領地狀態 (改為根據經驗值 EXP 升級)
        $territory_stage = 1; 
        if ($player_exp < 150) {
            $territory_status = "荒蕪沙漠 🌵";
            $bg_color = "#fcf3cf";
            $territory_desc = "目前的經驗值還不夠，你的個人領地仍是一片荒涼。快去填寫減碳日誌來拯救它吧！";
            $territory_image = "img/desert.png";
            $territory_stage = 1;
        } elseif ($player_exp >= 150 && $player_exp < 300) {
            $territory_status = "生機草地 🌱";
            $bg_color = "#e8f5e9";
            $territory_desc = "太棒了！因為你持續的環保行為，領地開始長出小草，展現一絲綠意了！再接再厲！";
            $territory_image = "img/grassland.png";
            $territory_stage = 2;
        } else {
            $territory_status = "茂密森林 🌳";
            $bg_color = "#a5d6a7";
            $territory_desc = "太令人驚嘆了！你強大的減碳力量，讓這片土地徹底進化成了充滿芬多精的茂密森林！";
            $territory_image = "img/forest.png";
            $territory_stage = 3;
        }
    } else {
        // ==========================================
        // 🛡️ 管理員：專業背景色與撈取圖表數據 (PDO)
        // ==========================================
        $bg_color = "#ecf0f1";

        // 1. 各項減碳行為分佈
        $stmt1 = $pdo->query("SELECT action_type, COUNT(*) as action_count FROM task_records WHERE status = 'approved' GROUP BY action_type");
        $chart1_data = "";
        if ($stmt1->rowCount() > 0) {
            while ($row = $stmt1->fetch()) {
                $chart1_data .= "['" . $row['action_type'] . "', " . $row['action_count'] . "],";
            }
        } else { $chart1_data = "['尚無數據', 1]"; }

        // 💡 2. 每週減碳量 (使用 PHP 產生完整月份框架，解決空資料不顯示的問題)
        
        // --- 步驟 A：用 PHP 自動產生「當月」的 1~5 週空框架 (預設為 0) ---
        $weekly_data = [];
        $current_date = new DateTime(); // 取得今天日期
        $c_year = $current_date->format('Y');
        $c_month = $current_date->format('n');
        
        // 找到當月 1 號
        $first_day = new DateTime("$c_year-$c_month-01");
        // 找到當月第一週的「星期一」
        $first_monday = clone $first_day;
        if ($first_monday->format('N') != 1) { 
            $first_monday->modify('last Monday'); 
        }
        
        // 產生 5 週的預設框架 (一個月最多涵蓋 5 週)
        for ($i = 0; $i < 5; $i++) {
            $start_w = clone $first_monday;
            $start_w->modify("+$i weeks");
            $end_w = clone $start_w;
            $end_w->modify("+6 days");
            
            // 判斷這週是否還屬於「當月」(以星期四落在哪個月為基準)
            $mid_w = clone $start_w;
            $mid_w->modify("+3 days");
            if ($mid_w->format('n') != $c_month) {
                continue; // 如果這週已經過渡到下個月，就略過不畫
            }
            
            $w_num = $i + 1;
            // 💡 修改點 1：組合出 "6月 第1週 (6/1 - 6/7)"
            $label = $c_month . "月 第" . $w_num . "週 (" . $start_w->format('n/j') . " - " . $end_w->format('n/j') . ")";
            $weekly_data[$label] = 0; // 預設把這週的碳排量塞入 0
        }

        // --- 步驟 B：去資料庫把「當月」有紀錄的週次撈出來 ---
        $sql2 = "SELECT 
                    MONTH(created_at) as record_month,
                    (WEEK(created_at, 1) - WEEK(DATE_SUB(created_at, INTERVAL DAY(created_at) - 1 DAY), 1) + 1) as week_of_month,
                    CONCAT(
                        DATE_FORMAT(DATE_ADD(created_at, INTERVAL -WEEKDAY(created_at) DAY), '%c/%e'), 
                        ' - ', 
                        DATE_FORMAT(DATE_ADD(created_at, INTERVAL 6 - WEEKDAY(created_at) DAY), '%c/%e')
                    ) as date_range,
                    SUM(co2_saved) as total_co2 
                 FROM task_records 
                 WHERE status = 'approved' 
                   AND MONTH(created_at) = MONTH(CURRENT_DATE()) /* 限制只撈當月 */
                   AND YEAR(created_at) = YEAR(CURRENT_DATE())
                 GROUP BY YEARWEEK(created_at, 1) 
                 ORDER BY YEARWEEK(created_at, 1) ASC";
                 
        $stmt2 = $pdo->query($sql2);
        
        // --- 步驟 C：把資料庫的數字，填入剛剛的空框架中 ---
        if ($stmt2->rowCount() > 0) {
            while ($row = $stmt2->fetch()) {
                $co2 = $row['total_co2'] ? $row['total_co2'] : 0;
                // 💡 修改點 2：這裡也要改成 "6月 第1週 (6/1 - 6/7)" 才能跟框架完美配對
                $label = $row['record_month'] . "月 第" . $row['week_of_month'] . "週 (" . $row['date_range'] . ")";
                
                // 如果這週在我們的框架裡，就更新數值 (從 0 變成實際數字)
                if (array_key_exists($label, $weekly_data)) {
                    $weekly_data[$label] = $co2;
                }
            }
        }
        
        // --- 步驟 D：轉換成 Google Charts 看得懂的字串格式 ---
        $chart2_data = "";
        foreach ($weekly_data as $lbl => $val) {
            $chart2_data .= "['" . $lbl . "', " . $val . "],";
        }

        // 3. 審核狀態
        $stmt3 = $pdo->query("SELECT status, COUNT(*) as status_count FROM task_records GROUP BY status");
        $chart3_data = "";
        $status_map = ['approved' => '✅ 已通過', 'pending' => '⏳ 審核中', 'rejected' => '❌ 未通過'];
        if ($stmt3->rowCount() > 0) {
            while ($row = $stmt3->fetch()) {
                $status_zh = isset($status_map[$row['status']]) ? $status_map[$row['status']] : $row['status'];
                $chart3_data .= "['" . $status_zh . "', " . $row['status_count'] . "],";
            }
        } else { $chart3_data = "['尚無任務', 1]"; }

        // 4. 活躍玩家
        $stmt4 = $pdo->query("SELECT SUM(CASE WHEN last_login_date >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as active_users, SUM(CASE WHEN last_login_date < DATE_SUB(NOW(), INTERVAL 7 DAY) OR last_login_date IS NULL THEN 1 ELSE 0 END) as inactive_users FROM users WHERE role = 'user'");
        $chart4_data = "";
        if ($row = $stmt4->fetch()) {
            $active = $row['active_users'] ?: 0;
            $inactive = $row['inactive_users'] ?: 0;
            if ($active == 0 && $inactive == 0) {
                $chart4_data = "['無玩家', 1]";
            } else {
                $chart4_data = "['活躍玩家 (7天內上線)', " . $active . "], ['沉睡玩家 (超過7天未登入)', " . $inactive . "]";
            }
        }
    }

} catch (PDOException $e) {
    die("系統載入失敗：" . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>大廳 - 綠色冒險家公會</title>
    
    <?php if ($player_role === 'admin'): ?>
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script type="text/javascript">
      google.charts.load('current', {'packages':['corechart', 'bar']});
      google.charts.setOnLoadCallback(drawAllCharts);

      function drawAllCharts() {
        var defaultBg = '#ffffff';

        new google.visualization.PieChart(document.getElementById('chart1')).draw(google.visualization.arrayToDataTable([ ['減碳行為', '完成次數'], <?php echo $chart1_data; ?> ]), { title: '🌿 各項減碳行為分佈', is3D: true, backgroundColor: defaultBg, colors: ['#2e8b57', '#81c784', '#aed581', '#dcedc8'] });
        new google.visualization.ColumnChart(document.getElementById('chart2')).draw(google.visualization.arrayToDataTable([ ['週別', '減少碳排 (kg)'], <?php echo $chart2_data; ?> ]), { title: '📈 全站每週減碳總量趨勢', backgroundColor: defaultBg, colors: ['#27ae60'], legend: { position: 'none' } });
        new google.visualization.PieChart(document.getElementById('chart3')).draw(google.visualization.arrayToDataTable([ ['狀態', '數量'], <?php echo $chart3_data; ?> ]), { title: '📋 任務審核完成率', pieHole: 0.4, backgroundColor: defaultBg, colors: ['#27ae60', '#f39c12', '#e74c3c'] });
        new google.visualization.PieChart(document.getElementById('chart4')).draw(google.visualization.arrayToDataTable([ ['玩家狀態', '人數'], <?php echo $chart4_data; ?> ]), { title: '👥 近7天活躍玩家分佈', is3D: true, backgroundColor: defaultBg, colors: ['#3498db', '#95a5a6'] });
      }
      window.addEventListener('resize', drawAllCharts);
    </script>
    <?php endif; ?>

    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: <?php echo $bg_color; ?>; transition: background-color 1s ease; margin: 0; padding: 0; display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background-color: #2e8b57; color: white; transition: 0.3s; display: flex; flex-direction: column; box-shadow: 2px 0 10px rgba(0,0,0,0.1); z-index: 100; }
        .sidebar-header { padding: 20px; text-align: center; border-bottom: 1px solid rgba(255, 255, 255, 0.2); background-color: rgba(0, 0, 0, 0.1); }
        .sidebar-header h2 { margin: 0; font-size: 20px; color: white; }
        .sidebar-menu { padding: 20px 0; flex-grow: 1; }
        .sidebar-menu a { display: block; padding: 15px 25px; color: rgba(255, 255, 255, 0.8); text-decoration: none; font-weight: bold; transition: 0.2s; border-left: 4px solid transparent; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background-color: rgba(255, 255, 255, 0.1); color: white; border-left: 4px solid #fff; }
        .main-content { flex-grow: 1; width: calc(100% - 250px); transition: 0.3s; display: flex; flex-direction: column; }
        .sidebar.collapsed { width: 0; overflow: hidden; }
        .main-content.expanded { width: 100%; }
        .topbar { background: white; padding: 15px 25px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .toggle-btn { background: none; border: none; font-size: 24px; cursor: pointer; color: #2c3e50; }
        .user-panel { text-align: right; }
        .content-container { padding: 30px; max-width: 1400px; margin: 0 auto; width: 100%; box-sizing: border-box; }
        .character-card { background: linear-gradient(135deg, #74b9ff, #0984e3); color: white; padding: 20px 25px; border-radius: 15px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 20px; }
        .avatar-box { font-size: 55px; background: rgba(255,255,255,0.2); border-radius: 50%; width: 85px; height: 85px; display: flex; justify-content: center; align-items: center; box-shadow: inset 0 2px 5px rgba(0,0,0,0.1); }
        .info-box { flex-grow: 1; }
        .progress-bg { background: rgba(0,0,0,0.2); border-radius: 10px; height: 14px; width: 100%; margin-top: 12px; overflow: hidden; position: relative; }
        .progress-fill { background: #ffeaa7; height: 100%; border-radius: 10px; transition: width 0.8s ease-in-out; box-shadow: 0 0 10px rgba(255, 234, 167, 0.5); }
        .exp-text { font-size: 13px; margin-top: 6px; text-align: right; font-weight: bold; letter-spacing: 1px; }
        .territory-card { background: #fff; border: 2px solid #2e8b57; padding: 25px; border-radius: 15px; text-align: center; margin-bottom: 25px; }
        .evolution-tracker { display: flex; justify-content: space-between; align-items: center; max-width: 600px; margin: 20px auto; position: relative; }
        .evo-line { position: absolute; top: 25px; left: 10%; right: 10%; height: 4px; background: #eee; z-index: 1; }
        .evo-line-fill { position: absolute; top: 25px; left: 10%; height: 4px; background: #2ecc71; z-index: 2; transition: 1s; }
        .evo-step { position: relative; z-index: 3; text-align: center; width: 33.33%; }
        .evo-icon { width: 50px; height: 50px; background: #fff; border: 3px solid #eee; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; margin: 0 auto 10px auto; transition: 0.3s; }
        .evo-step.active .evo-icon { border-color: #2ecc71; background: #e8f5e9; transform: scale(1.1); box-shadow: 0 0 15px rgba(46, 204, 113, 0.4); }
        .evo-step.passed .evo-icon { border-color: #2ecc71; background: #2ecc71; color: white; }
        .evo-text { font-weight: bold; color: #999; font-size: 14px; }
        .evo-step.active .evo-text { color: #2ecc71; }
        .dashboard-section { margin-top: 30px; border-top: 2px dashed #ccc; padding-top: 20px; }
        .chart-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px; }
        .chart-box { width: 100%; height: 350px; border: 1px solid #ddd; border-radius: 10px; overflow: hidden; background: white;}
        .mall-section { margin-top: 30px; background: white; padding: 25px; border-radius: 15px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .item-card { border: 1px solid #ddd; padding: 15px; border-radius: 8px; display: inline-block; width: 230px; text-align: center; background: #fafafa; margin-right: 15px; margin-bottom: 15px; vertical-align: top;}
        .btn { padding: 8px 15px; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; text-align: center; display: inline-block; border: none; cursor: pointer; }
        .btn-green { background-color: #27ae60; } .btn-blue { background-color: #2980b9; } .btn-red { background-color: #e74c3c; } 
        .btn:hover { opacity: 0.8; }
        .music-player { position: fixed; bottom: 30px; right: 30px; width: 55px; height: 55px; background-color: #2e8b57; color: white; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 24px; cursor: pointer; box-shadow: 0 4px 15px rgba(0,0,0,0.2); z-index: 1000; transition: transform 0.3s, background-color 0.3s; }
        .music-player:hover { transform: scale(1.1); background-color: #27ae60; }
        #bgMusic { display: none; }
        @media (max-width: 900px) { .chart-grid { grid-template-columns: 1fr; } .sidebar { position: absolute; height: 100vh; transform: translateX(-100%); } .sidebar.active { transform: translateX(0); } .main-content { width: 100%; } }
    </style>
</head>
<body>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h2>🌍 綠色冒險家</h2>
    </div>
    <div class="sidebar-menu">
        <a href="dashboard.php" class="active">🏠 大廳首頁</a>
        <?php if ($player_role === 'admin'): ?>
            <a href="admin_review.php">🛡️ 任務審核中心</a>
            <a href="admin_users.php">👥 管理使用者</a>
            <a href="admin_notify.php">📢 發送通知</a>
            <a href="admin_tasks.php">🎯 挑戰任務管理</a>
        <?php else: ?>
            <a href="submit_log.php">📖 填寫減碳日誌</a>
            <a href="my_records.php">📜 我的紀錄</a>
            <a href="leaderboard.php">🏆 英雄排行榜</a>
            <a href="social_feed.php">💬 環保動態牆</a>
        <?php endif; ?>
    </div>
</div>

<div class="main-content" id="main-content">
    
    <div class="topbar">
        <button class="toggle-btn" onclick="toggleSidebar()">☰</button>
        <div class="user-panel">
            <?php if ($player_role !== 'admin'): ?>
                <span style="font-size: 18px; font-weight: bold; color: #e67e22; margin-right: 15px;">💰 擁有碳幣：<?php echo $current_points; ?> 點</span>
            <?php else: ?>
                <span style="font-size: 16px; font-weight: bold; color: #8e44ad; margin-right: 15px;">🛡️ 系統管理員權限</span>
            <?php endif; ?>
            歡迎回來，<strong><?php echo htmlspecialchars($player_name); ?></strong> 
            <a href="logout.php" style="color: #e74c3c; margin-left: 10px; text-decoration: none; font-size: 14px;">安全登出</a>
        </div>
    </div>

    <div class="content-container">

        <?php if ($player_role !== 'admin'): ?>
            <div class="character-card">
                <div class="avatar-box">🧙‍♂️</div>
                <div class="info-box">
                    <div style="display: flex; justify-content: space-between; align-items: flex-end;">
                        <h3 style="margin: 0; font-size: 24px;">Lv. <?php echo $player_level; ?></h3>
                        <span style="font-weight: bold; background: rgba(255,255,255,0.2); padding: 3px 10px; border-radius: 12px; font-size: 14px;"><?php echo $title; ?></span>
                    </div>
                    <div class="progress-bg">
                        <div class="progress-fill" style="width: <?php echo $progress_percent; ?>%;"></div>
                    </div>
                    <div class="exp-text">EXP: <?php echo $player_exp; ?> / <?php echo $next_level_exp; ?></div>
                </div>
            </div>

            <div class="territory-card">
                <h3 style="margin-top: 0;">🌍 您的個人專屬領地：<span style="color: #2e8b57;"><?php echo $territory_status; ?></span></h3>
                <div class="evolution-tracker">
                    <div class="evo-line"></div>
                    <div class="evo-line-fill" style="width: <?php echo ($territory_stage == 1) ? '0%' : (($territory_stage == 2) ? '50%' : '100%'); ?>;"></div>
                    <div class="evo-step <?php echo ($territory_stage >= 1) ? ($territory_stage == 1 ? 'active' : 'passed') : ''; ?>">
                        <div class="evo-icon">🌵</div>
                        <div class="evo-text">荒蕪沙漠<br><small>(0+ 經驗值)</small></div>
                    </div>
                    <div class="evo-step <?php echo ($territory_stage >= 2) ? ($territory_stage == 2 ? 'active' : 'passed') : ''; ?>">
                        <div class="evo-icon">🌱</div>
                        <div class="evo-text">生機草地<br><small>(150+ 經驗值)</small></div>
                    </div>
                    <div class="evo-step <?php echo ($territory_stage >= 3) ? 'active' : ''; ?>">
                        <div class="evo-icon">🌳</div>
                        <div class="evo-text">茂密森林<br><small>(300+ 經驗值)</small></div>
                    </div>
                </div>
                <p style="font-size: 15px; color: #555; line-height: 1.6; margin-bottom: 20px;"><?php echo $territory_desc; ?></p>
                <?php if (!empty($territory_image)): ?>
                    <div style="background-color: #3e6d42; padding: 20px; border-radius: 15px; box-shadow: inset 0 4px 10px rgba(0,0,0,0.2);">
                        <img src="<?php echo htmlspecialchars($territory_image); ?>" alt="領地狀態" style="width: 100%; height: auto; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.3); display: block;">
                    </div>
                <?php endif; ?>
            </div>

            <div class="mall-section" style="margin-bottom: 30px; border-top: 4px solid #f39c12;">
                <div style="border-bottom: 2px solid #eee; padding-bottom: 15px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="margin: 0; color: #d35400;">🎯 每日挑戰任務</h3>
                    <span style="font-size: 13px; color: #7f8c8d;">完成專屬挑戰，賺取豐厚碳幣！</span>
                </div>
                <div>
                    <?php
                    // 🔒 PDO 撈取未過期的挑戰任務
                    try {
                        $today = date('Y-m-d');
                        $task_stmt = $pdo->prepare("SELECT * FROM challenge_tasks WHERE deadline >= :today ORDER BY deadline ASC LIMIT 4");
                        $task_stmt->execute([':today' => $today]);
                        
                        if ($task_stmt->rowCount() > 0):
                            while ($task = $task_stmt->fetch()):
                                $t_name = $task['task_name'] ?? $task['title'] ?? '未命名任務';
                                $t_desc = $task['description'] ?? $task['content'] ?? '暫無說明';
                                $t_points = $task['reward_points'] ?? $task['points'] ?? $task['reward'] ?? 10;
                    ?>
                    <div class="item-card" style="border-left: 5px solid #f39c12; text-align: left; width: 100%; display: flex; justify-content: space-between; align-items: center; box-sizing: border-box; margin-bottom: 15px;">
                        <div style="flex-grow: 1; padding-right: 15px;">
                            <h4 style="margin: 0 0 5px 0; color: #2c3e50; font-size: 16px;"><?php echo htmlspecialchars($t_name); ?></h4>
                            <p style="color: #7f8c8d; font-size: 13px; margin: 0; line-height: 1.4;">
                                <?php echo htmlspecialchars($t_desc); ?>
                            </p>
                        </div>
                        <div style="text-align: right; min-width: 100px;">
                            <div style="font-weight: bold; color: #e67e22; font-size: 16px; margin-bottom: 8px;">💰 +<?php echo $t_points; ?></div>
                            <a href="submit_log.php?task_id=<?php echo $task['id']; ?>" class="btn btn-green" style="font-size: 12px; padding: 6px 12px; text-decoration: none;">🚀 前往解鎖</a>
                        </div>
                    </div>
                    <?php 
                            endwhile; 
                        else: 
                    ?>
                    <div style="text-align: center; padding: 20px; color: #999; background: #fafafa; border-radius: 8px;">
                        <p style="margin: 0;">📭 目前公會還沒有發布新的挑戰任務喔！先去填寫一般的減碳日誌吧！</p>
                    </div>
                    <?php 
                        endif; 
                    } catch (PDOException $e) {
                        echo "<p style='color:red;'>無法讀取任務：{$e->getMessage()}</p>";
                    }
                    ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($player_role === 'admin'): ?>
            <div class="dashboard-section" style="background: white; padding: 25px; border-radius: 15px; box-shadow: 0 4px 10px rgba(0,0,0,0.05);">
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px dashed #ccc; padding-bottom: 15px;">
                    <h3 style="margin: 0;">📈 全站數據戰情室</h3>
                    <a href="export_excel.php" class="btn btn-green" style="font-size: 13px; padding: 6px 12px;">📥 匯出紀錄 (Excel)</a>
                </div>
                <div class="chart-grid">
                    <div id="chart2" class="chart-box"></div>
                    <div id="chart1" class="chart-box"></div>
                    <div id="chart3" class="chart-box"></div>
                    <div id="chart4" class="chart-box"></div>
                </div>
            </div>
        <?php endif; ?>

        <div class="mall-section">
            <div style="border-bottom: 2px solid #eee; padding-bottom: 15px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0;">🛒 碳幣福利商城</h3>
                <?php if ($player_role === 'admin'): ?>
                    <a href="admin_mall_add.php" class="btn btn-green">➕ 新增店家/商品</a>
                <?php endif; ?>
            </div>
            
            <?php if ($player_role !== 'admin'): ?>
                <p style="font-size: 14px; color: #666; margin-top: 0;">累積足夠的碳幣，即可直接兌換合作廠商的憑證！</p>
            <?php endif; ?>

            <div>
                <?php
                try {
                    // 🔒 PDO 查詢商城數量與種子資料
                    $check_mall = $pdo->query("SELECT COUNT(*) as count FROM mall_items");
                    $mall_count = $check_mall->fetch()['count'];

                    if ($mall_count == 0) {
                        $pdo->query("INSERT INTO mall_items (item_name, store_name, cost_points) VALUES
                            ('🥤 聯名馬卡龍環保杯', '最懂你的杯子', 150),
                            ('🛍️ 有機棉大容量環保袋', '無印綠色選品', 50),
                            ('☕ 燕麥奶免費升級券', 'GREEN BREW CAFE', 20)");
                    }

                    // 🔒 PDO 撈取商城清單
                    $mall_stmt = $pdo->query("SELECT * FROM mall_items ORDER BY id DESC");
                    while ($item = $mall_stmt->fetch()):
                ?>
                <div class="item-card">
                    <h4 style="margin-top: 5px;"><?php echo htmlspecialchars($item['item_name']); ?></h4>
                    <p style="color: #777; font-size: 12px; margin-bottom: 5px;">合作店家：<?php echo htmlspecialchars($item['store_name']); ?></p>
                    <p style="font-weight: bold; color: #e67e22; font-size: 18px; margin: 10px 0;">💰 <?php echo $item['cost_points']; ?> 碳幣</p>
                    
                    <?php if ($player_role === 'admin'): ?>
                        <div style="margin-top: 15px; border-top: 1px solid #eee; padding-top: 10px;">
                            <a href="admin_mall_edit.php?id=<?php echo $item['id']; ?>" class="btn btn-blue" style="font-size: 12px;">✏️ 修改</a>
                            <a href="admin_mall_delete.php?id=<?php echo $item['id']; ?>" class="btn btn-red" style="font-size: 12px;" onclick="return confirm('確定要下架刪除此商品嗎？');">🗑️ 刪除</a>
                        </div>
                    <?php else: ?>
                        <a href="export_coupon_pdf.php?item_id=<?php echo $item['id']; ?>" class="btn btn-red" style="font-size: 13px; display: block; margin-top: 15px;">🎁 點此兌換 (下載 PDF)</a>
                    <?php endif; ?>
                </div>
                <?php 
                    endwhile; 
                } catch (PDOException $e) {
                    echo "<p style='color:red;'>無法讀取商城資訊：{$e->getMessage()}</p>";
                }
                ?>
            </div>
        </div>

    </div>
</div>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        if (window.innerWidth <= 900) {
            sidebar.classList.toggle('active');
        } else {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
            <?php if ($player_role === 'admin'): ?>
            setTimeout(() => { if(typeof drawAllCharts === 'function') drawAllCharts(); }, 350);
            <?php endif; ?>
        }
    }
</script>

<audio id="bgMusic" loop>
    <source src="audio/bgm.mp3" type="audio/mpeg">
</audio>

<div class="music-player" onclick="toggleMusic()" title="點擊播放/暫停背景音樂">
    <span id="music-icon">🔇</span>
</div>

<script>
    var music = document.getElementById("bgMusic");
    var icon = document.getElementById("music-icon");
    var isPlaying = false;
    function toggleMusic() {
        if (isPlaying) { music.pause(); icon.innerText = "🔇"; } 
        else { music.play(); icon.innerText = "🎵"; }
        isPlaying = !isPlaying;
    }
</script>

</body>
</html>
<?php 
// 關閉 PDO 連線
$pdo = null; 
?>