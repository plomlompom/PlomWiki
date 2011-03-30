<?php
$design = '<!DOCTYPE html>'.$nl.'<meta charset="UTF-8">'.$nl.
          '<style type="text/css">'.$nl.
          'textarea { font-family: monospace; }'.$nl.
          '.default { padding: 10px; padding-top: 0px; }'.$nl.
          '.fail { font-weight: bold; }'.$nl.
          '.edit textarea { width:100%; }'.$nl.
          '.history pre { white-space: pre-wrap; text-indent:-12pt; margin-top:0px; margin-bottom:0px; padding-left: 20px; }'.$nl.
          '.diff_text { margin-left:12pt; }'.$nl.
          '.diff_desc { padding: 10px; text-indent:-8pt; }'.$nl.
          '</style>'.$nl.
          '<title>'.$esc.'title'.$esc.'</title>'.$nl.
          $esc.'head'.$esc.$nl.'PlomWiki BETA: '.$nl.
          '<a href="'.$root_rel.'?title=Start">'.$esc.'JumpStart'.$esc.'</a> '.$nl.
          '<a href="'.$root_rel.'?action=set_pw_admin">'.$esc.'SetAdminPW'.$esc.'</a> '.$nl2.
          '<h1>'.$esc.'title'.$esc.'</h1>'.$nl.
          '<p>'.$nl.
          $esc.'ActionLinks_page'.$esc.$nl.
          '</p>'.$nl.'<hr />'.$nl.
          '<div class="'.$esc.'css_class'.$esc.'">'.$esc.'content'.$esc.'</div>';

$l['ActionLinks_page'] = 
'<a href="'.$title_url.'&amp;action=page_view">'.$esc.'View'.$esc.'</a> '.$nl.
'<a href="'.$title_url.'&amp;action=page_edit">'.$esc.'Edit'.$esc.'</a> '.$nl.
'<a href="'.$title_url.'&amp;action=page_history">'.$esc.'History'.$esc.'</a> '.$nl;

# $hook_Action_page_view   .= '$text .= \'<hr />\'.Comments();';
# $hook_Action_page_view  .= '$text .= \'<hr />\'.AutoLink_Backlinks(); ';
$hook_Action_page_edit .= '$l[\'css_class\'] = \'edit\';';
$hook_Action_page_history .= '$l[\'css_class\'] = \'history\';';
$hook_ErrorFail .= '$l[\'css_class\'] = \'default fail\';';
$hook_before_action .= '
if (substr($action, 7, 5) !== \'page_\') $l[\'ActionLinks_page\'] = \'\';';
