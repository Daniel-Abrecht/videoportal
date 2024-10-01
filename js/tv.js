function Future(){
  let resolve, reject;
  const p = new Promise((a,b)=>{
    resolve = a;
    reject = b;
  });
  p.resolve = resolve;
  p.reject = reject;
  return p;
};

function lookup_atoms(b,l){
  const results = [];
  const s = l.shift()
  //console.log(s.toString(16),l.length,b, b.byteOffset, b.byteLength);
  const dv = new DataView(b.buffer, b.byteOffset, b.byteLength);
  let o = 0;
  while(o+8 <= b.byteLength){
    const type = dv.getUint32(o+4);
    const size = dv.getUint32(o+0);
    //console.log('>',b.byteOffset,o,size,type.toString(16));
    if(type == s){
      if(!l.length){
        results.push(b.subarray(o+8, o+size));
      }else if(size > 8){
        results.push(lookup_atoms(b.subarray(o+8, o+size), [...l]));
      }
    }
    o += size;
    if(size == 0)
      break;
  }
  return results.flat();
}

function mp4_get_codec(b){
  const astsd = lookup_atoms(b, [0x6D6F6F76,0x7472616B,0x6D646961,0x6D696E66,0x7374626C,0x73747364]); // 'moov','trak','mdia','minf','stbl','stsd'
  const codecs = [];
  for(const bstsd of astsd){
    if(bstsd.byteLength < 16) continue;
    const dv = new DataView(bstsd.buffer, bstsd.byteOffset+8, bstsd.byteLength-8);
    const type = dv.getUint32(4);
    const size = dv.getUint32(0);
    switch(type){
      case 0x61766331: { // avc1
        codecs.push('avc1.' + [...bstsd.slice(103,106)].map(x=>x.toString(16).padStart(2,0)).join('').toUpperCase());
      } break;
      case 0x6d703461: { // mp4a
        codecs.push("mp4a.40.2"); // TODO: don't hard code the parameters (the 40.2 part)
      } break;
    }
  }
  return codecs;
}

function mp4_mse_mime(b){
  const codecs = mp4_get_codec(b);
  return 'video/mp4; codecs="'+codecs+'"';
}

