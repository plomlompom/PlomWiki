<?php
# PlomWiki plugin: StandardMarkup. Provides default markup functions.
# 
# Copyright 2010-2012 Christian Heller / <http://www.plomlompom.de/>
# License: AGPLv3 or any later version. See file LICENSE for details.

$s = ReadStringsFile($plugin_strings_dir.'StandardMarkup', $s);

# Escape marks. Remember $esc is stripped from any page texts.
# A line starting with $esc escapes paragraphing by MarkupParagraphs().
# $esc_{on,off,store} are used by MarkupEscape() and MarkupUnescape()
# for the user-defined markup escaping via "[=...=]".
$esc_on    = '['.$esc;
$esc_off   = $esc.']';
$esc_store = array();

##################
# In-line markup #
##################

function MarkupLinks($text) {
# [[LinkedPagename]], [[Linked|Text displayed]], [[http://example.com]].
  global $esc, $nl, $s, $legal_title, $pages_dir;
  $legal_url = '(http|https|ftp):([A-Za-z0-9\.\-_~:/\?#\[\]@!\$&\'\(\)'.
               '\*\+,;=]|%[A-Fa-f0-9]{2})+';    # Stolen from erlehmann.
  $regex     = '/\[\[([^'.$nl.']+?)]]/';
  $esc_store = array();
  $esc_off   = $esc.'}';
  $esc_on    = '{'.$esc;
  $esc_n     = 0;

  # Go through potential linking markup, decide with what to replace it.
  preg_match_all($regex, $text, $store);
  $store = $store[1];
  foreach ($store as $string) {
    $old  = '[['.$string.']]';

    # Separate $string into $to_link & $desc, even if they're the same.
    $pos_sep = strpos($string, '|');
    if ($pos_sep) {
      $to_link = substr($string, 0, $pos_sep);
      $desc    = substr($string, $pos_sep + 1); }
    else
      $to_link = $desc = $string;

    # Allow for whitespace before and after $legal_url in $to_link.
    if (preg_match('{^ *'.$legal_url.' *$}', $to_link))
      $to_link = str_replace(' ', '', $to_link);

    # Try to force $to_link into $legal_title format if not URL-like.
    $gaps    = array('&apos;', '&quot;', '&amp;', ';', ':', '\\', '/',
                     ',', '.', ' ', '?', '!');
    $umlauts = array(array('Ä','Ae'), array('Ö','Oe'), array('Ü','Ue'),
                     array('ä','ae'), array('ö','oe'), array('ü','ue'), 
                     array('ß','ss'));
    if (!preg_match('{^'.$legal_url.'$}', $to_link)) {
      foreach ($umlauts as $umlaut) 
        $to_link = str_replace($umlaut[0], $umlaut[1], $to_link);
      foreach ($gaps as $gap)
        $to_link = str_replace($gap, ' ', $to_link);
      $to_link=preg_replace_callback('/ ([a-z])/',
        function($match) { return strtoupper($match[1]); },
        $to_link);
      $to_link=str_replace(' ', '', $to_link); }

    # Try collecting from $to_link HTML link parameters.
    $link = TRUE;
    $page = FALSE;
    if (preg_match('{^'.$legal_url.'$}', $to_link))
      $url  = $to_link;
    elseif (preg_match('/^'.$legal_title.'$/', $to_link))
      $page = $to_link;
    else
      $link = FALSE;

    # If $link, build HTML link to replace markup; else, leave text 
    # unchanged. Don't replace now; place markers to replace later.
    $style = FALSE;
    if ($link) {
      if ($page) {
        if (!is_file($pages_dir.$page)) 
          $style = 'style="color: red;" ';
        $url = $s['title_root'].$page; }
      $repl        = '<a '.$style.'href="'.$url.'">'.$desc.'</a>'; 
      $esc_store[] = $repl;
      $repl        = $esc_on.$esc_n.$esc_off;
      $esc_n++; }
    else
      $repl = $old;
    $text = str_replace($old, $repl, $text); }

  # Link URLs outside of linking markup.
  $text = preg_replace('{('.$legal_url.')}',
                       '<a style="text-decoration: none;" href="$1">$1'.
                                                                 '</a>',
                       $text);

  # Replace linking markup markers with HTML link strings.
  foreach ($esc_store as $esc_n => $string)
    $text = str_replace($esc_on.$esc_n.$esc_off, $string, $text);

  return $text; }

function MarkupStrong($text) {
# "[*This*]" becomes "<strong>This</strong>", if not broken by newlines.
  return preg_replace('/\[\*(.*?)\*]/', '<strong>$1</strong>', $text); }

function MarkupEmphasis($text) {
# "[/This/]" becomes "<em>This</em>", if not broken by newlines.
  return preg_replace('/\[\/(.*?)\/]/', '<em>$1</em>',         $text); }

