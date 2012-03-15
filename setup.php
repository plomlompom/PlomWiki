<?php
# PlomWiki setup. Runs once to initialize new PlomWiki installation.
# 
# Copyright 2010-2012 Christian Heller / <http://www.plomlompom.de/>
# License: AGPLv3 or any later version. See file LICENSE for details.

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
if (!file_put_contents($pw_path, $pw_file_text))
  $fail = TRUE;

# Answer according to success.
if ($fail) {
  echo '<p><strong>PlomWiki Setup failed.</strong></p>';
  exit(); }
else {
  unlink($setup_file); 
  WorkScreenReload(); }
