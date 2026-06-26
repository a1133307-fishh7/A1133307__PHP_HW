<?php
session_start();

// 引入機密設定檔 (已包含 $pdo 連線)
require_once 'config.php';

// 1. 權限檢查：只有管理員可以匯出全站資料
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    exit('權限不足，無法匯出資料。');
}

// 2. 撈取資料 (移到前面，避免出錯時破壞了 HTTP Header 或 Excel 結構)
$records = [];
try {
    // 準備 SQL 語法 (利用 JOIN 綁定 users 資料表，才能抓到玩家暱稱)
    $sql = "SELECT tr.id, u.name AS user_name, tr.action_type, tr.co2_saved, tr.description, tr.status, tr.created_at
            FROM task_records tr
            LEFT JOIN users u ON tr.user_id = u.id
            ORDER BY tr.id DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("資料查詢失敗，請聯繫管理員: " . $e->getMessage());
}

// 3. 設定 HTTP Header，告訴瀏覽器這是一個 Excel 檔案
// 💡 小優化：讓檔名自動押上當天的日期，看起來更專業！
$export_filename = "Green_Adventurer_Records_" . date('Ymd') . ".xls";

header("Pragma: public");
header("Expires: 0");
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Content-type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: inline; filename="' . $export_filename . '";');
header('Content-Transfer-Encoding: binary');
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <style>
        /* 替 Excel 的表格加上簡單的樣式 */
        table { border-collapse: collapse; }
        th { background-color: #2e8b57; color: white; font-weight: bold; }
        td, th { border: 1px solid #000000; padding: 5px; text-align: center; }
        .text-left { text-align: left; }
    </style>
</head>
<body>

<table>
    <tr>
        <th>紀錄 ID</th>
        <th>冒險者暱稱</th>
        <th>環保行為類型</th>
        <th>減少碳排 (kg)</th>
        <th>玩家描述</th>
        <th>審核狀態</th>
        <th>提交時間</th>
    </tr>

<?php
// 狀態翻譯對照表
$status_map = [
    'pending' => '待審核',
    'approved' => '已通過',
    'rejected' => '已退回'
];

// 4. 迴圈印出每一筆資料
if (count($records) > 0) {
    foreach ($records as $row) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row["id"]) . "</td>";
        echo "<td>" . htmlspecialchars($row["user_name"] ?? '未知冒險者') . "</td>";
        echo "<td>" . htmlspecialchars($row["action_type"]) . "</td>";
        echo "<td>" . htmlspecialchars($row["co2_saved"]) . "</td>";
        
        // 描述可能比較長，設定靠左對齊
        echo "<td class='text-left'>" . htmlspecialchars($row["description"]) . "</td>";
        
        // 轉換狀態為中文
        $status_zh = isset($status_map[$row["status"]]) ? $status_map[$row["status"]] : htmlspecialchars($row["status"]);
        echo "<td>" . $status_zh . "</td>";
        
        echo "<td>" . htmlspecialchars($row["created_at"]) . "</td>";
        echo "</tr>";
    }
} else {
    // 防呆機制：如果系統剛上線完全沒資料時，至少要有個提示
    echo "<tr><td colspan='7'>目前尚無任務資料</td></tr>";
}
?>

</table>

<?php 
// 釋放資源
$pdo = null; 
?>

</body>
</html>