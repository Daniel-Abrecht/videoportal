<?php

require("db.php");

$st = $db->prepare("SELECT id FROM video ORDER BY RANDOM() LIMIT 1");
$st->execute();
$id = $st->fetch(\PDO::FETCH_ASSOC)['id'];
$location = "view.php?video=$id#current";
header('HTTP/1.1 307 Temporary Redirect');
header('Location: '.$location);
?>
