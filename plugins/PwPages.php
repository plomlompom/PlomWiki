<?php

# PlomWiki plugin "PwPages"
# Provides page passwords; Action_page_set_pw()

$l['SetPagePW'] = 'Set page password';
$hook_Action_page_edit .= '$form = BuildPostForm($title_url.\'&amp;action=write'
                        .'&amp;t=page\', $input, \'<select name="auth"><option '
                        .'value="*">Admin</option><option value="\'.$title.\'">'
                              .'Page</option></select> password: <input type='.
                                                    '"password" name="pw">\');';
$permissions['page'][] = $title;

$l['page'] = 'page';
function Action_page_set_pw()
{ global $esc, $title;
  ChangePW_form($esc.'page'.$esc.' "'.$title.'"', $title); }
