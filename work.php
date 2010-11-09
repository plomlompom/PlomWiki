<?php
$title = $_GET['title'];

file_put_contents($title, $_POST['text']);

echo '<!DOCTYPE html>
<html><head>
<meta http-equiv="refresh" content="0; URL=view.php?title='.$title.'" />
</head></html>';
