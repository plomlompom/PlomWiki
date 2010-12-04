<?php

function MarkupLinesParagraphs($text)
# Line-break and paragraph markup.
{ $text = str_replace("\n",             '<br />',                      $text); 
  $text = str_replace('<br /><br />',   "\n".'</p>'."\n".'<p>'."\n",   $text); 
  $text = str_replace('<br />',         '<br />'."\n",                 $text); 
  return $text; }

function MarkupInternalLinks($text)
# Wiki-internal linking markup [[LikeThis]].
{ return preg_replace('/\[\[([A-Za-z0-9]+)]]/',
                             '<a href="plomwiki.php?title=$1">$1</a>', $text); } 

function MarkupStrong($text)
# "[*This*]" becomes "<strong>This</strong>", if not broken by newlines.
{ return preg_replace('/\[\*([^'."\n".']*?)\*]/', '<strong>$1</strong>', 
                                                                       $text); }

function MarkupEmphasis($text)
# "[/This/]" becomes "<em>This</em>", if not broken by newlines.
{ return preg_replace('/\[\/([^'."\n".']*?)\/]/', '<em>$1</em>', $text); }
