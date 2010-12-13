<?php
# PlomWiki StandardMarkup

# Provide help message to be shown in editing window.
$markup_help = '<h4>PlomWiki markup cheatsheet</h4>'."\n".'<p>In-line:</p>'."\n"
.'<pre style="white-space: pre-wrap;">[*<strong>strong</strong>*] '.
         '[/<em>emphasis</em>/] [-<del>deleted</del>-] [[<a href="'.$title_root.
     'PagenameOrURL">PagenameOrURL</a>]] [[PagenameOrURL|<a href="'.$title_root.
                       'PagenameOrURL">Text displayed instead</a>]]</pre>'."\n".
'<p>Multi-line:</p>'."\n".'<pre>[\Escape markup\]</pre>'."\n".
'<pre>*] list element'."\n".'  *] indented once'."\n".
'    *] indented twice</pre>';

# Escaping variables. $markup_esc."\n" newlines won't be replaced by "<br />.
# Bracket lines to exclude from "<p>" in $markup_esc_p_on and $markup_esc_p_off.
# User-escaped strings are stored via Escape() / Unescape() in $markup_escaped.
$markup_esc = "\r";
$markup_esc_p_on = $markup_esc.'p_on';
$markup_esc_p_off = $markup_esc.'p_off';
$markup_esc_user = '['.$markup_esc.']';
$markup_escaped = array();

##################
# In-line markup #
##################

function MarkupLinks($text)
# [[LinkedPagename]], [[Linked|Text displayed]], [[http://linked-url.com]].
{ global $title_root, $legal_title;
  $text = preg_replace('/\[\[('.$legal_title.')]]/',
                       '<a href="'.$title_root.'$1">$1</a>', $text);
  $text = preg_replace('/\[\[('.$legal_title.')\|([^'."\n".']+?)]]/',
                       '<a href="'.$title_root.'$1">$2</a>', $text);
  $legal_url = '((http)|(https)|(ftp)):\/\/[^ '."\n".'|]+?';
  $text = preg_replace('/\[\[('.$legal_url.')\|([^'."\n".']+?)]]/',
                       '<a href="$1">$6</a>', $text);
  return  preg_replace('/\[\[('.$legal_url.')]]/',
                       '<a href="$1">$1</a>', $text); }

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

#########################
# Multiple-lines markup #
#########################

function MarkupEscape($text)
# Replace "[\This"\]" with "[\r]", store "This" in array $markup_escaped.
{ global $markup_escaped, $markup_esc_user;

  preg_match_all('/\[\\\(.*?)\\\]/s', $text, $markup_escaped);
  $markup_escaped = $markup_escaped[1];
  $text           = preg_replace('/\[\\\.*?\\\]/s', $markup_esc_user, $text);
  return $text; }

function MarkupUnescape($text)
# Replace all "[\r]" with the string originally escaped.
{ global $markup_escaped, $markup_esc_user;

  foreach ($markup_escaped as $string)
  { $pos         = strpos($text, $markup_esc_user);
    $text_before = substr($text, 0, $pos);
    $text_after  = substr($text, $pos + strlen($markup_esc_user));
    $text        = $text_before.$string.$text_after; }
  return $text; }

function MarkupLists($text)
# Lines starting with '*] ' preceded by multiples of double whitespace -> lists.
{ global $markup_esc, $markup_esc_p_on, $markup_esc_p_off;

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
        $lines[$n] = $depth.'<li>'.$line_end.'</li>'.$markup_esc; } }
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
        $add .= $i.'<ul><li>'.$markup_esc."\n";     # differences beyond 1.
      $lines[$n] = $add.$depth.'<ul>'.$markup_esc."\n".$lines[$n]; 
      if ($last_depth == -1) # If last line be non-list, prepend "<p />" escape.
        $lines[$n] = $markup_esc_p_on.$lines[$n];
      else                   # Else, open last line's "<li />" for "<ul>".
        $lines[$n - 1] = substr($lines[$n - 1], 0, -6).$markup_esc; }

    # As depth descends, add "</ul>" and "<li>" as needed.
    elseif ($depth < $last_depth)
    { for ($i = $depth; $i < $last_depth; $i++)
      { if ($i == -1)                    # Unescaped "\n" opens non-list line
          $add = $markup_esc_p_off."\n"; # with "\n\n" start, allows for "<p>".
        else
         $add = '</li>'.$markup_esc."\n"; 
        $lines[$n] = 1 + $i.'</ul>'.$add.$lines[$n]; } }

    $last_depth = $depth; }
  
  # Transform depth numbers before "<li>", "<ul>" and "</ul>" into whitespace.
  foreach ($lines as $n => $line)
  { $lines_sub = explode($markup_esc."\n", $line);
    foreach ($lines_sub as $n_sub => $line_sub)
    { $match = preg_match('/([0-9]+)(<((li)|(ul)|(\/ul))\>)/',$line_sub,$catch);
      if ($match)
      { $depth = $catch[1] + 0; 
        $replace = str_pad('', $depth*2);
        $lines_sub[$n_sub] = preg_replace('/[0-9]+(?=(<li>)|(<ul>)|(<\/ul>))/', 
                                                       $replace, $line_sub); } }
    $lines[$n] = implode($markup_esc."\n", $lines_sub); }

  # Implode lines back to $text. Remove temporary final line added earlier.
  $text = implode("\n", $lines); $text = substr($text, 0, -1); return $text; }

function MarkupLinesParagraphs($text)
# Line-break and paragraph markup.
{ global $markup_esc, $markup_esc_p_on, $markup_esc_p_off;

  # Temporarily replace escaped newlines them with $markup_esc.'n'.
  $text = str_replace($markup_esc."\n", $markup_esc.'n', $text);

  # Unescaped newlines get transformed into "<br \>" and "<p />".
  $text = str_replace("\n",                                    '<br />', $text); 
  $text = str_replace('<br /><br />',       "\n".'</p>'."\n".'<p>'."\n", $text); 
  $text = str_replace('<br />',                           '<br />'."\n", $text); 

  # Assume $text starts, ends as paragraph. If wrong, will be corrected later.
  $text = '<p>'."\n".$text."\n".'</p>';

  # All replacing of "\n" done, it's safe to replace $markup_esc.'n' with it.
  $text = str_replace($markup_esc.'n',                             "\n", $text);

  # Move anything bracketed between $markup_esc_p_{on,off} out of paragraphs.
  $text = str_replace('<p>'."\n".$markup_esc_p_on,                   '', $text);
  $text = str_replace($markup_esc_p_off."\n".'</p>',                 '', $text);
  $text = str_replace('<br />'."\n".$markup_esc_p_on,  "\n".'</p>'."\n", $text);
  $text = str_replace($markup_esc_p_off.'<br />',            "\n".'<p>', $text);
  
  return $text; }
