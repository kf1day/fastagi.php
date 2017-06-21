# fastagi.php
Provides a class for creation AGI inet server operating in non-blocking mode


## How to use

### Create PHP script *myserver.php*:
```
<?php

require 'core.php';
class instance extends _FASTAGI {

	function action( $i, $status, &$chanvars ) {
		if ( $i == 0 ) return 'verbose "Got event while calling ' . $chanvars['extension'] . '"';
		if ( $i < 3 ) return 'exec Dial "SIP/100,10"';
	}
}
new instance( '0.0.0.0', 1038 );
```

check out *example* directory for more 

### Configure Asterisk's dialplan in *extensions.conf*:
```
[some-usefull-context]
exten = _XXX,1,NoOp
	same = n,AGI(agi://127.0.0.1:1038,arg_1,arg_2)
	same = n,Hangup
```

## Detail
### Callback
Class method `action( $i, $status, &$chanvars )` triggers every time when client (asterisk) is ready for command. Call syntax is:
* `$i`: Index of event within one connection
* `$status`: array of responce code and string (or subcode, if code is 200)\
e.g. `[ 200, -1 ]` or `[ 520, 'End of proper usage' ]`
* `$chanvars`: array of AGI channel variables with stripped `agi_` prefix\
e.g. [ 'channel` => 'SIP/302-0000a0b9', 'callerid' => '302', 'extension' => '301' ]
