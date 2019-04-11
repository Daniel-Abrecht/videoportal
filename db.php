<?php

$db = new PDO('sqlite:/var/videodb/video.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

?>
