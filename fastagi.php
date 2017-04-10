<?php

class _FASTAGI {

	const ST_INIT = 0x01;	// new connection => read chanvars
	const ST_RECV = 0x02;	// writing command done => read responce
	const ST_SEND = 0x04;	// command is set to buffer => write buffer


	private $sock = null;
	private $flag = false;	// reindex is nessesary
	private $vvvv = -1;		// verbosity level

	private $vars = [];		// status, buffer, chanvars, event version
	private $conn;

	public function __construct( $host, $port, $v = -1 ) {
		$this->vvvv = $v;
		$this->message( 1, 'Verbosity is set to '.$verbose );
		$sock = @stream_socket_server( 'tcp://'.$host.':'.$port, $errno, $errstr );
		if ( ! $sock ) {
			echo $errstr.PHP_EOL;
			exit -1;
		}
		$this->connect( $sock );
		$this->sock = &$this->conn[0];
	}

	public function worker( $callback ) {
		$e = $w = null;
		while ( 1 ) {
			if ( $this->flag ) $this->reindex();
			$r = $this->conn;
			$this->message( 4, '.' );

			if ( ! stream_select( $r, $w, $e, null ) ) break;
			
			// check incoming connection
			if ( isset( $r[0] ) ) {
				$this->connect( stream_socket_accept( $this->sock, -1) );
				unset( $r[0] );
			}

			// processing sockets
			foreach ( $r as $k => $sock ) {
				$a = &$this->vars[$k];
				$buf = fread( $sock, 1024 );
				if ( ! $buf ) {
					$this->message( 0, '#'.$k.' Read error' );
					$this->disconnect( $k );
					continue;
				}
				$a['buffer'] .= $buf;
				if ( $a['status'] == self::ST_INIT && ( strpos( $a['buffer'], "\n\n" ) !== false ) ) {
					$a['chanvars'] = $this->parse( $a['buffer'] );
					$this->message( 2, '#'.$k.' Argument parsing done, executing callback...' );
					$a['buffer'] = call_user_func( $callback, $a['version']++, [ 200, 0 ], $a['chanvars'] );
					$a['status'] = self::ST_SEND;
					$this->message( 2, '#'.$k.' Write buffer is set' );
				} elseif ( $a['status'] == self::ST_RECV && ( strpos( $a['buffer'], "\n" ) !== false ) ) {
					if ( $a['buffer'] == "HANGUP\n" ) {
						$this->message( 2, '#'.$k.' Caller hangs up' );
						$this->disconnect( $k );
						continue;
					} elseif ( preg_match( '/^(\d+)\s(?:result=)?(.*?)\s+/', $a['buffer'], $r ) ) {
						$this->message( 2, '#'.$k.' Responce was recieved, executing callback...' );
						$a['buffer'] = call_user_func( $callback, $a['version']++, [ $r[1], $r[2] ], $a['chanvars'] );
						$a['status'] = self::ST_SEND;
						$this->message( 2, '#'.$k.' Write buffer is set' );
					} else {
						$this->message( 2, '#'.$k.' Undefined response: "'.trim( $a['buffer'] ).'"' );
						$this->disconnect( $k );
						continue;
					}
				}
				if ( $a['status'] == self::ST_SEND ) {
					if ( ! $a['buffer'] ) {
						$this->message( 2, '#'.$k.' Nothing to write' );
						$this->disconnect( $k );
						continue;
					}
					$buf = fwrite( $sock, $a['buffer']."\n" );
					if ( ! $buf ) {
						$this->message( 0, 'Write error' );
						$this->disconnect( $k );
						continue;
					}
					$a['buffer'] = substr( $a['buffer'], $buf - 1 );
					if ( ! $a['buffer'] ) {
						$a['status'] = self::ST_RECV;
						$this->message( 2, '#'.$k.' Writing done, waiting for responce' );
					}
				}
			}
		}
	}

	private function connect( $pt ) {
		$i = count( $this->conn );
		if ( $i > 0 ) $this->message( 1, '#'.$i.' Accepting connection' );
		$this->conn[$i] = $pt;
		$this->vars[$i] = [ 'status' => self::ST_INIT, 'buffer' => '', 'chanvars' => [], 'version' => 0 ];
	}

	private function disconnect( $i ) {
		$this->message( 1, '#'.$i.' Closing connection' );
		fclose( $this->conn[$i] );
		unset( $this->conn[$i] );
		unset( $this->vars[$i] );
		$this->flag = true;
	}
	
	private function reindex() {
		$this->message( 1, 'Reindexing' );
		$this->conn = array_values( $this->conn );
		$this->vars = array_values( $this->vars );
		$this->flag = false;
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
		if ( $level < 0 ||  $level > $this->vvvv ) return false;
		switch ( $level ) {
			case 0:
				$string = '[error]   '.$string;
				break;
			case 1:
				$string = '[notice]  '.$string;
				break;
			case 2:
				$string = '[debug]   '.$string;
				break;
			case 3:
				$string = '[extra]   '.$string;
				break;
		}
		echo $string.PHP_EOL;
	}
}
