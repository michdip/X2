<?php

require_once( ROOT_DIR . '/conf.d/x2.conf' );
require_once( ROOT_DIR . '/lib/class/jobFunctions.class.php' );
require_once( ROOT_DIR . '/lib/class/mutex.class.php' );
require_once( ROOT_DIR . '/lib/class/sequence.class.php' );
require_once( ROOT_DIR . '/lib/class/modulFunctions.class.php' );

class jobFunctions
{
    public static function isDeletable( $db, $oid )
    {
        $folgeJobs = $db->dbRequest( "select 1
                                        from X2_JOB_TREE
                                       where PARENT = ?",
                                     array( array( 'i', $oid )));

        $refJobs = $db->dbRequest( "select 1
                                      from X2_JOB_REFERENCE
                                     where OBJECT_TYPE = 'JOB'
                                       and OBJECT_ID != REF_JOB
                                       and OBJECT_ID = ?",
                                   array( array( 'i', $oid )));

        if( $folgeJobs->numRows == 0 && $refJobs->numRows == 0 )
            return true;

        return false;
    }

    public static function isValidLinkTarget( $db, $src, $target )
    {
        // das Target darf kein START-Job sein und nicht bereits ein Kind / Vater
        $linkable = $db->dbRequest( "select 1
                                       from X2_JOBLIST
                                      where JOB_TYPE = 'START'
                                        and OID = ?
                                     union
                                     select 1
                                       from X2_JOB_TREE
                                      where OID in ( ?, ? )
                                        and PARENT in ( ?, ? )",
                                    array( array( 'i', $target ),
                                           array( 'i', $target ),
                                           array( 'i', $src ),
                                           array( 'i', $target ),
                                           array( 'i', $src )));

        if( $linkable->numRows == 0 )
            return true;

        return false;
    }

    public static function isUnlinkable( $db, $oid )
    {
        // es müssen mindestens 2 Väter vorhanden sein
        $parents = $db->dbRequest( "select 1
                                      from X2_JOB_TREE
                                     where OID = ?",
                                   array( array( 'i', $oid )));

        if( $parents->numRows > 1 )
            return true;

        return false;
    }

    public static function getJob4Edit( $db, $oid )
    {
        $eJob = $db->dbRequest( "select TEMPLATE_ID,
                                        OID,
                                        JOB_TYPE,
                                        JOB_NAME,
                                        BREAKPOINT
                                   from X2_JOBLIST
                                  where OID = ?",
                                array( array( 'i', $oid )));

        return $eJob->resultset[0];
    }

    public static function getMyTemplateID( $db, $oid )
    {
        $tid = $db->dbRequest( "select TEMPLATE_ID
                                  from X2_JOBLIST
                                 where OID = ?",
                               array( array( 'i', $oid )));

        return $tid->resultset[0]['TEMPLATE_ID'];
    }

    public static function nativeCreateJob( $db, $tid, $jobType, $jobName, $user )
    {
        // Job erstellen
        $newJob = $db->dbRequest( "insert into X2_JOBLIST (TEMPLATE_ID, JOB_TYPE, JOB_NAME)
                                   values ( ?, ?, ? )",
                                  array( array( 'i', $tid ),
                                         array( 's', $jobType ),
                                         array( 's', $jobName )),
                                  true );

        // ActionLog
        actionlog::logAction4Job( $db, 9, $newJob->autoincrement, $user );

        return $newJob->autoincrement;
    }

    public static function createJob( $db, $tid, $oid, $user )
    {
        // nur wenn das Template editierbar ist
        if( !templateFunctions::isEditable( $db, $tid ))
        {
            print "Das Template ist nicht editierbar";
            return;
        }

        try
        {
            // Job erstellen
            $newJob = jobFunctions::nativeCreateJob( $db, $tid, 'COMMAND', 'Neuer Folgejob von ' . $oid, $user );

            // Verbindung zum Vater herstellen
            jobFunctions::linkJob( $db, $newJob, $oid, $user, true );

            // Defaults setzen ggf. vom Parent übernehmen
            require_once( ROOT_DIR . '/lib/modul/modulCommand.class.php' );

            $modulCommand = new modulCommand( );
            $modulCommand->duplicateJob( $db, $tid, $oid, $newJob, $user );

            $db->commit( );
        }
        catch( Exception $e )
        {
            $db->rollback();
            print $e->getMessage();
        }
    }

