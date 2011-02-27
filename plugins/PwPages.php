<?php

$actions_page[] = array('Set page password', '&amp;action=page_set_pw');
$hook_Action_page_edit = '$form = BuildPostForm($title_url.\'&amp;action=write'
                        .'&amp;t=page\', $input, \'<select name="auth"><option '
                        .'value="\'.$title.\'">Page</option><option value="*">'.
                               'Admin</option></select> password: <input type='.
                                                    '"password" name="pw">\');';

$hook_PrepareWrite_page .= 'if ($auth_posted == $title)'.$nl.
                          '  $x[\'auth\'] = $auth_posted; ';

$permissions['page'][] = $title;

function Action_page_set_pw()
{ global $title;
  ChangePW_form('page "'.$title.'"', $title); }
