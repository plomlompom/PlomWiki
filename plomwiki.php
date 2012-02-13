<?php
########################################################################
#                     I N I T I A L I Z A T I O N                      #
########################################################################

# Filesystem info / important paths.
$config_dir             = 'config/';
$markup_list_path       = $config_dir.'markups';
$pw_path                = $config_dir.'passwords';
$plugin_list_path       = $config_dir.'plugins';
$reg_dir                = $config_dir.'regs/';
$reg_config             = $reg_dir.'config';
$reg_design             = $reg_dir.'design';
$reg_phrases            = $reg_dir.'phrases';
$pages_dir              = 'pages/';
$diff_dir               = $pages_dir.'diffs/';
$del_dir                = $pages_dir.'deleted/';
$plugin_dir             = 'plugins/';
$setup_file             = 'setup.php';
$work_dir               = 'work/';
$todo_urgent            = $work_dir.'todo_urgent';
$work_temp_dir          = $work_dir.'temp/';
$work_failed_logins_dir = $work_dir.'failed_logins/';

# Use LF for newlines. Sanitize() filters CR, frees it for other uses.
$nl  = "\n";
$esc = "\r";

# Check for existence of setup file; execute it if found.
if (is_file($setup_file))
  require($setup_file);

# Read in registries: default values for configuration, HTML, phrases.
$l = ReadReg($reg_config);
$l = ReadReg($reg_design, $l);
$l = ReadReg($reg_phrases, $l);

# Snippets used for URL generation.
$root_rel   = 'plomwiki.php';
$title_root = $root_rel.'?title=';

# These help to know when to orderly end before being killed by server. 
$max_exec_time = ini_get('max_execution_time');
$now           = time();

# Get page title, build dependent variables. $legal_title defines rules
# for page titles: may consist of alphanum chars and hyphens. Violation
# is be punished later. A harmless fallback replaces empty page titles. 
$legal_title = '[a-zA-Z0-9-]+';
$title       = $_GET['title'];
if (!$title)
  $title     = 'Start';
if (!preg_match('/^'.$legal_title.'$/', $title))
  $title     = '';
$page_path = $pages_dir .$title;
$diff_path = $diff_dir  .$title;
$title_url = $title_root.$title;  
$l['page_title'] = $title;
$l['title_url']  = $title_url;

# Regex for legal key names. '*' = admin key. Plugins may add via '|'.
$legal_pw_key = '\*';

# Add/execute code via $l['code'] and plugin files named in plugin list.
eval($l['code']);
foreach (ReadAndTrimLines($plugin_list_path) as $line)
  require($line);

# Before executing user's action, do urgent work if todo_urgent found.
if (is_file($todo_urgent))
  WorkTodo($todo_urgent, TRUE);

# Fail if GetPageTitle() returned NULL due to failing $legal_title rule.
if (!$title)
  ErrorFail('IllegalPageTitle');

# Get user action, execute it; give plugins a chance to hook in before.
$action          = $_GET['action'];
$action = 'Action_'.$action;
if (!function_exists($action))           # If no appropriate function is
  $action = 'Action_page_view';          # found, use harmless fallback.
eval($hook_before_action);
$action();

########################################################################
#      P A G E - S P E C I F I C      U S E R      A C T I O N S       #
########################################################################

function Action_page_view() {
# Formatted / marked-up display of a wiki page.
  global $hook_Action_page_view, $l, $page_path, $title;
  $l['title'] = $title;
  
  # Get file text. If none, show page creation invitation. Else, markup.
  if (is_file($page_path))
    $l['content'] = Markup(file_get_contents($page_path));
  else
    $l['content'] = $l['PageDisplayNone'];

  # Before output of result, execute plugin hook.
  eval($hook_Action_page_view);
  OutputHTML(); }

function Action_page_edit() {
# Output edit form to a page source text. Send results to ?action=write.
  global $hook_Action_page_edit, $l, $page_path;
  $l['title'] = $l['ActionPageEditTitle'];

  # If no page file, start $text empty. Otherwise, escape evil chars.
  if (is_file($page_path)) 
    $l['text'] = EscapeHTML(file_get_contents($page_path));
  else
    $l['text'] = '';

  # HTML of edit form.
  $form = $l['ActionPageEditForm'];
  
  # Plugins may add stuff via $hook_action_page_edit and $add.
  eval($hook_Action_page_edit);
  $l['content'] = $form.$add;
  OutputHTML(); }

