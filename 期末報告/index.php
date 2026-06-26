<?php
session_start();

// 💡 智慧判斷：如果系統發現你「已經登入過」了，就不讓你停在首頁，直接送你進大廳
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

// ⚠️ 注意：把原本無條件跳轉到 login.php 的那行刪掉了！
// 這樣如果玩家「還沒登入」，伺服器就會繼續往下讀取，把下面的漂亮網頁顯示出來。
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>綠色冒險家 - 最佳的減碳遊戲化平台</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f0f4f8; 
            margin: 0;
            padding: 50px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .header-title {
            text-align: left;
            width: 100%;
            max-width: 800px;
            margin-bottom: 40px;
        }
        .header-title p {
            color: #555;
            font-size: 18px;
            margin: 0 0 5px 0;
        }
        .header-title h1 {
            color: #3b4b5e;
            font-size: 42px;
            margin: 0;
            letter-spacing: 2px;
        }
       
        /* 卡片外觀設計 */
        .portal-card {
            background-color: white;
            width: 100%;
            max-width: 800px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            display: flex;
            margin-bottom: 25px;
            overflow: hidden;
        }
       
        /* 卡片左側：圖文介紹 */
        .card-left {
            flex: 7;
            display: flex;
            align-items: center;
            padding: 30px;
        }
        .card-icon {
            font-size: 50px;
            margin-right: 25px;
            background: #e8f5e9;
            width: 90px;
            height: 90px;
            display: flex;
            justify-content: center;
            align-items: center;
            border-radius: 20px;
        }
        .icon-blue { background: #e3f2fd; } 
       
        .card-text h2 {
            color: #2980b9;
            margin: 0 0 8px 0;
            font-size: 24px;
        }
        .card-text p {
            color: #7f8c8d;
            margin: 0;
            font-size: 15px;
            line-height: 1.5;
        }

        /* 卡片右側：操作按鈕區 */
        .card-right {
            flex: 3;
            border-left: 1px solid #eee;
            display: flex;
            flex-direction: column;
        }
        .card-right a {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            text-decoration: none;
            color: #3498db;
            font-size: 16px;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        /* 上半部按鈕加底線分隔 */
        .card-right a:first-child {
            border-bottom: 1px solid #eee;
        }
        .card-right a:hover {
            background-color: #f7f9fa;
            color: #2980b9;
        }
       
        /* 為登入按鈕做一點視覺強化 */
        .login-btn {
            color: #2e8b57 !important;
        }
    </style>
</head>
<body>

    <div class="header-title">
        <p>最佳的減碳遊戲化平台</p>
        <h1>歡迎光臨 綠色冒險家</h1>
    </div>

    <div class="portal-card">
        <div class="card-left">
            <div class="card-icon">🌿</div>
            <div class="card-text">
                <h2>一般冒險者</h2>
                <p>記錄每日環保行為，累積碳幣與經驗值<br>讓專屬領地從沙漠進化為茂密森林</p>
            </div>
        </div>
        <div class="card-right">
            <a href="register.php">註冊帳號</a>
            <a href="login.php" class="login-btn">冒險者登入</a>
        </div>
    </div>

    <div class="portal-card">
        <div class="card-left">
            <div class="card-icon icon-blue">🛡️</div>
            <div class="card-text">
                <h2 style="color: #34495e;">地球守護者 (管理員)</h2>
                <p>審核玩家減碳任務，發放獎勵積分<br>視覺化掌握全站數據報表與系統管理</p>
            </div>
        </div>
        <div class="card-right">
            <a href="about.php" style="color: #2980b9; background-color: #f7f9fa;">💡 瀏覽專案與團隊介紹</a>
            <a href="login.php?type=admin" style="color: #34495e;">管理員登入</a>
        </div>
    </div>

</body>
</html>