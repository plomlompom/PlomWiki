<?php
$title = $_GET['title'];

echo'<!DOCTYPE html>
<html>
<body>
<p>'.$title.': <a href="view.php?title='.$title.'">Back to View</a></p>';

if (is_file($title)) $text = file_get_contents($title); 
else $text = '';

echo '<form method="post" action="work.php?title='.$title.'" >
<textarea name="text" rows="10" cols="10">'.$text.'</textarea>
<input type="submit" value="Submittiere!" />
</form>
</body>';
