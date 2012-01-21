<?php

# PlomWiki plugin "AutoLink"
# Provides autolinks; Action_autolink_admin()

# Language-specific phrases.
$l['AutoLinkAdmin'] = 'AutoLink administration';
$l['AutoLinkBuild'] = 'Build';
$l['AutoLinkDestroy'] = 'Destroy';
$l['AutoLinkNoBuildDB'] = 'Not building AutoLink DB. Directory already exists.';
$l['AutoLinkNoDestroyDB'] = 'Not destroying AutoLink DB. Directory does not exist.';
$l['AutoLinkInvalidDBAction'] = 'Invalid AutoLink DB action.';
$l['AutoLinkToggle'] = 'Toggle AutoLink display';

# AutoLink display toggling.
$l['AutoLinks_show_neg'] = 'yes';
if ('yes' == $_GET['show_autolinks'])
{ $l['AutoLinks_show_neg'] = 'no'; 
  $l['pageview_params'] = $l['pageview_params'].'&amp;show_autolinks=yes'; }

$AutoLink_dir   = $plugin_dir.'AutoLink/';
$hook_WritePage .= '
$x = NewTemp($txt_PluginsTodo);
$y = array();
$y = UpdateAutoLinks($y, $title, $text, $diff_add);
foreach ($y as $task)
  WriteTask($x, $task[0], $task[1]);
$txt_PluginsTodo = file_get_contents($x);
unlink($x);';

##########
# Markup #
##########

function MarkupAutolink($text)
# Autolink $text according to its Autolink file.
{ global $AutoLink_dir, $title;

  # AutoLink display toggling.
  if ('yes' !== $_GET['show_autolinks'])
    return $text;
  
  # Don't do anything if there's no Autolink file for the page displayed.
  $cur_page_file = $AutoLink_dir.$title;
  if (!is_file($cur_page_file))
    return $text; 
  
  # Get $links_out from $cur_page_file, turn into regex from their resp. files.
  $links_out = AutoLink_GetFromFileLine($cur_page_file, 1, TRUE);
  foreach ($links_out as $pagename)
  { $regex_pagename = AutoLink_RetrieveRegexForTitle($pagename);
    
    # Build autolinks into $text where $avoid applies.
    $avoid  = '(?=[^>]*($|<(?!\/(a|script))))';
    $match  = '/('.$regex_pagename.')'.$avoid.'/ieu';
    $titles = array();
    foreach ($links_out as $x)
      if (preg_match('/'.$regex_pagename.'/ieu', $x))
        $titles[] = $x;
    $repl   = 'AutoLink_SetLink("$1", $titles)';
    $text   = preg_replace($match, $repl, $text); }

  return $text; }

function AutoLink_SetLink($string, $titles)
# From $links_out choose best title regex match to $string, return HTML link.
{ global $l, $root_rel;

  # In $titles_ranked, store for each title its levenshtein distance to $string.
  $titles_ranked = array();
  foreach ($titles as $title)
    $titles_ranked[$title] = levenshtein($title, $string);

  # Choose from $titles_ranked $title with lowest levenshtein distance.
  $title = '';
  $last_score = 9000;
  foreach ($titles_ranked as $title_ranked => $score)
    if ($score < $last_score)
    { $title      = $title_ranked;
      $last_score = $score; }

  # Build link.
  return '<a rel="nofollow" style="text-decoration: none;" href="'.$root_rel.
                   '?title='.$title.$l['pageview_params'].'">'.$string.'</a>'; }
#############
# Backlinks #
#############

$l['AutoLinkBacklinks'] = 'AutoLink BackLinks';
$l['AutoLinkNoBacklinks'] = 'No AutoLink backlinks found for this page.';

