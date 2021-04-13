<?php
require("db.php");
require("utils.php");
$s_category = @$_GET['category'];

if(isset($_GET['q']) && (!is_string($_GET['q']) || $_GET['q'] == ''))
  unset($_GET['q']);

?><!doctype html>
<html>
<head>
  <title>Videoportal - <?php echo htmlentities(ucfirst($_GET['collector'])); ?></title>
  <script src="js/optimizer.js"></script> <!-- Optional stuff -->
<?php include("head.php"); ?>
</head>
<body>
  <div class="header">
    <a href="."><img src="image/favicon.svg" /></a>&ensp;
    <a href="portal.php?collector=<?php echo urlencode($_GET['collector']); ?>"><img src="<?php echo str_replace("%2F","/",urlencode(@glob("image/collector/$_GET[collector].*")[0])); ?>" alt="<?php echo htmlentities(ucfirst($_GET['collector'])); ?>" /></a>&ensp;
<?php
  $fullurl = 'portal.php?collector='.urlencode($_GET['collector']);
  if(is_string($s_category)){
    $fullurl = 'portal.php?collector='.urlencode($_GET['collector']).'&category='.urlencode($s_category);
    echo '<a href="portal.php?collector='.urlencode($_GET['collector']).'&category='.urlencode($s_category).'">'.htmlentities(ucfirst($s_category)).'</a>&ensp;';
  }else if(is_array($s_category)){
    $fullurl = 'portal.php?collector='.urlencode($_GET['collector']).'&'.arr1D2query('category',$s_category);
    foreach($s_category as $category => $name){
      if($name){
        if($category != 'series')
          echo '<a href="portal.php?collector='.urlencode($_GET['collector']).'&category='.urlencode($category).'">'.htmlentities(ucfirst($category)).'</a>: ';
        echo '<a href="portal.php?collector='.urlencode($_GET['collector']).'&category['.urlencode($category).']='.urlencode($name).'">'.htmlentities($name).'</a>&ensp;';
      }else{
        echo '<a href="portal.php?collector='.urlencode($_GET['collector']).'&category['.urlencode($category).']">'.htmlentities(ucfirst($category)).'</a>';
      }
    }
  }
  if(isset($_GET['q']) && is_string($_GET['q'])){
    $es_q = '&q='.urlencode($_GET['q']);
  }else{
    $es_q = '';
  }
  $fullurl .= $es_q;
  $fullurlparts = spliturl($fullurl);
?>
  </div>
  <div class="sidebar">
    <form class="search" method="get" action="<?php echo htmlentities($fullurlparts['base']); ?>">
      <?php
        foreach($fullurlparts['query'] as $name => $value){
          if($name == 'q' || $name == 'page')
            continue;
          echo '<input type="hidden" name="'.htmlentities($name).'" '.($value?'value="'.htmlentities($value).'" ':'').'/>';
        }
      ?>
      <input type="search" name="q" placeholder="Suche" <?php if(isset($_GET['q'])) echo 'value="'.htmlentities($_GET['q']); ?>" /><!--
      --><input type="submit" value="&#x1F50D;"/>
    </form>
<?php
$args = [];
$query = "
  SELECT
    p.name,
    nvp.value,
    nvp.identifying,
    nvp.is_category,
    v.id AS random_video_id,
    v.name AS random_video_name,
    count(v.id) AS video_count
  FROM property AS op
    INNER JOIN video_property AS vp ON op.id = vp.property AND op.name = 'collector' and vp.value=?
    INNER JOIN video_property AS nvp ON nvp.video=vp.video AND vp.property != nvp.property
    INNER JOIN property AS p ON nvp.property=p.id
    INNER JOIN video AS v ON v.id=nvp.video
  WHERE ( nvp.is_category OR p.name = ? )
";
$args[] = $_GET['collector'];
$args[] = $s_category && is_string($s_category) ? $s_category : 'series';
if(isset($_GET['q']) && is_string($_GET['q'])){
  $query .= ' AND v.name LIKE ?';
  $args[] = '%'.$_GET['q'].'%';
}
$query .= "
  GROUP BY nvp.property, nvp.value
  ORDER BY p.name ASC, nvp.value ASC
";
$st = $db->prepare($query);
$st->execute($args);
$tmp = $st->fetchAll(\PDO::FETCH_ASSOC);
$properties = [];
$tags = [];
$categories = [];
$category_tags = [];
foreach($tmp as $entry) {
  if($entry['value']){
    if(!isset($properties[$entry['name']]))
      $properties[$entry['name']] = [];
    $properties[$entry['name']][] = $entry;
    if($entry['is_category']){
      if(!isset($categories[$entry['name']]))
        $categories[$entry['name']] = [];
      $categories[$entry['name']][] = $entry;
    }
  }else{
    if(!in_array($entry['name'],$tags))
      $tags[] = $entry['name'];
    if($entry['is_category']){
      if(!in_array($entry['name'],$category_tags))
        $category_tags[] = $entry['name'];
    }
  }
}

if(!$s_category && isset($properties['series']))
  $s_category='series';

