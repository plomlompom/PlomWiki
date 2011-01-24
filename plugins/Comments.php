<?php

$Comments_dir   = $plugin_dir.'Comments/';
$actions_meta[] = array('Comments administration', '?action=comments_admin');
$hook_Action_page_view .= '$text .= Comments(); ';
$permissions['comment'][] = '_comment_captcha';

#########################
# Most commonly called. #
#########################

function Comments()
# Return display of page comments and commenting form.
{ global $Comments_dir, $nl, $nl2, $pages_dir, $title, $title_url;

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
      $comment_text = '<p>'.$x['text'].'</p>';
      $comments .= $nl2.'<p><a name="comment_'.$id.'" href="#comment_'.$id.'">#'
                                                            .$id.'</a></p>'.$nl.
                   $comment_text.$nl.
                   '<p><em>(Written '.$datetime.' by "'.$author.
                                                             '".)</em></p>'; } }
  if (!$comments)
    $comments = $nl2.'<p>No one commented on this page yet.</p>';

  # Commenting $form. Allow commenting depending on $captcha.
  $captcha = Comments_GetCurCaptcha();
  $write   = '<h2>Write your own comment</h2>'.$nl2;
  if ($captcha)
  { $input = 'Your name: <input name="author" /><br />'.$nl.
             'Your URL: <input name="URL" />'.$nl.
             '<pre><textarea name="text" rows="10" cols="40">'.$nl.$text.
                                                            '</textarea></pre>';
    $form  = BuildPostForm($title_url.'&amp;action=write&amp;t=comment', $input, 
                           'Captcha password needed! Write "'.$captcha.
                           '": <input name="pw" size="5" /><input name="auth" '.
                                   'type="hidden" value="_comment_captcha" />');
    $write .= $form; }
  else
    $write .= '<p>Comment writing currently not possible: captcha not set.</p>';


  return $nl2.'<h2>Comments</h2>'.$comments.$nl2.$write; }

function Comments_GetComments($comment_file, $escape_html = TRUE)
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
    { if ($escape_html)
        $line = EscapeHTML($line);
      if     ($line_n == 0)              $id              = $line;
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

  # Check for failure conditions: empty variables.
  if (!$author) ErrorFail('Author field empty.');
  if (!$text)   ErrorFail('No comment written.');

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
  return $x; }

###########################
# Comments administration #
###########################

function Action_comments_admin()
# Administration menu for comments.
{ global $Comments_dir, $nl, $nl2, $root_rel;

  # If no $Comments_dir, offer creating it.
  $build_dir = '';
  if (!is_dir($Comments_dir))
    $build_dir = '<p>'.$nl.'<strong>Comments directory not yet built.</strong>'.
                                  ' (Comments will not work before.)<br />'.$nl.
                 '<input type="radio" name="build_dir" value="yes" '.
                          'checked="checked">Build comments directory.<br>'.$nl.
                 '<input type="radio" name="build_dir" value="no"> Do not '.
                                            'build comments directory.<br>'.$nl.
                 '</p>'.$nl;

  # Captcha setting.
  $cur_captcha = Comments_GetCurCaptcha();
  if ($cur_captcha) $cur_captcha = 'Current captcha is: "'.$cur_captcha.'".';
  else              $cur_captcha = 'No captcha set yet.';
  $captcha = '<p><strong>'.$cur_captcha.'</strong></p>'.$nl.
             '<p>Set new captcha: <input name="captcha" /> (Write "delete" to '.
                  'unset captcha. Commenting won\'t be possible then.)</p>'.$nl;

  # Final HTML.
  $input   = $build_dir.$captcha;
  $form = BuildPostForm($root_rel.'?action=write&amp;t=comments_admin', $input);
  Output_HTML('Comments administration', $form); }

function PrepareWrite_comments_admin()
# Return to Action_write() all information needed for comments administration.
{ global $Comments_dir, $nl, $pw_path, $root_rel, $title_url, $todo_urgent;
  $new_pw    = $_POST['captcha'];
  $build_dir = $_POST['build_dir'];
  $x['tasks'] = array();

  # Directory building.
  if ($build_dir == 'yes')
  { if (is_dir($Comments_dir))
      ErrorFail('Aborted comment administration. Cannot build Comments '.
                                                                   'directory.',
                'Comments directory already exists.');
    else
      $x['tasks'][$todo_urgent][] = array('mkdir', array($Comments_dir)); }

  # If $new_pw is "delete", unset captcha. Else, $new_pw becomes new captcha.
  if ($new_pw)
  { $passwords = ReadPasswordList($pw_path);
    if ('delete' == $new_pw) 
    { unset($passwords['_comment_captcha']);
      $success_captcha = '<p>Unsetting captcha.</p>'; }
    else
    { $passwords['_comment_captcha'] = $new_pw;
      $success_captcha = '<p>Setting new captcha</p>'; }
    $pw_file_text = '';
    foreach ($passwords as $key => $pw)
      $pw_file_text .= $key.':'.$pw.$nl;
    $x['tasks'][$todo_urgent][] = array('SafeWrite', 
                                        array($pw_path), array($pw_file_text));}
  return $x; }

##########################
# Minor helper functions #
##########################

function Comments_GetCurCaptcha()
# Get current comment captcha from password list.
{ global $pw_path;
  $password_list = ReadPasswordList($pw_path);
  return $password_list['_comment_captcha']; }

function Comments_CheckCaptcha($pw_posted, $passwords)
# Return whether $pw_posted is $passwords['_comment_captcha'].
{ if ($pw_posted === $passwords['_comment_captcha']) return TRUE;
  else                                               return FALSE; }
