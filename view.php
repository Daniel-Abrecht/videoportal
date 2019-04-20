<?php
require("db.php");
require("utils.php");

$st = $db->prepare("SELECT * FROM video WHERE id=? LIMIT 1");
$st->execute([$_GET['video']]);
$video = $st->fetchAll()[0];

$st = $db->prepare("SELECT p.name, vp.value FROM video_property AS vp INNER JOIN property AS p ON p.id=vp.property WHERE vp.video=? ");
$st->execute([$_GET['video']]);
$video['property'] = [];
foreach($st->fetchAll() as $property){
  if(!isset($video['property'][$property['name']]))
    $video['property'][$property['name']] = [];
  $video['property'][$property['name']][] = $property;
}
?><!doctype html>
<html>
<head>
  <title>Videoportal - <?php echo htmlentities($video['name']); ?></title>
  <script src="js/optimizer.js"></script> <!-- Optional stuff -->
<?php include("head.php"); ?>
</head>
<body>
  <div class="header">
    <a href="."><img src="image/favicon.svg" /></a>&ensp;
    <a href="portal.php?collector=<?php echo urlencode($video['property']['collector'][0]['value']); ?>"><img src="<?php echo str_replace("%2F","/",urlencode(@glob("image/collector/".$video['property']['collector'][0]['value'].".*")[0])); ?>" alt="<?php echo htmlentities(ucfirst($video['property']['collector'][0]['value'])); ?>" /></a>&ensp;
    <a href="view.php?video=<?php echo urlencode($_GET['video']); ?>"><?php echo htmlentities($video['name']); ?></a>
  </div>
<?php

  $categories = [];
  if(is_array(@$_GET['category']))
    $categories = $_GET['category'];
  $categories['collector'] = $video['property']['collector'][0]['value'];

  $query = 'SELECT v.* FROM video AS v';
  $args = [];
  $i = 0;
  foreach($categories as $name => $value){
    $i += 1;
    $query .= " INNER JOIN video_property AS vp$i ON vp$i.video=v.id AND vp$i.value=?";
    $args[] = $value;
    $query .= " INNER JOIN property AS p$i ON vp$i.property=p$i.id AND p$i.name=?";
    $args[] = $name;
  }
  $query .= ' ORDER BY v.date DESC, v.name ASC';
  $st = $db->prepare($query);
  $st->execute($args);
  $videos = $st->fetchAll(\PDO::FETCH_ASSOC);
  $index = -1;
  foreach($videos as $i => $v){
    if($v['id'] == $video['id']){
      $index = $i;
      break;
    }
  }
  if($index != -1 && count($videos)>1){
?>
  <div class="sidebar sidebarlist">
<?php
    $n = 32;
    $s = max(0, $index - $n/2);
    $e = min($s + $n, count($videos));
    $s = max(0, $e - $n);
    for($i=$s; $i<$e; $i++){
      $v = $videos[$i];
?>
      <a class="entry video <?php if($i == $index) echo 'current'; ?>" <?php if($i == $index) echo 'name="current" id="current" '; ?>href="view.php?video=<?php echo urlencode($v['id']); ?>&<?php echo arr1D2query('category',$categories); ?>#current">
        <span class="image">
          <img src="thumbnail.php?video=<?php echo urlencode($v['id']); ?>" />
        </span>
        <span class="name"><?php echo htmlentities(ucfirst($v['name'])); ?></span>
      </a>
<?php
    }
?>
  </div>
<?php
  }

?>
  <div class="main player">
<?php
include("player.php");
?>
    <div class="videoinfo">
      <div class="info name"><?php echo htmlentities($video['name']); ?></div>
<?php
foreach($video['property'] as $name => $values){
  if($name == 'collector')
    continue;
  if(count($values) == 1){
?>
    <div class="info">
      <a href="portal.php?collector=<?php echo urlencode($video['property']['collector'][0]['value']).'&category='.urlencode($name); ?>"><?php echo htmlentities(ucfirst($name)); ?></a>:
      <a class="value" href="portal.php?collector=<?php echo urlencode($video['property']['collector'][0]['value']).'&category['.urlencode($name).']='.urlencode($values[0]['value']); ?>"><?php echo htmlentities($values[0]['value']); ?></a>
    </div>
<?php
  }else{
?>
    <div class="info">
      <a href="portal.php?collector=<?php echo urlencode($video['property']['collector'][0]['value']).'&category='.urlencode($name); ?>"><?php echo htmlentities(ucfirst($name)); ?></a>:
<?php
    foreach($values as $value){
?>
      <a class="value" href="portal.php?collector=<?php echo urlencode($video['property']['collector'][0]['value']).'&category['.urlencode($name).']='.urlencode($value['value']); ?>"><?php echo htmlentities($value['value']); ?></a>
<?php
    }
?>
    </div>
<?php
  }
}
?>
    </div>
    <div style="padding: 0.5rem 1rem;" id="sources">Sources:
<?php
foreach($all_media_sources as $source){
?>
      <a href="video.php?id=<?php echo urlencode($source['id']); ?>" style="color: #FFF;"><?php echo $source['mime'].' '.$source['width'].'x'.$source['height']; ?></a>
<?php
}
?>
    </div>
    <div style="padding: 0.5rem 1rem;" id="vlc">VLC: </div>
<script>
  var vlc = document.getElementById("vlc");
  var sources = Array.prototype.slice.call(document.querySelectorAll("#sources a"));
  for(var i=0; i<sources.length; i++){
    var url = 'vlc://'+sources[i].href;
    var a = sources[i].cloneNode(true);
    a.href = url;
    vlc.appendChild(document.createTextNode(' '));
    vlc.appendChild(a);
  }
</script>
  </div>
</body>
</html>
