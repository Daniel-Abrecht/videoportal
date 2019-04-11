<?php
require("db.php");

$st = $db->prepare("SELECT * FROM video WHERE id=? LIMIT 1");
$st->execute([$_GET['video']]);
$video = $st->fetchAll()[0];

$st = $db->prepare("SELECT p.name, vp.value FROM video_property AS vp INNER JOIN property AS p ON p.id=vp.property WHERE vp.video=? ");
$st->execute([$_GET['video']]);
$video['property'] = [];
foreach($st->fetchAll() as $property)
  $video['property'][$property['name']] = $property;

?><!doctype html>
<html>
<head>
  <title>Videoportal - <?php echo htmlentities($video['name']); ?></title>
<?php include("head.php"); ?>
</head>
<body>
  <div class="header">
    <a href="."><img src="image/favicon.svg" /></a>&ensp;
    <a href="portal.php?collector=<?php echo urlencode($video['property']['collector']['value']); ?>"><img src="<?php echo htmlentities(@glob("image/collector/".$video['property']['collector']['value'].".*")[0]); ?>" alt="<?php echo htmlentities(ucfirst($video['property']['collector']['value'])); ?>" /></a>&ensp;
    <a href="view.php?video=<?php echo urlencode($_GET['video']); ?>"><?php echo htmlentities($video['name']); ?></a>
  </div>
  <div class="main player">
<?php
include("player.php");
?>
    <div class="videoinfo">
      <div class="info name"><?php echo htmlentities($video['name']); ?></div>
<?php
foreach($video['property'] as $p){
  if($p['name'] == 'collector')
    continue;
  echo '<div class="info">'.htmlentities(ucfirst($p['name'])).': '.htmlentities($p['value']).'</div>';
}
?>
    </div>
  </div>
</body>
</html>
