<?php

require __DIR__ . '/vendor/autoload.php';

class Starter {
    public function __construct()
    {
        $url = 'http://balashover.ru/';

        (new \App\Crawler)->start($url);
//        (new \App\Crawler)->parseStyleImage($url, 'test', file_get_contents(__DIR__ . '/project/balashover.ru/index_files/ba39e182ae.css'));
//        (new \App\Crawler)->modifyStyleImage($url, 'http://balashover.ru/index_files/ba39e182ae.css', file_get_contents(__DIR__ . '/project/balashover.ru/index_files/ba39e182ae.css'));

        print 'Parsing finished.' . PHP_EOL;

       // var_dump(\App\Storage\LinksStorage::$unprocessedLinks);
    }
}

(new Starter());