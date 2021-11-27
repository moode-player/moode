<?php

$file = '/var/local/www/currentsong.txt';
if (file_exists($file)) {
    try {
        $handle = fopen($file, "r");
        if ($handle) {
            $arr = [];
            while (($line = fgets($handle)) !== false) {
                $res = preg_match("/^([^=]+)=(.+)/", $line, $matches);
                if ($res) {
                    $arr[$matches[1]] = trim($matches[2]);
                }
            }
            fclose($handle);
            echo json_encode($arr);
        }
    } catch (\Exception $exception) {
    }
}
