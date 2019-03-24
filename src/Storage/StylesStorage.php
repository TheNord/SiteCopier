<?php

namespace App\Storage;

class StylesStorage
{
    public static $processedStyle = [];
    public static $images = [];

    public static function storeProcessed($link)
    {
        self::$processedStyle[] = $link;
    }

    public static function storeImagesProcessed($link)
    {
        self::$images[] = $link;
    }
}