#!/usr/bin/env python3

import os, sys
import re
import html
import threading
import subprocess
from difflib import get_close_matches
from http.server import HTTPServer, BaseHTTPRequestHandler
from urllib.parse import quote, unquote

base_port = 4300

channel_list = []
channel_dict = dict()
tmp_configfile = None
tmp_configfile_fd = None
current_frequency = None
current_channels = dict()
mumudvb = None

def stop_mumudvb():
  global current_frequency
  global mumudvb
  if mumudvb:
    print("stopping mumudvb")
    mumudvb.terminate()
    mumudvb.wait()
    mumudvb = None
  current_frequency = None

re_autoconf_channel_count = re.compile(b'^Info:  Autoconf:  Diffusion ([0-9]+) channels?$')
re_autoconf_channel_info = re.compile(b'^Info:  Autoconf:  Channel number : *([0-9]+), name : "(.*)"  service id ([0-9]+) *$')


def start_mumudvb(program):
  global current_frequency
  global mumudvb
  global current_channels
  program.update_config()
  print("starting mumudvb")
  mumudvb = subprocess.Popen(
    ['mumudvb','-d','-c','/proc/self/fd/'+str(tmp_configfile_fd)],
    stdin=subprocess.DEVNULL, stdout=subprocess.PIPE, stderr=subprocess.STDOUT,
    pass_fds=(tmp_configfile_fd,)
  )
  autoconfig_done = False
  channel_list = dict()
  channel_count = 0
  for line in mumudvb.stdout:
    sys.stdout.buffer.write(b'mumudvb> '+line)
    sys.stdout.buffer.flush()
    if autoconfig_done:
      res = re_autoconf_channel_info.match(line)
    else:
      res = re_autoconf_channel_count.match(line)
    if res:
      if not autoconfig_done:
        autoconfig_done = True
        channel_count = int(res[1])
      else:
        channel_list[res[2]] = (int(res[1]),int(res[3]))
    if autoconfig_done and channel_count == len(channel_list):
      break
  mumudvb.stdout.close()
  current_channels = channel_list
  current_frequency = program.freq
  print("started")

class ChannelListEntry:
  def __init__(self, p):
    (
      self.program,
      self.freq,
      self.inv,
      self.srate,
      self.dec,
      self.modulation
    ) = p[0:6]
    self.freq = int(self.freq)
    self.srate = int(self.srate)
    self.modulation = self.modulation.replace('_','')
    channel_list.append(self)
    channel_dict[self.program] = self

  def update_config(c):
    tmp_configfile.truncate(0)
    tmp_configfile.write(f'''
autoconfiguration=full
autoconf_unicast_start_port={base_port}
autoconf_radios=1
autoconf_scrambled=1
#cam_support=1
freq={c.freq//1000}
srate={c.srate//1000}
modulation={c.modulation}
delivery_system=DVBC_ANNEX_AC
unicast=1
multicast_ipv4=0
multicast_ipv6=0
''')
    tmp_configfile.flush()
    os.lseek(tmp_configfile_fd, os.SEEK_SET, 0)

def init():
  global tmp_configfile
  global tmp_configfile_fd
  with open("/etc/channels.conf") as reader:
    for line in reader:
      p = ChannelListEntry([x.strip() for x in line.split(':')])
  tmp_configfile_fd = os.open('/tmp/', os.O_RDWR|os.O_TMPFILE, 0o400)
  tmp_configfile = os.fdopen(tmp_configfile_fd, 'w+')

class Handler(BaseHTTPRequestHandler):
  def do_GET(self):
    global current_frequency
    host = self.headers['Host']
    if '/' in host:
      self.send_response(400)
      self.send_header('Content-Type', 'text/html')
      self.end_headers()
      self.wfile.write(b'<h1>404 Not Found</h1>')
    res = self.path.split('?')
    res.append('')
    self.path = res[0]
    query = res[1]
    spath = self.path.split('/')[1:]
    if self.command == 'GET':
      if self.path == '/':
        self.send_response(200)
        self.send_header('Content-type', 'text/html')
        self.end_headers()
        self.wfile.write(b'''
<h1>TV Server</h1>
<ul>
  <li><a href="/playlist.m3u">playlist.m3u</a></li>
  <li><a href="/vlc/">vlc</a></li>
</ul>
''')
        return
      if self.path == '/playlist.m3u' or self.path == '/playlist.m3u8':
        self.send_response(200)
        self.send_header('Content-Type', 'audio/x-mpegurl')
        self.send_header('Access-Control-Allow-Origin', '*')
        self.end_headers()
        self.wfile.write(b'#EXTM3U\n')
        for entry in channel_list:
          message = '#EXTINF:0,' + entry.program + '\n'
          message += '/program/' + quote(entry.program) + '\n'
          self.wfile.write(message.encode('utf-8'))
        return
      if self.path == '/vlc/':
        self.send_response(200)
        self.send_header('Content-Type', 'text/html; charset=UTF-8')
        self.end_headers()
        self.wfile.write(b'<ul>\n')
        for entry in channel_list:
          message = '  <li><a href="vlc://'+host+'/program/' + quote(entry.program) + '">' + html.escape(entry.program) + '</a></li>\n'
          self.wfile.write(message.encode('utf-8'))
        self.wfile.write(b'</ul>\n')
        return
      if len(spath) == 2 and spath[0] == 'program':
        program = unquote(spath[1])
        if program in channel_dict:
          program = channel_dict[program]
          if current_frequency != program.freq:
            if query == 'noswitch':
              self.send_response(412)
              return
            stop_mumudvb()
            start_mumudvb(program)
          if mumudvb.poll() is not None:
            start_mumudvb(program)
          match = get_close_matches(program.program.encode('UTF-8'), current_channels.keys(), 1)
          if len(match):
            match = match[0]
            index, spid = current_channels[match]
            location = f'http://{host.split(":")[0]}:{index+base_port}'
            self.send_response(302)
            #self.send_header('Content-Type', 'video/mpeg')
            self.send_header('Access-Control-Allow-Origin', '*')
            self.send_header('Location', location)
            self.end_headers()
            return
    self.send_response(404)
    self.send_header('Content-Type', 'text/html')
    self.end_headers()
    self.wfile.write(b'<h1>404 Not Found</h1>')

if __name__ == '__main__':
  init()
  server = HTTPServer(('', 8080), Handler)
  server.serve_forever()
