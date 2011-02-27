<?php
$actions_meta[] = array('GlobalRegexReplace', '?action=ReplaceAll');

function ReplaceAll_RegexError($errno, $errstr)
# Return PHP preg_replace() error message to explain regex mistake.
{ ErrorFail('No valid regex given for $pattern.', 
            'PHP mumbles: <em>'.$errstr.'</em>'); }

function Action_ReplaceAll()
# Administration menu for markup translation.
{ global $nl, $root_rel;

  $input = '<p>Perform magical PHP code "$text = preg_replace($pattern, '.
                                    '$replace, $text);" for all pages.</p>'.$nl.
           '<h3>What to replace by what</h3>'.$nl.
           '<p>$pattern: <input name="pattern" type="text" size="30" '.
           'placeholder="/Some reg(ular)? .ex(pression)?[?!]*/i" \><br />'.$nl
          .'$replace: <input name="replace" type="text" size="30" placeholder='.
                                       '"What to replace it with" \><br />'.$nl.
           '(Any "\r" in $pattern and $replace and any "e" modifier in $pattern'
                                                  .' will be removed.)</p>'.$nl.
           '<h3>Accidental/planned page deletion</h3>'.$nl.
           '<p><input type="radio" name="del_rule_0" value="0" />Delete pages '.
                                       'that become empty as a result.<br>'.$nl.
           '<input type="radio" name="del_rule_0" value="1" checked />Fill said'
          .' pages with this text: <input type="text" name="del_rule_0_alt" '.
                 'size="30" value="Emptied by GlobalRegexReplace" /><p />'.$nl.
           '<p><input type="radio" name="del_rule_1" value="0" />Delete pages '.
                       'whose text is reduced to "delete" as a result.<br>'.$nl.
           '<input type="radio" name="del_rule_1" value="1" checked />Fill said'
          .' pages with this text: <input type="text" name="del_rule_1_alt" '.
                 'size="30" value="Emptied by GlobalRegexReplace" /><p />'.$nl.
           '<h3>Affirm</h3>';

  $form  = BuildPostForm($root_rel.'?action=write&amp;t=ReplaceAll', $input);
  Output_HTML('Global regular expression replacement', $form); }

function PrepareWrite_ReplaceAll()
# Return to Action_write() tasks for a whole new todo list of replacements.
{ global $pages_dir, $todo_urgent, $work_dir;
  $todo_replace_all = $work_dir.'todo_replace_all';
  $pattern          = Sanitize($_POST['pattern']);
  $replace          = Sanitize($_POST['replace']);
  $del_rule_0       = Sanitize($_POST['del_rule_0']);
  $del_rule_0_alt   = Sanitize($_POST['del_rule_0_alt']);
  $del_rule_1       = Sanitize($_POST['del_rule_1']);
  $del_rule_1_alt   = Sanitize($_POST['del_rule_1_alt']);
  $tmp_path_del_0   = NewTemp($del_rule_0_alt);
  $tmp_path_del_1   = NewTemp($del_rule_1_alt);
  $tmp_path_replace = NewTemp($replace);
  $titles           = GetAllLegalTitlesInDir($pages_dir);
  $timestamp        = time();

  # Validate and un-mine $pattern (remove e modifier) before writing it.
  $pattern = preg_replace('/e(?=[eimsxADSUXJu]*$)/', '', $pattern);
  set_error_handler('ReplaceAll_RegexError');
  preg_match($pattern, 'test');
  restore_error_handler();
  $tmp_path_pattern = NewTemp($pattern);

  # Write tasks.
  foreach ($titles as $title)
    $x['tasks'][$todo_urgent][] = array('ReplaceAll_OnPage', 
                                        array($todo_replace_all, $title,
                                              $timestamp, $tmp_path_pattern,
                                              $tmp_path_replace, $del_rule_0,
                                              $tmp_path_del_0, $del_rule_1,
                                              $tmp_path_del_1));
  $x['tasks'][$todo_urgent][] = array('WorkTodo', array($todo_replace_all));
  $x['tasks'][$todo_urgent][] = array('unlink', array($tmp_path_pattern));
  $x['tasks'][$todo_urgent][] = array('unlink', array($tmp_path_replace));
  $x['tasks'][$todo_urgent][] = array('unlink', array($tmp_path_del_0));
  $x['tasks'][$todo_urgent][] = array('unlink', array($tmp_path_del_1));

  return $x; }

function ReplaceAll_OnPage($todo, $title, $timestamp, $path_pattern, 
                           $path_replace, $del_rule_0, $tmp_path_del_0, 
                           $del_rule_1, $tmp_path_del_1)
# Build tasks for applying replacement rules on a specific page.
{ global $diff_dir, $nl, $pages_dir;
  $page_path      = $pages_dir.$title;
  $diff_path      = $diff_dir.$title;
  $old_text       = file_get_contents($page_path);
  $pattern        = file_get_contents($path_pattern);
  $replace        = file_get_contents($path_replace);
  $del_rule_0_alt = file_get_contents($tmp_path_del_0);
  $del_rule_1_alt = file_get_contents($tmp_path_del_1);

  # Perform actual replace task on page $text. Abort if nothing changed.
  $text = preg_replace($pattern, $replace, $old_text);
  if ($text === $old_text)
    return;

  # Don't overwrite any existing todo file with an unfinished product!
  if (is_file($todo))
    $old_todo  = file_get_contents($todo);
  $todo_temp = NewTemp($old_todo);

  # Apply page deletion rules.
  if ('' === $text)
  { if     (0 == $del_rule_0)
    { WriteTask($todo_temp, 'DeletePage', array($title, $timestamp));
      rename($todo_temp, $todo);
      return; }
    elseif (1 == $del_rule_0) 
      $text = $del_rule_0_alt; }
  if ('delete' === $text)
  { if     (0 == $del_rule_1)
    { WriteTask($todo_temp, 'DeletePage', array($title, $timestamp));
      rename($todo_temp, $todo);
      return; }
    elseif (1 == $del_rule_1) 
      $text = $del_rule_1_alt; }  

  # Else, as in any page text edit, first update / create diff file content, ...
  $new_diff_id = 0;
  $author      = 'admin';
  $summary     = 'ReplaceAll';
  $diff_add    = PlomDiff($old_text, $text);
  $diff_old    = file_get_contents($diff_path);
  $diff_list   = DiffList($diff_path); 
  $old_diff_id = 0;
  foreach ($diff_list as $id => $diff_data)
  { if ($id > $old_diff_id)
      $old_diff_id = $id;
    $new_diff_id = $old_diff_id + 1; }
  $diff_new    = $new_diff_id.$nl.$timestamp.$nl.$author.$nl.$summary.$nl.
                 $diff_add.'%%'.$nl.$diff_old;

  # ... then write diff and page file updating task into todo file
  WriteTask($todo_temp, 'SafeWrite', array($diff_path), array($diff_new));
  WriteTask($todo_temp, 'SafeWrite', array($page_path), array($text));
  rename($todo_temp, $todo); }

function GetAllLegalTitlesInDir($dir)
# Return an array of all legal page titles in $dir.
{ global $legal_title; 
  $p_dir = opendir($dir);
  while (FALSE !== ($fn = readdir($p_dir)))
    if (is_file($dir.$fn) and preg_match('/^'.$legal_title.'$/', $fn))
      $titles[] = $fn;
  closedir($p_dir); 
  return $titles; }
