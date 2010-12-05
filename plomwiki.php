<?php

# Only allow simple alphanumeric titles to avoid security risks.
$title = $_GET['title']; 
if (!preg_match('/^[a-zA-Z0-9]+$/', $title)) 
  { echo 'Illegal page title. Only alphanumeric characters allowed.'; exit(); }

# Where page data is located.
$pages_dir  = 'pages/';
$page_path = $pages_dir.$title;
$diff_dir = $pages_dir.'diffs/';
$diff_path = $diff_dir.$title;

# Insert this at the head of pages.
$page_header = '<h1>'.$title.'</h1>'."\n".'<p>'."\n".'<a href="plomwiki.php?'.
'title='.$title.'">View</a> <a href="plomwiki.php?title='.$title.'&amp;action='.
   'edit">Edit</a> <a href="plomwiki.php?title='.$title.'&amp;action=history">'.
                                                'History</a> '."\n".'</p>'."\n";

# Insert plugins' code.
$plugin_list_path = 'plugins.txt';
$lines = ReadAndTrimLines($plugin_list_path);
foreach ($lines as $line) require($line);

# Find appropriate code for user's '?action='. Assume "view" if not found.
$fallback = 'Action_view';
$action = $_GET['action'];
$action_function = 'Action_'.$action;
if (!function_exists($action_function)) $action_function = $fallback;

# Database manipulation files/dirs. Do urgent work if urgent todo file is found.
$work_dir = 'work/';
$work_temp_dir = $work_dir.'temp/';
$todo_urgent = $work_dir.'todo_urgent';
WorkToDo($todo_urgent);

# Final HTML.
echo '<!DOCTYPE html>'."\n".'<html>'."\n".'<head>'."\n".
                                                  '<meta charset="UTF-8">'."\n";
eval($action_function.'();');
echo "\n".'</body>'."\n".'</html>';

################
# Page actions #
################

function Action_view()
# Formatted display of a page.
{ global $page_path, $page_header, $title;
  
  # Get text from file. If none, show invitation to create one. Else, markup it.
  if (is_file($page_path)) 
  { $text = file_get_contents($page_path);
    $text = EscapeHTML($text);
    $text = Markup($text); }
  else $text = 'Page does not exist. <a href="plomwiki.php?title='.$title.
                                                '&amp;action=edit">Create?</a>';
  
  # Final HTML.
  echo '<title>'.$title.'</title>'."\n".'</head>'."\n".'<body>'."\n".
                                                 $page_header."\n".$text."\n"; }

function Action_history()
# Show version history of page, offer reverting.
{ global $diff_path, $page_header, $title;

  if (is_file($diff_path))
  { $diff = file_get_contents($diff_path);

    # Do some formatting on the diff output and create revert hooks.
    $diffs = explode('%%'."\n", $diff);
    foreach ($diffs as $diff_n => $diff)
    { $diff = explode("\n", $diff);
      foreach ($diff as $line_n => $line) 
      { if ($line_n == 0 and $line !== '') 
        { $time = $line;
          $diff[$line_n] = date('Y-m-d H:i:s', (int) $time); }
        elseif ($line[0] == '>') $diff[$line_n][0] = '+';
        elseif ($line[0] == '<') $diff[$line_n][0] = '-';
        $diff[$line_n] = EscapeHTML($diff[$line_n]).'<br />'; }
      if ($diff[0] !== '') $revert = '<a href="plomwiki.php?title='.$title.
                      '&amp;action=revert&amp;time='.$time.'">Revert</a><br />';
      else $revert = '';
      $diffs[$diff_n] = '<p>'.$revert.implode("\n", $diff).'</p>'; }
    $text = implode("\n", $diffs); }

  else $text = 'Page "'.$title.'" has no history.';

  # Final HTML.
  echo '<title> Version history of page "'.$title.'"</title>'."\n".'</head>'.
                        "\n".'<body>'."\n".$page_header."\n".$text."\n"; }

