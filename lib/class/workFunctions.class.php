<?php

define( 'WORK_JOB_POSITION_PARENT',   0 );
define( 'WORK_JOB_POSITION_PARALLEL', 1 );
define( 'WORK_JOB_POSITION_AFTER',    2 );
define( 'WORK_JOB_POSITION_FOLLOW',   3 );

require_once( ROOT_DIR . '/lib/class/actionlog.class.php' );
require_once( ROOT_DIR . '/lib/class/mutex.class.php' );
require_once( ROOT_DIR . '/lib/class/logger.class.php' );
require_once( ROOT_DIR . '/lib/class/modulFunctions.class.php' );
require_once( ROOT_DIR . '/lib/class/myPHPMailer.class.php' );

class workFunctions
{
    public static function hasWJobStateOrder( $db, $wid, $state )
    {
        $jState = $db->dbRequest( "select 1
                                     from X2_WORKLIST wl
                                          inner join X2_JOB_STATE j
                                             on     j.JOB_STATE = wl.STATE
                                                and j.STATE_ORDER = ?
                                    where wl.OID = ?",
                                  array( array( 'i', $state ),
                                         array( 'i', $wid )));

        if( $jState->numRows != 0 )
            return true;

        return false;
    }

    public static function hasWJobState( $db, $wid, $state )
    {
        $jState = $db->dbRequest( "select 1
                                     from X2_WORKLIST
                                    where STATE = ?
                                      and OID = ?",
                                  array( array( 'i', $state ),
                                         array( 'i', $wid )));

        if( $jState->numRows != 0 )
            return true;
        
        return false;
    }

    public static function isValidLinkTarget( $db, $src, $target )
    {
        // das Target darf nicht bereits Kind sein
        $linkable = $db->dbRequest( "select 1
                                       from X2_WORK_TREE
                                      where OID = ?
                                        and PARENT = ?",
                                    array( array( 'i', $target ),
                                           array( 'i', $src )));

        if( $linkable->numRows == 0 )
            return true;

        return false;
    }

    public static function isUnlinkable( $db, $target )
    {
        // es müssen mindestens 2 Väter vorhanden sein
        $parents = $db->dbRequest( "select 1
                                      from X2_WORK_TREE
                                     where OID = ?",
                                   array( array( 'i', $target )));

        if( $parents->numRows > 1 )
            return true;

        return false;
    }

    public static function isDeletable( $db, $oid )
    {
        $folgeJobs = $db->dbRequest( "select 1
                                        from X2_WORK_TREE
                                       where PARENT = ?",
                                     array( array( 'i', $oid )));

        $refJobs = $db->dbRequest( "select 1
                                      from X2_WORK_REFERENCE
                                     where OBJECT_ID = ?",
                                   array( array( 'i', $oid )));

        if( $folgeJobs->numRows == 0 && $refJobs->numRows == 0 )
            return true;

        return false;
    }

    public static function nativeLinkWorkJob( $db, $src, $target, $exeID, $user = '', $withLog = false )
    {
        if( !workFunctions::isValidLinkTarget( $db, $src, $target ))
            throw new Exception( "der Job kann nicht verlinkt werden" );

        // den Link aufnehmen
        $db->dbRequest( "insert into X2_WORK_TREE ( OID, PARENT, TEMPLATE_EXE_ID)
                         values ( ?, ?, ? )",
                        array( array( 'i', $target ),
                               array( 'i', $src ),
                               array( 'i', $exeID )),
                        true );

        // Actionlog
        if( $withLog )
            actionlog::logAction4WJob( $db, 10, $src, $user, ' / ' . $target );
    }

    public static function nativeUnlinkWorkJob( $db, $src, $target, $user = '', $withLog = false, $noCheck = false )
    {
        if( !$noCheck && !workFunctions::isUnlinkable( $db, $target ))
            throw new Exception( "der Link kann nicht gelöscht werden" );

        // den Link löschen
        $db->dbRequest( "delete
                           from X2_WORK_TREE
                          where OID = ?
                            and PARENT = ?",
                        array( array( 'i', $target ),
                               array( 'i', $src )),
                        true );

        // Actionlog
        if( $withLog )
            actionlog::logAction4WJob( $db, 14, $src, $user, ' / ' . $target );
    }

    public static function insertWorkJob( $db,
                                          $exeID,
                                          $jobType,
                                          $jobName,
                                          $relID,
                                          $position,
                                          $user = '',
                                          $withLog = false,
                                          $reuseJobId = true )
    {
        $jobId = null;

        if( !$reuseJobId )
            $jobId = -1;

        // den job erstellen
        $newIDs = $db->dbRequest( "insert into X2_WORKLIST (JOB_OID, TEMPLATE_ID, TEMPLATE_EXE_ID, JOB_NAME, JOB_TYPE)
                                   select coalesce( ?, JOB_OID), TEMPLATE_ID, TEMPLATE_EXE_ID, ?, ?
                                     from X2_WORKLIST
                                    where OID = ?",
                                  array( array( 'i', $jobId ),
                                         array( 's', $jobName ),
                                         array( 's', $jobType ),
                                         array( 'i', $relID )),
                                  true );

        $newID = $newIDs->autoincrement;

        // Actionlog füllen
        if( $withLog )
            actionlog::logAction4WJob( $db, 9, $newID, $user );

        // meine Väter laden
        $parents = $db->dbRequest( "select PARENT
                                      from X2_WORK_TREE
                                     where OID = ?",
                                   array( array( 'i', $relID )));

        // meine Nachfolger laden
        $childs = $db->dbRequest( "select OID
                                     from X2_WORK_TREE
                                    where PARENT = ?",
                                  array( array( 'i', $relID )));

        // die Position unterscheiden
        switch( $position )
        {
            case WORK_JOB_POSITION_PARENT: // den neuen als meinen Vater eintragen
                                           workFunctions::nativeLinkWorkJob( $db, $newID, $relID, $exeID, $user, $withLog );
                                           break;

            case WORK_JOB_POSITION_PARALLEL: // meine Väter sind seine
                                             foreach( $parents->resultset as $parent )
                                                 workFunctions::nativeLinkWorkJob( $db,
                                                                                   $parent['PARENT'],
                                                                                   $newID,
                                                                                   $exeID,
                                                                                   $user,
                                                                                   $withLog );

                                             // meine Kinder sind seine
                                             foreach( $childs->resultset as $child )
                                                 workFunctions::nativeLinkWorkJob( $db,
                                                                                   $newID,
                                                                                   $child['OID'],
                                                                                   $exeID,
                                                                                   $user,
                                                                                   $withLog );

                                             break;

            case WORK_JOB_POSITION_AFTER: // der neue wird mein Kind
                                          workFunctions::nativeLinkWorkJob( $db, $relID, $newID, $exeID, $user, $withLog );

                                          // meine Kinder werden seine
                                          foreach( $childs->resultset as $child )
                                          {
                                              workFunctions::nativeLinkWorkJob( $db, $newID, $child['OID'], $exeID, $user, $withLog );

                                              workFunctions::nativeUnlinkWorkJob( $db, $relID, $child['OID'], $user, $withLog );
                                          }

                                          break;

            case WORK_JOB_POSITION_FOLLOW: // der neue wird mein Kind
                                           workFunctions::nativeLinkWorkJob( $db, $relID, $newID, $exeID, $user, $withLog );

                                           break;
        }

        return $newID;
    }

