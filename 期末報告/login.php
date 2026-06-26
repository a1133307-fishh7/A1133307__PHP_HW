<?php
session_start();

// 💡 引入機密設定檔 (裡面應該已經改寫為 PDO 連線，並產生 $pdo 物件)
require_once 'config.php';

$saved_email = "";
if (isset($_COOKIE['remember_email'])) {
    $saved_email = $_COOKIE['remember_email'];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $uEmail = $_POST['email'];
    $uPassword = $_POST['password'];
    $remember = isset($_POST['remember']) ? $_POST['remember'] : '';

    try {
        // ==========================================
        // 🔒 升級重點 1：使用 PDO 預處理語句來搜尋帳號
        // ==========================================
        // 先準備好 SQL 骨架，用 :email 當作安全佔位符
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
        
        // 執行查詢，並把真實的信箱變數綁定進去
        $stmt->execute([':email' => $uEmail]);

        // PDO 抓取資料的方式 (如果找不到會回傳 false)
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) { // 如果有抓到資料 (代表信箱存在)
            
            // 密碼比對邏輯不變，這原本就非常安全！
            if (password_verify($uPassword, $user['password'])) {
                
                // 🛑 關鍵安全修改：檢查使用者是否被停權
                if (isset($user['status']) && $user['status'] === 'suspended') {
                    echo "<script>alert('您的帳號目前已被停權，請聯絡管理員！'); window.location.href='login.php';</script>";
                    $pdo = null; // 斷開資料庫連線
                    exit; // 終止程式，不寫入 Session
                }
                
                // 登入成功，寫入 Session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['role'] = $user['role'];

                // ==========================================
                // 🔒 升級重點 2：使用 PDO 更新最後登入時間
                // ==========================================
                $update_stmt = $pdo->prepare("UPDATE users SET last_login_date = NOW() WHERE id = :id");
                $update_stmt->execute([':id' => $user['id']]);

                // 處理 Cookie
                if ($remember == 'on') {
                    setcookie('remember_email', $uEmail, time() + (86400 * 7), "/");
                } else {
                    setcookie('remember_email', '', time() - 3600, "/");
                }

                echo "<script>alert('登入成功！準備進入領地...'); window.location.href='dashboard.php';</script>";
            } else {
                echo "<script>alert('密碼錯誤，請再試一次！');</script>";
            }
        } else {
            echo "<script>alert('找不到這個帳號，請先註冊！');</script>";
        }
        
    } catch (PDOException $e) {
        // 如果執行 SQL 過程中發生錯誤，會被這裡捕捉並顯示
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
    <title>登入 - 綠色冒險家</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f9f4; padding: 50px; text-align: center; }
        form { background: white; max-width: 400px; margin: auto; padding: 30px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        input[type="email"], input[type="password"] { width: 90%; padding: 10px; margin: 10px 0; border: 1px solid #ccc; border-radius: 5px; }
        button { background-color: #2e8b57; color: white; border: none; padding: 12px 20px; border-radius: 5px; cursor: pointer; font-size: 16px; width: 100%; }
        button:hover { background-color: #246b43; }
    </style>
</head>
<body>
    <?php
        // 判斷網址有沒有 ?type=admin
        $is_admin_login = (isset($_GET['type']) && $_GET['type'] == 'admin');
    ?>

    <?php if($is_admin_login): ?>
        <h2 style="color: #2980b9;">🛡️ 地球守護者 - 後台登入</h2>
    <?php else: ?>
        <h2 style="color: #2e8b57;">🌍 登入冒險家公會</h2>
    <?php endif; ?>

    <form method="POST" action="login.php">
        <label>電子信箱：</label>
        <input type="email" name="email" value="<?php echo htmlspecialchars($saved_email); ?>" required><br>
        
        <label>輸入密碼：</label>
        <input type="password" name="password" required><br><br>
        
        <label>
            <input type="checkbox" name="remember" <?php if($saved_email != "") echo "checked"; ?>>
            記住我的帳號 (使用 Cookie)
        </label><br><br>
        
        <button type="submit" style="background-color: <?php echo $is_admin_login ? '#2980b9' : '#2e8b57'; ?>;">登入系統</button>
    </form>
    
    <?php if(!$is_admin_login): ?>
        <p>還不是冒險家嗎？ <a href="register.php" style="color: #2e8b57; font-weight: bold;">點此註冊</a></p>
    <?php else: ?>
        <p style="color: #7f8c8d; font-size: 14px;">此為高階權限通道，請使用管理員專屬帳號登入</p>
    <?php endif; ?>
</body>
</html>