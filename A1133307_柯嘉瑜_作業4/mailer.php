<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

$phpmailerBase = __DIR__ . '/PHPMailer/src/';
$candidates = [
    $phpmailerBase,
    __DIR__ . '/phpmailer/src/',
    __DIR__ . '/PHPMailer-master/src/',
    __DIR__ . '/vendor/phpmailer/phpmailer/src/',
];
foreach ($candidates as $candidate) {
    if (is_file($candidate . 'PHPMailer.php')) {
        $phpmailerBase = $candidate;
        break;
    }
}

require_once $phpmailerBase . 'Exception.php';
require_once $phpmailerBase . 'PHPMailer.php';
require_once $phpmailerBase . 'SMTP.php';

function embedLocalImages(PHPMailer $mail, string $html, string $baseDir): string
{
    return preg_replace_callback('/<img\b([^>]*?)\bsrc=(["\'])([^"\']+)\2([^>]*)>/i', function (array $m) use ($mail, $baseDir): string {
        $src = html_entity_decode($m[3], ENT_QUOTES, 'UTF-8');
        if (preg_match('/^(https?:|data:|cid:)/i', $src)) {
            return $m[0];
        }

        $relative = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($src, '/\\'));
        $path = realpath($baseDir . DIRECTORY_SEPARATOR . $relative);
        $uploadsRoot = realpath($baseDir . DIRECTORY_SEPARATOR . 'uploads');
        if (!$path || !$uploadsRoot || strpos($path, $uploadsRoot) !== 0 || !is_file($path)) {
            return $m[0];
        }

        $cid = 'img_' . md5($path . microtime(true));
        $name = basename($path);
        try {
            $mail->addEmbeddedImage($path, $cid, $name);
            return '<img' . $m[1] . 'src="cid:' . $cid . '"' . $m[4] . '>';
        } catch (Throwable $e) {
            return $m[0];
        }
    }, $html) ?? $html;
}

function sendMail(array $params): array
{
    $smtpHost = 'smtp.gmail.com';
    $smtpPort = 587;
    $smtpSecure = PHPMailer::ENCRYPTION_STARTTLS;

    $to = trim((string)($params['to'] ?? ''));
    $subject = trim((string)($params['subject'] ?? '冷笑話郵件'));
    $body = (string)($params['body'] ?? '');
    $smtpUser = trim((string)($params['smtp_email'] ?? ''));
    $smtpPass = trim((string)($params['smtp_pass'] ?? ''));
    $fromName = trim((string)($params['from_name'] ?? 'Mail System'));
    $fromEmail = trim((string)($params['from_email'] ?? ''));
    $confidential = !empty($params['confidential']);
    $baseDir = (string)($params['base_dir'] ?? __DIR__);

    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => '收件者 Email 格式不正確'];
    }
    if (!filter_var($smtpUser, FILTER_VALIDATE_EMAIL) || $smtpPass === '') {
        return ['success' => false, 'message' => '請先設定 Google 帳號與應用程式密碼'];
    }
    if ($fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        $fromEmail = $smtpUser;
    }
    if ($fromName === '') {
        $fromName = 'Mail System';
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPass;
        $mail->SMTPSecure = $smtpSecure;
        $mail->Port = $smtpPort;
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->SMTPDebug = 0;

        $mail->setFrom($smtpUser, $fromName);
        $mail->addReplyTo($fromEmail, $fromName);
        $mail->addAddress($to);

        if ($confidential) {
            $mail->addCustomHeader('Sensitivity', 'Company-Confidential');
            $mail->addCustomHeader('X-Confidential', 'true');
        }

        $banner = $confidential ? '
            <div style="background:#fef3c7;border:1px solid #f59e0b;color:#92400e;padding:12px 14px;border-radius:8px;margin-bottom:16px;font-weight:bold;">
                機密模式：此郵件內容僅供指定收件者閱讀，請勿任意轉寄或公開。
            </div>' : '';

        $body = embedLocalImages($mail, $body, $baseDir);
        $html = '<!DOCTYPE html><html lang="zh-Hant-TW"><head><meta charset="UTF-8"><title>' .
            htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') .
            '</title></head><body style="margin:0;padding:22px;background:#f4f7fb;color:#1f2937;font-family:Microsoft JhengHei,Arial,sans-serif;">' .
            '<div style="max-width:680px;margin:0 auto;background:#ffffff;border:1px solid #dbe3ef;border-radius:12px;overflow:hidden;">' .
            '<div style="padding:24px 28px;line-height:1.8;font-size:15px;">' . $banner . $body . '</div>' .
            '<div style="background:#f8fafc;border-top:1px solid #dbe3ef;padding:12px 28px;color:#64748b;font-size:12px;">寄件者：' .
            htmlspecialchars($fromName, ENT_QUOTES, 'UTF-8') .
            ($confidential ? ' <strong style="color:#b45309;">｜機密郵件</strong>' : '') .
            '</div></div></body></html>';

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->AltBody = trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body)), ENT_QUOTES, 'UTF-8'));
        $mail->send();

        return ['success' => true, 'to' => $to, 'message' => '寄送成功'];
    } catch (Exception $e) {
        return ['success' => false, 'to' => $to, 'message' => '寄送失敗：' . $mail->ErrorInfo];
    }
}
