<?php

##################
# Initialization #
##################

# Filesystem information.
$config_dir = 'config/';            $markup_list_path = $config_dir.'markups';
$plugin_dir = 'plugins/';           $pw_path          = $config_dir.'password';
$pages_dir  = 'pages/';             $plugin_list_path = $config_dir.'plugins';
$diff_dir   = $pages_dir.'diffs/';     $work_dir      = 'work/';
$del_dir    = $pages_dir.'deleted/';   $work_temp_dir = $work_dir.'temp/';
$setup_file = 'setup.php';             $todo_urgent   = $work_dir.'todo_urgent';
$work_failed_logins_dir = $work_dir.'failed_logins/';

# Limit page lengths and line numbers. Keeps 'em manageable to PlomDiff() etc.
$page_max_lines = 6000; $page_max_length = 250000;

# Newline information. PlomWiki likes "\n", dislikes "\r".
$nl = "\n";                      $nl2 = $nl.$nl;                    $esc = "\r";

# Check for unfinished setup file, execute if found.
if (is_file($setup_file)) 
  require($setup_file);

# URL generation information.
$root_rel = 'plomwiki.php';                   $title_root = $root_rel.'?title=';

# Get $max_exec_time and $now to know until when orderly stopping is possible.
$max_exec_time = ini_get('max_execution_time');                   $now = time();

# Get page title. Build dependent variables.
$legal_title = '[a-zA-Z0-9-]+';
$title       = GetPageTitle();
{ $page_path = $pages_dir .$title;
  $diff_path = $diff_dir  .$title;
  $title_url = $title_root.$title; }

# Allowed password keys: '*', pagenames and any "_"-preceded [a-z_] chars.
$legal_pw_key = '\*|_[a-z_]+|'.$legal_title;

# Insert plugins' code.
foreach (ReadAndTrimLines($plugin_list_path) as $line) 
  require($line);

# Before executing user's action, do urgent work if urgent todo file is found.
if (is_file($todo_urgent))
  WorkTodo($todo_urgent, TRUE);

# Fail if $title provided was invalid.
if (!$title)
  ErrorFail($esc.'IllegalPageTitle'.$esc); 

# Get user action. If page-specific, set $theme_default's $l['header_page'].
$action = GetUserAction();
eval($hook_before_action);
$action();

############################################
#                                          #
#   P A G E - S P E C I F IC   S T U F F   #
#                                          #
############################################

#######################
# Common page actions #
#######################

function Action_page_view()
# Formatted display of a page.
{ global $esc, $hook_Action_page_view, $l, $page_path, $title, $title_url;
  
  # Get text from file. If none, show invitation to create one. Else, markup it.
  if (is_file($page_path)) 
  { $text = file_get_contents($page_path); 
    $text = Markup($text); }
  else
    $text = $esc.'PageDontExist'.$esc.' <a href="'.$title_url.
                   '&amp;action=page_edit">'.$esc.'PageCreate?'.$esc.'</a>';

  # Before leaving, execute plugin hook.
  eval($hook_Action_page_view);
  $l['title'] = $title; $l['content'] = $text;
  OutputHTML(); }

function Action_page_edit()
# Edit form on a page source text. Send results to ?action=write.
{ global $esc,$hook_Action_page_edit,$l,$nl,$page_path,$title,$title_url;

  # If no page file is found, start with an empty $text.
  if (is_file($page_path)) 
  { $text = file_get_contents($page_path); 
    $text = EscapeHTML($text); }
  else $text = '';

  # Final HTML of edit form.
  $form = '<form method="post" '.
                     'action="'.$title_url.'&amp;action=write&amp;t=page">'.$nl.
          '<textarea name="text" '.
                    'rows="'.$esc.'Action_page_edit_TextareaRows'.$esc.'">'.$nl.
          $text.'</textarea>'.$nl.
          $esc.'Author'.$esc.': <input name="author" type="text" />'.$nl.
          $esc.'Summary'.$esc.': <input name="summary" type="text" />'.$nl.
          'Admin '.$esc.'pw'.$esc.': '.'<input name="pw" type="password" />'.
                            '<input name="auth" type="hidden" value="*" />'.$nl.
          '<input type="submit" value="OK" />'.$nl.'</form>';
  
  eval($hook_Action_page_edit);
  $l['title'] = $esc.'Editing'.$esc.': "'.$title.'"';
  $l['content'] = $form.$content;
  OutputHTML(); }

