<?php

require_once 'conf.d/base.conf';
require_once( ROOT_DIR . '/conf.d/x2.conf' );
require_once( ROOT_DIR . '/lib/class/dbLink.class.php' );
require_once( ROOT_DIR . '/lib/class/logger.class.php' );

// Datenbankverbindung erstellen
$db = new dbMySQL( 'X2', false );

// Logfile öffnen
$logger = new logger( $db, 'x2ServerClient' );

if( isset( $_GET['oid'] ))
{
    $dMode = 0;

    // PID erhalten
    if( isset( $_GET['setPID'] ))
    {
        $logger->writeLog( "Host-PID erhalten " . $_GET['setPID'] . " für Job " . $_GET['oid'] );

        $dMode = DEAMON_MODE_SET_PID;
        $message = array( 'pid' => $_GET['setPID'] );
    }

    // Job beendet
    else if( isset( $_GET['return'] ))
    {
        $logFileName = base64_decode( $_GET['logfile'] );

        $logger->writeLog( "JOB " . $_GET['oid'] . " beendet mit Returncode " . $_GET['return'] . ' und Logfile ' . $logFileName );

        $dMode = DEAMON_MODE_FINISH_JOB;
        $message = array( 'return'  => $_GET['return'],
                          'logFile' => $logFileName );
    }

    // ein natives Logfile
    else if( isset( $_GET['nativeLogFile'] ) && isset( $_GET['nativeLogDate'] ))
    {
        $logFileName = base64_decode( $_GET['nativeLogFile'] );

        $logger->writeLog( "Logfile " . $logFileName . " zum " . $_GET['nativeLogDate'] . " gemeldet" );

        $dMode = DEAMON_MODE_NATIVE_LOG;
        $message = array( 'logFile' => $logFileName,
                          'logDate' => $_GET['nativeLogDate'] );
    }

    if( $dMode != 0 )
    {
        $db->dbRequest( "insert into X2_DEAMON (WORKLIST_OID, DEAMON_MODE, DEAMON_MESSAGE)
                         values ( ?, ?, ? )",
                        array( array( 'i', $_GET['oid'] ),
                               array( 'i', $dMode ),
                               array( 's', json_encode( $message ))));

        $db->commit( );
    }
}

?>
