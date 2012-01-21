<?php
# PlomWiki plugin "RecentChanges"
# Provides Action_RecentChanges().

# Language-specific variables.
$l['RecentChanges'] = 'Recent Changes';
$l['NoRecentChanges'] = 'No RecentChanges file found.';

$RC_dir                  = $plugin_dir.'RecentChanges/';
$RC_path                 = $RC_dir.'RecentChanges.txt';
$hook_WritePage .= '
$tmp = Newtemp();
$x = NewTemp($txt_PluginsTodo);
$state = 1;
if     ($text == \'delete\') $state = 0;
elseif ($diff_old == \'\')   $state = 2;
$tmp_author  = NewTemp($author);
$tmp_summary = NewTemp($summary);
WriteTask($x, "Add_to_RecentChanges", array($title, $timestamp, $new_diff_id, 
                                            $tmp_author, $tmp_summary, $tmp,
                                            $state));
WriteTask($x, "unlink", array($tmp_author));
WriteTask($x, "unlink", array($tmp_summary));
$txt_PluginsTodo = file_get_contents($x);
unlink($x);';

function Add_to_RecentChanges($title, $timestamp, $id, $tmp_author,
                              $tmp_summary, $tmp,$state)
# Add info of page change to RecentChanges file.
{ global $nl, $l, $RC_dir, $RC_path, $todo_urgent;

  $author  = file_get_contents($tmp_author);
  $summary = file_get_contents($tmp_summary);

  if (!is_dir($RC_dir))
    mkdir($RC_dir);

  $RC_txt = '';
  if (is_file($RC_path))
    $RC_txt = file_get_contents($RC_path);

  if     (0 == $state) $title = '!'.$title;
  elseif (2 == $state) $title = '+'.$title;

  $RC_txt = $timestamp.$nl.$title.$nl.$id.$nl.$author.$nl.$summary.$nl.'%%'.$nl.
                                                                        $RC_txt;

  if (is_file($tmp))
  { file_put_contents($tmp, $RC_txt); 
    rename($tmp, $RC_path); } }

function Action_RecentChanges()
# Provide HTML output of RecentChanges file.
{ global $esc, $l, $nl, $nl2, $RC_path, $title_root;

  # Format RecentChanges file content into HTML output.
  $output = '';
  if (is_file($RC_path)) 
  { $txt = file_get_contents($RC_path);
    $lines    = explode($nl, $txt);
    $i        = 0;
    $date_old = $state = $state_on = $state_off = '';
    foreach ($lines as $line)
    { $i++;
      if ('%%' == $line)
        $i = 0;
      if     (1 == $i) 
      { $datetime   = date('Y-m-d H:i:s', (int) $line);
        list($date, $time) = explode(' ', $datetime); }
      elseif (2 == $i)
      { $title  = $line; 
        if ('!' == $title[0] or '+' == $title[0]) 
        { $state = $title[0];
          $title = substr($title, 1); } }
      elseif (3 == $i)
        $id     = $line;
      elseif (4 == $i)
        $author = $line;
      elseif (5 == $i) 
      { $diff_link = ' <a href="'.$title_root.$title.'&amp;action=page_history#'
                                                                 .$id.'">#</a>';
        if     ('!' == $state)
        { $state_on  = '<del>';
          $state_off = '</del>';
          $diff_link=''; }
        elseif ('+' == $state)
        { $state_on='<strong>';
          $state_off='</strong>'; }
        $string = '               <li>'.$time.' <a href="'.$title_root.$title.
                   '">'.$state_on.$title.$state_off.'</a>'.$diff_link.': '.$line
                                                         .' ('.$author.')</li>';
        $state = $state_on = $state_off = '';
        if ($date != $date_old)
        { $string = substr($string, 15);
          $string = '          </ul>'.$nl.'     </li>'.$nl.'     <li>'.$date.$nl
                                                     .'          <ul> '.$string;
          $date_old = $date; }
        $list[] = $string; } }
    $list[0] = substr($list[0], 15);
    $output = '<ul>'.implode($nl, $list).$nl.'          </ul>'.$nl.'     </li>'.
                                                                  $nl.'</ul>'; }
  else 
    $output = '<p>'.$esc.'NoRecentChanges'.$esc.'</p>';
  
  # Final HTML.
  $l['title'] = $esc.'RecentChanges'.$esc; $l['content'] = $output;
  OutputHTML(); }
