<?php

$AutoLink_dir   = $plugin_dir.'AutoLink/';

# Plugin hooks.
$actions_meta[] = array('AutoLink administration', '?action=autolink_admin');
$hook_PrepareWrite_page .= '$x[\'tasks\'] = UpdateAutoLinks($x[\'tasks\'], '.
                                                           '$text, $diff_add);';
$hook_Action_page_view  .= '$text .= AutoLink_Backlinks(); ';

##########
# Markup #
##########

function MarkupAutolink($text)
# Autolink $text according to its Autolink file.
{ global $AutoLink_dir, $title;
  
  # Don't do anything if there's no Autolink file for the page displayed
  $cur_page_file = $AutoLink_dir.$title;
  if (!is_file($cur_page_file))
    return $text; 
  
  # Get $links_out from $cur_page_file, turn into regex from their resp. files.
  $links_out = AutoLink_GetFromFileLine($cur_page_file, 1, TRUE);
  foreach ($links_out as $pagename)
  { $regex_pagename = AutoLink_RetrieveRegexForTitle($pagename);
    
    # Build autolinks into $text where $avoid applies.
    $avoid = '(?=[^>]*($|<(?!\/(a|script))))';
    $match = '/('.$regex_pagename.')'.$avoid.'/ieu';
    $repl  = 'AutoLink_SetLink("$1", $links_out)';
    $text  = preg_replace($match, $repl, $text); }
  
  return $text; }

function AutoLink_SetLink($string, $titles)
# From $links_out choose best title regex match to $string, return HTML link.
{ global $root_rel; 

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
  return '<a class="autolink" href="'.$root_rel.'?title='.$title.'">'.$string.
                                                                       '</a>'; }
#############
# Backlinks #
#############

function AutoLink_Backlinks()
{ global $AutoLink_dir, $nl2, $root_rel, $title;

  # Don't do anything if there's no Autolink file for the page displayed
  $cur_page_file = $AutoLink_dir.$title;
  if (!is_file($cur_page_file))
    return; 

  # Build HTML of linked $links_in.
  $links_in = AutoLink_GetFromFileLine($cur_page_file, 2, TRUE);
  foreach ($links_in as $link)
    $backlinks .= '<a href="'.$root_rel.'?title='.$link.'">'.$link.'</a> ';

  # $backlinks empty message.
  if (!$links_in)
    $backlinks = 'No AutoLink backlinks found for this page.';
  
  return $nl2.'<h2>AutoLink Backlinks</h2>'.$nl2.'<p>'.$backlinks.'</p>'; }

####################
# Regex generation #
####################

function BuildRegex($title)
# Generate the regular expression to match $title for autolinking in pages.
{ $umlaut_table = array('äÄ' => array('ae', 'Ae'), 'öÖ' => array('oe', 'Oe'),
                        'üÜ' => array('ue', 'Ue'), 'ß'  => array('ss'));
  $encoding           = 'UTF-8';
  $minimal_root       = 4;
  $suffix_tolerance   = 3;
  $gaps_to_allow_easy = ' .,:;';
  $gaps_to_allow_hard = array('\'', '/', '\\', '(', ')', '[', ']');
  $gaps_to_allow_long = array();

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

      # To a possibly reduced $part, add tolerance => $suffix_tolerance.
      $tolerance_sum = min($ln_part, ($replace_tolerance + $suffix_tolerance));
      $part .= '[a-z'.$legal_umlauts.']{0,'.$tolerance_sum.'}'; }

    # In a numerical $part, just add tolerance of $suffix_tolerance size.
    else $part .= '[a-z'.$legal_umlauts.']{0,'.$suffix_tolerance.'}'; }

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

