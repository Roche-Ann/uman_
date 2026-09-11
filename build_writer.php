<?php file_put_contents($argv[1], base64_decode($argv[2]), (isset($argv[3]) && $argv[3] === "append") ? FILE_APPEND : 0); ?>
