<?php

$file = '/var/local/www/currentsong.txt';
if (file_exists($file)) {
    try {
        preg_match_all('/^(.+?)= ?(.*?)$/m', file_get_contents($file), $matches);
        print(json_encode(array_combine(array_map('trim', $matches[1]), $matches[2])));
    } catch (\Exception $exception) {
    }
}