    public static function removeJob( $db, $oid, $user, $automatic = false )
    {
        // nur wenn das Template editierbar ist
        if( !templateFunctions::isEditable( $db, jobFunctions::getMyTemplateID( $db, $oid )))
        {
            print "Das Template ist nicht editierbar";
            return;
        }

        // den Job nur löschen, wenn dies erlaubt ist
        if( !$automatic && !jobFunctions::isDeletable( $db, $oid ))
        {
            print "Der Job kann nicht gelöscht werden";
            return;
        }

        try
        {
            // den Job laden
            $job = jobFunctions::getJob4Edit( $db, $oid );

            // das Modul laden
            $modul = modulFunctions::getModulInstance( $job['JOB_TYPE'] );

            $modul->deleteJob( $db, $oid );

            // Actionlog füllen
            actionlog::logAction4Job( $db, 12, $oid, $user );

            // aushängen
            $db->dbRequest( "delete
                               from X2_JOB_TREE
                              where OID = ?",
                            array( array( 'i', $oid )),
                            true );

            // löschen
            $db->dbRequest( "delete
                               from X2_JOBLIST
                              where OID = ?",
                            array( array( 'i', $oid )),
                            true );

            if( !$automatic )
                $db->commit( );
        }
        catch( Exception $e )
        {
            $db->rollback();

            if( !$automatic )
                print $e->getMessage();

            else
                throw $e;
        }
    }

    public static function changeJobType( $db, $oid, $newJobType, $user )
    {
        // nur wenn das Template editierbar ist
        if( !templateFunctions::isEditable( $db, jobFunctions::getMyTemplateID( $db, $oid )))
        {
            print "Das Template ist nicht editierbar";
            return;
        }

        try
        {
            // den Job laden
            $mySelf = jobFunctions::getJob4Edit( $db, $oid );

            // das Modul instanziieren
            $modul = modulFunctions::getModulInstance( $mySelf['JOB_TYPE'] );
            $modul->deleteJob( $db, $oid );

            // den Typen ändern
            $db->dbRequest( "update X2_JOBLIST
                                set JOB_TYPE = ?
                              where OID = ?",
                            array( array( 's', $newJobType ),
                                   array( 'i', $oid )),
                            true );

            actionlog::logAction4Job( $db, 16, $oid, $user, ' / ' . $newJobType );

            // das neue Modul instanziieren
            $modul = modulFunctions::getModulInstance( $newJobType );
            $modul->duplicateJob( $db, 0, -1, $oid, $user );

            $db->commit( );
        }
        catch( Exception $e )
        {
            $db->rollback();
            print $e->getMessage();
        }
    }

    public static function setBreakpoint( $db, $oid, $user, $value, $automatic = false )
    {
        // nur wenn das Template editierbar ist
        if( !templateFunctions::isEditable( $db, jobFunctions::getMyTemplateID( $db, $oid )))
        {
            print "Das Template ist nicht editierbar";
            return;
        }

        $db->dbRequest( "update X2_JOBLIST
                            set BREAKPOINT = ?
                          where OID = ?",
                        array( array( 'i', $value ),
                               array( 'i', $oid )));

        actionlog::logAction4Job( $db, 13, $oid, $user, ' / ' . $value );

        if( !$automatic )
            $db->commit( );
    }

