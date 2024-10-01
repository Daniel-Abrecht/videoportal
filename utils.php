<?php

function resolve_url($path, $base){
  if(preg_match('/^(http|https):\/\//', $path))
    return $path;
  preg_match('/^((http|https):\/\/[^\/]+)\/([^?#]*?\/)?([^?#\/]*)?([#?]|$)/', $base, $parts);
  if(@$path[0] == '/')
    return $parts[1] . '/' . $path;
  return $parts[1] . '/' . $parts[3] . '/' . $path;
}

function load_playlist($playlist){
  $programs = [];
  $contents = file_get_contents($playlist);
  $next_name = null;
  foreach(explode("\n",$contents) as $line){
    if(!$line) continue;
    if(@$line[0] == '#'){
      if(str_starts_with($line,'#EXTINF:0,'))
        $next_name = substr($line, 10);
    }else{
      if(!$next_name)
        $next_name = rawurldecode(@end(explode('/',$line)));
      $programs[$next_name] = resolve_url($line, $playlist);
      $next_name = null;
    }
  }
  return $programs;
}

function arr1D2query($name, $x){
  if(!$x)
    return '';
  $parts = [];
  foreach($x as $key => $value){
    if(!is_string($key) || ($value == null || !is_string($value)))
      continue;
    $x = urlencode($name).'['.urlencode($key).']';
    if($value != null)
      $x .= '=' . urlencode($value);
    $parts[] = $x;
  }
  return implode('&',$parts);
}

function spliturl($url){
  $res = [];
  list($res['base'], $query) = explode('?',$url,2);
  $res['query'] = [];
  foreach(explode('&',$query) as $item){
    list($key, $value) = explode('=', $item, 2);
    $res['query'][urldecode($key)] = urldecode($value);
  }
  return $res;
}

function pagination_link($fullurl, $page, $i){
  return '<a href="'.$fullurl.'&page='.urlencode($i).'"'.($page==$i?' class="currentpage"':'').'>'.($i+1).'</a>';
}

function pagination($pages, $page, $fullurl, $min_start=3, $min_center=3, $min_end=-1){
  if($min_end < 0)
    $min_end = $min_start;
  $s = '';
  if($pages > 1){
    $s .= '<div class="pagination">';
    if($page > 0)
      $s .= '<a href="'.$fullurl.'&page='.urlencode($page-1).'">&lt;&lt;</a>';
    if($pages < $min_center*2+1+$min_start+$min_end){
      for($i=0; $i<$pages; $i++)
        $s .= pagination_link($fullurl, $page, $i);
    }else if($min_start+$min_center+1 > $page){
      for($i=0; $i<max($page+$min_center+1,$min_start); $i++)
        $s .= pagination_link($fullurl, $page, $i);
      $s .= '<span class="spacing"> ... </span>';
      for($i=$pages-$min_end; $i<$pages; $i++)
        $s .= pagination_link($fullurl, $page, $i);
    }else if($pages-$min_center-$min_end-1 <= $page){
      for($i=0; $i<$min_start; $i++)
        $s .= pagination_link($fullurl, $page, $i);
      $s .= '<span class="spacing"> ... </span>';
      for($i=min($page-$min_center,$pages-$min_start); $i<$pages; $i++)
        $s .= pagination_link($fullurl, $page, $i);
    }else{
      for($i=0; $i<$min_start; $i++)
        $s .= pagination_link($fullurl, $page, $i);
      $s .= '<span class="spacing"> ... </span>';
      for($i=$page-$min_center; $i<$page+$min_center+1; $i++)
        $s .= pagination_link($fullurl, $page, $i);
      $s .= '<span class="spacing"> ... </span>';
      for($i=$pages-$min_end; $i<$pages; $i++)
        $s .= pagination_link($fullurl, $page, $i);
    }
    if($page < $pages-1)
      $s .= '<a href="'.$fullurl.'&page='.urlencode($page+1).'">&gt;&gt;</a>';
    $s .= '</div>';
  }
  return $s;
}

?>
