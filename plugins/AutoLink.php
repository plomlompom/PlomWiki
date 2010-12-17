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
    $x = explode(' ', $x);
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
{ global $AutoLink_dir, $legal_title, $nl, $pages_dir, $todo_urgent;

  # Variables easily produced.
  $x['todo'] = $todo_urgent;
  $x['msg']  = '<p>Building AutoLink database.</p>';

  # Abort if $AutoLink_dir found, else prepare task to create it.
  if (is_dir($AutoLink_dir))
    ErrorFail('Not building AutoLink DB.', 'Directory already exists.');
  $x['tasks'][] = array('mkdir', $AutoLink_dir);

  # Snippet also used by RecentChanges.php. Outsource into a general plugin lib?
  $p_dir = opendir($pages_dir);
    while (FALSE !== ($fn = readdir($p_dir)))
      if (is_file($pages_dir.$fn) and preg_match('/^'.$legal_title.'$/', $fn))
        $titles[] = $fn;
    closedir($p_dir);

  # Scan all pages for occurences of other pages' names, store in $links_out.
  foreach ($titles as $title)
  { $page_txt = file_get_contents($pages_dir.$title);
    $links_out = array();
    foreach ($titles as $linkable)
      if ($linkable != $title)
        if (preg_match('/'.$linkable.'/iu', $page_txt))
          $links_out[] = $linkable;
    $links_out = implode(' ', $links_out);

    # For each page, prepare writing of its AutoLink DB file.
    $txt = $title.$nl.$links_out;
    $x['tasks'][] = array('SafeWrite', $AutoLink_dir.$title, $txt); }

  return $x; }
