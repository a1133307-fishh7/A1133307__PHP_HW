<?php
//使用表格顯示所有陣列Cookie儲存的商品清單。 
session_start();
?>

<html>
<head>
    <title>我的購物車</title>
</head>
    
<body bgcolor="#fee8f5">

    <table border="1">
        <tr bgcolor="#cea8cb"><th>功能</th><th>編號</th><th>名稱</th><th>價格</th><th>數量</th></tr>
        
        <?php
        $total=0;

        if(isset($_COOKIE['cart'])){
            foreach($_COOKIE['cart'] as $id => $qty){
                if(isset($_SESSION['catalog'][$id])){
                    $name=$_SESSION['catalog'][$id]['name'];
                    $price=$_SESSION['catalog'][$id]['price'];

                    $total += ($price*$qty);

                    echo "<tr bgcolor='#bbd5e9'>";
                    echo "<td><a href='delete.php?id=" . $id . "'>刪除</a></td>";
                    echo "<td>". $id. "</td>";
                    echo "<td>". $name . "</td>";
                    echo "<td>". $price . "</td>";
                    echo "<td>". $qty. "</td>";
                    echo "</tr>";
                }
            }
        }
        ?>

        <tr bgcolor="#ffffff">
            <td colspan="5" align="right">總金額 = NT$<?php echo $total; ?>元</td>
        </tr> 
    
    </table>

    <hr color="#465363">
    | <a href = "catalog.php">商品目錄</a> | 
    <a href= "shoppingcart.php">檢視購物車</a> |

</body>
</html>