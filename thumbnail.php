<?php

if(!isset($_GET['video'])){
  http_response_code(404);
  die("File found found!");
}

require("db.php");

$st = $db->prepare("SELECT * FROM source WHERE type='I' AND video=? ORDER BY width DESC LIMIT 1");
$st->execute([$_GET['video']]);
$image = @$st->fetchAll(\PDO::FETCH_ASSOC)[0];

if(!$image || !file_exists($image['location'])){
  http_response_code(404);
  die("File found found!");
}

$time=filemtime($image['location']);
$tsstring = gmdate('D, d M Y H:i:s ', $time) . 'GMT';
$etag = "W/" . sha1($time . $image['location']);

$if_modified_since = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? $_SERVER['HTTP_IF_MODIFIED_SINCE'] : false;
$if_none_match = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? $_SERVER['HTTP_IF_NONE_MATCH'] : false;

if( ($if_none_match && $if_none_match == $etag)
 || ($if_modified_since && $if_modified_since == $tsstring)
){
  header('HTTP/1.1 304 Not Modified');
  exit();
}
else{
  header('ETag: '.$etag);
}
header("Last-Modified: $tsstring");
header("Cache-Control: max-age=31536000");

header("Content-Type: ".$image['mime']);
readfile($image['location']);

?>
