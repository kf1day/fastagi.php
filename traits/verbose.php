<?php

trait verbose {

	protected $vvvv = -1;		// verbosity level

	final protected function vinit() {
		$v = getopt( "vv:" );
		$v = isset( $v['v'] ) ? count ( $v['v'] ) : -1 ;
		$this->vvvv = $v;
		$this->say( 1, 'Verbosity is set to '.$v );
	}

	final protected function say( $level, $string ) {
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
