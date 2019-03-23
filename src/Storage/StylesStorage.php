<?php

namespace App\Storage;

class StylesStorage
{
    public static $processedStyle = [];

    public static function storeProcessed($link)
    {
        self::$processedStyle[] = $link;
    }
}