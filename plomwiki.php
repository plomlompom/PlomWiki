<?php
# PlomWiki: @plomlompom's wiki.      This file contains the core execution code.

# Filesystem information.
$config_dir = 'config/';         $markup_list_path = $config_dir.'markups.txt';
                                 $password_path    = $config_dir.'password.txt';
                                 $plugin_list_path = $config_dir.'plugins.txt';
$pages_dir  = 'pages/';                  $work_dir = 'work/';
$diff_dir = $pages_dir.'diffs/';         $work_temp_dir = $work_dir.'temp/';
$del_dir = $pages_dir.'deleted/';        $todo_urgent = $work_dir.'todo_urgent'; 

# HTML start and end.
$html_start = '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>'; 
$html_end = '

</body>
</html>';

# Check for unfinished setup file, execute if found.
$setup_file = 'setup.php';
if (is_file($setup_file)) require($setup_file);

# Only allow simple alphanumeric titles to avoid security risks.
$title = $_GET['title']; 
if (!preg_match('/^[a-zA-Z0-9]+$/', $title)) 
{ echo 'Illegal page title. Only alphanumeric characters allowed.'; exit(); }
$page_path = $pages_dir.$title; $diff_path = $diff_dir. $title;

# Normal view start.
$normal_view_start = '</title>
</head>
<body>

<h1>'.$title.'</h1>
<p>
<a href="plomwiki.php?title='.$title.'">View</a> 
<a href="plomwiki.php?title='.$title.'&amp;action=edit">Edit</a> 
<a href="plomwiki.php?title='.$title.'&amp;action=history">History</a> 
</p>
'."\n";

# Insert plugins' code.
$lines = ReadAndTrimLines($plugin_list_path); 
foreach ($lines as $line) require($line);

# Find appropriate code for user's '?action='. Assume "view" if not found.
$fallback = 'Action_view'; 
$action = $_GET['action'];                 $action_function = 'Action_'.$action;
if (!function_exists($action_function))    $action_function = $fallback;

# Do urgent work if urgent todo file is found.
WorkToDo($todo_urgent);

# Final HTML.
echo $html_start; eval($action_function.'();'); echo $html_end;

################
# Page actions #
################

function Action_view()
# Formatted display of a page.
{ global $normal_view_start, $page_path, $title;
  
  # Get text from file. If none, show invitation to create one. Else, markup it.
  if (is_file($page_path)) 
  { $text = file_get_contents($page_path);
    $text = EscapeHTML($text);
    $text = Markup($text); }
  else $text = 'Page does not exist. <a href="plomwiki.php?title='.$title.
                                                '&amp;action=edit">Create?</a>';
  
  # Final HTML.
  echo $title.$normal_view_start.$text; }

function Action_history()
# Show version history of page (based on its diff file), offer reverting.
{ global $diff_path, $normal_view_start, $title;

  # Check for non-empty diff file on page. Remove superfluous "%%" and "\n".
  $text = 'Page "'.$title.'" has no history.';                   $diff_all = '';
  if (is_file($diff_path))
  { $diff_all = file_get_contents($diff_path);
    if (substr($diff_all,0,2) == '%%'     ) $diff_all = substr($diff_all,3);
    if (substr($diff_all, -3) == '%%'."\n") $diff_all = substr($diff_all,0,-3);
    if (substr($diff_all, -2) == '%%'     ) $diff_all = substr($diff_all,0,-2);
    if (substr($diff_all, -1) == "\n"     ) $diff_all = substr($diff_all,0,-1);}
  if ($diff_all != '')

  # Transform $diff_all into structured HTML output. Add revert-by-time hooks.
  { $diffs = explode('%%'."\n", $diff_all);
    foreach ($diffs as $diff_n => $diff_str)
    { if (substr($diff_str, -1) == "\n")      # Last element's ending "\n" isn't
        $diff_str = substr($diff_str, 0, -1); # needed, would trigger explode()
      $diff = explode("\n", $diff_str);       # to an empty final element.
      $time = '';
      foreach ($diff as $line_n => $line) 
      { if ($line_n == 0) 
        { $time = $line;
          $diff[$line_n] = date('Y-m-d H:i:s', (int) $time); }
        elseif ($line[0] == '>') $diff[$line_n][0] = '+';
        elseif ($line[0] == '<') $diff[$line_n][0] = '-';
        $diff[$line_n] = EscapeHTML($diff[$line_n]).'<br />'; }
      $diff_output = implode("\n", $diff);
      $diff_output = substr($diff_output, 0, -6); # Delete superfluous "<br />".
      $diffs[$diff_n] = '<p>'."\n".
      '<a href="plomwiki.php?title='.$title.'&amp;action=revert'.
                                   '&amp;time='.$time.'">Revert</a><br />'."\n".
      $diff_output."\n".
      '</p>'; }
    $text = implode("\n", $diffs); }

  # Final HTML.
  echo 'Version history of page "'.$title.'"'.$normal_view_start.$text; }

