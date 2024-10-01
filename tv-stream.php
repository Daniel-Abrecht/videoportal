<?php
require("config.php");

if(!isset($tv_playlist))
  exit();

require("utils.php");
$programs = load_playlist($tv_playlist);

if(!@$_GET['channel']){
  header("Content-Type: application/json");
  echo json_encode(array_keys($programs));
  exit();
}

$channel = $_GET['channel'];
$url = @$programs[$channel];
if(!$url){
  http_response_code(404);
  exit();
}

header("Content-Type: video/mp4");
for($i=0; $i<10; $i++){
  $src = $url;
  if($i) $src .= "?noswitch";
  $cmd = [
    "ffmpeg",
    "-hide_banner", "-loglevel", "error",
    "-strict", "experimental",
    "-tcp_nodelay", "1",
    "-analyzeduration", "20", /*"-probesize", "300000",*/ "-flags", "low_delay",
    "-i", $src, /*"-pix_fmt", "yuv420p",*/
    /*"-f", "lavfi", "-i", "testsrc=size=1280x720", "-pix_fmt", "yuv420p", "-shortest",*/
    "-sn",
    "-c:v", "libx264",
    "-b:v:0", "3000K", "-maxrate:v:0", "3000K", "-bufsize:v:0", "3000K/2",
    /*"-b:v:1", "3000K", "-maxrate:v:1", "3000K", "-bufsize:v:1", "3000K/2",*/
    "-c:a", "aac", "-ar", "48000", "-b:a", "128k",
    "-g:v", "30", "-keyint_min:v", "30", "-sc_threshold:v", "0",
    "-muxpreload", "0", "-muxdelay", "0",
    "-fflags", "nobuffer", "-avoid_negative_ts", "make_zero",
    "-f", "mp4", "-movflags", "frag_keyframe+empty_moov+default_base_moof", "-frag_duration", "500",
    /*"-c", "copy", */ "-map", "0:v:0?", /*"-map", "1:v:0",*/ "-map", "0:a:0",
    "-preset", "veryfast", "-tune", "zerolatency",
    "-x264-params", "sliced-threads=0",
    "-"
  ];
//  echo implode(' ',array_map('escapeshellarg',$cmd)); break;
  passthru(implode(' ',array_map('escapeshellarg',$cmd)));
}
