<?php

/* -- Debugging -- */

if ($_SERVER['REMOTE_ADDR'] == '86.164.45.207') {
	error_reporting(E_ALL);
	ini_set('display_errors', 'On');

} else {
	error_reporting(E_ERROR);
	ini_set('display_errors', 'Off');
}

/*
  TODO
*/

/* -- Rate limiting -- */
/*
  At https://tfl.gov.uk/corporate/terms-and-conditions/transport-data-service
  we're required to limit requests to 300 per minute. EZTFL manages this via
  iptables.

  # tfl use five machines at api.tfl.gov.uk 104.16.24.236, 104.16.25.236 ... 104.16.28.236
  tfl=104.16.24.236/19
  iptables -A OUTPUT -o $ifout -p TCP -s $any -d $tfl -m owner --uid-owner www-data --dport 443 -m limit --limit 300/minute --limit-burst 300 -j ACCEPT
  iptables -A OUTPUT -o $ifout -p TCP -s $any -d $tfl -m owner --uid-owner www-data --dport 443 -j DROP
*/

require_once('helper_functions.php');
require_once('constants.php');

/* -- a later include, not in the repo, may overwrite these -- */

$app_id = '';
$app_key = '';

/* -- people should arrive at "/bus/" not "/bus" so redirect them if necc -- */

// This also redirects people who guess tfl.php
//
if (preg_match('#^' . INTENDED_SUBDIR . '.*$#', URI) !== 1) {
	header('Location: ' . INTENDED_SERVER_DETS . INTENDED_SUBDIR);
	exit;
}

/* -- Preprocessing checks for what we'll be asked to do -- */

// Check if we've been asked to favourite a bus stop.
//
$fave_code = '';
if (isset($_GET['fave_code']) && is_numeric($_GET['fave_code'])) {
	$fave_code = intval($_GET['fave_code']);
}

$clear_faves = false;
if (isset($_GET[CLEAR_FAVES_VERB])) {
	$clear_faves = true;
}

$clear_recents = false;
if (isset($_GET[CLEAR_RECENTS_VERB])) {
	$clear_recents = true;
}

/* -- Persistent storage -- */

// Retrieve or initialise cookie, clear faves/recents if requested
//
$dflt_cookie = array(SETTINGS_NAME => array(NUM_FAVES_NAME => DEFAULT_NUM_FAVES,
											NUM_RECENTS_NAME => DEFAULT_NUM_RECENTS,
											PRESENTATION_NAME => DEFAULT_PRESENTATION_STYLE),
					 FAVES_NAME => array(),
					 RECENTS_NAME => array());
for ($a = 0; $a < $dflt_cookie[SETTINGS_NAME][NUM_FAVES_NAME]; $a++) {
	$dflt_cookie[FAVES_NAME][] = '';
}
for ($a = 0; $a < $dflt_cookie[SETTINGS_NAME][NUM_RECENTS_NAME]; $a++) {
	$dflt_cookie[RECENTS_NAME][] = '';
}
$check_cookie = $dflt_cookie;
if (isset($_COOKIE[PERSISTENT_COOKIE_NAME])) {
	$check_cookie = unserialize($_COOKIE[PERSISTENT_COOKIE_NAME]);
}
if ($clear_faves) {
	for ($a = 0; $a < sizeof($check_cookie[FAVES_NAME]); $a++) {
		$check_cookie[FAVES_NAME][$a] = '';
	}
}
if ($clear_recents) {
	for ($a = 0; $a < sizeof($check_cookie[RECENTS_NAME]); $a++) {
		$check_cookie[RECENTS_NAME][$a] = '';
	}
}

