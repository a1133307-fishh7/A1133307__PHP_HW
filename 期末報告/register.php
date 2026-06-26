<?php
session_start();

// 💡 引入機密設定檔 (抓取 PDO 資料庫物件 $pdo 與信箱密碼變數)
require_once 'config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $uName = trim($_POST['name']);
    $uEmail = trim($_POST['email']);
    $uPassword = $_POST['password'];
    
    // 密碼加密
    $hashed_password = password_hash($uPassword, PASSWORD_DEFAULT);

    try {
        // ==========================================
        // 🔒 升級重點 1：使用 PDO 檢查信箱是否重複
        // ==========================================
        $check_stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $check_stmt->execute([':email' => $uEmail]);

        // 如果 fetch() 有抓到東西，代表信箱已經存在
        if ($check_stmt->fetch()) {
            echo "<script>alert('註冊失敗，這個 Email 已經被註冊過了喔！請直接登入。'); window.location.href='login.php';</script>";
        } else {
            
            // ==========================================
            // 🔒 升級重點 2：使用 PDO 安全寫入新玩家資料
            // ==========================================
            $insert_stmt = $pdo->prepare("INSERT INTO users(name, email, password) VALUES(:name, :email, :password)");
            
            // 將玩家資料綁定進保護箱，然後執行寫入
            $is_success = $insert_stmt->execute([
                ':name' => $uName,
                ':email' => $uEmail,
                ':password' => $hashed_password
            ]);

            // 如果資料庫寫入成功
            if ($is_success) {
                
                // 💡 啟動 PHPMailer 寄出歡迎信
                $mail = new PHPMailer(true);

                try {
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com'; 
                    $mail->SMTPAuth   = true; 
                    
                    // 💡 自動抓取 config.php 裡面的設定，避免寫死在檔案裡
                    $mail->Username   = $smtp_user; 
                    $mail->Password   = $smtp_pass; 
                    
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587; 
                    $mail->CharSet    = 'UTF-8';

                    $mail->setFrom($smtp_user, $system_name);
                    $mail->addAddress($uEmail, $uName);

                    $mail->isHTML(true);
                    $mail->Subject = '🌱 歡迎加入綠色冒險家！展開你的減碳旅程';
                    $mail->Body = "
                        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
                            <h2 style='color: #2e8b57;'>您好，冒險者 {$uName}！</h2>
                            <p>恭喜您成功註冊【綠色冒險家】！您的帳號是：<strong>{$uEmail}</strong></p>
                            <p>我們是一個將減碳行為轉化為遊戲冒險的平台。從今天起，您可以透過紀錄日常的環保行為來累積積分、升級專屬角色，並在碳幣商城兌換合作店家的專屬優惠！</p>
                            <a href='http://greenadventurer.free.nf/login.php' style='display: inline-block; padding: 10px 20px; background-color: #2e8b57; color: white; text-decoration: none; border-radius: 5px;'>登入展開冒險</a>
                        </div>
                    ";

                    $mail->send();
                    echo "<script>alert('✅ 註冊成功！一封歡迎信已經寄到你的信箱囉！'); window.location.href='login.php';</script>";
                    
                } catch (Exception $e) {
                    echo "<script>alert('帳號註冊成功！但歡迎信發送失敗。錯誤原因: {$mail->ErrorInfo}'); window.location.href='login.php';</script>";
                }

            } else {
                echo "<script>alert('資料庫寫入發生異常！請重新嘗試。');</script>";
            }
        }
    } catch (PDOException $e) {
        die("系統發生錯誤：" . $e->getMessage());
    }
    
    // 關閉資料庫連線
    $pdo = null;
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>註冊 - 綠色冒險家</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f9f4; padding: 50px; text-align: center; }
        form { background: white; max-width: 400px; margin: auto; padding: 30px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        input[type="text"], input[type="email"], input[type="password"] { width: 90%; padding: 10px; margin: 10px 0; border: 1px solid #ccc; border-radius: 5px; }
        button { background-color: #2e8b57; color: white; border: none; padding: 12px 20px; border-radius: 5px; cursor: pointer; font-size: 16px; width: 100%; }
        button:hover { background-color: #246b43; }
    </style>
</head>
<body>
    <h2>🌱 成為綠色冒險家</h2>
    <form method="POST" action="register.php">
        <label>玩家暱稱：</label>
        <input type="text" name="name" required><br>
        <label>電子信箱：</label>
        <input type="email" name="email" required><br>
        <label>設定密碼：</label>
        <input type="password" name="password" required><br><br>
        <button type="submit">開始冒險 (註冊)</button>
    </form>
    <p>已經有帳號了嗎？ <a href="login.php" style="color: #2e8b57; font-weight: bold;">點此登入</a></p>
</body>
</html>