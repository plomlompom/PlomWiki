<?php

function MarkupLinesParagraphs($text)
# Line-break and paragraph markup.
{ 
  # Newlines not to be translated into HTML are escaped by "\r".
  $text = str_replace("\r\n",            "\r",                           $text);

  # Unescaped newlines get transformed into "<br \>" and "<p />".
  $text = str_replace("\n",              '<br />',                       $text); 
  $text = str_replace('<br /><br />',    "\n".'</p>'."\n".'<p>'."\n",    $text); 
  $text = str_replace('<br />',          '<br />'."\n",                  $text); 

  # Move lists out of paragraphs. Assume escaped newline after final "</ul>"s.
  if (substr($text, 0, 6) == '  <ul>')   # Take care of "<ul>" at $text start.
    $text = '</p>'."\n".'  <ul>'.substr($text, 6);
  $text = str_replace('<p>'."\n".'  <ul>',      "\n".'  <ul>',           $text);  
  $text = str_replace('<br />'."\n".'  <ul>',   '</p>'."\n".'  <ul>',    $text);
  $text = str_replace('</ul>'."\r",             '</ul>'."\n".'<p>',      $text);

  # At the end, "\r"-escaped newlines become ordinary newlines.
  $text = str_replace("\r",              "\n",                           $text);
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

function MarkupLists($text)
# Turn line-text starting "*] " list element indicators into lists HTML.
{ 
  # Add temporary buffer line to $text's for final line-by-line comparision.
  $text = $text."\n".'DEL'; $lines = explode("\n", $text);

  # Find lines marked as list elements. Search up to $failed_tries_limit depth.
  # Transform any line identified into [depth number]<li>[line text]</li>.
  $failed_tries_limit = 10;
  $li_start = '*] '; $ln_li_start = strlen($li_start); $depth = 1;
  while ($failed_tries <= $failed_tries_limit)
  { $failed_tries++;
    foreach ($lines as $n => $line)
    { $line_start = substr($line, 0, $ln_li_start);
      if ($line_start == $li_start)
      { $failed_tries = 0;
        $line_end = substr($line, $ln_li_start);
        $lines[$n] = $depth.'<li>'.$line_end.'</li>'."\r"; } }
    $li_start = '  '.$li_start;  $ln_li_start = strlen($li_start); $depth++; }

  # Nest list elements into "<ul />" according to depth number differences.
  # For elements deeper than 1 step beyond previous element, add needed steps.
  # Use "\r\n" to beautify the HTML, for MarkupLinesParagraphs() will transform 
  # any "\n" not escaped by a prefixed "\r" to "<br />". 
  $last_depth = 0;
  foreach ($lines as $n => $line)
  { $depth = 0; $captured = array();
    $match = preg_match('/^([0-9]+)<li>/', $line, $captured);
    if ($match or $last_depth > 0)
    { $depth = $captured[1] + 0;

      # If depth is ascending, add needed "<ul>". If previous line was a list
      # element, open it up for new list by deleting trailing "</li>".
      if ($depth > $last_depth)
      { if ($last_depth !== 0)
          $lines[$n - 1] = substr($lines[$n - 1], 0, -6)."\r";
        if ($depth == $last_depth + 1)                  # If only 1 step deeper,
          $lines[$n] = $depth.'<ul>'."\r\n".$lines[$n]; # add just one "<ul>".
        else                                            # Else, add as many 
        { $add = '';                                    # "<ul><li>" as needed.
          for ($i = $last_depth+1; $i < $depth; $i++) 
            $add = $add.$i.'<ul><li>'."\r\n";
          $lines[$n] = $add.$depth.'<ul>'."\r\n".$lines[$n]; } }
      
      # If depth is descending, add needed </ul></li>'s.
      elseif ($depth < $last_depth)
      { for ($i = $depth; $i < $last_depth; $i++)
        { $li = ''; if ($i > 0) $li = '</li>';
          $lines[$n] = 1+$i.'</ul>'.$li."\r\n".$lines[$n]; } } }
    $last_depth = $depth; }

  # Transform the depth numbers before <li /> and <ul /> tags into whitespace.
  foreach ($lines as $n => $line)
  { $lines_sub = explode("\r\n", $line);
    foreach ($lines_sub as $n_sub => $line_sub)
    { $captured = array();
      $match = preg_match('/([0-9]+)(<((li)|(ul)|(\/ul))\>)/', $line_sub, 
                                                                     $captured);
      if ($match)
      { $depth = $captured[1] + 0; $replace = str_pad('', $depth*2);
        $lines_sub[$n_sub] = preg_replace('/[0-9]+(?=(<li>)|(<ul>)|(<\/ul>))/', 
                                                       $replace, $line_sub); } }
    $lines[$n] = implode("\r\n", $lines_sub); }

  # Implode lines back to $text. Remove the final buffer line added earlier.
  $text = implode("\n", $lines); $text = substr($text, 0, -4); return $text; }