function AutoLink_Backlinks()
{ global $AutoLink_dir, $l, $esc, $nl, $nl2, $root_rel, $title;

  # Don't do anything if there's no Autolink file for the page displayed
  $cur_page_file = $AutoLink_dir.$title;
  if (!is_file($cur_page_file))
    return; 

  # Build HTML of linked $links_in.
  $links_in = AutoLink_GetFromFileLine($cur_page_file, 2, TRUE);
  foreach ($links_in as $link)
    $backlinks .= '<a rel="nofollow" href="'.$root_rel.'?title='.$link.'">'.$link.'</a> ';

  # $backlinks empty message.
  if (!$links_in)
    $backlinks = $esc.'AutoLinkNoBacklinks'.$esc;
  
  return $nl2.
         '<h2>'.$esc.'AutoLinkBacklinks'.$esc.'</h2>'.$nl.'<p>'.$backlinks.
                                                                       '</p>'; }

####################
# Regex generation #
####################

function BuildRegex($title)
# Generate the regular expression to match $title for autolinking in pages.
{ $umlaut_table = array('äÄ' => array('ae', 'Ae'), 'öÖ' => array('oe', 'Oe'),
                        'üÜ' => array('ue', 'Ue'), 'ß'  => array('ss'));
  foreach ($umlaut_table as $umlaut => $transl)
  { $umlaut_table_sub[$transl[0][0]] = $transl[0];
    $umlaut_table_sub[$transl[1][0]] = $transl[1]; }
  $encoding           = 'UTF-8';
  $minimal_root       = 4;
  $suffix_tolerance   = 3;
  $gaps_to_allow_easy = ' .,:\'';                               # Double symbols
  $gaps_to_allow_hard = array('/', '\\', '(', ')', '[', ']');   # transformed by
  $gaps_to_allow_long = array('&apos;');                        # EscapeHTML().

  # Divide with "!" over hyphens; at digit vs. char; char followed by uppercase.
  $regex = preg_replace(        '/(-+)/',           '!',   $title);
  $regex = preg_replace(   '/([0-9])([A-Za-z])/', '$1!$2', $regex);
  $regex = preg_replace('/([A-Za-z])([0-9])/',    '$1!$2', $regex);
  $regex = preg_replace('/([A-Za-z])(?=[A-Z])/',  '$1!',   $regex);

  # Umlauts to be allowed in the tolerances at regex part ends (see next step).
  $legal_umlauts = '';
  foreach($umlaut_table as $umlaut => $translation)
    $legal_umlauts .= mb_substr($umlaut, 0, 1, $encoding);

  # Build toleration for char additions or even changes, at regex part ends.
  $regex_parts      = explode('!', $regex);
  foreach ($regex_parts as &$part)
  {
    # In non-numerical parts, see if changed ending chars can be tolerated.
    if (strpos('0123456789', $part[0]) === FALSE)
    {
      # $ln_flexible: number of chars in string left after $minimal_root.
      $ln_part       = strlen($part);
      $ln_static     = min($minimal_root, $ln_part);
      $minimal_root -= $ln_static;
      $ln_flexible   = $ln_part - $ln_static;

      # $replace_tolerance: largest-possible mirror of $suffix_tolerance that
      # its into $ln_flexible and is not larger than 1/3 of $ln_part.
      $replace_tolerance = $suffix_tolerance;
      while ($replace_tolerance > 0)
      { if (    ($ln_flexible >= $replace_tolerance) 
            and ($ln_part >= 2 * $replace_tolerance))
        { $part = substr($part, 0, -$replace_tolerance);
          break; }
        $replace_tolerance--; }

      # What if cut-off is inside an umlaut translation? Identify all potential
      # cut-off umlaut translations, replace with respective full versions.
      $last_char = substr($part, -1);
      foreach ($umlaut_table_sub as $char => $umlaut)
        if ($last_char == $char)
          $part = substr($part, 0, -1).$umlaut;

      # To a possibly reduced $part, add tolerance => $suffix_tolerance.
      $tolerance_sum = min($ln_part, ($replace_tolerance + $suffix_tolerance));
      $part .= '([a-z'.$legal_umlauts.'\']|&apos;){0,'.$tolerance_sum.'}'; }

    # In a numerical $part, just add tolerance of $suffix_tolerance size.
    else $part .= '([a-z'.$legal_umlauts.'\']|&apos;){0,'.$suffix_tolerance.'}'; }

  # $gaps_to_allow: glue for $regex_parts. Integrate $gaps_to_allow_easy as is,
  # $...hard with escape chars and $...long with their own "or" parantheses.
  $gaps_to_allow = $gaps_to_allow_easy;
  foreach ($gaps_to_allow_hard as $char)
    $gaps_to_allow .= '\\'.$char;
  $gaps_to_allow = '['.$gaps_to_allow.'\-]';
  if (!empty($gaps_to_allow_long))
    $gaps_to_allow =
          '(('.implode(')|(', $gaps_to_allow_long).')|'.$gaps_to_allow.')';
  $regex = implode($gaps_to_allow.'*', $regex_parts);

  # Make regexes umlaut-cognitive according to $umlaut_table.
  foreach ($umlaut_table as $umlaut => $transl)
  { 
    # Slice uppercase and lowercase versions off $umlaut *multibyte-compatibly*.
    $umlaut_lower = mb_substr($umlaut, 0, 1, $encoding);
    $umlaut_upper = mb_substr($umlaut, 1, 1, $encoding);

    # For any multi-char umlaut translation, also allow a first-char version.
    $transl_lower = $transl[0];
    $transl_upper = $transl[1];
    if (strlen($transl_lower) > 1) 
                             $transl_lower = $transl_lower.'|'.$transl_lower[0];
    if (strlen($transl_upper) > 1) 
                             $transl_upper = $transl_upper.'|'.$transl_upper[0];

    # Replace "ae" etc. with "(ä|ae)" etc. Check for uppercase versions.
    $regex = str_replace($transl[0], '('.$transl_lower.'|'.$umlaut_lower.')', 
                                                                        $regex);
    if ($umlaut_upper != '')
      $regex = str_replace($transl[1], '('.$transl_upper.'|'.$umlaut_upper.')',
                                                                      $regex); }

  return $regex; }

