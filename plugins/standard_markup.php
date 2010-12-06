<?php
# PlomWiki StandardMarkup

# Escape symbols / sequences. $esc."\n" newlines won't be replaced by "<br />.
# Bracket lines to exclude from "<p>" paragraphing in $esc_p_on and $esc_p_off.
$esc = "\r";
$esc_p_on = $esc.'p_on';
$esc_p_off = $esc.'p_off';

function MarkupLinesParagraphs($text)
# Line-break and paragraph markup.
{ global $esc, $esc_p_on, $esc_p_off;

  # Temporarily replace escaped newlines them with $esc.'n'.
  $text = str_replace($esc."\n",                               $esc.'n', $text);

  # Unescaped newlines get transformed into "<br \>" and "<p />".
  $text = str_replace("\n",                                    '<br />', $text); 
  $text = str_replace('<br /><br />',       "\n".'</p>'."\n".'<p>'."\n", $text); 
  $text = str_replace('<br />',                           '<br />'."\n", $text); 

  # Assume $text starts, ends as paragraph. If wrong, will be corrected later.
  $text = '<p>'."\n".$text."\n".'</p>';

  # All replacing of "\n" done, it's safe to replace $esc.'n' with it.
  $text = str_replace($esc.'n',                                    "\n", $text);

  # Move anything bracketed between $esc_p_on and $esc_p_off out of paragraphs.
  $text = str_replace('<p>'."\n".$esc_p_on,                          '', $text);
  $text = str_replace($esc_p_off."\n".'</p>',                        '', $text);
  $text = str_replace('<br />'."\n".$esc_p_on,         "\n".'</p>'."\n", $text);
  $text = str_replace($esc_p_off.'<br />',                   "\n".'<p>', $text);
  
  return $text; }

function MarkupInternalLinks($text)
# Wiki-internal linking markup [[LikeThis]].
{ return preg_replace('/\[\[([A-Za-z0-9]+)]]/',
                             '<a href="plomwiki.php?title=$1">$1</a>', $text); } 

function MarkupStrong($text)
# "[*This*]" becomes "<strong>This</strong>", if not broken by newlines.
{ return preg_replace('/\[\*([^'."\n".']*?)\*]/', '<strong>$1</strong>', 
                                                                       $text); }

function MarkupEmphasis($text)
# "[/This/]" becomes "<em>This</em>", if not broken by newlines.
{ return preg_replace('/\[\/([^'."\n".']*?)\/]/', '<em>$1</em>', $text); }

function MarkupDeleted($text)
# "[-This-]" becomes "<del>This</del>", if not broken by newlines.
{ return preg_replace('/\[-([^'."\n".']*?)-]/', '<del>$1</del>', $text); }

function MarkupLists($text)
# Lines starting with '*] ' preceded by multiples of double whitespace -> lists.
{ global $esc, $esc_p_on, $esc_p_off;

  # Add temporary final line to $text for final line-by-line comparision.
  $lines = explode("\n", $text."\n");

  # Find lines marked as list elements. Search up to $failed_tries_limit depth.
  # Transform any line identified into "[depth number]<li>[line text]</li>".
  $failed_tries_limit = 10;
  $li_on = '*] '; 
  $ln_li_on = strlen($li_on); 
  $depth = 0;
  while ($failed_tries <= $failed_tries_limit)
  { $failed_tries++;
    foreach ($lines as $n => $line)
    { $line_start = substr($line, 0, $ln_li_on);
      if ($line_start == $li_on)
      { $failed_tries = 0;
        $line_end = substr($line, $ln_li_on);
        $lines[$n] = $depth.'<li>'.$line_end.'</li>'.$esc; } }
    $li_on = '  '.$li_on; 
    $ln_li_on = strlen($li_on); 
    $depth++; }

  # Nest lists elements into "<ul>"/"</ul>" by depth number differences.
  $last_depth = -1;                    # -1 depth is assumed for non-list lines.
  foreach ($lines as $n => $line)
  { $depth = -1; 
    $match = preg_match('/^([0-9]+)<li>/', $line, $catch);
    if ($match) $depth = $catch[1] + 0;

    # As depth ascends, add "<ul>" and "<li>" as needed.
    if ($depth > $last_depth)
    { $add = '';                                    # $add contains "<ul><li>"
      for ($i = $last_depth + 1; $i < $depth; $i++) # steps for depth
        $add .= $i.'<ul><li>'.$esc."\n";            # differences beyond 1.
      $lines[$n] = $add.$depth.'<ul>'.$esc."\n".$lines[$n]; 
      if ($last_depth == -1) # If last line be non-list, prepend "<p />" escape.
        $lines[$n] = $esc_p_on.$lines[$n];
      else                   # Else, open last line's "<li />" for "<ul>".
        $lines[$n - 1] = substr($lines[$n - 1], 0, -6).$esc; }

    # As depth descends, add "</ul>" and "<li>" as needed.
    elseif ($depth < $last_depth)
    { for ($i = $depth; $i < $last_depth; $i++)
      { if ($i == -1)              # Unescaped "\n" provides first non-list line
          $add = $esc_p_off."\n";  # with "\n\n" start, allowing "<p>" opening.
        else
         $add = '</li>'.$esc."\n"; 
        $lines[$n] = 1 + $i.'</ul>'.$add.$lines[$n]; } }

    $last_depth = $depth; }
  
  # Transform depth numbers before "<li>", "<ul>" and "</ul>" into whitespace.
  foreach ($lines as $n => $line)
  { $lines_sub = explode($esc."\n", $line);
    foreach ($lines_sub as $n_sub => $line_sub)
    { $match = preg_match('/([0-9]+)(<((li)|(ul)|(\/ul))\>)/',$line_sub,$catch);
      if ($match)
      { $depth = $catch[1] + 0; 
        $replace = str_pad('', $depth*2);
        $lines_sub[$n_sub] = preg_replace('/[0-9]+(?=(<li>)|(<ul>)|(<\/ul>))/', 
                                                       $replace, $line_sub); } }
    $lines[$n] = implode($esc."\n", $lines_sub); }

  # Implode lines back to $text. Remove temporary final line added earlier.
  $text = implode("\n", $lines); $text = substr($text, 0, -1); return $text; }
