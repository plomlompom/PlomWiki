<?php

function Action_work()
# Work through todo list.
{ global $work_dir;
  $path_todo = $work_dir.'todo';
  
  # Final HTML
  echo '<title>Doing some processing work ...</title>'."\n".'</head>'."\n".
          '<body>'."\n".'<p>'."\n".'Doing some processing work ...'."\n".'</p>';
  WorkToDo($path_todo);
  echo '<p>'."\n".'Finished!'."\n".'</p>'; }
