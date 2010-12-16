<?php

$actions_meta[] = array('Search', '?action=search');

function Action_search()
# Case-insensitive search through all pages' texts and titles.
{ global $legal_title, $nl, $nl2, $pages_dir, $title_root;

  # Produce search results HTML if $_GET['query'] is provided.
  $query = EscapeHTML($_GET['query']);
  if ($query)
  { if (get_magic_quotes_gpc())
      $query = stripslashes($query);

    $results = $nl2.'<h2>Search results for: '.$query.'</h2>'.$nl2;

    $p_dir = opendir($pages_dir);
    while (FALSE !== ($fn = readdir($p_dir)))
      if (is_file($pages_dir.$fn) and preg_match('/^'.$legal_title.'$/', $fn))
        $titles[] = $fn;
    closedir($p_dir);

    $matches = array();
    $query_low = strtolower($query);
    foreach ($titles as $title)
    { $content_low = strtolower(file_get_contents($pages_dir.$title));
      if (strstr($content_low, $query_low) 
          or strstr(strtolower($title), $query_low)) 
        $matches[]='<li><a href="'.$title_root.$title.'">'.$title.'</a></li>'; }

    $matches_str .= implode($nl, $matches); 
    if ($matches_str) $results .= '<ul>'.$nl.$matches_str.$nl.'</ul>'; 
    else              $results .= '<p>None.</p>'; }

  $title_h = 'Search';
  $content = '<form method="get" action="'.$root_rel.'">'.$nl.
             '<input type="hidden" name="action" value="search" />'.$nl.
             '<input type="text" name="query" value="'.$query.'" />'.$nl.
             '<input type="submit" value="Search!" />'.$nl.
             '</form>'.$results; 
  Output_HTML($title_h, $content); }