function Action_edit()
# Edit form on a page source text. Send results to ?action=write.
{ global $page_header, $page_path, $title;

  # If no page file is found, start with an empty $text.
  if (is_file($page_path)) 
  { $text = file_get_contents($page_path); 
    $text = EscapeHTML($text); }
  else $text = '';
  
  # Final HTML.
  echo '<title>Editing "'.$title.'"</title>'."\n".'</head>'."\n".'<body>'."\n".
          $page_header.'<form method="post" action="plomwiki.php?title='.$title.
  '&amp;action=write">'."\n".'<textarea name="text" rows="10" cols="40">'.$text.
  '</textarea><br />'."\n".'Password: <input type="password" name="password" />'
              .'<br /><input type="submit" value="Update!" />'."\n".'</form>'; }

function Action_revert()
# Prepare version reversion and ask user for confirmation.
{ global $diff_path, $title, $page_header, $page_path;

  $time = $_GET['time']; 
  $time_string = date('Y-m-d H:i:s', (int) $time);

  # Build $diff_array from $diff_path to be cycled through, keyed by timestamps.
  $diff_array = array();
  $diffs_text = explode('%%'."\n", file_get_contents($diff_path));
  foreach ($diffs_text as $diff_n => $diff)
  { $diff = explode("\n", $diff);
    $diff_text = '';
    $id = 0;
    foreach ($diff as $line_n => $line) 
    { if ($line_n == 0 and $line !== '') $id = $line;
      else $diff_text .= $line."\n"; }
    if ($id > 0) $diff_array[$id] = $diff_text; }

  # Revert $text back through $diff_array until $time hits $id.
  $text = file_get_contents($page_path);
  $finished = FALSE;
  foreach ($diff_array as $id => $diff)
  { if ($finished) break;
    $reversed_diff = ReverseDiff($diff);
    $text = PlomPatch($text, $reversed_diff);
    if ($time == $id) $finished = TRUE; }

  if ($finished)
  { $content = 'Reverting page to before '.$time_string.'?'."\n".'</p><form '.
  'method="post" action="plomwiki.php?title='.$title.'&amp; action=write">'."\n"
  .'<input type="hidden" name="text" value="'.$text.'"><br />'."\n".'Password:'.
               '<input type="password" name="password" /><input type="submit" '.
                                                         'value="Revert!" />'; }
  else { $content = 'Error. No valid reversion date given.'; }

  # Final HTML.
  echo '<title>Reverting "'.$title.'"</title>'."\n".'</head>'."\n".'<body>'."\n"
                             .$page_header.'<p>'."\n".$content."\n".'</form>'; }

function Action_write()
# Password-protected writing of page update to work/, calling todo that results.
{ global $page_path, $title, $todo_urgent, $diff_path;
  $text = $_POST['text']; $password_posted = $_POST['password'];
  $html_start = '<title>Trying to edit "'.$title.'"</title>';
  
  # Check for failure conditions: wrong $password, empty $text.
  $password_expected = substr(file_get_contents('password.txt'), 0, -1);
  if ($password_posted !== $password_expected) 
    $message = '<strong>Wrong password.</strong>';
  elseif (!$text) $message = '<strong>Empty pages not allowed.</strong><br />'.
      "\n".'Replace page text with "delete" if you want to eradicate the page.';
  
  # Successful edit writes to todo_urgent, triggers work on it and a redirect.
  else
  { $html_start = '<meta http-equiv="refresh" content="0; URL=plomwiki.php?'.
                                             'title='.$title.'" />'.$html_start;
    $p_todo = fopen($todo_urgent, 'a+');
    
    if ($text == 'delete')           # "delete" triggers page deletion.
    { if (is_file($page_path)) 
        fwrite($p_todo, 'DeletePage("'.$page_path.'", "'.$title.'");'."\n");
      $message = '<strong>Page "'.$title.'" is now non-existant.</strong>'; }
  
    else
    { if (get_magic_quotes_gpc())    # 
        $text = stripslashes($text); # Undo possible PHP magical_quotes horrors.
      $text = NormalizeNewlines($text);
      $diff_temp = NewDiffTemp($page_path, $text, $diff_path);
      fwrite($p_todo, 'SafeWrite("'.$diff_path.'", "'.$diff_temp.'");'."\n");
      $page_temp = NewTempFile($text);
      fwrite($p_todo, 'SafeWrite("'.$page_path.'", "'.$page_temp.'");'."\n");
      $message = '<strong>Page "'.$title.'" updated.</strong>'; }
    
    fclose($p_todo);
    WorkToDo($todo_urgent);          # Try to do urgent work at once, right now.
    $message .= '<br />'."\n".
           'If you read this, then your browser failed to redirect you back.'; }
  
  # Final HTML.
  echo $html_start."\n".'</head>'."\n".'<body>'."\n".'<p>'."\n".$message."\n".
   '</p>'."\n".'<p>'."\n".'Return to page "<a href="plomwiki.php?title='.$title.
                                             '">'.$title.'</a>".'."\n".'</p>'; }

