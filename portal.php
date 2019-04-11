<?php
require("db.php");

function arr1D2query($name, $x){
  if(!$x)
    return '';
  $parts = [];
  foreach($x as $key => $value){
    if(!is_string($key) || ($value == null || !is_string($value)))
      continue;
    $x = urlencode($name).'['.urlencode($key).']';
    if($value != null)
      $x .= '=' . urlencode($value);
    $parts[] = $x;
  }
  return implode('&',$parts);
}

?><!doctype html>
<html>
<head>
  <title>Videoportal - <?php echo htmlentities(ucfirst($_GET['collector'])); ?></title>
<?php include("head.php"); ?>
</head>
<body>
  <div class="header">
    <a href="."><img src="image/favicon.svg" /></a>&ensp;
    <a href="portal.php?collector=<?php echo urlencode($_GET['collector']); ?>"><img src="<?php echo htmlentities(@glob("image/collector/$_GET[collector].*")[0]); ?>" alt="<?php echo htmlentities(ucfirst($_GET['collector'])); ?>" /></a>&ensp;
<?php
  $fullurl = 'portal.php?collector='.urlencode($_GET['collector']);
  if(is_string(@$_GET['category'])){
    $fullurl = 'portal.php?collector='.urlencode($_GET['collector']).'&category='.urlencode($_GET['category']);
    echo '<a href="portal.php?collector='.urlencode($_GET['collector']).'&category='.urlencode($_GET['category']).'">'.htmlentities(ucfirst($_GET['category'])).'</a>&ensp;';
  }else if(is_array(@$_GET['category'])){
    $fullurl = 'portal.php?collector='.urlencode($_GET['collector']).'&'.arr1D2query('category',$_GET['category']);
    foreach($_GET['category'] as $category => $name){
      echo '<a href="portal.php?collector='.urlencode($_GET['collector']).'&category='.urlencode($category).'">'.htmlentities(ucfirst($category)).'</a>: ';
      echo '<a href="portal.php?collector='.urlencode($_GET['collector']).'&category['.urlencode($category).']='.urlencode($name).'">'.htmlentities(ucfirst($name)).'</a>&ensp;';
    }
  }
?>
  </div>
  <div class="sidebar">
<?php

$st = $db->prepare("SELECT DISTINCT p.name, nvp.value FROM property AS op INNER JOIN video_property AS vp ON op.id = vp.property AND op.name = 'collector' and vp.value=? INNER JOIN video_property AS nvp ON nvp.video=vp.video AND vp.property != nvp.property INNER JOIN property AS p ON nvp.property=p.id /*AND nvp.identifying*/ ORDER BY p.name ASC, nvp.value ASC");
$st->execute([$_GET['collector']]);
$tmp = $st->fetchAll(\PDO::FETCH_NUM);
$categories = [];
foreach($tmp as list($key,$value)) {
  if(!isset($categories[$key]))
    $categories[$key] = [];
  $categories[$key][] = $value;
}

$i=0;
foreach($categories as $name => $entries){
  $i += 1;
  if(count($entries) == 0 || (count($entries) == 1 && !$entries[0])){
?>
  <div class="category">
    <span class="name">
      <a href="portal.php?collector=<?php echo urlencode($_GET['collector']); ?>&category[<?php echo urlencode($name); ?>]">
        <?php echo htmlentities(ucfirst($name)); ?>
      </a>
    </span>
  </div>
<?php
  }else{
?>
  <div class="category">
    <input class="categoryentrylistextended" type="checkbox" id="categoryentrylistextended_<?php echo $i; ?>" />
    <span class="name">
      <a href="portal.php?collector=<?php echo urlencode($_GET['collector']); ?>&category=<?php echo urlencode($name); ?>">
        <?php echo htmlentities(ucfirst($name)); ?>
      </a><label class="categoryentrylistextender" for="categoryentrylistextended_<?php echo $i; ?>"></label>
    </span>
    <div class="categoryentrylist">
<?php
  foreach($entries as $entry){
?>
      <a class="categoryentry" href="portal.php?collector=<?php echo urlencode($_GET['collector']); ?>&category[<?php echo urlencode($name); ?>]=<?php echo urlencode($entry); ?>">
        <span class="name"><?php echo htmlentities(ucfirst($entry)); ?></span>
      </a>
<?php
  }
?>
    </div>
    <label class="categoryentrylistextender" for="categoryentrylistextended_<?php echo $i; ?>"></label>
  </div>
<?php
  }
}
?>
  </div>
  <div class="main">
