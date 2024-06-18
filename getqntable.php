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
 
$sql="SELECT time,speed,temp,dire,hum FROM wkqn ORDER BY time desc LIMIT 15";                      //数据库下属表名

$result = mysqli_query($con,$sql);

if (!$result) 
{
printf("Error: %s\n", mysqli_error($con));
exit();
}

$spedata = [];
$tempdata = [];
$diredata = [];
$humdata = [];

while($row = mysqli_fetch_array($result))
{
    $spedata[] = $row['speed'];
    $tempdata[] = $row['temp'];
    $diredata[] = $row['dire'];
    $humdata[] = $row['hum'];
}
    $jsondata = array($spedata,$tempdata,$diredata,$humdata);
    echo json_encode($jsondata);

mysqli_close($con);

?>
