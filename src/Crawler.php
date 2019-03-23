<?php

namespace App;

use App\Storage\LinksStorage;
use App\Storage\StylesStorage;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;

class Crawler
{
    public $projectFolder;
    public $linksStorage;
    public $lastCrawling;
    /**
     * @var $lastCrawling int Timeout before new parsing (micro seconds)
     */
    public $crawlingTimeout;

    public function __construct()
    {
        $this->setTimeout(500);
    }

    public function start(string $url)
    {
        $this->projectFolder = 'project/' . $this->parseUrl($url)['host'];

        if (!file_exists($this->projectFolder)) {
            mkdir($this->projectFolder);
        }

        LinksStorage::storeUnProcessed($url);

        $this->parsePage();
    }

    /**
     * @throws \Throwable
     */
    public function parsePage()
    {
        $baseUri = LinksStorage::getUnProcessedLink();

        print 'Start parse page: ' . $baseUri . PHP_EOL;

        if ($this->lastCrawling) {
            usleep($this->crawlingTimeout);
        }

        $client = new Client(['base_uri' => $baseUri]);

        $headers = [
            "user-agent" => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_6_8) AppleWebKit/537.13+ (KHTML, like Gecko) Version/5.1.7 Safari/534.57.2",
        ];

        $url = $this->parseUrl($baseUri);
        $path = $this->parsePath($url['path']);
        $pageName = array_pop($path);

        // Initiate do not block requests
        $promises = [
            'page' => $client->getAsync($pageName, ['headers' => $headers]),
            //'png' => $client->getAsync('/image/png'),
            //'jpeg' => $client->getAsync('/image/jpeg'),
            //'webp' => $client->getAsync('/image/webp')
        ];

        $this->lastCrawling = true;

        // Wait for the requests to complete, even if some of them fail
        $results = Promise\settle($promises)->wait();

        // If page not found, start parse next link
        if (!array_key_exists('value', $results['page'])) {
            print 'Page ' . $baseUri . 'not found, ignore url';
            $this->parsePage();
        }

        $status = $results['page']['value']->getStatusCode();
        $content = $results['page']['value']->getBody()->getContents();

        if ($status == 200) {
            $this->savePage($baseUri, $content);
            $this->parseStyles($baseUri, $content);
            $this->parseLinks($baseUri, $content);
        }
    }

    public function parseUrl($url)
    {
        return parse_url($url);
    }

    public function parsePath(string $path)
    {
        $res = explode('/', trim($path, '/'));
        return $res;
    }

    public function savePage($uri, $content, $type = 'page')
    {
        $url = $this->parseUrl($uri);
        $path = $this->parsePath($url['path']);

        print 'Start saving ' . $type . ' : ' . $uri . PHP_EOL;



        // Single url (http://example.com/news/), create single file
        if (count($path) == 1) {
            $fileName = array_pop($path);
            $correctPath = $this->projectFolder . '/' . implode('/', $path);
        }

        // Nested url (http://example.com/news/welcome), create recursive folders
        if (count($path) > 1) {
            $fileName = array_pop($path);
            $correctPath = $this->projectFolder . '/' . implode('/', $path);
            @mkdir($correctPath, 0777, true);
        }

        $name = $fileName ?: 'index';

        if ($type == 'page') {
            if (!preg_match('/\.html/', $name)) {
                $name = $name . '.html';
            }

            LinksStorage::storeProcessed($uri);
        }

        if ($type == 'style') {
            StylesStorage::storeProcessed($uri);
        }

        file_put_contents($correctPath . '/' . $name, $content);
    }

    public function parseLinks($uri, $content)
    {
        print 'Start parsing links from the page: ' . $uri . PHP_EOL;

        $result = [];
        preg_match_all('/<a\s[^>]*href=\"([^\"]*)\"/', $content, $result);

        // Remove duplicate and empty link
        $links = array_values(array_unique(array_filter($result[1])));

        $baseUrl = $this->parseUrl($uri);
        $host = $baseUrl['scheme']."://".$baseUrl['host'];

        // Remove bad url (tel, mailto, img, etc.) and remove third-party sites links
        $filteredLinks = array_filter($links, function ($link) use ($baseUrl, $links) {
            if (preg_match('/\.png/', $link) ||
                preg_match('/\.gif/', $link) ||
                preg_match('/\.jpeg/', $link) ||
                preg_match('/\.jpg/', $link)) {
                return false;
            }

            // Filter third-party sites
            if (preg_match('/^http:\/\/([^\/]*)\//', $link, $matches) ||
                preg_match('/^\/\/([^\/]*)\//', $link, $matches) ||
                preg_match('/^\/\/([^\/]*)/', $link, $matches) ||
                preg_match('/^https:\/\/([^\/]*)\//', $link, $matches)) {
                return $baseUrl['host'] == $matches[1] ? true : false;
            }

            if (preg_match('/^\//', $link) ||
                preg_match('/^\./', $link)) {
                return true;
            }

            return false;
        });

        // Remove query params
        $filteredLinks = array_map(function ($link) {
            return strtok($link, '?');
        }, $filteredLinks);

        $filteredLinks = array_map(function ($link) {
            return strtok($link, '#');
        }, $filteredLinks);

        // rewrite relative references to absolute
        $result = array_map(function ($link) use ($host) {
            if (strpos($link, 'http') !== false) {
                return $link;
            }
            return $host . $link;
        }, $filteredLinks);

        LinksStorage::storeUnProcessed($result);

//        var_dump(LinksStorage::$unprocessedLinks);

        if (count(LinksStorage::$unprocessedLinks) > 0) {
            $this->parsePage();
        }
    }

    public function parseStyles($uri, $content)
    {
        print 'Start parsing styles from the page: ' . $uri . PHP_EOL;

        $result = [];
        preg_match_all('/<link\s[^>]*href=\"([^\"]*)\"/', $content, $result);

        $this->saveStyles($uri, $result[1]);
    }

    public function saveStyles($uri, $styles)
    {
        $baseUrl = $this->parseUrl($uri);
        $host = $baseUrl['scheme']."://".$baseUrl['host'];

        foreach ($styles as $style) {
            $path = $host . $style;

            if (preg_match('/\/\//', $style)) {
                $path = $style;
            }

            if (!in_array($path, StylesStorage::$processedStyle)) {
                $file = file_get_contents($path);
                $this->savePage($path, $file, 'style');
                continue;
            }
        }
    }

    public function setTimeout($milliseconds = 500)
    {
        $this->crawlingTimeout = $milliseconds * 100;
    }
}