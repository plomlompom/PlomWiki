<?php
# PlomWiki StandardMarkup
#
# This file contains the code for the default PlomWiki core markup.
# 
# This markup aims for tidy and readable HTML code. Functions are responsible
# not only for producing HTML, but for formatting it in a readable way, too, via
# via newlines and indentations. 
# To use, for these purposes, "\n" -- which, by itself, is marked up to "<br />"
# -- without producing HTML, escape it via preceding "\r", such as this: "\r\n".

function MarkupLinesParagraphs($text)
# Line-break and paragraph markup.
{ # Identify escaped newlines, temporarily replace them with "\r".
  $text = str_replace("\r\n",               "\r",                        $text);

  # Unescaped newlines get transformed into "<br \>" and "<p />".
  $text = str_replace("\n",                 '<br />',                    $text); 
  $text = str_replace('<br /><br />',       "\n".'</p>'."\n".'<p>'."\n", $text); 
  $text = str_replace('<br />',             '<br />'."\n",               $text); 


  # Move lists out of paragraphs. Assume escaped newline after final "</ul>"s.
  if (substr($text, 0, 4) == '<ul>')              # Take care of "<ul>" at $text 
    $text = '</p>'."\n".'<ul>'.substr($text, 4);  # start with its forced "<p>".
  $text = str_replace('<p>'."\n".'  <ul>',  "\n".'<ul>',                 $text);  
  $text = str_replace('<br />'."\n".'<ul>', '</p>'."\n".'<ul>',          $text);
  $text = str_replace('</ul>'."\r",         '</ul>'."\n".'<p>',          $text);

  # After all work is done on the latter, it's safe to replace "\r" with "\n".
  $text = str_replace("\r",                 "\n",                        $text);
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
# Lines starting with '*] ' preceded by multiples of double whitespace -> lists.
{ # Add temporary buffer line to $text for final line-by-line comparision.
  $text = $text."\n".'DEL'; $lines = explode("\n", $text);

  # Find lines marked as list elements. Search up to $failed_tries_limit depth.
  # Transform any line identified into "[depth number]<li>[line text]</li>".
  $failed_tries_limit = 10;
  $li_on = '*] '; $ln_li_on = strlen($li_on); $depth = 0;
  while ($failed_tries <= $failed_tries_limit)
  { $failed_tries++;
    foreach ($lines as $n => $line)
    { $line_start = substr($line, 0, $ln_li_on);
      if ($line_start == $li_on)
      { $failed_tries = 0;
        $line_end = substr($line, $ln_li_on);
        $lines[$n] = $depth.'<li>'.$line_end.'</li>'."\r"; } }
    $li_on = '  '.$li_on; $ln_li_on = strlen($li_on); $depth++; }

  # Nest lists elements into "<ul>"/"</ul>" by depth number differences.
  $last_depth = -1;
  foreach ($lines as $n => $line)
  { $depth = -1; 
    $match = preg_match('/^([0-9]+)<li>/', $line, $catch);
    if ($match) $depth = $catch[1] + 0;
    # As depth ascends, add as many "<ul>" and "<li>" as needed.
    if ($depth > $last_depth)
    { $add = '';
      for ($i = $last_depth + 1; $i < $depth; $i++) 
        $add .= $i.'<ul><li>'."\r\n";
      $lines[$n] = $add.$depth.'<ul>'."\r\n".$lines[$n]; 
      # If last line is "<li>" element, strip its "</li>" to open for "<ul>".
      if ($last_depth != -1)  
        $lines[$n - 1] = substr($lines[$n - 1], 0, -6)."\r"; }
    # As depth descends, add as many "</ul>" and "<li>" as needed.
    elseif ($depth < $last_depth)
    { for ($i = $depth; $i < $last_depth; $i++)
      { $li = ''; if ($i > -1) $li = '</li>';
        $lines[$n] = 1 + $i.'</ul>'.$li."\r\n".$lines[$n]; } }
    $last_depth = $depth; }
  
  # Transform depth numbers before "<li>", "<ul>" and "</ul>" into whitespace.
  foreach ($lines as $n => $line)
  { $lines_sub = explode("\r\n", $line);
    foreach ($lines_sub as $n_sub => $line_sub)
    { $match = preg_match('/([0-9]+)(<((li)|(ul)|(\/ul))\>)/',$line_sub,$catch);
      if ($match)
      { $depth = $catch[1] + 0; $replace = str_pad('', $depth*2);
        $lines_sub[$n_sub] = preg_replace('/[0-9]+(?=(<li>)|(<ul>)|(<\/ul>))/', 
                                                       $replace, $line_sub); } }
    $lines[$n] = implode("\r\n", $lines_sub); }

  # Implode lines back to $text. Remove temporary final line added earlier.
  $text = implode("\n", $lines); $text = substr($text, 0, -4); return $text; }
