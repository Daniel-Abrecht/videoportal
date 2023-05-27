<?php

require("db.php");

$RAND = null;
if(!$RAND) try { $st=$db->prepare("SELECT RANDOM() IS NOT NULL"); $st->execute(); $RAND='RANDOM'; } catch(Exception $e) {}
if(!$RAND) try { $st=$db->prepare("SELECT RAND() IS NOT NULL"  ); $st->execute(); $RAND='RAND';   } catch(Exception $e) {}

$st = $db->prepare("SELECT id FROM video ORDER BY $RAND() LIMIT 1");
$st->execute();
$id = $st->fetch(\PDO::FETCH_ASSOC)['id'];
$location = "view.php?video=$id#current";
header('HTTP/1.1 307 Temporary Redirect');
header('Location: '.$location);
?>
