<?php

# Get $text from its page file.
if (is_file($page_path)) 
{ $text = file_get_contents($page_path); 

  # Replace symbols that might be confused for HTML markup with HTML entities.
  $text = str_replace('&', '&amp;', $text);
  $text = str_replace('<', '&lt;',  $text); 
  $text = str_replace('>', '&gt;',  $text); 
  $text = str_replace('\'', '&apos;',  $text); 
  $text = str_replace('"', '&quot;',  $text); 
  
  # Line-break and paragraph markup.
  $text = str_replace("\r\n\r", "\n".'</p>'."\n".'<p>', $text);
  $text = str_replace("\r", '<br />', $text);
  
  # Wiki-internal linking markup [[LikeThis]].
  $text = preg_replace('/\[\[([A-Za-z0-9]+)\]\]/', 
                             '<a href="plomwiki.php?title=$1">$1</a>', $text); }

# If no page file is found, show invitation to create one.
else $text = 'Page does not exist. <a href="plomwiki.php?title='.$title.
                                                '&amp;action=edit">Create?</a>';

# Final HTML.
echo '<title>'.$title.'</title>
</head>
<body>
<p>
'.$title.': <a href="plomwiki.php?title='.$title.'&amp;action=edit">Edit</a>
</p>
<p>
'.$text.'
</p>';
