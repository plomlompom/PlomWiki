<?php

# Try to create directories.
$fail = FALSE;
$cwd = getcwd();
if (!mkdir($cwd.'/'.$work_dir))               $fail = TRUE;
if (!mkdir($cwd.'/'.$work_temp_dir))          $fail = TRUE;
if (!mkdir($cwd.'/'.$work_failed_logins_dir)) $fail = TRUE;
if (!mkdir($cwd.'/'.$del_dir))                $fail = TRUE;

# Try to build password file.
$salt = rand();
$pw = hash('sha512', $salt.'Password');
$pw_file_text = $salt.$nl.'*:'.$pw.$nl;
if (!file_put_contents($pw_path, $pw_file_text)) $fail = TRUE;

# Answer according to success.
if ($fail)
  ErrorFail('PlomWiki Setup failed. Something is wrong!');
else
{ unlink($setup_file); 
  WorkScreenReload($root_rel); }
