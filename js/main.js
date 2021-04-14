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

function init(){
  var player = document.getElementById("player");
  if(player && !player.init){
    player.init = true;
    player.addEventListener('ended', function(event){
      var p = document.querySelector(".sidebarlist .entry.current");
      p = p && p.previousElementSibling;
      if(p){
        //p.click()
        overload(p.href);
      }
    });
  }
}
init();


//// Hack for browsers which just don't ever allow autoplay (iOS) ////

function overload(url, nohistory){
  // Synchroneous request. "obsolete", but let's us keep the click event context, without which we can't call play()
  var xhr = new XMLHttpRequest();
  xhr.open("GET", url, false);
  xhr.send();
  if(xhr.status != 200)
    return false;
  let doc = new DOMParser().parseFromString(xhr.responseText, 'text/html');
  if(!doc || !doc.body)
    return false;
  if(!nohistory)
    history.pushState(null, doc.title||null, url);
  if(doc.title)
    document.title = doc.title;
  var div = document.createElement("div");
  div.innerHTML = doc.body.innerHTML; // Import nodes
  var new_player = div.querySelector("#player");
  if(!new_player)
    return false;
  var old_player = document.body.querySelector("#player");
  // If we deal with an "ended" event, we can't .play() on a different media element, at least not on iOS...
  if(old_player){
    old_player.play(); // Start or restart old player if any
    old_player.innerHTML = new_player.innerHTML; // Set sources
    old_player.className = new_player.className;
    old_player.poster = new_player.poster;
    old_player.load();
  }
  // never stop a playing player...
  var first_new = div.childNodes[0];
  while(div.childNodes.length)
    document.body.appendChild(div.childNodes[0]);
  // Now, all stuff of the new document is part of the old one, swap out the new player with the old probably already playing one if any
  if(old_player)
    new_player.parentElement.replaceChild(old_player, new_player);
  new_player = document.body.querySelector("#player"); // Get whatever the new player will be now
  new_player.play(); // Maybe there was no old player, and this is a click event?
  // Remove the old stuff
  while(document.body.childNodes[0] != first_new)
    document.body.removeChild(document.body.childNodes[0]);
  if(location.hash){
    var active = document.getElementById(location.hash.slice(1));
    if(active && active.scrollIntoView)
      active.scrollIntoView(true);
  }
  var scripts = document.body.querySelectorAll("script");
  for(var i=0, n=scripts.length; i<n; i++) try {
    eval(scripts[i].innerText);
  } catch(error) {
    console.error(error);
  }
  init();
  return true;
}

addEventListener("popstate", function(event){
  if(!/\/view.php\?/.test(location.href) || !overload(location.href, true))
    location.href = location.href;
});

addEventListener("click", function(event){
  var anchor = event.target && event.target.closest("a");
  if(!anchor)
     return;
  if(!/\/view.php\?/.test(anchor.href))
    return;
  if(anchor.href == location.href)
    return;
  if(overload(anchor.href))
    event.preventDefault();
});

////////
