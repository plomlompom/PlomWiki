<?php

# Only allow simple alphanumeric titles to avoid security risks.
$title = $_GET['title']; 
if (!preg_match('/^[a-zA-Z0-9]+$/', $title)) { echo 'Bad page title'; exit(); }

# Where page data is located.
$pages_dir  = 'pages/';
$page_path = $pages_dir.$title;

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
{ global $page_path, $title;
  
  # Get text from file. If none, show invitation to create one. Else, markup it.
  if (is_file($page_path)) 
  { $text = file_get_contents($page_path); 
    $text = Markup($text); }
  else $text = 'Page does not exist. <a href="plomwiki.php?title='.$title.
                                                '&amp;action=edit">Create?</a>';
  
  # Final HTML.
  echo '<title>'.$title.'</title>'."\n".'</head>'."\n".'<body>'."\n".'<p>'."\n".
    $title.': <a href="plomwiki.php?title='.$title.'&amp;action=edit">Edit</a>'.
                                "\n".'</p>'."\n".'<p>'."\n".$text."\n".'</p>'; }

function Action_edit()
# Edit form on a page source text. Send results to ?action=write.
{ global $page_path, $title;
  
  # If no page file is found, start with an empty $text.
  if (is_file($page_path)) 
  { $text = file_get_contents($page_path); 
    $text = EscapeHTML($text); }
  else $text = '';
  
  # Final HTML.
  echo '<title>Editing "'.$title.'"</title>'."\n".'</head>'."\n".'<body>'."\n".
  '<p>'."\n".$title.': <a href="plomwiki.php?title='.$title.'">Back to View</a>'
     ."\n".'</p>'."\n".'<form method="post" action="plomwiki.php?title='.$title.
  '&amp;action=write">'."\n".'<textarea name="text" rows="10" cols="40">'.$text.
  '</textarea><br />'."\n".'Password: <input type="password" name="password" />'
              .'<br /><input type="submit" value="Update!" />'."\n".'</form>'; }

function Action_write()
# Password-protected writing of page update to work/, calling todo that results.
{ global $page_path, $title, $todo_urgent;
  $text = $_POST['text']; $password_posted = $_POST['password'];
  $html_start = '<title>Trying to edit "'.$title.'"</title>';
  
  # Check for failure conditions: wrong password, empty $text.
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
        fwrite($p_todo, 'DeletePage("'.$title.'");'."\n");
      $message = '<strong>Page "'.$page_path.'" is now non-existant.</strong>';}
  
    else
    { if (get_magic_quotes_gpc())    # 
        $text = stripslashes($text); # Undo possible PHP magical_quotes horrors.
      $temp_path = NewTempFile($text);
      fwrite($p_todo, 'UpdatePage("'.$page_path.'", "'.$temp_path.'");'."\n");
      $message = '<strong>Page "'.$title.'" updated.</strong>'; }
    
    fclose($p_todo);
    WorkToDo($todo_urgent);          # Try to do urgent work at once, right now.
    $message .= '<br />'."\n".
           'If you read this, then your browser failed to redirect you back.'; }
  
  # Final HTML.
  echo $html_start."\n".'</head>'."\n".'<body>'."\n".'<p>'."\n".$message."\n".
   '</p>'."\n".'<p>'."\n".'Return to page "<a href="plomwiki.php?title='.$title.
                                             '">'.$title.'</a>".'."\n".'</p>'; }

function Action_work()
# Work through todo list.
{ global $work_dir;

  $path_todo = $work_dir.'todo';
  
  echo '<title>Doing some processing work ...</title>'."\n".'</head>'."\n".
          '<body>'."\n".'<p>'."\n".'Doing some processing work ...'."\n".'</p>';
  WorkToDo($path_todo);
  echo '<p>'."\n".'Finished!'."\n".'</p>'; }

####################
# Markup functions #
####################

function Markup($text)
# Applying markup functions in a certain order to $text.
{ $text = EscapeHTML($text);
  $text = MarkupLinesParagraphs($text);
  return  MarkupInternalLinks($text); }

function EscapeHTML($text)
# Replace symbols that might be confused for HTML markup with HTML entities.
{ $text = str_replace('&', '&amp;', $text);
  $text = str_replace('<', '&lt;',  $text); 
  $text = str_replace('>', '&gt;',  $text);
  $text = str_replace('\'', '&apos;',  $text); 
  return  str_replace('"', '&quot;',  $text); }

function MarkupLinesParagraphs($text)
# Line-break and paragraph markup.
{ $text = str_replace("\r\n\r", "\n".'</p>'."\n".'<p>', $text);
  return  str_replace("\r", '<br />', $text); }

function MarkupInternalLinks($text)
# Wiki-internal linking markup [[LikeThis]].
{ return preg_replace('/\[\[([A-Za-z0-9]+)\]\]/', 
                             '<a href="plomwiki.php?title=$1">$1</a>', $text); }

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

function DeletePage($page_path) 
# What to do when a page is to be deleted. Might grow more elaborate later.
{ unlink($pages_path); }

function UpdatePage($page_path, $temp_path)
# Avoid data corruption: Exit if no temp file. Rename, don't overwrite directly.
{ if (!is_file($temp_path)) return;
  if (is_file($page_path)) unlink($page_path);
  rename($temp_path, $page_path); }
