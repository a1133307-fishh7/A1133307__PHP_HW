<?php
setcookie('uName', '', time()-100);
echo "以成功清除COOKIE! 3秒後返回首頁...";
header("Refresh:3;url=index.php");
?>