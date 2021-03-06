<?php

include 'init.php';

$request = new Request();
$response = new Response($config['site_base'], $request->uri);
$site = new Site($request->uri, $response, $planning);
set_exception_handler(array($site, 'exception_handler'));

if ($request->matches('/^$/')) {
  $site->action_home();
} else if ($q = $request->matches('/^(about|contact)$/')) {
  $page = $q[1];
  $titles = array(
      "about" => "About this site",
      "contact" => "Contact us",
  );
  $response->render("page-$page", array('title' => $titles[$page]));
} else if ($request->matches('/^stats$/')) {
  $site->action_stats();
} else if ($q = $request->matches('/^(latest|all)$/', array('bounds'))) {
  $site->action_api_area($q[1], $q['bounds']);
} else if ($q = $request->matches('/^near$/', array('center'))) {
  $site->action_api_near($q['center']);
} else if ($q = $request->matches('/^councils$/')) {
  $site->action_council_list();
} else if ($q = $request->matches('/^search$/', array('q'))) {
  $site->action_search($q['q']);
} else if (($q = $request->matches('/^([A-Za-z]+)$/')) && $planning->is_council_shortname($q[1])) {
  $site->action_council_details($q[1]);
} else if (($q = $request->matches('/^([A-Za-z]+)\/app$/', array('ref'))) && $planning->is_council_shortname($q[1])) {
  $site->action_api_app($q['ref'], $q[1]);
} else if (($q = $request->matches('/^feed$/'))) {
  $site->action_feed();
} else if (($q = $request->matches('/^feed\/([A-Za-z]+)$/')) && $planning->is_council_shortname($q[1])) {
  $site->action_feed($q[1]);
} else {
  $response->error(404);
}
