<?php
require("db.php");
?><!doctype html>
<html>
<head>
  <title>Videoportal</title>
<?php include("head.php"); ?>
</head>
<body>
  <div class="header">Medienart/quelle auswählen</div>
  <div class="main list">
<?php

$collectors = array_map("current", $db->query("SELECT DISTINCT vp.value as collector FROM property AS p LEFT JOIN video_property AS vp ON p.id = vp.property WHERE p.name = 'collector'")->fetchAll(\PDO::FETCH_NUM));

foreach($collectors as $collector){
?>
    <a class="entry collector" href="portal.php?collector=<?php echo urlencode($collector); ?>">
      <span class="image">
        <img src="<?php echo htmlentities(@glob("image/collector/$collector.*")[0]); ?>" />
      </span>
      <span class="name"><?php echo htmlentities(ucfirst($collector)); ?></span>
    </a>
<?php
}

?>
  </div>
</body>
</html>
