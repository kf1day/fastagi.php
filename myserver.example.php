<?php

require 'core.php';


class instance extends _FASTAGI {

	function action( $i, $status, $chanvars ) {
		if ( $i == 0 ) {  // first command
			return 'exec Dial "SIP/' . $chanvars['extension'] . ',30"'; // dial calling extension 
		}
		if ( $i == 1 && $status[0] == 200 && $status[1] == 0 ) {  // second, if timeout/declined
			return 'exec Dial "SIP/980,30"'; // dial our super-octopus-employee
			}
		if ( $i == 1 ) {  // second, otherway
			return ''; // empty result hangs up channel. Also, we can return 'hangup', but it generates next event with command return status
		}
		if ( $i == 2 && $status[0] == 200 && $status[1] == 0 ) {  // third, our super-octopus-employee went out
			return 'exec Queue "sales"'; // transfering calling party to queue "sales"
		}
	}
}

new instance( '127.0.0.1', 1038 );
