<?php

class _FASTAGI {

	const ST_INIT = 0x01; // new connection => read chanvars
	const ST_RECV = 0x02; // writing command done => read responce
	const ST_SEND = 0x04; // command is set to buffer => write buffer


	private $sock = null;
	private $rxf = false;  // reindex is nessesary flag
	private $verbosity = -1;

	private $pt = []; // [0]stream, [1]status, [2]buffer, [3]chanvars, [4]event version

	public function __construct( $host, $port, $verbose = -1 ) {
		$this->verbosity = $verbose;
		$this->message( 1, 'Verbosity is set to '.$verbose );
		$sock = @stream_socket_server( 'tcp://'.$host.':'.$port, $errno, $errstr );
		if ( ! $sock ) {
			echo $errstr.PHP_EOL;
			exit -1;
		}
		$this->connect( $sock, 0 );
		$this->sock = &$this->pt[0][0];
	}

	public function worker( $callback ) {
		$e = $w = null;
		while ( 1 ) {
			if ( $this->rxf ) $this->reindex();
			$r = $this->list_sock();
			$this->message( 4, '.' );

			if ( ! stream_select( $r, $w, $e, null ) ) break;
			
			// check incoming connection
			if ( isset( $r[0] ) ) {
				$this->connect( stream_socket_accept( $this->sock, -1) );
				unset( $r[0] );
			}

			// processing sockets
			foreach ( $r as $k => $rd ) {
				$a = &$this->pt[$k];
				$buf = fread( $rd, 1024 );
				if ( ! $buf ) {
					$this->message( 0, '#'.$k.' Read error' );
					$this->disconnect( $k );
					continue;
				}
				$a[2] .= $buf;
				if ( $a[1] == self::ST_INIT && ( strpos( $a[2], "\n\n" ) !== false ) ) {
					$a[3] = $this->parse( $a[2] );
					$this->message( 2, '#'.$k.' Argument parsing done, executing callback...' );
					$a[2] = call_user_func( $callback, $a[4]++, [ 200, 0 ], $a[3] );
					$a[1] = self::ST_SEND;
					$this->message( 2, '#'.$k.' Write buffer is set' );
				} elseif ( $a[1] == self::ST_RECV && ( strpos( $a[2], "\n" ) !== false ) ) {
					if ( $a[2] == "HANGUP\n" ) {
						$this->message( 2, '#'.$k.' Caller hangs up' );
						$this->disconnect( $k );
						continue;
					} elseif ( preg_match( '/^(\d+)\s(?:result=)?(.*?)\s+/', $a[2], $r ) ) {
						$this->message( 2, '#'.$k.' Responce was recieved, executing callback...' );
						$a[2] = call_user_func( $callback, $a[4]++, [ $r[1], $r[2] ], $a[3] );
						$a[1] = self::ST_SEND;
						$this->message( 2, '#'.$k.' Write buffer is set' );
					} else {
						$this->message( 2, '#'.$k.' Undefined response: "'.trim( $a[2] ).'"' );
						$this->disconnect( $k );
						continue;
					}
				}
				if ( $a[1] == self::ST_SEND ) {
					if ( ! $a[2] ) {
						$this->message( 2, '#'.$k.' Nothing to write' );
						$this->disconnect( $k );
						continue;
					}
					$buf = fwrite( $rd, $a[2]."\n" );
					if ( ! $buf ) {
						$this->message( 0, 'Write error' );
						$this->disconnect( $k );
						continue;
					}
					$a[2] = substr( $a[2], $buf - 1 );
					if ( ! $a[2] ) {
						$a[1] = self::ST_RECV;
						$this->message( 2, '#'.$k.' Writing done, waiting for responce' );
					}
				}
			}
		}
	}

	private function connect( $pt, $i = false ) {
		if ( $i === false ) {
			$this->message( 1, 'Accepting connection' );
			$this->pt[] = [ $pt, self::ST_INIT, '', [], 0 ];
		} else {
			$this->pt[$i] = [ $pt, self::ST_INIT, '', [], 0 ];
		}
	}

	private function disconnect( $i ) {
		$this->message( 1, 'Closing connection' );
		fclose( $this->pt[$i][0] );
		unset( $this->pt[$i] );
		$this->rxf = true;
	}
	
	private function reindex() {
		$this->message( 1, 'Reindexing' );
		$this->pt = array_values( $this->pt );
		$this->rxf = false;
	}

	private function list_sock() {
		$fff = [];
		foreach( $this->pt as $k => $v ) {
			$fff[$k] = $v[0];
		}
		return $fff;
	}

	private function parse( $s ) {
		$fff = [];
		$tmp = explode( "\n", $s );
		foreach ( $tmp as $v ) {
			if ( preg_match( '/^agi_(.*?): (.*)$/', $v, $r ) ) {
				$fff[$r[1]] = $r[2];
			}
		}
		return $fff;
	}
	
	private function message( $level, $string ) {
		if ( $level < 0 ||  $level > $this->verbosity ) return false;
		switch ( $level ) {
			case 0:
				$string = '[error] '.$string;
				break;
			case 1:
				$string = '[notice] '.$string;
				break;
			case 2:
				$string = '[debug] '.$string;
				break;
			case 3:
				$string = '[extra] '.$string;
				break;
		}
		echo $string.PHP_EOL;
	}
}
