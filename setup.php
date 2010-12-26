<?php

# Try to create directories.
$fail = FALSE;
$cwd = getcwd();
if (!mkdir($cwd.'/'.$work_dir))      $fail = TRUE;
if (!mkdir($cwd.'/'.$work_temp_dir)) $fail = TRUE;
if (!mkdir($cwd.'/'.$del_dir))       $fail = TRUE;

# Answer according to success.
if ($fail)
  ErrorFail('PlomWiki Setup failed. Something is wrong!');
else
{ unlink($setup_file); 
  WorkScreenReload($root_rel); }
