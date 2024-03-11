<?php
 
$con = mysqli_connect('localhost','masterlu_top','GHryLsZARMd5Arx5');//链接数据库服务器
if (!$con)
{
    die('Could not connect: ' . mysqli_error($con));
}
// 选择数据库
mysqli_select_db($con,"masterlu_top");                           //数据库名
// 设置编码，防止中文乱码
mysqli_set_charset($con, "utf8");
 
$sql="SELECT * FROM Gas ORDER BY time DESC LIMIT 2000";                       //数据库下属表名
 
$result = mysqli_query($con,$sql);



if (!$result) 
{
printf("Error: %s\n", mysqli_error($con));
exit();
}
 
echo
<<<html
        <td style="background-color:#FFFFFF;height:100px;width:200px;vertical-align:top;">
        
       <table class="mytable" border="1">
        <tr>
            <th>时间</th>                      
            <th>甲烷</th>                      
            <th>硫化氢</th>
        </tr> 
html;
 
while($row = mysqli_fetch_array($result))
{
    echo "<tr>";
    echo "<td width=\"300px\">" . $row['time'] . "</td>";               //字段名
    echo "<td width=\"300px\">" . $row['CH4'] . "</td>";               //字段名
    echo "<td width=\"300px\">" . $row['H2S'] . "</td>";                //字段名
    echo "</tr>";
}
echo "</table>";
 
mysqli_close($con);

