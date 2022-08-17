<?php

require_once( 'phpseclib/autoload.php' );

use phpseclib\Crypt\RSA;
use phpseclib\Net\SSH2;
use phpseclib\Net\SCP;

class phpseclib implements ssh
{
    public $activ;

    private $key;
    private $conn;

    function __construct( $host, $user, $key, &$logger = null )
    {
        $this->activ = false;

        switch( $key->keyType )
        {
            case SSH_KEY_TYPE_RSA: $this->key = new RSA( );
                                   $this->key->loadKey( file_get_contents( $key->privKeyFile ));

                                   if( $logger != null )
                                       $logger->writeLog( 'RSA-SSH-Key initialisiert' );
                                   else
                                       print "RSA-SSH-Key initialisiert\n";

                                   break;
        }

        if( $logger != null )
            $logger->writeLog( "Verbindung zu " . $host . " aufbauen ... " );
        else
            print "Verbindung zu " . $host . " aufbauen ... ";

        $this->conn = new SSH2( $host );

        if( !$this->conn->login( $user, $this->key ))
        {
            if( $logger != null )
                $logger->writeLog( 'Anmeldung fehlgeschlagen' );
            else
                print "Anmeldung fehlgeschlagen\n";
        }
        else if( $logger != null )
            $logger->writeLog( 'Anmeldung erfolgreich' );

        else
            print "Anmeldung erfolgreich\n";

        $this->activ = true;
        return true;
    }

    function __destruct()
    {
        if( $this->activ )
            $this->execute( 'exit' );
    }

    public function execute( $command, &$logger = null )
    {
        $stdout = $this->conn->exec( $command );

        if( $logger != null )
            $logger->writeLog( $stdout );
        else
            return $stdout;
    }

    public function copyFrom( $quellFile, $zielfile )
    {
        $scp = new SCP( $this->conn );

        return $scp->get( $quellFile, $zielfile );
    }
}

?>
