<?php
$html = file_get_contents('index.html');
$lines = explode("\n", $html);
$header = implode("\n", array_slice($lines, 0, 28)) . "\n";
$footer = implode("\n", array_slice($lines, 323)) . "\n";
$body = implode("\n", array_slice($lines, 28, 323 - 28)) . "\n";
file_put_contents('header.php', $header);
file_put_contents('footer.php', $footer);
$index = "<?php include 'header.php'; ?>\n" . $body . "<?php include 'footer.php'; ?>\n";
file_put_contents('index.php', $index);
unlink('index.html');
echo "Done";
