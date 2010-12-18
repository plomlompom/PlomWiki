<?php

$AutoLink_dir   = $plugin_dir.'AutoLink/';
$actions_meta[] = array('Start AutoLink DB', '?action=autolink_start_db');
$actions_meta[] = array('Destroy AutoLink DB', '?action=autolink_destroy_db');
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
  { $regex_pagename = AutoLink_RetrieveRegexForTitle($pagename);
    
    # Build autolinks into $text where $avoid applies.
    $avoid = '(?=[^>]*($|<(?!\/(a|script))))';
    $match = '/('.$regex_pagename.')'.$avoid.'/iu';
    $repl  = '<a href="'.$root_rel.'?title='.$pagename.'">$1</a>';
    $text  = preg_replace($match, $repl, $text); }
  
  return $text; }

####################################
# DB updating / building / purging #
####################################

function UpdateAutoLinks($t, $text, $diff)
# Add to task list $t AutoLink DB update tasks. $text, $diff determine change.
{ global $AutoLink_dir, $nl, $title;
  $cur_page_file = $AutoLink_dir.$title;
  $all_other_titles = array_diff(GetAllPageTitles(), array($title));

  # Silently fail if AutoLink DB directory does not exist.
  if (!is_dir($AutoLink_dir)) return $t;

  # Page creation demands new file, going through all pages for new AutoLinks.
  if (!is_file($cur_page_file))
  { $t[] = array('AutoLink_CreateFile', $title);
    foreach ($all_other_titles as $linkable)
    { $t[] = array('AutoLink_TryLinking', $title.'_'.$linkable);
      $t[] = array('AutoLink_TryLinking', $linkable.'_'.$title); } }

  else
  { $links_out  = Autolink_GetFromFileLine($cur_page_file, 1, TRUE);

    # Page deletion severs links between files before $cur_page_file deletion.
    if ($text == 'delete')
    { foreach ($links_out as $pagename)
        $t[] = array('AutoLink_ChangeLine', $pagename.'_2_out_'.$title);
      $links_in = Autolink_GetFromFileLine($cur_page_file, 2, TRUE);
      foreach ($links_in as $pagename)
        $t[] = array('AutoLink_ChangeLine', $pagename.'_1_out_'.$title);
      $t[] = array('unlink', $cur_page_file); }

    # For mere page change, determine tasks comparing $diff against $links_out.
    else
    { # Divide $diff into $diff_del / $diff_add: lines deleted / added.
      $lines = explode($nl, $diff);
      $diff_del = array(); $diff_add = array();
      foreach ($lines as $line)
      if     ($line[0] == '<') $diff_del[] = substr($line, 1);
      elseif ($line[0] == '>') $diff_add[] = substr($line, 1);
  
      # Compare unlinked titles' regexes against $diff_add: harvest $links_new.
      $links_new = array();
      $not_linked = array_diff($all_other_titles, $links_out);
      foreach (AutoLink_TitlesInLines($not_linked, $diff_add) as $pagename)
        $links_new[] = $pagename;
      $t = AutoLink_TasksLinksInOrOut($t, 'in', $title, $links_new);
 
      # Threaten $links_out by matches in $diff_del. Remove threat if regexes
      # still matched in $diff_add or whole page $text. Else remove link_out.
      $links_rm = array();
      foreach (AutoLink_TitlesInLines($links_out, $diff_del) as $pagename)
        $links_rm[] = $pagename;
      foreach (AutoLink_TitlesInLines($links_rm, $diff_add) as $pagename)
        $links_rm = array_diff($links_rm, array($pagename)); 
      $lines_text = explode($nl, $text);
      foreach (AutoLink_TitlesInLines($links_rm, $lines_text) as $pagename)
        $links_rm = array_diff($links_rm, array($pagename));
      $t = AutoLink_TasksLinksInOrOut($t, 'out', $title, $links_rm); } }

  return $t; }

function Action_autolink_start_db()
# Form asking for confirmation and password before triggering AutoLink DB build.
{ global $nl, $root_rel;

  # Final HTML.
  $title_h = 'Start AutoLink DB.';
  $form    = '<p>Start AutoLink DB?</p>'.$nl.
             '<form method="post" action="'.$root_rel.'?action=write&amp;t='.
                                                      'autolink_start_db">'.$nl.
             'Admin password: <input type="password" name="pw" />'.$nl.
             '<input type="submit" value="Build!" />'.$nl.'</form>';
  Output_HTML($title_h, $form); }

