<?php
$text = $_POST['text'];

# Undo damage that results from PHP's magical_quotes horrors.
if (get_magic_quotes_gpc()) $text = stripslashes($text);

# Don't write any empty $text. Page eradication happens for $text == 'delete'.
if ($text) 
{ file_put_contents($page_path, $text);
  if ($text == 'delete') unlink($page_path); }

echo '<head>
<meta http-equiv="refresh" content="0; URL=plomwiki.php?title='.$title.'" />
</head>';
