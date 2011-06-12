<?php

include dirname(__FILE__) . '/common.php';

$importer_name = 'SouthDublin';
run();

function create_app($row) {
    $status = get_decision_and_status($row['Decision']);
    return array(
        'app_ref' => $row['app_ref'],
        'council_id' => 15,   // South Dublin
        'received_date' => ($row['Date Reived'] ? get_unformatted_date($row['Date Reived']) : null),
        'decision_date' => ($row['Decision Date'] ? get_unformatted_date($row['Decision Date']) : null),
        'address1' => $row['Location'],
        'decision' => $status[0],
        'status' => $status[1],
        'details' => $row['Proposed Development'],
        'url' => $row['url'],
    );
}

function get_decision_and_status($s) {
    if (!$s) return array('N', 1);
    if (preg_match('/(grant|approved)/i', $s)) return array('C', 9);
    if (preg_match('/invalid/i', $s)) return array('N', 0);
    if (preg_match('/refuse/i', $s)) return array('R', 9);
    if (preg_match('/additional information/i', $s)) return array('N', 2);
    if (preg_match('/withdrawn/i', $s)) return array('N', 8);
    die($s);
    return array('D', 3);
}

function get_unformatted_date($date) {
    return substr($date, 6, 4) . '-' . substr($date, 3, 2) . '-' . substr($date, 0, 2);
}