$i=0;
foreach($category_tags as $name){
  $i += 1;
?>
  <div class="category">
    <span class="name">
      <a href="portal.php?collector=<?php echo urlencode($_GET['collector']); ?>&category[<?php echo urlencode($name).$es_q; ?>]">
        <?php echo htmlentities(ucfirst($name)); ?>
      </a>
    </span>
  </div>
<?php
}
foreach($categories as $name => $entries){
  $i += 1;
?>
  <div class="category">
    <input class="categoryentrylistextended" type="checkbox" id="categoryentrylistextended_<?php echo $i; ?>" />
    <span class="name">
      <a href="portal.php?collector=<?php echo urlencode($_GET['collector']); ?>&category=<?php echo urlencode($name).$es_q; ?>">
        <?php echo htmlentities(ucfirst($name)); ?>
      </a><label class="categoryentrylistextender" for="categoryentrylistextended_<?php echo $i; ?>"></label>
    </span>
    <div class="categoryentrylist">
<?php
  foreach($entries as $entry){
?>
      <a class="categoryentry" href="portal.php?collector=<?php echo urlencode($_GET['collector']); ?>&category[<?php echo urlencode($name); ?>]=<?php echo urlencode($entry['value']).$es_q; ?>">
        <span class="name"><?php echo htmlentities($entry['value']); ?></span>
      </a>
<?php
  }
?>
    </div>
    <label class="categoryentrylistextender" for="categoryentrylistextended_<?php echo $i; ?>"></label>
  </div>
<?php
}
?>
  </div>
  <div class="main">
<?php

$limit = 120; // highly composite number: https://oeis.org/A002182
$page = intval(@$_GET['page']);
$pages = 0;

if(is_string(@$s_category)){
  echo '<div class="list">';
  $category = $s_category;
  $i = 0;
  foreach($properties[$category] as $entry){
    if($entry['video_count'] == 0)
      continue;
    $i += 1;
    if($i <= $page * $limit)
      continue;
    if($i > ($page+1) * $limit)
      continue;
    if($entry['video_count'] > 1){
?>
  <a class="entry category" href="?collector=<?php echo urlencode($_GET['collector']); ?>&category[<?php echo urlencode($category); ?>]=<?php echo urlencode($entry['value']).$es_q; ?>">
    <span class="image">
      <img src="thumbnail.php?collector=<?php echo urlencode($_GET['collector']); ?>&category=<?php echo urlencode($category); ?>&value=<?php echo urlencode($entry['value']); ?>&area=125000" />
    </span>
    <span class="name"><?php echo htmlentities(ucfirst($entry['value'])); ?></span>
  </a>
<?php
    }else{
?>
    <a class="entry video" href="view.php?video=<?php echo urlencode($entry['random_video_id']); ?>&<?php echo arr1D2query('category',$categories).$es_q; ?>#current">
      <span class="image">
        <img src="thumbnail.php?video=<?php echo urlencode($entry['random_video_id']); ?>&area=125000" />
      </span>
      <span class="name"><?php if($category != 'series') echo htmlentities(ucfirst($entry['value']).": "); echo htmlentities(ucfirst($entry['random_video_name'])); ?></span>
    </a>
<?php
    }
  }
  echo '</div>';
  $pages = ceil($i/$limit);
}else{
  $categories = [];
  if(is_array(@$s_category))
    $categories = $s_category;
  $categories['collector'] = $_GET['collector'];
  $query = 'FROM video AS v';
  $args = [];
  $i = 0;
  foreach($categories as $name => $value){
    $i += 1;
    $query .= " INNER JOIN video_property AS vp$i ON vp$i.video=v.id AND vp$i.value=?";
    $args[] = $value;
    $query .= " INNER JOIN property AS p$i ON vp$i.property=p$i.id AND p$i.name=?";
    $args[] = $name;
  }
  if(isset($_GET['q']) && is_string($_GET['q'])){
    $query .= ' WHERE v.name LIKE ?';
    $args[] = '%'.$_GET['q'].'%';
  }
  $query .= ' ORDER BY v.date DESC, v.name DESC';
  $st = $db->prepare('SELECT count(v.id) AS count '.$query);
  $st->execute($args);
  $count = $st->fetchAll(\PDO::FETCH_ASSOC)[0]['count'];
  $pages = ceil($count/$limit);
  if($page >= $pages)
    $page = $pages - 1;
  $query .= ' LIMIT ? OFFSET ?';
  $args[] = $limit;
  $args[] = $page * $limit;
  $st = $db->prepare('SELECT v.*, EXISTS(SELECT * FROM `source` WHERE video=v.id AND type=\'I\') AS has_thumb '.$query);
  $st->execute($args);
  $videos = $st->fetchAll(\PDO::FETCH_ASSOC);

  echo pagination($pages, $page, $fullurl);

  echo '<div class="list">';

  foreach($videos as $video){
?>
    <a class="entry video<?php echo $video['has_thumb'] ? '' : ' nothumb'; ?>" href="view.php?video=<?php echo urlencode($video['id']); ?>&<?php echo arr1D2query('category',$categories).$es_q; ?>#current">
      <?php if($video['has_thumb']){ ?>
        <span class="image">
          <img src="thumbnail.php?video=<?php echo urlencode($video['id']); ?>&area=125000" />
        </span>
      <?php } ?>
      <span class="name"><?php echo htmlentities(ucfirst($video['name'])); ?></span>
    </a>
<?php
  }
  echo '</div>';
}
echo pagination($pages, $page, $fullurl);
?>
  </div>
</body>
</html>
