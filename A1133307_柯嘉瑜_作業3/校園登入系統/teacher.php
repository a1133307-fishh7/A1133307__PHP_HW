<?php
session_start();

if(isset($_SESSION['login'])){
    if($_SESSION['login']=='teacher'){
        echo "<h1>歡迎來到教師專區</h1>";
        echo "<p>登入身分：" . $_SESSION['uID'] . "</p>";
        echo "<p>這裡是教師輸入成績與點名的地方。</p>";
        echo "<a href='logout.php'>登出</a>";
    }else{
        echo "<h1>非法進入教師網頁你會看不到任何東西！3秒後回登入首頁</h1>";
        header("Refresh:3;url=index.php");
    }
}else{
    echo "<h1>非法進入教師網頁你會看不到東西！4秒後回登入頁面</h1>";
    header("Refresh:4;url=index.php");
}
?>