####################################
# Page text manipulation functions #
####################################

function Markup($text)
# Applying markup functions in the order described by markups.txt to $text.
{ $markup_list_path = 'markups.txt';
  $lines = ReadAndTrimLines($markup_list_path);
  foreach ($lines as $line) eval('$text = '.$line.'($text);');
  return $text; }

function NormalizeNewlines($text)
# Allow "\n" newline only. "\r" stripped from user input is free for other uses.
{ return str_replace("\r", '', $text); }

function EscapeHTML($text)
# Replace symbols that might be confused for HTML markup with HTML entities.
{ $text = str_replace('&',  '&amp;',   $text);
  $text = str_replace('<',  '&lt;',    $text); 
  $text = str_replace('>',  '&gt;',    $text);
  $text = str_replace('\'', '&apos;',  $text); 
  return  str_replace('"',  '&quot;',  $text); }

###################################
# Database manipulation functions #
###################################

function WorkToDo($path_todo)
# Work through todo file. Comment-out finished lines. Delete file when finished.
{ global $work_dir; 
  if (file_exists($path_todo))
  { LockOn($work_dir);
    $p_todo = fopen($path_todo, 'r+');
    while (!feof($p_todo))
    { $position = ftell($p_todo);
      $line = fgets($p_todo);
      if ($line[0] !== '#')
      { $call = substr($line, 0, -1);
        eval($call);
        fseek($p_todo, $position);
        fwrite($p_todo, '#');
        fgets($p_todo); } }
    fclose($p_todo);
    unlink($path_todo); 
    LockOff($work_dir); } }

function NewTempFile($string)
# Put $string into new $work_temp_dir temp file.
{ global $work_temp_dir;
  LockOn($work_temp_dir);
  $temps = array(0);
  $p_dir = opendir($work_temp_dir); 
  while (FALSE !== ($fn = readdir($p_dir))) if ($fn[0] != '.') $temps[] = $fn;
  closedir($p_dir);
  $int = max($temps) + 1; 
  $temp_path = $work_temp_dir.$int;
  file_put_contents($temp_path, $string);
  LockOff($work_temp_dir); 
  return $temp_path; }

function LockOn($dir)
# Check for and create lockfile for $dir. Lockfiling runs out after $max_time.
{ $lock_duration = 60;   # Lockfile duration. Should be > server execution time.
  $now = time();
  $lock = $dir.'lock';
  if (is_file($lock))
  { $time = file_get_contents($lock);
    if ($time + $lock_duration > $now)
    { echo 'Lockfile found, timestamp too recent. Try again later.'; exit(); } }
  file_put_contents($lock, $now); }

function LockOff($dir)
# Unlock $dir.
{ unlink($dir.'lock'); }

function DeletePage($page_path, $title) 
# Deletion renames and timestamps a page and its diff and moves it to deleted/.
{ global $pages_dir, $diff_dir;
  $pages_del_dir = $pages_dir.'deleted/';
  $timestamp = time();
  $deleted_page_path = $pages_del_dir.$title.',del-page-'.$timestamp;
  $diff_path = $diff_dir.$title;
  $deleted_diff_path = $pages_del_dir.$title.',del-diff-'.$timestamp;
  if (is_file($diff_path)) rename($diff_path, $deleted_diff_path);
  if (is_file($page_path)) rename($page_path, $deleted_page_path); }

function SafeWrite($path_original, $path_temp)
# Avoid data corruption: Exit if no temp file. Rename, don't overwrite directly.
{ if (!is_file($path_temp)) return;
  if (is_file($path_original)) unlink($path_original);
  rename($path_temp, $path_original); }

########
# Diff #
########

