<?php
session_start();
require_once 'config.php'; 

// 檢查是否為管理員
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    exit('權限不足，無法執行刪除。');
}

// 確認網址有帶 id 過來
if (isset($_GET['id'])) {
    $item_id = (int)$_GET['id'];
    
    try {
        // 使用 PDO 預處理語句執行刪除，防止 SQL Injection
        $stmt = $pdo->prepare("DELETE FROM mall_items WHERE id = :id");
        $stmt->execute([':id' => $item_id]);
        
        echo "<script>alert('🗑️ 商品已成功下架刪除！'); window.location.href='dashboard.php';</script>";
        
    } catch (PDOException $e) {
        // 捕捉例外並顯示友善錯誤
        die("商品刪除失敗，請聯絡系統管理員：" . $e->getMessage());
    }
} else {
    // 如果沒有帶 id 就跑來這頁，直接踢回大廳
    header("Location: dashboard.php");
}

exit;
?>