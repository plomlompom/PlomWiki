<?php
file_put_contents($path, $_POST['text']);

echo '<head>
<meta http-equiv="refresh" content="0; URL=plomwiki.php?title='.$title.'" />
</head>';
