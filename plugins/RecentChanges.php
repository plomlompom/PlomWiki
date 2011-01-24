<?php
# RecentChanges plugin.

$RC_dir                  = $plugin_dir.'RecentChanges/';
$RC_path                 = $RC_dir.'RecentChanges.txt';
$actions_meta[]          = array('RecentChanges', '?action=RecentChanges');
$hook_PrepareWrite_page .= 
             'Add_to_RecentChanges($x, $title, $timestamp, $author, $summary);';

function Add_to_RecentChanges(&$x, $title, $timestamp, $author, $summary)
# Add time stamp of page change to RecentChanges file.
{ global $nl, $RC_dir, $RC_path, $todo_urgent;

  if (!is_dir($RC_dir))
    mkdir($RC_dir);

  $RC_txt = '';
  if (is_file($RC_path))
    $RC_txt = file_get_contents($RC_path);

  $RC_txt = $timestamp.$nl.$title.$nl.$author.$nl.$summary.$nl.'%%'.$nl.$RC_txt;

  $x['tasks'][$todo_urgent][] = array('SafeWrite',
                                             array($RC_path), array($RC_txt)); }

function Action_RecentChanges()
# Provide HTML output of RecentChanges file.
{ global $nl, $nl2, $RC_path, $title_root;

  # Format RecentChanges file content into HTML output.
  $output = '';
  if (is_file($RC_path)) 
  { $txt = file_get_contents($RC_path);
    $lines    = explode($nl, $txt);
    $date_old = '';
    $i        = 0;
    foreach ($lines as $line)
    { $i++;
      if ('%%' == $line)
        $i = 0;
      if     (1 == $i) 
      { $datetime   = date('Y-m-d H:i:s', (int) $line);
        list($date, $time) = explode(' ', $datetime); }
      elseif (2 == $i)
        $title  = $line;
      elseif (3 == $i)
        $author = $line;
      elseif (4 == $i) 
      { $string = '               <li>'.$time.' <a href="'.$title_root.$title.
                               '">'.$title.'</a>: '.$line.' ('.$author.')</li>';
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
    $output = '<p>No RecentChanges file found.</p>';
  
  # Final HTML.
  Output_HTML('Recent Changes', $output); }
