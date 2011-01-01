<?php
define('TESTS_DIR', '/var/www/balimich/tests/');
define('BASE_DIR', '/var/www/balimich/UJ/0.2/');
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'mlsnTest');
define('EMAIL_SENDER', 'EmailSenderTest');

class EmailSenderTest {
	function sendRegistrationToken($toEmail, $token) {
		if(!isset($GLOBALS['registrationTokensSent'])) {
			$GLOBALS['registrationTokensSent'] = array();
		}
		$GLOBALS['registrationTokensSent'][$toEmail] = $token;
	}
}
