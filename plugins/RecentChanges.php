<?php
# RecentChanges plugin.

$hook_Action_write .= 'Add_to_RecentChanges($timestamp, $p_todo);';
$hook_meta_actions .= '$meta_actions .= "\n".\'<a href="plomwiki.php?action='
                                         .'RecentChanges">RecentChanges</a>\';';
$RC_Path = $plugin_dir.'RecentChanges.txt';

function Add_to_RecentChanges($timestamp, $p_todo)
# Add time stamp of page change to RecentChanges file.
{ global $title, $RC_Path;                                         $RC_Txt = '';
  if (is_file($RC_Path)) $RC_Txt = file_get_contents($RC_Path);
  $RC_Txt = $timestamp.':'.$title."\n".$RC_Txt;
  $RC_Tmp = NewTempFile($RC_Txt);
  fwrite($p_todo, 'SafeWrite("'.$RC_Path.'", "'.$RC_Tmp.'");'."\n"); }

function Action_RecentChanges()
# Provide formatted output of RecentChanges file.
{ global $html_end, $RC_Path, $title_root, $wiki_view_start;

  # Format RecentChanges file content into HTML output.
  $output = '';
  if (is_file($RC_Path)) 
  { $txt = file_get_contents($RC_Path);
    $lines = explode("\n", $txt, -1);
    $date_str_old = '';
    foreach ($lines as $n => $line)
    { list($datetime_int, $pagename) = explode(':', $line);
      $datetime_str = date('Y-m-d H:i:s', (int) $datetime_int);
      list($date_str, $time_str) = explode(' ', $datetime_str);
      $lines[$n] = '  <li>'.$time_str.' <a href="'.$title_root.$pagename.'">'.
                                                          $pagename.'</a></li>'; 
      if ($date_str != $date_str_old) 
        $lines[$n] = '  </ul>'."\n".'</li>'."\n".'<li>'.$date_str."\n".
                                                       '  <ul>'."\n".$lines[$n];
      $date_str_old = $date_str; }
    $lines[0] = substr($lines[0], 14);
    $output = '<ul>'."\n".implode("\n", $lines)."\n".'  </ul>'."\n".'</li>'."\n"
                                                                     .'</ul>'; }
  else $output = '<p>No RecentChanges file found.</p>';
  
  # Final HTML.
  echo 'Recent Changes'.$wiki_view_start.
                           '<h1>Recent Changes</h1>'."\n\n".$output.$html_end; }
