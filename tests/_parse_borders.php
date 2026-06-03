<?php
$files = [
    'avery' => __DIR__.'/_avery_tmp/word/document.xml',
    'herma' => __DIR__.'/_herma_extract/word/document.xml',
];
foreach ($files as $name => $path) {
    if (! is_file($path)) {
        echo "$name: missing\n";
        continue;
    }
    $x = file_get_contents($path);
    echo "=== $name ===\n";
    echo 'tblBorders: '.(preg_match('/<w:tblBorders>/', $x) ? 'yes' : 'no')."\n";
    echo 'tcBorders count: '.preg_match_all('/<w:tcBorders>/', $x)."\n";
    echo 'v:rect count: '.preg_match_all('/<v:rect/', $x)."\n";
    echo 'w:drawing count: '.preg_match_all('/<w:drawing/', $x)."\n";
    if (preg_match('/<w:tblBorders>.*?<\/w:tblBorders>/s', $x, $m)) {
        echo substr($m[0], 0, 500)."\n";
    }
    if (preg_match('/<v:rect[^>]{0,200}/', $x, $m)) {
        echo 'sample rect: '.$m[0]."\n";
    }
}
