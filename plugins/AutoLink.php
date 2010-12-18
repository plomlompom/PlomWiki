<?php

$AutoLink_dir   = $plugin_dir.'AutoLink/';
$actions_meta[] = array('Build AutoLink DB', '?action=autolink_build_db');
$hook_PrepareWrite_page .= '$x[\'tasks\'] = UpdateAutoLinks($x[\'tasks\'], '.
                                                           '$text, $diff_add);';
##########
# Markup #
##########

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

#########################
# DB building / purging #
#########################

function UpdateAutoLinks($t, $text, $diff)
# Add to task list $t AutoLink DB update tasks. $text, $diff determine change.
{ global $AutoLink_dir, $nl, $title;
  $cur_page_file = $AutoLink_dir.$title;

  # Silently fail if AutoLink DB directory does not exist.
  if (!is_dir($AutoLink_dir))
    return $t;

  # Page deletion severs links between files before deletion of $cur_page_file.
  if ($text == 'delete')
  { $links_out = Autolink_GetFromFileLine($cur_page_file, 1, TRUE);
    foreach ($links_out as $pagename)
      $t[] = array('AutoLink_RemoveFromLine', $pagename.'_2_'.$title);
    $links_in = Autolink_GetFromFileLine($cur_page_file, 2, TRUE);
    foreach ($links_in as $pagename)
      $t[] = array('AutoLink_RemoveFromLine', $pagename.'_1_'.$title);
    $t[] = array('unlink', $cur_page_file); }

  # Page creation demands new file, going through all pages for new AutoLinks.
  elseif (!is_file($cur_page_file))
  { $t[] = array('AutoLink_CreateFile', $title);
    foreach (GetAllPageTitles() as $linkable)
      if ($linkable != $title)
      { $t[] = array('AutoLink_TryLinking', $title.'_'.$linkable);  
        $t[] = array('AutoLink_TryLinking', $linkable.'_'.$title); } }

  # For mere page change, determine tasks by comparing diff against $links_out.
  else
  { # Divide $diff into $diff_del / $diff_add: lines deleted / added.
    $lines = explode($nl, $diff);
    $diff_del = array();
    $diff_add = array();
    foreach ($lines as $line)
      if ($line[0] == '<')
        $diff_del[] = substr($line, 1);
      elseif ($line[0] == '>')
        $diff_add[] = substr($line, 1);

    # Compare unlinked titles' regexes against new lines to harvest $links_new.
    $titles = GetAllPageTitles();
    $links_new = array();
    $links_out = Autolink_GetFromFileLine($cur_page_file, 1, TRUE);
    $yet_unlinked = array_diff($titles, $links_out, array($title));
    foreach ($yet_unlinked as $pagename)
    { $linked_page_file = $AutoLink_dir.$pagename;
      $regex = Autolink_GetFromFileLine($linked_page_file, 0);
      foreach ($diff_add as $line)
        if (preg_match('/'.$regex.'/iu', $line))
          $links_new[] = $pagename; }
    foreach ($links_new as $pagename)
    { $t[] = array('AutoLink_InsertInLine', $title.'_1_'.$pagename);
      $t[] = array('AutoLink_InsertInLine', $pagename.'_2_'.$title); }

    # Match $links_out's regexes against deleted lines. Reduce $links_maybe_dead
    # by matching first against new lines, secondly against whole page $text.
    $links_maybe_dead = array();
    foreach ($links_out as $pagename)
    { $linked_page_file = $AutoLink_dir.$pagename;
      $regex = Autolink_GetFromFileLine($linked_page_file, 0);
      foreach ($diff_del as $line)
        if (preg_match('/'.$regex.'/iu', $line))
          $links_maybe_dead[] = array($pagename, $regex); }
    foreach ($links_maybe_dead as $pagename => $regex)
      foreach ($diff_add as $line)
        if (preg_match('/'.$regex.'/iu', $line))
        { unset($links_maybe_dead[$pagename]);
          break; }
    foreach ($links_maybe_dead as $pagename => $regex)
      if (preg_match('/'.$regex.'/iu', $text))
        unset($links_maybe_dead[$pagename]);
    foreach ($links_maybe_dead as $array)
    { $pagename = $array[0];
      $t[] = array('AutoLink_RemoveFromLine', $title.'_1_'.$pagename);
      $t[] = array('AutoLink_RemoveFromLine', $pagename.'_2_'.$title); } }

  return $t; }

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
{ global $AutoLink_dir, $nl, $root_rel, $pages_dir, $todo_urgent;

  # Variables easily produced.
  $x['todo'] = $todo_urgent;
  $x['msg']  = '<p>Building AutoLink database.</p>';

  # Abort if $AutoLink_dir found, else prepare task to create it.
  if (is_dir($AutoLink_dir))
    ErrorFail('Not building AutoLink DB.', 
              'Directory already exists. <a href="'.$root_rel.
                                       '?action=autolink_purge_db">Purge?</a>');
  $x['tasks'][] = array('mkdir', $AutoLink_dir);

  # Build page file creation, linking tasks.
  $titles = GetAllPageTitles();
  foreach ($titles as $title)
    $x['tasks'][] = array('AutoLink_CreateFile', $title);
  foreach ($titles as $title)
    foreach ($titles as $linkable)
      if ($linkable != $title)
        $x['tasks'][] = array('AutoLink_TryLinking', $title.'_'.$linkable);

  return $x; }

