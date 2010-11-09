<?php
$text = $_POST['text'];

# Undo damage that results from PHP's magical_quotes horrors.
if (get_magic_quotes_gpc()) $text = stripslashes($text);

# Only write to file if a non-empty $_POST['text'] got delivered.
if ($text) file_put_contents($page_path, $text);

echo '<head>
<meta http-equiv="refresh" content="0; URL=plomwiki.php?title='.$title.'" />
</head>';
