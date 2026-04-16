<?php
session_start();

$sID='A1133307'; $sPWD='0710';
$tID='julie'; $tPWD='12345';
$aID='boss'; $aPWD='676767';

$uID=$_POST['uName'];
$uPWD=$_POST['uPWD'];

$date=strtotime("+10 seconds", time());

if($uID==$sID && $uPWD==$sPWD){
    $_SESSION['login']='student';
    $_SESSION['uID']=$uID;
    setcookie("uName", $uID, $date);
    header("Refresh:0;url=student.php");

}elseif($uID==$tID && $uPWD==$tPWD){
    $_SESSION['login']='teacher';
    $_SESSION['uID']=$uID;
    setcookie("uName", $uID, $date);
    header("Refresh:0;url=teacher.php");

}elseif($uID==$aID && $uPWD==$aPWD){
    $_SESSION['login']='admin';
    $_SESSION['uID']=$uID;
    setcookie("uName", $uID, $date);
    header("Refresh:0;url=admin.php");

}else{
    echo "<h1>Login Failed!!!</h1>";
    header("Refresh:3;url=index.php");
}

?>