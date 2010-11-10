<?php
$password_posted = $_POST['password'];
$text = $_POST['text'];

$redirect = FALSE;

# Check for passwords.
$password_expected = substr(file_get_contents('password.txt'), 0, -1);
if ($password_posted !== $password_expected)
  $message = '<strong>Wrong password.</strong>';

# Ignore any emptying of pages.
elseif (!$text)
  $message = '<strong>Empty pages not allowed.</strong><br />
Replace the page text with "delete" if you want to eradicate the page.';

else
{ $redirect = TRUE;

  # "delete" deletes the page.
  if ($text == 'delete')
  { unlink($page_path);
    $message = '<strong>Page "'.$title.'" deleted.</strong>'; }

  else
  { # Undo damage that results from PHP's magical_quotes horrors.
    if (get_magic_quotes_gpc()) $text = stripslashes($text);

    file_put_contents($page_path, $text);
    $message = '<strong>Page "'.$title.'" updated.</strong>'; }
  $message .= '<br />
If you read this, then your browser failed to redirect you back.'; }

if ($redirect)
  echo '<head>
<meta http-equiv="refresh" content="0; URL=plomwiki.php?title='.$title.'" />
</head>';

echo '<body>
<p>
'.$message.'
</p>
<p>
Return to page "<a href="plomwiki.php?title='.$title.'">'.$title.'</a>".
</p>
</body>';
