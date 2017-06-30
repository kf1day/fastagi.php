<?php

class _PGSQL {

	protected $pt = null;
	protected $rx = null;
	
	protected function pingloop() {
		while ( ! pg_ping( $this->pt ) ) {
			sleep( 1 );
		}
	}

	public function __construct( $host, $port, $base, $user, $pass ) {

		$s = "options='--client_encoding=UTF8'";
		if ( $host != '' ) $s .= ' host='.$host;
		if ( $port != '' ) $s .= ' port='.$port;
		if ( $base != '' ) $s .= ' dbname='.$base;
		if ( $user != '' ) $s .= ' user='.$user;
		if ( $pass != '' ) $s .= ' password='.$pass;
		$this->pt = pg_pconnect( $s );

		if ( ! $this->pt ) {
			throw new Exception( 'DBA connection failed' );
		}
	}

	public function get( $table, $fields, $filter = false, $group = false, $sort = false ) {
		$this->pingloop();
		if ( is_array( $fields ) ) {
			$fields = implode( '", "', $fields );
		}
		$q = 'SELECT "'.$fields.'" FROM "'.$table.'"';
		if ( is_array( $filter ) && count( $filter ) > 0 ) {
			$t = [];
			foreach( $filter as $k => $v ) {
				$t[] = '"'.$k.'" = '.$v.'';
			}
			$q .= ' WHERE '.implode( ' AND ', $t );
		}
		if ( ( is_array( $sort ) && count( $sort ) > 0 ) || ( $sort && $sort = [ $sort ] ) ) {
			$t = [];
			foreach( $sort as $v ) {
				$v = '"'.$v.'"';
				$v = preg_replace( '/^"\+(.*)"$/', '"$1" ASC', $v );
				$v = preg_replace( '/^"\-(.*)"$/', '"$1" DESC', $v );
				$t[] = $v;
			}
			$q .= ' ORDER BY '.implode( ', ', $t );
		}
		$this->rx = @pg_query( $this->pt, $q.';' );
		if ( ! $this->rx ) throw new Exception( 'DBA query error: '.$q.';' );
		return pg_num_rows( $this->rx );
	}
	
	public function raw( $q ) {
		$this->pingloop();
		$this->rx = @pg_query( $this->pt, $q.';' );
		if ( ! $this->rx ) throw new Exception( 'DBA query error: '.$q.';' );
		return pg_num_rows( $this->rx );
	}
	
	public function fetch() {
		if ( $this->rx ) {
			return pg_fetch_row( $this->rx );
		} else {
			return false;
		}
	}
	
	public function fetch_all() {
		$fff = [];
		if ( $this->rx ) {
			while( $tmp = pg_fetch_row( $this->rx ) ) {
				$fff[] = $tmp;
			}
		} else {
			return false;
		}
		return $fff;
	}

	public function put( $table, $fields ) {
		$this->pingloop();
		if ( !is_array( $fields ) || count( $fields ) == 0 ) return false;
		$q = 'INSERT INTO "'.$table.'" ("'.implode( '", "', array_keys( $fields ) ).'") VALUES('.implode(', ', $fields ).')';
		$this->rx = @pg_query( $this->pt, $q.';' );
		if ( ! $this->rx ) throw new Exception( 'DBA query error: '.$q.';' );
		return pg_num_rows( $this->rx );
	}

	public function del( $table, $filter ) {
		$this->pingloop();
		if ( !is_array( $filter ) || count( $filter ) == 0 ) return false;
		$qcase = [];
		foreach( $case as $k => $v ) {
			$qcase[] = '"'.$k.'" = '.$v;
		}
		$q = 'DELETE FROM "'.$table.'" WHERE '.implode( ' AND ', $qcase );
		$q = $this->pt->query( $q.';' );
		$this->rx = @pg_query( $this->pt, $q.';' );
		if ( ! $this->rx ) throw new Exception( 'DBA query error: '.$q.';' );
		return pg_affected_rows( $this->rx );
	}

	public function upd( $table, $fields, $case ) {
		$this->pingloop();
		if ( !is_array( $fields ) || count( $fields ) == 0 ) return false;
		if ( !is_array( $case ) || count( $case ) == 0 ) return false;
		$q = [];
		foreach( $fields as $k => $v ) {
			if ( !is_numeric( $v ) ) $v = '"'.$v.'"';
			$qfields[] = '`'.$k.'` = '.$v;
		}
		foreach( $case as $k => $v ) {
			$qcase[] = '`'.$k.'` = "'.$v.'"';
		}
		$q = 'UPDATE `'.$table.'` SET '.implode( ', ', $qfields).' WHERE '.implode( ' AND ', $qcase );
		$q = $this->pt->query( $q.';' );
		return ( $q ) ? $this->pt->affected_rows : false;
	}

}
