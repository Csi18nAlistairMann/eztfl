<?php
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
?>
