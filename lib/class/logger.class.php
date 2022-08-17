<?php

require_once( ROOT_DIR . '/lib/class/logRotation.class.php' );

class logger
{
    private $logfile;

    function __construct( $db, $caller )
    {
        // aufrufer festhalten
        $this->caller = $caller;

        // name des Logfiles festlegen
        $logDate = date( 'Y-m-d' );
        $filename = LOG_DIR . '/' . PHP_SAPI . '-x2Deamon-' . $logDate . '.log';

        // Logfile öffnen
        $this->logfile = fopen( $filename, 'a' );

        // logfile melden
        logRotation::reportLogFile( $db, 'local', $filename, $logDate );

        // Erstellung im Log vermerken
        $this->writeLog( "Logfile für " . $caller . " göffnet." );
    }

    function __destruct( )
    {
        $this->writeLog( "Logfile geschlossen\n" );

        fclose( $this->logfile );
    }

    public function writeLog( $message )
    {
        fwrite( $this->logfile, date( 'Y-m-d H:i:s' ) . ' [' . getmypid() . ']: ' . $message . "\n" );
    }
}

?>
