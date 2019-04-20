"use strict";

// Optional stuff

function setCookie(name, value, days){
  var expires = "";
  if(days){
    var date = new Date();
    date.setTime(date.getTime() + (days*24*60*60*1000));
    expires = "; expires=" + date.toUTCString();
  }
  document.cookie = name + "=" + (value || "")  + expires + "; path=/";
}

if(!window.HTMLSourceElement || !HTMLSourceElement.prototype || !('sizes' in HTMLSourceElement.prototype)){
  // Browser doesn't support choosing ideal video source based on size.
  // This could lead to devices with low processing power or bad network connectivity to fail to play the video smoothly
  var screensize = [screen.width*(window.devicePixelRatio||1), screen.height*(window.devicePixelRatio||1)].join('x');
  // Setting a cookie to remove oversized sources at server side in player.php. Otherwise, the browser may still start loading the wrong source before it can be removed.
  setCookie("screensize", screensize, 1);
}