function Action_page_history()
# Show version history of page (based on its diff file), offer reverting.
{ global $diff_path,$esc,$hook_Action_page_history,$l,$nl,$title,$title_url;
  $text = $esc.'PageNoHistory'.$esc;                            # Fallback text.

  # Try to build $diff_list from $diff_path. If successful, format into HTML.
  if (is_file($diff_path)) 
    $diff_list = DiffList($diff_path);
  if ($diff_list)
  { 
    # Transform key into datetime string and revert link.
    $diffs = array();
    foreach ($diff_list as $id => $diff_data)
    { $time_string = date('Y-m-d H:i:s', (int) $diff_data['time']);
      $author      = EscapeHTML($diff_data['author']);
      $summary     = EscapeHTML($diff_data['summary']);
      $desc        = $time_string.': '.$summary.' ('.$author.')';
      $diffs[] =  '<div id="'.$id.'" class="diff_desc">'.$desc.' (<a href="'.
                     $title_url.'&amp;action=page_revert&amp;id='.$id.'">'.$esc.
                     'revert'.$esc.'</a>):</div>'.$nl.'<div class="diff_text">';

      # Preformat remaining lines. Translate arrows into less ambiguous +/-.
      foreach (explode($nl, $diff_data['text']) as $line_n => $line)
      { if     ($line[0] == '>') $line = '+ '.substr($line, 1);
        elseif ($line[0] == '<') $line = '- '.substr($line, 1);
        $diffs[] = '<pre>'.EscapeHTML($line).'</pre>'; } 
      $diffs[] = '</div>'.$nl; }
    $text = implode($nl, $diffs); }

  # Before leaving, execute plugin hook.
  eval($hook_Action_page_history);
  $l['title'] = $esc.'History'.$esc.': "'.$title.'"'; $l['content'] = $text;
  OutputHTML(); }

function Action_page_revert()
# Prepare version reversion and ask user for confirmation.
{ global $diff_path, $esc, $hook_Action_page_revert, $l, $nl, $title,
         $title_url, $page_path;
  $id          = $_GET['id'];
  $diff_list   = DiffList($diff_path);
  
  # Try to find diff ID provided by user, determine its time. Fail if necessary.
  if (!$diff_list[$id]['time'])
    ErrorFail($esc.'InvalidRevertPoint'.$esc);
  $time_string = date('Y-m-d H:i:s', (int) $diff_list[$id]['time']);

  # Revert $text back through $diff_list until $i hits $id.
  $text = file_get_contents($page_path);
  foreach ($diff_list as $i => $diff_data)
  { $reversed_diff = PlomDiffReverse($diff_data['text']); 
    $text          = PlomPatch($text, $reversed_diff);
    if ($id == $i) break; }
  $text = EscapeHTML($text);

  # Ask for revert affirmation and password.
  $form = '<form '.$class.'method="post" '.
                     'action="'.$title_url.'&amp;action=write&amp;t=page">'.$nl.
          '<input type="hidden" name="text" value="'.$text.'">'.$nl.
          '<input type="hidden" name="summary" value="revert">'.$nl.
          'Admin '.$esc.'pw'.$esc.': <input name="pw" type="password" />'.
                            '<input name="auth" type="hidden" value="*" />'.$nl.
          '<input type="submit" value="OK" />'.$nl.'</form>';

  # Before leaving, execute plugin hook.
  eval($hook_Action_page_revert);
  $l['title'] = $esc.'RevertToBefore'.$esc.' '.$time_string.'?';
  $l['content']= $form;
  OutputHTML(); }
  
####################################
# Page text manipulation functions #
####################################

function Markup($text)
# Applying markup functions in the order described by markups file to $text.
{ global $markup_list_path; 
  $lines = ReadAndTrimLines($markup_list_path);
  foreach ($lines as $line)
    $text = $line($text);
  return $text; }

function Sanitize($text)
# Remove $esc from text and magical_quotes horrors.
{ global $esc;
  if (get_magic_quotes_gpc())
    $text = stripslashes($text);
  return str_replace($esc, '', $text); }

function EscapeHTML($text)
# Replace symbols used by HTML. Correct ugly htmlspecialchars() formatting.
{ return str_replace('&#039;', '&apos;', htmlspecialchars($text, ENT_QUOTES)); }

###########################
#                         #
#   D B   W R I T I N G   #
#                         #
###########################

###############################################
# Action_write() and internal DB manipulation #
###############################################

