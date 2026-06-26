<?php
session_start();
// 這裡不需要登入門禁，因為這是公開的專案介紹頁面，任何人（包括教授）都可以直接從首頁點進來看！
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>專案與團隊介紹 - 綠色冒險家</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f0f4f8;
            margin: 0;
            padding: 40px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .container {
            width: 100%;
            max-width: 850px;
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
        }
        .btn-back {
            display: inline-block;
            padding: 10px 20px;
            background-color: #95a5a6;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            transition: 0.3s;
            margin-bottom: 20px;
        }
        .btn-back:hover { background-color: #7f8c8d; }
        
        h1 { color: #2e8b57; font-size: 32px; margin-top: 10px; border-bottom: 3px solid #2e8b57; padding-bottom: 10px; }
        h2 { color: #34495e; font-size: 22px; margin-top: 30px; display: flex; align-items: center; gap: 8px; }
        p { color: #555; line-height: 1.7; font-size: 16px; }
        
        /* 特色區塊樣式 */
        .feature-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 15px; }
        .feature-card { background: #f8f9fa; border-left: 4px solid #27ae60; padding: 15px; border-radius: 0 8px 8px 0; }
        .feature-card strong { color: #2c3e50; font-size: 16px; }
        .feature-card p { font-size: 14px; margin: 5px 0 0 0; color: #666; }

        /* 👥 團隊成員卡片樣式 */
        .team-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px; }
        .member-card {
            background: linear-gradient(135deg, #ffffff, #f9fbf9);
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.02);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .member-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(46, 139, 87, 0.1);
            border-color: #2e8b57;
        }
        .member-avatar { font-size: 45px; margin-bottom: 10px; }
        .member-name { font-size: 18px; font-weight: bold; color: #2c3e50; margin: 5px 0; }
        .member-id { font-size: 14px; color: #7f8c8d; font-family: monospace; margin-bottom: 15px; display: block; }
        .member-job {
            background: #e8f5e9; color: #2e8b57; padding: 4px 12px; 
            border-radius: 20px; font-size: 13px; font-weight: bold; display: inline-block;
        }
        
        @media (max-width: 650px) {
            .feature-grid, .team-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="container">
    <a href="index.php" class="btn-back">⬅ 返回首頁大門</a>
    
    <h1>🌍 關於「綠色冒險家」專案</h1>
    
    <h2>💡 專案核心理念</h2>
    <p>
        在全球暖化與氣候變遷日益嚴重的今天，「減碳環保」已成為每位地球公民的重要課題。然而，傳統的環保宣導往往流於枯燥的口號，難以激起大眾持續實踐的熱情。
    </p>
    <p>
        因此，我們開發了<b>【綠色冒險家】</b>平台，將<b>「環保減碳」與「遊戲化機制」深度結合</b>。我們將現實中的環保行為轉化為虛擬世界中的冒險任務，讓玩家在幫地球減碳的同時，能夠累積經驗值升級，並親眼見證自己的專屬領地從「荒蕪沙漠」逐步進化為「茂密森林」。透過遊戲化的趣味反饋與成就感，培養大眾長期的環保習慣。
    </p>

    <h2>✨ 系統核心特色</h2>
    <div class="feature-grid">
        <div class="feature-card">
            <strong>📖 減碳日誌與科學計算機</strong>
            <p>紀錄日常環保足跡（如自行車通勤），系統自動試算減碳公斤數，兼具實用與量化科技感。</p>
        </div>
        <div class="feature-card">
            <strong>🌍 個人領地動態進化視覺</strong>
            <p>根據玩家擁有的碳幣多寡，主廳背景與進化狀態條會即時動態切換，打造沉浸式養成體驗。</p>
        </div>
        <div class="feature-card">
            <strong>💬 環保社群動態牆</strong>
            <p>串聯全站冒險者，公開展示已通過審核的環保行動與照片，並支援同儕相互按讚給予愛心鼓勵。</p>
        </div>
        <div class="feature-card">
            <strong>🛒 碳幣福利商城</strong>
            <p>實作與合作店家串聯的商業模式，扣除碳幣即可透過 TCPDF 套件動態產出帶有專屬序號的 PDF 兌換券。</p>
        </div>
        <div class="feature-card">
            <strong>📊 管理員全站數據戰情室</strong>
            <p>利用 Google Charts 渲染四大圖表，直觀掌握每日行為分佈、減碳趨勢、審核率與玩家活躍度。</p>
        </div>
        <div class="feature-card">
            <strong>🛡️ 完整安全與防弊機制</strong>
            <p>密碼全面採用雜湊加密，嚴格實作後台人工照片審核與停權系統，確保減碳數據真實不灌水。</p>
        </div>
    </div>

    <h2>👥 網頁開發團隊與分工</h2>
    <div class="team-grid">
        <div class="member-card">
            <div class="member-avatar">👩‍💻</div>
            <div class="member-name">柯嘉瑜</div>
            <span class="member-id">學號: A1133307</span>
            <div class="member-job">後端核心邏輯、系統架構與資安防護</div>
            <p style="font-size: 13px; color: #7f8c8d; text-align: left; margin-top: 15px;">
                💡 負責建立機密安全設定檔中心、PHPMailer 真實郵件開關串接、資料庫結構重建優化、大廳介面動態分流與登入安全防弊阻擋機制。
            </p>
        </div>
        
        <div class="member-card">
            <div class="member-avatar">👩‍🎨</div>
            <div class="member-name">黃苡甄</div>
            <span class="member-id">學號: A1133355</span>
            <div class="member-job">前端使用者體驗、互動 UI 與資料視覺化</div>
            <p style="font-size: 13px; color: #7f8c8d; text-align: left; margin-top: 15px;">
                💡 負責設計精美自適應動態進化追蹤條、DataTransfer 多圖分批異步上傳預覽區塊、BGM 懸浮開關元件與後台戰情室 RWD 圖表自適應縮放調整。
            </p>
        </div>
    </div>

</div>

</body>
</html>