function NewDiffTemp($page_path, $text_new, $diff_path)
# Temp of $diff_path diff updated with diff $page_path's content to $text_new.
{ if (is_file($page_path)) $text_old = file_get_contents($page_path);
  else $text_old = '';
  $diff_add = PlomDiff($text_old, $text_new);
  $timestamp = time();
  if (is_file($diff_path)) $diff_old = file_get_contents($diff_path);
  else $diff_old = '';
  $diff_new = $timestamp."\n".$diff_add.'%%'."\n".$diff_old;
  return NewTempFile($diff_new); }

function PlomDiff($text_A, $text_B)
# Output diff $text_A -> $text_B.
{ 
  $lines_A_temp = explode("\n", $text_A); 
  $lines_B_temp = explode("\n", $text_B);

  # Make A and B the same length, one element larger than the largest of them.
  $ur_length_A = count($lines_A_temp); # Remember for later.
  $new_length = max($ur_length_A, count($lines_B_temp)) + 1;
  $lines_A_temp = array_pad($lines_A_temp, $new_length, '');
  $lines_B_temp = array_pad($lines_B_temp, $new_length, '');

  # Our line numbers don't start at 0 but at one.
  foreach ($lines_A_temp as $key => $line) $lines_A[$key + 1] = $line;
  foreach ($lines_B_temp as $key => $line) $lines_B[$key + 1] = $line;

  # Collect additions and deletions from line mismatches between A and B.
  $diff = array(); $offset = 0;
  foreach ($lines_A as $key_A => $line_A)
  { 
    # $offset in B grows/shrinks for each line added/deleted.
    $key_B = $key_A + $offset;
    $line_B = $lines_B[$key_B];
   
    if ($line_A !== $line_B)
    { # Find matching line in later parts of B.
      # If successful, mark the area in-between as lines added.
      $lines_B_sub = array_slice($lines_B, $key_B, NULL, TRUE);
      $change = 0;
      foreach ($lines_B_sub as $key_B_sub => $line_B_sub)
      { if ($line_A == $line_B_sub)
        { $diff[$key_A - 1]['a'] = array($key_B, $key_B + $change);
          $offset += $change + 1; break; }
        $change++; }
      
      # If mismatch is not due to new lines added, mark line as deleted.
      if (!$diff[$key_A - 1]['a'] 
          and $key_A <= $ur_length_A)   # Ignore lines beyond A's real size.
      { $diff[$key_A]['d'] = array($key_B - 1, $key_A);
        $offset--; } } }
  
  # Combine subsequent single line deletions to line deletion blocks.
  $old_del = array(NULL, NULL, -1);
  foreach ($diff as $line_n => $info)
  { foreach ($info as $char => $limits) if ($char == 'd')
    { $new_end = $limits[1];
      $old_end = $old_del[2];
      if ($line_n - 1 == $old_end)
      { $old_line_number = $old_del[0];
        $old_start = $old_del[1];
        $diff[$old_line_number]['d'] = array($old_start, $new_end);
        unset($diff[$line_n]['d']);
        $old_del = array($old_line_number, $old_start, $new_end); }
      else 
      { $new_start = $limits[0]; 
        $old_del = array($line_n, $new_start, $new_end); } } }
  
  # Combine 'a' and 'd' to 'c' in cases where they meet.
  foreach ($diff as $line_n => $info)
  { if ($diff[$line_n]['d'])
    { $end_d = $diff[$line_n]['d'][1];
      if ($diff[$end_d]['a'])
      { $start_a = $diff[$end_d]['a'][0];
        $end_a   = $diff[$end_d]['a'][1];
        $diff[$line_n]['c'] = array($end_d, $start_a, $end_a);
        unset($diff[$line_n]['d']);
        unset($diff[$end_d]['a']); } } }

  # Output diff info.
  $string = '';
  foreach ($diff as $line_n => $info)
  { foreach ($info as $char => $limits)
    { if ($char == 'a') 
      { if ($limits[0] == $limits[1]) $string .= $line_n.$char.$limits[0]."\n";
        else $string .= $line_n.$char.$limits[0].','.$limits[1]."\n";
        for ($i = $limits[0]; $i <= $limits[1]; $i++) 
          $string .= '>'.$lines_B[$i]."\n"; }
      elseif ($char == 'd')
      { if ($line_n == $limits[1]) $string .= $line_n.$char.$limits[0]."\n";
        else $string .= $line_n.','.$limits[1].$char.$limits[0]."\n";
        for ($i = $line_n; $i <= $limits[1]; $i++)
          $string .= '<'.$lines_A[$i]."\n"; }
      elseif ($char == 'c')
      { if ($line_n == $limits[0]) $string .= $line_n.$char;
        else $string .= $line_n.','.$limits[0].$char;
        if ($limits[1] == $limits[2]) $string .= $limits[1]."\n";
        else $string .= $limits[1].','.$limits[2]."\n";
        for ($i = $line_n; $i <= $limits[0]; $i++)
          $string .= '<'.$lines_A[$i]."\n";
        for ($i = $limits[1]; $i <= $limits[2]; $i++)
          $string .= '>'.$lines_B[$i]."\n"; } } }

  return $string; }

