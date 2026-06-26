<?php
session_start();
// 💡 引入機密設定檔 (確保裡面已經有 $pdo 物件)
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

try {
    // ==========================================
    // 🔒 1. 確保是管理員 (使用 PDO 預處理語句)
    // ==========================================
    $admin_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = :id");
    $stmt->execute([':id' => $admin_id]);
    $user_data = $stmt->fetch();

    if (!$user_data || $user_data['role'] !== 'admin') {
        exit('權限不足');
    }

    // ==========================================
    // 處理表單送出 (真實發信版)
    // ==========================================
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_notice'])) {
        $notice_type = $_POST['notice_type'];
        $target_email = $_POST['target_email'];
        $message = $_POST['message'];

        // 引入 PHPMailer
        require_once 'PHPMailer/src/Exception.php';
        require_once 'PHPMailer/src/PHPMailer.php';
        require_once 'PHPMailer/src/SMTP.php';

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);

        try {
            // SMTP 伺服器設定
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtp_user; 
            $mail->Password   = $smtp_pass; 
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;
            $mail->CharSet    = 'UTF-8';

            // 設定寄件人
            $mail->setFrom($smtp_user, $system_name);
            $mail->Subject = "【綠色冒險家公會】" . $notice_type;
            $mail->isHTML(true);

            $success_count = 0;

            if ($target_email === 'all') {
                // ==========================================
                // 🔒 2. 群發邏輯：使用 PDO 撈取一般玩家
                // ==========================================
                $stmt_users = $pdo->query("SELECT name, email FROM users WHERE role = 'user'");
                
                while ($row = $stmt_users->fetch()) {
                    $mail->addAddress($row['email'], $row['name']);
                    
                    // 客製化信件內容
                    $mail->Body = "親愛的冒險者 <strong>" . htmlspecialchars($row['name']) . "</strong> 您好：<br><br>" . nl2br(htmlspecialchars($message)) . "<br><br>綠色冒險家公會 敬上";
                    
                    $mail->send();
                    $success_count++;
                    
                    // ⚠️ 超級關鍵：寄完這圈，一定要清除收件人
                    $mail->clearAddresses(); 
                }
                $success_msg = "✅ 已成功向【全站 $success_count 位冒險者】真實發送 $notice_type 通知！";

            } else {
                // 單一寄送邏輯
                $mail->addAddress($target_email);
                $mail->Body = "親愛的冒險者您好：<br><br>" . nl2br(htmlspecialchars($message)) . "<br><br>綠色冒險家公會 敬上";
                $mail->send();
                $success_msg = "✅ 已成功向【$target_email】發送 $notice_type 通知！";
            }

            echo "<script>alert('$success_msg'); window.location.href='admin_notify.php';</script>";

        } catch (Exception $e) {
            $error_msg = addslashes($mail->ErrorInfo);
            echo "<script>alert('❌ 郵件發送失敗，原因：$error_msg');</script>";
        }
    }
} catch (PDOException $e) {
    die("系統資料庫發生錯誤：" . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>發送通知 - 綠色冒險家公會</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #ecf0f1; padding: 20px; }
        .container { max-width: 600px; margin: auto; background: white; padding: 30px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; }
        .btn-submit { background-color: #8e44ad; color: white; border: none; padding: 12px; border-radius: 5px; cursor: pointer; font-weight: bold; width: 100%; }
        .btn-back { display: inline-block; margin-bottom: 20px; padding: 10px 15px; background: #95a5a6; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; }
    </style>
</head>
<body>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <a href="dashboard.php" class="btn-back">⬅ 返回大廳</a>
        <a href="cron_mail.php?demo=1" target="_blank" style="background-color: #f39c12; color: white; padding: 10px 15px; border-radius: 5px; text-decoration: none; font-weight: bold; font-size: 14px;">⚙️ 執行排程測試 (Demo)</a>
    </div>
    
    <h2>📢 發送系統推播 / Email 通知</h2>

    <form method="POST" action="admin_notify.php">
        <div class="form-group">
            <label>發送對象</label>
            <select name="target_email">
                <option value="all">🌍 全站所有冒險者 (群發)</option>
                
                <optgroup label="--- 或選擇指定單一冒險者 ---">
                <?php
                try {
                    // ==========================================
                    // 🔒 3. 下拉選單：使用 PDO 撈取玩家選單
                    // ==========================================
                    $stmt_list = $pdo->query("SELECT name, email FROM users WHERE role = 'user'");
                    
                    while ($u = $stmt_list->fetch()) {
                        // 加上 htmlspecialchars 避免 XSS 攻擊
                        echo "<option value='" . htmlspecialchars($u['email']) . "'>👤 " . htmlspecialchars($u['name']) . " (" . htmlspecialchars($u['email']) . ")</option>";
                    }
                } catch (PDOException $e) {
                    echo "<option value=''>無法載入玩家列表</option>";
                }
                ?>
                </optgroup>
            </select>
        </div>

        <div class="form-group">
            <label>通知類型</label>
            <select name="notice_type">
                <option value="🎯 任務提醒">🎯 任務提醒 (提醒未解任務)</option>
                <option value="💰 點數通知">💰 點數通知 (點數入帳/扣除)</option>
                <option value="⚠️ 系統公告">⚠️ 系統公告</option>
            </select>
        </div>

        <div class="form-group">
            <label>信件詳細內容</label>
            <textarea name="message" rows="5" required placeholder="親愛的冒險者，您的碳幣已入帳..."></textarea>
        </div>

        <button type="submit" name="send_notice" class="btn-submit">🚀 立即發送通知信</button>
    </form>
</div>

</body>
</html>
<?php
// 安全關閉 PDO 連線
$pdo = null;
?>