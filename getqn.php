<?php
 
$con = mysqli_connect('localhost','wk','TkJb6kCTLGCbRyTM');//链接数据库服务器
if (!$con)
{
    die('Could not connect: ' . mysqli_error($con));
}
// 选择数据库
mysqli_select_db($con,"wk");                           //数据库名
// 设置编码，防止中文乱码
mysqli_set_charset($con, "utf8");
 
$sql="SELECT * FROM wkqn ORDER BY time desc LIMIT 10";                      //数据库下属表名

$result = mysqli_query($con,$sql);

if (!$result) 
{
printf("Error: %s\n", mysqli_error($con));
exit();
}
while($row = mysqli_fetch_array($result))
{
    $timedata[] = $row['time'];
    $spedata[] = $row['speed'];
    $temdata[] = $row['temp'];
    $dirdata[] = $row['dire'];
    // $dirdata[] = number_format($row['dire'] / 18.4, 2);
    $humdata[] = $row['hum'];
    // $humdata[] = number_format($row['hum'] / 10, 2);
}
    $jsondata = array($timedata,$spedata,$temdata,$dirdata,$humdata);
    echo json_encode($jsondata);

mysqli_close($con);

?>
