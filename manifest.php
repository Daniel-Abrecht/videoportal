<?php header("Content-Type: application/json"); ?>
{
  "short_name": "Videoportal",
  "name": "Videoportal",
  "icons": [
    {
      "src": "image/favicon.svg",
      "type": "image/svg+xml"
    },{
      "src": "image/favicon1024x1024.png",
      "sizes": "1024x1024",
      "type": "image/png"
    },{
      "src": "image/favicon512x512.png",
      "sizes": "512x512",
      "type": "image/png"
    },{
      "src": "image/favicon256x256.png",
      "sizes": "256x256",
      "type": "image/png"
    },{
      "src": "image/favicon192x192.png",
      "sizes": "192x192",
      "type": "image/png"
    },{
      "src": "image/favicon128x128.png",
      "sizes": "128x128",
      "type": "image/png"
    },{
      "src": "image/favicon64x64.png",
      "sizes": "64x64",
      "type": "image/png"
    },{
      "src": "image/favicon32x32.png",
      "sizes": "32x32",
      "type": "image/png"
    },{
      "src": "image/favicon16x16.png",
      "sizes": "16x16",
      "type": "image/png"
    }
  ],
  "start_url": "<?php echo dirname($_SERVER['PHP_SELF']); ?>/",
  "background_color": "#000",
  "display": "standalone",
  "scope": "<?php echo dirname($_SERVER['PHP_SELF']); ?>/",
  "theme_color": "#000"
}

