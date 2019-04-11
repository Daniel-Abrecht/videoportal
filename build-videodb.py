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

dbc = sqlite3.connect('/var/videodb/video.db')
db = dbc.cursor()

rebuild=(len(sys.argv) >= 2 and sys.argv[1] == 'rebuild')

if rebuild:
  db.execute('DROP TABLE IF EXISTS source')
  db.execute('DROP TABLE IF EXISTS property')
  db.execute('DROP TABLE IF EXISTS video_property')
  db.execute('DROP TABLE IF EXISTS video')

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
    FOREIGN KEY (video) REFERENCES video(id)
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
    identifying INTEGER NOT NULL DEFAULT 0,
    value VARCHAR(256),
    FOREIGN KEY (video) REFERENCES video(id),
    FOREIGN KEY (property) REFERENCES property(id),
    PRIMARY KEY (property, video)
  )
''');

db.execute('''
  CREATE TABLE IF NOT EXISTS video (
    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
    name VARCHAR(32) NOT NULL,
    date DATETIME
  )
''');

# Commiting all changes at the very end is much faster
#dbc.commit()

collectorname = ''

class Collector:
  def addVideo(self, name, ids=None, date=None):
    video=None
    if not ids:
      ids=()
    ids['collector'] = collectorname
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

  def addSource(self,video,location):
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
    db.execute('REPLACE INTO source (video, location, type, mime, width, height, duration) VALUES (?,?,?,?,?,?,?)', (video, location, type, mime, width, height, duration))
#    dbc.commit()
    return db.lastrowid

  def addProperty(self,video,name,value,primary=False):
    db.execute('INSERT OR IGNORE INTO property (name) VALUES (?)', (name,))
    id, = db.execute('SELECT id FROM property WHERE name=?', (name,)).fetchone()
    db.execute('INSERT OR IGNORE INTO video_property (video,property,identifying,value) VALUES (?,?,?,?)', (video,id,+primary,value))
    db.execute('UPDATE video_property SET value=?, identifying=? WHERE video=? AND property=?', (value,+primary,video,id))
#    dbc.commit()
    return id

c = Collector()

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

dbc.commit()
dbc.close()
