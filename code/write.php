<?php
file_put_contents($page_path, $_POST['text']);

echo '<head>
<meta http-equiv="refresh" content="0; URL=plomwiki.php?title='.$title.'" />
</head>';