function Action_page_history() {
# Show version history of page (based on diff file), offer reverting.
  global $diff_path, $l, $nl;
  $l['title']   = $l['ActionPageHistoryTitle']; 

  # Read in diff list from path; if none found, output fallback message.
  $l['content'] = $l['PageNoHistory'];
  if (is_file($diff_path)) 
    $diff_list = DiffList($diff_path);

  # Add to output diff element by diff element, formatted.
  if ($diff_list) {
    $diffs = array();
    foreach ($diff_list as $id => $diff_data) {

      # Move diff data into temporary $l values, to be applied at each
      # cycle's end into $l['i_diff'] via ReplaceEscapedVariables().
      $l['i_id']   = $id;
      $l['i_time'] = date('Y-m-d H:i:s', (int) $diff_data['time']);
      $l['i_auth'] = EscapeHTML($diff_data['author']);
      $l['i_summ'] = EscapeHTML($diff_data['summary']);
      $l['i_text'] = '';
      foreach (explode($nl, $diff_data['text']) as $line_n => $line) {
        if     ($line[0] == '>') $theme = 'diff_ins';
        elseif ($line[0] == '<') $theme = 'diff_del';
        else                     $theme = 'diff_meta';
        if ($line[0] == '<' or $line[0] == '>') 
          $line = EscapeHTML(substr($line, 1));
        $l['line'] = $line;
        $l['i_text'] .= ReplaceEscapedVariables($l[$theme]); }
      $diffs[]     = ReplaceEscapedVariables($l['i_diff']); }

    $l['content'] = implode($nl, $diffs); }
  OutputHTML(); }

function Action_page_revert() {
# Prepare version reversion and ask user for confirmation.
  global $diff_path, $l, $page_path;
  
  # Try diff ID provided by user, determine its time. Fail if necessary.
  $id        = $_GET['id'];
  $diff_list = DiffList($diff_path);
  if (!$diff_list[$id]['time'])
    ErrorFail('InvalidRevertPoint');
  $l['time'] = date('Y-m-d H:i:s', (int) $diff_list[$id]['time']);

  # Reverse-patch $text back through $diff_list until $i hits $id.
  $text = file_get_contents($page_path);
  foreach ($diff_list as $i => $diff_data) {
    $reversed_diff = PlomDiffReverse($diff_data['text']); 
    $text          = PlomPatch($text, $reversed_diff);
    if ($id == $i) break; }
  $l['text'] = EscapeHTML($text);

  # Output.
  $l['title']  = $l['ActionPageRevertTitle'];
  $l['content']= $l['ActionPageRevertForm'];
  OutputHTML(); }
  
########################################################################
#                        D B      W R I T I N G                        #
########################################################################

############# Action_write() and internal DB manipulation ##############

function Action_write() {
# Trigger writing to DB. Expects password $_POST['pw'] and target type
# $_GET['t'], determining which PrepareWrite_() function shapes details.
  global $root_rel, $todo_urgent; 
  $pw   = $_POST['pw'];
  $auth = $_POST['auth'];
  $t    = $_GET['t'];

  # Password check, for every DB writing the user requests.
  if (!CheckPW($auth, $pw, $t))
    ErrorFail('AuthFail');

  # Choose (according to "t="), execute function to build writing tasks.
  $prep_func = 'PrepareWrite_'.$t;
  if (function_exists($prep_func))
    $todo_txt = $prep_func($redir);
  else 
    ErrorFail('InvalidTarget');

  # If $redir URL was not determined, define the most harmless one.
  if (empty($redir))
    $redir = $root_rel;

  # Atomic writing of new $todo_urgent file with $todo_txt task list.
  rename(NewTemp($todo_txt), $todo_urgent);

  # Reloads plomwiki.php to $redir, triggers WorkTodo() on todo_urgent.
  WorkScreenReload($redir); }

