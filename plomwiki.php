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
echo '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
';
eval($action_function.'();');
echo '
</body>
</html>';

################
# Page actions #
################

function Action_view()
# Formatted display of a page.
{ global $page_path, $title;
  
  # Get $text from its page file.
  if (is_file($page_path)) 
  { $text = file_get_contents($page_path); 

    # Replace symbols that might be confused for HTML markup with HTML entities.
    $text = str_replace('&', '&amp;', $text);
    $text = str_replace('<', '&lt;',  $text); 
    $text = str_replace('>', '&gt;',  $text); 
    $text = str_replace('\'', '&apos;',  $text); 
    $text = str_replace('"', '&quot;',  $text); 
    
    # Line-break and paragraph markup.
    $text = str_replace("\r\n\r", "\n".'</p>'."\n".'<p>', $text);
    $text = str_replace("\r", '<br />', $text);
    
    # Wiki-internal linking markup [[LikeThis]].
    $text = preg_replace('/\[\[([A-Za-z0-9]+)\]\]/', 
                             '<a href="plomwiki.php?title=$1">$1</a>', $text); }
  
  # If no page file is found, show invitation to create one.
  else $text = 'Page does not exist. <a href="plomwiki.php?title='.$title.
                                                '&amp;action=edit">Create?</a>';
  
  # Final HTML.
  echo '<title>'.$title.'</title>
</head>
<body>
<p>
'.$title.': <a href="plomwiki.php?title='.$title.'&amp;action=edit">Edit</a>
</p>
<p>
'.$text.'
</p>'; }

function Action_edit()
# Edit form on a page source text. Send results to ?action=write.
{ global $page_path, $title;
  
  # If no page file is found, start with an empty $text.
  if (is_file($page_path)) $text = file_get_contents($page_path); 
  else $text = '';
  
  # Replace symbols that might be confused for HTML markup with HTML entities.
  $text = str_replace('&', '&amp;', $text);
  $text = str_replace('<', '&lt;',  $text); 
  $text = str_replace('>', '&gt;',  $text);
  $text = str_replace('\'', '&apos;',  $text); 
  $text = str_replace('"', '&quot;',  $text); 
  
  # Final HTML.
  echo '<title>Editing "'.$title.'"</title>
</head>
<body>
<p>
'.$title.': <a href="plomwiki.php?title='.$title.'">Back to View</a>
</p>
<form method="post" action="plomwiki.php?title='.$title.'&amp;action=write">
<textarea name="text" rows="10" cols="40">'.$text.'
</textarea><br />
Password: <input type="password" name="password" /><br />
<input type="submit" value="Update!" />
</form>'; }

function Action_write()
# Password-protected writing of page update to work/.
{ global $page_path, $title, $todo_urgent;
  
  # The edited page text submitted by edit.php.
  $text = $_POST['text'];
  
  # Start by checking for edit failure conditions.
  $html_start = '<title>Trying to edit "'.$title.'"</title>';
  
  # Check for failure condition: wrong password.
  $password_posted = $_POST['password'];
  $password_expected = substr(file_get_contents('password.txt'), 0, -1);
  if ($password_posted !== $password_expected)
    $message = '<strong>Wrong password.</strong>';
  
  # Check for failure condition: empty $text.
  elseif (!$text)
    $message = 
'<strong>Empty pages not allowed.</strong><br />
Replace the page text with "delete" if you want to eradicate the page.';
  
  # Successful edit writes to todo_urgent, triggers work on it and a redirect.
  else
  { $html_start = 
                '<meta http-equiv="refresh" content="0; URL=plomwiki.php?title='
                                                     .$title.'" />'.$html_start;
    $p_todo = fopen($todo_urgent, 'a+');
    
    # "delete" triggers page deletion.
    if ($text == 'delete')
    { if (is_file($page_path)) 
        fwrite($p_todo, 'DeletePage("'.$title.'");'."\n");
      $message = '<strong>Page "'.$page_path.'" is now non-existant.</strong>';}
  
    else
    { # Undo damage that results from PHP's magical_quotes horrors.
      if (get_magic_quotes_gpc()) $text = stripslashes($text);
      
      # Write $text into a temp file to be given to UpdatePage().
      $temp_path = NewTempFile($text);
      fwrite($p_todo, 'UpdatePage("'.$page_path.'", "'.$temp_path.'");'."\n");
      $message = '<strong>Page "'.$title.'" updated.</strong>'; }
    
    fclose($p_todo);
    WorkToDo($todo_urgent);
    
    # Message for very speedy readers or very slow redirects.
    $message .= '<br />
If you read this, then your browser failed to redirect you back.'; }
  
  # Final HTML.
  echo $html_start.'
</head>
<body>
<p>
'.$message.'
</p>
<p>
Return to page "<a href="plomwiki.php?title='.$title.'">'.$title.'</a>".
</p>'; }

function Action_work()
# Work through todo list.
{ global $work_dir;

  $path_todo = $work_dir.'todo';
  
  echo '<title>Doing some processing work ...</title>
</head>
<body>
<p>
Doing some processing work ...
</p>';
  WorkToDo($path_todo);
  echo '<p>
Finished!
</p>'; }

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
