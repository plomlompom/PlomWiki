<?php
# Provide a more sophisticated default $summary for diffs.

$hook_WritePage_diff .= 'if (!$summary) $summary = DoSomething($diff_add);';

function DoSomething($diff_add)
# Build a nice short summary of a diff from $diff_add.
{ global $nl;
  $diff_lines = explode($nl, $diff_add); 
  $n_add = 0; $n_del = 0;
  foreach ($diff_lines as $line)
  { if     ('>' == $line[0]) $n_add++;
    elseif ('<' == $line[0]) $n_del++; }
  if (0 < $n_add)
  { $sep = ' ';
    $prefix_add = '[+]';
    foreach ($diff_lines as $line)
    { if ('>' == $line[0])
        $string_add .= ' | '.substr($line, 1); } }
  if (0 < $n_del)
  { $prefix_del = '[-]';
    foreach ($diff_lines as $line)
    { if ('<' == $line[0])
        $string_del .= ' | '.substr($line, 1); } }
  $summary = 'AutoSum: '.$prefix_add.substr($string_add, 2).$sep.$prefix_del.
                                                         substr($string_del, 2);
  if (100 < strlen($summary))
    $summary = mb_substr($summary, 0, 99, 'UTF-8').'…'; 
  return $summary; }