    public static function linkJob( $db, $oid, $parent, $user, $automatic = false )
    {
        // nur wenn das Template editierbar ist
        if( !templateFunctions::isEditable( $db, jobFunctions::getMyTemplateID( $db, $oid )))
        {
            print "Das Template ist nicht editierbar";
            return;
        }

        // ist dar Link erlaubt
        if( !jobFunctions::isValidLinkTarget( $db, $parent, $oid ))
        {
            print "Der Job kann nicht verknüpft werden";
            return;
        }

        $db->dbRequest( "insert into X2_JOB_TREE (OID, PARENT)
                         values ( ?, ? )",
                        array( array( 'i', $oid ),
                               array( 'i', $parent )));

        // ActionLog
        actionlog::logAction4Job( $db, 10, $parent, $user, ' / ' . $oid );

        if( !$automatic )
            $db->commit();
    }

    public static function unlinkJob( $db, $oid, $parent, $user, $automatic = false )
    {
        // nur wenn das Template editierbar ist
        if( !templateFunctions::isEditable( $db, jobFunctions::getMyTemplateID( $db, $oid )))
        {
            print "Das Template ist nicht editierbar";
            return;
        }

        // ist der unlink erlaubt
        if( !jobFunctions::isUnlinkable( $db, $oid ))
        {
            print "Der Link kann nicht entfernt werden";
            return;
        }

        $db->dbRequest( "delete from X2_JOB_TREE
                          where OID = ?
                            and PARENT = ?",
                        array( array( 'i', $oid ),
                               array( 'i', $parent )));

        // ActionLog
        actionlog::logAction4Job( $db, 14, $parent, $user, ' / ' . $oid );

        if( !$automatic )
            $db->commit();
    }

    public static function renameJob( $db, $oid, $name, $user )
    {
        // nur wenn das Template editierbar ist
        if( !templateFunctions::isEditable( $db, jobFunctions::getMyTemplateID( $db, $oid )))
        {
            print "Das Template ist nicht editierbar";
            return;
        }

        $db->dbRequest( "update X2_JOBLIST
                            set JOB_NAME = ?
                          where OID = ?",
                        array( array( 's', $name ),
                               array( 'i', $oid )));

        // ActionLog
        actionlog::logAction4Job( $db, 15, $oid, $user, ' / ' . $name );

        $db->commit();
    }

