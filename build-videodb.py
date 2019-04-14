#!/usr/bin/python3

import os, sys, traceback
import math
import json
import magic
import sqlite3
import datetime
import subprocess

script_root = os.path.dirname(os.path.realpath(__file__))
collector_base = os.path.join(script_root, "collectors")

dbfile='/var/videodb/video.db'
thumbnails='/var/videodb/thumbnails/'

addVideos=False
generateThumbnails=True
removeLostSources=True
removeVideosWithoutSources=True
removeUnusedProperties=True

dbc = sqlite3.connect(dbfile)
db = dbc.cursor()

rebuild=(len(sys.argv) >= 2 and sys.argv[1] == 'rebuild')

if rebuild:
  db.execute('DROP TABLE IF EXISTS source')
  db.execute('DROP TABLE IF EXISTS property')
  db.execute('DROP TABLE IF EXISTS video_property')
  db.execute('DROP TABLE IF EXISTS video')

db.execute("PRAGMA foreign_keys = ON")

db.execute('''
  CREATE TABLE IF NOT EXISTS video (
    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
    name VARCHAR(32) NOT NULL,
    date DATETIME
  )
''');

db.execute('''
  CREATE TABLE IF NOT EXISTS source (
    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
    video INTEGER NOT NULL,
    location VARCHAR(32) NOT NULL UNIQUE,
    type VARCHAR(1) NOT NULL,
    mime VARCHAR(16) NOT NULL,
    width INTEGER NOT NULL,
    height INTEGER NOT NULL,
    duration INTEGER NOT NULL,
    generated INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY (video) REFERENCES video(id) ON DELETE CASCADE ON UPDATE CASCADE
  )
''');

db.execute('''
  CREATE TABLE IF NOT EXISTS property (
    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
    name VARCHAR(256) UNIQUE NOT NULL
  )
''');

db.execute('''
  CREATE TABLE IF NOT EXISTS video_property (
    property INTEGER NOT NULL,
    video INTEGER NOT NULL,
    is_category INTEGER NOT NULL DEFAULT 1,
    identifying INTEGER NOT NULL DEFAULT 0,
    value VARCHAR(256) NOT NULL DEFAULT '',
    FOREIGN KEY (video) REFERENCES video(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (property) REFERENCES property(id) ON DELETE CASCADE ON UPDATE CASCADE,
    PRIMARY KEY (property, video, value)
  )
''');

# Commiting all changes at the very end is much faster
#dbc.commit()

collectorname = ''

class Collector:
  def addVideo(self, name, ids=None, date=None, collector=None):
    video=None
    if not ids:
      ids={}
    if collector is None:
      collector = collectorname
    ids['collector'] = collector
    for id, in  db.execute('SELECT id FROM video WHERE name=?',(name,)).fetchall():
      match=True
      for pname, value in db.execute('SELECT p.name, vp.value FROM video_property AS vp LEFT JOIN property AS p ON vp.property=p.id WHERE vp.video=? AND vp.identifying=1',(id,)).fetchall():
        if ids.get(pname) != value:
          match=False
          break
      if match:
        video=id
        break
    if not video:
      db.execute('INSERT INTO video (name,date) VALUES (?,?)',(name,date))
      video=db.lastrowid
    elif date:
      db.execute('UPDATE video SET date=? WHERE id=?',(date,video))
    for pname, value in ids.items():
      self.addProperty(video,pname,value,True)
