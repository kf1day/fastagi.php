<?php

require dirname( __FILE__ ).'/traits/verbose.php';

abstract class _AMCLIENT {
	use verbose;

	const AST_EOL = "\r\n";
	const AST_EOM = "\r\n\r\n";

	protected $_ptr = false;
	protected $_buf = '';

	final public function __construct( $host, $port, $user, $pass ) {
		$this->vinit();
		$i = 0;
		$this->_ptr = @stream_socket_client( 'tcp://'.$host.':'.$port, $errno, $errstr );
		while ( ! $this->_ptr ) {
			if ( $i % 60 == 0 ) {
				$this->say( 0, 'Connection to AMI failed: <'.$errstr.'>, retrying...' );
			}
			$i++;
			sleep( 1 );
			$this->_ptr = @stream_socket_client( 'tcp://'.$host.':'.$port, $errno, $errstr );
		}
		stream_set_timeout( $this->_ptr, 0, 400000);
		$this->say( 2, 'Connection to AMI success at #' . ( $i + 1 ) );
		$srv = fread( $this->_ptr, 1024 );
		$this->say( 2, 'Server signature: '.trim( $srv ) );
		$this->send( 'Login', [ 'Username' => $user, 'Secret' => $pass ] );
		while ( 1 ) {
			$buf = $this->read();
			foreach ( $buf as $e ) {
				if ( $e['Response'] == 'Success' ) {
					$this->say( 1, 'Authentication accepted' );
					break( 2 );
				}
				if ( $e['Response'] == 'Error' ) {
					$this->say( 0, 'Authentication rejected, giving up' );
					return false;
				}
			}
			sleep( 1 );
		}

		
		$this->init();
		$this->main();
	}

	final public function __destruct() {
		if ( $this->_ptr ) {
			$this->send( 'Logoff' );
			fclose( $this->_ptr );
			$this->_ptr = false;
		}
	}
	

	final protected function main() {
		while( 1 ) {
			$buf = $this->read();
			foreach ( $buf as $e ) {
				if ( $e['Event'] == 'Shutdown' ) {
					$this->say( 1, 'Asterisk is shutting down' );
					return;
				}
//				echo '> '.$e['Event'].PHP_EOL;
				$this->action( $e );
			}
		}
	}

	final protected function send( $action, $param = [] ) {
		$paramStr = '';
		foreach( $param as $k => $v ) $paramStr .= $k . ': ' . $v.self::AST_EOL;
		fwrite( $this->_ptr, 'Action: ' . $action . self::AST_EOL . $paramStr . self::AST_EOL );
		
	}

	final protected function read() {
		$buf = $this->_buf;
		do {
			$buf .= fread( $this->_ptr, 1024 );
			$ctl = socket_get_status( $this->_ptr );
		} while ( $ctl['unread_bytes']  );
		$ret = explode( self::AST_EOM, $buf );
		$this->_buf = array_pop( $ret );

		foreach ( $ret as &$packet ) {
			$keyval = explode ( self::AST_EOL, $packet );
			$packet = [ 'Event' => false, 'Response' => false ];
			foreach ( $keyval as $v ) {
				$buf = explode( ': ', $v );
				if ( count( $buf ) > 2 ) {
					echo 'WARNING: bad string: "'.$v.'"'.PHP_EOL;
				} else {
					$packet[$buf[0]] = $buf[1];
				}
			}
		}

		return $ret;
	}

	function init(){}

	abstract function action( $e );
}
