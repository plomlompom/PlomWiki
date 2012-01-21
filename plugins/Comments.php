<?php

# PlomWiki plugin Comments
# Provides comments; Action_comments_admin(), Action_RecentComments()

# Language-specific variables.
$l['CommentsAdmin'] = 'Comments administration';
$l['RecentComments'] = 'RecentComments';
$l['Comments'] = 'Comments';
$l['NoComments'] = 'No one commented on this page yet.';
$l['CommentsWrite'] = 'Write your own comment';
$l['CommentsWriteNo'] = 'Comment writing currently not possible: captcha not set.';
$l['CommentsName'] = 'Your name';
$l['CommentsURL'] = 'Your URL';
$l['CommentsAskCaptcha'] = 'Captcha password needed! Write';
$l['CommentsNoAuthor'] = 'Author field empty.';
$l['CommentsNoText'] = 'No comment written.';
$l['CommentsAuthorMax'] = 'Author name must not exceed length (characters/bytes)';
$l['CommentsURLMax'] = 'URL must not exceed length (characters/bytes)';
$l['CommentsTextMax'] = 'Text must not exceed length (characters/bytes)';
$l['CommentsInvalidURL'] = 'Invalid URL format.';
$l['CommentsRecentNo'] = 'No RecentComments file found.';
$l['CommentsNoDir'] = 'Comments directory not yet built.';
$l['BuildCommentsDir'] = 'Build comments directory.';
$l['NoBuildCommentsDir'] = 'Do not build comments directory.';
$l['CommentsCurCaptcha'] = 'Current captcha';
$l['CommentsNoCurCaptcha'] = 'No captcha set yet.';
$l['CommentsNewCaptcha'] = 'Set new captcha';
$l['CommentsNewCaptchaExplain'] = 'Write "delete" to unset captcha. Commenting won\'t be possible then.';
$l['CommentsCannotBuildDir'] = 'Cannot build Comments directory. Comments directory already exists.';
$l['Comments_Textarea_Rows'] = 10;
$l['Comments_Textarea_Cols'] = 40;

$Comments_dir             = $plugin_dir.'Comments/';
$captcha_path             = $Comments_dir.'captcha';
$Comments_Recent_path     = $Comments_dir.'_RecentComments.txt';
$permissions['comment'][] = '_comment_captcha';

#########################
# Most commonly called. #
#########################

function Comments()
# Return display of page comments and commenting form.
{ global $captcha_path, $Comments_dir, $esc, $nl, $nl2, $pages_dir, $title,$title_url;

  # Silently fail if $Comments_dir or page do not exist.
  if (!is_dir($Comments_dir) or !is_file($pages_dir.$title))
    return;

  # Build, format $comments display.
  $cur_page_file = $Comments_dir.$title;
  if (is_file($cur_page_file))
  { $comment_list = Comments_GetComments($cur_page_file);
    foreach ($comment_list as $id => $x)
    { $datetime = date('Y-m-d H:i:s', (int) $x['datetime']);
      $author = '<strong>'.$x['author'].'</strong>';
      $url = $x['url'];
      if ($url)
        $author = '<a href="'.$url.'">'.$author.'</a>';
      $comment_text = Comments_FormatText($x['text']);
      $comments .= $nl2.'<article id="comment_'.$id.'"><header class="Comments_head">'.
                            '<a href="#comment_'.$id.'">#'.$id.'</a></header>'.$nl.
                   '<div class="Comments_body">'.$comment_text.'</div>'.$nl.
                   '<footer class="Comments_foot">'.$author.' / '.$datetime.
                                                                   '</footer></article>'; } }
  if (!$comments)
    $comments = $nl2.'<p>'.$esc.'NoComments'.$esc.'</p>';

  # Commenting $form. Allow commenting depending on $captcha_path's existence.
  $write   = '<h2>'.$esc.'CommentsWrite'.$esc.'</h2>'.$nl2;
  if (is_file($captcha_path))
  { $input = $esc.'CommentsName'.$esc.': <input class="Comments_InputName" '.
                                                   'name="author" /><br />'.$nl.
             $esc.'CommentsURL'.$esc.': <input class="Comments_InputURL" '.
                                                            'name="URL" />'.$nl.
             '<pre><textarea name="text" class="Comments_Textarea" rows="'.$esc.
                                  'Comments_Textarea_Rows'.$esc.'" cols="'.$esc.
                                         'Comments_Textarea_Cols'.$esc.'">'.$nl.
                                                      $text.'</textarea></pre>';
    $captcha = file_get_contents($captcha_path);
    $form  = BuildPostForm($title_url.'&amp;action=write&amp;t=comment', $input, 
                           $esc.'CommentsAskCaptcha'.$esc.' "'.$captcha.
                           '": <input class="Comments_InputCaptcha" name="pw" '.
                           'size="5" /><input name="auth" type="hidden" value='.
                                                       '"_comment_captcha" />');
    $write .= $form; }
  else
    $write .= '<p>'.$esc.'CommentsWriteNo'.$esc.'</p>';

  return $nl2.'<h2>'.$esc.'Comments'.$esc.'</h2>'.$comments.$nl2.$write; }