    public static function createWorkJob( $db, $exeID, $parent, $user )
    {
        // Das Template laden
        $template = templateFunctions::getTemplate2GraphByWID( $db, $exeID );

        // das Logfile öffnen
        $logger = new logger( $db,
                              'workFunctions::createWorkJob( ' . $exeID . ', '
                                                               . $template['OID'] . ', '
                                                               . $parent . ', '
                                                               . $user . ' )' );

        // Mutex holen
        if( !mutex::requestMutex( $db, $logger, 'TEMPLATE', $template['OID'], $user, 5, 1 ))
        {
            print "Kein Mutex erhalten!";
            return;
        }

        // ist das Template angehalten
        if( $template['STATE'] != TEMPLATE_STATES['PAUSED'] &&
            $template['STATE'] != TEMPLATE_STATES['POWER_OFF'] )
        {
            print "Das Template ist nicht pausiert";

            mutex::releaseMutex( $db, $logger, 'TEMPLATE', $template['OID'] );

            return;
        }

        // ist der Parent noch nicht beendet
        if( workFunctions::hasWJobStateOrder( $db, $parent, JOB_STATES['OK']['id'] ))
        {
            print "Der Job ist bereits beendet worden";

            mutex::releaseMutex( $db, $logger, 'TEMPLATE', $template['OID'] );

            return;
        }

        try
        {
            // den Job erstellen
            $newID = workFunctions::insertWorkJob( $db,
                                                   $exeID,
                                                   'COMMAND',
                                                   'Folgejob von ' . $parent,
                                                   $parent,
                                                   WORK_JOB_POSITION_FOLLOW,
                                                   $user,
                                                   true,
                                                   false );

            // Kommando setzen
            require_once( MODULES['COMMAND']['classFile'] );

            $modul = new modulCommand( );
            $modul->duplicateWorkJob( $db, $template['OID'], $parent, $newID, $user );

            $db->commit( );
        }
        catch( Exception $e )
        {
            $db->rollback();
            print $e->getMessage();
        }

        // den Mutex zurückgeben
        mutex::releaseMutex( $db, $logger, 'TEMPLATE', $template['OID'] );
    }

    public static function deleteWorkJob( $db, $exeID, $wid, $user )
    {
        // Das Template laden
        $template = templateFunctions::getTemplate2GraphByWID( $db, $exeID );

        // das Logfile öffnen
        $logger = new logger( $db,
                              'workFunctions::deleteWorkJob( ' . $exeID . ', '
                                                               . $template['OID'] . ', '
                                                               . $wid . ', '
                                                               . $user . ' )' );

        // Mutex holen
        if( !mutex::requestMutex( $db, $logger, 'TEMPLATE', $template['OID'], $user, 5, 1 ))
        {
            print "Kein Mutex erhalten!";
            return;
        }

        // ist das Template angehalten
        if( $template['STATE'] != TEMPLATE_STATES['PAUSED'] &&
            $template['STATE'] != TEMPLATE_STATES['POWER_OFF'] )
        {
            print "Das Template ist nicht pausiert";

            mutex::releaseMutex( $db, $logger, 'TEMPLATE', $template['OID'] );

            return;
        }

        // kann der Job gelöscht werden
        if( !workFunctions::isDeletable( $db, $wid ))
        {
            print "Der Job kann nicht gelöscht werden";

            mutex::releaseMutex( $db, $logger, 'TEMPLATE', $template['OID'] );

            return;
        }

        // ist der Job noch nicht gestartet
        if( !workFunctions::hasWJobStateOrder( $db, $wid, JOB_STATES['CREATED']['id'] ))
        {
            print "Der Job befindet sich bereits in der Ausführung";

            mutex::releaseMutex( $db, $logger, 'TEMPLATE', $template['OID'] );

            return;
        }

        try
        {
            // den Job laden
            $mySelf = workFunctions::getWorkJob4Edit( $db, $wid );

            // das Modul instanziieren
            $modul = modulFunctions::getModulInstance( $mySelf['JOB_TYPE'] );

            // das Modul löschen
            $modul->deleteWorkJob( $db, $wid );

            // Gruppenzugehörigkeiten löschen
            $db->dbRequest( "delete
                               from X2_WORK_GROUP
                              where MEMBER_OID = ?",
                            array( array( 'i', $wid )),
                            true );

            // die Väter löschen
            $parents = $db->dbRequest( "select PARENT
                                          from X2_WORK_TREE
                                         where OID = ?",
                                       array( array( 'i', $wid )),
                                       true );

            foreach( $parents->resultset as $row )
                workFunctions::nativeUnlinkWorkJob( $db, $row['PARENT'], $wid, $user, true, true );

            // Actionlog
            actionlog::logAction4WJob( $db, 12, $wid, $user );

            // den Job löschen
            $db->dbRequest( "delete
                               from X2_WORKLIST
                              where OID = ?",
                            array( array( 'i', $wid )),
                            true );

            $db->commit( );
        }
        catch( Exception $e )
        {
            $db->rollback();
            print $e->getMessage();
        }

        // den Mutex zurückgeben
        mutex::releaseMutex( $db, $logger, 'TEMPLATE', $template['OID'] );
    }