function MarkupDeleted($text) {
# "[-This-]" becomes "<del>This</del>", if not broken by newlines.
  return preg_replace('/\[-(.*?)-]/',   '<del>$1</del>',       $text); }

function MarkupEscape($text) {
# Replace "[=This"=]" with $esc_{on,off}-aped key to "This" in $escaped.
  global $esc_off, $esc_on, $esc_store;
  $regex = '/\[=(.*?)=]/';

  # Catch all escaped strings in $store_tmp.
  $store_tmp = array();
  preg_match_all($regex, $text, $store_tmp);
  $store_tmp = $store_tmp[1];

  # Replace escs in $text with placeholders for/keys to escaped strings.
  $offset = count($esc_store);
  foreach ($store_tmp as $n => $string) {
    $n   += $offset;
    $repl = $esc_on.$n.$esc_off;
    $text = preg_replace($regex, $repl, $text, $limit = 1); }

  # Add $store_tmp to markup_esc_store.
  foreach ($store_tmp as $add)
    $esc_store[] = $add;

  return $text; }

function MarkupUnescape($text) {
# Replace all "[\r]" with the string originally escaped.
  global $esc_off, $esc_on, $esc_store;

  foreach ($esc_store as $n => $string)
    $text = str_replace($esc_on.$n.$esc_off, $string, $text);

  foreach ($esc_store as $n => $string)
    $text = str_replace($esc_on.$n.$esc_off, $string, $text);

  return $text; }

function MarkupHeadings($text) {
# !] => <h6>...</h6>, !!] => <h5>...</h5>, !!!!!!] => <h1>...</h1>.
  global $esc, $nl;
  $text = preg_replace('/(^|'.$nl.')!!!!!!] (.+)($|'.$nl.')/',
                                    '$1'.$esc.'<h1>$2</h1>$3', $text);
  $text = preg_replace('/(^|'.$nl.')!!!!!] (.+)($|'.$nl.')/',
                                    '$1'.$esc.'<h2>$2</h2>$3', $text);
  $text = preg_replace('/(^|'.$nl.')!!!!] (.+)($|'.$nl.')/',
                                    '$1'.$esc.'<h3>$2</h3>$3', $text);
  $text = preg_replace('/(^|'.$nl.')!!!] (.+)($|'.$nl.')/',
                                    '$1'.$esc.'<h4>$2</h4>$3', $text);
  $text = preg_replace('/(^|'.$nl.')!!] (.+)($|'.$nl.')/',     
                                    '$1'.$esc.'<h5>$2</h5>$3', $text);
  return  preg_replace('/(^|'.$nl.')!] (.+)($|'.$nl.')/',
                                    '$1'.$esc.'<h6>$2</h6>$3', $text); }

#####################
# Multi-line markup #
#####################

function MarkupCode($text) {
# <pre>-format multi-line code block.
  global $nl, $esc;
  $line_start = '^|'.$nl;
  $line_end   = '$|'.$nl;
  $regex      = '\[@(.*?)@]';
  
  # For further modification, temporarily save marked up code in $store.
  $store = array();
  preg_match_all('/('.$line_start.')'.$regex.'('.$line_end.')/s', 
                 $text, $store);
  $store = $store[2];

  # Escape $store'd lines. Replace marked up code with these, <pre>-
  # format it. Solve some problems with symbols that are dangerous or
  # confuse formatting.
  foreach ($store as $pre) {
    $pre  = str_replace('$', $esc.'dollar'.$esc, $pre);
    $pre  = str_replace('\\', $esc.'backslash'.$esc, $pre);
    $pre  = preg_replace('/(?<='.$line_start.')(.*?)(?='.$line_end.')/', 
                         $esc.'$1 ', $pre);
    $pre  = preg_replace('/^'.$esc.' '.$nl.$esc.'/', $esc, $pre);
    $pre  = preg_replace('/'.$esc.' $/', '', $pre);
    $text = preg_replace('/(?<='.$line_start.')'.$regex.'(?='.$line_end.
                                                                  ')/s', 
                         $esc.'<pre>'.$nl.$pre.$nl.$esc.'</pre>', 
                         $text, $limit = 1); 
    $text = str_replace($esc.'backslash'.$esc, '\\', $text);
    $text = str_replace($esc.'dollar'.$esc, '$', $text); }
  
  return $text; }

