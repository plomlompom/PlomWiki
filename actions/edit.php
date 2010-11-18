<?php

# If no page file is found, start with an empty $text.
if (is_file($page_path)) $text = file_get_contents($page_path); 
else $text = '';

# Replace symbols that might be confused for HTML markup with HTML entities.
$text = str_replace('&', '&amp;', $text);
$text = str_replace('<', '&lt;',  $text); 
$text = str_replace('>', '&gt;',  $text);
$text = str_replace('\'', '&apos;',  $text); 
$text = str_replace('"', '&quot;',  $text); 

# Final HTML.
echo '<title>Editing "'.$title.'"</title>
</head>
<body>
<p>
'.$title.': <a href="plomwiki.php?title='.$title.'">Back to View</a>
</p>
<form method="post" action="plomwiki.php?title='.$title.'&amp;action=write">
<textarea name="text" rows="10" cols="40">'.$text.'
</textarea><br />
Password: <input type="password" name="password" /><br />
<input type="submit" value="Update!" />
</form>';
