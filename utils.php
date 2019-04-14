<?php

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
