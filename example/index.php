<?php
require_once __DIR__ . '/../vendor/autoload.php';

$db = new \DT\Dude("/Users/ceres/Desktop/dude.db");


$devices = $db->fetchDevices();
$maps = $db->fetchMaps();
$types = $db->fetchTypes();
$links = $db->fetchLinks();

printf( "There are %u devices spread over %u maps. Types are a total of %u types and we got over %u links through all maps and devices",
    count($devices), count($maps), count($types), count($links)
);