function Action_write()
# User writing to DB. Expects password $_POST['pw'] and target type $_GET['t'],
# which determines the function shaping the details (like what to write where).
{ global $esc, $nl, $nl2, $root_rel, $todo_urgent; 
  $pw = $_POST['pw']; $auth = $_POST['auth']; $t = $_GET['t'];

  # Target type chooses writing preparation function, gets variables from it.
  $task_write_list = array();
  $prep_func = 'PrepareWrite_'.$t;
  if (function_exists($prep_func))
    $todo_txt = $prep_func($redir);
  else 
    ErrorFail($esc.'InvalidTarget'.$esc);

  # Write temporay todo file, with a trailing newline as WorkTodo() expects.
  $todo_tmp = NewTemp($todo_txt.$nl);

  # Give a redir URL more harmless than a write action page if $redir is empty.
  if (empty($redir))
    $redir = $root_rel;

  # Password check.
  if (!CheckPW($auth, $pw, $t))
    ErrorFail($esc.'AuthFail'.$esc);

  # Atomic writing of new $todo_urgent file.
  rename($todo_tmp, $todo_urgent);

  # Final HTML.
  WorkScreenReload($redir); }

function WorkTodo($todo, $do_reload = FALSE)
# Work through todo file. Comment out finished lines. Delete file when finished.
{ global $max_exec_time, $now;

  if (is_file($todo))
  { # Lock todo file while working on it.
    Lock($todo);
    $p_todo = fopen($todo, 'r+');

    # Work through todo file until stopped by EOF or time limit.
    $limit_dur = $max_exec_time / 2;
    $limit_pos = $now + $limit_dur;
    $stop_by_time = FALSE;
    while (!feof($p_todo))
    { if (time() >= $limit_pos)
      { $stop_by_time = TRUE;
        break; }
    
      # Eval lines not commented out. Comment out lines worked through, except
      # for unfinished WorkTodo's.
      $pos  = ftell($p_todo);
      $line = fgets($p_todo);
      if ($line[0] !== '#')
      { fseek($p_todo, $pos);
        $call = substr($line, 0, -1);
        eval($call);
        $WorkTodo_finished = TRUE;
        if (substr($call, 0, 9) == 'WorkTodo(')
          eval('$WorkTodo_finished = '.$call);
        if ($WorkTodo_finished)
        { fwrite($p_todo, '#');
          fgets($p_todo); } } }

    # Delete file if stopped by EOF. In any case, unlock it.
    fclose($p_todo);
    if (!$stop_by_time) 
      unlink($todo);
    UnLock($todo); }

  # No todo file or $do_reload? WorkTodo() may be a child process of itself.
  else
    return TRUE;
  if ($do_reload)
    WorkScreenReload(); }

function Lock($path)
# Check for and create lockfile for $path. Locks block $lock_dur seconds max.
{ global $esc, $max_exec_time;
  $lock_dur = 2 * $max_exec_time;
  $now      = time();
  $lock     = $path.'_lock';

  # Fail if $lock file exists already and is too young. Else, write new $lock.
  if (is_file($lock))
  { $time = file_get_contents($lock);
    if ($time + $lock_dur > $now)
      ErrorFail($esc.'Locked'.$esc.$lock_dur); }
  file_put_contents($lock, $now); }

function UnLock($path)
# Unlock $path.
{ $lock = $path.'_lock';
  unlink($lock); }

function NewTemp($string = '')
# Put $string into new temp file in $work_temp_dir, return its path.
{ global $work_temp_dir;

  # Lock dir so its filename list won't change via something else during this.
  Lock($work_temp_dir);
  $p_dir = opendir($work_temp_dir);

  # Collect numerical filenames of temp files in $tempfiles.
  $tempfiles = array(0);
  while (FALSE !== ($fn = readdir($p_dir))) 
    if (preg_match('/^[0-9]*$/', $fn))
      $tempfiles[] = $fn;

  # Build new highest-number $temp_path, write $string into it.
  $new_max_int = max($tempfiles) + 1;
  $temp_path = $work_temp_dir.$new_max_int;
  file_put_contents($temp_path, $string);

  # As our change to $work_temp_dir's filename list is finished, unlock dir.
  closedir($p_dir);
  UnLock($work_temp_dir);
  return $temp_path; }

#############
# Passwords #
#############

function Action_set_pw_admin()
# Display page for setting new admin password.
{ global $esc, $l, $nl, $nl2, $title_url;
  $form = '<form '.$class.'method="post" '.
            'action="'.$title_url.'&amp;action=write&amp;t=admin_sets_pw">'.$nl.
          $esc.'NewPWfor'.$esc.' '.$esc.'admin'.$esc.':<br />'.$nl.
          '<input type="hidden" name="new_auth" value="*">'.$nl.
          '<input type="password" name="new_pw" /><br />'.$nl.
          '<input type="hidden" name="auth" value="*">'.$nl.
          $esc.'OldAdmin'.$esc.' '.$esc.'pw'.$esc.':<br />'.$nl.
          '<input type="password" name="pw">'.$nl.
         '<input type="submit" value="OK" />'.$nl.'</form>';
  $l['title'] = $esc.'ChangePWfor'.$esc.' '.$esc.'admin'.$esc; 
  $l['content'] = $form;
  OutputHTML(); }
  
