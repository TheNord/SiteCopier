<?php

require __DIR__ . '/vendor/autoload.php';

class Starter {
    public function __construct()
    {
        $url = 'http://balashover.ru/';

        (new \App\Crawler)->start($url);

        print 'Parsing finished.' . PHP_EOL;

        var_dump(\App\Storage\LinksStorage::$unprocessedLinks);
    }
}

(new Starter());