<?php

if(!isset($_GET['video'])){
  http_response_code(404);
  die("File found found!");
}

require("db.php");

$st = $db->prepare("SELECT * FROM source WHERE video=? ORDER BY type DESC, width DESC, duration DESC");
$st->execute([$_GET['video']]);
$sources = $st->fetchAll(\PDO::FETCH_ASSOC);

?>
<video controls poster="thumbnail.php?video=<?php echo urlencode($_GET['video']); ?>">
<?php
foreach($sources as $source)
switch($source['type']){
  case 'V': {
    echo '  <source src="video.php?id='.urlencode($source['id']).'" type="'.htmlentities($source['mime']).'" />';
  } break;
}
?>
</video>
