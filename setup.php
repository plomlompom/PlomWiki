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
echo $html_start.'<title>PlomWiki Setup</title>
</head>
<body>

<h1>PlomWiki Setup</h1>

<p><strong>'; echo $msg;
echo '</strong></p>'.$html_end;

exit();
