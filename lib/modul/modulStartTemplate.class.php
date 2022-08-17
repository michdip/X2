<?php

require_once( ROOT_DIR . '/lib/class/logger.class.php' );

class modulStartTemplate implements modulInterface
{
    public static $REF_MODES = array( 'OK'    => 'diesen Job ohne Fehler beenden',
                                      'ERROR' => 'diesen Job mit Fehler beenden',
                                      'WAIT'  => 'auf das Ende der laufenden Instanz warten, ohne Neustart der Referenz',
                                      'RETRY' => 'auf das Ende der laufenden Instanz warten, dann Neustart auslösen' );

    public static $REF_RUN_MODES = array( 'FOREGROUND' => 'nach dem Start auf das Ende des Templates warten',
                                          'BACKGROUND' => 'nach dem Start des Templates diesen Job beenden' );

    public static $REF_VAR_MODES = array( 'NONE'     => 'keine Weitergabe der Variablen',
                                          'PRIORITY' => 'priorisierte Weitergabe der Variablen' );

    public $opts = array( 'isEditable' => true,
                          'isKillable' => false,
                          'hasDelete'  => true,
                          'hasRetry'   => false 
                        );

    private $refJFSCallID;

    private function getGraphLabel( $ref, $cols )
    {
        $label = '<TR>'
               . '<TD>Modus der Referenz</TD>'
               . '<TD ALIGN="left" COLSPAN="' . $cols . '">' . modulStartTemplate::$REF_MODES[ $ref['START_MODE'] ] . '</TD>'
               . '</TR>'
               . '<TR>'
               . '<TD>Modus beim Start</TD>'
               . '<TD ALIGN="left" COLSPAN="' . $cols . '">' . modulStartTemplate::$REF_RUN_MODES[ $ref['RUN_MODE'] ] . '</TD>'
               . '</TR>'
               . '<TR>'
               . '<TD>Variablen</TD>'
               . '<TD ALIGN="left" COLSPAN="' . $cols . '">' . modulStartTemplate::$REF_VAR_MODES[ $ref['VAR_MODE'] ] . '</TD>'
               . '</TR>'
               . '<TR>'
               . '<TD>Referenz</TD>';

        if( isset( $ref['REF_EXE'] ) && $ref['REF_EXE'] != '' )
            $label .= '<TD ALIGN="left" COLSPAN="' . ( $cols - 1 )  . '">'
                        . $ref['OID'] . ': ' . $ref['OBJECT_NAME'] . ' (' . $ref['OBJECT_TYPE'] . ')'
                    . '</TD>'
                    . '<TD  HREF="worklist.php?wid=' . $ref['REF_EXE'] . '" '
                       . 'TARGET="_parent" '
                       .  'TITLE="zur referenzierten Ausführung springen" '
                       . 'HEIGHT="25" '
                       .  'WIDTH="20">'
                       . '<FONT FACE="Glyphicons Halflings">&#xe003;</FONT>'
                    . '</TD>';

        else
            $label .= '<TD ALIGN="left" COLSPAN="' . $cols . '">'
                         . $ref['OID'] . ': ' . $ref['OBJECT_NAME'] . ' (' . $ref['OBJECT_TYPE'] . ')'
                    . '</TD>';

        $label .= '</TR>';

        return $label;
    }

    private function nativeGetJob4Edit( $db, $get, &$eJob, &$smarty, $workJob )
    {
        $smarty->assign( 'refModes', modulStartTemplate::$REF_MODES );
        $smarty->assign( 'refRuns', modulStartTemplate::$REF_RUN_MODES );
        $smarty->assign( 'refVars', modulStartTemplate::$REF_VAR_MODES );

        // wurde eine Referenz gesucht den Job im get übergeben
        if( isset( $get['jobDefinition'] ))
        {
            if( isset( $get['ReferenceFound'] ))
                $eJob['TEMPLATE_REFERENCE'] = $get['ReferenceFound'];

            $eJob['START_MODE'] = $get['refMode'];
            $eJob['VAR_MODE'] = $get['refVars'];
            $eJob['RUN_MODE'] = $get['refRun'];
        }

        // den Job aus der DB laden
        else
        {
            if( !$workJob )
                $mySelf = $db->dbRequest( "select TEMPLATE_ID TEMPLATE_REFERENCE, START_MODE, VAR_MODE, RUN_MODE
                                             from X2_JOB_START_TEMPLATE
                                            where OID = ?",
                                          array( array( 'i', $get['edit'] )));

            else
                $mySelf = $db->dbRequest( "select TEMPLATE_ID TEMPLATE_REFERENCE, START_MODE, VAR_MODE, RUN_MODE
                                             from X2_WORK_START_TEMPLATE
                                            where OID = ?",
                                          array( array( 'i', $get['edit'] )));

            foreach( $mySelf->resultset as $row )
                foreach( $row as $key => $value )
                    $eJob[ $key ] = $value;
        }

        // auf der Suche nach einer Referenz die Suchergebnisse übergeben
        if( isset( $get['jobReference'] ))
            $smarty->assign( 'jobReference', $get['jobReference'] );
    }