function PrepareWrite_admin_sets_pw(&$redir)
# Deliver to Action_write() all information needed for pw writing process.
{ global $esc, $legal_pw_key, $nl, $pw_path, $todo_urgent;

  # Check password key and new password for validity.
  $new_pw   = $_POST['new_pw'];
  $new_auth = $_POST['new_auth'];
  if (!$new_pw)
    ErrorFail($esc.'EmptyPW'.$esc);
  if (!$new_auth)
    ErrorFail($esc.'EmptyAuth'.$esc);
  if (!preg_match('/^('.$legal_pw_key.')$/', $new_auth))
    ErrorFail($esc.'InvalidPWKey'.$esc);

  # Splice new password hash into text of password file at $pw_path.
  $passwords            = ReadPasswordList($pw_path);
  $salt                 = $passwords['$salt'];
  $pw_file_text         = $salt.$nl;
  $passwords[$new_auth] = hash('sha512', $salt.$new_pw);
  unset($passwords['$salt']);
  foreach ($passwords as $key => $pw)
    $pw_file_text .= $key.':'.$pw.$nl;

  # Return todo file text.
  $tmp = NewTemp($pw_file_text);
  return 'if (is_file(\''.$tmp.'\')) rename(\''.$tmp.'\', \''.$pw_path.'\');'; }
  
function CheckPW($key, $pw_posted, $target)
# Check if hash of $pw_posted fits $key password hash in internal password list.
{ global $permissions, $pw_path, $work_failed_logins_dir;
  $return = FALSE;
 
  # Let IPs that recently failed a login wait $delay seconds before next chance.
  $ip_file = $work_failed_logins_dir.$_SERVER['REMOTE_ADDR'];
  $delay   = 10;
  if (is_file($ip_file))
  { $birth = file_get_contents($ip_file);
    while ($birth + $delay > time())
      sleep(1); }
  file_put_contents($ip_file, time());

  # Fail if empty $key provided.
  if (!$key)
    return $return;

  # Fail if $key is not authorized for target. Assume admin always authorized.
  if ($key != '*' and !in_array($key, $permissions[$target]))
      return $return;

  # Try positive authentication. If successful, delete IP form failed logins.
  $passwords   = ReadPasswordList($pw_path);
  $salt        = $passwords['$salt'];
  $salted_hash = hash('sha512', $salt.$pw_posted);
  if (isset($passwords[$key])
      and $salted_hash == $passwords[$key])
  { $return = TRUE;
    unlink($ip_file); }

  return $return; }

function ReadPasswordList($path)
# Read password list from $path into array.
{ global $esc, $legal_pw_key, $nl;
  $content = substr(file_get_contents($path), 0, -1);

  # Trigger error if password file is not found / empty.
  if (!$content)
    ErrorFail($esc.'NoPWfile'.$esc);
  
  # Build $passwords list from file's $content.
  $passwords = array();
  $lines = explode($nl, $content);
  $salt = $lines[0];
  unset($lines[0]);
  foreach ($lines as $line)
  { 
    # Only read in allowed password keys according to $legal_pw_key.
    preg_match('/^('.$legal_pw_key.'):(.+)$/', $line, $catch);
    if ($catch)
    { $range = $catch[1];
      $pw    = $catch[2];
      $passwords[$range] = $pw; } }

  # Can't hurt to overwrite any '$salt' key smuggled in DESPITE $legal_pw_key.
  $passwords['$salt'] = $salt; 

  return $passwords; }

################
# Page writing #
################

