<?php
echo '<body>
<p>'.$title.': <a href="plomwiki.php?title='.$title.'">Back to View</a></p>';

if (is_file($page_path)) $text = file_get_contents($page_path); 
else $text = '';

echo '<form method="post" enctype="multipart/form-data" action="plomwiki.php?title='.$title.'&action=write" >
<textarea name="text" rows="10" cols="40">'.$text.'</textarea>
<input type="submit" value="Update!" />
</form>
</body>';
