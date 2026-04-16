<?php
//刪除指定名稱的陣列Cookie。

if(isset($_GET['id'])){

    $id = $_GET['id'];  // 把要刪除的商品編號抓下來
    setcookie("cart[$id]", '', time()-100);
}

header("Location: shoppingcart.php");
?>
