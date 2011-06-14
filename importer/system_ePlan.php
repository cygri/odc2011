<?php

include dirname(__FILE__) . '/common.php';
require_once dirname(__FILE__) . '/../lib/geotools.class.php';

if ($argc == 4 && $argv[1] == '--geocode') {
    $geocode = true;
    $council = $argv[2];
    $filename = $argv[3];
} else if ($argc == 3) {
    $geocode = false;
    $council = $argv[1];
    $filename = $argv[2];
} else {
    fputs(STDERR, "Usage: php system_ePlan.php [--geocode] CouncilID dump.csv\n");
    die();
}
$council_id = $planning->get_council_id($council);
$apps = read_csv($filename);
$report = $planning->import_apps($apps, $geocode);
echo date('c') . " Added $report[added] and updated $report[updated] applications from $filename\n";
if ($geocode) {
    echo date('c') . "   Geocoding succeeded for $report[geocode_success] and failed for $report[geocode_fail]\n";
}

function create_app($row) {
    global $council_id;
    if (empty($row['File Number']) || empty($row['Received Date'])) return null;
    $app = array(
        // Make application reference all upper-case: fs/12110 => FS/12110
        'app_ref' => strtoupper($row['File Number']),
        'council_id' => $council_id,
        'received_date' => get_unformatted_date($row['Received Date']),
        'decision_date' => ($row['Decision Date'] ?
                get_unformatted_date($row['Decision Date']) :
                ($row['Grant Date'] ? get_unformatted_date($row['Grant Date']) : null)),
        'address1' => utf8_encode(clean_address($row['Development Address'], $row['Location Key'])),
        'decision' => get_decision($row['Decision Type']),
        'status' => get_status($row['Planning Status']),
        // First letter of details should be upper-case
        'details' => utf8_encode(remove_all_caps(clean_string($row['Development Description']))),
        'url' => $row['url'],
    );
    $location = GeoTools::grid_to_lat_lng(@$row['Grid Northings'], @$row['Grid Eastings']);
    if ($location) {
        $app['lat'] = $location[0];
        $app['lng'] = $location[1];
    }
if ($app['received_date'] < '1900-00-00') {var_dump($row); die();}
    return $app;
}

function get_unformatted_date($date) {
    if (!$date) return null;
    preg_match('!^(\d\d)/(\d\d)/(\d\d\d\d)!', $date, $match);
    return "$match[3]-$match[2]-$match[1]";
}

function get_decision($decision) {
    switch (strtoupper($decision)) {
        case '': return 'N';
        case 'CONDITIONAL': return 'C';
        case 'UNCONDITIONAL': return 'U';
        case 'REFUSED': return 'R';
        case 'NOT YET DECIDED': return 'N';
        default: return null;
    }
}

function get_status($status) {
    switch (strtoupper($status)) {
        case 'INCOMPLETED APPLICATION': return 0;
        case 'INVALID APPLICATION': return 0;
        case 'NEW APPLICATION': return 1;
        case 'FURTHER INFORMATION': return 2;
        case 'DECISION MADE': return 3;
        case 'APPEALED': return 5;
        case 'APPLICATION FINALISED': return 9;
        case 'PRE-VALIDATION': return 10;
        case 'DEEMED WITHDRAWN': return 11;
        case 'WITHDRAWN': return 8;
        default: return 14;
    }
}

function clean_address($address, $location) {
    // Fix a weird issue in old Galway data, where ", " is inserted
    // into the middle of the address at position 25. We use the fact
    // that Location Key is the correct form, but cut off at 35.
    $spliced = substr($address, 0, 25) . substr($address, 27);
    if (substr($spliced, 0, strlen($location)) == $location) {
        $address = $spliced;
    }
    // Remove excess punctuation and whitespace from addresses
    $address = preg_replace("/[, ]*\n/m", "\n", $address);
    $address = preg_replace('/\s*,+/', ',', $address);
    $address = preg_replace('/ +/', ' ', $address);
    $address = preg_replace('/[., ]+$/', '', $address);
    return remove_all_caps($address, true);
}