function Action_edit()
# Edit form on a page source text. Send results to ?action=write.
{ global $markup_help, $normal_view_start, $page_path, $title;

  # If no page file is found, start with an empty $text.
  if (is_file($page_path)) 
  { $text = file_get_contents($page_path); 
    $text = EscapeHTML($text); }
  else $text = '';
  
  # Final HTML.
  echo 'Editing "'.$title.$normal_view_start.
  '<form method="post" action="plomwiki.php?title='.$title.'&amp;action=write">'
                                                                          ."\n".
  '<textarea name="text" rows="20" style="width:100%">'.$text.'</textarea><br />'."\n".
  'Password: <input type="password" name="password" /> <input type="submit" '.
                                                      'value="Update!" />'."\n".
  '</form>'."\n\n".
  $markup_help; }

function Action_revert()
# Prepare version reversion and ask user for confirmation.
{ global $normal_view_start, $diff_path, $title, $page_path;
  $time = $_GET['time'];        $time_string = date('Y-m-d H:i:s', (int) $time);

  # Build $diff_array from $diff_path to be cycled through, keyed by timestamps.
  $diff_array = array();
  $diffs_text = explode('%%'."\n", file_get_contents($diff_path));
  foreach ($diffs_text as $diff_n => $diff_str)
  { $diff = explode("\n", $diff_str);                  $diff_text = ''; $id = 0;
    foreach ($diff as $line_n => $line) 
    { if ($line_n == 0 and $line !== '') $id = $line;
      else                               $diff_text .= $line."\n"; }
    if ($id > 0) $diff_array[$id] = $diff_text; }

  # Revert $text back through $diff_array until $time hits $id.
  $text = file_get_contents($page_path);                      $finished = FALSE;
  foreach ($diff_array as $id => $diff)
  { if ($finished) break;
    $reversed_diff = ReverseDiff($diff); 
    $text = PlomPatch($text, $reversed_diff);  
    if ($time == $id) $finished = TRUE; }

  if ($finished)
  { $content = 'Reverting page to before '.$time_string.'?</p>'."\n".
    '<form method="post" action="plomwiki.php?title='.$title.'&amp;'.
                                                          'action=write">'."\n".
    '<input type="hidden" name="text" value="'.$text.'">'."\n".
    'Password: <input type="password" name="password" />'."\n".
    '<input type="submit" value="Revert!" />'."\n".
    '</form>'; }
  else { $content = 'Error. No valid reversion date given.</p>'; }

  # Final HTML.
  echo 'Reverting "'.$title.$normal_view_start.'<p>'.$content; }