function PrepareWrite_page(&$redir)
# Deliver to Action_write() all information needed for page writing process.
{ global $esc, $page_max_lines, $page_max_length, $nl, $page_path, $title,
         $title_url, $todo_urgent, $work_dir;
  $redir   = $title_url;
  $text    = Sanitize($_POST['text']);
  $summary = str_replace($nl, '', Sanitize($_POST['summary']));
  $author  = str_replace($nl, '', Sanitize($_POST['author'] ));
  if (!$author) $author  = '?';

  # Check for error conditions: $text empty, unchanged or too long/large.
  if (is_file($page_path))
    $old_text = file_get_contents($page_path);
  if (!$text)         
    ErrorFail($esc.'NoEmptyPage'.$esc);
  if ($text == $old_text)  
    ErrorFail($esc.'NothingChanged'.$esc);
  if (count(explode($nl, $text)) > $page_max_lines)
    ErrorFail($esc.'MaxLinesText'.$esc.$page_max_lines);
  if (strlen($text) > $page_max_length)
    ErrorFail($esc.'MaxSizeText'.$esc.$page_max_length);

  # Reserve empty temporary files for WritePage(), and temp files with strings.
  $t0 = NewTemp(); $t1 = NewTemp(); $t2 = NewTemp();
  $t3 = NewTemp($text); $t4 = NewTemp($author); $t5 = NewTemp($summary);

  # $todo_plugin is for tasks added in WritePage() by plugins via hook.
  $todo_plugin = $work_dir.'todo_bonus';

  # Return todo file text.
  return 'WritePage(\''.$title.'\',\''.$todo_plugin.'\',\''.$t0.'\',\''.$t1.
                     '\',\''.$t2.'\',\''.$t3.'\',\''.$t4.'\',\''.$t5.'\');'.$nl.
         'WorkTodo(\''.$todo_plugin.'\');'; }

function WritePage($title, $todo_plugins, $path_tmp_diff, $path_tmp_PluginsTodo, 
                   $path_tmp_page, $path_src_text, $path_src_author,
                   $path_src_summary)
# Write text found at $path_src_text to page $title. Safely trigger file writing
# actions only according to (non-)existence of appropriate $path_tmp_[…] files.
# Use texts found at $path_src_author & $path_src_summary as diff descriptions. 
# $todo_plugins catches actions added via plugin hook $hook_WritePage to its
# $txt_PluginTodo; WorkTodo() on $todo_plugin needs to be called externally.
{ global $del_dir, $diff_dir, $esc, $hook_WritePage, $hook_WritePage_diff, $nl,
         $pages_dir;
  $page_path       = $pages_dir.$title; 
  $diff_path       = $diff_dir .$title;
  $text            = file_get_contents($path_src_text);
  $author          = file_get_contents($path_src_author);
  $summary         = file_get_contents($path_src_summary);
  $timestamp       = time();
  $txt_PluginsTodo = '';

  # If 'delete', rename and timestamp page and its diff, move both to $del_dir.
  if ($text == 'delete')
  { if (is_file($page_path))
    { unlink($path_tmp_diff); unlink($path_tmp_page); # Clean up unneeded temps.
      $path_diff_del = $del_dir.$title.',del-diff-'.$timestamp;
      $path_page_del = $del_dir.$title.',del-page-'.$timestamp;
      if (is_file($diff_path)) rename($diff_path, $path_diff_del);
      if (is_file($page_path)) rename($page_path, $path_page_del); } }
    
  else
  { # Collect $old_text for diff generation. Abort if identical to $text.
    $old_text = $esc;  # Code to PlomDiff() of $old_text having no lines at all.
    if (is_file($page_path))
      $old_text = file_get_contents($page_path);
      
    # This step should probably be deleted. But the plugins RecentChanges.php
    # and AutoLink.php may need to be changed then, too.
    if ($old_text == $text) return;
    
    # Diff to previous version, add to diff file.
    $new_diff_id = 0;
    $diff_add    = PlomDiff($old_text, $text);
    if (is_file($diff_path))
    { $diff_old    = file_get_contents($diff_path);
      $diff_list   = DiffList($diff_path); 
      $old_diff_id = 0;
      foreach ($diff_list as $id => $diff_data)
        if ($id > $old_diff_id)
          $old_diff_id = $id;
      $new_diff_id = $old_diff_id + 1; }
    else
      $diff_old = '';
    eval($hook_WritePage_diff);         # The best place to construct a summary
    if (!$summary) $summary = '?';      # from the diff, if so inclined.
    $diff_new = $new_diff_id.$nl.$timestamp.$nl.$author.$nl.$summary.$nl.
                $diff_add.'%%'.$nl.$diff_old;

    # Safe overwriting of page & diff file. Tmp files' absences indicate: done.
    if (is_file($path_tmp_diff))
    { file_put_contents($path_tmp_diff, $diff_new);
      rename($path_tmp_diff, $diff_path); }
    if (is_file($path_tmp_page))
    { file_put_contents($path_tmp_page, $text);
      rename($path_tmp_page, $page_path); } }

  # Add $txt_PluginTodo to $todo_plugin for plugin actions added via hook.
  eval($hook_WritePage);
  if (is_file($path_tmp_PluginsTodo))
  { file_put_contents($path_tmp_PluginsTodo, $txt_PluginsTodo);
    rename($path_tmp_PluginsTodo, $todo_plugins); }

  # Clean up.
  unlink($path_src_author); unlink($path_src_summary); unlink($path_src_text); }

