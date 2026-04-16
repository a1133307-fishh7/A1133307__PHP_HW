<?php
session_start();
session_destroy();
echo "以成功登出!2秒後返回首頁....";
header("Refresh:2;url=index.php");

?>