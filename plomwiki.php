<?php

# Only allow simple alphanumeric titles to avoid security risks.
$title = $_GET['title']; 
if (!preg_match('/^[a-zA-Z0-9]+$/', $title)) { echo 'Bad page title'; exit(); }

$data_dir  = 'pages/';
$page_path = $data_dir.$title;

echo '<!DOCTYPE html>
<html>';

$code_dir  = 'code/';
$action = $_GET['action'];
if     ($action == 'edit')  require($code_dir.'edit.php'); 
elseif ($action == 'write') require($code_dir.'write.php');
else                        require($code_dir.'view.php');

echo '</html>';