function MarkupLists($text) {
# Lists: Lines started with '*] ' preceded by multiples of 2*whitespace.
  global $nl, $esc;
  $li_on     = '*] '; 
  $lines     = explode($nl, $text);
  $lines[]   = '';# Add last one for backwards line-by-line comparisons.
  $mark_list = $esc.'l'.$esc;
  $ln_mark   = strlen($mark_list);

  # Find lines marked as list elements. Search up to $failed_tries_limit
  # depth. Transform any line identified into
  # "$mark_list[depth]<li>[line text]</li>".
  $failed_tries_limit = 10;
  $ln_li_on           = strlen($li_on); 
  $depth              = 1;
  while ($failed_tries <= $failed_tries_limit) {
    $failed_tries++;
    foreach ($lines as $n => $line) {
      $line_start = substr($line, 0, $ln_li_on);
      if ($line_start == $li_on) {
        $failed_tries = 0;
        $line_end     = substr($line, $ln_li_on);
        $lines[$n]    = $mark_list.$depth.'<li>'.$line_end.'</li>'; } }
    $li_on    = '  '.$li_on; 
    $ln_li_on = strlen($li_on); 
    $depth++; }

  # Nest lists elements into "<ul>"/"</ul>" by depth number differences.
  $lines_new  = array();
  $n_new      = -1;
  $last_depth = 0;           # $depth = 0 is assumed for non-list lines.
  foreach ($lines as $line) {
    $depth   = 0;
    $is_list = FALSE;
    if (substr($line, 0, $ln_mark) == $mark_list) {
      $is_list = preg_match('/^'.$mark_list.'([0-9]+)/', $line, $catch);
      $depth   = $catch[1]; }
      
    # As depth ascends, add "<ul>" and "<li>" as needed.
    if ($depth > $last_depth) {
      if ($last_depth != 0) # If nested in list, delete earlier "</li>".
        $lines_new[$n_new] = substr($lines_new[$n_new], 0, -5);
      for ($i = $last_depth; $i < $depth; $i++) {
        $lines_new[] = $mark_list.$i.'<ul> <li>';
        $n_new++; }
      $line_short = substr($line, $ln_mark + strlen($depth) + 4);
      $lines_new[$n_new] .= $line_short; }

    # As depth descends, add "</ul>" and "</li>" as needed.
    elseif ($depth < $last_depth) {
      for ($i = $last_depth - 1; $i >= $depth; $i--) {
        $lines_new[] = $mark_list.$i.'</ul></li>';
        $n_new++; }
      $lines_new[] = $line;
      $n_new++; 
      if (!$is_list)          # If outside list, delete earlier "</li>".
        $lines_new[$n_new - 1] =  substr($lines_new[$n_new-1], 0, -5); }

    else {
      $lines_new[] = $line;
      $n_new++; }
  $last_depth = $depth; }
  
  # Transform "$mark_list[depth]" line starts into whitespace.
  foreach ($lines_new as $n => $line) {
    $match = preg_match('/^'.$mark_list.'([0-9]+)/', $line, $catch);
    if ($match) {
      $depth         = $catch[1];
      $line_short    = substr($line, $ln_mark + strlen($depth));
      $whitespace    = str_pad('', $depth * 5);
      $lines_new[$n] = $esc.$whitespace.$line_short; } }
  
  # Delete virtual last line added at beginning of function.
  unset($lines[-1]);
  return implode($nl, $lines_new); }

function MarkupParagraphs($text) {
# Build paragraphs (& linebreaks therein) from lines not $esc-started.
  global $nl, $esc;
  
  # For line-by-line comparison reasons, add temporary last line.
  $lines   = explode($nl, $text);
  $lines[] = $esc;

  # Empty p-lines coming before non-empty p-lines? Absorb to latter's
  #  paragraph. (Mark every empty line following a non-empty one as a
  # paragraph break.)
  $line_prev = '';
  foreach ($lines as $n => $line) {
    if ($line_prev != '' and  $line == '')
      $lines[$n] = $esc;
    $line_prev = $line; }
  
  # Add '<p>', '</p>' and '<br />' on p-line blocks.
  $lines_new = array();
  $n_new = -1;
  $line_prev = $esc;
  foreach ($lines as $line) {
    if ($line[0] != $esc)           # Line is a p-line.
      if ($line_prev[0] == $esc)      # First p-line, prepend '<p>'.
        $line = '<p>'.$line; 
      else                                   # Later p-line, prepend 
        $lines_new[$n_new] .= '<br />';      # '<br />'.
    elseif ($line_prev[0] != $esc) # Non-p-line after p-line? Add '</p>'
      $lines_new[$n_new] .= '</p>';
    $line_prev = $line; 
    if ($line != $esc) {
      $lines_new[] = $line; $n_new++; } }

  # Eliminate $esc starts from non-p lines.
  foreach ($lines_new as $n => $line)
    if ($line[0] == $esc)
      $lines_new[$n] = substr($line, 1);

  return implode($nl, $lines_new); }