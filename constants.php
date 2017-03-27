<?php

define('SERVER_NAME_AND_PORT',
	   $_SERVER['SERVER_NAME'] . ':'
	   . $_SERVER['SERVER_PORT']);
define('SERVER_DETS', (@$_SERVER['HTTPS'] == 'on')
	   ? 'https://' . SERVER_NAME_AND_PORT
	   : 'http://' . SERVER_NAME_AND_PORT);

define('INTENDED_SERVER', 'eztfl.pectw.net');
define('INTENDED_SUBDIR', '/bus/');
define('INTENDED_SERVER_DETS',
	   'http://' . INTENDED_SERVER . ':80');

define('PRESENT_IFRAME', 0);
define('PRESENT_V1', 1);

define('SETTINGS_NAME', 'settings');
define('FAVES_NAME', 'faves');
define('NUM_FAVES_NAME', 'NumFaves');
define('RECENTS_NAME', 'recents');
define('NUM_RECENTS_NAME', 'NumRecents');
define('DEFAULT_NUM_FAVES', 5);
define('DEFAULT_NUM_RECENTS', 5);
define('PRESENTATION_NAME', 'PresentationStyle');
define('DEFAULT_PRESENTATION_STYLE', PRESENT_V1);

define('URI', $_SERVER['REQUEST_URI']);

define('LEN_BUS_CODE', 5);
define('COUNTDOWN_URL', 'http://m.countdown.tfl.gov.uk/arrivals/');

define('VIEWPORT_WIDTH', 360);
define('STAR_BLACK', '&#9733');
define('STAR_WHITE', '&#9734');
define('DIVIDER', ' |');

define('PERSISTENT_COOKIE_NAME', 'eztfl');
define('PERSISTENT_COOKIE_LIFE', time() + 60 * 60 * 24 * 365 * 10);  // 10 years

define('NOTHING_STORED_TEXT', '-none-');
define('FAVES_TITLE', 'Faves:');
define('CLEAR_FAVES_VERB', 'clear_faves');
define('CLEAR_FAVES_TEXT', 'clear faves');
define('RECENTS_TITLE', 'Recents:');
define('CLEAR_RECENTS_VERB', 'clear_recents');
define('CLEAR_RECENTS_TEXT', 'clear recents');
define('STOPNO_VERB', 'stopno');
define('RELOAD_NAME', 'reload');
define('RELOAD_TEXT', 'Reload ');

define('ERR_NO_ERROR', 0);
define('ERR_TFL_NOSVC', 1);
define('ERR_CANT_CONNECT', 2);
define('ERR_BAD_NAPTAN', 3);

?>
