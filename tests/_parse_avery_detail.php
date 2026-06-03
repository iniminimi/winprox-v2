<?php
$x = file_get_contents(__DIR__.'/_avery_tmp/word/document.xml');
preg_match('/<w:tcBorders>.*?<\/w:tcBorders>/s', $x, $m);
echo "tcBorders sample:\n".$m[0]."\n\n";
preg_match('/<w:drawing>.*?<\/w:drawing>/s', $x, $d);
echo "drawing sample (first 2500 chars):\n".substr($d[0] ?? '', 0, 2500)."\n";
