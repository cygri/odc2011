<?php

include dirname(__FILE__) . '/common.php';

global $argc, $argv, $planning;
if ($argc == 4 && $argv[1] == '--geocode') {
    $geocode = true;
    $council = $argv[2];
    $filename = $argv[3];
} else if ($argc == 3) {
    $geocode = false;
    $council = $argv[1];
    $filename = $argv[2];
} else {
    fputs(STDERR, "Usage: php system_Swift.php [--geocode] CouncilID dump.csv\n");
    die();
}
if ($council != 'DublinCity' && $council != 'DunLaoghaire') {
    fputs(STDERR, "Unsupported council: $council\n");
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
    $app_ref = @$row['Planning Application Reference'] ? $row['Planning Application Reference'] : $row['Planning Application Ref'];
    if (!$app_ref) return null;
    $status = get_decision_and_status($row['Decision']);
    return array(
        'app_ref' => $app_ref,
        'council_id' => $council_id,
        // Some applications don't have an application date,
        // e.g., D0685/11. We'll use the registration date.
        'received_date' => ($row['Application Date'] ? get_unformatted_date($row['Application Date']) : get_unformatted_date($row['Registration Date'])),
        'decision_date' => ($row['Decision Date'] ? get_unformatted_date($row['Decision Date']) : null),
        'address1' => ucfirst(rtrim(preg_replace('/,+/', ',', clean_string($row['Main Location'])), '.')),
        'decision' => $status[0],
        'status' => $status[1],
        'details' => ucfirst(clean_string($row['Proposal'])),
        'url' => $row['url'],
    );
}

function get_decision_and_status($s) {
    if (!$s) return array('N', 1);
    if (preg_match('/non compliance|refuse/i', $s)) return array('R', 9);
    if (preg_match('/grant|approved|compliance/i', $s)) return array('C', 9);
    if (preg_match('/invalid/i', $s)) return array('N', 0);
    if (preg_match('/additional information/i', $s)) return array('N', 2);
    if (preg_match('/withdrawn/i', $s)) return array('N', 8);
    return array('D', 3);
}

function get_unformatted_date($date) {
    $months = array(
        'Jan' => '01', 'Feb' => '02', 'Mar' => '03', 'Apr' => '04', 'May' => '05', 'Jun' => '06',
        'Jul' => '07', 'Aug' => '08', 'Sep' => '09', 'Oct' => '10', 'Nov' => '11', 'Dec' => '12',
    );
    if (!@$months[substr($date, 3, 3)]) throw new Exception("Date format: $date");
    return substr($date, 7) . '-' . $months[substr($date, 3, 3)] . '-' . substr($date, 0, 2);
}
