<?php

error_reporting( 0 );
@ini_set('zlib.output_compression', 'Off');
@ini_set('output_buffering', 'Off');
@ini_set('output_handler', '');
@apache_setenv('no-gzip', 1);

$id = @explode('/',@$_SERVER['PATH_INFO'],3)[1];
if(!$id)
  $id = @$_GET['id'];

if(!$id){
  http_response_code(404);
  die("File found found!");
}

require("db.php");

$st = $db->prepare("SELECT s.*, v.name FROM source s LEFT JOIN video v ON s.video=v.id WHERE s.id=?");
$st->execute([$id]);
$source = @$st->fetchAll(\PDO::FETCH_ASSOC)[0];

if(!$source || !file_exists($source['location'])){
  http_response_code(404);
  die("File found found!");
}

$location = $source['location'];

$size = filesize($location);
$time = date('r',filemtime($location));

$fm = @fopen($location,'rb');
if(!$fm){
  header("HTTP/1.0 505 Internal server error");
  die("Failed to open video source");
}

$begin = 0;
$end = $size;

if(isset($_SERVER['HTTP_RANGE'])){
  if(preg_match('/^bytes=\h*(\d+)-(\d*)/i', $_SERVER['HTTP_RANGE'], $matches)){
    $begin=intval($matches[1]);
    if(!empty($matches[2])){
      $end=min(intval($matches[2])+1,$end);
    }/*else{
      $end = min($end, $begin+1024*1024*4);
    }*/
  }
}

//if( $begin>0 || $end<$size ){
  http_response_code(206);
//}else{
//  http_response_code(200);
//}



//header("Cache-Control: no-cache, must-revalidate");
//header("Pragma: no-cache");
header("Connection: keep-alive", true);
header("Content-Type: $source[mime]");
header('Accept-Ranges: bytes');
header("Content-Range: bytes $begin-".($end-1)."/$size");
header('Content-Length:'.($end-$begin));
header("Last-Modified: $time");

if($source['name']){
  $name = $source['name'] . '.' . pathinfo($source['location'], PATHINFO_EXTENSION);
}else{
  $name = basename($source['location']);
}
$name = preg_replace('/["\/%\\\\]/', '_', $name);
header('Content-Disposition: inline; filename="'.$name.'"');

$cur=$begin;
fseek($fm,$begin);

while( !feof($fm) && $cur<$end && (connection_status()==0) ){
  print fread($fm,min(1024*16,$end-$cur));
  $cur += 1024*16;
}

fclose($fm);
exit();

?>
