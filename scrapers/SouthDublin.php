<?php

require_once dirname(__FILE__) . '/common.php';

$scraper_name = 'SouthDublin';
$site_url = 'http://www.sdublincoco.ie/index.aspx?pageid=144';
$crawl_delay = 1;

run();

/**
 * Executes a search and returns all modified applications.
 * @param $start_date Inclusive, in YYYY-MM-DD format
 * @param $end_date Inclusive, in YYYY-MM-DD format
 * @return Array of applications
 */
function get_changed_applications($start_date, $end_date) {
    $apps = get_applications($start_date, $end_date, 'Applications');
    $apps += get_applications($start_date, $end_date, 'Decisions');
    return $apps;
}

/**
 * Executes a search and returns applications.
 * @param $start_date Inclusive, in YYYY-MM-DD format
 * @param $end_date Inclusive, in YYYY-MM-DD format
 * @param $status One of "Applications" (default), "Decisions"
 * @return Array of applications
 */
function get_applications($start_date, $end_date, $type = 'Applications') {
    $app_urls = get_application_urls($start_date, $end_date, ($type == 'Decisions') ? 'decs' : 'apps');
    $apps = array();
    foreach ($app_urls as $url) {
        fputs(STDERR, "Fetching application " . (count($apps) + 1) . " of " . count($app_urls) . "\n");
        $apps[] = get_application_details($url);
        polite_delay();
    }
    return $apps;
}

function get_application_urls($date1, $date2, $type = 'apps') {
    global $site_url;
    fputs(STDERR, "Searching for applications between $date1 and $date2 with type '$type'\n");
    $date1 = get_formatted_date($date1);
    $date2 = get_formatted_date($date2);
    $url = "$site_url&type=$type&fromdate=$date1&todate=$date2&dateoptions=specific&p=1";
    $html = str_get_html(http_request($url));
    $next_pages = array();
    $seen_pages = array($url);
    $urls = array();
    $done = 1;
    while (true) {
        fputs(STDERR, "Status: " . $html->find("span[id='ctl0_PageStatus']", 0)->plaintext . "\n");
        foreach ($html->find("table[class='sdcctable'] tbody tr td a") as $link) {
            if (preg_match('/^index\.aspx\?pageid=144(.*)$/', $link->href, $match)) {
              $urls[] = $site_url.str_replace("&amp;", "&", $match[1]);
            }
        }
        foreach ($html->find("a[id='ctl0_NextPageLink']") as $link) {
            if (preg_match('/^index\.aspx\?pageid=144(.*)$/', $link->href, $match)) {
              $next_page = $site_url.str_replace("&amp;", "&", $match[1]);
              if (!in_array($next_page, $seen_pages)) {
                $next_pages[] = $next_page;
                $seen_pages[] = $next_page;
              }
            }
        }
        if (!$next_pages) break;
        polite_delay();
        $url = array_shift($next_pages);
        $html = str_get_html(http_request($url));
        $done++;
    }
    return $urls;
}

function get_formatted_date($date) {
    return substr($date, 8) . '/' . substr($date, 5, 2) . '/' . substr($date, 0, 4);
}

function get_application_details($url) {
    $html = str_get_html(http_request($url));
    $app = array('url' => $url, 'scrape_date' => date('c'));
    $key = null;
    foreach ($html->find('div[class="planningapp"] div') as $div) {
        $value = trim(preg_replace("/\s+/", " ", clean_html($div->plaintext)));
        if (($div->class == "appleft") && ($value != "Notify me of any changes:")) {
          $key = trim($value, ':');
        }
        if ($div->class == "appright") {
          if ($key) {
            $value = str_replace(" - If third party submission closing date falls on a weekend or public holiday then submissions may be accepted on the following day.", "", $value);
            $app[$key] = $value;
            $key = null;
          }
        }
        if (($div->class == "apptitle") && (preg_match("/^Details of Application:\s*(.*)/", $div->plaintext, $match))) {
          $app["app_ref"] = trim($match[1]);
        }
    }
    return $app;
}
