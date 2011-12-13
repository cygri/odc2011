<?php

# Fingal data online: http://data.fingal.ie/datasets/csv/Planning_Applications.csv

function create_app($council, $row) {
    list($decision, $status) = get_decision($row['Current_Status']);
    $app = array(
        'council' => $council,
        'app_ref' => $row['Planning_Reference'],
        'status' => $status,
        'received_date' => $row['Registration_Date'],
        'decision' => $decision,
        'address' => $row['Location'],
        'details' => $row['Description'],
        'url' => $row['More_Information'],
    );
    if (isset($row['Coordinates'])) {
        list($app['lat'], $app['lng']) = explode(",", $row['Coordinates']);
    }
    return $app;
}

function get_decision($fingal_status_code) {
    $code = strtoupper($fingal_status_code);
    if ($code == 'DECIDED') return array('DECISION', 'APPLICATION FINALISED');
    if (preg_match('/INVAI?LID OR WITHDRAWN/', $code)) return array('REFUSED', 'WITHDRAWN');
    if ($code == 'ON APPEAL') return array('NO DECISION', 'APPEALED');
    if ($code == 'PENDING') return array('NO DECISION', 'NEW APPLICATION');
    throw new Exception("Unknown status code '$fingal_status_code'");
}
