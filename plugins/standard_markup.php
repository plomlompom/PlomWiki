<?php
# PlomWiki StandardMarkup

# Provide help message to be shown in editing window.
$markup_help = '<h4>PlomWiki markup cheatsheet</h4>'."\n".'<p>In-line:</p>'."\n"
              .'<pre style="white-space: pre-wrap;">[*<strong>strong</strong>*]'
              .' [/<em>emphasis</em>/] [-<del>deleted</del>-] [[<a href="'.
               $title_root.'PagenameOrURL">PagenameOrURL</a>]] [[PagenameOrURL|'
              .'<a href="'.$title_root.'PagenameOrURL">Text displayed instead</'
              .'a>]] [\Escaped [*markup*]\]</pre>'."\n".
               '<p>Multi-line:</p>'."\n".'<pre>*] list element'."\n".
               '  *] indented once'."\n".'    *] indented twice'."\n\n".
               '[@'."\n".'echo "Code, preformatted";'."\n".'@]</pre>';

# Escape marks. Remember "\r" is stripped from any page text by plomwiki.php.
# A line starting with $markup_esc escapes paragraphing by MarkupParagraphs().
# $markup_esc_{on,off,store} are used by MarkupEscape() and MarkupUnescape()
# for the user-defined markup escaping via "[\...\]".
$markup_esc       = "\r";
$markup_esc_on    = '['.$markup_esc;
$markup_esc_off   = $markup_esc.']';
$markup_esc_store = array();

##################
# In-line markup #
##################

function MarkupLinks($text)
# [[LinkedPagename]], [[Linked|Text displayed]], [[http://linked-url.com]].
{ global $title_root, $legal_title;

  # [[Title]] and [[Title|Text]]
  $text = preg_replace('/\[\[('.$legal_title.')]]/',
                       '<a href="'.$title_root.'$1">$1</a>', $text);
  $text = preg_replace('/\[\[('.$legal_title.')\|([^'."\n".']+?)]]/',
                       '<a href="'.$title_root.'$1">$2</a>', $text);

  # [[URL|Text]] and [[URL]]
  $legal_url = '((http)|(https)|(ftp)):\/\/[^ '."\n".'|]+?';
  $text = preg_replace('/\[\[('.$legal_url.')\|([^'."\n".']+?)]]/',
                       '<a href="$1">$6</a>', $text);
  return  preg_replace('/\[\[('.$legal_url.')]]/',
                       '<a href="$1">$1</a>', $text); }

function MarkupStrong($text)
# "[*This*]" becomes "<strong>This</strong>", if not broken by newlines.
{ return preg_replace('/\[\*(.*?)\*]/', '<strong>$1</strong>', $text); }

function MarkupEmphasis($text)
# "[/This/]" becomes "<em>This</em>", if not broken by newlines.
{ return preg_replace('/\[\/(.*?)\/]/', '<em>$1</em>',         $text); }

function MarkupDeleted($text)
# "[-This-]" becomes "<del>This</del>", if not broken by newlines.
{ return preg_replace('/\[-(.*?)-]/',   '<del>$1</del>',       $text); }

function MarkupEscape($text)
# Replace "[\This"\]" with "[\r]", store "This" in array $markup_escaped.
{ global $markup_esc_off, $markup_esc_on, $markup_esc_store;
  $regex = '/\[\\\(.*?)\\\]/';

  # Catch all escaped strings in $store_tmp.
  $store_tmp = array();
  preg_match_all($regex, $text, $store_tmp);
  $store_tmp = $store_tmp[1];

  # Replace escapes in $text with placeholders for / keys to escaped strings.
  $offset = count($markup_esc_store);
  foreach ($store_tmp as $n => $string)
  { $n += $offset;
    $repl = $markup_esc_on.$n.$markup_esc_off;
    $text = preg_replace($regex, $repl, $text, $limit = 1); }

  # Add $store_tmp to markup_esc_store.
  foreach ($store_tmp as $add)
    $markup_esc_store[] = $add;

  return $text; }

function MarkupUnescape($text)
# Replace all "[\r]" with the string originally escaped.
{ global $markup_esc_off, $markup_esc_on, $markup_esc_store;

  foreach ($markup_esc_store as $n => $string)
    $text = str_replace($markup_esc_on.$n.$markup_esc_off, $string, $text);

  foreach ($markup_esc_store as $n => $string)
    $text = str_replace($markup_esc_on.$n.$markup_esc_off, $string, $text);

  return $text; }

#####################
# Multi-line markup #
#####################

function MarkupCode($text)
# <pre>-format multi-line code block.
{ global $markup_esc;
  $line_start = '^|'."\n";
  $line_end   = '$|'."\n";
  $regex      = '\[@(.*?)@]';

  # For further modification, temporarily store marked up code in $store.
  $store = array();
  preg_match_all('/('.$line_start.')'.$regex.'('.$line_end.')/s', 
                 $text, $store);
  $store = $store[2];

  # Escape $store'd lines. Replace marked up code with these, <pre>-format it.
  foreach ($store as $pre)
  { $pre  = preg_replace('/(?<='.$line_start.')(.*?)(?='.$line_end.')/', 
                         $markup_esc.'$1', $pre);
    $text = preg_replace('/(?<='.$line_start.')'.$regex.'(?='.$line_end.')/s', 
                         $markup_esc.'<pre>'."\n".$pre."\n\r".'</pre>', 
                         $text, $limit = 1); }
  
  return $text; }