####################################
# DB updating / building / purging #
####################################

function UpdateAutoLinks($t, $title, $text, $diff)
# Add to task list $t AutoLink DB update tasks. $text, $diff determine change.
{ global $AutoLink_dir, $nl;

  # Silently fail if AutoLink DB directory does not exist.
  if (!is_dir($AutoLink_dir)) return $t;

  # Some needed variables.
  $cur_page_file = $AutoLink_dir.$title;
  $all_other_titles = array_diff(GetAllPageTitles(), array($title));

  # Page creation demands new file, going through all pages for new AutoLinks.
  if (!is_file($cur_page_file))
  { $t[] = array('AutoLink_CreateFile', array($title));
    foreach ($all_other_titles as $linkable)
    { $t[] = array('AutoLink_TryLinking', array($title, $linkable));
      $t[] = array('AutoLink_TryLinking', array($linkable, $title)); } }

  else
  { $links_out  = AutoLink_GetFromFileLine($cur_page_file, 1, TRUE);

    # Page deletion severs links between files before $cur_page_file deletion.
    if ($text == 'delete')
    { foreach ($links_out as $pagename)
        $t[] = array('AutoLink_ChangeLine', array($pagename, 2, 'out', $title));
      $links_in = AutoLink_GetFromFileLine($cur_page_file, 2, TRUE);
      foreach ($links_in as $pagename)
        $t[] = array('AutoLink_ChangeLine', array($pagename, 1, 'out', $title));
      $t[] = array('unlink', array($cur_page_file)); }

    # For mere page change, determine tasks comparing $diff against $links_out.
    else
    { # Divide $diff into $diff_del / $diff_add: lines deleted / added.
      $lines = explode($nl, $diff);
      $diff_del = array(); $diff_add = array();
      foreach ($lines as $line)
      if     ($line[0] == '<') $diff_del[] = substr($line, 1);
      elseif ($line[0] == '>') $diff_add[] = substr($line, 1);
  
      # Compare unlinked titles' regexes against $diff_add: harvest $links_new.
      $links_new = array();
      $not_linked = array_diff($all_other_titles, $links_out);
      foreach (AutoLink_TitlesInLines($not_linked, $diff_add) as $pagename)
        $links_new[] = $pagename;
      $t = AutoLink_TasksLinksInOrOut($t, 'in', $title, $links_new);
 
      # Threaten $links_out by matches in $diff_del. Remove threat if regexes
      # still matched in $diff_add or whole page $text. Else remove link_out.
      $links_rm = array();
      foreach (AutoLink_TitlesInLines($links_out, $diff_del) as $pagename)
        $links_rm[] = $pagename;
      foreach (AutoLink_TitlesInLines($links_rm, $diff_add) as $pagename)
        $links_rm = array_diff($links_rm, array($pagename)); 
      $lines_text = explode($nl, $text);
      foreach (AutoLink_TitlesInLines($links_rm, $lines_text) as $pagename)
        $links_rm = array_diff($links_rm, array($pagename));
      $t = AutoLink_TasksLinksInOrOut($t, 'out', $title, $links_rm); } }

  return $t; }