// sanitise user's cookie
//
$cookie_good = true;
if ($check_cookie !== $dflt_cookie) {
	if (!is_int($check_cookie[SETTINGS_NAME][NUM_FAVES_NAME])) {
		$cookie_good = false;
	}
	if (!is_int($check_cookie[SETTINGS_NAME][NUM_RECENTS_NAME])) {
		$cookie_good = false;
	}
	if (!is_int($check_cookie[SETTINGS_NAME][PRESENTATION_NAME])) {
		$cookie_good = false;
	}

	// add blanks if we have fewer of them than settings suggests
	//
	if (sizeof($check_cookie[FAVES_NAME] <
			   $check_cookie[SETTINGS_NAME][NUM_FAVES_NAME])) {
		for ($a = sizeof($check_cookie[FAVES_NAME]);
			 $a < $check_cookie[SETTINGS_NAME][NUM_FAVES_NAME]; $a++) {
			$check_cookie[FAVES_NAME][] = '';
		}
	}
	if (sizeof($check_cookie[RECENTS_NAME] <
			   $check_cookie[SETTINGS_NAME][NUM_RECENTS_NAME])) {
		for ($a = sizeof($check_cookie[RECENTS_NAME]);
			 $a < $check_cookie[SETTINGS_NAME][NUM_RECENTS_NAME];
			 $a++) {
			$check_cookie[RECENTS_NAME][] = '';
		}
	}

	// remove oldest entries if we have more of them than settings suggests
	//
	if (sizeof($check_cookie[FAVES_NAME] >
			   $check_cookie[SETTINGS_NAME][NUM_FAVES_NAME])) {
		for ($a = sizeof($check_cookie[FAVES_NAME]);
			 $a > $check_cookie[SETTINGS_NAME][NUM_FAVES_NAME]; $a--) {
			array_pop($check_cookie[FAVES_NAME]);
		}
	}
	if (sizeof($check_cookie[RECENTS_NAME] >
			   $check_cookie[SETTINGS_NAME][NUM_RECENTS_NAME])) {
		for ($a = sizeof($check_cookie[RECENTS_NAME]);
			 $a > $check_cookie[SETTINGS_NAME][NUM_RECENTS_NAME];
			 $a--) {
			array_pop($check_cookie[RECENTS_NAME]);
		}
	}

	// All remaining entries should be either empty string, or
	// numeric
	//
	for ($a = 0; $a < sizeof($check_cookie[FAVES_NAME]); $a++) {
		if (!is_numeric($check_cookie[FAVES_NAME][$a])
			&& $check_cookie[FAVES_NAME][$a] !== '') {
			$cookie_good = false;
		}
	}
	for ($a = 0; $a < sizeof($check_cookie[RECENTS_NAME]); $a++) {
		if (!is_numeric($check_cookie[RECENTS_NAME][$a])
			&& $check_cookie[RECENTS_NAME][$a] !== '') {
			$cookie_good = false;
		}
	}
}

// if user's cookie good, copy over just the bits we tested
// as good
//
$pers_cookie = $dflt_cookie;
if ($cookie_good === true) {
	$pers_cookie[SETTINGS_NAME][NUM_FAVES_NAME] =
		$check_cookie[SETTINGS_NAME][NUM_FAVES_NAME];
	$pers_cookie[SETTINGS_NAME][NUM_RECENTS_NAME] =
		$check_cookie[SETTINGS_NAME][NUM_RECENTS_NAME];
	$pers_cookie[SETTINGS_NAME][PRESENTATION_NAME] =
		$check_cookie[SETTINGS_NAME][PRESENTATION_NAME];

	for ($a = 0; $a < $pers_cookie[SETTINGS_NAME][NUM_FAVES_NAME]; $a++) {
		$pers_cookie[FAVES_NAME][$a] = $check_cookie[FAVES_NAME][$a];
	}
	for ($a = 0; $a < $pers_cookie[SETTINGS_NAME][NUM_RECENTS_NAME]; $a++) {
		$pers_cookie[RECENTS_NAME][$a] =
			$check_cookie[RECENTS_NAME][$a];
	}
}

/* -- Process the bus stop code itself -- */

