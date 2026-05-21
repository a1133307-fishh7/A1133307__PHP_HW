<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/mailer.php';

$uploadDir = __DIR__ . '/uploads';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}

try {
    $db = new PDO('sqlite:' . __DIR__ . '/emails.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("CREATE TABLE IF NOT EXISTS emails (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT UNIQUE NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS send_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT NOT NULL,
        subject TEXT NOT NULL,
        status TEXT NOT NULL,
        message TEXT,
        sent_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Throwable $e) {
    if (!empty($_POST['action']) || !empty($_GET['action'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => '資料庫連線失敗：' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }
    die('<h3 style="color:red">資料庫連線失敗：' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</h3>');
}

function json_response(array $payload): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function clean_html(string $html): string
{
    $allowed = '<p><br><b><strong><i><em><s><strike><u><span><div><font><ul><ol><li><a><img><hr><blockquote><h1><h2><h3><h4><h5><h6><center>';
    $html = strip_tags($html, $allowed);
    $html = preg_replace('/\son[a-z]+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i', '', $html) ?? $html;
    $html = preg_replace('/javascript\s*:/i', '', $html) ?? $html;
    return $html;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
if ($action !== '') {
    switch ($action) {
        case 'add_email':
            $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
            if (!$email) {
                json_response(['success' => false, 'message' => 'Email 格式不正確']);
            }
            try {
                $db->prepare('INSERT INTO emails (email) VALUES (?)')->execute([$email]);
                json_response(['success' => true, 'id' => (int)$db->lastInsertId(), 'email' => $email]);
            } catch (Throwable $e) {
                json_response(['success' => false, 'message' => '這個 Email 已經存在']);
            }

        case 'add_bulk':
            $lines = preg_split('/\R+/', $_POST['emails'] ?? '') ?: [];
            $added = 0;
            $failed = 0;
            $dupes = 0;
            $stmt = $db->prepare('INSERT INTO emails (email) VALUES (?)');
            foreach ($lines as $line) {
                $email = filter_var(trim($line), FILTER_VALIDATE_EMAIL);
                if (!$email) {
                    $failed++;
                    continue;
                }
                try {
                    $stmt->execute([$email]);
                    $added++;
                } catch (Throwable $e) {
                    $dupes++;
                }
            }
            json_response(['success' => true, 'added' => $added, 'failed' => $failed, 'dupes' => $dupes]);

        case 'delete_email':
            $db->prepare('DELETE FROM emails WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
            json_response(['success' => true]);

        case 'clear_all':
            $db->exec('DELETE FROM emails');
            json_response(['success' => true]);

        case 'get_emails':
            $stmt = $db->query('SELECT id, email, created_at FROM emails ORDER BY id ASC');
            json_response(['success' => true, 'emails' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

        case 'get_random':
            $n = max(1, (int)($_POST['n'] ?? 1));
            $stmt = $db->prepare('SELECT id, email, created_at FROM emails ORDER BY RANDOM() LIMIT ?');
            $stmt->bindValue(1, $n, PDO::PARAM_INT);
            $stmt->execute();
            json_response(['success' => true, 'emails' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

        case 'upload_image':
            if (empty($_FILES['image']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
                json_response(['success' => false, 'message' => '請選擇圖片檔']);
            }
            $file = $_FILES['image'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                json_response(['success' => false, 'message' => '圖片上傳失敗']);
            }
            if ($file['size'] > 4 * 1024 * 1024) {
                json_response(['success' => false, 'message' => '圖片不可超過 4MB']);
            }
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
            if (!isset($extMap[$mime])) {
                json_response(['success' => false, 'message' => '只支援 JPG、PNG、GIF、WEBP']);
            }
            $name = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $extMap[$mime];
            $target = $uploadDir . DIRECTORY_SEPARATOR . $name;
            if (!move_uploaded_file($file['tmp_name'], $target)) {
                json_response(['success' => false, 'message' => '無法儲存圖片']);
            }
            json_response(['success' => true, 'url' => 'uploads/' . $name, 'name' => $file['name']]);

        case 'save_smtp_settings':
            $smtpEmail = filter_var(trim($_POST['smtp_email'] ?? ''), FILTER_VALIDATE_EMAIL);
            $smtpPass = trim($_POST['smtp_pass'] ?? '');
            $smtpName = trim($_POST['smtp_name'] ?? 'Mail System');
            if (!$smtpEmail) {
                json_response(['success' => false, 'message' => '請輸入正確的 Google 帳號 Email']);
            }
            if ($smtpPass === '') {
                json_response(['success' => false, 'message' => '請輸入 Google 應用程式密碼']);
            }
            $_SESSION['smtp_settings'] = [
                'email' => $smtpEmail,
                'pass' => $smtpPass,
                'name' => $smtpName !== '' ? $smtpName : 'Mail System',
            ];
            json_response(['success' => true, 'message' => '寄件人設定已暫存在目前工作階段']);

        case 'clear_smtp_settings':
            unset($_SESSION['smtp_settings']);
            json_response(['success' => true]);

        case 'get_logs':
            $stmt = $db->query('SELECT id, email, subject, status, message, sent_at FROM send_logs ORDER BY id DESC LIMIT 100');
            json_response(['success' => true, 'logs' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

        case 'clear_logs':
            $db->exec('DELETE FROM send_logs');
            json_response(['success' => true]);

        case 'send_one':
            $smtp = $_SESSION['smtp_settings'] ?? null;
            if (!$smtp || empty($smtp['email']) || empty($smtp['pass'])) {
                json_response(['success' => false, 'message' => '請先儲存寄件人 Google 帳號與應用程式密碼']);
            }
            $result = sendMail([
                'to' => trim($_POST['to'] ?? ''),
                'subject' => trim($_POST['subject'] ?? '冷笑話郵件'),
                'body' => clean_html($_POST['body'] ?? ''),
                'smtp_email' => $smtp['email'],
                'smtp_pass' => $smtp['pass'],
                'from_name' => $smtp['name'] ?: trim($_POST['from_name'] ?? 'Mail System'),
                'from_email' => trim($_POST['from_email'] ?? ''),
                'confidential' => (($_POST['confidential'] ?? '0') === '1'),
                'base_dir' => __DIR__,
            ]);
            $logStmt = $db->prepare('INSERT INTO send_logs (email, subject, status, message) VALUES (?, ?, ?, ?)');
            $logStmt->execute([
                trim($_POST['to'] ?? ''),
                trim($_POST['subject'] ?? '冷笑話郵件'),
                !empty($result['success']) ? 'success' : 'failed',
                $result['message'] ?? '',
            ]);
            json_response($result);
    }
    json_response(['success' => false, 'message' => '未知的操作']);
}

$colors = ['#111827', '#dc2626', '#ea580c', '#ca8a04', '#16a34a', '#0891b2', '#2563eb', '#7c3aed', '#db2777', '#ffffff'];
$highlights = ['#fef08a', '#bbf7d0', '#bfdbfe', '#fecaca', '#e9d5ff', '#fed7aa', '#ffffff'];
$emojis = ['😀','😂','🤣','😊','😍','👍','👏','🙏','🔥','✨','🎉','🤔','😎','😅','🙌','💡','📌','❤️','💌','☕','🍀','🌟','🧊','🥶','😄','😆','😉','😋','🤓','😴','🤝','👌'];
?>
<!DOCTYPE html>
<html lang="zh-Hant-TW">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>垃圾郵件寄送系統</title>
<style>
:root{
  --bg:#f4f7fb;--panel:#fff;--panel2:#f8fafc;--line:#dbe3ef;--line2:#c8d3e1;
  --text:#1f2937;--muted:#64748b;--light:#94a3b8;--primary:#3b82f6;--primary2:#2563eb;
  --green:#16a34a;--red:#dc2626;--yellow:#f59e0b;--shadow:0 10px 30px rgba(31,41,55,.08);
  --radius:10px;
}
*{box-sizing:border-box}body{margin:0;font-family:"Microsoft JhengHei","Noto Sans TC",Arial,sans-serif;background:var(--bg);color:var(--text);font-size:14px;line-height:1.6}
button,input,textarea,select{font:inherit}button{cursor:pointer}
.topbar{height:60px;background:rgba(255,255,255,.92);border-bottom:1px solid var(--line);display:flex;align-items:center;gap:18px;padding:0 24px;position:sticky;top:0;z-index:10;backdrop-filter:blur(8px)}
.brand{font-size:18px;font-weight:800;color:var(--primary2);white-space:nowrap}.tabs{display:flex;gap:4px;background:#eaf1fb;border-radius:8px;padding:4px}.tab{border:0;background:transparent;padding:7px 16px;border-radius:6px;color:var(--muted);font-weight:700}.tab.active{background:#fff;color:var(--primary2);box-shadow:0 1px 6px rgba(30,41,59,.12)}
.badge{margin-left:auto;background:#dbeafe;color:#1d4ed8;border-radius:999px;padding:4px 12px;font-weight:700;font-size:12px}
.wrap{max-width:1180px;margin:0 auto;padding:24px}.panel{display:none}.panel.active{display:block}
.grid{display:grid;grid-template-columns:340px 1fr;gap:18px}.stack{display:flex;flex-direction:column;gap:16px}
.card{background:var(--panel);border:1px solid var(--line);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden}.card-h{padding:14px 18px;border-bottom:1px solid var(--line);background:var(--panel2);font-weight:800;display:flex;justify-content:space-between;align-items:center}.card-b{padding:18px}
.stats{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:18px}.stat{background:#fff;border:1px solid var(--line);border-radius:8px;padding:14px;text-align:center;box-shadow:var(--shadow)}.stat strong{display:block;font-size:26px;color:var(--primary2)}.stat span{font-size:12px;color:var(--muted)}
label{display:block;margin:0 0 5px;color:var(--muted);font-weight:700;font-size:13px}input[type=text],input[type=email],input[type=number],textarea,select{width:100%;border:1.5px solid var(--line2);border-radius:7px;padding:9px 11px;background:#fff;color:var(--text);outline:none}textarea{min-height:110px;resize:vertical}input:focus,textarea:focus,select:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(59,130,246,.16)}
.row{display:flex;gap:8px}.row input{flex:1}.form-row{margin-bottom:14px}.form-row:last-child{margin-bottom:0}
.btn{border:0;border-radius:7px;padding:9px 15px;font-weight:800;display:inline-flex;align-items:center;justify-content:center;gap:6px}.btn.primary{background:var(--primary);color:#fff}.btn.primary:hover{background:var(--primary2)}.btn.green{background:var(--green);color:#fff}.btn.red{background:var(--red);color:#fff}.btn.ghost{background:#fff;color:var(--muted);border:1.5px solid var(--line2)}.btn.full{width:100%}
.email-list{max-height:468px;overflow:auto}.email-item{display:grid;grid-template-columns:46px 1fr 82px 34px;gap:8px;align-items:center;padding:10px 14px;border-bottom:1px solid var(--line)}.email-item:hover{background:#f8fafc}.no{font-family:Consolas,monospace;color:var(--light);text-align:right}.addr{word-break:break-all}.time{font-size:11px;color:var(--light)}.del{border:0;background:#fff;color:var(--light);border-radius:5px;padding:4px}.del:hover{background:#fee2e2;color:var(--red)}.empty{text-align:center;color:var(--light);padding:42px 14px}
.notice{background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;border-radius:8px;padding:12px 14px;margin-bottom:16px}
.compose-grid{display:grid;grid-template-columns:minmax(0,1fr) 360px;gap:18px}.editor-wrap{border:1.5px solid var(--line2);border-radius:8px;overflow:hidden}.toolbar{background:var(--panel2);border-bottom:1px solid var(--line);padding:8px;display:flex;flex-wrap:wrap;gap:4px;align-items:center}.tb{border:0;background:transparent;border-radius:5px;padding:6px 8px;color:var(--text);min-width:30px}.tb:hover,.tb.active{background:#e2e8f0}.sep{width:1px;height:24px;background:var(--line2);margin:0 3px}.tb-select{width:auto;padding:5px 8px;font-size:12px}.color-wrap,.emoji-wrap{position:relative}.popup{display:none;position:absolute;z-index:20;top:36px;left:0;background:#fff;border:1px solid var(--line2);border-radius:8px;box-shadow:0 12px 30px rgba(15,23,42,.14);padding:9px}.popup.open{display:flex}.swatches{width:160px;gap:7px;flex-wrap:wrap}.swatch{width:24px;height:24px;border-radius:50%;border:2px solid #fff;box-shadow:0 0 0 1px var(--line2)}.emoji-pop.open{display:grid;grid-template-columns:repeat(8,30px);gap:4px}.emoji-btn{border:0;background:#fff;font-size:20px;border-radius:5px;padding:4px}.emoji-btn:hover{background:#eaf1fb}
#editor{min-height:270px;max-height:440px;overflow:auto;background:#fff;padding:16px;outline:none;line-height:1.8}#editor:empty:before{content:attr(data-placeholder);color:var(--light)}
.options{display:grid;gap:14px}.opt{background:#f8fafc;border:1px solid var(--line);border-radius:8px;padding:14px}.opt-title{font-weight:800;margin-bottom:10px}.radio{display:flex;align-items:center;gap:8px;margin:8px 0}.radio label{margin:0;color:var(--text);font-weight:500}.inline{display:flex;gap:8px;align-items:center}.inline input{width:86px}.switch-row{display:flex;justify-content:space-between;align-items:center}.switch{position:relative;width:44px;height:24px}.switch input{display:none}.slider{position:absolute;inset:0;background:#cbd5e1;border-radius:20px}.slider:before{content:"";position:absolute;width:18px;height:18px;left:3px;top:3px;background:#fff;border-radius:50%;transition:.2s}.switch input:checked + .slider{background:var(--primary)}.switch input:checked + .slider:before{transform:translateX(20px)}.conf{display:none;margin-top:10px;background:#fef3c7;border:1px solid #fcd34d;color:#92400e;border-radius:7px;padding:9px}.conf.show{display:block}
.progress{display:none;margin-top:16px}.track{height:13px;background:#e2e8f0;border-radius:999px;overflow:hidden}.fill{height:100%;width:0;background:linear-gradient(90deg,#3b82f6,#22c55e);transition:width .25s}.prog-line{display:flex;justify-content:space-between;color:var(--muted);font-size:13px;margin-top:7px}.pct{font-weight:900;color:var(--primary2)}.log{margin-top:10px;max-height:170px;overflow:auto;background:#0f172a;color:#dbeafe;border-radius:8px;padding:10px;font-family:Consolas,monospace;font-size:12px}.ok{color:#86efac}.bad{color:#fca5a5}.info{color:#93c5fd}
.log-table{max-height:260px;overflow:auto}.log-table.logs-page{max-height:560px}.log-row{display:grid;grid-template-columns:120px 1fr 90px;gap:12px;padding:12px 4px;border-bottom:1px solid var(--line);font-size:13px}.log-row:last-child{border-bottom:0}.log-email{word-break:break-all;font-weight:700}.log-subject{color:var(--muted);margin-top:2px}.log-status{font-weight:800;text-align:right}.log-status.success{color:var(--green)}.log-status.failed{color:var(--red)}.mini-note{font-size:12px;color:var(--muted);line-height:1.55;margin-top:6px}
.modal-bg{display:none;position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:30;align-items:center;justify-content:center;padding:20px}.modal-bg.open{display:flex}.modal{background:#fff;border-radius:10px;box-shadow:0 18px 50px rgba(15,23,42,.25);width:min(430px,100%);padding:22px}.modal h3{margin:0 0 14px}.modal-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:16px}.toastbox{position:fixed;right:22px;bottom:22px;z-index:50;display:grid;gap:8px}.toast{background:#fff;border:1px solid var(--line);border-left:4px solid var(--primary);border-radius:8px;padding:10px 14px;box-shadow:var(--shadow);font-weight:700}.toast.success{border-left-color:var(--green)}.toast.error{border-left-color:var(--red)}
@media(max-width:900px){.grid,.compose-grid{grid-template-columns:1fr}.topbar{align-items:flex-start;height:auto;padding:12px;flex-wrap:wrap}.badge{margin-left:0}.wrap{padding:14px}.email-item{grid-template-columns:40px 1fr 30px}.time{display:none}}
</style>
</head>
<body>
<header class="topbar">
  <div class="brand">垃圾郵件寄送系統</div>
  <nav class="tabs">
    <button class="tab active" type="button" onclick="switchTab('db', this)">Email 資料庫</button>
    <button class="tab" type="button" onclick="switchTab('compose', this)">撰寫與寄送</button>
    <button class="tab" type="button" onclick="switchTab('logs', this)">寄送紀錄</button>
  </nav>
  <div class="badge" id="headerBadge">載入中</div>
</header>

<main class="wrap">
  <section class="panel active" id="panel-db">
    <div class="stats">
      <div class="stat"><strong id="statTotal">0</strong><span>Email 總筆數</span></div>
      <div class="stat"><strong id="statLast" style="font-size:16px;padding-top:8px">無</strong><span>最後新增</span></div>
    </div>
    <div class="grid">
      <div class="stack">
        <div class="card">
          <div class="card-h">單筆新增 Email</div>
          <div class="card-b">
            <label for="singleEmail">Email 位址</label>
            <div class="row">
              <input id="singleEmail" type="email" placeholder="example@gmail.com" onkeydown="if(event.key==='Enter')addSingle()">
              <button class="btn primary" type="button" onclick="addSingle()">新增</button>
            </div>
          </div>
        </div>
        <div class="card">
          <div class="card-h">批次匯入 Email</div>
          <div class="card-b">
            <label for="bulkEmails">每行一個 Email</label>
            <textarea id="bulkEmails" placeholder="user1@gmail.com&#10;user2@yahoo.com&#10;user3@hotmail.com"></textarea>
            <button class="btn primary full" type="button" onclick="addBulk()" style="margin-top:10px">批次匯入</button>
          </div>
        </div>
        <div class="card">
          <div class="card-h" style="color:var(--red)">資料管理</div>
          <div class="card-b">
            <button class="btn red full" type="button" onclick="clearAll()">清空全部 Email</button>
          </div>
        </div>
      </div>
      <div class="card">
        <div class="card-h"><span>Email 清單</span><span id="listCount" style="color:var(--muted);font-size:12px"></span></div>
        <div class="email-list" id="emailList"><div class="empty">目前沒有 Email</div></div>
      </div>
    </div>
  </section>

  <section class="panel" id="panel-compose">
    <div class="notice">
      PHPMailer 已接在 <strong>mailer.php</strong>。Gmail 若要成功寄出，請使用 Google App Password，不要使用一般登入密碼。
    </div>
    <div class="compose-grid">
      <div class="stack">
        <div class="card">
          <div class="card-h">基本郵件介面</div>
          <div class="card-b">
            <div class="row">
              <div style="flex:1">
                <label for="fromName">寄件者名稱</label>
                <input id="fromName" type="text" value="冷笑話郵件系統">
              </div>
              <div style="flex:1">
                <label for="fromEmail">回覆信箱</label>
                <input id="fromEmail" type="email" placeholder="your@gmail.com">
              </div>
            </div>
            <div class="form-row" style="margin-top:14px">
              <label for="mailSubject">郵件主旨</label>
              <input id="mailSubject" type="text" value="今天的冷笑話時間">
            </div>
            <div class="form-row">
              <label>郵件內容</label>
              <div class="editor-wrap">
                <div class="toolbar">
                  <select class="tb-select" title="字型大小" onchange="execCmd('fontSize', this.value); this.selectedIndex=0">
                    <option value="">大小</option><option value="1">小</option><option value="3">正常</option><option value="5">大</option><option value="7">特大</option>
                  </select>
                  <span class="sep"></span>
                  <button class="tb" title="粗體" type="button" onclick="execCmd('bold')"><b>B</b></button>
                  <button class="tb" title="斜體" type="button" onclick="execCmd('italic')"><i>I</i></button>
                  <button class="tb" title="刪除線" type="button" onclick="execCmd('strikeThrough')"><s>S</s></button>
                  <button class="tb" title="底線" type="button" onclick="execCmd('underline')"><u>U</u></button>
                  <span class="sep"></span>
                  <button class="tb" title="置左" type="button" onclick="execCmd('justifyLeft')">左</button>
                  <button class="tb" title="置中" type="button" onclick="execCmd('justifyCenter')">中</button>
                  <button class="tb" title="置右" type="button" onclick="execCmd('justifyRight')">右</button>
                  <span class="sep"></span>
                  <div class="color-wrap">
                    <button class="tb" id="colorBtn" type="button" onclick="togglePopup('colorPicker')">文字色</button>
                    <div class="popup swatches" id="colorPicker">
                      <?php foreach ($colors as $c): ?><button class="swatch" type="button" style="background:<?=htmlspecialchars($c)?>" onclick="setColor('<?=htmlspecialchars($c)?>')"></button><?php endforeach; ?>
                      <input type="color" title="自訂文字色" onchange="setColor(this.value)">
                    </div>
                  </div>
                  <div class="color-wrap">
                    <button class="tb" id="bgBtn" type="button" onclick="togglePopup('bgPicker')">底色</button>
                    <div class="popup swatches" id="bgPicker">
                      <?php foreach ($highlights as $c): ?><button class="swatch" type="button" style="background:<?=htmlspecialchars($c)?>" onclick="setBackColor('<?=htmlspecialchars($c)?>')"></button><?php endforeach; ?>
                      <input type="color" title="自訂底色" onchange="setBackColor(this.value)">
                    </div>
                  </div>
                  <span class="sep"></span>
                  <button class="tb" type="button" onclick="execCmd('insertUnorderedList')">項目</button>
                  <button class="tb" type="button" onclick="execCmd('insertOrderedList')">編號</button>
                  <button class="tb" type="button" onclick="insertLink()">連結</button>
                  <button class="tb" type="button" onclick="openImageModal()">圖片</button>
                  <span class="sep"></span>
                  <div class="emoji-wrap">
                    <button class="tb" type="button" onclick="toggleEmoji(event)">Emoji</button>
                    <div class="popup emoji-pop" id="emojiPicker">
                      <?php foreach ($emojis as $em): ?><button class="emoji-btn" type="button" onclick="insertEmoji('<?=htmlspecialchars($em, ENT_QUOTES, 'UTF-8')?>')"><?=htmlspecialchars($em, ENT_QUOTES, 'UTF-8')?></button><?php endforeach; ?>
                    </div>
                  </div>
                  <button class="tb" type="button" title="移除選取文字的粗體、斜體、顏色、大小等格式，保留純文字。" onclick="execCmd('removeFormat')">清除格式</button>
                </div>
                <div id="editor" contenteditable="true" data-placeholder="撰寫郵件內容"><p>嗨，</p><p>今天想分享一則輕鬆的冷笑話給你。</p><p>祝你有美好的一天！</p></div>
              </div>
            </div>
            <div class="switch-row">
              <label for="confidentialToggle" style="margin:0">機密模式</label>
              <label class="switch"><input id="confidentialToggle" type="checkbox" onchange="toggleConf()"><span class="slider"></span></label>
            </div>
            <div class="conf" id="confBox">會在郵件上方加入機密提示，並加上 Sensitivity 標頭；一般 PHP 無法真正啟用 Gmail 官方的「禁止轉寄/到期」機密模式。</div>
          </div>
        </div>
      </div>

      <aside class="card">
        <div class="card-h">寄送設定</div>
        <div class="card-b options">
          <div class="opt">
            <div class="opt-title">寄件人設定</div>
            <div class="notice" style="margin:0 0 12px;padding:10px">
              🔐 請使用 Google 帳號產生的「應用程式密碼」，這些資料只會暫存在目前工作階段，不會寫入程式碼。
            </div>
            <label for="smtpEmail">Google 帳號</label>
            <input id="smtpEmail" type="email" placeholder="your@gmail.com">
            <label for="smtpPass" style="margin-top:10px">應用程式密碼</label>
            <input id="smtpPass" type="password" placeholder="16 位應用程式密碼" autocomplete="off">
            <div class="mini-note" id="smtpStatus">按下開始寄送時郵件帳號會自動暫存在目前工作階段</div>
          </div>
          <div class="opt">
            <div class="opt-title">寄送對象</div>
            <div class="radio"><input type="radio" id="modeAll" name="sendMode" value="all" checked onchange="updateSendMode()"><label for="modeAll">全部寄送</label></div>
            <div class="radio"><input type="radio" id="modeRandom" name="sendMode" value="random" onchange="updateSendMode()"><label for="modeRandom">隨機寄送幾筆</label></div>
            <div id="randomCount" style="display:none;margin-top:8px"><label for="randomN">隨機筆數</label><input id="randomN" type="number" min="1" value="5"></div>
          </div>
          <div class="opt">
            <div class="opt-title">寄送間隔</div>
            <div class="radio"><input type="radio" id="intFixed" name="intervalMode" value="fixed" checked onchange="updateIntervalMode()"><label for="intFixed">固定秒數</label></div>
            <div id="fixedSec"><label for="intervalSec">每封間隔秒數</label><input id="intervalSec" type="number" min="0" max="300" value="2"></div>
            <div class="radio"><input type="radio" id="intRandom" name="intervalMode" value="random" onchange="updateIntervalMode()"><label for="intRandom">隨機秒數</label></div>
            <div id="randomSec" style="display:none"><label>隨機範圍</label><div class="inline"><input id="minSec" type="number" min="0" value="1"><span>到</span><input id="maxSec" type="number" min="0" value="5"><span>秒</span></div></div>
          </div>
          <div class="opt">
            <div class="switch-row">
              <label for="jokeToggle" style="margin:0">每封自動加入不同冷笑話</label>
              <label class="switch"><input id="jokeToggle" type="checkbox" checked><span class="slider"></span></label>
            </div>
          </div>
          <button class="btn green full" id="sendBtn" type="button" onclick="startSend()">開始寄送</button>
          <button class="btn red full" id="stopBtn" type="button" onclick="stopSend()" style="display:none">停止寄送</button>
          <div class="progress" id="progressWrap">
            <div class="track"><div class="fill" id="progressFill"></div></div>
            <div class="prog-line"><span id="progressText">等待中</span><span class="pct" id="progressPct">0%</span></div>
            <div class="log" id="sendLog"></div>
          </div>
        </div>
      </aside>
    </div>
  </section>

  <section class="panel" id="panel-logs">
    <div class="card">
      <div class="card-h">
        <span>寄送紀錄</span>
        <button class="btn ghost" type="button" onclick="clearSendLogs()" style="padding:6px 12px">清除紀錄</button>
      </div>
      <div class="card-b">
        <div class="notice">這裡會顯示最近 100 筆寄送結果，包含寄送時間、收件者、主旨與成功或失敗狀態。</div>
        <div class="log-table logs-page" id="sendLogTable"><div class="empty" style="padding:28px 0">尚無寄送紀錄</div></div>
      </div>
    </div>
  </section>
</main>

<div class="modal-bg" id="imageModal">
  <div class="modal">
    <h3>從本機上傳圖片</h3>
    <div class="form-row">
      <label for="imageFile">選擇圖片</label>
      <input id="imageFile" type="file" accept="image/png,image/jpeg,image/gif,image/webp">
    </div>
    <div class="form-row">
      <label for="imageAlt">替代文字</label>
      <input id="imageAlt" type="text" placeholder="圖片說明">
    </div>
    <div class="form-row">
      <label for="imageWidth">圖片寬度</label>
      <input id="imageWidth" type="text" value="420px" placeholder="例如 100%、420px">
    </div>
    <div class="modal-actions">
      <button class="btn ghost" type="button" onclick="closeImageModal()">取消</button>
      <button class="btn primary" type="button" onclick="uploadAndInsertImage()">上傳並插入</button>
    </div>
  </div>
</div>
<div class="toastbox" id="toastbox"></div>

<script>
const jokes = [
  {title:'冰箱篇', text:'為什麼冰箱不會唱歌？因為它只會冷藏，不會熱唱。'},
  {title:'數學篇', text:'0 跟 8 說：你繫皮帶的樣子很有精神。'},
  {title:'電腦篇', text:'鍵盤為什麼很會交朋友？因為它常常按讚。'},
  {title:'咖啡篇', text:'咖啡為什麼很有禮貌？因為它懂得加奶說謝謝。'},
  {title:'魚篇', text:'魚為什麼不會迷路？因為牠一直知道自己的方向是「魚」路。'},
  {title:'月亮篇', text:'月亮為什麼不加班？因為它到點就下弦。'},
  {title:'雨傘篇', text:'雨傘為什麼很低調？因為它只在有需要時才撐場。'},
  {title:'麵包篇', text:'麵包為什麼很會鼓勵人？因為它總是說你一定會發。'}
];
let sendQueue = [], sendIndex = 0, isSending = false, sendTimer = null, savedRange = null;

function switchTab(id, btn){
  document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
  document.getElementById('panel-' + id).classList.add('active');
  btn.classList.add('active');
}
function toast(msg, type='info'){
  const box = document.getElementById('toastbox');
  const el = document.createElement('div');
  el.className = 'toast ' + type;
  el.textContent = msg;
  box.appendChild(el);
  setTimeout(() => el.remove(), 3200);
}
async function api(data, fileData=null){
  const fd = new FormData();
  Object.entries(data).forEach(([k,v]) => fd.append(k, v));
  if (fileData) fd.append(fileData.name, fileData.file);
  const res = await fetch('', {method:'POST', body:fd});
  return res.json();
}
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));

async function saveSmtpSettings(showToast=false){
  const smtpEmail = document.getElementById('smtpEmail').value.trim();
  const smtpPass = document.getElementById('smtpPass').value.trim();
  const smtpName = document.getElementById('fromName').value.trim() || 'Mail System';
  const r = await api({action:'save_smtp_settings', smtp_email:smtpEmail, smtp_pass:smtpPass, smtp_name:smtpName});
  if (r.success) {
    document.getElementById('smtpStatus').textContent = `已暫存：${smtpEmail}`;
    document.getElementById('smtpPass').value = '';
    if (showToast) toast(r.message || '寄件人設定已暫存', 'success');
  } else {
    toast(r.message || '寄件人設定失敗', 'error');
  }
  return r.success;
}

async function loadSendLogs(){
  const r = await api({action:'get_logs'});
  const logs = r.logs || [];
  const box = document.getElementById('sendLogTable');
  if (!logs.length) {
    box.innerHTML = '<div class="empty" style="padding:18px 0">尚無寄送紀錄</div>';
    return;
  }
  box.innerHTML = logs.map(log => `<div class="log-row">
    <div>${esc(String(log.sent_at || '').slice(5, 16))}</div>
    <div><div class="log-email">${esc(log.email)}</div><div class="log-subject">${esc(log.subject)}</div></div>
    <div class="log-status ${esc(log.status)}">${log.status === 'success' ? '成功' : '失敗'}</div>
  </div>`).join('');
}

async function clearSendLogs(){
  if (!confirm('確定要清除寄送紀錄？')) return;
  await api({action:'clear_logs'});
  loadSendLogs();
  toast('寄送紀錄已清除', 'info');
}

async function loadEmails(){
  const r = await api({action:'get_emails'});
  const emails = r.emails || [];
  document.getElementById('statTotal').textContent = emails.length;
  document.getElementById('listCount').textContent = `${emails.length} 筆`;
  document.getElementById('headerBadge').textContent = `資料庫：${emails.length} 筆 Email`;
  document.getElementById('statLast').textContent = emails.length ? emails[emails.length - 1].email : '無';
  const list = document.getElementById('emailList');
  if (!emails.length) {
    list.innerHTML = '<div class="empty">目前沒有 Email，請先新增資料。</div>';
    return;
  }
  list.innerHTML = emails.map((e, i) => `<div class="email-item">
    <span class="no">#${i + 1}</span><span class="addr">${esc(e.email)}</span>
    <span class="time">${esc(String(e.created_at || '').slice(5, 16))}</span>
    <button class="del" type="button" title="刪除" onclick="deleteEmail(${Number(e.id)})">×</button>
  </div>`).join('');
}
async function addSingle(){
  const input = document.getElementById('singleEmail');
  const r = await api({action:'add_email', email:input.value.trim()});
  if (r.success) { toast('已新增 ' + r.email, 'success'); input.value = ''; loadEmails(); }
  else toast(r.message || '新增失敗', 'error');
}
async function addBulk(){
  const emails = document.getElementById('bulkEmails').value.trim();
  if (!emails) return toast('請輸入 Email', 'error');
  const r = await api({action:'add_bulk', emails});
  toast(`新增 ${r.added} 筆，重複 ${r.dupes} 筆，格式錯誤 ${r.failed} 筆`, 'success');
  document.getElementById('bulkEmails').value = '';
  loadEmails();
}
async function deleteEmail(id){
  await api({action:'delete_email', id});
  toast('已刪除', 'info');
  loadEmails();
}
async function clearAll(){
  if (!confirm('確定要清空全部 Email？')) return;
  await api({action:'clear_all'});
  toast('已清空資料庫', 'info');
  loadEmails();
}

function editor(){ return document.getElementById('editor'); }
function execCmd(cmd, val=null){ editor().focus(); document.execCommand(cmd, false, val); }
function saveRange(){ const s = window.getSelection(); if (s.rangeCount) savedRange = s.getRangeAt(0).cloneRange(); }
function restoreRange(){ if (!savedRange) return; const s = window.getSelection(); s.removeAllRanges(); s.addRange(savedRange); }
editor().addEventListener('keyup', saveRange);
editor().addEventListener('mouseup', saveRange);
function closePopups(){ document.querySelectorAll('.popup.open').forEach(p => p.classList.remove('open')); }
function togglePopup(id){ saveRange(); document.querySelectorAll('.popup').forEach(p => { if (p.id !== id) p.classList.remove('open'); }); document.getElementById(id).classList.toggle('open'); }
function setColor(c){ restoreRange(); execCmd('foreColor', c); closePopups(); }
function setBackColor(c){ restoreRange(); execCmd('hiliteColor', c); execCmd('backColor', c); closePopups(); }
function toggleEmoji(e){ e.stopPropagation(); togglePopup('emojiPicker'); }
function insertEmoji(em){ restoreRange(); execCmd('insertText', em); closePopups(); }
function insertLink(){
  saveRange();
  const url = prompt('請輸入連結網址', 'https://');
  if (!url) return;
  const text = prompt('請輸入顯示文字，留空會使用網址', '') || url;
  restoreRange();
  execCmd('insertHTML', `<a href="${esc(url)}" target="_blank" rel="noopener" style="color:#2563eb">${esc(text)}</a>`);
}
function openImageModal(){ saveRange(); document.getElementById('imageModal').classList.add('open'); }
function closeImageModal(){ document.getElementById('imageModal').classList.remove('open'); }
async function uploadAndInsertImage(){
  const input = document.getElementById('imageFile');
  if (!input.files.length) return toast('請先選擇圖片', 'error');
  const r = await api({action:'upload_image'}, {name:'image', file:input.files[0]});
  if (!r.success) return toast(r.message || '上傳失敗', 'error');
  const alt = document.getElementById('imageAlt').value.trim() || r.name || 'image';
  const width = document.getElementById('imageWidth').value.trim() || '420px';
  restoreRange();
  execCmd('insertHTML', `<img src="${esc(r.url)}" alt="${esc(alt)}" style="max-width:100%;width:${esc(width)};height:auto;display:block;margin:10px 0;border-radius:6px">`);
  closeImageModal();
  input.value = ''; document.getElementById('imageAlt').value = '';
  toast('圖片已插入', 'success');
}
document.addEventListener('click', e => { if (!e.target.closest('.color-wrap') && !e.target.closest('.emoji-wrap')) closePopups(); });
document.getElementById('imageModal').addEventListener('click', e => { if (e.target.id === 'imageModal') closeImageModal(); });
function toggleConf(){ document.getElementById('confBox').classList.toggle('show', document.getElementById('confidentialToggle').checked); }
function updateSendMode(){ document.getElementById('randomCount').style.display = document.querySelector('input[name=sendMode]:checked').value === 'random' ? 'block' : 'none'; }
function updateIntervalMode(){
  const random = document.querySelector('input[name=intervalMode]:checked').value === 'random';
  document.getElementById('fixedSec').style.display = random ? 'none' : 'block';
  document.getElementById('randomSec').style.display = random ? 'block' : 'none';
}
function pickJoke(){ return jokes[Math.floor(Math.random() * jokes.length)]; }
function makeMailForRecipient(to){
  const baseSubject = document.getElementById('mailSubject').value.trim() || '今天的冷笑話時間';
  let body = editor().innerHTML.trim();
  let subject = baseSubject;
  if (document.getElementById('jokeToggle').checked) {
    const joke = pickJoke();
    subject = `${baseSubject} - ${joke.title}`;
    body += `<p style="margin-top:18px;margin-bottom:0;color:#5f6368;font-size:13px;">今日冷笑話：${esc(joke.text)}</p>`;
  }
  return {subject, body};
}
async function startSend(){
  const smtpReady = await saveSmtpSettings(false);
  if (!smtpReady) return;
  if (!editor().innerHTML.trim()) return toast('請先輸入郵件內容', 'error');
  const mode = document.querySelector('input[name=sendMode]:checked').value;
  const r = mode === 'random'
    ? await api({action:'get_random', n:Math.max(1, parseInt(document.getElementById('randomN').value || '1', 10))})
    : await api({action:'get_emails'});
  sendQueue = (r.emails || []).map(e => e.email);
  if (!sendQueue.length) return toast('資料庫沒有可寄送的 Email', 'error');
  sendIndex = 0; isSending = true;
  document.getElementById('sendBtn').style.display = 'none';
  document.getElementById('stopBtn').style.display = 'inline-flex';
  document.getElementById('progressWrap').style.display = 'block';
  document.getElementById('sendLog').innerHTML = '';
  updateProgress(0, sendQueue.length, '準備寄送');
  sendNext();
}
async function sendNext(){
  if (!isSending || sendIndex >= sendQueue.length) return finishSend();
  const to = sendQueue[sendIndex];
  updateProgress(sendIndex, sendQueue.length, `寄送中：${to}`);
  const mail = makeMailForRecipient(to);
  const r = await api({
    action:'send_one', to,
    from_name:document.getElementById('fromName').value,
    from_email:document.getElementById('fromEmail').value,
    subject:mail.subject,
    body:mail.body,
    confidential:document.getElementById('confidentialToggle').checked ? '1' : '0'
  });
  appendLog(r.success ? `<span class="ok">[${sendIndex + 1}/${sendQueue.length}] ${esc(to)} 寄送成功</span>` : `<span class="bad">[${sendIndex + 1}/${sendQueue.length}] ${esc(to)} 失敗：${esc(r.message)}</span>`);
  sendIndex++;
  updateProgress(sendIndex, sendQueue.length, sendIndex >= sendQueue.length ? '寄送完成' : '等待下一封');
  if (sendIndex >= sendQueue.length) return finishSend();
  const intMode = document.querySelector('input[name=intervalMode]:checked').value;
  let delay = 0;
  if (intMode === 'random') {
    let min = parseFloat(document.getElementById('minSec').value || '1');
    let max = parseFloat(document.getElementById('maxSec').value || '5');
    if (max < min) [min, max] = [max, min];
    delay = (min + Math.random() * (max - min)) * 1000;
  } else {
    delay = parseFloat(document.getElementById('intervalSec').value || '0') * 1000;
  }
  appendLog(`<span class="info">等待 ${(delay / 1000).toFixed(1)} 秒...</span>`);
  sendTimer = setTimeout(sendNext, delay);
}
function stopSend(){
  isSending = false;
  clearTimeout(sendTimer);
  document.getElementById('sendBtn').style.display = 'inline-flex';
  document.getElementById('stopBtn').style.display = 'none';
  appendLog(`<span class="bad">已停止：${sendIndex}/${sendQueue.length}</span>`);
  toast('已停止寄送', 'info');
}
function finishSend(){
  isSending = false;
  clearTimeout(sendTimer);
  document.getElementById('sendBtn').style.display = 'inline-flex';
  document.getElementById('stopBtn').style.display = 'none';
  updateProgress(sendIndex, sendQueue.length, '寄送完成');
  appendLog(`<span class="info">完成，共處理 ${sendIndex} 封。</span>`);
  loadSendLogs();
  toast(`寄送完成，共處理 ${sendIndex} 封`, 'success');
}
function updateProgress(cur, total, label){
  const pct = total ? Math.round(cur / total * 100) : 0;
  document.getElementById('progressFill').style.width = pct + '%';
  document.getElementById('progressPct').textContent = pct + '%';
  document.getElementById('progressText').textContent = label;
}
function appendLog(html){
  const log = document.getElementById('sendLog');
  log.innerHTML += html + '<br>';
  log.scrollTop = log.scrollHeight;
}
loadEmails();
loadSendLogs();
</script>
</body>
</html>
