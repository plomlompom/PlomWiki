<?php

# Try to create directories.
$fail = FALSE;
$cwd = getcwd();
if (!mkdir($cwd.'/'.$work_dir))      $fail = TRUE;
if (!mkdir($cwd.'/'.$work_temp_dir)) $fail = TRUE;
if (!mkdir($cwd.'/'.$diff_dir))      $fail = TRUE;
if (!mkdir($cwd.'/'.$del_dir))       $fail = TRUE;

# Answer according to success.
if ($fail)   $msg = 'PlomWiki Setup failed. Something is wrong!';
else       { $msg = 'PlomWiki Setup successful. Reload!'; unlink($setup_file); }

# Final HTML.
echo $html_start.'PlomWiki Setup</title>'."\n".'</head>'."\n".'<body>'."\n\n".
     '<h1>PlomWiki Setup</h1>'."\n\n".'<p><strong>'.$msg.'</strong></p>'."\n\n".
                                                       '</body>'."\n".'</html>';

exit();