###################################################
#                                                 #
#   M I N O R   H E L P E R   F U N C T I O N S   #
#                                                 #
###################################################

#########
# Input #
#########

function ReadAndTrimLines($path)
# Read file $path into a list of all lines sans comments and ending whitespaces.
{ global $nl;

  $lines = explode($nl, file_get_contents($path));
  $list = array(); 
  foreach ($lines as $line)
  { $hash_pos = strpos($line, '#');
    if ($hash_pos !== FALSE)
      $line = substr($line, 0, $hash_pos);
    $line = rtrim($line);
    if ($line)
      $list[] = $line; } 
  return $list; }

function GetPageTitle($fallback = 'Start')
# Only allow alphanumeric titles plus -. If title is empty, assume $fallback.
{ global $legal_title;
  $title = $_GET['title']; 
  if (!$title) $title = $fallback;
  if (!preg_match('/^'.$legal_title.'$/', $title)) return;
  return $title; }

function GetUserAction($fallback = 'Action_page_view')
# Find appropriate code for user's '?action='. Assume $fallback if not found.
{ $action          = $_GET['action'];
  $action_function = 'Action_'.$action;
  if (!function_exists($action_function)) 
    $action_function = $fallback;
  return $action_function; }

##########
# Output #
##########

function ErrorFail($msg)
# Fail and output error $msg.
{ global $esc, $hook_ErrorFail, $l, $nl;
  eval($hook_ErrorFail);
  $text = $msg;
  $l['title'] = $esc.'Error'.$esc; $l['content'] = $text;
  OutputHTML(); 
  exit(); }

function OutputHTML()
# Generate final HTML output by applying parameters on global variable $style.
{ global $esc, $design;
  while (FALSE !== strpos($design, $esc))
    $design = ReplaceEscapedVariables($design);
  echo $design; }

function ReplaceEscapedVariables($string)
{ global $esc, $l; 

  # Explode $string by $esc, collect strings surrounded by it as variable names.
  $strings = explode($esc, $string); $collect = FALSE; $vars = array();
  foreach ($strings as $n => $part)
    if ($collect) 
    { $vars[] = $part;
      $collect = FALSE; }
    else 
      $collect = TRUE;

  # Replace variable names in $vars with $l variable contents.
  foreach ($vars as $n => $var)
    $vars[$n] = $l[$var];

  # Echo elements of $string alternately as-is or as variables from $vars.
  $collect = FALSE; $i = 0; $string = '';
  foreach ($strings as $n => $part)
    if ($collect) 
    { $string .= $vars[$i];
      $i++;
      $collect = FALSE; }
    else 
    { $string .= $part;
      $collect = TRUE; }

  return $string; }

function WorkScreenReload($redir = '')
# Just output the HTML of a work message and instantly redirect to $redirect.
{ global $nl;
  if (!empty($redir))
    $redir = '; URL='.$redir;
  echo '<title>Working</title>'.$nl.
       '<meta http-equiv="refresh" content="0'.$redir.'" />'.$nl.
       '<p>Working.</p>'; 
  exit(); }

#########
# Other #
#########

function DiffList($diff_path)
# Build, return page-specific diff list from file text at $diff_path.
{ global $nl;
  $diff_list = array();

  # Read in file text with superfluous trailing "%%\n" deleted.
  $file_txt = substr(file_get_contents($diff_path),0,-3);

  if ($file_txt != '')
  # Break $file_txt into separate $diff_txt's. Remove superfluous trailing $nl.
  { $diffs = explode('%%'.$nl, $file_txt);
    foreach ($diffs as $diff_n => $diff_txt)
    { if (substr($diff_txt, -1) == $nl)
        $diff_txt = substr($diff_txt, 0, -1);

      # Harvest diff data / metadata from $diff_txt into $diff_list[$id].
      $diff_lines = explode($nl, $diff_txt);
      $diff_txt_new = array();
      foreach ($diff_lines as $line_n => $line) 
        if     ($line_n == 0) $id                        = $line;
        elseif ($line_n == 1) $diff_list[$id]['time']    = $line;
        elseif ($line_n == 2) $diff_list[$id]['author']  = $line;
        elseif ($line_n == 3) $diff_list[$id]['summary'] = $line;
        else                  $diff_txt_new[] = $line;
      $diff_list[$id]['text'] = implode($nl, $diff_txt_new); } }

  return $diff_list; }