    public static function start( $db, $tid, $user, $logger = null, $automatic = false, &$ref = null )
    {
        // das Logfile öffnen, wenn nicht vorhanden
        if( $logger == null )
            $logger = new logger( $db, 'jobFunctions::start( ' . $tid . ', ' . $user . ' )' );

        // den Mutex holen
        if( !mutex::requestMutex( $db, $logger, 'TEMPLATE', $tid, $user, 5, 1 ))
        {
            print "Kein Mutex erhalten!";
            return -1;
        }

        // kann das Template gestartet werden
        if( !templateFunctions::startable( $db, $tid, $automatic ))
        {
            if( !$automatic )
                print "das Template kann nicht gestartet werden";

            $logger->writeLog( 'Template ist nicht startable' );

            mutex::releaseMutex( $db, $logger, 'TEMPLATE', $tid );

            return -1;
        }

        // eine EXE-ID holen
        $exeID = sequence::getNextValue( $db, 'SEQ_X2_TEMPLATE_EXE_ID', $logger, $user );

        if( $exeID == -1 )
        {
            mutex::releaseMutex( $db, $logger, 'TEMPLATE', $tid );

            if( !$automatic )
                print "Keine EXE-ID erhalten";

            $logger->writeLog( 'Keine EXE-ID erhalten' );

            return -1;
        }

        try
        {
            // alle Module instanziieren
            $modules = modulFunctions::getAllModulInstances( );

            // Alte aufräumen
            $lastExeId = $db->dbRequest( "select TEMPLATE_EXE_ID
                                            from X2_WORKLIST
                                           where TEMPLATE_ID = ?
                                           group by TEMPLATE_EXE_ID
                                           order by TEMPLATE_EXE_ID desc
                                           limit 10, 1",
                                         array( array( 'i', $tid )));

            if( $lastExeId->numRows > 0 )
            {
                $maxExe = $lastExeId->resultset[0]['TEMPLATE_EXE_ID'];

                workFunctions::cleanUpTemplateExe( $db, $tid, $lastExeId->resultset[0]['TEMPLATE_EXE_ID'], $modules );
            }

            // wurde eine Referenz übergeben, so diese Aufrufen
            if( $ref != null )
                $ref->refJFStartCall( $db, $exeID );

            // alle Jobs ohne den Starter kopieren
            $db->dbRequest( "insert into X2_WORKLIST (JOB_OID, TEMPLATE_ID, TEMPLATE_EXE_ID, JOB_NAME, JOB_TYPE, STATE, BREAKPOINT)
                             select OID, TEMPLATE_ID, ?, JOB_NAME, JOB_TYPE, ?, BREAKPOINT
                               from X2_JOBLIST
                              where JOB_TYPE != 'START'
                                and TEMPLATE_ID = ?",
                            array( array( 'i', $exeID ),
                                   array( 'i', JOB_STATES['CREATED']['id'] ),
                                   array( 'i', $tid )),
                            true );

            // alle Variablen kopieren
            foreach( templateFunctions::getVariables( $db, $tid ) as $var )
                $db->dbRequest( "insert into X2_WORK_VARIABLE (TEMPLAtE_EXE_ID, VAR_NAME, VAR_VAlUE)
                                 values ( ?, ?, ? )
                                 on duplicate key update VAR_NAME = VAR_NAME",
                                array( array( 'i', $exeID ),
                                       array( 's', $var['VAR_NAME'] ),
                                       array( 's', $var['VAR_VALUE'] )),
                                true );

            // die Hierarchie kopieren ohne Starter
            $db->dbRequest( "insert into X2_WORK_TREE (OID, PARENT, TEMPLATE_EXE_ID)
                             select wl.OID, ws.OID, wl.TEMPLATE_EXE_ID
                               from X2_WORKLIST wl
                                    inner join X2_JOB_TREE jt
                                       on wl.JOB_OID = jt.OID
                                    inner join X2_WORKLIST ws
                                       on     ws.JOB_OID = jt.PARENT
                                          and wl.TEMPLATE_EXE_ID = ws.TEMPLATE_EXE_ID
                              where wl.TEMPLATE_EXE_ID = ?",
                            array( array( 'i', $exeID )),
                            true );

            // die Module auswerten
            $jobs = $db->dbRequest( "select OID, JOB_OID, JOB_TYPE
                                       from X2_WORKLIST
                                      where TEMPLATE_EXE_ID = ?",
                                    array( array( 'i', $exeID )));

            foreach( $jobs->resultset as $job )
                $modules[ $job['JOB_TYPE']]->createWorkCopy( $db, $job['JOB_OID'], $job['OID'], $exeID, $logger );

            // Alle Jobs die ausschließlich unter dem Starter hängen auf JOB_STATES.READY_TO_RUN setzen
            $db->dbRequest( "update X2_WORKLIST
                                set STATE = ?
                              where TEMPLATE_EXE_ID = ?
                                and JOB_OID in ( select OID
                                                   from ( select jt.OID,
                                                                 case when jl.JOB_TYPE = 'START'
                                                                      then 1
                                                                      else 0
                                                                  end tpe
                                                            from X2_JOBLIST jl
                                                                 inner join X2_JOB_TREE jt
                                                                    on     jt.PARENT = jl.OID
                                                                       and jl.TEMPLATE_ID = ?
                                                        ) st
                                                  group by OID
                                                  having count(*) = 1
                                                     and sum( tpe ) = 1
                                               )",
                            array( array( 'i', JOB_STATES['READY_TO_RUN']['id'] ),
                                   array( 'i', $exeID ),
                                   array( 'i', $tid )),
                            true );

            // Breakpoint am Starter => Template pausieren
            $sbreak = $db->dbRequest( "select 1
                                         from X2_JOBLIST
                                        where JOB_TYPE = 'START'
                                          and BREAKPOINT is not null
                                          and TEMPLATE_ID = ?",
                                      array( array( 'i', $tid )),
                                      true );

            if( $sbreak->numRows > 0 )
                templateFunctions::pause( $db, $tid, true );

            // Nächste Startzeit auf jetzt setzen zur Anzeige der voraussichtlichen Laufzeit
            $db->dbRequest( "update X2_TEMPLATE
                                set ACTUAL_START_TIME = now( ),
                                    LAST_RUN = now( )
                              where OID = ?",
                            array( array( 'i', $tid )));

            // Letzte Ausführungszeit an alle Väter setzen
            $parents = $db->dbRequest( "select PARENT,
                                               date_format( now( ), '%Y-%m-%d %H:%i:%s' ) LAST_RUN
                                          from X2_TEMPLATE_TREE
                                         where OID = ?",
                                       array( array( 'i', $tid )));

            foreach( $parents->resultset as $parent )
                $db->dbRequest( "insert into X2_DEAMON (WORKLIST_OID, DEAMON_MODE, DEAMON_MESSAGE)
                                 values ( ?, ?, ? )",
                                array( array( 'i',  $tid ),
                                       array( 'i', DEAMON_MODE_SET_LAST_RUN ),
                                       array( 's', json_encode( array( 'tid'     => $parent['PARENT'],
                                                                       'lastRun' => $parent['LAST_RUN'] )))));

            // Actionlog
            actionlog::logAction( $db, 29, $tid, $user, null, $exeID );

            $db->commit( );
        }
        catch( Exception $e )
        {
            $db->rollback();
            print $e->getMessage();

            return -1;
        }

        // den Mutex frei geben
        mutex::releaseMutex( $db, $logger, 'TEMPLATE', $tid );

        return $exeID;
    }

