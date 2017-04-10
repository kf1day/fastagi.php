<?php

/* shell command:

VERBOSE=4 php -q worker-example.php

*/


$v = ( isset( $_SERVER['VERBOSE'] ) ) ? $_SERVER['VERBOSE'] : -1 ;

require 'fastagi.php';

( new _FASTAGI( '0.0.0.0', '1038', $v ) )->worker( 'command' );


function command( $ver, $status, $vars ) {
	echo "worker was event version $ver, status $status[0] ($status[1])\n";
	if ( $ver < 3 ) {
		return 'exec Dial "SIP/980,10"';
	} else {
		return 'hangup';
	}
}