function PlomPatch($text_A, $diff)
# Patch $text_A to $text_B via $diff.
{ 
  # Divide $diff's lines into chunks belonging to single actions.
  $patch_lines = explode("\n", $diff);
  $patch_temp = array(); $action_temp = '';
  foreach ($patch_lines as $line)
  { if ($line[0] !== '<' and $line[0] !== '>') $action_temp = $line;
    else $patch_temp[$action_temp][] = $line; }

  # Collect patch-relevant info array $patch.
  $patch = array();
  foreach ($patch_temp as $action_temp => $lines)
  { if (strpos($action_temp, 'd'))
    { list($left, $x) = explode('d', $action_temp);
      if (!strpos($left, ',')) $left = $left.','.$left;
      list($start, $end) = explode(',', $left);
      $action = 'd'.$start;
      $patch[$action] = $end; }
    elseif (strpos($action_temp, 'a'))
    { list($start, $x) = explode('a', $action_temp);
      $action = 'a'.$start;
      $patch[$action] = $lines; }
    elseif (strpos($action_temp, 'c'))
    { list($left, $right) = explode('c', $action_temp);
      if (!strpos($left, ',')) $left = $left.','.$left;
      list($start, $end) = explode(',', $left);
      $action = 'd'.$start;
      $patch[$action] = $end;
      $action = 'a'.$start;
      $add_lines = array();
      foreach ($lines as $line) if ($line[0] == '>') $add_lines[] = $line;
      $patch[$action] = $add_lines;}}

  # Apply additions and deletions.
  $lines_A = explode("\n", $text_A);
  foreach ($lines_A as $key => $line) $lines_A[$key + 1] = $line."\n";
  $lines_A[0] = '';
  $lines_B = $lines_A;
  foreach ($patch as $action => $value)
  { if ($action[0] == 'a')
    { $put_after_line = substr($action, 1);
      foreach ($value as $line_diff)
        $lines_B[$put_after_line] .= substr($line_diff, 1)."\n"; }
    elseif ($action[0] == 'd')
    { $delete_from_line = substr($action, 1);
      $end = $value;
      for ($i = $delete_from_line; $i <= $end; $i++) 
      { $ur_ln = strlen($lines_A[$i]);
        $lines_B[$i] = substr($lines_B[$i], $ur_ln); } } }
  $text_B = implode($lines_B);

  return $text_B; }

function ReverseDiff($old_diff)
# Reverse a diff.
{ $new_diff = '';
  $old_diff = explode("\n", $old_diff);
  foreach ($old_diff as $line_n => $line)
  { if     ($line[0] == '<') $line[0] = '>'; 
    elseif ($line[0] == '>') $line[0] = '<';
    else 
    { foreach (array('c' => 'c', 'a' => 'd', 'd' => 'a') as $char => $reverse) 
      { if (strpos($line, $char))
        { list($left, $right) = explode($char, $line); 
          $line = $right.$reverse.$left; 
          break; } } }
    $new_diff .= $line."\n"; }
  return $new_diff; }

##########################
# Minor helper functions #
##########################

function ReadAndTrimLines($path)
# Read file $path into a list of all lines sans comments and ending whitespaces.
{ $list = array();
  $lines = explode("\n", file_get_contents($path));
  foreach ($lines as $line)
  { $hash_pos = strpos($line, '#');
    if ($hash_pos !== FALSE) $line = substr($line, 0, $hash_pos);
    $line = rtrim($line);
    if ($line) $list[] = $line; } 
  return $list; }
