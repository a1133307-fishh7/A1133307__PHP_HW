<?php
//顯示選購商品下拉式清單的表單處理。
session_start();

$_SESSION['catalog'] = [
    'S001' => ['name' => '粉色格子襯衫', 'price' => 590],
    'S002' => ['name' => '復古藍七分褲', 'price' => 790],
    'S003' => ['name' => '黑色波點上衣', 'price' => 490]
];
?>

<html>
<head>
    <title>服飾購物商城</title>
</head>

<body bgcolor="#f7f2c6">
    <form action="savecart.php" method="POST">

        <font color="#5fa7cb"><b>選擇商品：</b></font>
        <select name="item">
            <option value="S001">粉色格子襯衫 - $590</option>
            <option value="S002">復古藍七分褲 - $790</option>
            <option value="S003">黑色波點上衣 - $490</option>
        </select>

        <input type="number" placeholder="購買數量" name="number" min="1" required>
        <input type="submit" value="訂購">

        <hr color="#676d75">

        | <a href = "catalog.php">商品目錄</a> | <a href= "shoppingcart.php">檢視購物車</a> |
    </form>

</body>
</html>