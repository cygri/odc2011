<?php

function create_app($council, $row) {
    if (empty($row['File Number']) || empty($row['Received Date'])) return null;
    $app = array(
        'council' => $council,
        // Make application reference all upper-case: fs/12110 => FS/12110
        'app_ref' => strtoupper($row['File Number']),
        'imported' => $row['scrape_date'],
        'received_date' => get_unformatted_date($row['Received Date']),
        'decision_date' => ($row['Decision Date'] ?
                get_unformatted_date($row['Decision Date']) :
                ($row['Grant Date'] ? get_unformatted_date($row['Grant Date']) : null)),
        'address' => utf8_encode(clean_address($row['Development Address'], $row['Location Key'])),
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
    $status = strtoupper($status);
    switch ($status) {
        case 'INCOMPLETED APPLICATION': return $status;
        case 'INVALID APPLICATION': return 'INCOMPLETED APPLICATION';
        case 'NEW APPLICATION': return $status;
        case 'FURTHER INFORMATION': return $status;
        case 'DECISION MADE': return $status;
        case 'APPEALED': return $status;
        case 'APPLICATION FINALISED': return $status;
        case 'PRE-VALIDATION': return $status;
        case 'DEEMED WITHDRAWN': return $status;
        case 'WITHDRAWN': return $status;
        default: return 'UNKNOWN';
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