function Action_write()
# Password-protected writing of page update to work/, calling todo that results.
{ global $page_path, $password_path, $title, $todo_urgent, $diff_path;
  $text = $_POST['text']; $password_posted = $_POST['password']; $redirect = '';
  $old_text = '';
  if (is_file($page_path)) $old_text = file_get_contents($page_path);

  # Repair problems in submitted text. Undo possible PHP magical_quotes horrors.
  if (get_magic_quotes_gpc()) $text = stripslashes($text);
  $text = NormalizeNewlines($text);
  
  # Check for failure conditions: wrong $password, empty $text, $text unchanged.
  $password_expected = substr(file_get_contents($password_path), 0, -1);
  if ($password_posted !== $password_expected) 
    $msg ='Wrong password.</strong>';
  elseif (!$text) 
    $msg = 'Empty pages not allowed.</strong><br />'."\n".
    'Replace page text with "delete" if you want to eradicate the page.';
  elseif (NormalizeNewlines(stripslashes($text)) == $old_text)
    $msg = 'You changed nothing!</strong>';  

  # Successful edit writes to todo_urgent, triggers work on it and a redirect.
  else
  { $redirect = "\n".'<meta http-equiv="refresh" content="0; URL=plomwiki.php?'.
                                                         'title='.$title.'" />';
    $p_todo = fopen($todo_urgent, 'a+');
    
    # In case of "delete", add DeletePage() task to todo file.
    if ($text == 'delete')
    { if (is_file($page_path)) 
        fwrite($p_todo, 'DeletePage("'.$page_path.'", "'.$title.'");'."\n");
      $msg = 'Page "'.$title.'" is deleted (if it ever existed).</strong>'; }
  
    # Write $text, $diff temp files. Add SafeWrite() tasks to todo.
    else
    { $diff_temp = NewDiffTemp($old_text, $text, $diff_path);
      fwrite($p_todo, 'SafeWrite("'.$diff_path.'", "'.$diff_temp.'");'."\n");
      $page_temp = NewTempFile($text);
      fwrite($p_todo, 'SafeWrite("'.$page_path.'", "'.$page_temp.'");'."\n");
      $msg = 'Page "'.$title.'" updated.</strong>'; }
    
    # Try to finish newly added urgent work straight away before continuing.
    fclose($p_todo);  WorkToDo($todo_urgent);
    $msg .= '<br />'."\n".
    'If you read this, then your browser failed to redirect you back.'; }
  
  # Final HTML.
  echo 'Trying to edit "'.$title.'"</title>'
  .$redirect."\n".
  '</head>'."\n".
  '<body>'."\n".
  "\n".
  '<p><strong>'.$msg.'</p>'."\n".
  '<p>Return to page "<a href="plomwiki.php?title='.$title.'">'.$title.'</a>".'.
                                                                        '</p>';}

####################################
# Page text manipulation functions #
####################################

function Markup($text)
# Applying markup functions in the order described by markups.txt to $text.
{ global $markup_list_path; 
  $lines = ReadAndTrimLines($markup_list_path);
  foreach ($lines as $line) eval('$text = '.$line.'($text);');
  return $text; }

function NormalizeNewlines($text)
# Allow "\n" newline only. "\r" stripped from user input is free for other uses.
{ return str_replace("\r", '', $text); }

function EscapeHTML($text)
# Replace symbols that might be confused for HTML markup with HTML entities.
{ $text = str_replace('&',  '&amp;',  $text);
  $text = str_replace('<',  '&lt;',   $text); 
  $text = str_replace('>',  '&gt;',   $text);
  $text = str_replace('\'', '&apos;', $text); 
  return  str_replace('"',  '&quot;', $text); }

###################################
# Database manipulation functions #
###################################

function WorkToDo($path_todo)
# Work through todo file. Comment-out finished lines. Delete file when finished.
{ global $work_dir; 
  if (file_exists($path_todo))
  { LockOn($work_dir); $p_todo = fopen($path_todo, 'r+');
    while (!feof($p_todo))
    { $position = ftell($p_todo);             
      $line = fgets($p_todo);
      if ($line[0] !== '#')
      { $call = substr($line, 0, -1); eval($call);
        fseek($p_todo, $position); fwrite($p_todo, '#'); fgets($p_todo); } }
    fclose($p_todo); unlink($path_todo); LockOff($work_dir); } }