#    dbc.commit()
    return video

  def addSource(self,video,location,generated=False):
    if db.execute('SELECT id FROM source WHERE location=?', (location,)).fetchone() is not None:
      return
    mime = magic.detect_from_filename(location).mime_type
    type = '?'
    command = ['/usr/bin/ffprobe','-v','quiet','-print_format','json','-show_format','-show_streams','--',location]
    all = json.loads(subprocess.check_output(command).decode('utf-8',errors='ignore'))
    streams = all['streams']
    format = all['format']
    if str.startswith(mime, "image/"):
      type = 'I'
    elif any(stream.get('codec_type') == 'video' and stream.get('width') for stream in streams) or str.startswith(mime, "video/"):
      type = 'V'
    elif any(stream.get('codec_type') == 'audio' for stream in streams) or str.startswith(mime, "audio/"):
      type = 'A'
    info = streams[0]
    for stream in streams:
      if type == 'A':
        if stream.get('duration',0) > info.get('duration',0):
          info = stream
      else:
        if stream.get('width',0) > info.get('width',0):
          info = stream
    if 'duration' in format:
      info['duration'] = format['duration']
    if 'duration' not in info or type == 'I':
      info['duration'] = 0
    if 'width' in format:
      info['width'] = format['width']
    if 'width' not in info:
      info['width'] = 0
    if 'height' in format:
      info['height'] = format['height']
    if 'height' not in info:
      info['height'] = 0
    duration = math.ceil(float(info['duration']))
    width = int(info['width'])
    height = int(info['height'])
    db.execute('REPLACE INTO source (video, location, type, mime, width, height, duration, generated) VALUES (?,?,?,?,?,?,?,?)', (video, location, type, mime, width, height, duration, +generated))
#    dbc.commit()
    return db.lastrowid

  def addProperty(self,video,name,value,primary=False,iscategory=True):
    db.execute('INSERT OR IGNORE INTO property (name) VALUES (?)', (name,))
    id, = db.execute('SELECT id FROM property WHERE name=?', (name,)).fetchone()
    if primary:
      db.execute('DELETE FROM video_property WHERE video=? AND property=?',(video,id))
    db.execute('REPLACE INTO video_property (video,property,identifying,value,is_category) VALUES (?,?,?,?,?)', (video,id,+primary,value if value else '',+iscategory))
#    dbc.commit()

c = Collector()

# Use collectors to collect videos, thumbnails, etc.
if addVideos:
  for name in os.listdir(collector_base):
    if name.endswith(".py"):
      module = name[:-3]
      collectorname=module
      try:
        collector = __import__("collectors."+module).__dict__[module]
        collector.run(c)
      except Exception:
        print("collector",name,"threw an exception:")
        traceback.print_exc()

# Generate thumbnails for vidieos without one
if generateThumbnails:
  for video, location in db.execute("SELECT DISTINCT video, location FROM source WHERE type='V' AND video NOT IN (SELECT video FROM source WHERE type='I')").fetchall():
    thumbnail = os.path.join(thumbnails,str(video)+'.png')
    if os.path.isfile(thumbnail):
      c.addSource(video, thumbnail, generated=True)
      continue
    for command in [
      ["/usr/bin/ffmpeg","-hide_banner","-loglevel","fatal","-i",location,"-map","0:v","-map","-0:V",thumbnail],
      ["/usr/bin/ffmpeg","-hide_banner","-loglevel","fatal","-ss","60","-i",location,"-t","1","-f","image2","-vframes","1",thumbnail]
    ]:
      result = 1
      try:
        result = subprocess.run(command, stdout=subprocess.DEVNULL, stdin=subprocess.DEVNULL, stderr=sys.stderr).returncode
      except: pass
      if result == 0:
        c.addSource(video, thumbnail, generated=True)
        break;

# Remove sources which no longer exist
if removeLostSources:
  for id, location in db.execute("SELECT id, location FROM source").fetchall():
    if not os.path.isfile(location):
      db.execute("DELETE FROM source WHERE id=?",(id,))

# Remove media without video or audio sources
if removeVideosWithoutSources:
  try:
    db.execute("DELETE FROM video WHERE id NOT IN (SELECT video FROM source WHERE type IN ('V','A'))")
  except Exception:
    print("collector",name,"threw an exception:")
    traceback.print_exc()

if removeUnusedProperties:
  try:
    db.execute("DELETE FROM property WHERE id NOT IN (SELECT property FROM video_property)")
  except Exception:
    print("collector",name,"threw an exception:")
    traceback.print_exc()

dbc.commit()
dbc.close()
