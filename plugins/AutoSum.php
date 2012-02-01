<?php
# Provide a more sophisticated default $summary for diffs.

$hook_WritePage_diff .= 'if (!$summary) $summary = AutoSum($diff_add);';

function AutoSum($diff_add)
# Build a nice short summary of a diff from $diff_add.
{ global $nl;
  $diff_lines = explode($nl, $diff_add); 

  $n_add = 0; $n_del = 0;
  foreach ($diff_lines as $line)
  { if     ('>' == $line[0]) $n_add++;
    elseif ('<' == $line[0]) $n_del++; }

  if (0 < $n_add)
  { $sep_to_minus = ' ';
    $prefix_add = '[+]';
    foreach ($diff_lines as $line)
    { if ('>' == $line[0])
      { $string_add .= $sep.substr($line, 1); 
        $sep = ' / '; } 
      else $sep = ' […] '; } }
  $string_add = substr($string_add, 6);

  if (0 < $n_del)
  { $prefix_del = '[-]';
    foreach ($diff_lines as $line)
    { if ('<' == $line[0])
      { $string_del .= $sep.substr($line, 1); 
        $sep = ' / '; } 
      else $sep = ' […] '; } }
  $string_del = substr($string_del, 6);

  $summary = 'AutoSum: '.$prefix_add.$string_add.$sep_to_minus.$prefix_del.$string_del;
  if (100 < strlen($summary))
    $summary = mb_substr($summary, 0, 99, 'UTF-8').'…'; 
  return $summary; }
