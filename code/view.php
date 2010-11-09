<?php
echo '<body>
<p>'.$title.': <a href="plomwiki.php?title='.$title.'&action=edit">Edit</a></p>';

if (is_file($page_path)) $text = file_get_contents($page_path); 
else $text = 'Page does not exist.';

# Change < and > to their HTML entities to avoid user-generated HTML tags.
$text = str_replace('<', '&lt;', $text); 
$text = str_replace('>', '&gt;', $text);

# Line-break and paragraph markup.
$text = str_replace("\r\n\r", "\n".'</p>'."\n".'<p>', $text);
$text = str_replace("\r", '<br />', $text);

echo "\n".'<p>'."\n".$text."\n".'</p>'."\n";

echo '</body>';
