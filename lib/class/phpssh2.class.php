<?php

class phpssh2 implements ssh
{
    public $activ;

    private $conn;

    function __construct( $host, $user, $key, &$logger = null )
    {
        $this->activ = false;

        if( $logger != null )
            $logger->writeLog( "Verbindung zu " . $host . " aufbauen ... " );
        else
            print "Verbindung zu " . $host . " aufbauen ... ";

        // Verbindung zum Server herstellen
        $this->conn = ssh2_connect( $host );

        // einloggen
        if( !$this->conn )
        {
            if( $logger != null )
                $logger->writeLog( "fehlgeschlagen\n" );
            else
                print "fehlgeschlagen\n";

            return false;
        }

        if( $logger != null )
            $logger->writeLog( "erfolgreich\nAnmeldung ..." );
        else
            print "erfolgreich\nAnmeldung ...";

        $login = ssh2_auth_pubkey_file( $this->conn,
                                        $user,
                                        $key->pubKeyFile,
                                        $key->privKeyFile );

        if( !$login )
        {
            if( $logger != null )
                $logger->writeLog( "fehlgeschlagen\n" );
            else
                print "fehlgeschlagen\n";

            return false;
        }
        else if( $logger != null )
            $logger->writeLog( "erfolgreich\n" );
        else
            print "erfolgreich\n";

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
        $stdout = ssh2_exec( $this->conn, $command );

        if( !$stdout )
        {
            if( $logger != null )
                $logger->writeLog( "Kommando Fehlgeschlagen\n" );
            else
                print "Kommando Fehlgeschlagen\n";

            return false;
        }

        // Ausgabe umleiten
        $output = '';

        stream_set_blocking( $stdout, true );

        while ( $buf = fread( $stdout, 4096 ))
            $output .= $buf;

        fclose( $stdout );

        if( $logger != null )
            $logger->writeLog( $output );
        else
            return $output;
    }

    public function copyFrom( $quellFile, $zielfile )
    {
        return ssh2_scp_recv( $this->conn,
                              $quellFile,
                              $zielfile );
    }
}

?>