    public static function renameWorkJob( $db, $exeID, $wid, $name, $user )
    {
        // Das Template laden
        $template = templateFunctions::getTemplate2GraphByWID( $db, $exeID );

        // das Logfile öffnen
        $logger = new logger( $db,
                              'workFunctions::renameWorkJob( ' . $exeID . ', '
                                                               . $template['OID'] . ', '
                                                               . $wid . ', '
                                                               . $name . ', '
                                                               . $user . ' )' );

        // Mutex holen
        if( !mutex::requestMutex( $db, $logger, 'TEMPLATE', $template['OID'], $user, 5, 1 ))
        {
            print "Kein Mutex erhalten!";
            return;
        }

        // ist das Template angehalten
        if( $template['STATE'] != TEMPLATE_STATES['PAUSED'] &&
            $template['STATE'] != TEMPLATE_STATES['POWER_OFF'] )
        {
            print "Das Template ist nicht pausiert";

            mutex::releaseMutex( $db, $logger, 'TEMPLATE', $template['OID'] );

            return;
        }

        try
        {
            $db->dbRequest( "update X2_WORKLIST
                                set JOB_NAME = ?
                              where OID = ?",
                            array( array( 's', $name ),
                                   array( 'i', $wid )),
                            true );

            // ActionLog
            actionlog::logAction4WJob( $db, 15, $wid, $user, ' / ' . $name );

            $db->commit( );
        }
        catch( Exception $e )
        {
            $db->rollback();
            print $e->getMessage();
        }

        // den Mutex zurückgeben
        mutex::releaseMutex( $db, $logger, 'TEMPLATE', $template['OID'] );
    }

    public static function changeWorkJobType( $db, $exeID, $wid, $newJobType, $user )
    {
        // Das Template laden
        $template = templateFunctions::getTemplate2GraphByWID( $db, $exeID );

        // das Logfile öffnen
        $logger = new logger( $db,
                              'workFunctions::changeWorkJobType( ' . $exeID . ', '
                                                                   . $template['OID'] . ', '
                                                                   . $wid . ', '
                                                                   . $newJobType . ', '
                                                                   . $user . ' )' );

        // Mutex holen
        if( !mutex::requestMutex( $db, $logger, 'TEMPLATE', $template['OID'], $user, 5, 1 ))
        {
            print "Kein Mutex erhalten!";
            return;
        }

        // ist das Template angehalten
        if( $template['STATE'] != TEMPLATE_STATES['PAUSED'] &&
            $template['STATE'] != TEMPLATE_STATES['POWER_OFF'] )
        {
            print "Das Template ist nicht pausiert";

            mutex::releaseMutex( $db, $logger, 'TEMPLATE', $template['OID'] );

            return;
        }

        // ist der Job noch nicht gestartet
        if( !workFunctions::hasWJobStateOrder( $db, $wid, JOB_STATES['CREATED']['id'] ))
        {
            print "Der Job befindet sich bereits in der Ausführung";

            mutex::releaseMutex( $db, $logger, 'TEMPLATE', $template['OID'] );

            return;
        }

        try
        {
            // den Job laden
            $mySelf = workFunctions::getWorkJob4Edit( $db, $wid );

            // das Modul instanziieren
            $modul = modulFunctions::getModulInstance( $mySelf['JOB_TYPE'] );
            $modul->deleteWorkJob( $db, $wid );

            // den Typen ändern
            $db->dbRequest( "update X2_WORKLIST
                                set JOB_TYPE = ?
                              where OID = ?",
                            array( array( 's', $newJobType ),
                                   array( 'i', $wid )),
                            true );

            actionlog::logAction4WJob( $db, 16, $wid, $user, ' / ' . $newJobType );

            // das neue Modul instanziieren
            $modul = modulFunctions::getModulInstance( $newJobType );
            $modul->duplicateWorkJob( $db, 0, -1, $wid, $user );

            $db->commit( );
        }
        catch( Exception $e )
        {
            $db->rollback();
            print $e->getMessage();
        }

        // den Mutex zurückgeben
        mutex::releaseMutex( $db, $logger, 'TEMPLATE', $template['OID'] );
    }

    public static function linkWorkJob( $db, $exeID, $src, $target, $user )
    {
        // Das Template laden
        $template = templateFunctions::getTemplate2GraphByWID( $db, $exeID );

        // das Logfile öffnen
        $logger = new logger( $db,
                              'workFunctions::linkWorkJob( ' . $exeID . ', '
                                                             . $template['OID'] . ', '
                                                             . $src . ', '
                                                             . $target . ', '
                                                             . $user . ' )' );

        // Mutex holen
        if( !mutex::requestMutex( $db, $logger, 'TEMPLATE', $template['OID'], $user, 5, 1 ))
        {
            print "Kein Mutex erhalten!";
            return;
        }

        try
        {
            workFunctions::nativeLinkWorkJob( $db, $src, $target, $exeID, $user, true );

            $db->commit( );
        }
        catch( Exception $e )
        {
            $db->rollback();
            print $e->getMessage();
        }

        // den Mutex zurückgeben
        mutex::releaseMutex( $db, $logger, 'TEMPLATE', $template['OID'] );
    }

