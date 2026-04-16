<?php
session_start();

if(isset($_SESSION['login'])){
    if($_SESSION['login']=='admin'){
        echo "<h1>這裡是管理員專區</h1>";
        echo "<p>登入身分：" . $_SESSION['uID'] . "</p>";
        echo "<p>這裡是擁有最高權限的後台管理系統。</p>";
        echo "<a href='logout.php'>登出</a>";
    }else{
        echo "<h1>非法進入管理員網頁你會看不到任何東西!3秒後回登入頁面<h1>";
        header("Refresh:3;url=index.php");
    }
}else{
    echo "<h1>非法進入網頁你會看不到東西！4秒後回登入頁面</h1>";
    header("Refresh:4;url=index.php");
}

?>