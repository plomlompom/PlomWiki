<?php

# PlomWiki plugin "DiffRepair"
# Provides Action_DiffRepair_Check(), Action_page_DiffRepair_Check(),
#          Action_DiffRepair(), Action_page_DiffRepair()

# Language-specific variables.
$l['CheckDiffs'] = 'Check Diffs';
$l['RepairDiffs'] = 'Repair Diffs';

$DiffRepair_Check_Actions = array(
'CheckEmptyDiffs' => array('empty diffs',      'contain empty diff(s)',       'CONTAINS EMPTY DIFFS!',      'contains no empty diffs.'),
'CheckEmptyFirst' => array('empty first diff', 'contain empty first diff(s)', 'CONTAINS EMPTY FIRST DIFF!', 'contains no empty diff.'),
'CheckBrokenLine' => array('broken diff line', 'have a broken line of diffs', 'DIFF LINE BROKEN!',          'diff line not broken.'),
'CheckBadStart'   => array('non-empty start',  'have a non-empty diff start', 'NON-EMPTY START!',           'empty start.'),
'CheckChronology' => array('bad chronology',   'have a bad chronology',       'BAD CHRONOLOGY!',            'good chronology'));
$DiffRepair_Repair_Actions = array('CorrectAddDel' => 'Correct alt/del mix-ups',
                                   'Optimize'      => 'Optimize diffs',
                                   'DeleteEmpty'   => 'Delete empty diffs');

####################
# Check functions. #
####################

function Action_page_DiffRepair_Check()
{ global $l, $title, $title_url, $DiffRepair_Check_Actions;
  $action  = $_GET['function'];

  if (in_array($action, array_keys($DiffRepair_Check_Actions)))
  { $return = FALSE;
    eval('$return = DiffRepair_'.$action.'($title);');
    if ($return) $output = $DiffRepair_Check_Actions[$action][2];
    else         $output = $DiffRepair_Check_Actions[$action][3];
    $title = 'Does page "'.$title.'" '.$DiffRepair_Check_Actions[$action][1].'?'; }

  else
  { $title = 'Check diff of page "'.$title.'" for ...';
    $output = '';
    foreach ($DiffRepair_Check_Actions as $action => $data)
    { $name = $data[0];
      $output .= '<a href="'.$title_url.'&amp;action=page_DiffRepair_Check&amp;function='.$action.'" >'.$name.'</a><br />'.$nl; } }

  $l['title'] = $title; $l['content'] = $output;
  OutputHTML(); }

function Action_DiffRepair_Check()
{ global $l, $root_rel, $DiffRepair_Check_Actions, $title_root;
  $action = $_GET['function'];

  if (in_array($action, array_keys($DiffRepair_Check_Actions)))
  { $titles = GetAllPageTitles();
    $titles_affected = array();
    foreach ($titles as $title)
    { $return = FALSE;
      eval('$return = DiffRepair_'.$action.'($title);');
      if ($return)
        $titles_affected[] = $title; }
    foreach ($titles_affected as $title)
      $output .= '<a href="'.$title_root.$title.'">'.$title.'</a><br />'; 
    $title = 'These pages '.$DiffRepair_Check_Actions[$action][1].':'; }

else
  { $title = 'Check diffs for ...';
    $output = '';
    foreach ($DiffRepair_Check_Actions as $action => $data)
    { $name = $data[0];
      $output .= '<a href="'.$root_rel.'?action=DiffRepair_Check&amp;function='.$action.'" >'.$name.'</a><br />'.$nl; } }

  $l['title'] = $title; $l['content'] = $output;
  OutputHTML(); }

function DiffRepair_CheckEmptyFirst($title)
{ global $diff_dir;
  $diff_path = $diff_dir.$title;
  $diff_list_reversed = array_reverse(DiffList($diff_path));
  if (empty($diff_list_reversed[0]['text'])) return TRUE;
  else                                       return FALSE; }

function DiffRepair_CheckEmptyDiffs($title)
{ global $diff_dir;
  $diff_path = $diff_dir.$title;
  $diff_list = DiffList($diff_path);
  $has_empty_diffs = FALSE;
  foreach ($diff_list as $diff_data)
    if (empty($diff_data['text']))
    { $has_empty_diffs = TRUE;
      break; }
  return $has_empty_diffs;  }

function DiffRepair_CheckBrokenLine($title)
{ global $diff_dir, $pages_dir;
  $page_path = $pages_dir.$title;
  $diff_path = $diff_dir.$title;
  $diff_list = DiffList($diff_path);
  $text = $text_original = file_get_contents($page_path);

  foreach ($diff_list as $diff_data)
  { $diff          = $diff_data['text'];
    $reversed_diff = ReverseDiff($diff);
    $text          = PlomPatch($text, $reversed_diff); }

  $diff_list_reversed = array_reverse($diff_list);
  foreach ($diff_list_reversed as $diff_data)
  { $diff = $diff_data['text'];
    $text = PlomPatch($text, $diff); }

  if ($text !== $text_original) return TRUE;
  else                          return FALSE; }

