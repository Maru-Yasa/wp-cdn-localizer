<?php

namespace MaruYasa\WpCdnLocalizer;

class Helper
{

    public static function generateFileName($url)
    {
        return md5($url).'.'.basename(parse_url($url, PHP_URL_PATH));
    }

}