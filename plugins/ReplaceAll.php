<?php

# PlomWiki plugin "ReplaceAll"
# Provides Action_ReplaceAll()

$l['GlobalReplace'] = 'GlobalReplace';

function ReplaceAll_RegexError($errno, $errstr)
# Return PHP preg_replace() error message to explain regex mistake.
{ ErrorFail('No valid regex given for $pattern.', 
            'PHP mumbles: <em>'.$errstr.'</em>'); }

function Action_ReplaceAll()
# Administration menu for markup translation.
{ global $l, $nl, $root_rel;

  $input = '<p>Perform string / regular expression replacement for all pages.</p>'.$nl.
           '<h3>What to replace by what</h3>'.$nl.
           '<p>$pattern: <input name="pattern" type="text" size="30" \><br />'.
                                                                            $nl.
           '$replace: <input name="replace" type="text" size="30" \><br />'.$nl.
           '<input name="regex" type="checkbox" /> Perform regular '.$nl.
                 'expression replacement via preg_replace(). (Any "\r" in '.$nl.
                   '$pattern and $replace and any "e" modifier in $pattern'.$nl.
                                                    'will be removed.)</p>'.$nl.
           '<h3>Accidental/planned page deletion</h3>'.$nl.
           '<p><input type="radio" name="del_rule_0" value="0" />Delete pages '.
                                       'that become empty as a result.<br>'.$nl.
           '<input type="radio" name="del_rule_0" value="1" checked />Fill said'
          .' pages with this text: <input type="text" name="del_rule_0_alt" '.
                 'size="30" value="Emptied by GlobalReplace" /><p />'.$nl.
           '<p><input type="radio" name="del_rule_1" value="0" />Delete pages '.
                       'whose text is reduced to "delete" as a result.<br>'.$nl.
           '<input type="radio" name="del_rule_1" value="1" checked />Fill said'
          .' pages with this text: <input type="text" name="del_rule_1_alt" '.
                 'size="30" value="Emptied by GlobalReplace" /><p />'.$nl.
           '<h3>Author / summary</h3>'.
           '<p>Author: <input name="author" type="text" value="Admin" \> '.$nl.
                              'Summary: <input name="summary" type="text" '.$nl.
                                             'value="GlobalReplace" \></p>'.$nl.
           '<h3>Affirm</h3>';

  $form  = BuildPostForm($root_rel.'?action=write&amp;t=ReplaceAll', $input);
  $l['title'] = 'Global string / regex replacement'; $l['content'] = $form;
  OutputHTML(); }

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
  $regex            = Sanitize($_POST['regex']);
  $tmp_path_author  = NewTemp($_POST['author']);
  $tmp_path_summary = NewTemp($_POST['summary']);
  $tmp_path_del_0   = NewTemp($del_rule_0_alt);
  $tmp_path_del_1   = NewTemp($del_rule_1_alt);
  $tmp_path_replace = NewTemp($replace);
  $titles           = GetAllLegalTitlesInDir($pages_dir);
  $timestamp        = time();

  # Validate and un-mine $pattern (remove e modifier) before writing it.
  if ('on' == $regex)
  { $pattern = preg_replace('/e(?=[eimsxADSUXJu]*$)/', '', $pattern);
    set_error_handler('ReplaceAll_RegexError');
    preg_match($pattern, 'test');
    restore_error_handler(); }
  $tmp_path_pattern = NewTemp($pattern);

  # Write tasks.
  $x['tasks'][$todo_urgent][] = array('touch', array($todo_replace_all));
  foreach ($titles as $title)
  { $todo_temp = NewTemp();
    $x['tasks'][$todo_urgent][] = array('ReplaceAll_OnPage',
                                        array($todo_replace_all, $title, $regex,
                                              $timestamp, $tmp_path_pattern,
                                              $tmp_path_replace, 
                                              $tmp_path_author, 
                                              $tmp_path_summary, $del_rule_0,
                                              $tmp_path_del_0, $del_rule_1,
                                              $tmp_path_del_1, $todo_temp)); }
  $x['tasks'][$todo_urgent][] = array('WorkTodo', array($todo_replace_all));
  $x['tasks'][$todo_urgent][] = array('unlink', array($tmp_path_pattern));
  $x['tasks'][$todo_urgent][] = array('unlink', array($tmp_path_replace));
  $x['tasks'][$todo_urgent][] = array('unlink', array($tmp_path_author));
  $x['tasks'][$todo_urgent][] = array('unlink', array($tmp_path_summary));
  $x['tasks'][$todo_urgent][] = array('unlink', array($tmp_path_del_0));
  $x['tasks'][$todo_urgent][] = array('unlink', array($tmp_path_del_1));

  return $x; }

function ReplaceAll_OnPage($todo_replace_all, $title, $regex, $timestamp, 
                           $path_pattern, $path_replace, $path_author,
                           $path_summary, $del_rule_0, $tmp_path_del_0,
                           $del_rule_1, $tmp_path_del_1, $todo_tmp_replace_all)
{ global $diff_dir, $nl, $pages_dir, $work_dir;
  $page_path      = $pages_dir.$title;
  $diff_path      = $diff_dir.$title;
  $old_text       = file_get_contents($page_path);
  $pattern        = file_get_contents($path_pattern);
  $replace        = file_get_contents($path_replace);
  $del_rule_0_alt = file_get_contents($tmp_path_del_0);
  $del_rule_1_alt = file_get_contents($tmp_path_del_1);

  # Perform actual replace task on page $text. Abort if nothing changed.
  if ('on' == $regex) $text = preg_replace($pattern, $replace, $old_text);
  else                $text = str_replace($pattern, $replace, $old_text);
  if ($text === $old_text)
  { unlink($todo_tmp_replace_all);                # Clean up unneeded temp file.
    return; }

  # Apply page deletion rules.
  if ('delete' === $text)
    if (1 == $del_rule_1) $text = $del_rule_1_alt;
  if ('' === $text)
  { if     (0 == $del_rule_0) $text = 'delete';
    elseif (1 == $del_rule_0) $text = $del_rule_0_alt; }

  if ($todo_tmp_replace_all)
  { $x = file_get_contents($todo_replace_all);
    file_put_contents($todo_tmp_replace_all, $x);
    $path_text    = NewTemp($text);
    $todo_plugins = $work_dir.'todo_plugins';
    $tmp_0 = NewTemp(); $tmp_1 = NewTemp(); $tmp_2 = NewTemp();
    $tmp_path_author  = NewTemp(file_get_contents($path_author));
    $tmp_path_summary = NewTemp(file_get_contents($path_summary));
    WriteTask($todo_tmp_replace_all, 'WritePage', 
              array($title, $todo_plugins, $tmp_0, $tmp_1, $tmp_2, $path_text,
                    $tmp_path_author, $tmp_path_summary));
    WriteTask($todo_tmp_replace_all, 'WorkTodo', array($todo_plugins));
    rename($todo_tmp_replace_all, $todo_replace_all); } }

function GetAllLegalTitlesInDir($dir)
# Return an array of all legal page titles in $dir.
{ global $legal_title; 
  $p_dir = opendir($dir);
  while (FALSE !== ($fn = readdir($p_dir)))
    if (is_file($dir.$fn) and preg_match('/^'.$legal_title.'$/', $fn))
      $titles[] = $fn;
  closedir($p_dir); 
  return $titles; }
