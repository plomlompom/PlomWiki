<?php

$AutoLink_dir   = $plugin_dir.'AutoLink/';
$actions_meta[] = array('Build AutoLink DB', '?action=autolink_build_db');

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
  { $x = rtrim($x);
    $x = explode(' ', $x); }
  return $x; }

function Action_autolink_build_db()
# Form asking for confirmation and password before triggering AutoLink DB build.
{ global $nl, $root_rel;

  # Final HTML.
  $title_h = 'Build AutoLink DB.';
  $form    = '<p>Build AutoLink DB?</p>'.$nl.
             '<form method="post" action="'.$root_rel.'?action=write&amp;t='.
                                                      'autolink_build_db">'.$nl.
             'Admin password: <input type="password" name="pw" />'.$nl.
             '<input type="submit" value="Build!" />'.$nl.'</form>';
  Output_HTML($title_h, $form); }

function PrepareWrite_autolink_build_db()
# Deliver to Action_write() all information needed for AutoLink DB building.
{ global $AutoLink_dir, $nl, $pages_dir, $todo_urgent;

  # Variables easily produced.
  $x['todo'] = $todo_urgent;
  $x['msg']  = '<p>Building AutoLink database.</p>';

  # Abort if $AutoLink_dir found, else prepare task to create it.
  if (is_dir($AutoLink_dir))
    ErrorFail('Not building AutoLink DB.', 'Directory already exists.');
  $x['tasks'][] = array('mkdir', $AutoLink_dir);

  # Build page file creation, linking tasks.
  $titles = GetAllPageTitles();
  foreach ($titles as $title)
    $x['tasks'][] = AutoLink_CreateFile($title);
  foreach ($titles as $title)
  { $page_txt = file_get_contents($pages_dir.$title);
    foreach ($titles as $linkable)
      if ($linkable != $title)
        if (preg_match('/'.$linkable.'/iu', $page_txt))
        { $x['tasks'][] = array('AutoLink_InsertInLine', $title, 
                                                            '1'.$nl.$linkable);
          $x['tasks'][] = array('AutoLink_InsertInLine', $linkable, 
                                                            '2'.$nl.$title); } }
  return $x; }

function AutoLink_InsertInLine($title, $path_temp)
# Add in $path_file on $line_n $insert (last two found in $path_temp file).
{ global $AutoLink_dir, $nl;

  # Get $line_n, $insert from $path_temp.
  $array  = explode($nl, file_get_contents($path_temp));
  $line_n = $array[0];
  $insert = $array[1];

  # Get $content from $title's AutoLink file, add $insert.whitespace on $line_n.
  $path_file = $AutoLink_dir.$title;
  $lines = explode($nl, file_get_contents($path_file));
  $lines[$line_n] = $lines[$line_n].$insert.' ';
  $content = implode($nl, $lines);
    
  # Put $content into temp file for SafeWrite() to harvest.
  $path_temp_new = NewTempFile($content);
  SafeWrite($path_file, $path_temp_new); }

function AutoLink_CreateFile($title)
# Build link-empty file conte on page $title, return file writing task.
{ global $AutoLink_dir, $nl2;

  $path    = $AutoLink_dir.$title;
  $content = $title.$nl2;
  return     array('SafeWrite', $path, $content); }
