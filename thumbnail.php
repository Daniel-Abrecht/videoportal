<?php

if(!isset($_GET['video']) && (!isset($_GET['collector']) || !isset($_GET['category']) || !isset($_GET['value'])) ){
  http_response_code(404);
  die("File found found!");
}

require("db.php");

function set_cache_header($time, $id){
  $tsstring = gmdate('D, d M Y H:i:s ', $time) . 'GMT';
  $etag = "W/" . sha1($time . $id);

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
}

function getVideoThumbnail($id){
  global $db;
  $st = $db->prepare("SELECT * FROM source WHERE type='I' AND video=? ORDER BY generated ASC, width DESC LIMIT 1");
  $st->execute([$id]);
  return @$st->fetchAll(\PDO::FETCH_ASSOC)[0];
}

$area = +@$_GET['area'];
if(!$area || $area <= 0)
  $area = 500*500;

if(isset($_GET['video'])){

  $image = getVideoThumbnail($_GET['video']);

  if(!$image || !file_exists($image['location'])){
    http_response_code(404);
    die("File found found!");
  }

  set_cache_header(filemtime($image['location']),$image['location']);

  header("Content-Type: ".$image['mime']);

  if(!isset($_GET['area']) || $image['width'] * $image['height'] < $area){
    readfile($image['location']);
  }else{
    $im = @imagecreatefrompng($image['location']);
    if(!$im)
      $im = @imagecreatefromjpeg($image['location']);
    if(!$im)
      readfile($image['location']);
    if($im){
      $ratio = $image['width'] / $image['height'];
      $h = sqrt($area/$ratio);
      $w = $ratio * $h;
      $png = imagecreatetruecolor($w, $h);
      imagesavealpha($png, true);
      $trans_colour = imagecolorallocatealpha($png, 0, 0, 0, 127);
      imagefill($png, 0, 0, $trans_colour);
      imagecopyresized($png, $im, 0, 0, 0, 0, $w, $h, $image['width'], $image['height']);
      header("Content-type: image/png");
      imagepng($png);
    }
  }

}else if(isset($_GET['category'])){

  $max_image_count = 3;

  $st = $db->prepare("
    SELECT DISTINCT vps.video
    FROM property AS pc
      INNER JOIN video_property AS vpc ON vpc.property=pc.id AND pc.name='collector' AND vpc.value=?
      INNER JOIN video_property AS vps ON vps.video=vpc.video AND vps.value=?
      INNER JOIN property AS ps ON ps.id=vps.property AND ps.name=?
    LIMIT ?
    ");
  $st->execute([$_GET['collector'],$_GET['value'],$_GET['category'],$max_image_count]);
  $videos = @$st->fetchAll(\PDO::FETCH_NUM);

  $max_ratio = 0;
  $max_width = 0;

  $latest_date = 0;
  $latest = null;

  $thumbs = [];
  foreach($videos as list($video)){
    $thumb = getVideoThumbnail($video);
    if(!$thumb)
      continue;
    $date = filemtime($thumb['location']);
    if($latest_date < $date){
      $latest_date = $date;
      $latest = $thumb;
    }
    $thumbs[] = $thumb;
  }
  set_cache_header($latest_date, $latest['location']);

  $thumbnails = [];
  foreach($thumbs as $thumb){
    $im = @imagecreatefrompng($thumb['location']);
    if(!$im)
      $im = @imagecreatefromjpeg($thumb['location']);
    if(!$im)
      continue;
    $thumb['im'] = $im;
    $thumb['ratio'] = $thumb['width'] / $thumb['height'];
    $max_ratio = max($max_ratio, $thumb['ratio']);
    $thumbnails[] = $thumb;
  }

  $thumbnail_count = count($thumbnails);
  if(!$thumbnail_count){
    http_response_code(404);
    die("File found found!");
  }

  $h = sqrt($area/$max_ratio);
  $w = $max_ratio * $h;

  $png = imagecreatetruecolor($w, $h);
  imagesavealpha($png, true);

  $trans_colour = imagecolorallocatealpha($png, 0, 0, 0, 127);
  imagefill($png, 0, 0, $trans_colour);

  $white = imagecolorallocate($png, 255, 255, 255);

  $i = 0;
  $gd = min($w,$h) / 5;
  $d = $gd / ($thumbnail_count-1);
  foreach($thumbnails as $thumb){
    $ri = $thumbnail_count-$i-1;
    $x = $ri * $d;
    $y = $ri * $d;
    $dw = ($h - $gd) * $thumb['ratio'];
    $dh = ($h - $gd);
    $dx = ($w-$gd - $dw) / 2 + $x;
    $dy = ($h-$gd - $dh) / 2 + $y;
    imagecopyresized($png, $thumb['im'], $dx, $dy, 0, 0, $dw, $dh, $thumb['width'], $thumb['height']);
    imagerectangle($png, $dx, $dy, $dx+$dw, $dy+$dh, $white);
    $i += 1;
  }

  header("Content-type: image/png");
  imagepng($png);

}

?>
