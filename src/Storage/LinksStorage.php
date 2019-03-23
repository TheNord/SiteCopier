<?php

namespace App\Storage;

class LinksStorage
{
    public static $unprocessedLinks = [];
    public static $processedLinks = [];

    public static function storeUnProcessed($links)
    {
        is_string($links) ? $links = [$links] : null;

        $unprocessedLinks = array_values(array_unique(array_merge(self::$unprocessedLinks, $links)));
        self::$unprocessedLinks = array_diff($unprocessedLinks, self::$processedLinks);
        return self::$unprocessedLinks;
    }

    public static function storeProcessed($link)
    {
        unset(self::$unprocessedLinks[$link]);
        self::$processedLinks[] = $link;
        return self::$processedLinks;
    }

    public static function getUnProcessedLink()
    {
        $links = self::$unprocessedLinks;
        $lastLink = array_pop($links);
        self::$unprocessedLinks = $links;
        return $lastLink;
    }
}