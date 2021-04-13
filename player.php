<?php

if(!isset($_GET['video'])){
  http_response_code(404);
  die("File found found!");
}

require("db.php");

$st = $db->prepare("SELECT * FROM source WHERE video=? ORDER BY type DESC, width DESC, duration DESC");
$st->execute([$_GET['video']]);
$sources = $st->fetchAll(\PDO::FETCH_ASSOC);

$all_audio = true;
$has_audio = false;
$all_media_sources = [];
foreach($sources as $source){
  if($source['type'] != 'I'){
    $all_media_sources[] = $source;
    if($source['type'] != 'A')
      $all_audio = false;
    if($source['type'] == 'A')
      $has_audio = true;
  }
}
if(!$has_audio)
  $all_audio = false;

function area_sort($a, $b){
  return $a['width']*$a['height'] - $b['width']*$b['height'];
}

usort($sources, 'area_sort');

if(isset($_COOKIE['screensize'])){
  $ss = explode("x",$_COOKIE['screensize']);
  rsort($ss);
  $remove_next = false;
  foreach($sources as $k => $source){
    if($source['type'] != "V" && $source['type'] != "A")
      continue;
    $vs = [$source['width'],$source['height']];
    rsort($ss);
    if($remove_next)
      unset($sources[$k]);
    if($vs[0] >= $ss[0] || $vs[1] >= $ss[1])
      $remove_next = true;
  }
  $sources = array_values($sources);
}

$sources = array_reverse($sources);

?>
<<?php echo $all_audio ? 'audio' : 'video'; ?> controls poster="thumbnail.php?video=<?php echo urlencode($_GET['video']); ?>&area=1000000" autoplay>
<?php
foreach($sources as $source)
switch($source['type']){
  case 'A':
  case 'V': {
    echo '  <source src="video.php?id='.urlencode($source['id']).'" '
           . ( $source['width'] && $source['height'] ? 'sizes="'.htmlentities($source['width'].'x'.$source['height']).'" ':'')
           . 'type="'.htmlentities($source['mime']).'" />';
    if($source['mime'] == 'video/x-matroska'){
      // Ugly hack for browsers supporting webm but ignoring matroska. Since webm is a subset of matroska, this may sometimes work.
      echo '  <source src="video.php?id='.urlencode($source['id']).'" '
             . ( $source['width'] && $source['height'] ? 'sizes="'.htmlentities($source['width'].'x'.$source['height']).'" ':'')
             . 'type="video/webm" />';
    }
  } break;
}
?>
</<?php echo $all_audio ? 'audio' : 'video'; ?>>
