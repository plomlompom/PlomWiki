<?php

# Only allow simple alphanumeric titles to avoid security risks.
$title = $_GET['title']; 
if (!preg_match('/^[a-zA-Z0-9]+$/', $title)) { echo 'Bad page title'; exit(); }

# Where page data is located.
$pages_dir  = 'pages/';
$page_path = $pages_dir.$title;

# Find appropriate code for user's '?action='. Assume "view.php" if not found.
$actions_dir  = 'actions/';
$fallback = $actions_dir.'view.php';
$action = $_GET['action'];
$action_path = ($actions_dir.$action.'.php'); 
if (!is_file($action_path)) $action_path = $fallback;

# Final HTML.
echo '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
';
require($action_path);
echo '
</body>
</html>';

