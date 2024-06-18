<?php
 
$con = mysqli_connect('localhost', 'wk', 'TkJb6kCTLGCbRyTM'); // 连接数据库服务器
if (!$con) {
    die('Could not connect: ' . mysqli_error($con));
}

mysqli_select_db($con, "wk"); // 选择数据库
mysqli_set_charset($con, "utf8"); // 设置编码，防止中文乱码

// 编写SQL语句，选择特定的15个列和时间列
$sql = "SELECT time, speed, temp, dire, hum FROM wkqn ORDER BY time DESC LIMIT 10";

$result = mysqli_query($con, $sql);

if (!$result) {
    printf("Error: %s\n", mysqli_error($con));
    exit();
}

$timedata = [];
$columnsData = array_fill(1, 15, []); // 创建一个包含15个空数组的数组，对应15个列

while ($row = mysqli_fetch_array($result)) {
    $timedata[] = $row['time'];
    for ($i = 1; $i <= 15; $i++) {
        $columnsData[$i][] = $row["column$i"];
    }
}

$jsondata = array_merge([$timedata], $columnsData); // 合并时间数据和其他列数据
echo json_encode($jsondata);

mysqli_close($con);

?>