    private function nativeProcessJobChanges( $db, $post, $user, $workJob )
    {
        // der Job soll gespeichert werden
        if( !isset( $post['findReference'] ) &&
            isset( $post['refMode'] ) && $post['refMode'] != '' &&
            isset( $post['refRun'] ) && $post['refRun'] != '' &&
            isset( $post['refVars'] ) && $post['refVars'] != '' &&
            isset( $post['jobReference'] ) && $post['jobReference'] != '' )
        {
            try
            {
                if( !$workJob )
                {
                    $db->dbRequest( "update X2_JOB_START_TEMPLATE
                                        set START_MODE = ?,
                                            RUN_MODE = ?,
                                            VAR_MODE = ?,
                                            TEMPLATE_ID = ?
                                      where OID = ?",
                                    array( array( 's', $post['refMode'] ),
                                           array( 's', $post['refRun'] ),
                                           array( 's', $post['refVars'] ),
                                           array( 'i', $post['jobReference'] ),
                                           array( 'i', $post['jobID'] )),
                                    true );

                    $db->dbRequest( "update X2_JOB_REFERENCE
                                        set OBJECT_ID = ?
                                      where REF_JOB = ?",
                                    array( array( 'i', $post['jobReference'] ),
                                           array( 'i', $post['jobID'] )),
                                    true );

                    actionlog::logAction4Job( $db,
                                              17,
                                              $post['jobID'],
                                              $user,
                                              ' / ' . $post['jobReference'] . ' / '
                                                    . $post['refMode'] . ' / '
                                                    . $post['refRun'] . ' / '
                                                    . $post['refVars'] );
                }
                else
                {
                    $db->dbRequest( "update X2_WORK_START_TEMPLATE
                                        set START_MODE = ?,
                                            RUN_MODE = ?,
                                            VAR_MODE = ?,
                                            TEMPLATE_ID = ?
                                      where OID = ?",
                                    array( array( 's', $post['refMode'] ),
                                           array( 's', $post['refRun'] ),
                                           array( 's', $post['refVars'] ),
                                           array( 'i', $post['jobReference'] ),
                                           array( 'i', $post['jobID'] )),
                                    true );

                    actionlog::logAction4WJob( $db,
                                               17,
                                               $post['jobID'],
                                               $user,
                                               ' / ' . $post['jobReference'] . ' / '
                                                     . $post['refMode'] . ' / '
                                                     . $post['refRun'] . ' / '
                                                     . $post['refVars'] );
                }

                $db->commit( );
            }
            catch( Exception $e )
            {
                $db->rollback();
                print $e->getMessage();
            }
        }

        // es soll nach einem Template gesucht werden
        else if( isset( $post['findReference'] ))
        {
            // nach dem Template suchen
            $ref = $db->dbRequest( "select OID,
                                           OBJECT_NAME
                                      from X2_TEMPLATE
                                     where OBJECT_TYPE = 'T'
                                       and OID != ?
                                       and (   OBJECT_NAME like ?
                                            or convert( OID, char ) like ? )",
                                   array( array( 'i', $post['templateID'] ),
                                          array( 's', trim( $post['jobReference'] )),
                                          array( 's', trim( $post['jobReference'] ))));

            return array( 'edit'          => $post['jobID'],
                          'jobDefinition' => true,
                          'jobReference'  => $ref->resultset,
                          'refMode'       => $post['refMode'],
                          'refRun'        => $post['refRun'],
                          'refVars'       => $post['refVars'] );
        }

        // die Referenz wurde gefunden
        else if( isset( $post['ReferenceFound'] ))
        {
            return array( 'edit'           => $post['jobID'],
                          'jobDefinition'  => true,
                          'ReferenceFound' => $post['ReferenceFound'],
                          'refMode'        => $post['refMode'],
                          'refRun'         => $post['refRun'],
                          'refVars'        => $post['refVars'] );
        }

        return array( );
    }

