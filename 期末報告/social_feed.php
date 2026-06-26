<?php
session_start();
require_once 'config.php';

// 檢查登入狀態
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// 建立一個空陣列來存放貼文資料
$feed_data = [];

// 使用 try...catch 保護網來進行 PDO 操作
try {
    // ==========================================
    // 處理「按讚」邏輯
    // ==========================================
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['like_record_id'])) {
        $like_id = (int)$_POST['like_record_id'];
        
        // PDO 預處理語句更新按讚數
        $stmt = $pdo->prepare("UPDATE task_records SET likes_count = likes_count + 1 WHERE id = ?");
        $stmt->execute([$like_id]);
        
        // 按完讚回到原本貼文的位置
        header("Location: social_feed.php#post-" . $like_id);
        exit;
    }

    // ==========================================
    // 處理「留言」邏輯
    // ==========================================
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['comment_record_id'])) {
        $record_id = (int)$_POST['comment_record_id'];
        $user_id = $_SESSION['user_id'];
        $comment_text = trim($_POST['comment_text']);
        
        if (!empty($comment_text)) {
            // PDO 自動處理特殊字元跳脫，不再需要 mysqli_real_escape_string
            $sql_comment = "INSERT INTO post_comments (record_id, user_id, comment) VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($sql_comment);
            $stmt->execute([$record_id, $user_id, $comment_text]);
        }
        
        // 留完言回到該篇貼文
        header("Location: social_feed.php#post-" . $record_id);
        exit;
    }

    // ==========================================
    // 撈取動態牆資料
    // ==========================================
    $sql = "SELECT tr.*, u.name, u.level
            FROM task_records tr
            JOIN users u ON tr.user_id = u.id
            WHERE tr.status = 'approved'
            ORDER BY tr.created_at DESC
            LIMIT 50";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $feed_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("動態牆讀取失敗，請稍後再試或聯繫管理員。" . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>環保動態牆 - 綠色冒險家公會</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #ecf0f1; padding: 20px; }
        .container { max-width: 650px; margin: auto; }
        .header-box { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .btn-back { padding: 10px 15px; background: #95a5a6; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; }
        .btn-back:hover { background: #7f8c8d; }
        
        /* 貼文卡片樣式 */
        .post-card { background: white; border-radius: 15px; padding: 20px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); scroll-margin-top: 20px;}
        .post-header { display: flex; align-items: center; margin-bottom: 15px; }
        .avatar { font-size: 30px; background: #e8f5e9; width: 50px; height: 50px; display: flex; justify-content: center; align-items: center; border-radius: 50%; margin-right: 15px; }
        .user-info { flex-grow: 1; }
        .user-name { font-weight: bold; font-size: 16px; color: #2c3e50; }
        .user-level { font-size: 12px; background: #2e8b57; color: white; padding: 2px 6px; border-radius: 10px; margin-left: 5px; }
        .post-time { font-size: 12px; color: #999; margin-top: 3px; }
        
        .post-tag { display: inline-block; background: #dcedc8; color: #33691e; padding: 5px 10px; border-radius: 15px; font-size: 13px; font-weight: bold; margin-bottom: 10px; }
        .post-co2 { color: #27ae60; font-weight: bold; font-size: 14px; float: right; margin-top: 5px; }
        
        .post-desc { font-size: 15px; color: #444; line-height: 1.5; margin-bottom: 15px; }
        
        .post-images { display: flex; gap: 10px; overflow-x: auto; padding-bottom: 10px; }
        .post-images img { max-width: 100%; max-height: 300px; border-radius: 10px; object-fit: cover; }
        
        /* 互動按鈕區塊 */
        .post-footer { margin-top: 15px; border-top: 1px solid #eee; padding-top: 15px; display: flex; align-items: center; gap: 10px; }
        .btn-action { background: none; border: 1px solid #ddd; color: #555; padding: 6px 15px; border-radius: 20px; cursor: pointer; font-size: 14px; font-weight: bold; display: flex; align-items: center; gap: 5px; transition: 0.2s; }
        .btn-action:hover { background: #f1f2f6; }
        .btn-like { border-color: #ff7675; color: #d63031; }
        .btn-like:hover { background: #ff7675; color: white; }

        /* 留言區塊樣式 */
        .comment-section { margin-top: 15px; background: #f8f9fa; padding: 15px; border-radius: 10px; display: none; }
        .comment-item { font-size: 14px; margin-bottom: 8px; border-bottom: 1px dashed #ddd; padding-bottom: 8px;}
        .comment-item:last-child { border-bottom: none; }
        .comment-item strong { color: #2980b9; }
        .comment-form { display: flex; margin-top: 10px; gap: 10px; }
        .comment-input { flex-grow: 1; padding: 8px 12px; border: 1px solid #ccc; border-radius: 20px; outline: none; }
        .comment-submit { background: #2980b9; color: white; border: none; padding: 8px 15px; border-radius: 20px; cursor: pointer; font-weight: bold; }
        .comment-submit:hover { background: #2471a3; }
    </style>
    <script>
        // 切換顯示留言區塊
        function toggleComment(postId) {
            var section = document.getElementById('comment-section-' + postId);
            if (section.style.display === 'none' || section.style.display === '') {
                section.style.display = 'block';
            } else {
                section.style.display = 'none';
            }
        }

        // 複製貼文連結功能
        function copyLink(postId) {
            var url = window.location.origin + window.location.pathname + '#post-' + postId;
            navigator.clipboard.writeText(url).then(function() {
                alert('🔗 貼文連結已複製成功！快去分享給朋友吧！');
            }).catch(function() {
                alert('複製失敗，請手動複製網址。');
            });
        }
    </script>
</head>
<body>

<div class="container">
    <div class="header-actions">
        <a href="dashboard.php" class="btn-back">⬅ 返回大廳</a>
    </div>
    
    <h2 style="color: #2e8b57; text-align: center; margin-bottom: 5px;">💬 環保動態牆</h2>
    <p style="text-align: center; color: #7f8c8d; font-size: 14px; margin-bottom: 30px;">看看其他冒險者今天為地球做了什麼努力！</p>

    <?php if (count($feed_data) > 0): ?>
        <?php foreach ($feed_data as $row): ?>
            <div class="post-card" id="post-<?php echo $row['id']; ?>">
                <div class="post-header">
                    <div class="avatar">🌿</div>
                    <div class="user-info">
                        <div class="user-name">
                            <?php echo htmlspecialchars($row['name']); ?>
                            <span class="user-level">Lv. <?php echo $row['level']; ?></span>
                        </div>
                        <div class="post-time"><?php echo date('Y-m-d H:i', strtotime($row['created_at'])); ?></div>
                    </div>
                </div>

                <div>
                    <span class="post-tag">#<?php echo htmlspecialchars($row['action_type'] ?? '環保任務'); ?></span>
                    
                    <?php if (isset($row['action_type']) && $row['action_type'] === '騎自行車/步行'): ?>
                        <span class="post-co2">⬇ <?php echo htmlspecialchars($row['co2_saved']); ?> kg CO2</span>
                    <?php endif; ?>
                </div>
                
                <div class="post-desc">
                    <?php echo nl2br(htmlspecialchars($row['description'] ?? '')); ?>
                </div>

                <?php if (!empty($row['photo_path'])): ?>
                    <div class="post-images">
                        <?php
                        $photos = explode(',', $row['photo_path']);
                        foreach ($photos as $path) {
                            if (!empty($path)) {
                                echo "<img src='" . htmlspecialchars($path) . "' alt='減碳照片'>";
                            }
                        }
                        ?>
                    </div>
                <?php endif; ?>

                <div class="post-footer">
                    <form method="POST" action="social_feed.php" style="margin: 0;">
                        <input type="hidden" name="like_record_id" value="<?php echo $row['id']; ?>">
                        <button type="submit" class="btn-action btn-like">
                            ❤️ 給予鼓勵 (<?php echo isset($row['likes_count']) ? $row['likes_count'] : 0; ?>)
                        </button>
                    </form>
                    
                    <button class="btn-action" onclick="toggleComment(<?php echo $row['id']; ?>)">
                        💬 留言
                    </button>
                    
                    <button class="btn-action" onclick="copyLink(<?php echo $row['id']; ?>)">
                        🔗 分享連結
                    </button>
                </div>

                <div class="comment-section" id="comment-section-<?php echo $row['id']; ?>">
                    <?php
                    // 撈取這篇貼文的所有留言 (PDO 升級版)
                    $post_id = $row['id'];
                    try {
                        $comment_sql = "SELECT c.comment, c.created_at, u.name 
                                        FROM post_comments c 
                                        JOIN users u ON c.user_id = u.id 
                                        WHERE c.record_id = ? 
                                        ORDER BY c.created_at ASC";
                        $comment_stmt = $pdo->prepare($comment_sql);
                        $comment_stmt->execute([$post_id]);
                        $comments = $comment_stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (count($comments) > 0) {
                            foreach ($comments as $c) {
                                echo "<div class='comment-item'>";
                                echo "<strong>" . htmlspecialchars($c['name']) . "：</strong>";
                                echo htmlspecialchars($c['comment']);
                                echo "</div>";
                            }
                        } else {
                            echo "<div class='comment-item' style='color: #999; text-align: center; border: none;'>成為第一個給予肯定的人吧！</div>";
                        }
                    } catch (PDOException $e) {
                        echo "<div class='comment-item' style='color: #e74c3c; text-align: center; border: none;'>無法載入留言資料</div>";
                    }
                    ?>
                    
                    <form method="POST" action="social_feed.php" class="comment-form">
                        <input type="hidden" name="comment_record_id" value="<?php echo $row['id']; ?>">
                        <input type="text" name="comment_text" class="comment-input" placeholder="寫下你的留言..." required>
                        <button type="submit" class="comment-submit">送出</button>
                    </form>
                </div>

            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div style="text-align: center; color: #999; padding: 40px; background: white; border-radius: 15px;">
            目前動態牆上還沒有任何紀錄喔！趕快去填寫第一篇減碳日誌吧！
        </div>
    <?php endif; ?>

</div>

</body>
</html>
<?php $pdo = null; ?>