function DiffRepair_CheckBadStart($title)
{ global $diff_dir, $pages_dir;
  $page_path = $pages_dir.$title;
  $diff_path = $diff_dir.$title;
  $diff_list = DiffList($diff_path);
  $text = file_get_contents($page_path);

  foreach ($diff_list as $diff_data)
  { $diff          = $diff_data['text'];
    $reversed_diff = ReverseDiff($diff);
    $text          = PlomPatch($text, $reversed_diff); }

  if ($text !== '') return TRUE;
  else              return FALSE; }

function DiffRepair_CheckChronology($title)
{ global $diff_dir, $pages_dir;
  $page_path = $pages_dir.$title;
  $diff_path = $diff_dir.$title;
  $diff_list = DiffList($diff_path);
  $bad = FALSE;
  $last_date = 0;
  $diff_list = array_reverse($diff_list);
  foreach ($diff_list as $diff_data)
    if ($diff_data['time'] < $last_date)
    { $bad = TRUE;
      break; }
    else
      $last_date = $diff_data['time'];
  return $bad;  }

#####################
# Repair functions. #
#####################

function Action_page_DiffRepair()
{ global $l, $title, $title_url, $DiffRepair_Repair_Actions;
  foreach ($DiffRepair_Repair_Actions as $action => $name)
    $input .= '<input type="radio" name="function" value="'.$action.'">'.$name.'<br />';
  $content = BuildPostForm($title_url.'&amp;action=write&amp;t=DiffRepairPage', $input);
  $l['title'] = 'Apply repair function to diffs for "'.$title.'"?'; $l['content'] = $content;
  OutputHTML(); }

function Action_DiffRepair()
{ global $l, $root_rel, $DiffRepair_Repair_Actions;
  foreach ($DiffRepair_Repair_Actions as $action => $name)
    $input .= '<input type="radio" name="function" value="'.$action.'">'.$name.'<br />';
  $content = BuildPostForm($root_rel.'?action=write&amp;t=DiffRepairAll', $input);
  $l['title'] = 'Apply repair function to all pages?'; $l['content'] = $content;
  OutputHTML(); }

function PrepareWrite_DiffRepairPage()
{ global $todo_urgent, $title, $title_url;
  $function = $_POST['function'];
  $temp = NewTemp();
  $x['tasks'][$todo_urgent][] = array('DiffRepairWrite', array($title, $function, $temp));
  $x['redir'] = $title_url;
  return $x; }

function PrepareWrite_DiffRepairAll()
{ global $todo_urgent;
  $function = $_POST['function'];
  $titles = GetAllPageTitles();
  foreach ($titles as $title)
  { $temp = NewTemp();
    $x['tasks'][$todo_urgent][] = array('DiffRepairWrite', array($title, $function, $temp)); }
  return $x; }

function DiffRepairWrite($title, $function, $temp)
{ global $diff_dir, $nl, $pages_dir;
  $function = 'DiffRepair_'.$function;
  $diff_path = $diff_dir.$title;
  $diff_list = DiffList($diff_path);

  $diff_list = array_reverse($diff_list);
  eval('$diff_list ='.$function.'($diff_list);');

  $diff_text = '';
  $i = 0;
  foreach ($diff_list as $diff_data)
  { if ($nl === substr($diff_data['text'], -1)) $diff_data['text'] = substr($diff_data['text'], 0, -1);
    $diff_text = $i.$nl.$diff_data['time'].$nl.$diff_data['author'].$nl.$diff_data['summary'].$nl.$diff_data['text'].$nl.'%%'.$nl.$diff_text; 
    $i++; }
  file_put_contents($temp, $diff_text);

  $original_diff_text = file_get_contents($diff_path);
  if ($diff_text == $original_diff_text)
  { unlink($temp);
    echo 'not writing '.$title.'<br />'.$nl;
    return; }
  else
    echo 'WRITING '.$title.'<br />'.$nl;

  if (is_file($temp))   
    rename($temp, $diff_path); }

function DiffRepair_CorrectAddDel($diff_list)
{ foreach($diff_list as $id => $diff_data)
  { $diff = $diff_data['text'];
    $lines = explode("\n", $diff);
    foreach ($lines as $n => $line)
      if ('<' !== $line[0] and '>' !== $line[0] and FALSE !== strpos($line, 'a'))
        if ('<' == $lines[$n+1][0])
        { $pos = strpos($line, 'a');
          $line[$pos] = 'd';
          $lines[$n] = $line; }
    foreach ($lines as $n => $line)
      if ('<' !== $line[0] and '>' !== $line[0] and FALSE !== strpos($line, 'd'))
        if ('>' == $lines[$n+1][0])
        { $pos = strpos($line, 'd');
          $line[$pos] = 'a';
          $lines[$n] = $line; }
    $diff = implode("\n", $lines);
    $diff_list[$id]['text'] = $diff; }
  return $diff_list; }

function DiffRepair_DeleteEmpty($diff_list)
{ foreach ($diff_list as $n => $diff_data)
    if (empty($diff_data['text']))
      unset($diff_list[$n]);
  return $diff_list; }

function DiffRepair_Optimize($diff_list)
{ global $esc;
  $text_older = $esc;
  foreach ($diff_list as $id => $diff_data)
  { $diff_old               = $diff_data['text'];
    $text_newer             = PlomPatch($text_older, $diff_old);
    $diff_new               = PlomDiff($text_older, $text_newer);
    $text_older             = $text_newer;
    $diff_list[$id]['text'] = $diff_new; }
  return $diff_list; }