function WorkTodo($todo, $do_reload = FALSE) {
# Work through todo, execute code line-by-line, comment out what's done.
  global $max_exec_time, $nl, $now;

  if (is_file($todo)) {
    # Lock todo file while working on it.
    Lock($todo);
    $p_todo = fopen($todo, 'r+');

    # Work through todo file until stopped by EOF or time limit.
    $limit_dur    = $max_exec_time / 2;
    $limit_pos    = $now + $limit_dur;
    $stop_by_time = FALSE;
    while (!feof($p_todo)) {
      if (time() >= $limit_pos) {
        $stop_by_time = TRUE;
        break; }
    
      # Eval / work through lines not empty or commented out. Comment
      # out lines worked through, except for unfinished WorkTodo's.
      $pos  = ftell($p_todo);
      $line = fgets($p_todo);
      if ($line[0] !== '#' and $line[0] !== $nl) {
        fseek($p_todo, $pos);
        $NoUnfinishedTodo = TRUE;
        if (substr($line, 0, 9) == 'WorkTodo(')  # WorkTodo() calls MUST
          eval('$NoUnfinishedTodo = '.$line);    # start at line start.
        else
          eval($line);
        if ($NoUnfinishedTodo) {
          fwrite($p_todo, '#');
          fgets($p_todo); } } }

    # Delete file only if stopped by EOF. In any case, unlock it.
    fclose($p_todo);
    if (!$stop_by_time) 
      unlink($todo);
    UnLock($todo); }

  # If WorkTodo() is child process of itself, don't reload, return TRUE.
  else
    return TRUE;
  if ($do_reload)
    WorkScreenReload(); }

function Lock($path) {
# Check for/create lockfile for $path. Blocks for 2 * max_exec_time max.
  global $l, $max_exec_time;
  $l['lock_dur'] = 2 * $max_exec_time;
  $now           = time();
  $lock          = $path.'_lock';

  # Fail if $lock file exists *and* is too young. Else, write new $lock.
  if (is_file($lock)) {
    $time = file_get_contents($lock);
    if ($time + $l['lock_dur'] > $now)
      ErrorFail('Locked'); }
  file_put_contents($lock, $now); }

function UnLock($path) {
# Unlock $path.
  $lock = $path.'_lock';
  unlink($lock); }

function NewTemp($string = '') {
# Put $string into new temp file in $work_temp_dir, return its path.
  global $work_temp_dir;

  # Lock dir so its filename list won't change unexpectedly during this.
  Lock($work_temp_dir);
  $p_dir = opendir($work_temp_dir);

  # Collect numerical filenames of temp files in $tempfiles.
  $tempfiles = array(0);
  while (FALSE !== ($fn = readdir($p_dir))) 
    if (preg_match('/^[0-9]*$/', $fn))
      $tempfiles[] = $fn;

  # Build new highest-number $temp_path, write $string into it.
  $new_max_int = max($tempfiles) + 1;
  $temp_path   = $work_temp_dir.$new_max_int;
  file_put_contents($temp_path, $string);

  # As change to $work_temp_dir's filename list is finished, unlock dir.
  closedir($p_dir);
  UnLock($work_temp_dir);
  return $temp_path; }

############################## Passwords ###############################

function Action_set_pw_admin() {
# Display page / form for setting new admin password.
  global $l;
  $l['title']   = $l['ActionSetPwAdminTitle']; 
  $l['content'] = $l['ActionSetPwAdminForm'];
  OutputHTML(); }
  
function PrepareWrite_admin_sets_pw() {
# Return todo file text for adding adding/updating PW in password file.
  global $legal_pw_key, $nl, $pw_path;

  # Check password key and new password for validity.
  $new_pw   = $_POST['new_pw'];
  $new_auth = $_POST['new_auth'];
  if (!$new_pw)
    ErrorFail('EmptyPW');
  if (!$new_auth)
    ErrorFail('EmptyAuth');
  if (!preg_match('/^('.$legal_pw_key.')$/', $new_auth))
    ErrorFail('InvalidPWKey');

  # Splice new password hash into text of password file at $pw_path.
  $passwords            = ReadPasswordList($pw_path);
  $salt                 = $passwords['$salt'];
  $pw_file_text         = $salt.$nl;
  $passwords[$new_auth] = hash('sha512', $salt.$new_pw);
  unset($passwords['$salt']);
  foreach ($passwords as $key => $pw)
    $pw_file_text .= $key.':'.$pw.$nl;

  # Return todo file text.
  $tmp = NewTemp(substr($pw_file_text, 0, -1));
  return 'if (is_file("'.$tmp.'")) rename("'.$tmp.'","'.$pw_path.'");';}
  