    public static function ejectJobs( $db, $tid, $user )
    {
        // Mutex holen
        $logger = new logger( $db, 'jobFunctions::ejectJobs( ' . $tid . ', ' . $user . ' )'  );

        // Mutex holen
        if( !mutex::requestMutex( $db, $logger, 'TEMPLATE', $tid, $user, 5, 1 ))
        {
            print "Kein Mutex erhalten!";
            return;
        }

        // kann das Template abgebrochen werden
        if( !templateFunctions::ejectable( $db, $tid ))
        {
            print "Das Template kann nicht abgebrochen werden";
            $logger->writeLog( 'Template kann nicht abgebrochen werden' );

            mutex::releaseMutex( $db, $logger, 'TEMPLATE', $tid );

            return;
        }

        $db->dbRequest( "update X2_WORKLIST
                            set STATE = ?
                          where TEMPLATE_ID = ?
                            and STATE in ( select JOB_STATE
                                             from X2_JOB_STATE
                                            where STATE_ORDER = ? )",
                        array( array( 'i', JOB_STATES['OK_BY_USER']['id'] ),
                               array( 'i', $tid ),
                               array( 'i', JOB_STATES['CREATED']['id'] )));

        // Actionlog
        actionlog::logAction( $db, 28, $tid, $user );

        $db->commit( );

        // den Mutex zurückgeben
        mutex::releaseMutex( $db, $logger, 'TEMPLATE', $tid );
    }

