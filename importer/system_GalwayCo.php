<?php

function create_app($council, $row) {
    if (empty($row['AppNo'])) return false;
    if (empty($row['ReceivedDate'])) return false;
    $app = array(
        'council' => $council,
        'app_ref' => $row['AppNo'],
        'imported' => $row['scrape_date'],
        'received_date' => fix_GalwayCo_date($row['ReceivedDate']),
        'address' => fix_GalwayCo_address($row['Location']),
        'decision' => get_decision_code($row['FinalDecision']),
        'status' => get_status_code($row['Status']),
        'details' => fix_GalwayCo_details($row['Description']),
        'url' => $row['Link'],
    );
    $location = GeoTools::grid_to_lat_lng($row['LocationEasting'], $row['LocationNorthing']);
    if ($location) {
        $app['lat'] = $location[0];
        $app['lng'] = $location[1];
    }
    if ($row['FinalDecisionDate']) {
        $app['decision_date'] = fix_GalwayCo_date($row['FinalDecisionDate']);
    }
    return $app;
}

function fix_GalwayCo_date($s) {
    if (!$s) return null;
    if (!preg_match('#^(\d\d)/(\d\d)/(\d\d)$#', $s, $match)) {
        trigger_error("Date format: '$s'", E_USER_WARN);
        return null;
    }
    return ($match[3] < 50 ? '20' : '19') . $match[3] . '-' . $match[2] . '-' . $match[1];
}

function fix_GalwayCo_address($s) {
    // The scraper was broken for a while and left entities encoded; fix here
    // just in case
    $s = html_entity_decode($s, ENT_QUOTES, 'UTF-8');

    if ($s == 'NO ADDRESS') return null;
    $s = preg_replace('/\s+/', ' ', $s);
    $s = rtrim($s, ',');
    if (preg_match('/^(.*)\s+Co\.? Galway$/is', $s, $match)) {  // Corcullen Co. Galway
        $s = rtrim($match[1], ',');
    }
    $s = preg_replace('/([^\s])\(/', '\1', $s); // Carrownagower(Kiltulla)
    if (!preg_match('/ /', $s)) {          // "Townparrks-1st-Div-Tuam"
        $s = str_replace('-', ' ', $s);
    }
    return $s;
}

function fix_GalwayCo_details($s) {
    // The scraper was broken for a while and left entities encoded; fix here
    // just in case
    $s = html_entity_decode($s, ENT_QUOTES, 'UTF-8');

    $s = preg_replace('/\s+/', ' ', $s);
    return $s;
}

function get_decision_code($s) {
    if (!$s) return 'N';
    if ($s == 'Granted - Unconditional') return 'U';
    if ($s == 'Granted - Conditional') return 'C';
    if ($s == 'Refused') return 'R';
    if ($s == 'The final decision will be made available 32 days after the due date of the initial decision.') return 'D';
    trigger_error("Unknown decision string: '$s'", E_USER_WARN);
    return 'D';
}

function get_status_code($s) {
    if ($s == 'Incomplete Application') return 'INCOMPLETED APPLICATION';
    if ($s == 'New Application') return 'NEW APPLICATION';
    if ($s == 'Further Information Requested') return 'FURTHER INFORMATION';
    if ($s == 'Decision Made') return 'DECISION MADE';
    if ($s == 'Appealed') return 'APPEALED';
    if ($s == 'Withdrawn') return 'WITHDRAWN';
    if ($s == 'Application Finalised') return 'APPLICATION FINALISED';
    if ($s == 'Pre-Validation') return 'PRE-VALIDATION';
    if ($s == 'Deemed Withdrawn') return 'DEEMED WITHDRAWN';
    if ($s == 'Appealed Financial') return 'APPEALED FINANCIAL';
    if ($s == 'Pending decision') return 'PENDING DECISION';
    if ($s) trigger_error("Unknown status string: '$s'", E_USER_WARN);
    return 'UNKNOWN';
}
