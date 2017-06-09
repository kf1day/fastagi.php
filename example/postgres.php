<?php

require '../core.php';
require '../pgsql.php';

class instance extends _FASTAGI {
	
	private $dba = null;
	
	function init() {

		$this->dba = new pgsql( '', '', '', 'asterisk', 's3cret' );
		$this->message( 1, 'DBA connection OK' );
	}

	function action( $v, $status, &$e ) {
		if ( $v > 10 ) return; // loop-killer

		$e['arg_1'] ?? $e['arg_1'] = '';
		$e['callerid'] ?? $e['callerid'] = '';
		
		if ( $e['arg_1'] == 'route-discover' && $e['callerid'] !== '' ) {
			$e['arg_1'] = '';
			$r = $this->dba->get( 'leed', 'target', [ 'phone' => '\''.$e['callerid'].'\'' ] );
			if ( $r == 1 ) {
				$tgt = $this->dba->fetch()[0];
				return 'exec Dial '.$tgt.',7,m';
			} else {
				return;
			}
		}
	}
}

new instance( '127.0.0.1', 1038 );
