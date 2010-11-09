<?php
echo '<body>
<p>'.$title.': <a href="plomwiki.php?title='.$title.'&action=edit">Edit</a></p>';

if (is_file($page_path)) { $text = file_get_contents($page_path); echo $text; }
else echo 'Page does not exist.';

echo '</body>';
