<?php

# Only allow simple alphanumeric titles to avoid security risks.
$title = $_GET['title']; 
if (!preg_match('/^[a-zA-Z0-9]+$/', $title)) { echo 'Bad page title'; exit(); }

# Where page data is located.
$pages_dir  = 'pages/';
$page_path = $pages_dir.$title;

# Find appropriate code for user's '?action='. Assume "view.php" if not found.
$actions_dir  = 'actions/';
$fallback = $actions_dir.'view.php';
$action = $_GET['action'];
$action_path = ($actions_dir.$action.'.php'); 
if (!is_file($action_path)) $action_path = $fallback;

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
require($action_path);
echo '
</body>
</html>';

# Database manipulation functions.

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