<?php

if(is_string(@$_GET['category'])){
  echo '<div class="list">';
  $category = $_GET['category'];
  foreach($categories[$category] as $name){
?>
  <a class="entry category" href="?collector=<?php echo urlencode($_GET['collector']); ?>&category[<?php echo urlencode($category); ?>]=<?php echo urlencode($name); ?>">
    <span class="image">
      <img src="thumbnail.php?category=<?php echo urlencode($category); ?>&value=<?php echo urlencode($name); ?>" />
    </span>
    <span class="name"><?php echo htmlentities(ucfirst($name)); ?></span>
  </a>
<?php
  }
}else{
  $page = intval(@$_GET['page']);
  $limit = 120; // highly composite number: https://oeis.org/A002182
  $categories = [];
  if(is_array(@$_GET['category']))
    $categories = $_GET['category'];
  $query = 'FROM video AS v';
  $args = [];
  $i = 0;
  foreach($categories as $name => $value){
    $query .= " INNER JOIN video_property AS vp$i ON vp$i.video=v.id";
    if(!!$value){
      $query .= " AND vp$i.value=?";
      $args[] = $value;
    }
    $query .= " INNER JOIN property AS p$i ON vp$i.property=p$i.id AND p$i.name=?";
    $args[] = $name;
  }
  $query .= ' ORDER BY date DESC';
  $st = $db->prepare('SELECT count(v.id) AS count '.$query);
  $st->execute($args);
  $count = $st->fetchAll(\PDO::FETCH_ASSOC)[0]['count'];
  $pages = ceil($count/$limit);
  if($page >= $pages)
    $page = $pages - 1;
  $query .= ' LIMIT ? OFFSET ?';
  $args[] = $limit;
  $args[] = $page * $limit;
  $st = $db->prepare('SELECT v.* '.$query);
  $st->execute($args);
  $videos = $st->fetchAll(\PDO::FETCH_ASSOC);

function pagination_link($i){
  global $fullurl, $page;
  return '<a href="'.$fullurl.'&page='.urlencode($i).'"'.($page==$i?' class="currentpage"':'').'>'.($i+1).'</a>';
}

function pagination($min_start=3, $min_center=3, $min_end=-1){
  global $pages, $page, $fullurl;
  if($min_end < 0)
    $min_end = $min_start;
  $s = '<div class="pagination">';
  if($pages > 1){
    if($page > 0)
      $s .= '<a href="'.$fullurl.'&page='.urlencode($page-1).'">&lt;&lt;</a>';
    if($pages < $min_center*2+1+$min_start+$min_end){
      for($i=0; $i<$pages; $i++)
        $s .= pagination_link($i);
    }else if($min_start+$min_center+1 > $page){
      for($i=0; $i<max($page+$min_center+1,$min_start); $i++)
        $s .= pagination_link($i);
      $s .= '<span class="spacing"> ... </span>';
      for($i=$pages-$min_end; $i<$pages; $i++)
        $s .= pagination_link($i);
    }else if($pages-$min_center-$min_end-1 <= $page){
      for($i=0; $i<$min_start; $i++)
        $s .= pagination_link($i);
      $s .= '<span class="spacing"> ... </span>';
      for($i=min($page-$min_center,$pages-$min_start); $i<$pages; $i++)
        $s .= pagination_link($i);
    }else{
      for($i=0; $i<$min_start; $i++)
        $s .= pagination_link($i);
      $s .= '<span class="spacing"> ... </span>';
      for($i=$page-$min_center; $i<$page+$min_center+1; $i++)
        $s .= pagination_link($i);
      $s .= '<span class="spacing"> ... </span>';
      for($i=$pages-$min_end; $i<$pages; $i++)
        $s .= pagination_link($i);
    }
    if($page < $pages-1)
      $s .= '<a href="'.$fullurl.'&page='.urlencode($page+1).'">&gt;&gt;</a>';
  }
  $s .= '</div>';
  return $s;
}

  echo pagination();

  echo '<div class="list">';

  foreach($videos as $video){
?>
    <a class="entry video" href="view.php?video=<?php echo urlencode($video['id']); ?>&<?php echo arr1D2query('category',$categories); ?>">
      <span class="image">
        <img src="thumbnail.php?video=<?php echo htmlentities($video['id']); ?>" />
      </span>
      <span class="name"><?php echo htmlentities(ucfirst($video['name'])); ?></span>
    </a>
<?php
  }
}
?>
    </div>
<?php
echo pagination();

?>
  </div>
</body>
</html>