function Comments_FormatText($text)
# Comment formatting: EscapeHTML, paragraphing / line breaks.
{ global $nl;
  $text = EscapeHTML($text);
  $lines = explode($nl, $text);
  $last_line = '';
  foreach ($lines as $n => $line)
  { if     (''  == $last_line and '' !== $line) $lines[$n] = '<p>'.$line;
    elseif ('' !== $last_line and ''  == $line) $lines[$n] = '</p>'.$nl;
    elseif ('' !== $last_line and '' !== $line) $lines[$n] = '<br />'.$nl.$line;
    $last_line = $line; }
  $text = implode($lines);
  if ('</p>' == substr($text, -4)) $text = $text.'</p>';
  return $text; }

function Comments_GetComments($comment_file)
# Read $comment_file into more structured, readable array $comments.
{ global $esc, $nl;
  $comments = array();

  # Read comment info line byline, assume first lines each entry to be metadata.
  $file_txt = file_get_contents($comment_file);
  foreach (explode($esc.$nl, $file_txt) as $entry_txt)
  { if (!$entry_txt)
      continue;
    $time = ''; $author = ''; $url = ''; $lines_comment = array();
    foreach (explode($nl, $entry_txt) as $line_n => $line)
    { if     ($line_n == 0)              $id              = $line;
      elseif ($line_n == 1)              $datetime        = $line;
      elseif ($line_n == 2)              $author          = $line;
      elseif ($line_n == 3) { if ($line) $url             = $line; }
      else                               $lines_comment[] = $line; }
    $comments[$id]['date']     = $date;
    $comments[$id]['author']   = $author;
    $comments[$id]['datetime'] = $datetime;
    $comments[$id]['url']      = $url;
    $comments[$id]['text']     = implode($nl, $lines_comment); }

  return $comments; }

function PrepareWrite_comment()
# Deliver to Action_write() all information needed for comment submission.
{ global $Comments_dir, $esc, $nl, $title, $title_url, $todo_urgent;
  $author = $_POST['author']; $url = $_POST['URL']; $text = $_POST['text'];

  # Repair problematical characters in submitted texts.
  foreach (array('author', 'url', 'text') as $variable_name)
    $$variable_name = Sanitize($$variable_name);
  $author = str_replace("\xE2\x80\xAE", '', $author); # Unicode:ForceRightToLeft

  # Check for failure conditions: empty variables, too large or bad values.
  if (!$author) ErrorFail($esc.'CommentsNoAuthor'.$esc);
  if (!$text)   ErrorFail($esc.'CommentsNoText'.$esc);
  $max_length_url = 2048; $max_length_author = 1000; $max_length_text = 10000;
  if (strlen($author) > $max_length_author)
    ErrorFail($esc.'CommentsAuthorMax'.$esc.': '.$max_length_author);
  if (strlen($url) > $max_length_url)
    ErrorFail($esc.'CommentsURLMax'.$esc.': '.$max_length_url);
  if (strlen($text) > $max_length_text)
    ErrorFail($esc.'CommentsTextMax'.$esc.': '.$max_length_text);
  $legal_url = '[A-Za-z][A-Za-z0-9\+\.\-]*:([A-Za-z0-9\.\-_~:/\?#\[\]@!\$&\'\('.
               '\)\*\+,;=]|%[A-Fa-f0-9]{2})+'; # Thx to @erlehmann
  if ($url and !preg_match('{^'.$legal_url.'$}', $url))
    ErrorFail($esc.'CommentsInvalidURL'.$esc);

  # Collect from $cur_page_file $old text and $highest_id, to top with $new_id.
  $cur_page_file = $Comments_dir.$title;
  $highest_id = -1;
  if (is_file($cur_page_file))
  { $old = file_get_contents($cur_page_file);
    $previous_comments = Comments_GetComments($cur_page_file);
    foreach ($previous_comments as $id => $stuff)
      if ($id > $highest_id)
        $highest_id = $id; }
  $new_id = $highest_id + 1;
  $x['redir'] = $title_url.'#comment_'.$new_id;

  # Put all strings together into $add, set writing task for it added to $old.
  $timestamp = time();
  $add = $new_id.$nl.$timestamp.$nl.$author.$nl.$url.$nl.$text.$nl.$esc.$nl;
  $x['tasks'][$todo_urgent][] = array('SafeWrite',
                                      array($cur_page_file), array($old.$add));
  $tmp = NewTemp();
  $x['tasks'][$todo_urgent][] = array('Comments_AddToRecent',
                                      array($title, $new_id, $timestamp, $tmp),
                                      array($author));
  return $x; }

###################
# Recent Comments #
###################

