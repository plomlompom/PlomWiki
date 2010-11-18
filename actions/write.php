<?php

# The edited page text submitted by edit.php.
$text = $_POST['text'];

# Start by checking for edit failure conditions.
$html_start = '<title>Trying to edit "'.$title.'"</title>';

# Check for failure condition: wrong password.
$password_posted = $_POST['password'];
$password_expected = substr(file_get_contents('password.txt'), 0, -1);
if ($password_posted !== $password_expected)
  $message = '<strong>Wrong password.</strong>';

# Check for failure condition: empty $text.
elseif (!$text)
  $message = 
'<strong>Empty pages not allowed.</strong><br />
Replace the page text with "delete" if you want to eradicate the page.';

# Successful edit writes to todo_urgent, triggers working on it and a redirect.
else
{ $html_start = '<meta http-equiv="refresh" content="0; URL=plomwiki.php?title='
                                                     .$title.'" />'.$html_start;
  $p_todo = fopen($todo_urgent, 'a+');

  # "delete" triggers page deletion.
  if ($text == 'delete')
  { if (is_file($page_path)) fwrite($p_todo, 'DeletePage("'.$title.'");'."\n");
    $message = '<strong>Page "'.$page_path.'" is now non-existant.</strong>'; }

  else
  { # Undo damage that results from PHP's magical_quotes horrors.
    if (get_magic_quotes_gpc()) $text = stripslashes($text);

    # Write $text into a temp file to be given to UpdatePage().
    $temp_path = NewTempFile($text);
    fwrite($p_todo, 'UpdatePage("'.$page_path.'", "'.$temp_path.'");'."\n");
    $message = '<strong>Page "'.$title.'" updated.</strong>'; }

  fclose($p_todo);
  WorkToDo($todo_urgent);

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