###############
#             #
#   D I F F   #
#             #
###############

function PlomDiff($text_A, $text_B)
# Output diff $text_A -> $text_B.
{ global $esc, $nl;

  # Transform $text_{A,B} into arrays of lines with empty line 0 appended.
  $lines_A = explode($nl, $text_A);     $lines_B = explode($nl, $text_B); 
  array_unshift($lines_A, $esc);        array_unshift($lines_B, $esc);
  $lines_A[] = $esc;                    $lines_B[] = $esc;

  # Build and sort a list of consecutive un-changed text sections.
  PlomDiff_AddUnchangedSections($lines_A, $lines_B, $equals);
  foreach ($lines_A as $n => $dump)
    foreach ($equals as $arr)
      if ($n === $arr[0])
        $equals_sorted[] = $arr;

  # Build diff by inverting $equal.
  foreach ($equals_sorted as $n => $arr)
  { if ($n == count($equals_sorted) - 1)               # Last diff element would
      break;                                           # be garbage, ignore.
    $n_A = $arr[0]; $n_B = $arr[1]; $ln = $arr[2];
    $arr_next = $equals_sorted[$n + 1];
    $offset_A = $n_A + $ln;           $offset_B = $n_B + $ln;
    $n_A_next = $arr_next[0] - 1;     $n_B_next = $arr_next[1] - 1;
    $txt_A = $txt_B = '';
    if ($offset_A == $n_A_next + 1)
    { $char = 'a';
      $A = $offset_A - 1;
      list($B, $txt_A) = PlomDiff_RangeLines($lines_B,$offset_B,$n_B_next,'>');}
    elseif ($offset_B == $n_B_next + 1)
    { $char = 'd';
      $B = $offset_B - 1;
      list($A, $txt_B) = PlomDiff_RangeLines($lines_A,$offset_A,$n_A_next,'<');}
    else
    { $char = 'c'; 
      list($A, $txt_A) = PlomDiff_RangeLines($lines_A,$offset_A,$n_A_next,'<');
      list($B, $txt_B) = PlomDiff_RangeLines($lines_B,$offset_B,$n_B_next,'>');}
    $diffs .= $A.$char.$B.$txt_A.$txt_B.$nl; }

  return $diffs; }

function PlomDiff_AddUnchangedSections($lines_A, $lines_B, &$equals)
# Recursively add to $equals consecutive unchanged lines between $lines_{A,B}.
{ $return = PlomDiff_AddUnchangedSection($lines_A, $lines_B, $equals);
  if (!empty($return))
  { $before = $return[0]; $after = $return[1];
    if (!empty($before[0]))
      PlomDiff_AddUnchangedSections($before[0], $before[1], $equals);
    if (!empty($after[0] ))
     PlomDiff_AddUnchangedSections($after [0], $after [1], $equals); } }

function PlomDiff_AddUnchangedSection($lines_A, $lines_B, &$equals)
# Add to $equal largest non-change between $lines_{A,B}, return before/after.
{ 
  # Try to find the largest section of unchanged lines between $lines_{A,B}.
  $ln_old = 0;
  foreach ($lines_A as $n_A => $line_A)
  { foreach ($lines_B as $n_B => $line_B)
    { if ($line_A === $line_B)
      { $ln = 1;
        for ($i = $n_A + 1; NULL !== $lines_A[$i]; $i++)
          if ($lines_A[$n_A + $ln] === $lines_B[$n_B + $ln]) $ln++;
          else                                               break;
        if ($ln > $ln_old)
        { $largest_equal = array($n_A, $n_B, $ln);
          $ln_old = $ln; } } } }
  if (empty($largest_equal))
    return;
  $equals[] = $largest_equal;

  # If successful, return slices of lines before and after $largest_equal.
  foreach ($lines_A as $n_A => $dump)
    { if ($n_A == $largest_equal[0]) break; $a++; }
  foreach ($lines_B as $n_B => $dump)
    { if ($n_B == $largest_equal[1]) break; $b++; }
  $start_A  = key($lines_A); 
  $start_B  = key($lines_B);
  $before[] = array_slice($lines_A, 0, $largest_equal[0] - $start_A, TRUE);
  $before[] = array_slice($lines_B, 0, $largest_equal[1] - $start_B, TRUE);
  $after[]  = array_slice($lines_A, $a + $ln_old, NULL, TRUE);
  $after[]  = array_slice($lines_B, $b + $ln_old, NULL, TRUE);
  return array($before, $after); }