// If we see the GET from form submission, issue an immediate reload
// to the RESTful link I want browsers to see
//
$recent_code = '';
$iframe_target = '';        // no opinion on what to show
if (isset($_GET[STOPNO_VERB])) {
	if (isset($_GET[RELOAD_NAME])) {
		$recent_code = intval(substr($_GET[RELOAD_NAME],
									 strlen(RELOAD_TEXT),
									 LEN_BUS_CODE));

	} else {
		if (preg_match('/^\d{' . LEN_BUS_CODE . '}$/', $_GET[STOPNO_VERB]) === 1) {
			$recent_code = intval($_GET[STOPNO_VERB]);
		}
	}
	header('Location: ' . SERVER_DETS .
		   substr(URI, 0, strlen(INTENDED_SUBDIR)) . $recent_code);
	exit;

	// Otherwise if we see the RESTful request, extract the bus stop code
	// or note a failure
	//
} else {
	if (preg_match('#^' . INTENDED_SUBDIR . '.+$#', URI) === 1) {
		$iframe_target = false;
	}    // specifically do not show
	if (preg_match('#^' . INTENDED_SUBDIR . '\d\d\d\d\d.*$#', URI) === 1) {
		$recent_code =
			intval(substr(URI, strlen(INTENDED_SUBDIR), LEN_BUS_CODE));
	}
}

/* -- Processing the recents links -- */

// If we have a code, then add it to the recents array if
// we don't have it already; then construct links to those
// we DO have
//
$recent_links = '';
if ($recent_code !== '') {
	$found = false;
	for ($a = 0; $a < $pers_cookie[SETTINGS_NAME][NUM_RECENTS_NAME]; $a++) {
		if ($pers_cookie[RECENTS_NAME][$a] === $recent_code) {
			$found = true;
		}
	}
	if ($found === false) {
		for ($a = $pers_cookie[SETTINGS_NAME][NUM_RECENTS_NAME] - 1;
			 $a > 0; $a--) {
			$pers_cookie[RECENTS_NAME][$a] =
				$pers_cookie[RECENTS_NAME][$a - 1];
		}
		$pers_cookie[RECENTS_NAME][0] = $recent_code;
	}
}

for ($a = 0; $a < $pers_cookie[SETTINGS_NAME][NUM_RECENTS_NAME]; $a++) {
	if (is_numeric($pers_cookie[RECENTS_NAME][$a])) {
		$recent_links .=
			' <a href="' . INTENDED_SERVER_DETS . INTENDED_SUBDIR .
			$pers_cookie[RECENTS_NAME][$a].'">' .
			$pers_cookie[RECENTS_NAME][$a].'</a>' . DIVIDER;
	}
}

if ($recent_links !== '') {
	$recent_links =
		RECENTS_TITLE . ' ['.substr($recent_links, 0,
									strlen($recent_links) -
									strlen(DIVIDER)) . '] <a href="' .
		INTENDED_SERVER_DETS . INTENDED_SUBDIR . '?' .
		CLEAR_RECENTS_VERB . '">' . CLEAR_RECENTS_TEXT.'</a>';
} else {
	$recent_links = RECENTS_TITLE . ' ' . NOTHING_STORED_TEXT;
}

/* -- Processing the faves links -- */

// Construct the faves links if any present, note if client has
// already faved this stop, and fave it if requested to
//
$faves_links = '';
$fave_star_html = STAR_WHITE;
$fave_found = false;
if ($fave_code !== '') {
	for ($a = 0; $a < $pers_cookie[SETTINGS_NAME][NUM_FAVES_NAME]; $a++) {
		if ($pers_cookie[FAVES_NAME][$a] === $fave_code) {
			$fave_found = true;
		}
	}
	if ($fave_found === false) {
		for ($a = $pers_cookie[SETTINGS_NAME][NUM_FAVES_NAME] - 1;
			 $a > 0; $a--) {
			$pers_cookie[FAVES_NAME][$a] =
				$pers_cookie[FAVES_NAME][$a - 1];
		}
		$pers_cookie[FAVES_NAME][0] = $fave_code;
	}
}
for ($a = 0; $a < $pers_cookie[SETTINGS_NAME][NUM_FAVES_NAME]; $a++) {
	if (is_numeric($pers_cookie[FAVES_NAME][$a])) {
		$faves_links .=
			' <a href="' . INTENDED_SERVER_DETS . INTENDED_SUBDIR .
			$pers_cookie[FAVES_NAME][$a] . '">' .
			$pers_cookie[FAVES_NAME][$a] . '</a>' . DIVIDER;
		if ($pers_cookie[FAVES_NAME][$a] == $recent_code) {
			$fave_star_html = STAR_BLACK;
		}
	}
}

