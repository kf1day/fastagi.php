<?php

abstract class _AMICLIENT {

	const AST_EOL = "\r\n";
	const AST_EOM = "\r\n\r\n";

	protected $_ptr = false;
	protected $_buf = '';


	final public function __construct( $host, $port, $user, $pass ) {
		$i = 0;
		$this->_ptr = @fsockopen( $host, $port, $a, $b, 10 );
		while ( ! $this->_ptr ) {
			if ( $i % 60 == 0 ) {
				echo( 'Connection to AMI failed, retrying...' . PHP_EOL );
			}
			$i++;
			sleep( 1 );
			$this->_ptr = @fsockopen( $host, $port, $a, $b, 10 );
		}
		stream_set_timeout( $this->_ptr, 0, 400000);
		echo ( 'Connection to AMI success at #' . ( $i + 1 ) . PHP_EOL );
		$srv = fread( $this->_ptr, 1024 );
		echo 'Server signature: '.trim( $srv ).PHP_EOL;
		$this->send( 'Login', [ 'Username' => $user, 'Secret' => $pass ] );
		$this->read();
		$this->init();
		$this->run();
	}

	final public function __destruct() {
		if ( $this->_ptr ) {
			$this->send( 'Logoff' );
			fclose( $this->_ptr );
			$this->_ptr = false;
		}
	}

	final protected function run() {
		while( 1 ) {
			$buf = $this->read();
			foreach ( $buf as $packet ) {
				$e = [ 'Event' => false, 'Response' => false ];
				$param = explode( self::AST_EOL, $packet );
				foreach ( $param as $pair ) {
					$pair = explode( ': ', $pair );
					$e[$pair[0]] = $pair[1];
				}
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
		$ctl = [];
		$buf = $this->_buf;
		$ret = [];
		do {
			$buf .= fread( $this->_ptr, 1024 );
			$ctl = socket_get_status( $this->_ptr );
		} while ( $ctl['unread_bytes']  );
		$ret = explode( self::AST_EOM, $buf );
		$this->_buf = array_pop( $ret );

		return $ret;
	}

	function init(){}

	abstract function action( $e );
}
