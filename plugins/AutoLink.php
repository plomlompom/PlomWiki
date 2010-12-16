<?php

$AutoLink_dir = $plugin_dir.'AutoLink/';

function MarkupAutolink($text)
# Autolink $text according to its Autolink file.
{ global $AutoLink_dir, $root_rel, $title;

  # Don't do anything if there's no Autolink file for the page displayed
  $cur_page_file = $AutoLink_dir.$title;
  if (!is_file($cur_page_file))
    return $text; 

  # Get $links_out from $cur_page_file, turn into regex from their resp. files.
  $links_out = Autolink_GetFromFileLine($cur_page_file, 1, TRUE);
  foreach ($links_out as $pagename)
  { $linked_page_file = $AutoLink_dir.$pagename;
    $regex_pagename = Autolink_GetFromFileLine($linked_page_file, 0);

    # Build autolinks into $text where $avoid applies.
    $avoid = '(?=[^>]*($|<(?!\/(a|script))))';
    $match = '/('.$regex_pagename.')'.$avoid.'/iu';
    $repl  = '<a href="'.$root_rel.'?title='.$pagename.'">$1</a>';
    $text  = preg_replace($match, $repl, $text); }

  return $text; }

function Autolink_GetFromFileLine($path, $line, $return_as_array = FALSE)
# Return $line of file $path. $return_as_array string separated by ' ' if set.
{ global $nl;
  $x = file_get_contents($path);
  $x = explode($nl, $x);
  $x = $x[$line];
  if ($return_as_array)
    $x = explode(' ', $x);
  return $x; }
