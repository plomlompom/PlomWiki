<?php

$hook_action_links .= '$action_links .= \' <a href="'.$title_root.'\'.$title.'
                                         .'\'&amp;action=search">Search</a>\';';

function Action_search()
{ global $legal_title, $pages_dir, $title, $title_root;

  # Produce search results HTML if $_GET['query'] is provided.
  $results = ''; $query = ''; 
  $query = $_GET['query']; 
  if ($query)
  { if (get_magic_quotes_gpc()) $query = stripslashes($query);
    $results = '<h2>Search results for: '.$query.'</h2>'."\n\n";
    $titles = array();
    $p_dir = opendir($pages_dir);
    while (FALSE !== ($fn = readdir($p_dir)))
      if (is_file($pages_dir.$fn) and preg_match('/^'.$legal_title.'$/', $fn))
        $titles[] = $fn;
    closedir($p_dir);
    $matches = array(); $matches_str = '';
    foreach ($titles as $title)
    { $content = file_get_contents($pages_dir.$title);
      if (strstr($content, $query)) $matches[] = '<li><a href="'.$title_root.
                                               $title.'">'.$title.'</a></li>'; }
    $matches_str .= implode("\n", $matches); 
    if ($matches_str) $results .= '<ul>'."\n".$matches_str."\n".'</ul>'; 
    else              $results .= '<p>None.</p>'; }

  # Final HTML. Start with the search query form.  
  echo 'Search</title>'."\n".'</head>'."\n".'<body>'."\n\n".
      '<h1>Search</h1>'."\n\n".'<form method="get" action="plomwiki.php">'."\n".
                 '<input type="hidden" name="title" value="'.$title.'" />'."\n".
                    '<input type="hidden" name="action" value="search" />'."\n".
                   '<input type="text" name="query" value="'.$query.'" />'."\n".
            '<input type="submit" value="Search!" />'."\n".'</form>'.$results; }
