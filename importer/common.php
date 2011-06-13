<?php

include dirname(__FILE__) . '/../config.inc.php';
require_once dirname(__FILE__) . '/../lib/db.class.php';
require_once dirname(__FILE__) . '/../lib/planning.class.php';

$planning = new Planning(new DB($config));

function run() {
    global $argc, $argv, $planning;
    if ($argc == 3 && $argv[1] == '--geocode') {
        $geocode = true;
        $filename = $argv[2];
    } else if ($argc == 2) {
        $geocode = false;
        $filename = $argv[1];
    } else {
        global $importer_name;
        echo "Usage: php $importer_name.php [--geocode] dump.csv\n";
        die();
    }
    $apps = read_csv($filename);
    $report = $planning->import_apps($apps, $geocode);
    echo date('c') . " Added $report[added] and updated $report[updated] applications from $filename\n";
    if ($geocode) {
        echo date('c') . "   Geocoding succeeded for $report[geocode_success] and failed for $report[geocode_fail]\n";
    }
}

function read_csv($filename) {
    $apps = array();
    $f = fopen($filename, 'r');
    $header = fgetcsv($f, 0, ',', '"', '"');
    while ($row = fgetcsv($f, 0, ',', '"', '"')) {
        $assoc = array();
        foreach ($row as $i => $value) {
            $assoc[$header[$i]] = $value;
        }
        $app = create_app($assoc);
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