function NewTempFile($string)
# Put $string into new $work_temp_dir temp file.
{ global $work_temp_dir;
  LockOn($work_temp_dir); $p_dir = opendir($work_temp_dir);   $temps = array(0);
  while (FALSE !== ($fn = readdir($p_dir))) if ($fn[0] != '.') $temps[] = $fn;
  $int = max($temps) + 1; 
  $temp_path = $work_temp_dir.$int;
  file_put_contents($temp_path, $string);
  closedir($p_dir); LockOff($work_temp_dir); 
  return $temp_path; }

function LockOn($dir)
# Check for and create lockfile for $dir. Lockfiling runs out after $max_time.
{ $lock_duration = 60;   # Lockfile duration. Should be > server execution time.
  $now = time(); $lock = $dir.'lock';
  if (is_file($lock))
  { $time = file_get_contents($lock);
    if ($time + $lock_duration > $now)
    { echo 'Lockfile found, timestamp too recent. Try again later.'; exit(); } }
  file_put_contents($lock, $now); }

function LockOff($dir)
# Unlock $dir.
{ unlink($dir.'lock'); }

function DeletePage($page_path, $title) 
# Deletion renames and timestamps a page and its diff and moves it to $del_dir.
{ global $pages_dir, $diff_dir, $del_dir;
  $timestamp = time();
  $deleted_page_path = $del_dir.$title.',del-page-'.$timestamp;
  $diff_path = $diff_dir.$title;
  $deleted_diff_path = $del_dir.$title.',del-diff-'.$timestamp;
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

function NewDiffTemp($text_old, $text_new, $diff_path)
# Build temp file of $diff_path updated to diff $page_path text -> $text_new.
{ $diff_add = PlomDiff($text_old, $text_new);
  if (is_file($diff_path)) $diff_old = file_get_contents($diff_path);
  else                     $diff_old = '';
  $timestamp = time();
  $diff_new = $timestamp."\n".$diff_add.'%%'."\n".$diff_old;
  return NewTempFile($diff_new); }

function PlomDiff($text_A, $text_B)
# Output diff $text_A -> $text_B.
{ 
  # Pad $lines_A and $lines_B to same length, add one empty line at end. Start
  # line counting in $lines_A and $lines_B at 1, not 0 -- just like diff does.
  $lines_A_tmp   = explode("\n", $text_A);  
  $lines_B_tmp   = explode("\n", $text_B);
  $original_ln_A = count($lines_A_tmp);     # Will be needed further below, too.
  $new_ln        = max($original_ln_A, count($lines_B_tmp)) + 1;
  $lines_A_tmp   = array_pad($lines_A_tmp, $new_ln, "\r");
  $lines_B_tmp   = array_pad($lines_B_tmp, $new_ln, "\r");
  foreach ($lines_A_tmp as $k => $line) $lines_A[$k + 1] = $line;
  foreach ($lines_B_tmp as $k => $line) $lines_B[$k + 1] = $line;

  # Collect adds / dels from line mismatches between $lines_{A,B} into $diff.
  # add pattern: $diff[$before_in]['a'] = array($in_first, $in_last)
  # del pattern: $diff[$out_first]['d'] = array($before_out, $out_last)
  $diff = array(); $offset = 0;
  foreach ($lines_A as $key_A => $line_A)
  { 
    # $offset in $lines_B grows/shrinks for each line added/deleted.
    $key_B = $key_A + $offset; $line_B = $lines_B[$key_B];
   
    if ($line_A !== $line_B)
    { # Find matching line in later lines of $lines_B. If successful, mark lines
      # range in-between as lines added and add its length $range to $offset.
      $lines_B_later = array_slice($lines_B, $key_B, NULL, TRUE);
      $range = 0; 
      foreach ($lines_B_later as $key_B_sub => $line_B_sub)
      { $range++;
        if ($line_A == $line_B_sub)
        { $diff[$key_A - 1]['a'] = array($key_B, $key_B + $range - 1);
          $offset += $range;
          break; } }
      
      # If mismatch is unredeemed by matching later lines, mark line as deleted
      # -- except for (temporarily added "\r") lines beyond $original_ln_A.
      if (!$diff[$key_A - 1]['a'] and $key_A <= $original_ln_A)
      { $diff[$key_A]['d'] = array($key_B - 1, $key_A);
        $offset--; } } }
  
  # Combine subsequent single line dels to line del blocks by, for each del,
  # checking if $old_del's $out_last is just one line before new $out_first.
  $old_del = array(NULL, NULL, -1);  # = array($out_first,$before_out,$out_last)
  foreach ($diff as $line_n => $info)
  { foreach ($info as $char => $limits) if ($char == 'd')
    { $new_out_last = $limits [1]; 
      $old_out_last = $old_del[2];
      if ($line_n - 1 == $old_out_last)
      { $old_out_first = $old_del[0]; $old_before_out = $old_del[1];
        $diff[$old_out_first]['d'] = array($old_before_out, $new_out_last);
        unset($diff[$line_n]['d']);
        $old_del = array($old_out_first, $old_before_out, $new_out_last); }
      else 
      { $new_start_in_B = $limits[0]; 
        $old_del = array($line_n, $new_start_in_B, $new_out_last); } } }
  
  # Combine 'a' and 'd' to 'c' in cases where they meet.
  # 'c' pattern: $diff[$out_first] = array($out_last, $in_first, $in_last);
  foreach ($diff as $line_n => $info)
  { if ($diff[$line_n]['d'])
    { $out_last = $diff[$line_n]['d'][1];
      if ($diff[$out_last]['a'])
      { $in_first = $diff[$out_last]['a'][0]; 
        $in_last  = $diff[$out_last]['a'][1];
        $diff[$line_n]['c'] = array($out_last, $in_first, $in_last);
        unset($diff[$line_n]['d']); unset($diff[$out_last]['a']); } } }

  # Output diff into $string and return.
  $string = '';
  foreach ($diff as $line_n => $info)
  { foreach ($info as $char => $limits)
    { if ($char == 'a') 
      { if ($limits[0] == $limits[1]) $string .= $line_n.$char.$limits[0]."\n";
        else                          $string .= $line_n.$char.$limits[0].','.
                                                                $limits[1]."\n";
        for ($i = $limits[0]; $i <= $limits[1]; $i++)
          $string .= '>'.$lines_B[$i]."\n"; }
      elseif ($char == 'd')
      { if ($line_n    == $limits[1]) $string .= $line_n.$char.$limits[0]."\n";
        else                          $string .= $line_n.','.$limits[1].$char.
                                                                $limits[0]."\n";
        for ($i = $line_n; $i <= $limits[1]; $i++)
          $string .= '<'.$lines_A[$i]."\n"; }
      elseif ($char == 'c')
      { if ($line_n    == $limits[0]) $string .= $line_n.$char;
        else                          $string .= $line_n.','.$limits[0].$char;
        if ($limits[1] == $limits[2]) $string .= $limits[1]."\n";
        else                          $string .= $limits[1].','.$limits[2]."\n";
        for ($i = $line_n; $i <= $limits[0]; $i++)
          $string .= '<'.$lines_A[$i]."\n";
        for ($i = $limits[1]; $i <= $limits[2]; $i++)
          $string .= '>'.$lines_B[$i]."\n"; } } }
  return $string; }

function PlomPatch($text_A, $diff)
# Patch $text_A to $text_B via $diff.
{ 
  # Explode $diff into $patch_tmp = array($action_tmp => array($line, ...), ...)
  $patch_lines = explode("\n", $diff);   $patch_tmp = array(); $action_tmp = '';
  foreach ($patch_lines as $line)
  { if ($line[0] != '<' and $line[0] != '>') $action_tmp = $line;
    else                                     $patch_tmp[$action_tmp][] = $line;}

  # Collect add/delete lines info (split 'c' into both) from $patch_tmp into
  # $patch = array($start.'a' => array($line, ...), $start.'d' => $end, ...)
  $patch = array();
  foreach ($patch_tmp as $action_tmp => $lines)
  { if     (strpos($action_tmp, 'd'))
           { list($left, $ignore) = explode('d', $action_tmp);
             if (!strpos($left, ',')) $left = $left.','.$left;
             list($start, $end) = explode(',', $left);
             $action = 'd'.$start; $patch[$action] = $end; }
    elseif (strpos($action_tmp, 'a'))
           { list($start, $ignore) = explode('a', $action_tmp);
             $action = 'a'.$start; $patch[$action] = $lines; }
    elseif (strpos($action_tmp, 'c'))
           { list($left, $right) = explode('c', $action_tmp);
             if (!strpos($left, ',')) $left = $left.','.$left;
             list($start, $end) = explode(',', $left);
             $action = 'd'.$start; $patch[$action] = $end;
             $action = 'a'.$start; 
             foreach ($lines as $line) if ($line[0] == '>')
               $patch[$action][] = $line; } }

  # Create $lines_{A,B} arrays where key equals line number. Add temp 0-th line.
  $lines_A = explode("\n", $text_A); 
  foreach ($lines_A as $key => $line) $lines_A[$key + 1] = $line."\n";
  $lines_A[0] = ''; $lines_B = $lines_A;

  foreach ($patch as $action => $value)
  { # Glue new lines to $lines_B[$apply_after_line] with "\n".
    if     ($action[0] == 'a')
           { $apply_after_line = substr($action, 1);
             foreach ($value as $line_diff)
               $lines_B[$apply_after_line] .= substr($line_diff, 1)."\n"; }
    # Cut deleted lines' lengths from $lines_B[$apply_from_line:$until_line].
    elseif ($action[0] == 'd')
           { $apply_from_line = substr($action, 1); $until_line = $value;
             for ($i = $apply_from_line; $i <= $until_line; $i++) 
             { $end_of_original_line = strlen($lines_A[$i]);
               $lines_B[$i] = substr($lines_B[$i], $end_of_original_line); } } }

  # Before returning, remove potential superfluous "\n" at $text_B end.
  $text_B = implode($lines_B);
  if (substr($text_B,-1) == "\n") $text_B = substr($text_B,0,-1);
  return $text_B; }

function ReverseDiff($old_diff)
# Reverse a diff.
{ $old_diff = explode("\n", $old_diff);                          $new_diff = '';
  foreach ($old_diff as $line_n => $line)
  { if     ($line[0] == '<') $line[0] = '>'; 
    elseif ($line[0] == '>') $line[0] = '<';
    else 
    { foreach (array('c' => 'c', 'a' => 'd', 'd' => 'a') as $char => $reverse) 
      { if (strpos($line, $char))
        { list($left, $right) = explode($char, $line); 
          $line = $right.$reverse.$left; break; } } }
    $new_diff .= $line."\n"; }
  return $new_diff; }

##########################
# Minor helper functions #
##########################

function ReadAndTrimLines($path)
# Read file $path into a list of all lines sans comments and ending whitespaces.
{ $lines = explode("\n", file_get_contents($path));             $list = array(); 
  foreach ($lines as $line)
  { $hash_pos = strpos($line, '#');
    if ($hash_pos !== FALSE) $line = substr($line, 0, $hash_pos);
    $line = rtrim($line);
    if ($line) $list[] = $line; } 
  return $list; }