if ($faves_links !== '') {
	$faves_links =
		FAVES_TITLE . ' [' . substr($faves_links, 0,
									strlen($faves_links) -
									strlen(DIVIDER)) . '] <a href="' .
		INTENDED_SERVER_DETS . INTENDED_SUBDIR . '?' .
		CLEAR_FAVES_VERB . '">' . CLEAR_FAVES_TEXT . '</a>';
} else {
	$faves_links = FAVES_TITLE . ' ' . NOTHING_STORED_TEXT;
}

/* -- Assess what form the star before faves will take -- */

$code_in_faves_found = false;
if ($recent_code !== '') {
	for ($a = 0; $a < $pers_cookie[SETTINGS_NAME][NUM_FAVES_NAME]; $a++) {
		if ($pers_cookie[FAVES_NAME][$a] === $recent_code) {
			$code_in_faves_found = true;
		}
	}
}
if ($fave_code !== '') {
	for ($a = 0; $a < $pers_cookie[SETTINGS_NAME][NUM_FAVES_NAME]; $a++) {
		if ($pers_cookie[FAVES_NAME][$a] === $fave_code) {
			$code_in_faves_found = true;
		}
	}
}

if ($recent_code === '' && $fave_code === '') {
	$show_star = 'nolink';
	$fave_star_html = STAR_WHITE;

} elseif ($code_in_faves_found === true) {
	$show_star = 'link in faves';
	$fave_star_html = STAR_BLACK;

} else {
	$show_star = 'link not in faves';
	$fave_star_html = STAR_BLACK;
}

/* -- Return the amended cookie to user -- */

setcookie(PERSISTENT_COOKIE_NAME, serialize($pers_cookie),
		  PERSISTENT_COOKIE_LIFE, INTENDED_SUBDIR, INTENDED_SERVER, false,
		  false);

/* -- Now show the user what we got -- */

echo '<html><head>';
// bodge to my phone's width. BODGE JOB!
echo '<meta name="viewport" content="width=' . VIEWPORT_WIDTH .
', initial-scale=1">';
// bodge to stop favicon requests. BODGE JOB!
echo '<link rel="icon" href="data:;base64,iVBORw0KGgo=">';
echo '</head>';
echo '<body>';

if ($iframe_target === false && ($clear_faves !== false || $fave_code !== '')) {
	echo 'Stop no. was ' . LEN_BUS_CODE .
		' consecutive digits. Not any more? This site needs a rewrite' . "\n";
	$iframe_target = '';
}

echo '
<form action="' . INTENDED_SERVER_DETS . INTENDED_SUBDIR . '" method="get">
Stop No.: <input type="text" name="' . STOPNO_VERB . '"><br>
<input type="submit" value="*Go!*"><input type="reset" value="Clear stop no.">
<input type="submit" name="' . RELOAD_NAME . '" value="' . RELOAD_TEXT . $recent_code . '"><br>
</form>';
echo '[ ';
if ($show_star === 'nolink' || $show_star === 'link in faves') {
	echo $fave_star_html;

} else {
	echo '<a href="' . INTENDED_SERVER_DETS . INTENDED_SUBDIR . '?fave_code=' .
		$recent_code . '">' . $fave_star_html . '</a>';
}