    public static function calculateNextStartTime( $db, $oid )
    {
        // Nur setzen, wenn alle JOBs im STATE_ORDER[ OK ] sind
        $running = $db->dbRequest( "select 1
                                      from X2_WORKLIST w
                                           inner join X2_JOB_STATE j
                                              on j.JOB_STATE = w.STATE
                                                 and j.STATE_ORDER != ?
                                     where TEMPLATE_ID = ?",
                                   array( array( 'i', JOB_STATES['OK']['id'] ),
                                          array( 'i', $oid )));

        if( $running->numRows > 0 )
            return;

        // alle Startzeiten laden
        $start = $db->dbRequest( "select SEKUNDEN,
                                         DAY_OF_WEEK,
                                         case when DAY_OF_MONTH < 10 then concat( '0', DAY_OF_MONTH )
                                              else DAY_OF_MONTH
                                              end as DAY_OF_MONTH,
                                         case when START_HOUR < 10 then concat( '0', START_HOUR )
                                              else START_HOUR
                                              end as START_HOUR,
                                         case when START_MINUTES < 10 then concat( '0', START_MINUTES )
                                              else START_MINUTES
                                              end as START_MINUTES,
                                         date_format( coalesce( VALIDFROM, str_to_date( '1970-01-01', '%Y-%m-%d' )), '%Y-%m-%d' ) VALIDFROM,
                                         date_format( coalesce( VALIDUNTIL, str_to_date( '9999-12-31', '%Y-%m-%d' )), '%Y-%m-%d' ) VALIDUNTIL,
                                         date_format( now(), '%Y-%m-%d' ) SYSDATE
                                    from X2_JOB_START
                                   where TEMPLATE_ID = ?
                                     and START_MODE != 'manual'",
                                 array( array( 'i', $oid )));

        foreach( $start->resultset as $sTime )
        {
            // SEKUNDEN
            if( $sTime['SEKUNDEN'] != '' )
                $db->dbRequest( "insert into X2_CALCULATE_START_TMP (TEMPLATE_ID, NEXT_START_TIME, VALIDFROM, VALIDUNTIL)
                                 select ?,
                                        coalesce( date_add( max(PROCESS_START), interval ? second), now()),
                                        str_to_date( ?, '%Y-%m-%d' ),
                                        str_to_date( ?, '%Y-%m-%d' )
                                   from X2_WORKLIST
                                  where TEMPLATE_ID = ?
                                 union
                                 select ?,
                                        date_add( str_to_date( ?, '%Y-%m-%d' ), interval ? second),
                                        str_to_date( ?, '%Y-%m-%d' ),
                                        str_to_date( ?, '%Y-%m-%d' )",
                                array( array( 'i', $oid ),
                                       array( 'i', $sTime['SEKUNDEN'] ),
                                       array( 's', $sTime['VALIDFROM'] ),
                                       array( 's', $sTime['VALIDUNTIL'] ),
                                       array( 'i', $oid ),
                                       array( 'i', $oid ),
                                       array( 's', $sTime['VALIDFROM'] ),
                                       array( 'i', $sTime['SEKUNDEN'] ),
                                       array( 's', $sTime['VALIDFROM'] ),
                                       array( 's', $sTime['VALIDUNTIL'] )));

            // Tageszeit
            else if( $sTime['DAY_OF_WEEK'] != '' &&
                     $sTime['START_HOUR'] != '' &&
                     $sTime['START_MINUTES'] != '' )
                foreach( array( 'VALIDFROM', 'SYSDATE' ) as $sPoint )
                {
                    /* welcher Tag der Woche ist vom Betrachtungszeitpunkt aus heute / morgen
                     * und kann heute mit Uhrzeit noch erreicht werden
                     */
                    $tmp = $db->dbRequest( "select dayofweek( str_to_date( ?, '%Y-%m-%d' )) as DOW,
                                                   dayofweek( date_add( str_to_date( ?, '%Y-%m-%d' ), interval 1 day )) as MORGEN,
                                                   str_to_date( ?, '%Y-%m-%d %H:%i' ) > now() ERREICHBAR",
                                           array( array( 's', $sTime[ $sPoint ] ),
                                                  array( 's', $sTime[ $sPoint ] ),
                                                  array( 's', $sTime[ $sPoint ] . ' ' . $sTime['START_HOUR'] . ':' . $sTime['START_MINUTES'] )));

                    // Zugriff vereinfachen
                    $dow    = $tmp->resultset[0]['DOW'];
                    $morgen = $tmp->resultset[0]['MORGEN'];
                    $tok    = $tmp->resultset[0]['ERREICHBAR'];
                    $tday   = 0;

                    // den Zieltag ermitteln
                    switch( $sTime['DAY_OF_WEEK'] )
                    {
                        /* jeden Tag
                         * ist heute nicht erreichbar, dann morgen nehmen
                         */
                       case 1: if( $tok )
                                   $tday = $dow;

                               else
                                   $tday = $morgen;

                               break;

                        // am Wochenende
                        case 2: if( $dow == 7 && !$tok ) // wenn Samstag und nicht Erreichbar, dann Sonntag
                                    $tday = $morgen;

                                else                     // sonst Samstag
                                    $tday = 7;

                                break;

                        // Werktags
                                // wenn Wochenende oder Freitag nicht erreichbar, dann Montag
                        case 3: if( $dow == 7 || $dow == 1 || ( $dow == 6 && !$tok ))
                                    $tday = 2;

                                else if ( !$tok )
                                    $tday = $morgen;

                                else
                                    $tday = $dow;

                                break;

                        // Mo - Fr
                        case  4: $tday = 2; break;
                        case  5: $tday = 3; break;
                        case  6: $tday = 4; break;
                        case  7: $tday = 5; break;
                        case  8: $tday = 6; break;
                        case  9: $tday = 7; break;
                        case 10: $tday = 1; break;
                    }

                    // Zieltag suchen

                    /* das SQL-Schnipsel kann auf dem heutigen oder dem morgigen Tag im Bezug auf den Betrachtungszeitpunkt
                     * die neue Startzeit bilden. Die Unterscheidung ob heute oder morgen verwendet wird
                     * ist das Offset 0 = heute / 1 = morgen
                     *
                     * die Startzeit ist
                     *                    addiere
                     *                                 wenn tDay in dieser Woche bereits war (mit Offset wird von morgen aus betrachtet)
                     *                                 dann die Tage bis zum tDay nächste Woche
                     *                                 sonst die Tage bis tDay diese Woche
                     *
                     *                    auf den Betrachtungszeitpunkt
                     */
                    $nextDay = "date_format( date_add( str_to_date( ?, '%Y-%m-%d' ),
                                                       interval if( ? - dayofweek( str_to_date( ?, '%Y-%m-%d' )) < ?,
                                                                    ? + ( 7 - dayofweek( str_to_date( ?, '%Y-%m-%d' ))),
                                                                    ? - dayofweek( str_to_date( ?, '%Y-%m-%d' )))
                                                                day ),
                                             '%Y%m%d' )";

                    // den nächste Startzeit auf heute und morgen des Betrachtungszeitpunktes berechnen
                    for( $i = 0; $i < 2; $i++ )
                        $db->dbRequest( "insert into X2_CALCULATE_START_TMP (TEMPLATE_ID, NEXT_START_TIME, VALIDFROM, VALIDUNTIL)
                                         select ?,
                                                str_to_date( concat( " . $nextDay . ", ? ), '%Y%m%d%H%i' ),
                                                str_to_date( ?, '%Y-%m-%d' ),
                                                str_to_date( ?, '%Y-%m-%d' )",
                                        array( array( 'i', $oid ),
                                               array( 's', $sTime[ $sPoint ] ),
                                               array( 'i', $tday ),
                                               array( 's', $sTime[ $sPoint ] ),
                                               array( 'i', $i ),
                                               array( 'i', $tday ),
                                               array( 's', $sTime[ $sPoint ] ),
                                               array( 'i', $tday ),
                                               array( 's', $sTime[ $sPoint ] ),
                                               array( 's', $sTime['START_HOUR'] . $sTime['START_MINUTES'] ),
                                               array( 's', $sTime['VALIDFROM'] ),
                                               array( 's', $sTime['VALIDUNTIL'] )));
                }

