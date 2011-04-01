<?php
# PlomWiki plugin Today
# Provides [:today:] markup.

function MarkupToday($text)
# Translates [:today:] into the current date, formatted as Y-m-d.
{ return str_replace('[:today:]', date('Y-m-d'), $text); }
