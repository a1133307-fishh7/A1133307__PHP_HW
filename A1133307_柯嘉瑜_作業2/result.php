<?php
$nName=$_POST["nName"];
$mBirthday=$_POST["mBirthday"];
$mGender=$_POST["mGender"];
$mHeight=$_POST["mHeight"];
$mStyle=$_POST["mStyle"];
$mCity=$_POST["mCity"];
$mColor=$_POST["mColor"];    
$mRange=$_POST["mRange"];
$mEmail=$_POST["mEmail"];
$mComment=$_POST["mComment"];
$mCampCode=$_POST["mCampCode"];
?>


<HTML>
<HEAD>
    <TITLE>報名成功！</TITLE>
</HEAD>
<BODY bgcolor="#fffde7">

    <h1><font color="#FBC02D">報名成功！以下是您的報名資訊</font></h1>

    <?php
    echo "<b>我的姓名：</b>".$nName."<br>";
    echo "<b>我的生日:</b>" .$mBirthday."<br>";

    if ($mGender == "m"){
        echo "<b>我的性別：</b>男生<br>";
    } else {
        echo "<b>我的性別:</b>女生<br>";
    }

    echo "<b>我的身高:</b>".$mHeight."公分<br>";

    echo "<b>我感興趣的主題:</b>";
    if(!empty($mStyle)){
        foreach($mStyle as $style) {
            switch($style){
                case "retro":
                    echo "復古穿著 ";
                    break;
                case "minimal":
                    echo "極簡美學 ";
                    break;
                case "cute":
                    echo "可愛清純 ";
                    break;
            }
        }
    } else{
        echo "未選擇";
    }
    echo "<br>";  

    echo "<b>我從這裡出發:</b>".$mCity."<br>";
    echo "<b>我個人代表色:</b><font color='".$mColor."'>這個顏色</font>(".$mColor.")<br>";
    echo "<b>我對於美的自信度：</b>".$mRange."<br>";
    echo "<b>我的聯絡信箱：</b>".$mEmail."<br>";
    echo "<b>我想說....</b><br>";
    echo nl2br(strip_tags($mComment))."<br>";    //去掉惡意連結
    echo "<b>活動代碼：</b>".$mCampCode."<br>";
    ?>

    <hr width="500" color="FBC020">
    <center><a href ="login.php"><font size ="4" color="#A48111">返回登入首頁</font></a></center>


</BODY>
</HTML>