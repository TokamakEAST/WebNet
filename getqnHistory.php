<?php
$con = mysqli_connect('localhost','wk','TkJb6kCTLGCbRyTM');//链接数据库服务器
if (!$con) {
    die('Could not connect: ' . mysqli_error($con));
}
// 选择数据库
mysqli_select_db($con, "wk"); //数据库名
// 设置编码，防止中文乱码
mysqli_set_charset($con, "utf8");

$sql = "SELECT * FROM wkqn ORDER BY time DESC LIMIT 2000"; //数据库下属表名

$result = mysqli_query($con, $sql);

if (!$result) {
    printf("Error: %s\n", mysqli_error($con));
    exit();
}

echo <<<html
 <td style="background-color:#FFFFFF;height:100px;width:200px;vertical-align:top;">
 <table class="mytable" border="1">
        <tr>
            <th>时间</th>
            <th>风速</th>
            <th>湿度</th>
            <th>风向</th>
            <th>温度</th>
        </tr>
html;

while($row = mysqli_fetch_array($result)) {
    // 将湿度除以一万后加60
    $hum = ($row['hum'] / 10000) + 60;
    // 将温度除以一万后加30
    $dire = ($row['dire'] / 10000) + 30;

    echo "<tr>";
    echo "<td width=\"300px\">" . $row['time'] . "</td>"; //字段名
    echo "<td width=\"300px\">" . $row['speed'] . "</td>"; //字段名
    echo "<td width=\"300px\">" . $hum . "</td>"; //字段名
    echo "<td width=\"300px\">" . $row['temp'] . "</td>"; //字段名
    echo "<td width=\"300px\">" . $dire . "</td>"; //字段名
    echo "</tr>";
}
echo "</table>";

mysqli_close($con);
?>
