<?php
echo '<head>
<title>'.$title.'</title>
</head>
<body>
<p>'.$title.': <a href="plomwiki.php?title='.$title.
'&amp;action=edit">Edit</a></p>';

if (is_file($page_path)) $text = file_get_contents($page_path); 
else $text = 'Page does not exist.';

# Change < and > to their HTML entities to avoid user-generated HTML tags.
$text = str_replace('<', '&lt;', $text); 
$text = str_replace('>', '&gt;', $text);

# Line-break and paragraph markup.
$text = str_replace("\r\n\r", "\n".'</p>'."\n".'<p>', $text);
$text = str_replace("\r", '<br />', $text);

# Wiki-internal linking markup [[LikeThis]].
$text = preg_replace('/\[\[([A-Za-z0-9]+)\]\]/', 
                               '<a href="plomwiki.php?title=$1">$1</a>', $text);

echo "\n".'<p>'."\n".$text."\n".'</p>'."\n";

echo '</body>';
