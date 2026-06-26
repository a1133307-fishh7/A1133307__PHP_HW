<?php
session_start();
require_once 'config.php';

// 1. 檢查登入狀態
if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('請先登入！'); window.location.href='login.php';</script>";
    exit;
}

date_default_timezone_set('Asia/Taipei');
require_once 'TCPDF/tcpdf.php';

if (!isset($_GET['item_id'])) {
    echo "<script>alert('無效的操作！'); window.location.href='dashboard.php';</script>";
    exit;
}

$item_id = (int)$_GET['item_id'];
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

try {
    // 💡 升級 PDO：取得商品資訊
    $stmt_item = $pdo->prepare("SELECT * FROM mall_items WHERE id = :id");
    $stmt_item->execute([':id' => $item_id]);
    $item = $stmt_item->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        echo "<script>alert('找不到該商品！'); window.location.href='dashboard.php';</script>";
        exit;
    }
    
    $cost = $item['cost_points'];
    $item_name = $item['item_name'];
    $store_name = $item['store_name'];

    // 💡 升級 PDO：取得玩家當前碳幣
    $stmt_user = $pdo->prepare("SELECT points FROM users WHERE id = :uid");
    $stmt_user->execute([':uid' => $user_id]);
    $user = $stmt_user->fetch(PDO::FETCH_ASSOC);
    $current_points = $user['points'];

    if ($current_points < $cost) {
        echo "<script>alert('碳幣餘額不足喔！'); window.location.href='dashboard.php';</script>";
        exit;
    }

    // 💡 升級 PDO：扣除碳幣
    $new_points = $current_points - $cost;
    $stmt_update = $pdo->prepare("UPDATE users SET points = :new_points WHERE id = :uid");
    $stmt_update->execute([':new_points' => $new_points, ':uid' => $user_id]);

} catch (PDOException $e) {
    die("交易處理失敗，請稍後再試或聯絡管理員：" . $e->getMessage());
}

// ==========================================
// 8. 開始產生 PDF 兌換憑證
// ==========================================
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('綠色冒險家公會');
$pdf->SetTitle('兌換憑證 - ' . $item_name);

$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(15, 20, 15);
$pdf->SetAutoPageBreak(TRUE, 15);
$pdf->AddPage();
$pdf->SetFont('msungstdlight', '', 12);

// ✨ 優化 1：把秒數拿掉，讓時間變短，避免換行跑版
$redeem_date = date('Y-m-d H:i');
$expire_date = date('Y-m-d', strtotime('+14 days')); 
$serial_number = "GA" . date('Ymd') . str_pad($user_id, 3, '0', STR_PAD_LEFT) . str_pad($item_id, 3, '0', STR_PAD_LEFT);

$style = array(
    'position' => '',
    'align' => 'C',
    'stretch' => false,
    'fitwidth' => true,
    'cellfitalign' => '',
    'border' => false,
    'hpadding' => 'auto',
    'vpadding' => 'auto',
    'fgcolor' => array(0,0,0),
    'bgcolor' => array(244,249,244),
    'text' => true,
    'font' => 'helvetica',
    'fontsize' => 9,
    'stretchtext' => 4
);

$file_name = "Eco_Coupon_" . date('Ymd') . ".pdf";

// ✨ 優化 2：將內部表格寬度調為 70%，並設定左欄 35%、右欄 65%，確保文字有足夠空間
// ✨ 優化 3：在底部加入更多 <br>，將綠色外框撐開
$html = <<<EOD
<table width="100%" cellpadding="15" style="background-color: #f4f9f4; border: 2px solid #2e8b57; text-align: center;">
    <tr>
        <td>
            <br>
            <h1 style="color: #2e8b57; font-size: 24px; letter-spacing: 2px;">綠色冒險家 - 專屬兌換憑證</h1>
            <br>
            <table width="80%" align="center" style="border-top: 2px dashed #bdc3c7;"><tr><td></td></tr></table>
            <br>
            <h2 style="color: #333; font-size: 20px;">兌換項目： <span style="color: #e67e22;">{$item_name}</span></h2>
            <h3 style="color: #555; font-size: 16px;">合作店家： {$store_name}</h3>
            <br>
            
            <table width="70%" align="center" cellpadding="6" style="font-size: 14px; line-height: 1.8;">
                <tr>
                    <td width="35%" align="right" style="color: #2c3e50;"><strong>冒險者姓名：</strong></td>
                    <td width="65%" align="left" style="color: #34495e;">{$user_name}</td>
                </tr>
                <tr>
                    <td align="right" style="color: #2c3e50;"><strong>兌換時間：</strong></td>
                    <td align="left" style="color: #34495e;">{$redeem_date}</td>
                </tr>
                <tr>
                    <td align="right" style="color: #2c3e50;"><strong>有效期限：</strong></td>
                    <td align="left" style="color: #d35400;"><strong>{$expire_date} 23:59 (14天內)</strong></td>
                </tr>
                <tr>
                    <td align="right" style="color: #2c3e50;"><strong>消耗碳幣：</strong></td>
                    <td align="left" style="color: #34495e;">-{$cost} 點</td>
                </tr>
            </table>
            <br>
            <p style="font-size: 12px; color: #7f8c8d;">(請於店家結帳時，出示下方憑證條碼進行兌換)</p>
            <br><br><br><br><br><br><br><br><br><br>
        </td>
    </tr>
</table>
EOD;

$pdf->writeHTML($html, true, false, true, false, '');

// ✨ 優化 4：將條碼的 Y 坐標從 165 往下推到 180，徹底避開上方的提示文字！
$pdf->write1DBarcode($serial_number, 'C128', 55, 180, 100, 18, 0.4, $style, 'N');

// 確保沒有多餘字元影響 PDF 輸出
if (ob_get_contents()) ob_end_clean();
$pdf->Output($file_name, 'D');
exit;
?>