function Action_autolink_admin()
{ global $l, $AutoLink_dir, $esc, $nl;

  # Offer building or purging of DB, dependant on existence of $AutoLink_dir.
  if (!is_dir($AutoLink_dir)) $do_what = 'Build';
  else                        $do_what = 'Destroy';

  # Final HTML.
  $input = '<p>'.$esc.'AutoLink'.$do_what.$esc.' AutoLink DB?</p>'.$nl.
           '<input name="auth" type="hidden" value="*" />'.$nl.
           '<input type="hidden" name="do_what" value="'.$do_what.'" />';
  $form = BuildPostForm($root_rel.'?action=write&amp;t=autolink_admin', $input);
  $l['title'] = $esc.'AutoLinkAdmin'.$esc; $l['content'] = $form; 
  OutputHTML(); }

function PrepareWrite_autolink_admin()
{ global $AutoLink_dir, $esc, $nl, $root_rel, $todo_urgent;
  $action = $_POST['do_what'];

  if ('Build' == $action)
  { 
    # Abort if $AutoLink_dir found, else prepare task to create it.
    if (is_dir($AutoLink_dir))
      ErrorFail($esc.'AutoLinkNoBuildDB'.$esc);
    $x['tasks'][$todo_urgent][] = array('mkdir', array($AutoLink_dir));

    # Build page file creation, linking tasks.
    $titles = GetAllPageTitles();
    $string = '';
    foreach ($titles as $title)
    { $x['tasks'][$todo_urgent][] = array('AutoLink_CreateFile', array($title));
      $x['tasks'][$todo_urgent][] = array('AutoLink_TryLinkingAll', 
                                          array($title)); } }

  elseif ('Destroy' == $action)
  {
    # Abort if $AutoLink_dir found, else prepare task to create it.
    if (!is_dir($AutoLink_dir))
      ErrorFail($esc.'AutoLinkNoDestroyDB'.$esc);
  
    # Add unlink(), rmdir() tasks for $AutoLink_dir and its contents.
    $p_dir = opendir($AutoLink_dir);
    while (FALSE !== ($fn = readdir($p_dir)))
      if (is_file($AutoLink_dir.$fn))
        $x['tasks'][$todo_urgent][] = array('unlink', array($AutoLink_dir.$fn));
    closedir($p_dir); 
    $x['tasks'][$todo_urgent][] = array('rmdir', array($AutoLink_dir)); }

  else
    ErrorFail($esc.'AutoLinkInvalidDBAction'.$esc);

  return $x; }

##########################################
# DB writing tasks to be called by todo. #
##########################################

function AutoLink_CreateFile($title)
# Start AutoLink file of page $title, empty but for title regex.
{ global $AutoLink_dir, $nl2;

  $path    = $AutoLink_dir.$title;
  $content = BuildRegex($title).$nl2;
  $temp    = NewTemp();
  if (!is_file($path))
  { file_put_contents($temp, $content);
    rename($temp, $path); } }

