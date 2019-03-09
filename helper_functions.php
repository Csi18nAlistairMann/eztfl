<?php

define('STOPPOINT_PREFIX', 'https://api.tfl.gov.uk/StopPoint/');

define('ENMSG_ERR_TFL_NOSVC', 'Sorry! TFL currently blocking'."\n");
define('ENMSG_ERR_CANT_CONNECT', 'Sorry! Failed to connect to TFL\'s API'."\n");
define('ENMSG_ERR_BAD_NAPTAN', 'Sorry! TFL have that stop no., but don\'t give the naptan code for it'."\n");
define('ENMSG_ERR_SVC_BLOCKED_DEFAULT', 'Sorry! Service blocked for unknown reason'."\n");

function howDueIsArrival($time)
{
  if ($time < 60) {
    return "due";
  } elseif ($time < 120) {
    return "1 minute";
  } else {
    return intval($time / 60 + 1) . " minutes";
  }
}

function sendSearchQueryToTFL($searchterm) {
  require_once('credents.php');
  $url = STOPPOINT_PREFIX . 'Search?query=' .
    $searchterm;
  if ($app_id !== '' && $app_key !== '') {
    $url .= '&app_id=' . $app_id;
    $url .= '&app_key=' . $app_key;
  }
  return sendRequestToTFLCore($url);
}

function sendRequestToTFL($naptan_code) {
  require_once('credents.php');
  $url = STOPPOINT_PREFIX . $naptan_code . '/arrivals';
  if ($app_id !== '' && $app_key !== '') {
    $url .= '?app_id=' . $app_id;
    $url .= '&app_key=' . $app_key;
  }
  return sendRequestToTFLCore($url);
}

function sendRequestToTFLCore($url) {
  $service_blocked = ERR_NO_ERROR;
  $streamContext = stream_context_create(['ssl' =>
					  ['cafile' =>
					   '/etc/apache2/cacert.pem',
					   'verify_peer' => true,
					   'verify_peer_name' => true
					   ]]);
  $response_json =
    @file_get_contents($url, false, $streamContext);
  if ($response_json === false) {
    $response_arr = array();
    $service_blocked = ERR_CANT_CONNECT;

  } else {
    $response_arr = json_decode($response_json, true);
    if ($response_arr === null) {
      $response_arr = array();
      $service_blocked = ERR_TFL_NOSVC;
    }
  }
  return array('error' => $service_blocked,
	       'response' => $response_arr);
}

function getServiceBlockedMessage($service_blocked) {
  switch ($service_blocked) {
  case ERR_TFL_NOSVC:
    return ENMSG_ERR_TFL_NOSVC; break;
  case ERR_CANT_CONNECT:
    // this also includes our own rate-limiting
    return ENMSG_ERR_CANT_CONNECT; break;
  case ERR_BAD_NAPTAN:
    // bus map has an sms code pointing to a bad naptan code
    return ENMSG_ERR_BAD_NAPTAN; break;
  default:
    return ENMSG_ERR_SVC_BLOCKED_DEFAULT; break;
  }
}

?>
