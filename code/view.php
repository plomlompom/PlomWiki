<?php
echo '<body>
<p>'.$title.': <a href="plomwiki.php?title='.$title.'&action=edit">Edit</a></p>';

if (is_file($page_path)) $text = file_get_contents($page_path); 
else $text = 'Page does not exist.';

$text = str_replace('<', '&lt;', $text);
$text = str_replace('>', '&gt;', $text);

echo "\n".$text."\n";

echo '</body>';
