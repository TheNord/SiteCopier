<?php

namespace App;

use GuzzleHttp\Client;
use GuzzleHttp\Promise;

class Crawler
{
    public $projectFolder;

    public function start(string $url)
    {
        $this->projectFolder = 'project/' . $this->parseUrl($url)['host'];

        if (!file_exists($this->projectFolder)) {
            mkdir($this->projectFolder);
        }

        $this->parsePage($url);
    }

    /**
     * @param $baseUri
     * @throws \Throwable
     */
    public function parsePage($baseUri)
    {
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

        // Wait for the requests to complete, even if some of them fail
        $results = Promise\settle($promises)->wait();

        if (!array_key_exists('value', $results['page'])) {
            return;
        }

        $status = $results['page']['value']->getStatusCode();
        $content = $results['page']['value']->getBody()->getContents();

        if ($status == 200) {
            $this->savePage($baseUri, $content);
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

    public function savePage($uri, $content)
    {
        $url = $this->parseUrl($uri);
        $path = $this->parsePath($url['path']);

        // Single url (http://example.com/news/), create single file
        if (count($path) == 1) {
            $fileName = array_pop($path);
            $correctPath = $this->projectFolder . '/' . implode('/', $path);
        }

        // Nested url (http://example.com/news/welcome), create recursive folders
        if (count($path) > 1) {
            $fileName = array_pop($path);
            $correctPath = $this->projectFolder . '/' . implode('/', $path);
            mkdir($correctPath, 0777, true);
        }

        file_put_contents($correctPath . '/' . $fileName . '.html', $content);
    }
}