    private function writeLog( $db, $wid, $message )
    {
        $db->dbRequest( "insert into X2_LOGFILE (OID, DATA)
                         values ( ?, ? )
                         on duplicate key update DATA = concat( DATA, ? )",
                        array( array( 'i', $wid ),
                               array( 's', $message ),
                               array( 's', $message )),
                        true );
    }

    private function finishMySelf( $db, $wid, $state, &$logger, $reset = false )
    {
        $tValue = 'now( )';

        if( $reset )
            $tValue = 'null';

        // PROCESS_STOP setzen
        $db->dbRequest( "update X2_WORKLIST
                            set PROCESS_STOP = " . $tValue . "
                          where OID = ?",
                        array( array( 'i', $wid )),
                        true );

        // den STATE setzen
        workFunctions::changeWorkJobState( $db, $wid, $state, 'CRON', $logger );
    }

    private function nativeCreateCallback( $db, $wid, $exeID, &$logMessage )
    {
        $db->dbRequest( "insert into X2_WORK_REFERENCE (OBJECT_TYPE, OBJECT_ID, REF_JOB, CALLBACK_ID)
                         values ( 'TEMPLATE_EXE_ID', ?, ?, ? )",
                        array( array( 'i', $exeID ),
                               array( 'i', $wid ),
                               array( 'i', DEAMON_MODE_CALLBACK_START_TEMPLATE )),
                        true );

        $logMessage .= date( 'Y-m-d H:i:s' ) . ": Callback wurde beauftragt\n";
    }

    private function createCallback( $db, $wid, $tid, &$logMessage )
    {
        /* die laufende Instanz ermitteln
         * JOB_STATE CREATED und RUNNING können nur in der letzten EXE_ID vorkommen
         */
        $exeIDs = $db->dbRequest( "select wl.TEMPLATE_EXE_ID
                                     from X2_WORKLIST wl
                                          inner join X2_JOB_STATE js
                                             on     js.JOB_STATE = wl.STATE
                                                and js.STATE_ORDER in ( ?, ? )
                                    where wl.TEMPLATE_ID = ?",
                                  array( array( 'i', JOB_STATES['CREATED']['id'] ),
                                         array( 'i', JOB_STATES['RUNNING']['id'] ),
                                         array( 'i', $tid )));

        if( $exeIDs->numRows == 0 )
        {
            $logMessage .= date( 'Y-m-d H:i:s' ) . ": Es wurde keine laufende Instanz des Template gefunden\n";
            return false;
        }

        $exeID = $exeIDs->resultset[0]['TEMPLATE_EXE_ID'];

        $logMessage .= date( 'Y-m-d H:i:s' ) . ': Das Template wird aktuell mit der EXE_ID ' . $exeID . " ausgeführt\n";

        // einen CallBack beauftragen
        $this->nativeCreateCallback( $db, $wid, $exeID, $logMessage );

        // die EXE-ID hinterlegen
        $this->addRefExeID( $db, $wid, $exeID );

        return true;
    }

    private function addRefExeID( $db, $wid, $refExe )
    {
        $db->dbRequest( "update X2_WORK_START_TEMPLATE
                            set REF_EXE = ?
                          where OID = ?",
                        array( array( 'i', $refExe ),
                               array( 'i', $wid )));
    }

    private function processRunMode( $db, $wid, $runMode, $exeID, &$logMessage, &$logger )
    {
        $logMessage .= date( 'Y-m-d H:i:s' ) . ': Das Template wird mit der EXE_ID ' . $exeID . " ausgeführt\n";

        $this->addRefExeID( $db, $wid, $exeID );

        switch( $runMode )
        {
            // nach dem Callback in den Status RUNNING_REF wechseln
            case 'FOREGROUND': $this->nativeCreateCallback( $db, $wid, $exeID, $logMessage );

                               workFunctions::changeWorkJobState( $db, $wid, JOB_STATES['RUNNING_REF']['id'], 'CRON', $logger );

                               break;

            case 'BACKGROUND': $logMessage .= date( 'Y-m-d H:i:s' ) . ": Beenden ohne Fehler\n";

                               $this->finishMySelf( $db, $wid, JOB_STATES['OK']['id'], $logger );

                               break;
        }
    }

