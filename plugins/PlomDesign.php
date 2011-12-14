<?php

$design = '<!DOCTYPE html>'.$nl.'<meta charset="UTF-8">'.$nl.
          '<style type="text/css">'.$nl.
          'body { background-color: #F2F2F2; font-family: sans-serif; }'.$nl.
          'textarea { font-family: monospace; }'.$nl.
          '.head { padding: 0px; border: 1px solid #C0C0C0; background-color: #FAFAFA;; margin-top: 10px; margin-bottom: 10px; }'.$nl.
          '.head h1 { margin:0px; background-color: white; padding:10px; }'.$nl.
          '.ActionLinks_page { padding:10px; padding-top: 5px; padding-bottom: 5px; }'.$nl.
          '.default { border: 1px solid #C0C0C0; background-color: #FAFAFA; padding: 10px; padding-top: 0px; }'.$nl.
          '.default form {margin-top: 10px; }'.$nl.
          '.fail { padding-top:10px; font-weight: bold; background-color: #FFC0C0; }'.$nl.
          '.view { padding-bottom:0px; background-color: #FFFFFF; }'.$nl.
          '.empty { background-color: #FAFAFA; padding:10px; }'.$nl.
          '.edit textarea { width:100%; }'.$nl.
          '.history pre { white-space: pre-wrap; text-indent:-12pt; margin-top:0px; margin-bottom:0px; background-color: #FFFFFF; padding-left: 20px; }'.$nl.
          '.diff_text { margin-left:12pt; }'.$nl.
          '.diff_desc { padding: 10px; text-indent:-8pt; }'.$nl.
          '.Comments { background-color: #FAFAFA; margin: 0px; margin-top: 10px; padding:10px; padding-top: 0px; width: 46%; min-width: 275px; float: left; }'.$nl.
          '.Comments_InputName { width:100%; max-width: 250px; }'.$nl.
          '.Comments_InputURL { width:100%; max-width: 250px; }'.$nl.
          '.Comments_InputCaptcha { width:100%; max-width: 100px; }'.$nl.
          '.Comments_Textarea { width:100%; max-width: 500px; } '.$nl.
          '.Comments_body { margin:0px; padding:10px; background-color: #FFFFFF; position: relative; top:-10px; z-index:0; }'.$nl.
          '.Comments_head { margin:0px; padding:0px; background-color: #FAFAFA; position: relative; top:5px; z-index:1; }'.$nl.
          '.Comments_foot { margin:0px; padding:0px; background-color: #FAFAFA; text-align: right; position: relative; bottom:25px; z-index:1; margin-bottom:-20px; }'.$nl.
          '.BackLinks { background-color: #FAFAFA; margin: 0px; margin-top: 10px; padding:10px; padding-top: 0px; width: 46%; min-width: 275px; float: right; }'.$nl.
          '</style>'.$nl.
          '<title>'.$esc.'title'.$esc.'</title>'.$nl.
          $esc.'head'.$esc.$nl.
          '<div style="float:left;">PlomWiki BETA: '.$nl.
          '<a href="'.$root_rel.'?title=Start'.$esc.'pageview_params'.$esc.'">'.$esc.'JumpStart'.$esc.'</a> '.$nl.
          '<a href="'.$root_rel.'?action=Search">'.$esc.'Search'.$esc.'</a> '.$nl.
          '<a href="'.$root_rel.'?action=RecentChanges">'.$esc.'RecentChanges'.$esc.'</a> '.$nl.
          '<a href="'.$root_rel.'?action=RecentComments">'.$esc.'RecentComments'.$esc.'</a> &nbsp; '.$nl.
          '<script type="text/javascript"> var flattr_url = "http://www.plomlompom.de"; var flattr_btn="compact"; </script> <script src="http://api.flattr.com/button/load.js" type="text/javascript"></script></div>'.$nl.
          '<div style="text-align: right;"><a href="http://meta.plomlompom.de/impressum.html">Impressum</a> '.$nl.
          '<a href="http://meta.plomlompom.de/datenschutz.html">Datenschutz-Erklärung</a></div>'.$nl2.
          '<div class="head" ><h1>'.$esc.'title'.$esc.'</h1>'.$nl.
          $esc.'ActionLinks_page'.$esc.$nl.
          '</div>'.$nl.
          '<div class="'.$esc.'css_class'.$esc.'">'.$esc.'content'.$esc.'</div>';

$l['ActionLinks_page'] = '<div class="ActionLinks_page">'.
'<a href="'.$title_url.'&amp;action=page_view'.$esc.'pageview_params'.$esc.'">'.$esc.'View'.$esc.'</a> '.$nl.
'<a href="'.$title_url.'&amp;action=page_edit">'.$esc.'Edit'.$esc.'</a> '.$nl.
'<a href="'.$title_url.'&amp;action=page_history">'.$esc.'History'.$esc.'</a> '.$nl.
'| '.$nl.
'<a href="'.$title_url.'&amp;action=page_view&amp;show_autolinks='.$esc.'AutoLinks_show_neg'.$esc.'">'.$esc.'AutoLinkToggle'.$esc.'</a></div>'.$nl;

$hook_Action_page_view .= 
'if (!is_file($page_path))
{ $l[\'css_class\'] = \'default view empty\'; }
else
{ $l[\'css_class\'] = \'default view\';
  $text .= \'</div><div><div class="default Comments">\'.Comments(); 
  $text .= \'</div><div class="default BackLinks" >\'.AutoLink_Backlinks().\'</div>\'; }';
$hook_Action_page_edit .= '$l[\'css_class\'] = \'default edit\';';
$hook_Action_page_history .= '$l[\'css_class\'] = \'default history\';';
$hook_ErrorFail .= '$l[\'css_class\'] = \'default fail\';';
$hook_before_action .= '
if (substr($action, 7, 5) !== \'page_\') $l[\'ActionLinks_page\'] = \'\';';