    public static function unlinkWorkJob( $db, $exeID, $src, $target, $user )
    {
        // Das Template laden
        $template = templateFunctions::getTemplate2GraphByWID( $db, $exeID );

        // das Logfile öffnen
        $logger = new logger( $db,
                              'workFunctions::unlinkWorkJob( ' . $exeID . ', '
                                                               . $template['OID'] . ', '
                                                               . $src . ', '
                                                               . $target . ', '
                                                               . $user . ' )' );

        // Mutex holen
        if( !mutex::requestMutex( $db, $logger, 'TEMPLATE', $template['OID'], $user, 5, 1 ))
        {
            print "Kein Mutex erhalten!";
            return;
        }

        try
        {
            workFunctions::nativeUnlinkWorkJob( $db, $src, $target, $user, true );

            $db->commit( );
        }
        catch( Exception $e )
        {
            $db->rollback();
            print $e->getMessage();
        }

        // den Mutex zurückgeben
        mutex::releaseMutex( $db, $logger, 'TEMPLATE', $template['OID'] );
    }

    public static function nativeSetBreakpoint( $db, $wid, $value, $user = '', &$logger, $withLog = false )
    {
        // den Breakpoint updaten
        $db->dbRequest( "update X2_WORKLIST
                            set BREAKPOINT = ?
                          where OID = ?",
                        array( array( 'i', $value ),
                               array( 'i', $wid )));

        // Actionlog
        if( $withLog )
            actionlog::logAction4WJob( $db, 13, $wid, $user, ' / ' . $value );

        // wird ein Breakpoint entfernt, ggf meine Kinder starten
        if( $value == null )
            workFunctions::runMyChilds( $db, $wid, $user, $logger );
    }

    public static function setBreakpoint( $db, $exeID, $wid, $user, $value )
    {
        // Das Template laden
        $template = templateFunctions::getTemplate2GraphByWID( $db, $exeID );

        // das Logfile öffnen
        $logger = new logger( $db,
                              'workFunctions::setBreakpoint( ' . $exeID . ', '
                                                               . $template['OID'] . ', '
                                                               . $wid . ', '
                                                               . $value . ', '
                                                               . $user . ' )' );

        // Mutex holen
        if( !mutex::requestMutex( $db, $logger, 'TEMPLATE', $template['OID'], $user, 5, 1 ))
        {
            print "Kein Mutex erhalten!";
            return;
        }

        workFunctions::nativeSetBreakpoint( $db, $wid, $value, $user, $logger, true );

        $db->commit( );

        // den Mutex zurückgeben
        mutex::releaseMutex( $db, $logger, 'TEMPLATE', $template['OID'] );
    }

    public static function setBreakpointGroup( $db, $exeID, $wid, $user, $value )
    {
        // Das Template laden
        $template = templateFunctions::getTemplate2GraphByWID( $db, $exeID );

        // das Logfile öffnen
        $logger = new logger( $db,
                              'workFunctions::setBreakpointGroup( ' . $exeID . ', '
                                                                    . $template['OID'] . ', '
                                                                    . $wid . ', '
                                                                    . $value . ', '
                                                                    . $user . ' )' );

        // Mutex holen
        if( !mutex::requestMutex( $db, $logger, 'TEMPLATE', $template['OID'], $user, 5, 1 ))
        {
            print "Kein Mutex erhalten!";
            return;
        }

        // jeden Job aus der Gruppe laden
        $jobs = $db->dbRequest( "select MEMBER_OID
                                   from X2_WORK_GROUP
                                  where TEMPLATE_EXE_ID = ?
                                    and GROUP_OID = ?",
                                array( array( 'i', $exeID ),
                                       array( 'i', $wid )));

        foreach( $jobs->resultset as $job )
            workFunctions::nativeSetBreakpoint( $db, $job['MEMBER_OID'], $value, $user, $logger, true );

        $db->commit( );

        // den Mutex zurückgeben
        mutex::releaseMutex( $db, $logger, 'TEMPLATE', $template['OID'] );
    }

    public static function calculateGroupStates( $db, $tid, &$logger )
    {
        // den Mutex holen
        if( !mutex::requestMutex( $db, $logger, 'TEMPLATE', $tid, 'CRON', 1, 0 ))
            return false;

        $logger->writeLog( 'Berechne Statie für Template ' . $tid );

        /* RUN_STATE und EXE_STATE des Templates ermitteln
         *
         * dazu werden alle Templates unter dem übergebenen ermittelt
         * von diesen alle WorkJobs der letzten Ausführung
         *
         * EXE_STATE: 1: wenn sich ein Job in der STATE_ORDER[ RUNNING ] befindet
         *            0: sonst
         *
         * RUN_STATE: JOB_STATES[ CREATED ]   wenn keiner im STATE_ORDER[ OK ]
         *            JOB_STATES[ ERROR ]     wenn dieser vorkommt,
         *            JOB_STATES[ RUNNING_6 ] wenn dieser vorkommt
         *            JOB_STATES[ OK ]        sonst
         */
        $workJobStates = $db->dbRequest( "with parents as ( select OID
                                                              from X2_TEMPLATE_TREE
                                                             where PARENT = ?
                                                            union
                                                            select ? ),
                                               exe_id as ( select w.TEMPLATE_ID,
                                                                  max( w.TEMPLATE_EXE_ID ) TEMPLATE_EXE_ID
                                                             from X2_WORKLIST w
                                                                  inner join parents p
                                                                     on w.TEMPLATE_ID = p.OID
                                                            group by w.TEMPLATE_ID )
                                          select w.STATE, o.STATE_ORDER
                                            from exe_id e
                                                 inner join X2_WORKLIST w
                                                    on     w.TEMPLATE_ID = e.TEMPLATE_ID
                                                       and w.TEMPLATE_EXE_ID = e.TEMPLATE_EXE_ID
                                                 inner join X2_JOB_STATE o
                                                    on w.STATE = o.JOB_STATE",
                                         array( array( 'i', $tid ),
                                                array( 'i', $tid )));

        $exeState = 0;
        $runState = JOB_STATES['CREATED']['id'];