    function duplicateJob( $db, $tid, $origId, $newId, $user, $startMode = 'OK', $varMode = 'NONE', $runMode = 'FOREGROUND' )
    {
        // das Template der getLogs verlinken
        $getLogs = $db->dbRequest( "select SEQ_VALUE
                                      from SEQUENCE
                                     where SEQ_NAME = 'X2_MASHINE_ROOM_getLogs'" );

        $getLog = $getLogs->resultset[0]['SEQ_VALUE'];

        $db->dbRequest( "insert into X2_JOB_START_TEMPLATE (OID, TEMPLATE_ID, START_MODE, VAR_MODE, RUN_MODE)
                         values ( ?, ?, ?, ?, ? )",
                        array( array( 'i', $newId ),
                               array( 'i', $getLog ),
                               array( 's', $startMode ),
                               array( 's', $varMode ),
                               array( 's', $runMode )),
                        true );

        $db->dbRequest( "insert into X2_JOB_REFERENCE (OBJECT_TYPE, OBJECT_ID, REF_JOB)
                         values ('TEMPLATE', ?, ? )",
                        array( array( 'i', $getLog ),
                               array( 'i', $newId )),
                        true );

        actionlog::logAction4Job( $db, 17, $newId, $user, ' / ' . $getLog . ' / ' . $startMode . ' / ' . $varMode . ' / ' . $runMode );
    }

    function duplicateWorkJob( $db, $tid, $origId, $newId, $user, $startMode = 'OK', $varMode = 'NONE', $runMode = 'FOREGROUND' )
    {
        // das Template der getLogs verlinken
        $getLogs = $db->dbRequest( "select SEQ_VALUE
                                      from SEQUENCE
                                     where SEQ_NAME = 'X2_MASHINE_ROOM_getLogs'" );

        $getLog = $getLogs->resultset[0]['SEQ_VALUE'];

        $db->dbRequest( "insert into X2_WORK_START_TEMPLATE (OID, TEMPLATE_ID, START_MODE, VAR_MODE, RUN_MODE)
                         values ( ?, ?, ?, ?, ? )",
                        array( array( 'i', $newId ),
                               array( 'i', $getLog ),
                               array( 's', $startMode ),
                               array( 's', $varMode ),
                               array( 's', $runMode )),
                        true );

        actionlog::logAction4WJob( $db, 17, $newId, $user, ' / ' . $getLog . ' / ' . $startMode . ' / ' . $varMode . ' / ' . $runMode );
    }

    function deleteJob( $db, $oid )
    {
        // die Referenz löschen
        $db->dbRequest( "delete
                           from X2_JOB_REFERENCE
                          where REF_JOB = ?",
                        array( array( 'i', $oid )),
                        true );

        // meine Konfiguration löschen
        $db->dbRequest( "delete
                           from X2_JOB_START_TEMPLATE
                          where OID = ?",
                        array( array( 'i', $oid )),
                        true );
    }

    function deleteWorkJob( $db, $oid )
    {
        // meine Kofig löschen
        $db->dbRequest( "delete
                           from X2_WORK_START_TEMPLATE
                          where OID = ?",
                        array( array( 'i', $oid )),
                        true );

        // die Referenz löschen
        $db->dbRequest( "delete
                           from X2_WORK_REFERENCE
                          where REF_JOB = ?",
                        array( array( 'i', $oid )),
                        true );

        // das Logfile löschen
        $db->dbRequest( "delete
                           from X2_LOGFILE
                          where OID = ?",
                        array( array( 'i', $oid )),
                        true );
    }

    // ein StartTemplate muss nicht archiviert werden
    function archiveWorkJob( $db, $wid )
    {
        $db->dbRequest( "insert into X2_ARCHIV (JOB_OID,
                                                TEMPLATE_ID,
                                                TEMPLATE_EXE_ID,
                                                STATE,
                                                PROCESS_START,
                                                PROCESS_STOP,
                                                PROCESS_DURATION,
                                                LOGDATA)
                         select wl.JOB_OID,
                                wl.TEMPLATE_ID,
                                wl.TEMPLATE_EXE_ID,
                                wl.STATE,
                                wl.PROCESS_START,
                                wl.PROCESS_STOP,
                                timestampdiff( second, wl.PROCESS_START, wl.PROCESS_STOP),
                                compress(l.DATA)
                           from X2_WORKLIST wl
                                left join X2_LOGFILE l
                                  on l.OID = wl.OID
                          where wl.PROCESS_START is not null
                            and wl.OID = ?",
                        array( array( 'i', $wid )),
                        true );
    }


    function createWorkCopy( $db, $jid, $wid, $exeid, &$logger )
    {
        // die JOB_START_TEMPLATE in die WORK_START_TEMPLATE kopieren
        $db->dbRequest( "insert into X2_WORK_START_TEMPLATE (OID, TEMPLATE_ID, START_MODE, VAR_MODE, RUN_MODE)
                         select ?, TEMPLATE_ID, START_MODE, VAR_MODE, RUN_MODE
                           from X2_JOB_START_TEMPLATE
                          where OID = ?",
                        array( array( 'i', $wid ),
                               array( 'i', $jid )),
                        true );
    }

    // den Job exportieren
    function exportJob( $db, $oid, &$mySelf, &$refMap )
    {
        $refs = $db->dbRequest( "select st.TEMPLATE_ID, st.START_MODE, st.VAR_MODE, st.RUN_MODE, rt.OBJECT_NAME
                                   from X2_JOB_START_TEMPLATE st
                                        inner join X2_TEMPLATE rt
                                           on rt.OID = st.TEMPLATE_ID
                                  where st.OID = ?",
                                array( array( 'i', $oid )));

        foreach( $refs->resultset as $ref )
        {
            $mySelf['refTid']  = $ref['TEMPLATE_ID'];
            $mySelf['sMode']   = $ref['START_MODE'];
            $mySelf['varMode'] = $ref['VAR_MODE'];
            $mySelf['runMode'] = $ref['RUN_MODE'];

            if( !isset( $refMap['refTid'][ $ref['TEMPLATE_ID']] ))
                $refMap['refTid'][ $ref['TEMPLATE_ID']] = $ref['OBJECT_NAME'];
        }
    }

    // den Job importieren
    function importJob( $db, $tid, $oid, $job, $user )
    {
        // zunächst wird die Referenz auf das getLog-Template gesetzt. Erst im Anschluß wird das Ziel eingefügt
        $this->duplicateJob( $db, null, null, $oid, $user, $job['sMode'], $job['varMode'], $job['runMode'] );
    }

    // nach dem Import: update der Referenzen
    function postImport( $db, $oid, $ref, $user )
    {
        $db->dbRequest( "update X2_JOB_START_TEMPLATE
                            set TEMPLATE_ID = ?
                          where OID = ?",
                        array( array( 'i', $ref ),
                               array( 'i', $oid )),
                        true );

        $db->dbRequest( "update X2_JOB_REFERENCE
                            set OBJECT_ID = ?
                          where OBJECT_TYPE = 'TEMPLATE'
                            and REF_JOB = ?",
                        array( array( 'i', $ref ),
                               array( 'i', $oid )),
                        true );

        actionlog::logAction4Job( $db, 26, $oid, $user, ' / ' . $ref );
    }

    // erstellt die GV-Ausgabe in der Job-Ansicht
    function getJobLabel( $db, $template, $oid, $writeable, $pageID )
    {
        $label = '';

        $refs = $db->dbRequest( "select t.OID, t.OBJECT_TYPE, t.OBJECT_NAME, r.START_MODE, r.VAR_MODE, r.RUN_MODE
                                   from X2_JOB_START_TEMPLATE r
                                        inner join X2_TEMPLATE t
                                           on t.OID  = r.TEMPLATE_ID
                                  where r.OID = ?",
                                array( array( 'i', $oid )));

        foreach( $refs->resultset as $ref )
            $label = $this->getGraphLabel( $ref, 4 );

        return $label;
    }

    function getWorkJobLabel( $db, $oid )
    {
        $label = '';

        $refs = $db->dbRequest( "select t.OID, t.OBJECT_TYPE, t.OBJECT_NAME, r.START_MODE, r.VAR_MODE, r.RUN_MODE, r.REF_EXE
                                   from X2_WORK_START_TEMPLATE r
                                        inner join X2_TEMPLATE t
                                           on t.OID  = r.TEMPLATE_ID
                                  where r.OID = ?",
                                array( array( 'i', $oid )));

        foreach( $refs->resultset as $ref )
            $label = $this->getGraphLabel( $ref, 6 );

        return $label;
    }

    // diese Funktion reichert den Job zum Editieren durch die TPL-Datei an
    function getJob4Edit( $db, $get, &$eJob, &$smarty )
    {
        $this->nativeGetJob4Edit( $db, $get, $eJob, $smarty, false );
    }

    function getWorkJob4Edit( $db, $get, &$eJob, &$smarty )
    {
        $this->nativeGetJob4Edit( $db, $get, $eJob, $smarty, true );
    }

    function processJobChanges( $db, $post, $user )
    {
        return $this->nativeProcessJobChanges( $db, $post, $user, false );
    }

    function processWorkJobChanges( $db, $post, $user )
    {
        // Das Template laden
        $template = templateFunctions::getTemplate2GraphByWID( $db, $post['exeID'] );

        // das Logfile öffnen
        $logger = new logger( $db, 'modulStartTemplate::processWorkJobChanges( ' . $user . ' )' );

        // Mutex holen
        if( !mutex::requestMutex( $db, $logger, 'TEMPLATE', $template['OID'], $user, 5, 1 ))
        {
            print "Kein Mutex erhalten!";
            return array( );
        }

        // ist das Template angehalten
        if( $template['STATE'] != TEMPLATE_STATES['PAUSED'] &&
            $template['STATE'] != TEMPLATE_STATES['POWER_OFF'] )
        {
            print "Das Template ist nicht pausiert";

            mutex::releaseMutex( $db, $logger, 'TEMPLATE', $template['OID'] );

            return array( );
        }

        // nativeProcessJobChanges benötigt die TID im post
        $post['templateID'] = $template['OID'];

        $result = $this->nativeProcessJobChanges( $db, $post, $user, true );

        // Mutex zurückgeben
        mutex::releaseMutex( $db, $logger, 'TEMPLATE', $template['OID'] );

        return $result;
    }

    function refJFStartCall( $db, $exeID )
    {
        $db->dbRequest( "insert into X2_WORK_VARIABLE (TEMPLAtE_EXE_ID, VAR_NAME, VAR_VAlUE)
                         select ?, wv.VAR_NAME, wv.VAR_VAlUE
                           from X2_WORK_VARIABLE wv
                                inner join X2_WORKLIST wl
                                   on     wl.TEMPLATE_EXE_ID = wv.TEMPLATE_EXE_ID
                                      and wl.OID = ?",
                        array( array( 'i', $exeID ),
                               array( 'i', $this->refJFSCallID )),
                        true );
    }

    function runJob( $db, $wid, &$logger )
    {
        // !!! doppelter Code zu processDeamonMessages !!!
        // mich laden
        $mySelfs = $db->dbRequest( "select TEMPLATE_ID, START_MODE, VAR_MODE, RUN_MODE
                                      from X2_WORK_START_TEMPLATE
                                     where OID = ?",
                                   array( array( 'i', $wid )));

        $mySelf = $mySelfs->resultset[0];

        $logDate = date( 'Y-m-d H:i:s' ) . ': ';
        $logMessage = $logDate . 'EXE_MODE: ' . $mySelf['START_MODE'] . "\n"
                    . $logDate . 'VAR_MODE: ' . $mySelf['VAR_MODE'] . "\n"
                    . $logDate . 'RUN_MODE: ' . $mySelf['RUN_MODE'] . "\n"
                    . $logDate . 'Starte Template ' . $mySelf['TEMPLATE_ID'] . "\n";

        // Variablen priorisiert weitergeben, dann den refJFSCall nutzen
        $ref = null;

        if( $mySelf['VAR_MODE'] == 'PRIORITY' )
        {
            $this->refJFSCallID = $wid;
            $ref = $this;
        }

        // versuche die Referenz zu starten
        $pID = jobFunctions::start( $db, $mySelf['TEMPLATE_ID'], 'CRON', $logger, true, $ref );

        // die Referenz läuft noch / hat andere Probleme
        if( $pID < 0 )
            switch( $mySelf['START_MODE'] )
            {
                case 'OK': $logDate = date( 'Y-m-d H:i:s' ) . ': ';
                           $logMessage .= $logDate . "Das Template konnte nicht gestartet werden\n"
                                        . $logDate . "Beenden ohne Fehler\n";

                           // ohne Fehler mich beenden
                           $this->finishMySelf( $db, $wid, JOB_STATES['OK']['id'], $logger );
                           break;

                case 'ERROR': $logDate = date( 'Y-m-d H:i:s' ) . ': ';
                              $logMessage .= $logDate . "Das Template konnte nicht gestartet werden\n"
                                           . $logDate . "Beenden mit Fehler\n";

                              // mit Fehler beenden
                              $this->finishMySelf( $db, $wid, JOB_STATES['ERROR']['id'], $logger );
                              break;

                // zum Warten auf das Ende der Ausführung wird ein Callback beauftragt
                case 'WAIT': if( !$this->createCallback( $db, $wid, $mySelf['TEMPLATE_ID'], $logMessage ))
                             {
                                 $logMessage .= date( 'Y-m-d H:i:s' ) . ": Beenden ohne Fehler\n";

                                 // ohne Fehler mich beenden
                                 $this->finishMySelf( $db, $wid, JOB_STATES['OK']['id'], $logger );
                             }

                             // mich als RUNNING setzen
                             else
                                 workFunctions::changeWorkJobState( $db, $wid, JOB_STATES['RUNNING_REF']['id'], 'CRON', $logger );

                             break;

                // zum Warten auf das Ende der Ausführung wird ein Callback beauftragt
                case 'RETRY': if( !$this->createCallback( $db, $wid, $mySelf['TEMPLATE_ID'], $logMessage ))
                              {
                                  $logMessage .= date( 'Y-m-d H:i:s' ) . ": Beenden mit Fehler\n";

                                  // ohne Fehler mich beenden
                                  $this->finishMySelf( $db, $wid, JOB_STATES['ERROR']['id'], $logger );
                              }

                              // mich als RUNNING setzen
                              else
                              {
                                  $this->addRefExeID( $db, $wid, null );
                                  workFunctions::changeWorkJobState( $db, $wid, JOB_STATES['WAIT_4_REF']['id'], 'CRON', $logger );
                              }

                              break;
            }

        // der Start war erfolgreich
        else
            $this->processRunMode( $db, $wid, $mySelf['RUN_MODE'], $pID, $logMessage, $logger );

        // das LogFile schreiben
        $this->writeLog( $db, $wid, $logMessage );
    }

    function processDeamonMessages( $db, &$logger )
    {
        $logger->writeLog( 'modulStartTemplate::processDeamonMessages( )' );

        $msgs = $db->dbRequest( "select OID,
                                        WORKLIST_OID,
                                        DEAMON_MESSAGE,
                                        DEAMON_MODE
                                   from X2_DEAMON
                                  where DEAMON_MODE = ?",
                                array( array( 'i', DEAMON_MODE_CALLBACK_START_TEMPLATE )));

        $logger->writeLog( 'Es werden ' . $msgs->numRows . ' verarbeitet' );

        foreach( $msgs->resultset as $msg )
        {
            // den Job laden
            $mySelf = workFunctions::getWorkJob4Edit( $db, $msg['WORKLIST_OID'] );
            $delDeamon = false;

            // gibt es den Job noch
            if( $mySelf == null )
                $delDeamon = true;

            // den Mutex für mein Template holen
            else if( mutex::requestMutex( $db, $logger, 'TEMPLATE', $mySelf['TEMPLATE_ID'], 'CRON', 1, 0 ))
            {
                $delDeamon = true;

                // ich warte auf den Neustart des Templates
                if( workFunctions::hasWJobState( $db, $msg['WORKLIST_OID'], JOB_STATES['WAIT_4_REF']['id'] ))
                {
                    // !!! doppelter Code zu runJob !!!
                    // meine Konfiguration laden
                    $myConfs = $db->dbRequest( "select TEMPLATE_ID, START_MODE, VAR_MODE, RUN_MODE
                                                  from X2_WORK_START_TEMPLATE
                                                  where OID = ?",
                                               array( array( 'i', $msg['WORKLIST_OID'] )));

                    $myConf = $myConfs->resultset[0];

                    // Variablen priorisiert weitergeben, dann den refJFSCall nutzen
                    $ref = null;

                    if( $myConf['VAR_MODE'] == 'PRIORITY' )
                    {
                        $this->refJFSCallID = $wid;
                        $ref = $this;
                    }

                    // versuche die Referenz zu starten
                    $pID = jobFunctions::start( $db, $myConf['TEMPLATE_ID'], 'CRON', $logger, true, $ref );

                    // Template läuft noch
                    if( $pID < 0 )
                        $this->writeLog( $db, $msg['WORKLIST_OID'], date( 'Y-m-d H:i:s' ) . ": Callback: das Template läuft noch\n" );

                    else
                    {
                        $logMessage = date( 'Y-m-d H:i:s' ) . ": Callback: die Ausführung des Template ist beendet\n";

                        $this->processRunMode( $db, $msg['WORKLIST_OID'], $myConf['RUN_MODE'], $pID, $logMessage, $logger );

                        $this->writeLog( $db, $msg['WORKLIST_OID'], $logMessage );
                    }
                }

                // ich warte auf das Ende des Templates
                else if( workFunctions::hasWJobState( $db, $msg['WORKLIST_OID'], JOB_STATES['RUNNING_REF']['id'] ) ||
                         workFunctions::hasWJobState( $db, $msg['WORKLIST_OID'], JOB_STATES['ERROR_REF']['id'] ))
                {
                    $myMessage = json_decode( $msg['DEAMON_MESSAGE'], true );

                    // welchen Status hat das Template
                    $tStats = $db->dbRequest( "with d as ( select case when js.STATE_ORDER in ( ?, ? )
                                                                       then 1
                                                                       else 0
                                                                   end STATE_RUNNING,
                                                                  case when js.STATE_ORDER = ?
                                                                       then 1
                                                                       else 0
                                                                   end STATE_ERROR,
                                                                  case when js.STATE_ORDER = ?
                                                                       then 1
                                                                       else 0
                                                                   end STATE_FINISH
                                                             from X2_WORKLIST wl
                                                                  inner join X2_JOB_STATE js
                                                                     on wl.STATE = js.JOB_STATE
                                                            where wl.TEMPLATE_EXE_ID = ? )
                                               select sum( STATE_RUNNING ) STATE_RUNNING,
                                                      sum( STATE_ERROR ) STATE_ERROR,
                                                      sum( STATE_FINISH ) STATE_FINISH
                                                 from d",
                                              array( array( 'i', JOB_STATES['CREATED']['id'] ),
                                                     array( 'i', JOB_STATES['RUNNING']['id'] ),
                                                     array( 'i', JOB_STATES['ERROR']['id'] ),
                                                     array( 'i', JOB_STATES['OK']['id'] ),
                                                     array( 'i', $myMessage['exeid'] )));

                    $logMessage = date( 'Y-m-d H:i:s' ) . ': Callback: Anzahl von WorkJob-States: ' . $tStats->numRows . "\n";

                    if( $tStats->numRows == 0 )
                        $this->finishMySelf( $db, $msg['WORKLIST_OID'], JOB_STATES['OK']['id'], $logger );

                    foreach( $tStats->resultset as $tStat )
                    {
                        $logMessage .= date( 'Y-m-d H:i:s' ) . ': Callback: Running: ' . $tStat['STATE_RUNNING']
                                                                        . ' Error: ' . $tStat['STATE_ERROR']
                                                                        . ' OK: ' . $tStat['STATE_FINISH'] . "\n";

                        // es sind Fehler vorhanden => auf Fehler gehen
                        if( $tStat['STATE_ERROR'] > 0 )
                        {
                            if( workFunctions::hasWJobState( $db, $msg['WORKLIST_OID'], JOB_STATES['RUNNING_REF']['id'] ))
                                $this->finishMySelf( $db, $msg['WORKLIST_OID'], JOB_STATES['ERROR_REF']['id'], $logger );
                        }

                        // keine Errors aber laufende
                        else if( $tStat['STATE_RUNNING'] > 0 )
                        {
                            if( workFunctions::hasWJobState( $db, $msg['WORKLIST_OID'], JOB_STATES['ERROR_REF']['id'] ))
                                $this->finishMySelf( $db, $msg['WORKLIST_OID'], JOB_STATES['RUNNING_REF']['id'], $logger, true );
                        }

                        // sonst finish
                        else
                            $this->finishMySelf( $db, $msg['WORKLIST_OID'], JOB_STATES['OK']['id'], $logger );
                    }

                    $this->writeLog( $db, $msg['WORKLIST_OID'], $logMessage );
                }

                // den Mutex zurückgeben
                mutex::releaseMutex( $db, $logger, 'TEMPLATE', $mySelf['TEMPLATE_ID'] );
            }

            if( $delDeamon )
                $db->dbRequest( "delete
                                   from X2_DEAMON
                                  where OID = ?",
                                array( array( 'i', $msg['OID'] )));

            $db->commit( );
        }
    }

    function finishWorkJob( $db, $wid, $message, &$logger )
    {
    }
}

?>
