<?php

require_once dirname(__FILE__) . '/common.php';

$crawl_delay = 1;
$scraper_name = 'system_ePlan';
$sites = array(
    'GalwayCity' => "http://gis.galwaycity.ie/ePlan/InternetEnquiry",
    'CorkCity' => "http://planning.corkcity.ie/InternetEnquiry",
    'Waterford' => "http://www.waterfordcity.ie/ePlan/InternetEnquiry",
    'LimerickCo' => "http://www.lcc.ie/ePlan/InternetEnquiry",
    'Kerry' => "http://atomik.kerrycoco.ie/ePlan/InternetEnquiry",
    'NTipperary' => "http://www.tipperarynorth.ie/iPlan/InternetEnquiry",
    'Longford' => "http://www.longfordcoco.ie/ePlan/InternetEnquiry",
    'Donegal' => "http://www.donegal.ie/DCC/iplaninternet/internetenquiry",
    'Letterkenny' => "http://www.donegal.ie/letterkenny_eplan/internetenquiry",
    'Bundoran' => "http://www.donegal.ie/bundoran_eplan/internetenquiry",
    'Buncrana' => "http://www.donegal.ie/buncrana_eplan/internetenquiry",
);

if ($argc == 3 && $argv[1] == '--recent') {
    $council = $argv[2];
    if (empty($sites[$council])) {
        fputs(STDERR, "Unknown council identifier: $council\n");
        die();
    }
    $site_url = $sites[$council];
    $apps = get_changed_applications_days(14);
    write_csv($apps);
} else if ($argc == 4 && $argv[1] == '--days' && is_numeric($argv[2])) {
    $days = $argv[2];
    $council = $argv[3];
    if (empty($sites[$council])) {
        fputs(STDERR, "Unknown council identifier: $council\n");
        die();
    }
    $site_url = $sites[$council];
    $apps = get_applications_days($days, 'RECEIVED');
    write_csv($apps);
} else {
    echo "Usage:\n";
    echo "  php $scraper_name.php --recent CouncilID\n";
    echo "    Scrapes two weeks of applications from one council\n";
    echo "  php $scraper_name.php --days n CouncilID\n";
    echo "    Scrapes all apps in the most recent n days from one council\n\n";
    echo "  Supported CouncilIDs are:\n    " . join("\n    ", array_keys($sites)) . "\n";
}

function get_changed_applications_days($days) {
    $apps = get_applications_days($days, 'RECEIVED');
    $apps += get_applications_days($days, 'DECIDED');
    return $apps;
}

function get_applications_days($days, $report_type = 'RECEIVED') {
    $urls = get_application_urls_days($days, $report_type);
    $apps = array();
    foreach ($urls as $url) {
        fputs(STDERR, "Fetching application " . (count($apps) + 1) .
                " of " . count($urls) . "\n");
        $apps[] = get_application_details($url);
        polite_delay();
    }
    return $apps;
}

// $report_type is one of DUE, RECEIVED, DECIDED
function get_application_urls_days($recent_days, $report_type = 'RECEIVED') {
    global $site_url;
    $urls = array();
    $postvars = array (
        'txtFileNum' => '',
        'txtSurname' => '',
        'ReportType' => $report_type,
        'txtLocation' => '',
        'NoDays' => $recent_days,
        'limitResults' => '0',
        'Submit5' => 'Search',
        'btnLookupFileNum' => 'Processing...',
        'distanceFrom' => '',     // for 'classic++' version of ePlan
        'selectedTown' => 'any',     // for 'classic++' version of ePlan
    );
    $thispage = 0;
    fputs(STDERR, "Fetching search result page for last $recent_days days\n");
    $html = http_request("$site_url/frmSelCritSearch.asp", $postvars);
    if (preg_match("/^Location: (.*)$/", $html, $match)) {
        return array("$site_url/$match[1]");
    }
    while (true) {
        $dom = str_get_html($html);
        $rows = $dom->find("table[class='AppDetailsTable'] tr a, table[class='AppDetailsTable2'] tr a");
        foreach($rows as $a) {
            $urls[] = str_replace(' ', '', "$site_url/$a->href");
        }
        $dom->clear();
        if (!preg_match("/Currently viewing page (\d+) of (\d+)/", $html, $match)) break;
        if (intval($match[1]) >= intval($match[2])) break;
        // There is more than one search result page
        $thispage++;
        polite_delay();
        fputs(STDERR, "Fetching search result page " . ($thispage + 1) . " of $match[2]\n");
        $html = http_request("$site_url/frmSelCritSearch.asp?page_num=$thispage&Op=1");
    }
    return $urls;
}

function get_application_details($url) {
    $app = array('url' => $url, 'scrape_date' => date('c'));
    // Apps contain either of those two but not both; pre-init the other one to null
    $app['Number of Reasons'] = $app['Number of Conditions'] = null;
    $html = http_request($url);
    $dom = str_get_html($html);
    foreach ($dom->find("table[class='AppDetailsTable'] th") as $th) {
        $td = $th->next_sibling();
        if (!$td) continue;
        $app[rtrim(clean_html($th->plaintext), ':? ')] = clean_html($td->plaintext);
    }
    $dom->clear();
    if (!empty($app['File Number'])) {
        polite_delay();
        $app_ref = str_replace('/', '', $app['File Number']);
        global $site_url;
        $location_url = "$site_url/rpt_ViewSiteLocDetails.asp?page_num=0&file_number=$app_ref";
        $dom = str_get_html(http_request($location_url));
        foreach ($dom->find("table[class='AppDetailsTable'] th") as $th) {
            $td = $th->next_sibling();
            if (!$td) continue;
            $app[rtrim(clean_html($th->plaintext), ':? ')] = clean_html($td->plaintext);
        }
        $dom->clear();
    }
    return $app;
}