function CheckPW($key, $pw_posted, $target) {
# Check for authorization of $key to write to $target with $pw_posted.
  global $permissions, $pw_path, $work_failed_logins_dir;
  $return = FALSE;
 
  # Recently failed IPs wait $delay seconds before login next chance.
  $ip_file = $work_failed_logins_dir.$_SERVER['REMOTE_ADDR'];
  $delay   = 10;
  if (is_file($ip_file)) {
    $birth = file_get_contents($ip_file);
    while ($birth + $delay > time())
      sleep(1); }
  file_put_contents($ip_file, time());

  # Fail if empty $key provided.
  if (!$key)
    return $return;

  # Fail if $key not authorized for $target. Admin is always authorized.
  if ($key != '*' and !in_array($key, $permissions[$target]))
      return $return;

  # Check PW to $key. If hash fits list, delete IP from failed logins.
  $passwords   = ReadPasswordList($pw_path);
  $salt        = $passwords['$salt'];
  $salted_hash = hash('sha512', $salt.$pw_posted);
  if (isset($passwords[$key]) and $salted_hash == $passwords[$key]) {
    $return = TRUE;
    unlink($ip_file); }

  return $return; }

function ReadPasswordList($path) {
# Read password list from $path into array.
  global $legal_pw_key, $nl;
  $content = file_get_contents($path);

  # Trigger error if password file is not found / empty.
  if (!$content)
    ErrorFail('NoPWfile');
  
  # Build $passwords list from file's $content.
  $passwords = array();
  $lines     = explode($nl, $content);
  $salt      = $lines[0];
  unset($lines[0]);
  foreach ($lines as $line) {

    # Only read in password keys allowed according to $legal_pw_key.
    preg_match('/^('.$legal_pw_key.'):(.+)$/', $line, $catch);
    if ($catch) {
      $range             = $catch[1];
      $pw                = $catch[2];
      $passwords[$range] = $pw; } }

  # Overwrite any key '$salt' smuggled in DESPITE $legal_pw_key.
  $passwords['$salt'] = $salt; 
  return $passwords; }

############################# Page writing #############################

function PrepareWrite_page(&$redir) {
# Prepare todo lists for page writing, via WritePage() and todo_bonus.
  global $l, $nl, $now, $page_path, $title, $todo_urgent, $work_dir;
  $redir   = $l['title_url'];
  $text    = Sanitize($_POST['text']);
  $summary = str_replace($nl, '', Sanitize($_POST['summary']));
  $author  = str_replace($nl, '', Sanitize($_POST['author'] ));
  if (!$author)
    $author  = '?';

  # Check for error conditions: $text empty /unchanged / too long/large.
  if (is_file($page_path))
    $old_text = file_get_contents($page_path);
  if (!$text)         
    ErrorFail('NoEmptyPage');
  if ($text == $old_text)  
    ErrorFail('NothingChanged');
  if (count(explode($nl, $text)) > $l['page_max_lines'])
    ErrorFail('MaxLinesText');
  if (strlen($text) > $l['page_max_length'])
    ErrorFail('MaxSizeText');

  # Temp files for WritePage(), some empty, some for dangerous strings.
  $t0 = NewTemp();
  $t1 = NewTemp();
  $t2 = NewTemp();
  $t3 = NewTemp($text);
  $t4 = NewTemp($author);
  $t5 = NewTemp($summary);

  # $todo_plugin is for tasks added in WritePage() by plugins via hook.
  $todo_plugin = $work_dir.'todo_bonus';

  # Return todo file text.
  return 'WritePage("'.$title.'","'.$todo_plugin.'","'.$t0.'","'.$t1.'"'
             .',"'.$t2.'","'.$t3.'","'.$t4.'","'.$t5.'",'.$now.');'.$nl.
         'WorkTodo("'.$todo_plugin.'");'; }

