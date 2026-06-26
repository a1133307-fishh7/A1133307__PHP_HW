<?php
// 這個檔案沒有 HTML，專門用來處理背景發信邏輯
require_once 'config.php'; 

// 引入 PHPMailer (確保路徑正確)
require_once 'PHPMailer/src/Exception.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';

// 💡 【Demo 模式開關】
$is_demo = (isset($_GET['demo']) && $_GET['demo'] == '1');

if ($is_demo) {
    echo "<h2 style='color: red;'>⚠️ 目前為 Demo 展示模式 (強制觸發條件) ⚠️</h2><hr>";
} else {
    echo "啟動自動郵件系統檢查...<br><hr>";
}

// ==========================================
// 準備 PHPMailer 發信引擎
// ==========================================
$mail = new PHPMailer\PHPMailer\PHPMailer(true);

try {
    // 共用的 SMTP 伺服器設定
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtp_user; 
    $mail->Password   = $smtp_pass; 
    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = 465;
    $mail->CharSet    = 'UTF-8';
    $mail->setFrom($smtp_user, $system_name);
    $mail->isHTML(true);

    // ==========================================
    // 邏輯 1：回訪提醒信 (正常: 3天 / Demo: 1分鐘)
    // ==========================================
    $time_condition = $is_demo ? strtotime('-1 minutes') : strtotime('-3 days');
    $check_date = date('Y-m-d H:i:s', $time_condition);

    // 💡 PDO 升級：使用 :check_date 作為參數綁定
    $sql_inactive = "SELECT name, email FROM users WHERE (last_login_date < :check_date OR last_login_date IS NULL) AND role = 'user'";
    $stmt_inactive = $pdo->prepare($sql_inactive);
    $stmt_inactive->execute([':check_date' => $check_date]);
    $inactive_users = $stmt_inactive->fetchAll(PDO::FETCH_ASSOC);

    echo "<h3>🔍 檢查未登入名單 (" . ($is_demo ? "超過 1 分鐘" : "超過 3 天") . ")：</h3>";
    if (count($inactive_users) > 0) {
        foreach ($inactive_users as $user) {
            echo "準備發送【回訪提醒信】給：{$user['name']} ({$user['email']}) ... ";
            
            // 清除上一位收件人，加入新收件人
            $mail->clearAddresses();
            $mail->addAddress($user['email'], $user['name']);
            
            $mail->Subject = "【綠色冒險家】冒險者，你的領地需要你！";
            $mail->Body = "親愛的 <strong>{$user['name']}</strong>：<br><br>你已經有一段時間沒有回到公會了！<br>你的專屬領地需要你的照顧，快回來完成減碳任務，賺取碳幣讓領地進化成茂密森林吧！<br><br>綠色冒險家公會 敬上";
            
            // 執行真實寄信
            if($mail->send()) {
                echo "<span style='color:green; font-weight:bold;'>✅ 寄送成功！</span><br>";
            }
        }
    } else {
        echo "太棒了，所有冒險者最近都有上線！<br>";
    }

    // ==========================================
    // 邏輯 2：減碳總結報告 (正常: 星期日 / Demo: 強制執行)
    // ==========================================
    echo "<h3>📊 檢查每週減碳報告發送條件：</h3>";

    if (date('w') == 0 || $is_demo) { 
        echo ($is_demo ? "【Demo 模式】強制啟動" : "今天是星期日，準備執行") . "【減碳總結報告】大批次寄送程序...<br><br>";
        
        // 💡 PDO 升級：直接執行並抓取所有 user
        $sql_all_users = "SELECT id, name, email, points, level, exp FROM users WHERE role = 'user'";
        $stmt_all = $pdo->prepare($sql_all_users);
        $stmt_all->execute();
        $all_users = $stmt_all->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($all_users as $user) {
            echo "發送週報給：{$user['name']} ({$user['email']}) ... ";
            
            // 清除上一位收件人，加入新收件人
            $mail->clearAddresses();
            $mail->addAddress($user['email'], $user['name']);
            
            $mail->Subject = "【綠色冒險家】您的減碳結算報告出爐囉！";
            
            // 🌟 真實變數數據排版
            $mail->Body = "
            <div style='font-family: sans-serif; line-height: 1.6; color: #333;'>
                親愛的 <strong>{$user['name']}</strong> 冒險者：<br><br>
                這是您最新的減碳結算報告！以下是您目前在公會中的輝煌戰績：<br><br>
                
                <div style='background-color: #f4f9f4; padding: 20px; border-radius: 10px; border: 2px solid #2e8b57; width: 80%; max-width: 400px;'>
                    <h3 style='margin-top: 0; color: #2e8b57;'>📊 您的專屬戰績表</h3>
                    🌱 <strong>目前等級：</strong> Level {$user['level']}<br>
                    ✨ <strong>累積經驗值：</strong> {$user['exp']} exp<br>
                    💰 <strong>擁有碳幣：</strong> <span style='color: #e67e22; font-weight: bold; font-size: 18px;'>{$user['points']} 點</span>
                </div>
                
                <br>感謝您這週為了地球的付出，每一次的環保行動都在讓世界變得更好，請繼續保持！<br><br>
                綠色冒險家公會 敬上
            </div>";
            
            // 執行真實寄信
            if($mail->send()) {
                echo "<span style='color:green; font-weight:bold;'>✅ 寄送成功！(包含真實戰績)</span><br>";
            }
        }
    } else {
        echo "今天不是週日，跳過總結報告發送。<br>";
    }

} catch (PDOException $e) {
    // 捕捉資料庫相關的錯誤
    echo "<h3 style='color:red;'>資料庫連線或查詢發生錯誤：{$e->getMessage()}</h3>";
} catch (Exception $e) {
    // 捕捉 PHPMailer 發信過程的錯誤
    echo "<h3 style='color:red;'>發信系統發生錯誤：{$mail->ErrorInfo}</h3>";
}

// 釋放資料庫資源
$pdo = null;
?>