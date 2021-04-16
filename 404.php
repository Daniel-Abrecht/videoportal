<?php
http_response_code(404);
?><!doctype html>
<html>
<head>
  <title>Videoportal - <?php echo htmlentities($video['name']); ?></title>
<?php include("head.php"); ?>
</head>
<body>
  <table width="100%" height="100%"><tbody><tr><td align="center" valign="middle">
    <a href="."><img src="image/favicon.svg" style="max-width: 100vw; max-height: calc(100vh - 13em);"/></a><br/>
    <font style="font-size: 5em;">404 Not Found</font>
  </td></tr></tbody></table>
</body>
</html>