function AutoLink_TryLinking($title, $linkable)
# $titles = $title_$linkable. Try auto-linking both pages, write to their files.
{ global $AutoLink_dir, $nl, $pages_dir;

  $page_txt       = file_get_contents($pages_dir.$title);
  $regex_linkable = AutoLink_RetrieveRegexForTitle($linkable);
  if (preg_match('/'.$regex_linkable.'/iu', $page_txt))
  { AutoLink_ChangeLine($title, 1, 'in', $linkable);
    AutoLink_ChangeLine($linkable, 2, 'in', $title); } }

function AutoLink_TryLinkingAll($title)
{ global $legal_title, $AutoLink_dir; 

  $titles = array();
  $p_dir = opendir($AutoLink_dir);
  while (FALSE !== ($fn = readdir($p_dir)))
    if (is_file($AutoLink_dir.$fn) and preg_match('/^'.$legal_title.'$/', $fn))
      $titles[] = $fn;
  closedir($p_dir); 

  foreach ($titles as $linkable)
    if ($linkable != $title)
    { AutoLink_TryLinking($title, $linkable); 
      AutoLink_TryLinking($linkable, $title); } }

function AutoLink_ChangeLine($title, $line_n, $action, $diff)
# On $title's AutoLink file, on $line_n, move $diff in/out according to $action.
{ global $AutoLink_dir, $nl;
  $path = $AutoLink_dir.$title;

  # Do $action with $diff on $title's file $line_n. Re-sort line for "in".
  $lines          = explode($nl, file_get_contents($path));
  $strings        = explode(' ', $lines[$line_n]);
  if     ($action == 'in')
  { if (!in_array($diff, $strings))
      $strings[]  = $diff;
    usort($strings, 'AutoLink_SortByLengthAlphabetCase'); }
  elseif ($action == 'out')
    $strings      = array_diff($strings, array($diff));  
  $new_line       = implode(' ', $strings);
  $lines[$line_n] = rtrim($new_line);
  $content        = implode($nl, $lines);

  $path_temp = NewTemp($content);
  SafeWrite($path, $path_temp); }

##########################
# Minor helper functions #
##########################

function AutoLink_GetFromFileLine($path, $line_n, $return_as_array = FALSE)
# Return $line_n of file $path. $return_as_array string separated by ' ' if set.
# From empty lines, explode() generates $x = array(''); return array() instead.
{ global $nl;
  $x = explode($nl, file_get_contents($path));
  $x = $x[$line_n];
  if ($return_as_array)
    $x = explode(' ', $x);
    if ($x == array(''))
      return array();
  return $x; }

function AutoLink_SortByLengthAlphabetCase($a, $b)
# Try to sort by stringlength, then follow sort() for uppercase vs. lowercase.
{ $strlen_a = strlen($a);
  $strlen_b = strlen($b);
  if     ($strlen_a < $strlen_b) return  1;
  elseif ($strlen_a > $strlen_b) return -1;

  $sort = array($a, $b);
  sort($sort);
  if ($sort[0] == $a) return -1;
  else                return  1; }

function AutoLink_RetrieveRegexForTitle($title)
# Return regex matching $title according to its AutoLink file.
{ global $AutoLink_dir;
  $AutoLink_file = $AutoLink_dir.$title;
  $regex = AutoLink_GetFromFileLine($AutoLink_file, 0);
  return $regex; }

function AutoLink_TitlesInLines($titles, $lines)
# Return array of all $titles whose AutoLink regex matches $lines.
{ $titles_new = array();
  foreach ($titles as $title)
  { $regex = AutoLink_RetrieveRegexForTitle($title);
    foreach ($lines as $line)
      if (preg_match('/'.$regex.'/iu', $line))
      { $titles_new[] = $title;
        break; } }
  return $titles_new; }

function AutoLink_TasksLinksInOrOut($tasks, $dir, $title, $titles)
# Add $tasks of moving $titles $dir ('in'/'out') of line 1 in $title's AutoLink
# file and $title $dir ('in'/'out')of line 2 in $titles' AutoLink files.
{ foreach ($titles as $pagename)
  { $tasks[] = array('AutoLink_ChangeLine', array($title, 1, $dir, $pagename));
    $tasks[] = array('AutoLink_ChangeLine', array($pagename, 2, $dir, $title));}
  return $tasks; }
