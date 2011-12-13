<?php

include dirname(__FILE__) . '/../init.php';
require_once dirname(__FILE__) . '/../lib/geotools.class.php';

$councils = array(
    'FingalOpenData' => 'FingalOpenData',

    'CorkCity' => 'ePlan',
    'DublinCity' => 'Swift',
    'GalwayCity' => 'ePlan',
    'LimerickCity' => 'ePlan4',
    'Waterford' => 'ePlan',
    'Carlow' => 'ePlan4',
    'Cavan' => 'ePlan4',
    'Clare' => 'ePlan4',
    'CorkCo' => null,
    'Donegal' => 'ePlan',
    'SouthDublin' => 'SouthDublin',
    'DunLaoghaire' => 'Swift',
    'Fingal' => 'Swift',
    'GalwayCo' => 'GalwayCo',
    'Kerry' => 'ePlan',
    'Kildare' => null,
    'Kilkenny' => null,
    'Laois' => 'ePlan4',
    'Leitrim' => 'ePlan4',
    'LimerickCo' => 'ePlan',
    'Longford' => 'ePlan', // also ePlan4
    'Louth' => 'ePlan4',
    'Mayo' => null,
    'Meath' => 'ePlan4',
    'Monaghan' => 'ePlan4',
    'Offaly' => 'ePlan4',
    'Roscommon' => 'ePlan4',
    'Sligo' => 'ePlan4',
    'NTipperary' => 'ePlan', // also ePlan4
    'STipperary' => 'ePlan4',
    'WaterfordCo' => 'ePlan4',
    'Westmeath' => 'ePlan4',
    'Wexford' => null,
    'Wicklow' => 'ePlan4',
    'Letterkenny' => 'ePlan',
    'Bundoran' => 'ePlan',
    'Buncrana' => 'ePlan',
);

$council = null;
$files = array();
$geocode = false;
array_shift($argv);
while ($arg = array_shift($argv)) {
    if ($arg == '--geocode') {
        $geocode = true;
        continue;
    }
    if (!$council) {
        $council = $arg;
        continue;
    }
    $files[] = $arg;
}
if (!$files) {
    echo "Usage: php import.php [--geocode] Council dump.csv\n\n";
    echo str_replace("\n", "\n  ",
            wordwrap("Councils are: " . join(' ', array_keys($councils)))) . "\n";
    exit(0);
}
if (!isset($councils[$council])) {
    fputs(STDERR, "Unknown council '$council'\n");
    exit(1);
}
if (is_null($councils[$council])) {
    fputs(STDERR, "No importer defined for $council\n");
    exit(1);
}
$system = $councils[$council];
include dirname(__FILE__) . "/system_$system.php";
if ($council == 'FingalOpenData') $council = 'Fingal';

foreach ($files as $filename) {
    fputs(STDERR, "Running $system($council) importer for $filename\n");
    $apps = read_csv($council, $filename);
    $report = $planning->import_apps($apps, $geocode);
    fputs(STDERR, "Added $report[added], updated $report[updated]\n");
    if ($geocode) {
        echo "Geocoding: $report[geocode_success] ok, $report[geocode_fail] failed\n";
    }
}

function read_csv($council, $filename) {
    $apps = array();
    $f = @fopen($filename, 'r');
    if (!$f) {
        fputs(STDERR, "File not found: $filename\n");
        exit(1);
    }
    $header = fgetcsv($f, 0, ',', '"', '"');
    $r = 1;
    while ($row = fgetcsv($f, 0, ',', '"', '"')) {
        $r++;
        $assoc = array();
        foreach ($row as $i => $value) {
            $assoc[$header[$i]] = $value;
        }
        $app = create_app($council, $assoc);
        if (empty($app)) {
            fputs(STDERR, "Skipping row $r\n");
            continue;
        }
        if (!isset($app['imported'])) {
            $app['imported'] = date('c');
        }
        $apps[] = $app;
    }
    fclose($f);
    return $apps;
}

function remove_all_caps($s, $to_title_case = false) {
    if (!preg_match('/^[^a-z]*[A-Z][^a-z]*$/', $s)) {
        return ucfirst($s);
    }
    // There are no lowercase chars, but at least one uppercase
    if ($to_title_case) {
        return ucwords(strtolower($s));
    }
    // Clever custom regex magic to remove caps but retain them where needed
    $s = preg_replace_callback('/(?<=NO\. )([A-Z]{2,})/', '_remove_all_caps_callback', $s);
    $s = preg_replace_callback('/(?<=^[A-Z])([A-Z]+)/', '_remove_all_caps_callback', $s);
    $s = preg_replace_callback('/(?<![a-zA-Z][a-zA-Z]\. |^)([A-Z]{2,})/', '_remove_all_caps_callback', $s);
    $s = str_replace(' A ', ' a ', $s);
    return $s;
}

function _remove_all_caps_callback($match) {
    return strtolower($match[1]);
}

function clean_string($s) {
    return preg_replace('/ +/', ' ', $s);
}
