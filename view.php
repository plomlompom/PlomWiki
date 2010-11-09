<?php
echo '<body>
<p>'.$title.': <a href="plomwiki.php?title='.$title.'&action=edit">Edit</a></p>';

if (is_file($path)) 
{ $text = file_get_contents($path); 
  echo '<pre>'.$text.'</pre>'; }
else echo 'Page does not exist.';

echo '</body>';
