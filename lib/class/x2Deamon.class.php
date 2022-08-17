<?php

require_once( ROOT_DIR . '/conf.d/x2.conf' );
require_once( ROOT_DIR . '/lib/class/logger.class.php' );
require_once( ROOT_DIR . '/lib/class/dbLink.class.php' );
require_once( ROOT_DIR . '/lib/class/mutex.class.php' );
require_once( ROOT_DIR . '/lib/class/jobFunctions.class.php' );
require_once( ROOT_DIR . '/lib/class/templateFunctions.class.php' );
require_once( ROOT_DIR . '/lib/class/modulFunctions.class.php' );
require_once( ROOT_DIR . '/lib/class/jobState.class.php' );

class x2Deamon
{
    private $runDeamon;
    private $db;
    private $logger;
    private $modules;

    function __construct( )
    {
        $this->runDeamon = true;

        // Datenbankverbindung aufbauen
        $this->db = new dbMySQL( 'X2', false );

        // laden der JOB_STATES
        jobState::getJobStates( $this->db );
        jobState::getReverseJobStates( $this->db );

        // logfile öffnen
        $this->logger = new logger( $this->db, 'x2Deamon' );

        // Errors ins LOG umleiten
        set_error_handler( 'self::handleError' );

        // alle Module instanziieren
        $this->modules = modulFunctions::getAllModulInstances( );
    }

    function __destruct( )
    {
        $this->runDeamon = false;
        unset( $this->logger );
        unset( $this->db );
    }

    // diese Funktion prüft ob noch andere x2Deamons laufen und beendet diese ggf.
    public function checkOtherDeamons( )
    {
        // prüfen ob die Datei existiert
        if( file_exists( DEAMON_PID_FILE ))
        {
            $this->logger->writeLog( "Die PID-Datei existiert bereits, es wird geprüft, ob der Deamon noch läuft" );

            if( posix_getpgid( file_get_contents( DEAMON_PID_FILE )))
            {
                $this->logger->writeLog( "Es läuft bereits ein anderer x2Deamon. Ich beende mich!\n" );
                exit(0);
            }
            else
                $this->logger->writeLog( "Der andere x2Deamon ist im System nicht zu finden. Ich starte mich!\n" );
        }
        else
            $this->logger->writeLog( "Die PID-Datei existiert noch nicht. Der Deamon wird gestartet\n" );

        file_put_contents( DEAMON_PID_FILE, getmypid( ));
    }

    public function handleError( $errno, $errstr, $errfile, $errline, $errctx )
    {
        $this->logger->writeLog( "Error No " . $errno . ": " . $errstr . " on line " . $errline . "(" . $errfile . ") -> "
                                 . var_export( $errctx, true ) . "\n" );
    }

    // die Funktion zum bearbeiten der Signals
    public function handleSignal( $sigNo, $sigInfo )
    {
        switch( $sigNo )
        {
            case SIGTERM: $this->logger->writeLog( "KILL-Signal erhalten\n" );
                          $this->runDeamon = false;
                          break;

            case SIGUSR1: $this->logger->writeLog( "Signal zum rotieren des Logfiles erhalten\n" );

                          $newLogger = new logger( $this->db, 'x2Deamon' );
                          unset( $this->logger );
                          $this->logger = $newLogger;

                          break;
        }
    }

    // die Signalhandler erstellen
    public function registerSignal( )
    {
        // Signale abonieren
        pcntl_signal( SIGTERM, 'self::handleSignal' );
        pcntl_signal( SIGUSR1, 'self::handleSignal' );
    }

    // die Loop-Funktion
    public function loop( )
    {
        while( $this->runDeamon )
        {
            // DB zurücksetzen
            $this->db->rollback();

            // Mutex-Reset
            if( AUTOMATIC_MUTEX_RESET )
                mutex::resetMutex( $this->db, $this->logger );

            // die Nachrichten der Clients verarbeiten
            $this->processMessages( );

            // Auf das Wartungsfenster prüfen
            if( !$this->checkMaintenanceTime( ))
            {
                // Templates automatisch starten
                $this->startTemplates( );

                // Alle Jobs starten
                $this->startJobs( );
            }

            // Signale verarbeiten
            pcntl_signal_dispatch();

            $this->logger->writeLog( "Ende der Schleife\n" );

            $this->logger->writeLog( "Sleeptime: " . DEAMON_SLEEP_TIME . "\n" );
            usleep( DEAMON_SLEEP_TIME );

$this->runDeamon = false;
        }
    }

