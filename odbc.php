<?php

class _ODBC {

	protected $pt = null;
	protected $rx = null;

	public function __construct( $dsn, $user = null, $pass = null ) {

		if ( $user || $pass ) {
			$this->pt = odbc_connect( $dsn, $user, $pass );
		} else {
			$this->pt = odbc_pconnect( $dsn, '', '' );
		}
		if ( ! $this->pt ) {
			throw new Exception( 'DBA connection failed' );
		}
	}

	public function get( $table, $fields, $filter = false, $sort = false ) {
		$fff = [];
		if ( is_array( $fields ) ) {
			$fields = implode( '", "', $fields );
		}
		$q = 'SELECT "'.$fields.'" FROM "'.$table.'"';
		if ( is_array( $filter ) && count( $filter ) > 0 ) {
			$t = [];
			foreach( $filter as $k => $v ) $t[] = '"'.$k.'" = '.$v.'';
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
		$this->rx = odbc_exec( $this->pt, $q.';' );
		if ( ! $this->rx ) throw new Exception( 'DBA query error: '.$q.';' );
		return odbc_num_rows( $this->rx );
	}

	public function fetch() {
		if ( $this->rx ) {
			return array_values( odbc_fetch_array( $this->rx ) );
		} else {
			return false;
		}
	}

	public function fetch_all() {
		$fff = [];
		if ( $this->rx ) {
			while( $fff[] = array_values( odbc_fetch_array( $this->rx ) );
		} else {
			return false;
		}
		return $fff;
	}

}
