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

// Setting a cookie to sort videos by resolution in player.php
// Even browsers which support the sizes attibute in videos often just use the first source otherwise,
// even if it's unreasonably large or small for the devices screen size
var screensize = [screen.width*(window.devicePixelRatio||1), screen.height*(window.devicePixelRatio||1)].join('x');
setCookie("screensize", screensize, 1);
