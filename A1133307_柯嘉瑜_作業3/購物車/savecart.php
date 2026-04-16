<?php
//啟用Session交談期後，就可以取得catalog.php建立的Session變數值來存入購物車。
session_start();

if(isset($_POST['item']) && isset($_POST['number'])){

    $id = $_POST['item'];      //抓取商品編號 (例如 S001)
    $qty = $_POST['number'];    //抓取購買數量 (對應表單的 name="number")

    $date = strtotime("+7 days", time());

    setcookie("cart[$id]", $qty, $date);    // 新增商品到購物車：將商品數量裝進 cart 陣列裡
}

header("Refresh:0;url=shoppingcart.php");
?>