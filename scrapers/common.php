<?php

require_once dirname(__FILE__) . '/simple_html_dom.php';

ini_set('memory_limit', '1000M');

$user_agent = 'Planning Explorer (http://planning-apps.opendata.ie)';

set_error_handler('_error_handler');

function _error_handler($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        // This error code is not included in error_reporting
        return;
    }
    switch ($errno) {
        case E_NOTICE: $msg = 'NOTICE'; break;
        case E_USER_NOTICE: $msg = 'NOTICE'; break;
        case E_WARNING: $msg = 'WARNING'; break;
        case E_USER_WARNING: $msg = 'WARNING'; break;
        case E_USER_ERROR: $msg = 'ERROR'; break;
        default: $msg = "ERROR $errno";
    }
    fputs (STDERR, "$msg $errstr [$errfile:$errlibe]\n");
    if ($errno == E_USER_ERROR) exit(1);
    return true;
}

function run() {
    global $argc, $argv;
    if ($argc == 2 && $argv[1] == '--recent') {
        $week_ago = date('Y-m-d', time() - 7 * 24 * 60 * 60);
        $today = date('Y-m-d');
        $apps = get_changed_applications($week_ago, $today);
        write_csv($apps);
    } else if ($argc == 4 && $argv[1] == '--month' && is_numeric($argv[2]) && is_numeric($argv[3])) {
        $year = (int) $argv[2];
        $month = str_pad((int) $argv[3], 2, '0', STR_PAD_LEFT);
        $lastday = date('t', strtotime("$year-$month-01"));
        $apps = get_applications("$year-$month-01", "$year-$month-$lastday");
        write_csv(&$apps);
    } else if ($argc == 3 && $argv[1] == '--year' && is_numeric($argv[2])) {
        $year = (int) $argv[2];
        $apps = get_applications("$year-01-01", "$year-12-31");
        write_csv(&$apps);
    } else {
        global $scraper_name;
        fputs(STDERR, "Usage:\n");
        fputs(STDERR, "  php $scraper_name.php --recent\n");
        fputs(STDERR, "    Scrapes this week's applications\n");
        fputs(STDERR, "  php $scraper_name.php --month YYYY MM\n");
        fputs(STDERR, "    Scrapes one month\n");
        if (!empty($extra_help)) fputs(STDERR, $extra_help);
        die();
    }
}

function polite_delay() {
    global $crawl_delay;
    sleep($crawl_delay);
}

function write_csv(&$apps) {
    if (!$apps) return;
    $merged = array();
    foreach ($apps as $app) {
        $merged += $app;
    }
    $fields = array_keys($merged);
    fputcsv(STDOUT, $fields);
    foreach ($apps as $app) {
        $row = array();
        foreach ($fields as $key) {
            $row[] = @$app[$key];
        }
        fputcsv(STDOUT, $row);
        foreach ($app as $key => $value) {
            if (!in_array($key, $fields)) {
                trigger_error("Application field not in field list: $key", E_USER_WARNING);
            }
        }
    }
}

function http_request($url, $postvars=NULL) {
    static $curl;
    global $user_agent;
    if (empty($curl)) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_COOKIEFILE, '/dev/null');   // necessary to enable cookies
        curl_setopt($curl, CURLOPT_USERAGENT, $user_agent);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
    }
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_POST, !empty($postvars));
    if ($postvars) {
        $fields = array();
        foreach($postvars as $key=>$value) {
            $fields[] = $key.'='.$value;
        }
        curl_setopt($curl, CURLOPT_POSTFIELDS, join($fields, '&'));
    }
    $response = curl_exec($curl);
    if ($response === false) {
        var_dump(curl_error($curl));
        var_dump(curl_errno($curl));
        throw new Exception(curl_errno($curl) . ' ' . curl_error($curl));
    }
    $info = curl_getinfo($curl);
    if ($info['http_code'] == 302) {
        preg_match('/Location:\s+(.*)/i', substr($response, 0, $info['header_size']), $match);
        return "Location: $match[1]";
    }
    return substr($response, $info['header_size']);
}

function clean_html($s) {
    return trim(html_entity_decode(str_replace('&nbsp;', ' ', $s), ENT_QUOTES, 'UTF-8'));
}