function PrepareWrite_autolink_start_db()
# Deliver to Action_write() all information needed for AutoLink DB building.
{ global $AutoLink_dir, $nl, $root_rel, $todo_urgent;

  # Variables easily produced.
  $x['todo'] = $todo_urgent;
  $x['msg']  = '<p>Building AutoLink database.</p>';

  # Abort if $AutoLink_dir found, else prepare task to create it.
  if (is_dir($AutoLink_dir))
    ErrorFail('Not building AutoLink DB.', 
              'Directory already exists. <a href="'.$root_rel.
                                     '?action=autolink_destroy_db">Purge?</a>');
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

function Action_autolink_destroy_db()
# Form asking for confirmation and password before triggering AutoLink DB purge.
{ global $nl, $root_rel;

  # Final HTML.
  $title_h = 'Destroy AutoLink DB.';
  $form    = '<p>Destroy AutoLink DB?</p>'.$nl.
             '<form method="post" action="'.$root_rel.'?action=write&amp;t='.
                                                    'autolink_destroy_db">'.$nl.
             'Admin password: <input type="password" name="pw" />'.$nl.
             '<input type="submit" value="Purge!" />'.$nl.'</form>';
  Output_HTML($title_h, $form); }

function PrepareWrite_autolink_destroy_db()
# Deliver to Action_write() all information needed for AutoLink DB destruction.
{ global $AutoLink_dir, $nl, $root_rel, $todo_urgent;

  # Variables easily produced.
  $x['todo'] = $todo_urgent;
  $x['msg']  = '<p>Purging AutoLink database.</p>';

  # Abort if $AutoLink_dir found, else prepare task to create it.
  if (!is_dir($AutoLink_dir))
    ErrorFail('Not destroying AutoLink DB.', 'Directory does not exist.');

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
  $path    = $AutoLink_dir.$title;
  $content = $title.$nl2;
  AutoLink_SendToSafeWrite($path, $content); }

function AutoLink_TryLinking($input_string)
# $titles = $title_$linkable. Try auto-linking both pages, write to their files.
{ global $AutoLink_dir, $nl, $pages_dir;

  # Get $title, $linkable from $titles. (Hack around WriteTasks().)
  list($title, $linkable) = explode('_', $input_string);

  $page_txt       = file_get_contents($pages_dir.$title);
  $regex_linkable = AutoLink_RetrieveRegexForTitle($linkable);
  if (preg_match('/'.$regex_linkable.'/iu', $page_txt))
  { AutoLink_ChangeLine($title.'_1_in_'.$linkable);
    AutoLink_ChangeLine($linkable.'_2_in_'.$title); } }

function AutoLink_ChangeLine($input_string)
# On $title's AutoLink file, on $line_n, move $diff in/out according to $action.
{ global $AutoLink_dir, $nl;

  # Get variables from exploded input string. (Hack around WriteTasks().)
  list($title, $line_n, $action, $diff) = explode('_', $input_string);
  $path = $AutoLink_dir.$title;

  # Do $action with $diff on $title's file $line_n. Re-sort line for "in".
  $lines          = explode($nl, file_get_contents($path));
  $strings        = explode(' ', $lines[$line_n]);
  if     ($action == 'in')
  { $strings[]    = $diff;
    usort($strings, 'AutoLink_SortByLengthAlphabetCase'); }
  elseif ($action == 'out')
    $strings      = array_diff($strings, array($diff));  
  $new_line       = implode(' ', $strings);
  $lines[$line_n] = rtrim($new_line);
  $content        = implode($nl, $lines);

  AutoLink_SendToSafeWrite($path, $content); }

##########################
# Minor helper functions #
##########################

function Autolink_GetFromFileLine($path, $line_n, $return_as_array = FALSE)
# Return $line_n of file $path. $return_as_array string separated by ' ' if set.
# From empty lines, explode() generates $x = array(''); return array() instead.
{ global $nl;
  $x = explode($nl, file_get_contents($path));
  $x = $x[$line_n];
  if ($return_as_array)
    $x = explode(' ', $x);
    if ($x == array(''))
      return array();
  return $x; }

function Autolink_SortByLengthAlphabetCase($a, $b)
# Try to sort by stringlength, then follow sort() for uppercase vs. lowercase.
{ $strlen_a = strlen($a);
  $strlen_b = strlen($b);
  if     ($strlen_a < $strlen_b) return  1;
  elseif ($strlen_a > $strlen_b) return -1;

  $sort = array($a, $b);
  sort($sort);
  if ($sort[0] == $a) return -1;
  else                return  1; }

function AutoLink_RetrieveRegexForTitle($title)
# Return regex matching $title according to its AutoLink file.
{ global $AutoLink_dir;
  $AutoLink_file = $AutoLink_dir.$title;
  $regex = Autolink_GetFromFileLine($AutoLink_file, 0);
  return $regex; }

function AutoLink_TitlesInLines($titles, $lines)
# Return array of all $titles whose AutoLink regex matches $lines.
{ $titles_new = array();
  foreach ($titles as $title)
  { $regex = AutoLink_RetrieveRegexForTitle($title);
    foreach ($lines as $line)
      if (preg_match('/'.$regex.'/iu', $line))
      { $titles_new[] = $title;
        break; } }
  return $titles_new; }

function AutoLink_TasksLinksInOrOut($tasks, $dir, $title, $titles)
# Add $tasks of moving $titles $dir ('in'/'out') of line 1 in $title's AutoLink
# file and $title $dir ('in'/'out')of line 2 in $titles' AutoLink files.
{ foreach ($titles as $pagename)
  { $tasks[] = array('AutoLink_ChangeLine', $title.'_1_'.$dir.'_'.$pagename);
    $tasks[] = array('AutoLink_ChangeLine', $pagename.'_2_'.$dir.'_'.$title); }
  return $tasks; }

function AutoLink_SendToSafeWrite($path, $content)
# Call SafeWrite() not on $content directly, but on newly built temp file of it.
{ $path_temp= NewTempFile($content);
  SafeWrite($path, $path_temp); }
