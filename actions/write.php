<?php
$password_posted = $_POST['password'];
$text = $_POST['text'];

$html_start = '<title>Unsuccessful edit of "'.$title.'"</title>';

# Check for passwords.
$password_expected = substr(file_get_contents('password.txt'), 0, -1);
if ($password_posted !== $password_expected)
  $message = '<strong>Wrong password.</strong>';

# Ignore any emptying of pages.
elseif (!$text)
  $message = 
'<strong>Empty pages not allowed.</strong><br />
Replace the page text with "delete" if you want to eradicate the page.';

# Anything else is a successful edit and will trigger an automatical redirect.
else
{ $html_start = 
'<meta http-equiv="refresh" content="0; URL=plomwiki.php?title='.$title.'" />
<title>Successful edit of "'.$title.'"</title>';

  # "delete" deletes a page.
  if ($text == 'delete')
  { if (is_file($page_path)) unlink($page_path);
    $message = '<strong>Page "'.$title.'" is now non-existant.</strong>'; }

  else
  { # Undo damage that results from PHP's magical_quotes horrors.
    if (get_magic_quotes_gpc()) $text = stripslashes($text);

    # Write changes to file.
    file_put_contents($page_path, $text);
    $message = '<strong>Page "'.$title.'" updated.</strong>'; }

  # Message for very speedy readers or very slow redirects.
  $message .= '<br />
If you read this, then your browser failed to redirect you back.'; }

# Final HTML.
echo $html_start.'
</head>
<body>
<p>
'.$message.'
</p>
<p>
Return to page "<a href="plomwiki.php?title='.$title.'">'.$title.'</a>".
</p>';