function WritePage($title, $todo_plugins, $tmp_diff, $tmp_PluginsTodo, 
                   $tmp_page, $path_src_text, $path_src_author,
                   $path_src_summary, $timestamp) {
# Do all the tasks connected to the updating of a wiki page.
  global $del_dir, $diff_dir, $esc, $hook_WritePage,
         $hook_WritePage_diff, $nl, $pages_dir;
  $page_path = $pages_dir.$title; 
  $diff_path = $diff_dir .$title;
  $text      = file_get_contents($path_src_text);
  $author    = file_get_contents($path_src_author);
  $summary   = file_get_contents($path_src_summary);

  # If 'delete', rename / timestamp page & diff, move both to $del_dir.
  if ($text == 'delete') {
    if (is_file($page_path)) {
      unlink($tmp_diff); # Clean up
      unlink($tmp_page); # unneeded temps.
      $path_diff_del = $del_dir.$title.',del-diff-'.$timestamp;
      $path_page_del = $del_dir.$title.',del-page-'.$timestamp;
      if (is_file($diff_path))
        rename($diff_path, $path_diff_del);
      if (is_file($page_path))
        rename($page_path, $path_page_del); } }
  else {

    # Get diff to earlier version, add to old diffs, safely overwrite.
    if (is_file($tmp_diff)) {

      # Collect $old_text for diff generation.
      $old_text = $esc;  # Code to PlomDiff(): $old_text has zero lines.
      if (is_file($page_path))
        $old_text = file_get_contents($page_path);
    
      # Determine $diff_old and $new_diff_id based on previous diffs.    
      $new_diff_id = 0;
      if (is_file($diff_path)) {
        $diff_old    = file_get_contents($diff_path);
        $new_diff_id = count(DiffList($diff_path)); }

      # Determine new diff's text/metadata, add to $diff_old: $diff_new.
      $diff_add = PlomDiff($old_text, $text);
      eval($hook_WritePage_diff);  # Plugins manipulate new diff's data.
      if (!$summary)
        $summary = '?';
      $diff_new = $new_diff_id.$nl.$timestamp.$nl.$author.$nl.
                  $summary.$nl.$diff_add.'%%'.$nl.$diff_old;

      # Safely overwrite diff file.
      file_put_contents($tmp_diff, $diff_new);
      rename($tmp_diff, $diff_path); }

    # Page text is written *after* diff: diff work needs old page text.
    if (is_file($tmp_page)) {
      file_put_contents($tmp_page, $text);
      rename($tmp_page, $page_path); } }

  # Hook into $txt_PluginTodo all plugin actions to follow page updates.
  eval($hook_WritePage);
  if (is_file($tmp_PluginsTodo)) {
    file_put_contents($tmp_PluginsTodo, $txt_PluginsTodo);
    rename($tmp_PluginsTodo, $todo_plugins); }

  # Clean up.
  unlink($path_src_author);
  unlink($path_src_summary);
  unlink($path_src_text); }

########################################################################
#          M I N O R      H E L P E R      F U N C T I O N S           #
########################################################################

################################ Input #################################

function ReadAndTrimLines($path) {
# Read file into list of all lines, sans comments and ending whitespace.
  global $nl;
  $lines = explode($nl, file_get_contents($path));
  $list = array(); 
  foreach ($lines as $line) {
    $hash_pos = strpos($line, '#');
    if ($hash_pos !== FALSE)
      $line = substr($line, 0, $hash_pos);
    $line = rtrim($line);
    if ($line)
      $list[] = $line; } 
  return $list; }

function Sanitize($text) {
# Remove $esc and magical_quotes horrors from $text.
  global $esc;
  if (get_magic_quotes_gpc())
    $text = stripslashes($text);
  return str_replace($esc, '', $text); }

