<?php

if (!defined('BANG_SYSLOG') || !BANG_SYSLOG) {
	if (is_callable('add_action')) {
	  require('main.php');
	} else {
		require_once('bang-syslog-logging.php');
	}
}