function PlomDiff_RangeLines($lines, $offset, $n_next, $prefix)
# Output list of diff $range string and diff lines $txt.
{ global $nl;
  if ($offset == $n_next) $range = $offset;
  else                    $range = $offset.','.$n_next;
  foreach ($lines as $n => $line)
    if ($offset <= $n and $n <= $n_next)
      $txt .= $nl.$prefix.$line;
  return array($range, $txt); }

function PlomDiffReverse($old_diff)
# Reverse a diff.
{ global $nl;

  $old_diff = explode($nl, $old_diff);
  $new_diff = '';
  foreach ($old_diff as $line_n => $line)
  { if     ($line[0] == '<') $line[0] = '>'; 
    elseif ($line[0] == '>') $line[0] = '<';
    else 
    { foreach (array('c' => 'c', 'a' => 'd', 'd' => 'a') as $char => $reverse) 
      { if (strpos($line, $char))
        { list($left, $right) = explode($char, $line); 
          $line = $right.$reverse.$left; break; } } }
    $new_diff .= $line.$nl; }
  $new_diff = substr($new_diff, 0, -1);

  return $new_diff; }

function PlomPatch($text_A, $diff)
# Patch $text_A to $text_B via $diff.
{ global $esc, $nl;

  # Explode $diff into $patch_tmp = array($action_tmp => array($line, ...), ...)
  $patch_lines = explode($nl, $diff);
  $patch_tmp = array(); $action_tmp = '';
  foreach ($patch_lines as $line)
  { if ($line[0] != '<' and $line[0] != '>') $action_tmp = $line;
    else                                     $patch_tmp[$action_tmp][] = $line;}

  # Collect add/delete lines info (split 'c' into both) from $patch_tmp into
  # $patch = array($start.'a' => array($line, ...), $start.'d' => $end, ...)
  $patch = array();
  foreach ($patch_tmp as $action_tmp => $lines)
  { if     (strpos($action_tmp, 'd'))
           { list($left, $ignore)  = explode('d', $action_tmp);
             if (!strpos($left, ',')) 
               $left               = $left.','.$left;
             list($start, $end)    = explode(',', $left);
             $action = 'd'.$start; $patch[$action] = $end; }
    elseif (strpos($action_tmp, 'a'))
           { list($start, $ignore) = explode('a', $action_tmp);
             $action               = 'a'.$start; $patch[$action] = $lines; }
    elseif (strpos($action_tmp, 'c'))
           { list($left, $right)   = explode('c', $action_tmp);
             if (!strpos($left, ','))
               $left = $left.','.$left;
             list($start, $end)    = explode(',', $left);
             $action               = 'd'.$start;
             $patch[$action]       = $end;
             $action               = 'a'.$start; 
             foreach ($lines as $line) if ($line[0] == '>')
               $patch[$action][]   = $line; } }

  # Create $lines_{A,B} arrays where key equals line number. Add temp 0-th line.
  $lines_A = array($nl);
  foreach (explode($nl, $text_A) as $key => $line)
    $lines_A[$key + 1] = $nl.$line;
  if     ($text_A == '')   $lines_A = array($nl);        # Special cases for
  elseif ($text_A == $esc) $lines_A = array($nl);        # empty or almost-empty
  elseif ($text_A == $nl)  $lines_A = array($nl, $nl);   # texts. 
  $lines_B = $lines_A;
  
  # According to $patch, add or delete line lengths on $lines_B.
  foreach ($patch as $action => $value)
  { $char          = $action[0];
    $apply_at_line = substr($action, 1);
    if     ($char == 'a')
      foreach ($value as $line_diff)
        $lines_B[$apply_at_line] .= $nl.substr($line_diff, 1);
    elseif ($char == 'd')
    { $until_line = $value;
      for ($i = $apply_at_line; $i <= $until_line; $i++) 
      { $original_line_end = strlen($lines_A[$i]);
        $lines_B[$i]       = substr($lines_B[$i], $original_line_end); } } }

  # Truncate from each line in B preceding "\n" or, if not found, unset line.
  foreach ($lines_B as $n => $line)
  { if ($nl == $line[0]) $lines_B[$n] = substr($line, 1);
    else                 unset($lines_B[$n]); }

  # Implode $lines_B to $text_B with $nl. Remove first char if it's not alone.
  $text_B = implode($nl, $lines_B);
  if (strlen($text_B) > 1)
    $text_B = substr($text_B, 1);

  return $text_B; }
