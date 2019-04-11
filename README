First of all, make sure to change the line `dbc = sqlite3.connect('/var/videodb/video.db')` to point to
your prefered location for the videodb.

The build-videodb.py script builds a video db for the videoportal in the maste branch of this repo.
It creates all tables if necessary. To remove and recreate everything, instead of just adding
new videos, you can use the rebuild option: `./build-videodb.py rebuild`.

In order for any videos to be added, you need to put a collector script into the `collector/` directory.

## Collector example

To give an example, let's say I have copied some youtube videos `/datas/media/video/mirror/youtube/`,
with the directory structure `{channel,playlist}/<name>/<videoname>.<extension>`. The following script can do that based on
the url in a file named "url" pointing to the channel/playlist in the corresponding folder:
```
#!/bin/bash

export PATH=/usr/local/bin:/usr/bin:/bin

cd /datas/media/video/mirror/youtube/

mirror(){
  for channel in */*/url
  do
    (
      set -x
      cd "$(dirname "$channel")"
      url="$(cat url)"
      youtube-dl --download-archive downloaded.txt --no-post-overwrites -f best -ciw -o "%(title)s.%(ext)s" --write-thumbnail -q "$url"
    )
  done
}

mirror
sleep 20
mirror
sleep 20
find -iname "*.part" -delete
mirror
```

To add such these Videos to the db, you can create a collector script in the "collectors/" directory.
For example "collectors/youtube.py": 
```
import os, traceback

base="/datas/media/video/mirror/youtube/"

def run(c):
  for type in sorted(os.listdir(base)):
    ptype=os.path.join(base,type)
    for instance in sorted(os.listdir(ptype)):
      pinstance=os.path.join(ptype,instance)
      for file in sorted(os.listdir(pinstance)):
        pfile=os.path.join(pinstance,file)
        name, ext = os.path.splitext(file)
        if not ext or ext == '' or ext == '.part':
          continue
        if ext not in ('.mp4','.webm','.jpg','.png'):
          continue
        date = None
        if ext in ('.mp4','.webm'):
          date = os.path.getmtime(pfile)
        try:
          video = c.addVideo(name, { type: instance }, date)
          c.addSource(video, pfile)
        except Exception:
          print("youtube collector, couldn't add video:", file)
          traceback.print_exc()
```

Of course, this s just an example. A collector for any directory structure could be created, and it doesnt matter where the videos came from.
