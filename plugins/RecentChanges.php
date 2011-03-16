<?php
# RecentChanges plugin.

$RC_dir                  = $plugin_dir.'RecentChanges/';
$RC_path                 = $RC_dir.'RecentChanges.txt';
$actions_meta[]          = array('RecentChanges', '?action=RecentChanges');
$hook_WritePage .= '
$tmp = Newtemp();
$x = NewTemp($txt_PluginsTodo);
$state = 1;
if     ($text == \'delete\') $state = 0;
elseif ($diff_old == \'\')   $state = 2;
WriteTask($x, "Add_to_RecentChanges", array($title, $timestamp, $author,
                                            $summary, $tmp, $state));
$txt_PluginsTodo = file_get_contents($x);
unlink($x);';

function Add_to_RecentChanges($title,$timestamp,$author,$summary,$tmp,$state)
# Add info of page change to RecentChanges file.
{ global $nl, $RC_dir, $RC_path, $todo_urgent;

  if (!is_dir($RC_dir))
    mkdir($RC_dir);

  $RC_txt = '';
  if (is_file($RC_path))
    $RC_txt = file_get_contents($RC_path);

  if     (0 == $state) $title = '!'.$title;
  elseif (2 == $state) $title = '+'.$title;

  $RC_txt = $timestamp.$nl.$title.$nl.$author.$nl.$summary.$nl.'%%'.$nl.$RC_txt;

  if (is_file($tmp))
  { file_put_contents($tmp, $RC_txt); 
    rename($tmp, $RC_path); } }

function Action_RecentChanges()
# Provide HTML output of RecentChanges file.
{ global $nl, $nl2, $RC_path, $title_root;

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
        $author = $line;
      elseif (4 == $i) 
      { if     ('!' == $state) { $state_on='<del>';    $state_off='</del>'; }
        elseif ('+' == $state) { $state_on='<strong>'; $state_off='</strong>'; }
        $string = '               <li>'.$time.' <a href="'.$title_root.$title.
                   '">'.$state_on.$title.$state_off.'</a>: '.$line.' ('.$author.
                                                                       ')</li>';
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
    $output = '<p>No RecentChanges file found.</p>';
  
  # Final HTML.
  Output_HTML('Recent Changes', $output); }
