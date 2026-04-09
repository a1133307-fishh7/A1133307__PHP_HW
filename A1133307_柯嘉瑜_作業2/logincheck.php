<?php
$fID="jiayu";
$fPWD="07100710";

if(isset($_POST["uID"]) && isset($_POST["uPWD"])){
    $uID=$_POST["uID"];
    $uPWD=$_POST["uPWD"];
    if($fID == $uID && $fPWD == $uPWD) {
        header("Location: CampForm.php");
    } else{
        echo "登入失敗，請再試一次";
        header("Refresh:3.5;url=login.php");
    }
}
?>