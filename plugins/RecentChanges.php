<?php
# RecentChanges plugin.

$anchor_Action_write .= 'Add_to_RecentChanges($timestamp, $p_todo);';
$anchor_action_links .= '$action_links .= \' <a href="plomwiki.php?title=\'.'.
                     '$title.\'&amp;action=RecentChanges">RecentChanges</a>\';';
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
{ global $normal_view_start, $RC_Path;

  # Format RecentChanges file content into HTML output.
  $output = '';
  if (is_file($RC_Path)) 
  { $txt = file_get_contents($RC_Path);
    $lines = explode("\n", $txt, -1);
    foreach ($lines as $n => $line)
    { list($time, $name) = explode(':', $line);
      $date = date('Y-m-d H:i:s', (int) $time);
      $lines[$n] = '<li>'.$date.' <a href="plomwiki.php?title='.$name.'">'.$name
                                                                 .'</a></li>'; }
    $output = '<ul>'."\n".implode("\n", $lines)."\n".'</ul>'; }
  else $output = '<p>No RecentChanges file found.</p>';
  
  # Final HTML.
  echo 'Recent Changes</title>'."\n".'</head>'."\n".'<body>'."\n\n".
                                     '<h1>Recent Changes</h1>'."\n\n".$output; }
