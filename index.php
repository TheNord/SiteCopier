<?php

require __DIR__ . '/vendor/autoload.php';

$url = 'http://moblog.net/latest';

(new \App\Crawler)->start($url);

echo PHP_EOL;