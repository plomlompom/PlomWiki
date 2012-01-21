<?php
# PlomWiki plugin "Search"
# Provides Action_search().

# Language-specific variables.
$l['Search']            = 'Search'; 
$l['SearchResults']     = 'Search results for';
$l['SearchResultsNone'] = 'None.';

function Action_search()
# Case-insensitive search through all pages' texts and titles.
{ global $esc, $legal_title, $l, $nl, $nl2, $pages_dir, $title_root;

  # Produce search results HTML if $_GET['query'] is provided.
  $query = $_GET['query'];
  if ($query)
  { if (get_magic_quotes_gpc())
      $query = stripslashes($query);

    $results = $nl2.
               '<h2>'.$esc.'SearchResults'.$esc.': '.EscapeHTML($query).'</h2>'.
                                                                           $nl2;

    $matches = array();
    $query_low = strtolower($query);
    foreach (GetAllPageTitles() as $title)
    { $content_low = strtolower(file_get_contents($pages_dir.$title));
      if (strstr($content_low, $query_low) 
          or strstr(strtolower($title), $query_low)) 
        $matches[]='<li><a href="'.$title_root.$title.'">'.$title.'</a></li>'; }

    $matches_str .= implode($nl, $matches); 
    if ($matches_str) $results .= '<ul>'.$nl.$matches_str.$nl.'</ul>'; 
    else              $results .= '<p>'.$esc.'SearchResultsNone'.$esc.'</p>'; }

  $content = '<form method="get" action="'.$root_rel.'">'.$nl.
             '<input type="hidden" name="action" value="search" />'.$nl.
             '<input type="text" name="query" value="'.$query.'" />'.$nl.
             '<input type="submit" value="'.$esc.'Search'.$esc.'!" />'.$nl.
             '</form>'.$results; 
  $l['title'] = $esc.'Search'.$esc; $l['content'] = $content;
  OutputHTML(); }
