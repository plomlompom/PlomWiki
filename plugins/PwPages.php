<?php

$actions_page[] = array('Set page password', '&amp;action=page_set_pw');
$hook_CheckPW  .= 'if ($t == \'page\')'.
                  '  $return = PwPage_Check($pw_posted, $passwords, $title); ';

function Action_page_set_pw()
# Display page for setting new page password.
{ global $nl, $nl2, $title, $title_url;
  $input = '<input type="hidden" name="pw_key" value="'.$title.'">'.$nl.
           'New password for page "'.$title.'":<br />'.$nl.
           '<input type="password" name="new_pw" /><br /><br />';
  $form = BuildPostForm($title_url.'&amp;action=write&amp;t=pw', $input);
  Output_HTML('Set password for page "'.$title.'"', $form); }

function PwPage_Check($pw_posted, $passwords, $title)
# Check for page title password.
{ if ($pw_posted === $passwords[$title]) return TRUE;
  else                                   return FALSE; }
