<?php

$hook_meta_actions .= '$meta_actions .= \'<a href="'.$root_rel.'?action='
                                                 .'search">Search</a> \'."\n";';

function Action_search()
# Case-insensitive search through all pages' texts and titles.
{ global $legal_title, $pages_dir, $title_root;

  # Produce search results HTML if $_GET['query'] is provided.
  $results = ''; $query = ''; 
  $query = EscapeHTML($_GET['query']);
  if ($query)
  { if (get_magic_quotes_gpc()) $query = stripslashes($query);
    $results = "\n\n".'<h2>Search results for: '.$query.'</h2>'."\n\n";
    $titles = array();
    $p_dir = opendir($pages_dir);
    while (FALSE !== ($fn = readdir($p_dir)))
      if (is_file($pages_dir.$fn) and preg_match('/^'.$legal_title.'$/', $fn))
        $titles[] = $fn;
    closedir($p_dir);
    $matches = array(); $matches_str = ''; $query_low = strtolower($query);
    foreach ($titles as $title)
    { $content_low = strtolower(file_get_contents($pages_dir.$title));
      if (strstr($content_low, $query_low) 
          or strstr(strtolower($title), $query_low)) 
        $matches[]='<li><a href="'.$title_root.$title.'">'.$title.'</a></li>'; }
    $matches_str .= implode("\n", $matches); 
    if ($matches_str) $results .= '<ul>'."\n".$matches_str."\n".'</ul>'; 
    else              $results .= '<p>None.</p>'; }

  $title_h = 'Search';
  $content = '<h1>Search</h1>'."\n\n".
             '<form method="get" action="'.$root_rel.'">'."\n".
             '<input type="hidden" name="action" value="search" />'."\n".
             '<input type="text" name="query" value="'.$query.'" />'."\n".
             '<input type="submit" value="Search!" />'."\n".
             '</form>'.$results; 
  Output_HTML($title_h, $content); }