function Action_autolink_purge_db()
# Form asking for confirmation and password before triggering AutoLink DB purge.
{ global $nl, $root_rel;

  # Final HTML.
  $title_h = 'Purge AutoLink DB.';
  $form    = '<p>Purge AutoLink DB?</p>'.$nl.
             '<form method="post" action="'.$root_rel.'?action=write&amp;t='.
                                                      'autolink_purge_db">'.$nl.
             'Admin password: <input type="password" name="pw" />'.$nl.
             '<input type="submit" value="Purge!" />'.$nl.'</form>';
  Output_HTML($title_h, $form); }

function PrepareWrite_autolink_purge_db()
# Deliver to Action_write() all information needed for AutoLink DB purging.
{ global $AutoLink_dir, $nl, $root_rel, $todo_urgent;

  # Variables easily produced.
  $x['todo'] = $todo_urgent;
  $x['msg']  = '<p>Purging AutoLink database.</p>';

  # Abort if $AutoLink_dir found, else prepare task to create it.
  if (!is_dir($AutoLink_dir))
    ErrorFail('Not purging AutoLink DB.', 'Directory does not exist.');

  # Add unlink(), rmdir() tasks for $AutoLink_dir and its contents.
  $p_dir = opendir($AutoLink_dir);
  while (FALSE !== ($fn = readdir($p_dir)))
    if (is_file($AutoLink_dir.$fn))
      $x['tasks'][] = array('unlink', $AutoLink_dir.$fn);
  closedir($p_dir); 
  $x['tasks'][] = array('rmdir', $AutoLink_dir);

  return $x; }

##########################################
# DB writing tasks to be called by todo. #
##########################################

function AutoLink_CreateFile($title)
# Start AutoLink file of page $title, empty but for title regex.
{ global $AutoLink_dir, $nl2;

  # $content (at start empty but for first, regex line) shall rest at $path.
  $path    = $AutoLink_dir.$title;
  $content = $title.$nl2;
  
  # Put $content into temp file for SafeWrite() to harvest.
  $path_temp = NewTempFile($content);
  SafeWrite($path, $path_temp); }

function AutoLink_TryLinking($titles)
# $titles = $title_$linkable. Try auto-linking both pages, write to their files.
{ global $AutoLink_dir, $nl, $pages_dir;
  list($title, $linkable) = explode('_', $titles);
  $page_txt = file_get_contents($pages_dir.$title);

  $path_linkable = $AutoLink_dir.$linkable;
  $regex_linkable = Autolink_GetFromFileLine($path_linkable, 0);
  if (preg_match('/'.$regex_linkable.'/iu', $page_txt))
  { AutoLink_InsertInLine($title.'_1_'.$linkable);
    AutoLink_InsertInLine($linkable.'_2_'.$title); } }

function AutoLink_InsertInLine($string)
# Add in $title's pagefile on $line_n $insert (all variables found in $string).
{ global $AutoLink_dir, $nl;

  # Get $title, $line_n, $insert from $string.
  list($title, $line_n, $insert) = explode('_', $string);

  # Get $content from $title's AutoLink file, add $insert.whitespace on $line_n.
  $path_file = $AutoLink_dir.$title;
  $lines = explode($nl, file_get_contents($path_file));
  $lines[$line_n] = $lines[$line_n].$insert.' ';
  $content = implode($nl, $lines);
    
  # Put $content into temp file for SafeWrite() to harvest.
  $path_temp= NewTempFile($content);
  SafeWrite($path_file, $path_temp); }

function AutoLink_RemoveFromLine($string)
# From $title's pagefile on $line_n $remove (all variables found in $string).
{ global $AutoLink_dir, $nl;

  # Get $title, $line_n, $remove from $string.
  list($title, $line_n, $remove) = explode('_', $string);

  # Get file content from $title's AutoLink file, remove $remove on $line_n.
  $path_file      = $AutoLink_dir.$title;
  $lines          = explode($nl, file_get_contents($path_file));
  $line           = rtrim($lines[$line_n]);
  $elements       = explode(' ', $line);
  $elements       = array_diff($elements, array($remove));  
  $line           = implode(' ', $elements).' ';
  $lines[$line_n] = ltrim($line);
  $content        = implode($nl, $lines);
    
  # Put $content into temp file for SafeWrite() to harvest.
  $path_temp= NewTempFile($content);
  SafeWrite($path_file, $path_temp); }

##########################
# Minor helper functions #
##########################

function Autolink_GetFromFileLine($path, $line, $return_as_array = FALSE)
# Return $line of file $path. $return_as_array string separated by ' ' if set.
{ global $nl;
  $x = explode($nl, file_get_contents($path));
  $x = $x[$line];
  if ($return_as_array)
  { $x = rtrim($x);
    $x = explode(' ', $x); 
    if (!$x[0])
      return array(); }
  return $x; }
