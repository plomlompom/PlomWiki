<?php

function MarkupLinesParagraphs($text)
# Line-break and paragraph markup.
{ $text = str_replace("\n",             '<br />',                      $text); 
  $text = str_replace('<br /><br />',   "\n".'</p>'."\n".'<p>'."\n",   $text); 
  $text = str_replace('<br />',         '<br />'."\n",                 $text); 
  return $text;
}

function MarkupInternalLinks($text)
# Wiki-internal linking markup [[LikeThis]].
{ return preg_replace('/\[\[([A-Za-z0-9]+)\]\]/', 
                             '<a href="plomwiki.php?title=$1">$1</a>', $text); } 
