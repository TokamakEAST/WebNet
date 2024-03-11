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
 
$sql="SELECT * FROM temp ORDER BY time desc LIMIT 10";                      //数据库下属表名
 
$result = mysqli_query($con,$sql);

if (!$result) 
{
printf("Error: %s\n", mysqli_error($con));
exit();
}

while($row = mysqli_fetch_array($result))
{
    $timedata[] = $row['time'];
    $tempdata[] = $row['temp'];
    $humdata[] = $row['hum'];
    
}
    $jsondata = array($timedata,$tempdata,$humdata);
echo json_encode($jsondata);


mysqli_close($con);
?>
