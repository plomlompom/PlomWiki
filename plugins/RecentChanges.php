<?php
# RecentChanges plugin.

$RC_Path            = $plugin_dir.'RecentChanges.txt';
$actions_meta[]     = array('RecentChanges', '?action=RecentChanges');
$hook_page_write   .= 'Add_to_RecentChanges($time, $p_todo);';

function Add_to_RecentChanges($timestamp, $p_todo)
# Add time stamp of page change to RecentChanges file.
{ global $nl, $title, $RC_Path;                                         
  $RC_Txt = '';
  if (is_file($RC_Path)) $RC_Txt = file_get_contents($RC_Path);
  $RC_Txt = $timestamp.':'.$title.$nl.$RC_Txt;
  $RC_Tmp = NewTempFile($RC_Txt);
  fwrite($p_todo, 'SafeWrite("'.$RC_Path.'", "'.$RC_Tmp.'");'.$nl); }

function Action_RecentChanges()
# Provide formatted output of RecentChanges file.
{ global $nl, $nl2, $RC_Path, $title_root;

  # Format RecentChanges file content into HTML output.
  $output = '';
  if (is_file($RC_Path)) 
  { $txt = file_get_contents($RC_Path);
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
  else $output = '<p>No RecentChanges file found.</p>';
  
  $title_h = 'Recent Changes';
  Output_HTML($title_h, $output); }
