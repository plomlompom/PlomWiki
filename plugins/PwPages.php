<?php

# PlomWiki plugin "PwPages"
# Provides page passwords; Action_page_set_pw()

$l['SetPagePW'] = 'Set page password';
$hook_Action_page_edit .= '$form = BuildPostForm($title_url.\'&amp;action=write'
                             .'&amp;t=page\', $input, $esc.\'PWfor\'.$esc.\' <'.
                          'select name="auth"><option value="*">\'.$esc.\'admin'
                          .'\'.$esc.\'</option><option value="\'.$title.\'">\''.
                            '.$esc.\'page\'.$esc.\'</option></select>: <input '.
                                               'type="password" name="pw">\');';
$permissions['page'][] = $title;

$l['PWfor'] = 'Password for';
$l['page'] = 'page';
function Action_page_set_pw()
{ global $esc, $title;
  ChangePW_form($esc.'page'.$esc.' "'.$title.'"', $title); }
