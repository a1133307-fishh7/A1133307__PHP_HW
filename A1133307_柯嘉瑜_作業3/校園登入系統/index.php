<html>

<head>
    <title>校園登入系統</title>
</head>

<body>
    <h2>系統登入</h2>


    <?php

    if (isset($_COOKIE['uName'])){
        echo $_COOKIE['uName']."歡迎回來!!!";
        echo "<a href = 'cookiedel.php'>刪除cookie</a>";
    }

    ?>

    <form action ="logincheck.php" method="POST">
        ID: <input type="text" name="uName" required><br>
        Password: <input type="password" name="uPWD" required><br>
        <input type="submit" value="登入">
    </form>

</body>
</html> 