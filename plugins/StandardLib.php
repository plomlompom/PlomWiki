<?php

function GetAllPageTitles()
# Return an array of all of the PlomWiki page's titles.
{ global $pages_dir, $legal_title; 
  $p_dir = opendir($pages_dir);
  $titles = array();
  while (FALSE !== ($fn = readdir($p_dir)))
    if (is_file($pages_dir.$fn) and preg_match('/^'.$legal_title.'$/', $fn))
      $titles[] = $fn;
  closedir($p_dir); 
  return $titles; }
