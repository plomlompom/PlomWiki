<?php

# Try to create directories.
$fail = FALSE;
$cwd = getcwd();
if (!mkdir($cwd.'/'.$work_dir))      $fail = TRUE;
if (!mkdir($cwd.'/'.$work_temp_dir)) $fail = TRUE;
if (!mkdir($cwd.'/'.$del_dir))       $fail = TRUE;

# Answer according to success.
if ($fail)   $msg = 'PlomWiki Setup failed. Something is wrong!';
else       { $msg = 'PlomWiki Setup successful. Reload!'; unlink($setup_file); }

$title_h = 'PlomWiki Setup';
$content = '<h1>PlomWiki Setup</h1>'.$nl2.'<p><strong>'.$msg.'</strong></p>';
Output_HTML($title_h, $content);

exit();