function MarkupLists($text)
# Lines starting with '*] ' preceded by multiples of double whitespace -> lists.
{ global $markup_esc;
  $li_on = '*] '; 
  $lines = explode("\n", $text);
  $lines[] = ''; # Add virtual last line for backwards line-by-line comparisons.
  $mark_list = $markup_esc.'l'.$markup_esc;
  $ln_mark = strlen($mark_list);

  # Find lines marked as list elements. Search up to $failed_tries_limit depth.
  # Transform any line identified into "$mark_list[depth]<li>[line text]</li>".
  $failed_tries_limit = 10;
  $ln_li_on = strlen($li_on); 
  $depth = 1;
  while ($failed_tries <= $failed_tries_limit)
  { $failed_tries++;
    foreach ($lines as $n => $line)
    { $line_start = substr($line, 0, $ln_li_on);
      if ($line_start == $li_on)
      { $failed_tries = 0;
        $line_end = substr($line, $ln_li_on);
        $lines[$n] = $mark_list.$depth.'<li>'.$line_end.'</li>'; } }
    $li_on = '  '.$li_on; 
    $ln_li_on = strlen($li_on); 
    $depth++; }

  # Nest lists elements into "<ul>"/"</ul>" by depth number differences.
  $lines_new = array();
  $n_new = -1;
  $last_depth = 0;                 # $depth = 0 is assumed for non-list lines.
  foreach ($lines as $line)
  { $depth = 0;
    $is_list = FALSE;
    if (substr($line, 0, $ln_mark) == $mark_list)
    { $is_list = preg_match('/^'.$mark_list.'([0-9]+)/', $line, $catch);
      $depth = $catch[1]; }
      
    # As depth ascends, add "<ul>" and "<li>" as needed.
    if ($depth > $last_depth)
    { if ($last_depth != 0)        # If nested in list, delete previous "</li>".
        $lines_new[$n_new] = substr($lines_new[$n_new], 0, -5);
      for ($i = $last_depth; $i < $depth; $i++)
      { $lines_new[] = $mark_list.$i.'<ul> <li>'; $n_new++; }
      $line_short = substr($line, $ln_mark + strlen($depth) + 4);
      $lines_new[$n_new] .= $line_short; }

    # As depth descends, add "</ul>" and "</li>" as needed.
    elseif ($depth < $last_depth)
    { for ($i = $last_depth - 1; $i >= $depth; $i--)
      { $lines_new[] = $mark_list.$i.'</ul></li>'; $n_new++; }
      $lines_new[] = $line; $n_new++; 
      if (!$is_list)               # If outside list, delete previous "</li>".
        $lines_new[$n_new - 1] =  substr($lines_new[$n_new - 1], 0, -5); }

    else
    { $lines_new[] = $line; $n_new++; }
  $last_depth = $depth; }
  
  # Transform "$mark_list[depth]" line starts into whitespace.
  foreach ($lines_new as $n => $line)
  { $match = preg_match('/^'.$mark_list.'([0-9]+)/', $line, $catch);
    if ($match)
    { $depth = $catch[1];
      $line_short = substr($line, $ln_mark + strlen($depth));
      $whitespace = str_pad('', $depth * 5);
      $lines_new[$n] = $markup_esc.$whitespace.$line_short; } }
  
  # Delete virtual last line added at beginning of function.
  unset($lines[-1]);
  return implode("\n", $lines_new); }

function MarkupParagraphs($text)
# Build paragraphs (& linebreaks therein) from lines not started by $markup_esc.
{ global $markup_esc;
  
  # For line-by-line comparison reasons, add virtual, temporary last line.
  $lines   = explode("\n", $text);
  $lines[] = $markup_esc;

  # Empty p-lines coming before non-empty p-lines? Absorb to latter's paragraph.
  # (Mark every empty line following a non-empty one as a paragraph break.)
  $line_prev = '';
  foreach ($lines as $n => $line)
  { if ($line_prev != '' 
        and  $line == '')
      $lines[$n] = $markup_esc;
    $line_prev = $line; }
  
  # Add '<p>', '</p>' and '<br />' on p-line blocks.
  $lines_new = array();
  $n_new = -1;
  $line_prev = $markup_esc;
  foreach ($lines as $line)
  { if ($line[0] != $markup_esc)           # Line is a p-line.
      if ($line_prev[0] == $markup_esc)      # First p-line, prepend '<p>'.
        $line = '<p>'.$line; 
      else                                   # Later p-line, prepend '<br />'.
        $lines_new[$n_new] .= '<br />';
    elseif ($line_prev[0] != $markup_esc) # Non-p-line after p-line? Add '</p>'.
      $lines_new[$n_new] .= '</p>';
    $line_prev = $line; 
    if ($line != $markup_esc)
    { $lines_new[] = $line; $n_new++; } }

  # Eliminate $markup_esc starts from non-p lines.
  foreach ($lines_new as $n => $line)
    if ($line[0] == $markup_esc)
      $lines_new[$n] = substr($line, 1);

  return implode("\n", $lines_new); }
