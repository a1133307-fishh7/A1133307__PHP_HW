<html>
<head>
    <title> 🌟 正式報名：開啟你的美學冒險 </title>
</head>

<body bgcolor="#FFFDE7"> <center>
    <h1><font color="#FBC02D"> 🌟 報名正式開始 🌟 </font></h1>
    <p> <font color="#A48111">請填寫以下資訊，讓我們幫你預留一個最有靈感的座位！</font> </p>
    
    <hr width="500" color="#FBC02D">
</center>

<form action="process.php" method="POST">
        <font color="#827717"><b>我的大名：</b></font>
        <input type="text" placeholder="請填寫本名" name="nName" required><br><br>

        <font color="#827717"><b>出生年月日：</b></font>
        <input type="date" name="mBirthday" required><br><br>

        <font color="#827717"><b>你的身份：</b></font>
        我是男生 <input type="radio" name="mGender" value="m"> 
        我是女生 <input type="radio" name="mGender" value="f" checked><br><br>

        <font color="#827717"><b>妳的身高 (cm)：</b></font>
        <input type="number" name="mHeight" value="160" min="100" max="250"><br><br>
        
        <font color="#827717"><b>感興趣的主題：</b></font><br>
        復古古著 <input type="checkbox" name="mStyle[]" value="retro">
        極簡美學 <input type="checkbox" name="mStyle[]" value="minimal">
        可愛清純 <input type="checkbox" name="mStyle[]" value="cute"><br><br>

        <font color="#827717"><b>你從哪裡出發？</b></font>
        <select name="nCity">
            <option value="Taipei">天龍國台北</option>
            <option value="Taichung">太陽很大台中</option>
            <option value="Kaohsiung" selected>熱情奔放高雄</option>
        </select><br><br>

        <font color="#827717"><b>你的個人代表色：</b></font>
        <input type="color" name="mColor" value="#FFEB3B"><br><br>

        <font color="#827717"><b>對美感的自信度：</b></font>
        0 <input type="range" name="mRange" min="0" max="100"> 100<br><br>

        <font color="#827717"><b>聯絡信箱：</b></font>
        <input type="email" name="mEmail" placeholder="記得寫對唷"><br><br>

        🌟 <b>對這個夏令營的風格宣告：</b><br>
        <textarea name="comment" rows="6" cols="40">在這個夏令營裡，我想拍出...</textarea>

        <br><br>
        <input type="hidden" name="mCampCode" value="FASHION_2026">

        <input type="submit" value=" 準備好了！送出報名 🚀 ">
        <input type="reset" value=" 哎呀寫錯了重來 ">
</form>

<center>
    <hr width="500" color="#FBC02D">
    <a href="A1133307_柯嘉瑜_作業1.php"> <font size="5" color="#A48111">返回活動簡介首頁</font> </a>
</center>

</body>
</html>