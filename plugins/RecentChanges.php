<?php
# RecentChanges plugin.

$RC_dir                  = $plugin_dir.'RecentChanges/';
$RC_path                 = $RC_dir.'RecentChanges.txt';
$actions_meta[]          = array('RecentChanges', '?action=RecentChanges');
$hook_PrepareWrite_page .= '$x = Add_to_RecentChanges($title, $timestamp, $x);';

function Add_to_RecentChanges($title, $timestamp, $x)
# Add time stamp of page change to RecentChanges file.
{ global $nl, $RC_dir, $RC_path, $todo_urgent;

  if (!is_dir($RC_dir))
    mkdir($RC_dir);

  $RC_txt = '';
  if (is_file($RC_path))
    $RC_txt = file_get_contents($RC_path);
  $RC_txt = $timestamp.':'.$title.$nl.$RC_txt;

  $x['tasks'][$todo_urgent][] = array('SafeWrite',
                                      array($RC_path), array($RC_txt));
  return $x; }

function Action_RecentChanges()
# Provide HTML output of RecentChanges file.
{ global $nl, $nl2, $RC_path, $title_root;

  # Format RecentChanges file content into HTML output.
  $output = '';
  if (is_file($RC_path)) 
  { $txt = file_get_contents($RC_path);
    $lines = explode($nl, $txt, -1);
    $date_str_old = '';
    foreach ($lines as $n => $line)
    { list($datetime_int, $pagename) = explode(':', $line);
      $datetime_str = date('Y-m-d H:i:s', (int) $datetime_int);
      list($date_str, $time_str) = explode(' ', $datetime_str);
      $lines[$n] = '  <li>'.$time_str.' <a href="'.$title_root.$pagename.'">'.
                                                          $pagename.'</a></li>'; 
      if ($date_str != $date_str_old) 
        $lines[$n] = '  </ul>'.$nl.'</li>'.$nl.'<li>'.$date_str.$nl.
                                                       '  <ul>'.$nl.$lines[$n];
      $date_str_old = $date_str; }
    $lines[0] = substr($lines[0], 14);
    $output = '<ul>'.$nl.implode($nl, $lines).$nl.'  </ul>'.$nl.'</li>'.$nl
                                                                     .'</ul>'; }
  else 
    $output = '<p>No RecentChanges file found.</p>';
  
  $title_h = 'Recent Changes';
  Output_HTML($title_h, $output); }
