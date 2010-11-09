<?php
$title = $_GET['title'];

echo'<!DOCTYPE html>
<html>
<body>
<p>'.$title.': <a href="edit.php?title='.$title.'">Edit</a></p>';

if (is_file($title)) { $text = file_get_contents($title); echo $text; }
else echo 'Page does not exist.';

echo '</body>
</html>';
