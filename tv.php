<?php
require("config.php");
if(!isset($tv_playlist))
  exit();
require("utils.php");
$programs = load_playlist($tv_playlist);
?><!doctype html>
<html>
<head>
  <title>Videoportal - TV</title>
<?php include("head.php"); ?>
  <script src="js/tv.js" defer></script>
</head>
<body>
  <div class="header">
    <a href="."><img src="image/favicon.svg" /></a>&ensp;
    <a href="tv.php"><img src="<?php echo htmlentities(@glob("image/collector/tv.*")[0]); ?>" alt="TV" /></a>
  </div>
  <div class="sidebar sidebarlist"><?php
foreach($programs as $name => $location){
?>
    <a class="entry video nothumb" href="#channel:<?php echo htmlentities($name); ?>">
      <span id="channel:<?php echo htmlentities($name); ?>"></span>
      <span>
        <span class="name"><?php echo htmlentities($name); ?></span>
      </span>
    </a>
<?php
}
?>
  </div>
  <div class="main player">
    <video class="video" controls autoplay id="player"></video>
  </div>
</body>
</html>