        foreach( $workJobStates->resultset as $job )
        {
            if( $job['STATE_ORDER'] == JOB_STATES['RUNNING']['id'] )
                $exeState = 1;

            if( $job['STATE_ORDER'] == JOB_STATES['ERROR']['id'] )
                $runState = JOB_STATES['ERROR']['id'];

            else if( $job['STATE'] == JOB_STATES['RUNNING_6']['id'] &&
                     $runState != JOB_STATES['ERROR']['id'] )
                $runState = JOB_STATES['RUNNING_6']['id'];

            else if( $job['STATE_ORDER'] == JOB_STATES['OK']['id'] &&
                     $runState == JOB_STATES['CREATED']['id'] )
                $runState = JOB_STATES['OK']['id'];
        }

        // das Update schreiben
        $db->dbRequest( "update X2_TEMPLATE
                            set EXE_STATE = ?,
                                RUN_STATE = ?
                          where OID = ?",
                        array( array( 'i', $exeState ),
                               array( 'i', $runState ),
                               array( 'i', $tid )));

        $db->commit( );

        // den Mutex zurückgeben
        mutex::releaseMutex( $db, $logger, 'TEMPLATE', $tid );

        return true;
    }

    public static function updateGroupStates( $db, $tid, $wid )
    {
        // alle Väter und mich ermitteln
        $templates = $db->dbRequest( "select PARENT
                                        from X2_TEMPLATE_TREE
                                       where OID = ?
                                      union
                                      select ?",
                                     array( array( 'i', $tid ),
                                            array( 'i', $tid )));

        foreach( $templates->resultset as $template )
            $db->dbRequest( "insert into X2_DEAMON (WORKLIST_OID, DEAMON_MODE, DEAMON_MESSAGE)
                             values ( ?, ?, ? )",
                            array( array( 'i', $wid ),
                                   array( 'i', DEAMON_MODE_CALC_GROUP_STATE ),
                                   array( 's', json_encode( array( 'tid' => $template['PARENT'] )))));
    }

    public static function runMyChilds( $db, $wid, $user, $logger )
    {
        // bin ich im Status OK
        if( !workFunctions::hasWJobStateOrder( $db, $wid, JOB_STATES['OK']['id'] ))
            return;

        /* alle meine Kinder Starten
         *
         * children: alle Kinder des fertigen Job
         * parents:  von allen Kindern alle Väter
         *
         * letztere müssen abgeschlossen sein (STATE_ORDER[ OK ])
         * und keinen Breakpoint gesetzt haben
         */

        $sChilds = $db->dbRequest( "with children as ( select OID
                                                         from X2_WORK_TREE
                                                        where PARENT = ? ),
                                         parents as ( select p.OID,
                                                             case when o.STATE_ORDER != ?
                                                                  then 1
                                                                  else 0
                                                              end STATE_ERROR,
                                                             w.BREAKPOINT
                                                        from X2_WORK_TREE p
                                                             inner join children c
                                                                on c.OID = p.OID
                                                             inner join X2_WORKLIST w
                                                                on w.OID = p.PARENT
                                                             inner join X2_JOB_STATE o
                                                                on o.JOB_STATE = w.STATE)
                                    select OID
                                      from parents
                                     group by OID
                                    having max(STATE_ERROR) = 0
                                       and max(BREAkPOINT) is null",
                                   array( array( 'i', $wid ),
                                          array( 'i', JOB_STATES['OK']['id'] )));

        foreach( $sChilds->resultset as $child )
            if( workFunctions::hasWJobState( $db, $child['OID'], JOB_STATES['CREATED']['id'] ))
                workFunctions::changeWorkJobState( $db, $child['OID'], JOB_STATES['READY_TO_RUN']['id'], $user, $logger );
    }

    public static function checkCallbacks( $db, $wid, $exeID )
    {
        $db->dbRequest( "insert into X2_DEAMON (WORKLIST_OID, DEAMON_MODE, DEAMON_MESSAGE)
                         select REF_JOB, CALLBACK_ID, ?
                           from X2_WORK_REFERENCE
                          where (     OBJECT_TYPE = 'JOB'
                                  and OBJECT_ID = ? )
                             or (     OBJECT_TYPE = 'TEMPLATE_EXE_ID'
                                  and OBJECT_ID = ? )",
                        array( array( 's', json_encode( array( 'exeid' => $exeID,
                                                               'wid'   => $wid ))),
                               array( 'i', $wid ),
                               array( 'i', $exeID )));
    }

    public static function sendNotifier( $db, $tid, $exeID, $wid, $newState, &$logger )
    {
        if( !USE_MAIL_DROP )
            return;

        $mState = null;

        // die Order des neuen State laden
        $sOrder = $db->dbRequest( "select STATE_ORDER
                                     from X2_JOB_STATE
                                    where JOB_STAtE = ?",
                                  array( array( 'i', $newState )));

        // bei einem Fehler immer senden
        if( $sOrder->resultset[0]['STATE_ORDER'] == JOB_STATES['ERROR']['id'] )
            $mState = JOB_STATES['ERROR']['id'];

        // bei Ok dürfen keine anderen States mehr im Template sein
        else if( $sOrder->resultset[0]['STATE_ORDER'] == JOB_STATES['OK']['id'] )
        {
            $tState = $db->dbRequest( "select 1
                                         from X2_WORKLIST wl
                                              inner join X2_JOB_STATE js
                                                 on     wl.STATE = js.JOB_STATE
                                                    and js.STATE_ORDER != ?
                                        where wl.TEMPLATE_EXE_ID = ?",
                                      array( array( 'i', JOB_STATES['OK']['id'] ),
                                             array( 'i', $exeID )));

            if( $tState->numRows == 0 )
                $mState = JOB_STATES['OK']['id'];
        }

        if( $mState == null )
            return;

        // alle Notifier von mir und meinen Vätern laden
        $notifier = $db->dbRequest( "with tpls as ( select PARENT
                                                      from X2_TEMPLATE_TREE
                                                     where OID = ?
                                                    union
                                                    select ? )
                                     select n.RECIPIENT
                                       from X2_TEMPLATE_NOTIFIER n
                                            inner join tpls
                                               on tpls.PARENT = n.TEMPLATE_ID
                                      where n.STATE = ?
                                      group by n.RECIPIENT",
                                    array( array( 'i', $tid ),
                                           array( 'i', $tid ),
                                           array( 'i', $mState )));

        if( $notifier->numRows > 0 )
        {
            $reci = array( );

            foreach( $notifier->resultset as $recipient )
                array_push( $reci, $recipient['RECIPIENT'] );

            $message = "Das Template " . $tid . " wurde mit Status " . REVERSE_JOB_STATES[ $newState ]['name'] . " beendet.\n\n"
                     . 'Diese Nachricht wurde vom X2 ' . ROOT_URL . 'worklist.php?wid=' . $exeID . " erstellt.";

            myPhpMailer::sendMail( $message, "X2 Status " . REVERSE_JOB_STATES[ $newState ]['name'], $reci, $logger );
        }
    }

    public static function changeWorkJobState( $db, $wid, $newState, $user, &$logger )
    {
        // den Status ändern
        $db->dbRequest( "update X2_WORKLIST
                            set STATE = ?
                          where OID = ?",
                        array( array( 'i', $newState ),
                               array( 'i', $wid )),
                        true );

        // Actionlog
        actionlog::logAction4WJob( $db, 27, $wid, $user, ' / ' . REVERSE_JOB_STATES[ $newState ]['name'] );

        // meine Kinder starten Statusprüfung findet in der Funktion statt
        workFunctions::runMyChilds( $db, $wid, $user, $logger );

        // den Job laden
        $mySelf = workFunctions::getWorkJob4Edit( $db, $wid );

        // nach Callbacks suchen
        workFunctions::checkCallbacks( $db, $wid, $mySelf['TEMPLATE_EXE_ID'] );

        // Neuberechnung von RUN_STATE und EXE_STATE beauftragen
        workFunctions::updateGroupStates( $db, $mySelf['TEMPLATE_ID'], $wid );

        // nächste Startzeit des Templates berechnen
        jobFunctions::calculateNextStartTime( $db, $mySelf['TEMPLATE_ID'] );

        // Notifier betrachten
        workFunctions::sendNotifier( $db, $mySelf['TEMPLATE_ID'], $mySelf['TEMPLATE_EXE_ID'], $wid, $newState, $logger );
    }

    public static function changeWorkJobStateCallback( $db, $message, &$logger )
    {
        // die Message parsen
        $myMessage = json_decode( $message, true );

        // Mutex für das Template holen
        if( !mutex::requestMutex( $db, $logger, 'TEMPLATE', $myMessage['tid'], 'CRON', 1, 0 ))
            return false;

        // hat der Job noch den Status
        if( workFunctions::hasWJobState( $db, $myMessage['wid'], $myMessage['srcState'] ))
        {
            workFunctions::changeWorkJobState( $db, $myMessage['wid'], $myMessage['dstState'], 'CRON', $logger );

            $db->commit( );
        }

        // den Mutex zurückgeben
        mutex::releaseMutex( $db, $logger, 'TEMPLATE', $myMessage['tid'] );

        return true;
    }

    public static function setOkByUser( $db, $exeID, $wid, $user, $admin = false )
    {
        // Das Template laden
        $template = templateFunctions::getTemplate2GraphByWID( $db, $exeID );

        // das Logfile öffnen
        $logger = new logger( $db,
                              'workFunctions::setOkByUser( ' . $exeID . ', '
                                                             . $template['OID'] . ', '
                                                             . $wid . ', '
                                                             . $user . ', '
                                                             . $admin . ' )' );

        // Mutex holen
        if( !mutex::requestMutex( $db, $logger, 'TEMPLATE', $template['OID'], $user, 5, 1 ))
        {
            print "Kein Mutex erhalten!";
            return;
        }

        // darf der Job auf OK_BY_USER gesetzt werden
        $stateField = 'SET_OK_BY_USER';

        if( $admin )
            $stateField = 'SET_OK_BY_ADMIN';

        $ok = $db->dbRequest( "select 1
                                 from X2_WORKLIST wl
                                      inner join X2_JOB_STATE js
                                         on     js.JOB_STATE = wl.STATE
                                            and js." . $stateField . " = 1
                                where wl.OID = ?",
                              array( array( 'i', $wid )));

        if( $ok->numRows == 1 )
        {
            // den Status setzen
            workFunctions::changeWorkJobState( $db, $wid, JOB_STATES['OK_BY_USER']['id'], $user, $logger );

            $db->commit( );
        }

        // den Mutex zurückgeben
        mutex::releaseMutex( $db, $logger, 'TEMPLATE', $template['OID'] );
    }

    public static function setOkByUserGroup( $db, $exeID, $wid, $state, $user )
    {
        // Das Template laden
        $template = templateFunctions::getTemplate2GraphByWID( $db, $exeID );

        // das Logfile öffnen
        $logger = new logger( $db,
                              'workFunctions::setOkByUserGroup( ' . $exeID . ', '
                                                                  . $template['OID'] . ', '
                                                                  . $wid . ', '
                                                                  . $state . ', '
                                                                  . $user . ' )' );

        // Mutex holen
        if( !mutex::requestMutex( $db, $logger, 'TEMPLATE', $template['OID'], $user, 5, 1 ))
        {
            print "Kein Mutex erhalten!";
            return;
        }

        // jeden betroffenen Job der Gruppe laden
        $jobs = $db->dbRequest( "select wg.MEMBER_OID
                                   from X2_WORK_GROUP wg
                                        inner join X2_WORKLIST wl
                                           on wl.OID = wg.MEMBER_OID
                                        inner join X2_JOB_STATE js
                                           on     js.JOB_STATE = wl.STATE
                                              and js.SET_OK_BY_USER = 1
                                              and js.STATE_ORDER = ?
                                  where wg.GROUP_OID = ?
                                    and wg.TEMPLATE_EXE_ID = ?",
                                array( array( 'i', $state ),
                                       array( 'i', $wid ),
                                       array( 'i', $exeID )));

        foreach( $jobs->resultset as $job )
            workFunctions::changeWorkJobState( $db, $job['MEMBER_OID'], JOB_STATES['OK_BY_USER']['id'], $user, $logger );

        $db->commit( );

        // den Mutex zurückgeben
        mutex::releaseMutex( $db, $logger, 'TEMPLATE', $template['OID'] );
    }

    public static function nativeRetryWorkJob( $db, $exeID, $tid, $job, $retryTime, $user, &$modul, $logger )
    {
        // den Job hinter mir einfügen
        $newId = workFunctions::insertWorkJob( $db,
                                               $exeID,
                                               $job['JOB_TYPE'],
                                               $job['JOB_NAME'],
                                               $job['OID'],
                                               WORK_JOB_POSITION_AFTER,
                                               true );

        // den Job duplizieren
        $modul->duplicateWorkJob( $db, $tid, $job['OID'], $newId, $user );

        // den Job-Status aus RETRY setzen
        workFunctions::changeWorkJobState( $db, $job['OID'], JOB_STATES['RETRY']['id'], $user, $logger );

        // eine Gruppe eröffnen
        if( !workFunctions::isWorkGroupMember( $db, $job['OID'] ))
            workFunctions::createWorkGroup( $db, $exeID, $job['JOB_OID'], $job['OID'] );

        // den neuen in die Grupe aufnehmen
        workFunctions::createWorkGroup( $db, $exeID, $job['JOB_OID'], $newId );

        /* ist eine retryTime gesetzt, dann den Deamon beauftragen den Job-State
         * dann auf OK_BY_USER zu setzen
         */
        if( $retryTime > 0 )
            $db->dbRequest( "insert into X2_DEAMON (WORKLIST_OID, DEAMON_MODE, DEAMON_TIME, DEAMON_MESSAGE)
                             values ( ?, ?, date_add( now( ), interval ? second ), ? )",
                            array( array( 'i', $job['OID'] ),
                                   array( 'i', DEAMON_MODE_SET_JOB_STATE ),
                                   array( 'i', $retryTime ),
                                   array( 's', json_encode( array( 'tid'      => $tid,
                                                                   'wid'      => $job['OID'],
                                                                   'srcState' => JOB_STATES['RETRY']['id'],
                                                                   'dstState' => JOB_STATES['OK_BY_USER']['id'] )))));
    }

    public static function retryWorkJob( $db, $exeID, $wid, $user )
    {
        // Das Template laden
        $template = templateFunctions::getTemplate2GraphByWID( $db, $exeID );

        // das Logfile öffnen
        $logger = new logger( $db,
                              'workFunctions::retryWorkJob ( ' . $exeID . ', '
                                                               . $template['OID'] . ', '
                                                               . $wid . ', '
                                                               . $user . ' )' );

        // Mutex holen
        if( !mutex::requestMutex( $db, $logger, 'TEMPLATE', $template['OID'], $user, 5, 1 ))
        {
            print "Kein Mutex erhalten!";
            return;
        }

        // nur wenn der Job auf ERROR steht
        if( !workFunctions::hasWJobState( $db, $wid, JOB_STATES['ERROR']['id'] ))
        {
            print "Der WorkJob ist nicht im Status ERROR";
            mutex::releaseMutex( $db, $logger, 'TEMPLATE', $template['OID'] );
            return;
        }

        // den Job laden
        $mySelf = workFunctions::getWorkJob4Edit( $db, $wid );

        // das Modul laden
        $modul = modulFunctions::getModulInstance( $mySelf['JOB_TYPE'] );

        // kann der Job neu gestartet werden
        if( !$modul->opts['hasRetry'] )
        {
            print "Der WorkJob kann nicht erneut gestartet werden";
            mutex::releaseMutex( $db, $logger, 'TEMPLATE', $template['OID'] );
            return;
        }

        workFunctions::nativeRetryWorkJob( $db, $exeID, $template['OID'], $mySelf, 0, $user, $modul, $logger );

        $db->commit( );

        // den Mutex zurückgeben
        mutex::releaseMutex( $db, $logger, 'TEMPLATE', $template['OID'] );
    }

    public static function finishWorkJob( $db, $wid, $message, &$logger, &$modules )
    {
        // den Job laden
        $mySelf = workFunctions::getWorkJob4Edit( $db, $wid );

        // wurde der WorkJob nicht gefunden, dann trotzdem die Message löschen
        if( $mySelf == null )
            return true;

        // den Mutex für das Template holen
        if( !mutex::requestMutex( $db, $logger, 'TEMPLATE', $mySelf['TEMPLATE_ID'], 'CRON', 1, 0 ))
            return false;

        // ist der WorkJob noch RUNNING
        if( !workFunctions::hasWJobStateOrder( $db, $wid, JOB_STATES['RUNNING']['id'] ))
        {
            $logger->writeLog( 'Job ' . $wid . ' wurde bereits beendet' );

            mutex::releaseMutex( $db, $logger, 'TEMPLATE', $mySelf['TEMPLATE_ID'] );

            return true;
        }

        // die Message parsen
        $myMessage = json_decode( $message, true );

        if( !isset( $myMessage['return'] ))
            $myMessage['return'] = 1;

        $logger->writeLog( 'Job ' . $wid . ' beendet mit retCode ' . $myMessage['return'] );

        // ende der Laufzeit am WorkJob setzen
        $db->dbRequest( "update X2_WORKLIST
                            set PROCESS_STOP = now( )
                          where OID = ?",
                        array( array( 'i', $wid )));

        // den STATE des WorkJob updaten
        if( $myMessage['return'] == 0 )
            workFunctions::changeWorkJobState( $db, $wid, JOB_STATES['OK']['id'], 'CRON', $logger );
        else
            workFunctions::changeWorkJobState( $db, $wid, JOB_STATES['ERROR']['id'], 'CRON', $logger );

        // dem Modul das Ergebnis übergeben
        $modules[ $mySelf['JOB_TYPE']]->finishWorkJob( $db, $wid, $myMessage, $logger );

        $db->commit( );

        // den Mutex zurückgeben
        mutex::releaseMutex( $db, $logger, 'TEMPLATE', $mySelf['TEMPLATE_ID'] );

        return true;
    }

    public static function setLastRun( $db, $message, &$logger )
    {
        // die Message parsen
        $myMessage = json_decode( $message, true );

        if( !isset( $myMessage['tid'] ) || !isset( $myMessage['lastRun'] ))
            return true;

        // den Mutex für das Template holen
        if( !mutex::requestMutex( $db, $logger, 'TEMPLATE', $myMessage['tid'], 'CRON', 1, 0 ))
            return false;

        // den LastRun setzen
        $db->dbRequest( "update X2_TEMPLATE
                            set LAST_RUN = str_to_date( ?, '%Y-%m-%d %H:%i:%s' )
                          where OID = ?",
                        array( array( 's', $myMessage['lastRun'] ),
                               array( 'i', $myMessage['tid'] )));

        $db->commit( );

        // den Mutex zurückgeben
        mutex::releaseMutex( $db, $logger, 'TEMPLATE', $myMessage['tid'] );

        return true;
    }

    public static function cleanUpTemplateExe( $db, $tid, $maxExe, &$modules )
    {
        // alle Jobs laden, die Archiviert werden sollen
        $jobs = $db->dbRequest( "select OID,
                                        JOB_TYPE
                                   from X2_WORKLIST
                                  where TEMPLATE_ID = ?
                                    and TEMPLATE_EXE_ID <= ?",
                                array( array( 'i', $tid ),
                                       array( 'i', $maxExe )));
                                               
        // den Job archivieren / löschen
        foreach( $jobs->resultset as $job )
        {
            $modules[ $job['JOB_TYPE']]->archiveWorkJob( $db, $job['OID'] );
            $modules[ $job['JOB_TYPE']]->deleteWorkJob( $db, $job['OID'] );
        }   

        // die LogFiles löschen
        $db->dbRequest( "delete l
                           from X2_LOGFILE l
                                inner join X2_WORKLIST w
                                   on w.TEMPLATE_EXE_ID = l.TEMPLATE_EXE_ID
                          where l.TEMPLATE_EXE_ID <= ?
                            and w.TEMPLATE_ID = ?",
                        array( array( 'i', $maxExe ),
                               array( 'i', $tid )),
                        true );

        // die Bäume löschen
        $db->dbRequest( "delete t
                           from X2_WORK_TREE t
                                inner join X2_WORKLIST w
                                   on w.TEMPLATE_EXE_ID = t.TEMPLATE_EXE_ID
                          where t.TEMPLATE_EXE_ID <= ?
                            and w.TEMPLATE_ID = ?",
                        array( array( 'i', $maxExe ),
                               array( 'i', $tid )),
                        true );

        // die Variablen löschen
        $db->dbRequest( "delete wv
                           from X2_WORK_VARIABLE wv
                                inner join X2_WORKLIST wl
                                   on wl.TEMPLATE_EXE_ID = wv.TEMPLATE_EXE_ID
                          where wl.TEMPLATE_EXE_ID <= ?
                            and wl.TEMPLATE_ID = ?",
                        array( array( 'i', $maxExe ),
                               array( 'i', $tid )),
                        true );

        // die Gruppen löschen
        $db->dbRequest( "delete wg
                           from X2_WORK_GROUP wg
                                inner join X2_WORKLIST wl
                                   on wl.TEMPLATE_EXE_ID = wg.TEMPLATE_EXE_ID
                          where wl.TEMPLATE_EXE_ID <= ?
                            and wl.TEMPLATE_ID = ?",
                        array( array( 'i', $maxExe ),
                               array( 'i', $tid )),
                        true );

        // die Worklist löschen
        $db->dbRequest( "delete
                           from X2_WORKLIST
                          where TEMPLATE_EXE_ID <= ?
                            and TEMPLATE_ID = ?",
                        array( array( 'i', $maxExe ),
                               array( 'i', $tid )),
                        true );
    }

    public static function createWorkGroup( $db, $exeID, $gid, $mid )
    {
        $db->dbRequest( "insert into X2_WORK_GROUP (TEMPLATE_EXE_ID, GROUP_OID, MEMBER_OID)
                         values ( ?, ?, ? )",
                        array( array( 'i', $exeID ),
                               array( 'i', $gid ),
                               array( 'i', $mid )),
                        true );
    }

    public static function isWorkGroupMember( $db, $wid )
    {
        $grp = $db->dbRequest( "select 1
                                  from X2_WORK_GROUP
                                 where MEMBER_OID = ?",
                               array( array( 'i', $wid )));

        if( $grp->numRows == 0 )
            return false;

        return true;
    }

    public static function getWorkGroups( $db, $exeID )
    {
        $wGroups = array( );

        $grps = $db->dbRequest( "select GROUP_OID, MEMBER_OID
                                   from X2_WORK_GROUP
                                  where TEMPLATE_EXE_ID = ?",
                                array( array( 'i', $exeID )));

        foreach( $grps->resultset as $grp )
        {
            if( !isset( $wGroups[ $grp['GROUP_OID']] ))
                $wGroups[ $grp['GROUP_OID']] = array( );

            $wGroups[ $grp['GROUP_OID']][ $grp['MEMBER_OID']] = true;
        }

        return $wGroups;
    }

    public static function getWorkJob4Edit( $db, $wid )
    {
        $eJob = $db->dbRequest( "select OID,
                                        JOB_OID,
                                        JOB_TYPE,
                                        JOB_NAME,
                                        TEMPLATE_ID,
                                        TEMPLATE_EXE_ID
                                   from X2_WORKLIST
                                  where OID = ?",
                                array( array( 'i', $wid )));

        if( $eJob->numRows != 1 )
            return null;

        return $eJob->resultset[0];
    }
}

?>