            // einen bestimmten Tag im Monat
            else if ( $sTime['DAY_OF_MONTH'] != '' &&
                      $sTime['START_HOUR'] != '' &&
                      $sTime['START_MINUTES'] != '' )
                foreach( array( 'VALIDFROM', 'SYSDATE' ) as $sPoint )
                    // diesen un den nächsten Monat nehmen
                    $db->dbRequest( "insert into X2_CALCULATE_START_TMP (TEMPLATE_ID, NEXT_START_TIME, VALIDFROM, VALIDUNTIL)
                                     select ?,
                                            str_to_date( ?, '%Y-%m-%d%H%i' ),
                                            str_to_date( ?, '%Y-%m-%d' ),
                                            str_to_date( ?, '%Y-%m-%d' )
                                     union
                                     select ?,
                                            date_add( str_to_date( ?, '%Y-%m-%d%H%i' ), interval 1 month ),
                                            str_to_date( ?, '%Y-%m-%d' ),
                                            str_to_date( ?, '%Y-%m-%d' )",
                                    array( array( 'i', $oid ),
                                           array( 's', substr( $sTime[ $sPoint ], 0, -2 ) . $sTime['DAY_OF_MONTH'] . $sTime['START_HOUR'] . $sTime['START_MINUTES'] ),
                                           array( 's', $sTime['VALIDFROM'] ),
                                           array( 's', $sTime['VALIDUNTIL'] ),
                                           array( 'i', $oid ),
                                           array( 's', substr( $sTime[ $sPoint ], 0, -2 ) . $sTime['DAY_OF_MONTH'] . $sTime['START_HOUR'] . $sTime['START_MINUTES'] ),
                                           array( 's', $sTime['VALIDFROM'] ),
                                           array( 's', $sTime['VALIDUNTIL'] )));
        }

        // die nächste Startzeit auswerten
        $nextStartTime = $db->dbRequest( "select date_format( s.NEXT_START_TIME, '%Y-%m-%d %H:%i:%s' ) NEXT_START_TIME
                                            from X2_CALCULATE_START_TMP s
                                                 inner join ( select coalesce( max( PROCESS_START), now()) LAST_START_TIME
                                                                from X2_WORKLIST
                                                               where TEMPLATE_ID = ? ) p
                                                    on 1 = 1
                                           where s.TEMPLATE_ID = ?
                                             and s.NEXT_START_TIME >= s.VALIDFROM
                                             and s.NEXT_START_TIME < s.VALIDUNTIL
                                             and s.NEXT_START_TIME >= p.LAST_START_TIME
                                             order by s.NEXT_START_TIME asc
                                           limit 1",
                                         array( array( 'i', $oid ),
                                                array( 'i', $oid )));

        // zurücksetzen der Berechnungstabelle
        $db->dbRequest( "delete
                           from X2_CALCULATE_START_TMP
                          where TEMPLATE_ID = ?",
                        array( array( 'i', $oid )));

        // keine Startzeitermittlung möglich ? dann manueller Start
        if( $nextStartTime->numRows == 0 )
            $db->dbRequest( "update X2_TEMPLATE
                                set NEXT_START_TIME = null
                              where OID = ?",
                            array( array( 'i', $oid )));

        else
            foreach( $nextStartTime->resultset as $sTime )
                $db->dbRequest( "update X2_TEMPLATE
                                    set NEXT_START_TIME = str_to_date( ?, '%Y-%m-%d %H:%i:%s' )
                                  where OID = ?",
                                array( array( 's', $sTime['NEXT_START_TIME'] ),
                                       array( 'i', $oid )));

        // die letzte Laufzeit ermitteln
        $durations = $db->dbRequest( "update X2_TEMPLATE
                                         set LAST_DURATION = (select time_to_sec( timediff( max( PROCESS_STOP ), min( PROCESS_START )))
                                                                from X2_WORKLIST
                                                               where TEMPLATE_ID = ?
                                                               group by TEMPLATE_EXE_ID
                                                               order by TEMPLATE_EXE_ID desc
                                                               limit 1 )
                                       where OID = ?",
                                     array( array( 'i', $oid ),
                                            array( 'i', $oid )));
    }
}

?>
