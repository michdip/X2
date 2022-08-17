<?php

define( 'SSH_LIB_PHP_SSH2',   0 );
define( 'SSH_LIB_PHP_SECLIB', 1 );

require_once( ROOT_DIR . '/lib/class/sshKey.class.php' );
require_once( ROOT_DIR . '/conf.d/x2_host.conf' );
require_once( ROOT_DIR . '/lib/class/logger.class.php' );
require_once( ROOT_DIR . '/lib/class/phpssh2.class.php' );
require_once( ROOT_DIR . '/lib/class/phpseclib.class.php' );

interface ssh
{
     public function __construct( $host, $user, $key, &$logger = null );
     public function __destruct();
     public function execute( $command, &$logger = null );
     public function copyFrom( $quellFile, $zielfile );
}

function sshFactory( $host, &$logger = null )
{
    // den Key laden
    $key = new sshKey( REMOTE_HOSTS[ $host ]['key']['keyType'],
                       REMOTE_HOSTS[ $host ]['key']['privKeyFile'],
                       REMOTE_HOSTS[ $host ]['key']['pubKeyFile'] );

    switch( REMOTE_HOSTS[ $host ]['lib'] )
    {
        case SSH_LIB_PHP_SSH2: return new phpssh2( REMOTE_HOSTS[ $host ]['host'],
                                                   REMOTE_HOSTS[ $host ]['user'],
                                                   $key,
                                                   $logger );
                               break;

        case SSH_LIB_PHP_SECLIB: return new phpseclib( REMOTE_HOSTS[ $host ]['host'],
                                                       REMOTE_HOSTS[ $host ]['user'],
                                                       $key,
                                                       $logger );
                                 break;
    }

    return null;
}

?>
