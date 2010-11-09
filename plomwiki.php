<?php

# Only allow simple alphanumeric titles to avoid security risks.
$title = $_GET['title']; 
if (!preg_match('/^[a-zA-Z0-9]+$/', $title)) { echo 'Bad pagename'; exit(); }

$dir = 'pages/';
$path = $dir.$title;

echo'<!DOCTYPE html>
<html>';

$action = $_GET['action'];
if     ($action == 'edit')  require('edit.php'); 
elseif ($action == 'write') require('write.php');
else                        require('view.php');

echo '</html>';
