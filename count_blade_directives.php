<?php
$s = file_get_contents('resources/views/posts/index.blade.php');
echo "@if: " . substr_count($s, '@if') . PHP_EOL;
echo "@else: " . substr_count($s, '@else') . PHP_EOL;
echo "@endif: " . substr_count($s, '@endif') . PHP_EOL;
