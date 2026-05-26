<?php
$lines = file('resources/views/posts/index.blade.php');
$stack = [];
foreach ($lines as $i => $line) {
    $lineNum = $i + 1;
    $ifCount = substr_count($line, '@if');
    $endifCount = substr_count($line, '@endif');
    for ($k=0;$k<$ifCount;$k++) {
        $stack[] = ['line'=>$lineNum, 'text'=>trim($line)];
    }
    for ($k=0;$k<$endifCount;$k++) {
        array_pop($stack);
    }
}
if (empty($stack)) {
    echo "All @if matched with @endif\n";
} else {
    echo "Unmatched @if entries (top-first):\n";
    foreach ($stack as $s) {
        echo "Line {$s['line']}: {$s['text']}\n";
    }
}
