<?php

function create_app($council, $row) {
    // This field is called differently between Dublin City and Dun Laoghaire
    $app_ref = @$row['Planning Application Reference']
            ? $row['Planning Application Reference']
            : $row['Planning Application Ref'];
    if (!$app_ref) return null; // Should not really happen
    list($decision, $status) = get_decision_and_status($row['Decision']);
    $app = array(
        'council' => $council,
        'app_ref' => $app_ref,
        'imported' => $row['scrape_date'],
        // Some applications don't have an application date,
        // e.g., D0685/11. We'll use the registration date.
        'received_date' => ($row['Application Date'] ? get_unformatted_date($row['Application Date']) : get_unformatted_date($row['Registration Date'])),
        'address' => ucfirst(rtrim(preg_replace('/,+/', ',', clean_string($row['Main Location'])), '.')),
        'decision' => $decision,
        'status' => $status,
        'details' => ucfirst(clean_string($row['Proposal'])),
        'url' => $row['url'],
    );
    if (!empty($row['Decision Date'])) {
        $app['decision_date'] = get_unformatted_date($row['Decision Date']);
    }
    return $app;
}

function get_decision_and_status($s) {
    if (!$s) return array('N', 'NEW APPLICATION');
    if (preg_match('/non compliance|refuse/i', $s)) return array('R', 'APPLICATION FINALISED');
    if (preg_match('/grant|approved|compliance/i', $s)) return array('A', 'APPLICATION FINALISED');
    if (preg_match('/invalid/i', $s)) return array('R', 'INCOMPLETED APPLICATION');
    if (preg_match('/additional information/i', $s)) return array('N', 'FURTHER INFORMATION');
    if (preg_match('/withdrawn/i', $s)) return array('R', 'WITHDRAWN');
    return array('D', 'DECISION MADE');
}

function get_unformatted_date($date) {
    $months = array(
        'Jan' => '01', 'Feb' => '02', 'Mar' => '03', 'Apr' => '04', 'May' => '05', 'Jun' => '06',
        'Jul' => '07', 'Aug' => '08', 'Sep' => '09', 'Oct' => '10', 'Nov' => '11', 'Dec' => '12',
    );
    if (!@$months[substr($date, 3, 3)]) throw new Exception("Date format: $date");
    return substr($date, 7) . '-' . $months[substr($date, 3, 3)] . '-' . substr($date, 0, 2);
}