echo ' ] ' . $faves_links . '<br>' . $recent_links . '<br>';
if ($pers_cookie[SETTINGS_NAME][PRESENTATION_NAME] === PRESENT_IFRAME) {
	if ($recent_code !== '') {
		$iframe_target = COUNTDOWN_URL . $recent_code;

	} elseif ($fave_code !== '') {
		$iframe_target = COUNTDOWN_URL . $fave_code;
	}
	echo '<iframe width=100% height=100%  src="' . $iframe_target .
		'"></iframe>';

} elseif ($pers_cookie[SETTINGS_NAME][PRESENTATION_NAME] === PRESENT_V1) {
	echo '<hr>';
	require_once('bus-map.php');

	$naptan_code = false;
	if ($recent_code !== '' && isset($bus_code_map[$recent_code])) {
		$naptan_code = $bus_code_map[$recent_code];

	} elseif ($fave_code !== '' && isset($bus_code_map[$fave_code])) {
		$naptan_code = $bus_code_map[$fave_code];
	}

	$service_blocked = ERR_NO_ERROR;
	if ($naptan_code === '') {
		$service_blocked = ERR_BAD_NAPTAN;

	} else {
		$response_arr = array();
		if ($naptan_code !== false && $naptan_code !== '') {
			require_once('credents.php');
			$streamContext = stream_context_create(['ssl' =>
													['cafile' =>
													 '/etc/apache2/cacert.pem',
													 'verify_peer' => true,
													 'verify_peer_name' => true
													 ]]);
			$url = 'https://api.tfl.gov.uk/StopPoint/' .
				$naptan_code . '/arrivals';
			if ($app_id !== '' && $app_key !== '') {
				$url .= '?app_id=' . $app_id;
				$url .= '&app_key=' . $app_key;
			}
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
		}
	}

	if ($service_blocked !== ERR_NO_ERROR) {
		switch ($service_blocked) {
		case ERR_TFL_NOSVC:
			echo 'Sorry! TFL currently blocking'."\n";
			break;
		case ERR_CANT_CONNECT:
			// this also includes our own rate-limiting
			echo 'Sorry! Failed to connect to TFL\'s API'."\n";
			break;
		case ERR_BAD_NAPTAN:
			// bus map has an sms code pointing to a bad naptan code
			echo 'Sorry! TFL have that stop no., but don\'t give the naptan code for it'."\n";
			break;
		default:
			echo 'Sorry! Service blocked for unknown reason'."\n";
		}
	} else {
		if ($response_arr === array ()) {
			echo '- No arrivals expected -<br />';

		} elseif (sizeof($response_arr > 0)) {
			//Display arrivals in order of arrival
			$response_arr_byTime = array();
			foreach ($response_arr as $arrival) {
				$response_arr_byTime[$arrival['timeToStation']][] =
					$arrival['lineId'];
			}
			ksort($response_arr_byTime);
			echo '<table><tr><th>Route</th><th>Expected</th></tr>';
			foreach ($response_arr_byTime as $arrival => $vehiclesArr) {
				foreach ($vehiclesArr as $lineId) {
					echo '<tr><td align=center>' . $lineId .
						'</td>';
					echo '<td align=center>' .
						howDueIsArrival($arrival) .
						'</td></tr>';
				}
			}
			echo '</table>';

			//Display arrivals in order of route
			$response_arr_byRoute = array();
			foreach ($response_arr as $arrival) {
				if (!isset($response_arr_byRoute[$arrival['lineId']])
					|| $response_arr_byRoute[$arrival['lineId']][0] >
					$arrival['timeToStation']) {
					$response_arr_byRoute[$arrival['lineId']][0] =
						$arrival['timeToStation'];
				}
			}
			ksort($response_arr_byRoute);
			echo '<table><tr><th>Route</th><th>Next</th></tr>';
			foreach ($response_arr_byRoute as $route => $arrivalsArr) {
				foreach ($arrivalsArr as $moment) {
					echo '<tr><td align=center>' . $route . '</td>';
					echo '<td align=center>' . howDueIsArrival($moment) .
						'</td></tr>';
				}
			}
			echo '</table>';
		}
	}
}

echo '<hr>';
echo '[ <a href="http://eztflwiki.mpsvr.com/index.php/Main_Page">About</a> ]';
echo '</body></html>';
?>