function Action_RecentComments()
# Provide HTML output of RecentComments file.
{ global $l, $esc, $Comments_Recent_path, $nl, $title_root;

  $output = '';
  if (is_file($Comments_Recent_path))
  { $txt      = file_get_contents($Comments_Recent_path);
    $lines    = explode($nl, $txt);
    $i        = 0;
    $date_old = '';
    foreach ($lines as $line)
    { $i++;
      if ('%%' == $line)
        $i = 0;
      elseif (1 == $i) 
      { $datetime   = date('Y-m-d H:i:s', (int) $line);
        list($date, $time) = explode(' ', $datetime); }
      elseif (2 == $i)
        $author = $line;
      elseif (3 == $i)
        $title = $line;
      elseif (4 == $i)
      { $id = $line;
        $string = '               <li>'.$time.': '.$author.' <a href="'.
                  $title_root.$title.'#comment_'.$id.'">on '.$title.'</a></li>';
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
    $output = '<p>'.$esc.'CommentsRecentNo'.$esc.'</p>';

  $l['title'] = $esc.'RecentComments'.$esc; $l['content'] = $output;
  OutputHTML(); }

function Comments_AddToRecent($title, $id, $timestamp, $tmp, $path_author)
# Add info of comment addition to RecentComments file.
{ global $Comments_Recent_path, $nl;
  $author = file_get_contents($path_author);

  $Comments_Recent_txt = '';
  if (is_file($Comments_Recent_path))
    $Comments_Recent_txt = file_get_contents($Comments_Recent_path);

  $add = $timestamp.$nl.$author.$nl.$title.$nl.$id.$nl;
  $Comments_Recent_txt = $add.'%%'.$nl.$Comments_Recent_txt;

  if (is_file($tmp))
  { file_put_contents($tmp, $Comments_Recent_txt); 
    rename($tmp, $Comments_Recent_path); 
    unlink($path_author); } }

###########################
# Comments administration #
###########################

function Action_comments_admin()
# Administration menu for comments.
{ global $captcha_path, $Comments_dir, $esc, $l, $nl, $nl2, $root_rel;

  # If no $Comments_dir, offer creating it.
  $build_dir = '';
  if (!is_dir($Comments_dir))
    $build_dir = '<p>'.$nl.
                 '<strong>'.$esc.'CommentsNoDir'.$esc.'</strong><br />'.$nl.
                 '<input type="radio" name="build_dir" value="yes" '.
                   'checked="checked">'.$esc.'BuildCommentsDir'.$esc.'<br>'.$nl.
                 '<input type="radio" name="build_dir" value="no">'.$esc.
                                           'NoBuildCommentsDir'.$esc.'<br>'.$nl.
                 '</p>'.$nl;

  # Captcha setting.
  if (is_file($captcha_path))
    $cur_captcha = $esc.'CommentsCurCaptcha'.$esc.': "'.
                   file_get_contents($captcha_path).'".';
  else
    $cur_captcha = $esc.'CommentsNoCurCaptcha'.$esc;
  $captcha = '<p><strong>'.$cur_captcha.'</strong></p>'.$nl.
             '<p>'.$esc.'CommentsNewCaptcha'.$esc.': <input name="captcha" /> ('
                             .$esc.'CommentsNewCaptchaExplain'.$esc.')</p>'.$nl;

  # Final HTML.
  $input   = $build_dir.$captcha;
  $form = BuildPostForm($root_rel.'?action=write&amp;t=comments_admin', $input);
  $l['title'] = $esc.'CommentsAdmin'.$esc; $l['content'] = $form;
  OutputHTML(); }

function PrepareWrite_comments_admin()
# Return to Action_write() all information needed for comments administration.
{ global $captcha_path, $Comments_dir, $esc, $nl, $pw_path, $root_rel, 
         $title_url, $todo_urgent;
  $new_pw    = $_POST['captcha'];
  $build_dir = $_POST['build_dir'];
  $x['tasks'] = array();

  # Directory building.
  if ($build_dir == 'yes')
  { if (is_dir($Comments_dir))
      ErrorFail($esc.'CommentsCannotBuildDir'.$esc);
    else
      $x['tasks'][$todo_urgent][] = array('mkdir', array($Comments_dir)); }

  # If $new_pw is "delete", unset captcha. Else, $new_pw becomes new captcha.
  if ($new_pw)
  { $passwords    = ReadPasswordList($pw_path);
    $salt         = $passwords['$salt'];
    $pw_file_text = $salt.$nl;
    if ('delete' == $new_pw) 
    { unset($passwords['_comment_captcha']);
      if (is_file($captcha_path))
        $x['tasks'][$todo_urgent][] = array('unlink', array($captcha_path)); }
    else
    { $passwords['_comment_captcha'] = hash('sha512', $salt.$new_pw);
      $x['tasks'][$todo_urgent][] = array('SafeWrite', 
                                        array($captcha_path), array($new_pw)); }
    foreach ($passwords as $key => $pw)
      $pw_file_text .= $key.':'.$pw.$nl;
    $x['tasks'][$todo_urgent][] = array('SafeWrite', 
                                        array($pw_path), array($pw_file_text));}
  return $x; }