function DiffList($diff_path) {
# Build, return page-specific diff list from file text at $diff_path.
  global $nl;
  $diff_list = array();

  # Read in file text with superfluous trailing "%%\n" deleted.
  $txt = substr(file_get_contents($diff_path),0,-3);

  if ($txt != '') {
  # Break $txt into separate $diff_txt's. Remove superfluous end $nl.
    $diffs = explode('%%'.$nl, $txt);
    foreach ($diffs as $diff_n => $diff_txt) {
      if (substr($diff_txt, -1) == $nl)
        $diff_txt = substr($diff_txt, 0, -1);

      # Harvest diff data/metadata from $diff_txt into $diff_list[$id].
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

function ReadReg($path, $reg = array()) {
# Read in registry file to, and return $reg. May overwrite its values. 
  global $esc, $l, $nl;

  # If empty, set $l variables necessary for a minimal ErrorFail().
  if (!$l['Error'])
    $l['Error'] = 'Error';
  if (!$l['NoR'])
    $l['NoR']   = 'Registry not found at path: '.$path;
  if (!$l['BadR'])
    $l['BadR']  = 'Registry at '.$path.' is bad.';
  if (!$l['design'])
    $l['design'] = '<!DOCTYPE html><title>'.$esc.'title'.$esc.'</title>'
                   .$nl.'<body><p>'.$esc.'content'.$esc.'</p></body>';

  # Read in file and escape character from 1st line. Fail if necessary.  
  if (!is_file($path))
    ErrorFail('NoR');
  $txt = file_get_contents($path);
  $pos = strpos($txt, $nl);
  if (!$pos)
    ErrorFail('BadR');
  $e   = substr($txt, 0, $pos);
  
  # Read in $r variables from remaining file. Set $esc as escape char. 
  $txt    = substr($txt, $pos + 1);
  $fields = explode($nl.$e.$nl, $txt);
  foreach ($fields as $field) {
    $pos = strpos($field, $e);
    $key = substr($field, 0, $pos);
    if ($key) {
      $value     = substr($field, $pos + strlen($e));
      $value     = str_replace($e, $esc, $value);
      $reg[$key] = $value; } }
  
  return $reg; }
  
################################ Output ################################

function Markup($text) {
# Apply to $text markup functions in order described by markups file.
  global $markup_list_path; 
  $lines = ReadAndTrimLines($markup_list_path);
  foreach ($lines as $line)
    $text = $line($text);
  return $text; }

function EscapeHTML($text) {
# Replace symbols used by HTML. Correct ugly htmlspecialchars() results.
  $text = htmlspecialchars($text, ENT_QUOTES);
  return str_replace('&#039;', '&apos;', $text); }

function WorkScreenReload($redir = '') {
# Just output HTML of a work message and instantly redirect to $redir.
  global $l;

  # $l["WorkScreenReload"] has a placeholder for $l['redir']; so set it.
  if (!empty($redir))
    $redir = $l['redir'].$redir;
  $l['redir'] = $redir;

  # Apply $l["WorkScreenReload"] as design for OutputHTML() and exit.
  $l['design'] = $l['WorkScreenReload'];
  OutputHTML();
  exit(); }

function ErrorFail($msg) {
# Fail and output error $msg. Exit no matter what.
  global $hook_ErrorFail, $l;
  eval($hook_ErrorFail);
  $l['title']   = $l['Error'];
  $l['content'] = $l[$msg];
  OutputHTML();
  exit(); }

function OutputHTML() {
# Generate final HTML output by filling $l['design'] with content.
  global $esc, $l;
  while (FALSE !== strpos($l['design'], $esc))
    $l['design'] = ReplaceEscapedVariables($l['design']);
  echo $l['design']; }

function ReplaceEscapedVariables($string) {
# Replace substrings of $string delimited by $esc with values from $l.
  global $esc, $l; 
  $vars = array();

  # Explode $string by $esc, collect $esc-surrounded strings in $vars.
  $strings = explode($esc, $string);
  $collect = FALSE;
  foreach ($strings as $n => $part)
    if ($collect) {
      $vars[] = $part;
      $collect = FALSE; }
    else 
      $collect = TRUE;

  # Replace variable names in $vars with $l variable contents.
  foreach ($vars as $n => $var)
    $vars[$n] = $l[$var];

  # Echo elements of $strings alternately as-is or as values from $vars.
  $string = '';
  $collect = FALSE;
  $i = 0;
  foreach ($strings as $n => $part)
    if ($collect) { 
      $string .= $vars[$i];
      $i++;
      $collect = FALSE; }
    else { 
      $string .= $part;
      $collect = TRUE; }

  return $string; }

########################################################################
#                               D I F F                                #
########################################################################

function PlomDiff($text_A, $text_B) {
# Output diff $text_A -> $text_B.
  global $esc, $nl;

  # Transform $text_{A,B} into arrays of lines, append empty line 0.
  $lines_A = explode($nl, $text_A);    $lines_B = explode($nl, $text_B); 
  array_unshift($lines_A, $esc);       array_unshift($lines_B, $esc);
  $lines_A[] = $esc;                   $lines_B[] = $esc;

  # Build and sort a list of consecutive un-changed text sections.
  PlomDiff_AddUnchangedSections($lines_A, $lines_B, $equals);
  foreach ($lines_A as $n => $dump)
    foreach ($equals as $arr)
      if ($n === $arr[0])
        $equals_sorted[] = $arr;

  # Build diff by inverting $equal.
  foreach ($equals_sorted as $n => $arr) {
    if ($n == count($equals_sorted) - 1)       # Last diff element would
      break;                                   # be garbage, ignore.
    $n_A = $arr[0]; $n_B = $arr[1]; $ln = $arr[2];
    $arr_next = $equals_sorted[$n + 1];
    $offset_A = $n_A + $ln;           $offset_B = $n_B + $ln;
    $n_A_next = $arr_next[0] - 1;     $n_B_next = $arr_next[1] - 1;
    $txt_A = $txt_B = '';
    if ($offset_A == $n_A_next + 1) {
      $char = 'a';
      $A = $offset_A - 1;
      list($B, $txt_A) = 
             PlomDiff_RangeLines($lines_B, $offset_B, $n_B_next, '>'); }
    elseif ($offset_B == $n_B_next + 1) {
      $char = 'd';
      $B = $offset_B - 1;
      list($A, $txt_B) = 
             PlomDiff_RangeLines($lines_A, $offset_A, $n_A_next, '<'); }
    else {
      $char = 'c'; 
      list($A, $txt_A) = 
             PlomDiff_RangeLines($lines_A, $offset_A, $n_A_next, '<');
      list($B, $txt_B) = 
             PlomDiff_RangeLines($lines_B, $offset_B, $n_B_next, '>'); }
    $diffs .= $A.$char.$B.$txt_A.$txt_B.$nl; }

  return $diffs; }

function PlomDiff_AddUnchangedSections($lines_A, $lines_B, &$equals) {
# Recursively add to $equals consecutive unchanged lines between A & B.
  $return = PlomDiff_AddUnchangedSection($lines_A, $lines_B, $equals);
  if (!empty($return)) {
    $before = $return[0]; $after = $return[1];
    if (!empty($before[0]))
      PlomDiff_AddUnchangedSections($before[0], $before[1], $equals);
    if (!empty($after[0] ))
     PlomDiff_AddUnchangedSections($after [0], $after [1], $equals); } }

function PlomDiff_AddUnchangedSection($lines_A, $lines_B, &$equals) {
# Add to $equal largest non-change between A and B, return before/after.
 
  # Find the largest section of unchanged lines between $lines_{A,B}.
  $ln_old = 0;
  foreach ($lines_A as $n_A => $line_A) {
    foreach ($lines_B as $n_B => $line_B) {
      if ($line_A === $line_B) {
        $ln = 1;
        for ($i = $n_A + 1; NULL !== $lines_A[$i]; $i++)
          if ($lines_A[$n_A + $ln] === $lines_B[$n_B + $ln]) $ln++;
          else                                               break;
        if ($ln > $ln_old) {
          $largest_equal = array($n_A, $n_B, $ln);
          $ln_old = $ln; } } } }
  if (empty($largest_equal))
    return;
  $equals[] = $largest_equal;

  # If success, return slices of lines before and after $largest_equal.
  foreach ($lines_A as $n_A => $dump) {
      if ($n_A == $largest_equal[0]) break; $a++; }
  foreach ($lines_B as $n_B => $dump) {
      if ($n_B == $largest_equal[1]) break; $b++; }
  $start_A  = key($lines_A); 
  $start_B  = key($lines_B);
  $bef[] = array_slice($lines_A, 0, $largest_equal[0] - $start_A, TRUE);
  $bef[] = array_slice($lines_B, 0, $largest_equal[1] - $start_B, TRUE);
  $aft[] = array_slice($lines_A, $a + $ln_old, NULL, TRUE);
  $aft[] = array_slice($lines_B, $b + $ln_old, NULL, TRUE);
  return array($bef, $aft); }

function PlomDiff_RangeLines($lines, $offset, $n_next, $prefix) {
# Output list of diff $range string and diff lines $txt.
  global $nl;
  if ($offset == $n_next) $range = $offset;
  else                    $range = $offset.','.$n_next;
  foreach ($lines as $n => $line)
    if ($offset <= $n and $n <= $n_next)
      $txt .= $nl.$prefix.$line;
  return array($range, $txt); }

function PlomDiffReverse($old_diff) {
# Reverse a diff.
  global $nl;
  $old_diff = explode($nl, $old_diff);
  $new_diff = '';
  foreach ($old_diff as $line_n => $line) {
    if     ($line[0] == '<') $line[0] = '>'; 
    elseif ($line[0] == '>') $line[0] = '<';
    else {
      foreach (array('c' => 'c', 'a' => 'd', 'd' => 'a') 
               as $char => $reverse) {
        if (strpos($line, $char)) {
          list($left, $right) = explode($char, $line); 
          $line = $right.$reverse.$left; break; } } }
    $new_diff .= $line.$nl; }
  $new_diff = substr($new_diff, 0, -1);
  return $new_diff; }

function PlomPatch($text_A, $diff) {
# Patch $text_A to $text_B via $diff.
  global $esc, $nl;

  # Explode $diff into $patch_tmp = array($action_tmp=>array($line,…),…)
  $patch_lines = explode($nl, $diff);
  $patch_tmp = array(); $action_tmp = '';
  foreach ($patch_lines as $line) {
    if ($line[0] != '<' and $line[0] != '>')
      $action_tmp = $line;
    else
      $patch_tmp[$action_tmp][] = $line; }

  # Collect add/del lines info (split 'c' into both) from $patch_tmp to
  # $patch = array($start.'a' => array($line, …), $start.'d' => $end, …)
  $patch = array();
  foreach ($patch_tmp as $action_tmp => $lines) {
    if     (strpos($action_tmp, 'd')) {
             list($left, $ignore)  = explode('d', $action_tmp);
             if (!strpos($left, ',')) 
               $left               = $left.','.$left;
             list($start, $end)    = explode(',', $left);
             $action = 'd'.$start;
             $patch[$action] = $end; }
    elseif (strpos($action_tmp, 'a')) {
             list($start, $ignore) = explode('a', $action_tmp);
             $action               = 'a'.$start;
             $patch[$action] = $lines; }
    elseif (strpos($action_tmp, 'c')) {
             list($left, $right)   = explode('c', $action_tmp);
             if (!strpos($left, ','))
               $left = $left.','.$left;
             list($start, $end)    = explode(',', $left);
             $action               = 'd'.$start;
             $patch[$action]       = $end;
             $action               = 'a'.$start; 
             foreach ($lines as $line) if ($line[0] == '>')
               $patch[$action][]   = $line; } }

  # Create $lines_{A,B} arrays where key equals line number.
  # Add temp 0-th line.
  $lines_A = array($nl);
  foreach (explode($nl, $text_A) as $key => $line)
    $lines_A[$key + 1] = $nl.$line;
  if     ($text_A == '')   $lines_A = array($nl);    # Special cases for
  elseif ($text_A == $esc) $lines_A = array($nl);     # empty or almost-
  elseif ($text_A == $nl)  $lines_A = array($nl, $nl); # empty texts. 
  $lines_B = $lines_A;
  
  # According to $patch, add or delete line lengths on $lines_B.
  foreach ($patch as $action => $value) {
    $char          = $action[0];
    $apply_at_line = substr($action, 1);
    if     ($char == 'a')
      foreach ($value as $line_diff)
        $lines_B[$apply_at_line] .= $nl.substr($line_diff, 1);
    elseif ($char == 'd') {
      $until_line = $value;
      for ($i = $apply_at_line; $i <= $until_line; $i++) {
        $original_line_end = strlen($lines_A[$i]);
        $lines_B[$i] = substr($lines_B[$i], $original_line_end); } } }

  # Truncate from lines in B preceding \n or, if not found, unset line.
  foreach ($lines_B as $n => $line)
  { if ($nl == $line[0]) $lines_B[$n] = substr($line, 1);
    else                 unset($lines_B[$n]); }

  # Implode $lines_B to $text_B with \n. Remove first char if not alone.
  $text_B = implode($nl, $lines_B);
  if (strlen($text_B) > 1)
    $text_B = substr($text_B, 1);

  return $text_B; }
