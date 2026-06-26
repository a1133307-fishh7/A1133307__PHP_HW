<?php
session_start(); // 💡 核心關鍵：必須先啟動，伺服器才能找到該使用者的 Session 盒子！

$_SESSION = array(); // 1. 清空記憶體中所有的 Session 變數值

// 2. 徹底清除瀏覽器端的 Session Cookie (自由選擇，但加上更完美)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy(); // 3. 徹底銷毀、刪除伺服器硬碟中的 Session 實體檔案

// 4. 清除完畢後，安全導向登入頁面
header("Location: login.php");
exit;