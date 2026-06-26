-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- 主機： 127.0.0.1
-- 產生時間： 2026-06-26 14:53:13
-- 伺服器版本： 10.4.32-MariaDB
-- PHP 版本： 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- 資料庫： `green_adventurer`
--

-- --------------------------------------------------------

--
-- 資料表結構 `challenge_tasks`
--

CREATE TABLE `challenge_tasks` (
  `id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL COMMENT '任務標題 (對應 $title)',
  `description` text NOT NULL COMMENT '任務描述與條件說明',
  `difficulty` varchar(20) NOT NULL COMMENT '難度分級：簡單、中等、困難',
  `reward_points` int(11) NOT NULL DEFAULT 10 COMMENT '完成任務可獲得的獎勵碳幣',
  `task_type` varchar(20) NOT NULL COMMENT '挑戰類型：daily(每日挑戰)、weekly(每週挑戰)',
  `deadline` date NOT NULL COMMENT '任務截止日期',
  `created_at` datetime DEFAULT current_timestamp() COMMENT '發布時間'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- 傾印資料表的資料 `challenge_tasks`
--

INSERT INTO `challenge_tasks` (`id`, `title`, `description`, `difficulty`, `reward_points`, `task_type`, `deadline`, `created_at`) VALUES
(1, '連續三天搭捷運', '如標題', '簡單', 30, 'daily', '2026-06-24', '2026-06-20 14:10:06');

-- --------------------------------------------------------

--
-- 資料表結構 `mall_items`
--

CREATE TABLE `mall_items` (
  `id` int(11) NOT NULL,
  `item_name` varchar(100) NOT NULL COMMENT '票券或商品名稱',
  `store_name` varchar(100) NOT NULL COMMENT '合作店家名稱',
  `cost_points` int(11) NOT NULL COMMENT '兌換所需的碳幣點數',
  `created_at` datetime DEFAULT current_timestamp() COMMENT '上架時間'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- 傾印資料表的資料 `mall_items`
--

INSERT INTO `mall_items` (`id`, `item_name`, `store_name`, `cost_points`, `created_at`) VALUES
(1, '🥤 聯名馬卡龍環保杯', 'LocknLock 樂扣樂扣', 150, '2026-06-18 21:15:48'),
(3, '🛍️ 有機棉大容量環保袋', '無印綠色選品', 70, '2026-06-18 21:15:48'),
(4, '☕ 燕麥奶免費升級券', 'GREEN BREW CAFE', 20, '2026-06-18 21:15:48');

-- --------------------------------------------------------

--
-- 資料表結構 `post_comments`
--

CREATE TABLE `post_comments` (
  `id` int(11) NOT NULL,
  `record_id` int(11) NOT NULL COMMENT '貼文的ID',
  `user_id` int(11) NOT NULL COMMENT '留言者的ID',
  `comment` text NOT NULL COMMENT '留言內容',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- 資料表結構 `task_records`
--

CREATE TABLE `task_records` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT '關聯對應到 users 表的 id',
  `action_type` varchar(50) NOT NULL COMMENT '環保行為類型 (如：吃環保蔬食、搭乘大眾運輸)',
  `description` text NOT NULL COMMENT '冒險過程簡單描述',
  `co2_saved` float DEFAULT 0 COMMENT '系統自動算出的減碳量 (kg)',
  `photo_path` text DEFAULT NULL COMMENT '證明照片路徑 (支援多圖，以逗號分隔)',
  `status` varchar(20) DEFAULT 'pending' COMMENT '審核狀態：pending(待審核)、approved(已通過)、rejected(未通過)',
  `reward_earned` int(11) DEFAULT 0 COMMENT '獲得的碳幣/經驗值',
  `reject_reason` text DEFAULT NULL COMMENT '管理員不符退回的原因說明',
  `likes_count` int(11) DEFAULT 0 COMMENT '在環保動態牆上獲得的愛心鼓勵數',
  `created_at` datetime DEFAULT current_timestamp() COMMENT '日誌提交時間'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- 傾印資料表的資料 `task_records`
--

INSERT INTO `task_records` (`id`, `user_id`, `action_type`, `description`, `co2_saved`, `photo_path`, `status`, `reward_earned`, `reject_reason`, `likes_count`, `created_at`) VALUES
(1, 4, '騎自行車/步行', '今天騎腳踏車5公里', 0.75, 'uploads/1781528118_ubike.jpg,uploads/1781528118_螢幕擷取畫面 2026-06-15 205411.png', 'approved', 0, NULL, 0, '2026-06-15 20:55:18'),
(2, 4, '騎自行車/步行', '騎腳踏車五公里真舒服', 0.75, 'uploads/1781785109_螢幕擷取畫面 2026-06-15 205411.png', 'approved', 0, NULL, 0, '2026-06-18 20:18:30'),
(3, 4, '搭乘大眾運輸', '今天搭捷運上班通勤！', 0, 'uploads/1781785281_搭乘捷運紀錄.png', 'approved', 20, NULL, 0, '2026-06-18 20:21:21'),
(4, 4, '買節能家電', '今天買了節能冰箱\r\n\r\n', 0, 'uploads/1781785319_Ubus_751FX.jpg', 'rejected', 0, '圖文不符', 0, '2026-06-18 20:21:59'),
(5, 4, '自備環保杯/餐具', '今天買飲料有帶環保杯我真棒~', 0, 'uploads/1781785918_環保杯買飲料.avif,uploads/1781785918_自備環保杯-5塊.png', 'approved', 0, NULL, 1, '2026-06-18 20:31:58'),
(6, 4, '買節能家電', '棒棒', 0, 'uploads/1781789281_1200x863_wmky_698843725506_202303300174000000.jpg', 'approved', 40, NULL, 0, '2026-06-18 21:28:02'),
(7, 4, '買節能家電', '棒棒棒棒棒棒', 0, 'uploads/1781790038_1200x863_wmky_698843725506_202303300174000000.jpg', 'approved', 100, NULL, 1, '2026-06-18 21:40:38'),
(8, 5, '騎自行車/步行', '流汗真舒服', 0.75, 'uploads/1781889415_騎自行車五公里.png', 'approved', 25, NULL, 0, '2026-06-20 01:16:55'),
(9, 5, '搭乘大眾運輸', '讚讚喔\r\n', 0, 'uploads/1781889431_搭乘捷運紀錄.png', 'approved', 10, NULL, 0, '2026-06-20 01:17:11'),
(10, 5, '自備環保杯/餐具', '好喝飲料', 0, 'uploads/1781889453_自備環保杯-5塊.png,uploads/1781889453_環保杯買飲料.avif', 'approved', 20, NULL, 0, '2026-06-20 01:17:33'),
(11, 5, '買節能家電', '愛地球', 0, 'uploads/1781889470_節能家電發票.png', 'approved', 50, NULL, 0, '2026-06-20 01:17:50'),
(12, 5, '騎自行車/步行', '開心開心', 0.6, 'uploads/1781889490_Ubus_751FX.jpg', 'rejected', 0, '圖文不符', 0, '2026-06-20 01:18:10'),
(13, 5, '自備環保杯/餐具', '好', 0, 'uploads/1781890098_1200x863_wmky_698843725506_202303300174000000.jpg', 'rejected', 0, '圖文不符', 0, '2026-06-20 01:28:18'),
(14, 5, '騎自行車/步行', 'cl', 0.75, 'uploads/1781892695_ubike.jpg', 'approved', 30, NULL, 0, '2026-06-20 02:11:35'),
(15, 5, '自備環保杯/餐具', '讚', 0, 'uploads/1781892722_環保杯買飲料.avif', 'approved', 50, NULL, 1, '2026-06-20 02:12:02'),
(16, 5, '騎自行車/步行', '讚', 0.75, 'uploads/1782368700_騎自行車五公里.png', 'pending', 0, NULL, 0, '2026-06-25 14:25:00');

-- --------------------------------------------------------

--
-- 資料表結構 `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('user','admin') NOT NULL DEFAULT 'user',
  `level` int(11) NOT NULL DEFAULT 1,
  `points` int(11) NOT NULL DEFAULT 0,
  `coins` int(11) NOT NULL DEFAULT 0,
  `remember_selector` varchar(32) DEFAULT NULL,
  `remember_token_hash` varchar(255) DEFAULT NULL,
  `remember_expires_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `last_login_date` datetime DEFAULT NULL COMMENT '最後登入時間',
  `status` varchar(20) NOT NULL DEFAULT 'active' COMMENT '帳號狀態：active/suspended',
  `exp` int(11) NOT NULL DEFAULT 0 COMMENT '經驗值'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `password_hash`, `role`, `level`, `points`, `coins`, `remember_selector`, `remember_token_hash`, `remember_expires_at`, `created_at`, `last_login_date`, `status`, `exp`) VALUES
(1, '系統管理員', '3650226@gmail.com', '$2y$10$Dg9F0vOlVeHDbYOKxCm5hec/UMfy1wgin1sKmac35pKS1DZSg1.IC', '$2y$10$VTPQXib8ZCe2FxqyH0PbWeiofP5NG0.E.sfAyOl.Ua/kWwKb8ELIy', 'admin', 3, 320, 64, NULL, NULL, NULL, '2026-06-18 22:29:12', '2026-06-20 14:09:16', 'active', 0),
(4, '小梅子', 'a1133307+test@mail.nuk.edu.tw', '$2y$10$uzcy5uAWC8PiB5sNsGweb.ymi.fVzklsEGnHyt83b82y1NUquhBZa', '', 'user', 1, 0, 0, NULL, NULL, NULL, '0000-00-00 00:00:00', '2026-06-25 15:06:28', 'active', 0),
(5, 'jyy', 'a1133307@mail.nuk.edu.tw', '$2y$10$F4DhayLIA18KTJAtgKGEkeNsCXbANZpCRYebxsQKSWmLpT9FytSCy', '', 'user', 3, 0, 0, NULL, NULL, NULL, '0000-00-00 00:00:00', '2026-06-25 15:16:35', 'active', 115);

--
-- 已傾印資料表的索引
--

--
-- 資料表索引 `challenge_tasks`
--
ALTER TABLE `challenge_tasks`
  ADD PRIMARY KEY (`id`);

--
-- 資料表索引 `mall_items`
--
ALTER TABLE `mall_items`
  ADD PRIMARY KEY (`id`);

--
-- 資料表索引 `post_comments`
--
ALTER TABLE `post_comments`
  ADD PRIMARY KEY (`id`);

--
-- 資料表索引 `task_records`
--
ALTER TABLE `task_records`
  ADD PRIMARY KEY (`id`);

--
-- 資料表索引 `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- 在傾印的資料表使用自動遞增(AUTO_INCREMENT)
--

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `challenge_tasks`
--
ALTER TABLE `challenge_tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `mall_items`
--
ALTER TABLE `mall_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `post_comments`
--
ALTER TABLE `post_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `task_records`
--
ALTER TABLE `task_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