class MediaSourceHelper {
  #x(){} // babel workaround
  #mediaSource;
  #sourceBuffer;
  #header;
  // Note: the codec parameters may be slightly wrong, but it'll probably be fine...
  #mime = 'video/mp4; codecs="avc1.640028, mp4a.40.2"';
  //#mime = 'video/mp4; codecs="avc1.4D401E"';
  #onerror;
  #onupdateend;
  #mseURL;
  #waitdone = null;
  #chunk_list = [];
  #resetinprogress = false;
  #waitdata = false;
  #alldone = Future();
  constructor(){
    this.#onerror = this.#_onerror.bind(this);
    this.#onupdateend = this.#_onupdateend.bind(this);
    if(typeof MediaSource === 'undefined')
      throw new Error("MediaSource API unavailable");
    this.#process();
  }
  async #process(){
    // let art = false;
    while(true){
      this.#waitdone = Future();
      await this.#waitdone;
      this.#waitdata = false;
      if(!this.#sourceBuffer)
        continue;
      // if(art){
      //   console.log(this.#sourceBuffer.timestampOffset,this.#sourceBuffer.appendWindowStart,this.#sourceBuffer.appendWindowEnd);
        //this.#sourceBuffer.remove(0,this.#sourceBuffer.appendWindowEnd-20);
        //continue;
      // }
      // art = !art;
      const data = this.#chunk_list.shift();
      if(data){
        // console.log('Got data', data.byteLength);
        this.#sourceBuffer.appendBuffer(data);
      }else{
        this.#waitdata = true;
        this.#alldone.resolve();
        this.#alldone = Future();
      }
    }
  }
  #_onerror(error){
    console.error(error.error ?? error);
    this.reset();
  }
  #_onupdateend(){
    if(this.#waitdone)
      this.#waitdone.resolve();
    // if(this.video)
    //   try { this.video.play(); } catch(e) {}
  }
  reset(){
    if(this.#resetinprogress)
      return;
    if(this.#mediaSource)
      try { this.#mediaSource.endOfStream(); } catch(e) {}
    if(this.#sourceBuffer) this.#sourceBuffer.onupdateend = null;
    this.#mediaSource = null;
    this.#sourceBuffer = null;
    if(this.#mseURL){
      URL.revokeObjectURL(this.#mseURL);
      this.#mseURL = null;
    }
    if(!this.#header || !this.#mime)
      return;
    console.debug("resetting video");
    this.#mediaSource = new MediaSource();
    this.#resetinprogress = true;
    this.#mediaSource.onsourceopen = ()=>{
      this.#resetinprogress = false;
      this.#mediaSource.onsourceopen = null;
      this.#sourceBuffer = this.#mediaSource.addSourceBuffer(this.#mime);
      this.#sourceBuffer.mode = "sequence";
      this.#sourceBuffer.onupdateend = this.#onupdateend;
      this.#chunk_list.unshift(this.#header);
      this.#waitdone.resolve();
    };
    this.#mediaSource.onsourceclose = ()=>{this.reset();};
    const video = this.video;
    if(video){
      try {
        if('srcObject' in video)
          video.srcObject = this.#mediaSource.handle || this.#mediaSource;
      } catch(e) {}
      if(!video.srcObject){
        this.#mseURL = URL.createObjectURL(this.#mediaSource);
        video.src = this.#mseURL;
      }
    }
  }
  setVideo(video){
    const oldvideo = this.video;
    this.video = null;
    if(oldvideo){
      oldvideo.removeEventListener("error", this.#onerror);
      oldvideo.srcObject = null;
      oldvideo.src = null;
    }
    this.video = video;
    if(video){
      this.video.addEventListener("error", this.#onerror);
      this.reset();
    }
  }
  updateMP4Header(header){
    let mime = mp4_mse_mime(header);
    const oldmime = this.#mime;
    this.#header = header;
    this.#mime = mime;
    console.log(mime);
    if(oldmime != mime || (!this.#sourceBuffer && !this.#resetinprogress)){
      this.reset();
    }else{
      return this.appendData(header, false);
    }
  }
  async appendData(data, detect_header=true){
    // console.log(data, detect_header);
    if(detect_header){
      const dv = new DataView(data.buffer, data.byteOffset, data.byteLength);
      let o = 0;
      while(true){
        const type = dv.getUint32(o+4);
        const size = dv.getUint32(o+0);
        if(type == 0x6d6f6f66) // moof
          break;
        o += size;
        if(size == 0 || o+8 >= dv.byteLength)
          break;
      }
      if(o){
        this.updateMP4Header(data.slice(0,o), this.#mime);
        data = data.subarray(o);
      }
    }
    if(!data.byteLength)
      return;
    if(!this.#mime)
      throw new Error("Error: updateMP4Header must be called first");
    this.#chunk_list.push(data);
    if(this.#waitdata)
      this.#waitdone.resolve();
    return this.#alldone;
  }
  ended(){
    console.log("ended");
    this.setVideo(null);
  }
};


const tv_play = ((video,channel)=>{
  const mseh = new MediaSourceHelper();
  mseh.setVideo(video);
  let done = false;
  const ac = new AbortController();
  const promise = (async()=>{
    try {
      const stream = await fetch("tv-stream.php?channel="+encodeURIComponent(channel),{signal:ac.signal})
                            .then((response) => response.body.getReader());
      let isfirst = true;
      let last = null;
      while(!done){
        const chunk = await stream.read();
        if(!chunk.value)
          break;
        const data = last ? new Uint8Array(chunk.value.byteLength+last.byteLength) : chunk.value;
        if(last){
          data.set(last, 0);
          data.set(chunk.value, last.byteLength);
        }
        const dv = new DataView(data.buffer, data.byteOffset, data.byteLength);
        let last_mdat_end = 0;
        let o = 0;
        while(o+8 < dv.byteLength){
          const type = dv.getUint32(o+4);
          const size = dv.getUint32(o+0);
          // console.log(type.toString(16), size, o+size, data.byteOffset, dv.byteLength);
          if(size == 0){
            // console.log(dv.byteLength, dv.byteOffset, o, last_mdat_end, data.slice());
            throw new Error("invalid MP4 chunk size!");
          }
          if(o+size > dv.byteLength)
            break;
          o += size;
          if(type == 0x6D646174) // mdat
            last_mdat_end = o;
        }
        last = null;
        if(last_mdat_end < dv.byteLength)
          last = data.slice(last_mdat_end);
        // console.log(dv.byteLength, dv.byteOffset, o, last_mdat_end, data.slice());
        if(last_mdat_end){
          // console.log(last_mdat_end);
          if(!mseh.video.paused || isfirst){
            mseh.appendData(data.subarray(0,last_mdat_end));
          }else{
            console.log("video is paused, dropping some data");
          }
          isfirst = false;
        }
      }
    } finally {
      mseh.ended();
      ac.abort();
      done = true;
    }
  })();
  try { mseh.video.play(); } catch(e) {}
  promise.abort = ()=>{
    mseh.ended();
    ac.abort();
    done = true;
  };
  return promise;
});

let last;
function switch_channel(){
  const player = document.getElementById("player");
  const ch = location.hash.slice(9);
  if(ch){
    if(last)
      last.abort();
    last = tv_play(player, decodeURIComponent(ch));
  }
}
switch_channel();
addEventListener("hashchange", switch_channel);