function UpdateAutoLinks($t, $text, $diff)
# Add to task list $t AutoLink DB update tasks. $text, $diff determine change.
{ global $AutoLink_dir, $nl, $title;

  # Silently fail if AutoLink DB directory does not exist.
  if (!is_dir($AutoLink_dir)) return $t;

  # Some needed variables.
  $cur_page_file = $AutoLink_dir.$title;
  $all_other_titles = array_diff(GetAllPageTitles(), array($title));

  # Page creation demands new file, going through all pages for new AutoLinks.
  if (!is_file($cur_page_file))
  { $t[] = array('AutoLink_CreateFile', $title);
    foreach ($all_other_titles as $linkable)
    { $t[] = array('AutoLink_TryLinking', $title.'_'.$linkable);
      $t[] = array('AutoLink_TryLinking', $linkable.'_'.$title); } }

  else
  { $links_out  = AutoLink_GetFromFileLine($cur_page_file, 1, TRUE);

    # Page deletion severs links between files before $cur_page_file deletion.
    if ($text == 'delete')
    { foreach ($links_out as $pagename)
        $t[] = array('AutoLink_ChangeLine', $pagename.'_2_out_'.$title);
      $links_in = AutoLink_GetFromFileLine($cur_page_file, 2, TRUE);
      foreach ($links_in as $pagename)
        $t[] = array('AutoLink_ChangeLine', $pagename.'_1_out_'.$title);
      $t[] = array('unlink', $cur_page_file); }

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
{ global $AutoLink_dir, $nl;

  # Offer building or purging of DB, dependant on existence of $AutoLink_dir.
  if (!is_dir($AutoLink_dir))
  { $msg   = 'Build AutoLink database?';
    $button = 'Build'; }
  else
  { $msg   = 'Destroy AutoLink database?';
    $button = 'Destroy'; }

  # Final HTML.
  $title_h = 'AutoLink administration';
  $form    = '<form method="post" action="'.$root_rel.'?action=write&amp;t='.
                                                         'autolink_admin">'.$nl.
             '<p>'.$msg.'<p>'.$nl.
             '<p>Admin password: <input type="password" name="pw" /><input type'
                      .'="submit" name="action" value="'.$button.'" /></p>'.$nl.
             '</form>';
  Output_HTML($title_h, $form); }

function PrepareWrite_autolink_admin()
{ global $AutoLink_dir, $nl, $root_rel, $todo_urgent;
  $action = $_POST['action'];
  $x['todo'] = $todo_urgent;
  $x['msg']  = '<p>'.$action.'ing AutoLink database.</p>';

  if ('Build' == $action)
  { 
    # Abort if $AutoLink_dir found, else prepare task to create it.
    if (is_dir($AutoLink_dir))
      ErrorFail('Not building AutoLink DB.', 
                'Directory already exists. <a href="'.$root_rel.
                                     '?action=autolink_destroy_db">Purge?</a>');
    $x['tasks'][] = array('mkdir', $AutoLink_dir);

    # Build page file creation, linking tasks.
    $titles = GetAllPageTitles();
    foreach ($titles as $title)
      $x['tasks'][] = array('AutoLink_CreateFile', $title);
    foreach ($titles as $title)
      foreach ($titles as $linkable)
        if ($linkable != $title)
          $x['tasks'][] = array('AutoLink_TryLinking', $title.'_'.$linkable); }

  elseif ('Destroy' == $action)
  {
    # Abort if $AutoLink_dir found, else prepare task to create it.
    if (!is_dir($AutoLink_dir))
      ErrorFail('Not destroying AutoLink DB.', 'Directory does not exist.');
  
    # Add unlink(), rmdir() tasks for $AutoLink_dir and its contents.
    $p_dir = opendir($AutoLink_dir);
    while (FALSE !== ($fn = readdir($p_dir)))
      if (is_file($AutoLink_dir.$fn))
        $x['tasks'][] = array('unlink', $AutoLink_dir.$fn);
    closedir($p_dir); 
    $x['tasks'][] = array('rmdir', $AutoLink_dir); }

  else
    ErrorFail('Invalid AutoLink DB action.');

  return $x; }

##########################################
# DB writing tasks to be called by todo. #
##########################################

function AutoLink_CreateFile($title)
# Start AutoLink file of page $title, empty but for title regex.
{ global $AutoLink_dir, $nl2;
  $path    = $AutoLink_dir.$title;
  $content = BuildRegex($title).$nl2;
  AutoLink_SendToSafeWrite($path, $content); }

function AutoLink_TryLinking($input_string)
# $titles = $title_$linkable. Try auto-linking both pages, write to their files.
{ global $AutoLink_dir, $nl, $pages_dir;

  # Get $title, $linkable from $input_string. (Hack around WriteTasks().)
  list($title, $linkable) = explode('_', $input_string);

  $page_txt       = file_get_contents($pages_dir.$title);
  $regex_linkable = AutoLink_RetrieveRegexForTitle($linkable);
  if (preg_match('/'.$regex_linkable.'/iu', $page_txt))
  { AutoLink_ChangeLine($title.'_1_in_'.$linkable);
    AutoLink_ChangeLine($linkable.'_2_in_'.$title); } }

function AutoLink_ChangeLine($input_string)
# On $title's AutoLink file, on $line_n, move $diff in/out according to $action.
{ global $AutoLink_dir, $nl;

  # Get variables from exploded input string. (Hack around WriteTasks().)
  list($title, $line_n, $action, $diff) = explode('_', $input_string);
  $path = $AutoLink_dir.$title;

  # Do $action with $diff on $title's file $line_n. Re-sort line for "in".
  $lines          = explode($nl, file_get_contents($path));
  $strings        = explode(' ', $lines[$line_n]);
  if     ($action == 'in')
  { $strings[]    = $diff;
    usort($strings, 'AutoLink_SortByLengthAlphabetCase'); }
  elseif ($action == 'out')
    $strings      = array_diff($strings, array($diff));  
  $new_line       = implode(' ', $strings);
  $lines[$line_n] = rtrim($new_line);
  $content        = implode($nl, $lines);

  AutoLink_SendToSafeWrite($path, $content); }

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
  { $tasks[] = array('AutoLink_ChangeLine', $title.'_1_'.$dir.'_'.$pagename);
    $tasks[] = array('AutoLink_ChangeLine', $pagename.'_2_'.$dir.'_'.$title); }
  return $tasks; }

function AutoLink_SendToSafeWrite($path, $content)
# Call SafeWrite() not on $content directly, but on newly built temp file of it.
{ $path_temp= NewTempFile($content);
  SafeWrite($path, $path_temp); }
