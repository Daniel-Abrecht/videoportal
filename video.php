<?php

error_reporting( 0 );
@ini_set('zlib.output_compression', 'Off');
@ini_set('output_buffering', 'Off');
@ini_set('output_handler', '');
@apache_setenv('no-gzip', 1);

if(!isset($_GET['id'])){
  http_response_code(404);
  die("File found found!");
}

require("db.php");

$st = $db->prepare("SELECT * FROM source WHERE id=?");
$st->execute([$_GET['id']]);
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

if( $begin>0 || $end<$size ){
  header('HTTP/1.0 206 Partial Content');
}else{
  header('HTTP/1.0 200 OK');
}

//header("Cache-Control: no-cache, must-revalidate");
//header("Pragma: no-cache");
header("Connection: keep-alive", true);
header("Content-Type: $source[mime]");
header('Accept-Ranges: bytes');
header("Content-Range: bytes $begin-".($end-1)."/$size");
header('Content-Length:'.($end-$begin));
header("Last-Modified: $time");

$cur=$begin;
fseek($fm,$begin);

while( !feof($fm) && $cur<$end && (connection_status()==0) ){
  print fread($fm,min(1024*16,$end-$cur));
  $cur += 1024*16;
}

fclose($fm);
exit();

?>
