<?php
echo '<body>
<p>'.$title.': <a href="plomwiki.php?title='.$title.'">Back to View</a></p>';

if (is_file($path)) $text = file_get_contents($path); 
else $text = '';

echo '<form method="post" action="plomwiki.php?title='.$title.'&action=write" >
<textarea name="text" rows="10" cols="10">'.$text.'</textarea>
<input type="submit" value="Update!" />
</form>
</body>';