    private function processMessages( )
    {
        // die Messages der Module verarbeiten lassen
        foreach( $this->modules as $modul )
            $modul->processDeamonMessages( $this->db, $this->logger );

        // meine eigenen Messages laden
        $msgs = $this->db->dbRequest( "select OID,
                                              WORKLIST_OID,
                                              DEAMON_MESSAGE,
                                              DEAMON_MODE
                                         from X2_DEAMON
                                        where DEAMON_MODE in ( ?, ?, ?, ? )
                                          and DEAMON_TIME < now( )
                                        order by OID",
                                      array( array( 'i', DEAMON_MODE_FINISH_JOB ),
                                             array( 'i', DEAMON_MODE_CALC_GROUP_STATE ),
                                             array( 'i', DEAMON_MODE_SET_LAST_RUN ),
                                             array( 'i', DEAMON_MODE_SET_JOB_STATE )));

        $this->logger->writeLog( 'Es werden ' . $msgs->numRows . ' vom x2Deamon verarbeitet' );

        $calcGrpStates = array( );

        foreach( $msgs->resultset as $msg )
        {
            $retCode = false;

            switch( $msg['DEAMON_MODE'] )
            {
                // melden eines Native-Log-Files
                case DEAMON_MODE_FINISH_JOB: $retCode = workFunctions::finishWorkJob( $this->db,
                                                                                      $msg['WORKLIST_OID'],
                                                                                      $msg['DEAMON_MESSAGE'],
                                                                                      $this->logger,
                                                                                      $this->modules );
                                             break;

                /* RUN_STATE und EXE_STATE neu berechnen
                 * da diese mehrfach vorkommen können werden diese
                 * erst in einem array zusammengefast
                 */
                case DEAMON_MODE_CALC_GROUP_STATE: $retCode = false;
                                                   $pjm = json_decode( $msg['DEAMON_MESSAGE'], true );

                                                   if( !isset( $pjm['tid'] ))
                                                       $retCode = true;

                                                   else if( !isset( $calcGrpStates[ $pjm['tid']] ))
                                                       $calcGrpStates[ $pjm['tid']] = array( $msg['OID'] );

                                                   else
                                                       array_push( $calcGrpStates[ $pjm['tid']], $msg['OID'] );

                                                   break;

                // den LAST_RUN am Template setzen
                case DEAMON_MODE_SET_LAST_RUN: $retCode = workFunctions::setLastRun( $this->db,
                                                                                     $msg['DEAMON_MESSAGE'],
                                                                                     $this->logger );
                                               break;

                // den STATE des Job verändern, wenn dieser noch auf dem srcState ist
                case DEAMON_MODE_SET_JOB_STATE: $retCode = workFunctions::changeWorkJobStateCallback( $this->db,
                                                                                                      $msg['DEAMON_MESSAGE'],
                                                                                                      $this->logger );
                                                break;
            }

            if( $retCode ) 
            {
                $this->db->dbRequest( "delete
                                         from X2_DEAMON 
                                        where OID = ?",
                                      array( array( 'i', $msg['OID'] )));

                $this->db->commit( );
            }
        }

        // alle calcGrpStates verarbeiten
        foreach( $calcGrpStates as $tid => $oids )
            if( workFunctions::calculateGroupStates( $this->db, $tid, $this->logger ))
            {
                foreach( $oids as $oid )
                    $this->db->dbRequest( "delete
                                             from X2_DEAMON 
                                            where OID = ?",
                                          array( array( 'i', $oid )));

                $this->db->commit( );
            }
    }

    // Auf das Wartungsfenster prüfen
    private function checkMaintenanceTime( )
    {
        $this->logger->writeLog( "Prüfung auf Wartungsfenster" );

        $maintenaceTime = $this->db->dbRequest( "select 1
                                                   from X2_MAINTENANCE_TIME
                                                  where (   (    MAINTENANCE_DAY is not null
                                                             and MAINTENANCE_DAY = dayofweek(now()))
                                                         or (    MAINTENANCE_DATE is not null
                                                             and MAINTENANCE_DATE = date(now())))
                                                    and CURRENT_TIME between MAINTENANCE_START_TIME and MAINTENANCE_END_TIME" );

        if( $maintenaceTime->numRows > 0 )
        {
            $this->logger->writeLog( "Das Wartungsfenster ist offen: es werden keine Jobs gestartet" );
            return true;
        }
        else
        {
            $this->logger->writeLog( "Das Wartungsfenster ist geschlossen: zu startende Jobs laden" );
            return false;
        }
    }

    private function startTemplates( )
    {
        // Templates automatisch starten
        $tpls = $this->db->dbRequest( "select OID
                                         from X2_TEMPLATE
                                        where OBJECT_TYPE = 'T'
                                          and NEXT_START_TIME < now( )
                                          and (    LAST_RUN is null
                                                or LAST_RUN < NEXT_START_TIME )" );

        $this->logger->writeLog( 'Es werden ' . $tpls->numRows . ' Templates gestartet' );

        foreach( $tpls->resultset as $tpl )
            jobFunctions::start( $this->db, $tpl['OID'], 'CRON', $this->logger, true );
    }

    private function startJobs( )
    {
        // Alle Jobs holen, die gestartet werden können
        $jobs = $this->db->dbRequest( "select wl.OID,
                                              wl.JOB_OID,
                                              wl.JOB_TYPE,
                                              wl.TEMPLATE_ID
                                         from X2_WORKLIST wl
                                              inner join X2_TEMPLATE t
                                                 on     wl.TEMPLATE_ID = t.OID
                                                    and t.STATE not in ( ?, ? )
                                        where wl.STATE = ?",
                                      array( array( 'i', TEMPLATE_STATES['POWER_OFF'] ),
                                             array( 'i', TEMPLATE_STATES['PAUSED'] ),
                                             array( 'i', JOB_STATES['READY_TO_RUN']['id'] )));

        $this->logger->writeLog( $jobs->numRows . " Jobs müssen gestartet werden" );

        foreach( $jobs->resultset as $job )
            // den Mutex für das Template holen
            if( mutex::requestMutex( $this->db, $this->logger, 'TEMPLATE', $job['TEMPLATE_ID'], 'CROM', 1, 0 ))
            {
                // die Startzeit eintragen
                $this->db->dbRequest( "update X2_WORKLIST
                                          set PROCESS_START = now( )
                                        where OID = ?",
                                      array( array( 'i', $job['OID'] )));

                // den Job starten
                $this->modules[ $job['JOB_TYPE']]->runJob( $this->db, $job['OID'], $this->logger );

                $this->db->commit( );

                mutex::releaseMutex( $this->db, $this->logger, 'TEMPLATE', $job['TEMPLATE_ID'] );
            }
    }
}

?>
