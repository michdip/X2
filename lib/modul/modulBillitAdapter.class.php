<?php

require_once( ROOT_DIR . '/lib/modul/modulCommand.class.php' );

class modulBillitAdapter extends modulCommand
{
    public $opts = array( 'isEditable' => true,
                          'isKillable' => false,
                          'hasDelete'  => true,
                          'hasRetry'   => false
                        );

    private function getBillitAdapter( $db, $oid, $opts )
    {
        $query = 'select ba.INSTANCE,
                         ba.MODUL,
                         ba.MIN_MESSAGES,
                         ba.BMQ_CHECK_TIME,
                         ba.PRODUCER,
                         j.JOB_NAME PRODUCER_NAME
                    from ' . $opts['table'] . ' ba
                         inner join ' . $opts['refTable'] . ' j
                            on ba.PRODUCER = j.OID
                   where ba.OID = ?';

        return $db->dbRequest( $query,
                               array( array( 'i', $oid )));
    }

    protected function getGraphLabel( $db, $oid, $cols, $opts )
    {
        // die Billit-Parameter laden
        $bas = $this->getBillitAdapter( $db, $oid, $opts );

        if( $bas->numRows == 0 )
            return '';

        $bAdapter = $bas->resultset[0];

        $label = '<TR>'
               . '<TD ALIGN="left">BMQ-Instanz</TD>'
               . '<TD ALIGN="left" COLSPAN="' . $cols . '">' . $bAdapter['INSTANCE'] . '</TD>'
               . '</TR>'
               . '<TR>'
               . '<TD ALIGN="left">BMQ-Modul</TD>'
               . '<TD ALIGN="left" COLSPAN="' . $cols . '">' . $bAdapter['MODUL'] . '</TD>'
               . '</TR>'
               . '<TR>'
               . '<TD ALIGN="left">BMQ-Prüfzyklus</TD>'
               . '<TD ALIGN="left" COLSPAN="' . $cols . '">' . $bAdapter['BMQ_CHECK_TIME'] . ' sec</TD>'
               . '</TR>'
               . '<TR>'
               . '<TD ALIGN="left">min BMQ-Messages</TD>'
               . '<TD ALIGN="left" COLSPAN="' . $cols . '">' . $bAdapter['MIN_MESSAGES'] . '</TD>'
               . '</TR>'
               . '<TR>'
               . '<TD ALIGN="left">X2 BMQ-Vorgänger</TD>'
               . '<TD ALIGN="left" COLSPAN="' . $cols . '">' . $bAdapter['PRODUCER'] . ': ' . $bAdapter['PRODUCER_NAME'] .  '</TD>'
               . '</TR>';

        return $label;
    }

    protected function nativeGetJob4Edit( $db, $get, &$eJob, &$smarty, $opts )
    {
        if( isset( $get['baDefinition'] ))
        {
            $eJob['INSTANCE'] = $get['bmqInstance'];
            $eJob['MIN_MESSAGES'] = $get['minBmqMessages'];
            $eJob['PRODUCER'] = $get['bmqProducer'];
            $eJob['BMQ_CHECK_TIME'] = $get['bmqCheckTime'];
        }
        else
        {
            // die Billit-Parameter laden
            $bas = $this->getBillitAdapter( $db, $eJob['OID'], $opts );

            $bAdapter = $bas->resultset[0];

            foreach( $bAdapter as $key => $value )
                $eJob[ $key ] = $value;
        }

        // Oracle-DB öffnen
        $dbo = new dbOracle( 'BILLIT', false );

        $bmqInstances = array( );
        $bmqModul = array( );

        // alle Instanzen laden
        $allInstances = $dbo->dbRequest( "select INSTANCE
                                            from BILLITMSGINPUTQUEUE
                                           where INSTANCE is not null
                                           group by INSTANCE
                                           order by INSTANCE" );

        foreach( $allInstances->resultset as $inst )
            array_push( $bmqInstances, $inst['INSTANCE'] );

        // alle Module laden
        $allModules = $dbo->dbRequest( "select MODULE
                                          from BILLITMSGINPUTQUEUE
                                         where INSTANCE = :INS
                                         group by MODULE
                                         order by MODULE",
                                       array( 'INS' => $eJob['INSTANCE'] ));

        foreach( $allModules->resultset as $modu )
            array_push( $bmqModul, $modu['MODULE'] );

        if( isset( $get['baDefinition'] ))
            $eJob['MODUL'] = $bmqModul[ 0 ];

        // parallele Jobs laden
        if( !$opts['workJob'] )
            $parallels = $db->dbRequest( "select OID,
                                                 JOB_NAME
                                            from X2_JOBLIST
                                           where JOB_TYPE != 'START'
                                             and TEMPLATE_ID = ?
                                             and OID != ?",
                                         array( array( 'i', $get['tid'] ),
                                                array( 'i', $eJob['OID'] )));

        else
            $parallels = $db->dbRequest( "select OID,
                                                 JOB_NAME
                                            from X2_WORKLIST
                                           where TEMPLATE_EXE_ID = ?
                                             and OID != ?",
                                         array( array( 'i', $get['wid'] ),
                                                array( 'i', $eJob['OID'] )));

        $smarty->assign( 'bmqInstances', $bmqInstances );
        $smarty->assign( 'bmqModul', $bmqModul );
        $smarty->assign( 'parallelJob', $parallels->resultset );
    }

    private function nativeProcessBAChanges( $db, $post, $user, $opts, &$result )
    {
        // die Billit-Parameter speichern wenn vorhanden
        if( isset( $post['bmqInstanceChanged'] ) && $post['bmqInstanceChanged'] == 'false' &&
            isset( $post['bmqInstance'] ) && $post['bmqInstance'] != '' &&
            isset( $post['bmqModul'] ) && $post['bmqModul'] != '' &&
            isset( $post['minBmqMessages'] ) && $post['minBmqMessages'] != '' &&
            isset( $post['bmqCheckTime'] ) && $post['bmqCheckTime'] != '' &&
            isset( $post['bmqProducer'] ) && $post['bmqProducer'] != '' )
        {
            // Billit-Parameter updaten
            $db->dbRequest( "update " . $opts['table'] . "
                                set INSTANCE = ?,
                                    MODUL = ?,
                                    MIN_MESSAGES = ?,
                                    BMQ_CHECK_TIME = ?,
                                    PRODUCER = ?
                              where OID = ?",
                            array( array( 's', $post['bmqInstance'] ),
                                   array( 's', $post['bmqModul'] ),
                                   array( 'i', $post['minBmqMessages'] ),
                                   array( 'i', $post['bmqCheckTime'] ),
                                   array( 'i', $post['bmqProducer'] ),
                                   array( 'i', $post['jobID'] )),
                            true );

            // die Referenz updaten
            $db->dbRequest( "update " . $opts['refTable'] . "
                                set OBJECT_ID = ?
                              where REF_JOB = ?",
                            array( array( 'i', $post['bmqProducer'] ),
                                   array( 'i', $post['jobID'] )),
                            true );

            // Actionlog
            if( !$opts['workJob'] )
                actionlog::logAction4Job( $db,
                                          31,
                                          $post['jobID'],
                                          $user,
                                          ' / ' . $post['bmqInstance'] . ' / '
                                                . $post['bmqModul'] . ' / '
                                                . $post['minBmqMessages'] . ' / '
                                                . $post['bmqCheckTime'] . ' / '
                                                . $post['bmqProducer'] );

            else
                actionlog::logAction4WJob( $db,
                                           31,
                                           $post['jobID'],
                                           $user,
                                           ' / ' . $post['bmqInstance'] . ' / '
                                                 . $post['bmqModul'] . ' / '
                                                 . $post['minBmqMessages'] . ' / '
                                                 . $post['bmqCheckTime'] . ' / '
                                                 . $post['bmqProducer'] );
        }

        // BMQ-Modul suchen
        else if( isset( $post['bmqInstanceChanged'] ) && $post['bmqInstanceChanged'] == 'true' )
        {
            // Modul und Instanz laden
            $result['edit']           = $post['jobID'];
            $result['baDefinition']   = true;
            $result['bmqInstance']    = $post['bmqInstance'];
            $result['minBmqMessages'] = $post['minBmqMessages'];
            $result['bmqProducer']    = $post['bmqProducer'];
            $result['bmqCheckTime']   = $post['bmqCheckTime'];

            if( isset( $post['templateID'] ))
                $result['tid'] = $post['templateID'];

            else if( isset( $post['exeID'] ))
                $result['wid'] = $post['exeID'];
        }
    }

    private function nativeCreateBillitAdapter( $db, $jid, $user, $bmqInst, $bmqModul, $bmqMinMsg, $bmqCheckTime )
    {
        // die Billit-Parameter erstellen
        $db->dbRequest( "insert into X2_JOB_BILLIT_ADAPTER (OID, INSTANCE, MODUL, MIN_MESSAGES, BMQ_CHECK_TIME, PRODUCER)
                         values ( ?, ?, ?, ?, ?, ? )",
                        array( array( 'i', $jid ),
                               array( 's', $bmqInst ),
                               array( 's', $bmqModul ),
                               array( 'i', $bmqMinMsg ),
                               array( 'i', $bmqCheckTime ),
                               array( 'i', $jid )),
                        true );

        // die Referenz eintragen
        $db->dbRequest( "insert into X2_JOB_REFERENCE (OBJECT_TYPE, OBJECT_ID, REF_JOB)
                         values ('JOB', ?, ? )",
                        array( array( 'i', $jid ),
                               array( 'i', $jid )),
                        true );

        // Actionlog
        actionlog::logAction4Job( $db, 31, $jid, $user, ' / ' . $bmqModul . ' / ' . $bmqInst . ' / ' . $bmqMinMsg . ' / ' . $bmqCheckTime . ' / ' . $jid );
    }

    private function nativeCreateWorkBillitAdapter( $db, $jid, $user,
                                                    $bmqInst, $bmqModul, $bmqMinMsg, $bmqCheckTime, $bmqProducer,
                                                    $cmdHost, $cmdSrc, $cmdExePath, $cmdCmd, $cmdInsts,
                                                    $withLog = false )
    {
        // die Billit-Parameter erstellen
        $db->dbRequest( "insert into X2_WORK_BILLIT_ADAPTER (OID, INSTANCE, MODUL, MIN_MESSAGES, BMQ_CHECK_TIME, PRODUCER)
                         values ( ?, ?, ?, ?, ?, ? )",
                        array( array( 'i', $jid ),
                               array( 's', $bmqInst ),
                               array( 's', $bmqModul ),
                               array( 'i', $bmqMinMsg ),
                               array( 'i', $bmqCheckTime ),
                               array( 'i', $bmqProducer )),
                        true );

        // die Referenz eintragen
        $db->dbRequest( "insert into X2_WORK_REFERENCE (OBJECT_TYPE, OBJECT_ID, REF_JOB, CALLBACK_ID)
                         values ('JOB', ?, ?, ? )",
                        array( array( 'i', $bmqProducer ),
                               array( 'i', $jid ),
                               array( 'i', DEAMON_MODE_CALLBACK_BILLIT_ADAPTER )),
                        true );

        // Actionlog
        if( $withLog )
            actionlog::logAction4WJob( $db,
                                       31,
                                       $jid,
                                       $user,
                                       ' / ' . $bmqModul . ' / '
                                             . $bmqInst . ' / '
                                             . $bmqMinMsg . ' / '
                                             . $bmqCheckTime . ' / '
                                             . $bmqProducer );

        // Kommando erstellen
        $db->dbRequest( "insert into X2_WORK_COMMAND (OID, HOST, SOURCE, EXEC_PATH, COMMAND, INSTANCES)
                         values( ?, ?, ?, ?, ?, ? )",
                        array( array( 'i', $jid ),
                               array( 's', $cmdHost ),
                               array( 's', $cmdSrc ),
                               array( 's', $cmdExePath ),
                               array( 's', $cmdCmd ),
                               array( 's', $cmdInsts )),
                        true );

        // Actionlog
        if( $withLog )
            actionlog::logAction4WJob( $db,
                                       11,
                                       $jid,
                                       $user,
                                       ' / ' . $cmdHost . ' / '
                                             . $cmdSrc . ' / '
                                             . $cmdExePath . ' / '
                                             . $cmdCmd . ' / '
                                             . $cmdInsts );
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

    private function checkBMQCallback( $db, $wid, $time )
    {
        $db->dbRequest( "insert into X2_DEAMON (WORKLIST_OID, DEAMON_MODE, DEAMON_TIME)
                         values ( ?, ?, " . $time . ")",
                        array( array( 'i', $wid ),
                               array( 'i', DEAMON_MODE_CHECK_BMQ )),
                        true );

        $this->writeLog( $db, $wid, date( 'Y-m-d H:i:s' ) . ": BMQ-Check beauftragt zu: " . $time . "\n" );
    }

    private function checkBMQ( $db, $wid, &$logger )
    {
        $mySelfs = $this->getBillitAdapter( $db, $wid, array( 'table'    => 'X2_WORK_BILLIT_ADAPTER',
                                                              'refTable' => 'X2_WORKLIST' ));

        $mySelf = $mySelfs->resultset[0];

        $dbo = new dbOracle( 'BILLIT', false );

        $cnt = $dbo->dbRequest( "select count(*) ANZ
                                   from BILLITMESSAGES
                                  where STATE = 0
                                    and DEST_MODULE = :MDL
                                    and DEST_INSTANCE = :INS",
                                array( 'MDL' => $mySelf['MODUL'],
                                       'INS' => $mySelf['INSTANCE'] ));

        $this->writeLog( $db,
                         $wid,
                         date( 'Y-m-d H:i:s' ) . ": BMQ-Check für Instanz " . $mySelf['INSTANCE'] . " Modul: " . $mySelf['MODUL'] . " => " . $cnt->resultset[0]['ANZ'] . " Messages\n" );

        // die Jobs starten
        if( $cnt->resultset[0]['ANZ'] >= $mySelf['MIN_MESSAGES'] )
            $this->runBillitCommand( $db, $wid, $logger );

        // callback in 5 Minuten erstellen
        else
            $this->checkBMQCallback( $db, $wid, "date_add( now( ), interval " . $mySelf['BMQ_CHECK_TIME'] . " second )" );
    }

    private function runBillitCommand( $db, $wid, &$logger )
    {
        // meinen Job laden
        $mySelf = workFunctions::getWorkJob4Edit( $db, $wid );

        // die Anzahl meiner Instanzen laden
        $insts = $db->dbRequest( "select INSTANCES
                                    from X2_WORK_COMMAND
                                   where OID = ?",
                                 array( array( 'i', $wid )));

        // für jede Instanz
        for( $i = 0; $i < $insts->resultset[0]['INSTANCES']; $i++ )
        {
            // einen Job anlegen
            $newID = workFunctions::insertWorkJob( $db, $mySelf['TEMPLATE_EXE_ID'], 'COMMAND', 'BMQ Command ' . $i, $wid, WORK_JOB_POSITION_PARENT, 'CRON', true, -$mySelf['JOB_OID'] );

            // die Instanz aus der vorherigen Runde finden
            $oldInst = $db->dbRequest( "select wl.OID
                                          from X2_WORK_TREE wt
                                               inner join X2_WORKLIST wl
                                                  on     wl.OID = wt.PARENT
                                                     and wl.JOB_NAME = ?
                                         where wt.OID = ?
                                           and wt.PARENT != ?",
                                       array( array( 's', 'BMQ Command ' . $i ),
                                              array( 'i', $wid ),
                                              array( 'i', $newID )));

            foreach( $oldInst->resultset as $old )
            {
                // die alte Instanz als Vater der neuen verlinken
                workFunctions::nativeLinkWorkJob( $db, $old['OID'], $newID, $mySelf['TEMPLATE_EXE_ID'], 'CRON', true );

                // die alte Instanz vom BA trennen
                workFunctions::nativeUnlinkWorkJob( $db, $old['OID'], $wid, $mySelf['TEMPLATE_EXE_ID'], 'CRON', true );
            }

            // das Kommando duplizieren
            parent::duplicateWorkJob( $db, $mySelf['TEMPLATE_ID'], $wid, $newID, 'CRON' );

            // der Gruppe hinzufügen
            workFunctions::createWorkGroup( $db, $mySelf['TEMPLATE_EXE_ID'], -$mySelf['JOB_OID'], $newID );

            // Referenz erstellen
            $db->dbRequest( "insert into X2_WORK_REFERENCE (OBJECT_TYPE, OBJECT_ID, REF_JOB, CALLBACK_ID)
                             values ('JOB', ?, ?, ? )",
                            array( array( 'i', $newID ),
                                   array( 'i', $wid ),
                                   array( 'i', DEAMON_MODE_CALLBACK_BILLIT_COMMAND )));

            // Status auf READY_TO_RUN stellen
            workFunctions::changeWorkJobState( $db, $newID, JOB_STATES['READY_TO_RUN']['id'], 'CRON', $logger );

            // Log erstellen
            $this->writeLog( $db, $wid, date( 'Y-m-d H:i:s' ) . ": Job " . $newID . " erstellt\n" );
        }

        // in den Status WAIT_4_REF wechseln
        workFunctions::changeWorkJobState( $db, $wid, JOB_STATES['WAIT_4_REF']['id'], 'CRON', $logger );
    }

    function duplicateJob( $db, $tid, $origId, $newId, $user )
    {
        $dbo = new dbOracle( 'BILLIT', false );

        $defaults = $dbo->dbRequest( "select INSTANCE, MODULE
                                        from BILLITMSGINPUTQUEUE
                                       order by INSTANCE, MODULE" );

        if( $defaults->numRows == 0 )
            throw new Exception( "es sind keine Billit-Module vorhanden" );

        $default = $defaults->resultset[ 1 ];

        // Billit-Parameter erstellen
        $this->nativeCreateBillitAdapter( $db, $newId, $user, $default['INSTANCE'], $default['MODULE'], 0, 60 );

        // das Kommando duplizieren
        parent::duplicateJob( $db, $tid, $origId, $newId, $user );
    }

    function duplicateWorkJob( $db, $tid, $origId, $newId, $user )
    {
        $this->nativeCreateWorkBillitAdapter( $db, $newId, $user, 'unk', 'UNK', 0, 60, $newId, 'local', null, '/', 'sleep 1', 1, true );
    }

    function deleteJob( $db, $oid )
    {
        // die Referenz löschen
        $db->dbRequest( "delete
                           from X2_JOB_REFERENCE
                          where REF_JOB = ?",
                        array( array( 'i', $oid )),
                        true );

        // die Billit-Parameter löschen
        $db->dbRequest( "delete
                           from X2_JOB_BILLIT_ADAPTER
                          where OID = ?",
                        array( array( 'i', $oid )),
                        true );

        // das Kommando löschen
        parent::deleteJob( $db, $oid );
    }

    function deleteWorkJob( $db, $oid )
    {
        // das Kommando && Logfile löschen
        parent::deleteWorkJob( $db, $oid );

        // Referenz löschen
        $db->dbRequest( "delete
                           from X2_WORK_REFERENCE
                          where REF_JOB = ?",
                        array( array( 'i', $oid )),
                        true );

        // Billit-Parameter löschen
        $db->dbRequest( "delete
                           from X2_WORK_BILLIT_ADAPTER
                          where OID = ?",
                        array( array( 'i', $oid )),
                        true );
    }

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
        // mich selber laden
        $mySelfs = $this->getBillitAdapter( $db, $jid, array( 'table'    => 'X2_JOB_BILLIT_ADAPTER',
                                                              'refTable' => 'X2_JOBLIST' ));

        $mySelf = $mySelfs->resultset[0];

        // meinen Producer laden
        $bmqProducers = $db->dbRequest( "select OID
                                           from X2_WORKLIST
                                          where TEMPLATE_EXE_ID = ?
                                            and JOB_OID = ?",
                                        array( array( 'i', $exeid ),
                                               array( 'i', $mySelf['PRODUCER'] )));

        // mein Command laden
        $cmds = $db->dbRequest( "select HOST, SOURCE, EXEC_PATH, COMMAND, INSTANCES
                                   from X2_JOB_COMMAND
                                  where OID = ?",
                                array( array( 'i', $jid )));

        $mySelf['CMD'] = $cmds->resultset[0];

        // alle Variablen des Templates laden
        $vars = $db->dbRequest( "select VAR_NAME, VAR_VALUE
                                   from X2_WORK_VARIABLE
                                  where TEMPLATE_EXE_ID = ?",
                                array( array( 'i', $exeid )));

        foreach( $vars->resultset as $var )
        {
            $mySelf['CMD']['COMMAND'] = str_replace( $var['VAR_NAME'], $var['VAR_VALUE'], $mySelf['CMD']['COMMAND'] );
            $mySelf['CMD']['EXEC_PATH'] = str_replace( $var['VAR_NAME'], $var['VAR_VALUE'], $mySelf['CMD']['EXEC_PATH'] );
        }

        // die Work-Kopie anlegen
        $this->nativeCreateWorkBillitAdapter( $db,
                                              $wid,
                                              null,
                                              $mySelf['INSTANCE'],
                                              $mySelf['MODUL'],
                                              $mySelf['MIN_MESSAGES'],
                                              $mySelf['BMQ_CHECK_TIME'],
                                              $bmqProducers->resultset[0]['OID'],
                                              $mySelf['CMD']['HOST'],
                                              $mySelf['CMD']['SOURCE'],
                                              $mySelf['CMD']['EXEC_PATH'],
                                              $mySelf['CMD']['COMMAND'],
                                              $mySelf['CMD']['INSTANCES'] );
    }

    function exportJob( $db, $oid, &$mySelf, &$refMap )
    {
        // den Job exportieren
        parent::exportJob( $db, $oid, $mySelf, $refMap );

        // mich selbst laden
        $bAdapters = $this->getBillitAdapter( $db, $oid, array( 'table'    => 'X2_JOB_BILLIT_ADAPTER',
                                                                'refTable' => 'X2_JOBLIST' ));

        // mich selbst exportieren
        foreach( $bAdapters->resultset as $bAdapter )
        {
            $mySelf['bmqInstance'] = $bAdapter['INSTANCE'];
            $mySelf['bmqModul'] = $bAdapter['MODUL'];
            $mySelf['bmqMinMsgs'] = $bAdapter['MIN_MESSAGES'];
            $mySelf['bmqCheckTime'] = $bAdapter['BMQ_CHECK_TIME'];
            $mySelf['bmqProducer'] = $bAdapter['PRODUCER'];
        }
    }

    function importJob( $db, $tid, $oid, $job, $user )
    {
        // das Kommando importieren
        parent::importJob( $db, $tid, $oid, $job, $user );

        // die Billit-Parameter erstellen
        $this->nativeCreateBillitAdapter( $db, $oid, $user, $job['bmqInstance'], $job['bmqModul'], $job['bmqMinMsgs'], $job['bmqCheckTime'] );
    }

    // nach dem Import: update der Referenzen
    function updateRef( $db, $oid, $ref, $user )
    {
        // update des PRODUCER
        $db->dbRequest( "update X2_JOB_BILLIT_ADAPTER
                            set PRODUCER = ?
                          where OID = ?",
                        array( array( 'i', $ref ),
                               array( 'i', $oid )),
                        true );

        $db->dbRequest( "update X2_JOB_REFERENCE
                            set OBJECT_ID = ?
                          where OBJECT_TYPE = 'JOB'
                            and REF_JOB = ?",
                        array( array( 'i', $ref ),
                               array( 'i', $oid )),
                        true );

        // Actionlog
        actionlog::logAction4Job( $db, 31, $oid, $user, ' / / / / ' . $ref );
    }

    function getJobLabel( $db, $template, $oid, $writeable, $pageID )
    {
        // das Kommando laden
        $label = parent::getGraphLabel( $db, $oid, 4, array( 'instances' => true,
                                                             'table'     => 'X2_JOB_COMMAND' ));

        // die Billit-Parameter laden
        $label .= $this->getGraphLabel( $db, $oid, 4, array( 'table'    => 'X2_JOB_BILLIT_ADAPTER',
                                                             'refTable' => 'X2_JOBLIST' ));

        return $label;
    }

    function getWorkJobLabel( $db, $oid )
    {
        // das Kommando laden
        $label = parent::getGraphLabel( $db, $oid, 6, array( 'instances' => true,
                                                             'table'     => 'X2_WORK_COMMAND' ));

        // die Billit-Parameter laden
        $label .= $this->getGraphLabel( $db, $oid, 6, array( 'table'    => 'X2_WORK_BILLIT_ADAPTER',
                                                             'refTable' => 'X2_WORKLIST' ));

        return $label;
    }

    function getJob4Edit( $db, $get, &$eJob, &$smarty )
    {
        // das Kommando laden
        parent::nativeGetJob4Edit( $db, $get, $eJob, $smarty, array( 'instances' => true,
                                                                     'table'     => 'X2_JOB_COMMAND' ));

        // die Billit-Parameter laden
        $this->nativeGetJob4Edit( $db, $get, $eJob, $smarty, array( 'table'    => 'X2_JOB_BILLIT_ADAPTER',
                                                                    'refTable' => 'X2_JOBLIST',
                                                                    'workJob'  => false ));
    }

    function getWorkJob4Edit( $db, $get, &$eJob, &$smarty )
    {
        // das Kommando laden
        parent::nativeGetJob4Edit( $db, $get, $eJob, $smarty, array( 'instances' => true,
                                                                     'table'     => 'X2_WORK_COMMAND' ));

        // die Billit-Parameter laden
        $this->nativeGetJob4Edit( $db, $get, $eJob, $smarty, array( 'table'    => 'X2_WORK_BILLIT_ADAPTER',
                                                                    'refTable' => 'X2_WORKLIST',
                                                                    'workJob'  => true ));
    }

    function processJobChanges( $db, $post, $user )
    {
        // das Kommando speichern
        $result = parent::nativeProcessJobChanges( $db, $post, $user, array( 'instances'   => true,
                                                                             'table'       => 'X2_JOB_COMMAND',
                                                                             'workJob'     => false ));

        // Billit-Parameter speichern
        $this->nativeProcessBAChanges( $db,
                                       $post,
                                       $user,
                                       array( 'table'    => 'X2_JOB_BILLIT_ADAPTER',
                                              'refTable' => 'X2_JOB_REFERENCE',
                                              'workJob'  => false ),
                                       $result );

        return $result;
    }

    function processWorkJobChanges( $db, $post, $user )
    {
        $result = array( );

        // Das Template laden
        $template = templateFunctions::getTemplate2GraphByWID( $db, $post['exeID'] );

        // das Logfile öffnen
        $logger = new logger( $db, 'modulBillitAdapter::processWorkJobChanges( ' . $user . ' )' );

        // Mutex holen
        if( !mutex::requestMutex( $db, $logger, 'TEMPLATE', $template['OID'], $user, 5, 1 ))
        {
            print "Kein Mutex erhalten!";
            return $result;
        }

        // ist das Template angehalten
        if( $template['STATE'] != TEMPLATE_STATES['PAUSED'] &&
            $template['STATE'] != TEMPLATE_STATES['POWER_OFF'] )
        {
            print "Das Template ist nicht pausiert";

            mutex::releaseMutex( $db, $logger, 'TEMPLATE', $template['OID'] );

            return $result;
        }

        // das Kommando speichern
        $result = parent::nativeProcessJobChanges( $db, $post, $user, array( 'instances'   => true,
                                                                             'table'       => 'X2_WORK_COMMAND',
                                                                             'workJob'     => true ));

        // die Billit-Parameter speichern
        $this->nativeProcessBAChanges( $db, 
                                       $post, 
                                       $user, 
                                       array( 'table'    => 'X2_WORK_BILLIT_ADAPTER',
                                              'refTable' => 'X2_WORK_REFERENCE',
                                              'workJob'  => true ),
                                       $result );

        // Mutex zurückgeben
        mutex::releaseMutex( $db, $logger, 'TEMPLATE', $template['OID'] );

        return $result;
    }

    /* Beim Start tut der Job gar nix
     * Er wartet auf das Starten des Producers.
     * Dieser wird initial den check auf die BMQ starten.
     * Danach wird der check alle X Minuten ausgeführt.
     *
     * Endet der Producer, so werden ein letztes mal alle
     * Messages verarbeitet und der Job beendet sich danach
     */
    function runJob( $db, $wid, &$logger )
    {
        // auf running setzen
        workFunctions::changeWorkJobState( $db, $wid, JOB_STATES['WAIT_4_PRODUCER']['id'], 'CRON', $logger );

        $this->writeLog( $db, $wid, date( 'Y-m-d H:i:s' ) . ": Billit-Adapter gestartet\n" );
    }

    function processDeamonMessages( $db, &$logger )
    {
        $logger->writeLog( "modulBillitAdapter::processDeamonMessages( )" );

        $msgs = $db->dbRequest( "select WORKLIST_OID,
                                        DEAMON_MODE,
                                        DEAMON_MESSAGE,
                                        OID
                                   from X2_DEAMON
                                  where DEAMON_MODE in ( ?, ?, ? )
                                    and DEAMON_TIME < now( )",
                                array( array( 'i', DEAMON_MODE_CHECK_BMQ ),
                                       array( 'i', DEAMON_MODE_CALLBACK_BILLIT_ADAPTER ),
                                       array( 'i', DEAMON_MODE_CALLBACK_BILLIT_COMMAND )));

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

                try
                {
                    // uncomment 4 Debug
                    //$this->writeLog( $db, $msg['WORKLIST_OID'], date( 'Y-m-d H:i:s' ) . ": MSG: " . $msg['OID'] . " Mode: " . $msg['DEAMON_MODE'] . " State: " . $mySelf['STATE'] . "\n" );

                    // den DeamonMode unterscheiden
                    switch( $msg['DEAMON_MODE'] )
                    {
                        case DEAMON_MODE_CHECK_BMQ: /* steht der Job im richtigen Status, dann die BMQ-checken
                                                     * läuft der Producer nicht mehr, dann ist der Status des BA bereits WAIT_4_FINISH
                                                     */
                                                    if( workFunctions::hasWJobState( $db, $msg['WORKLIST_OID'], JOB_STATES['WAIT_4_BMQ_CHECK']['id'] ))
                                                        $this->checkBMQ( $db, $msg['WORKLIST_OID'], $logger );

                                                    break;

                        case DEAMON_MODE_CALLBACK_BILLIT_ADAPTER: // die Message parsen
                                                                  $myMessage = json_decode( $msg['DEAMON_MESSAGE'], true );

                                                                  // wurde der Producer gestartet, dann erstmals die BMQ checken
                                                                  if( workFunctions::hasWJobStateOrder( $db, $myMessage['wid'], JOB_STATES['RUNNING']['id'] ))
                                                                  {
                                                                      /* bin ich im Status WAIT_4_PRODUCER,
                                                                       * dann in den Status WAIT_4_BMQ_CHECK wechseln
                                                                       * und den BMQ-Check zu sofot beauftragen
                                                                       */
                                                                      if( workFunctions::hasWJobState( $db, $msg['WORKLIST_OID'], JOB_STATES['WAIT_4_PRODUCER']['id'] ))
                                                                      {
                                                                          workFunctions::changeWorkJobState( $db, $msg['WORKLIST_OID'], JOB_STATES['WAIT_4_BMQ_CHECK']['id'], 'CRON', $logger );

                                                                          $this->checkBMQCallback( $db, $msg['WORKLIST_OID'], "now( )", $logger );
                                                                      }

                                                                      // sonst, so lange ich nicht gestartet bin, die Nachricht behalten
                                                                      else if( workFunctions::hasWJobStateOrder( $db, $msg['WORKLIST_OID'], JOB_STATES['CREATED']['id'] ))
                                                                          $delDeamon = false;
                                                                  }

                                                                  // wurde der Producer beendet
                                                                  else if( workFunctions::hasWJobStateOrder( $db, $myMessage['wid'], JOB_STATES['OK']['id'] ))
                                                                  {
                                                                      /* unterschieden werden die 3 Staties des BAdapter
                                                                       * WAIT_4_PRODUCER
                                                                       * * direkt auf OK wechseln
                                                                       *
                                                                       * WAIT_4_BMQ_CHECK
                                                                       * * die Abarbeitung starten
                                                                       * * auf WAIT_4_FINISH wechseln
                                                                       *
                                                                       * WAIT_4_REF
                                                                       * * auf WAIT_4_FINISH wechseln
                                                                       */
                                                                      if( workFunctions::hasWJobState( $db, $msg['WORKLIST_OID'], JOB_STATES['WAIT_4_PRODUCER']['id'] ))
                                                                          workFunctions::changeWorkJobState( $db, $msg['WORKLIST_OID'], JOB_STATES['OK']['id'], 'CRON', $logger );

                                                                      else if( workFunctions::hasWJobState( $db, $msg['WORKLIST_OID'], JOB_STATES['WAIT_4_BMQ_CHECK']['id'] ))
                                                                      {
                                                                          $this->runBillitCommand( $db, $msg['WORKLIST_OID'], $logger );

                                                                          workFunctions::changeWorkJobState( $db, $msg['WORKLIST_OID'], JOB_STATES['WAIT_4_FINISH']['id'], 'CRON', $logger );
                                                                      }
                                                                      else if( workFunctions::hasWJobState( $db, $msg['WORKLIST_OID'], JOB_STATES['WAIT_4_REF']['id'] ))
                                                                          workFunctions::changeWorkJobState( $db, $msg['WORKLIST_OID'], JOB_STATES['WAIT_4_FINISH']['id'], 'CRON', $logger );
                                                                  }

                                                                  break;

                        case DEAMON_MODE_CALLBACK_BILLIT_COMMAND: /* wenn alle Parents aus OK sind,
                                                                   * im MODUS WAIT_4_REF => WAIT_4_BMQ_CHECK + callback beauftragen
                                                                   * im Modus WAIT_4_FINISH => nach OK wechseln
                                                                   */
                                                                  $parents = $db->dbRequest( "select 1
                                                                                                from X2_WORK_TREE wt
                                                                                                     inner join X2_WORKLIST wl
                                                                                                        on wl.OID = wt.PARENT
                                                                                                     inner join X2_JOB_STATE js
                                                                                                        on     js.JOB_STATE = wl.STATE
                                                                                                           and js.STATE_ORDER != ?
                                                                                               where wt.OID = ?",
                                                                                             array( array( 'i', JOB_STATES['OK']['id'] ),
                                                                                                    array( 'i', $msg['WORKLIST_OID'] )));

                                                                  if( $parents->numRows == 0 )
                                                                  {
                                                                      if( workFunctions::hasWJobState( $db, $msg['WORKLIST_OID'], JOB_STATES['WAIT_4_REF']['id'] ))
                                                                      {
                                                                          $chkTime = $db->dbRequest( "select BMQ_CHECK_TIME
                                                                                                        from X2_WORK_BILLIT_ADAPTER
                                                                                                       where OID = ?",
                                                                                                     array( array( 'i', $msg['WORKLIST_OID'] )));

                                                                          $this->checkBMQCallback( $db,
                                                                                                   $msg['WORKLIST_OID'],
                                                                                                   "date_add( now( ), interval " . $chkTime->resultset[0]['BMQ_CHECK_TIME'] . " second )" );

                                                                          workFunctions::changeWorkJobState( $db, $msg['WORKLIST_OID'], JOB_STATES['WAIT_4_BMQ_CHECK']['id'], 'CRON', $logger );
                                                                      }
                                                                      else if( workFunctions::hasWJobState( $db, $msg['WORKLIST_OID'], JOB_STATES['WAIT_4_FINISH']['id'] ))
                                                                      {
                                                                        workFunctions::changeWorkJobState( $db, $msg['WORKLIST_OID'], JOB_STATES['OK']['id'], 'CRON', $logger );

                                                                        $db->dbRequest( "update X2_WORKLIST
                                                                                            set PROCESS_STOP = now( )
                                                                                          where OID = ?",
                                                                                        array( array( 'i', $msg['WORKLIST_OID'] )),
                                                                                        true );
                                                                      }
                                                                  }

                                                                  break;
                    }

                    $db->commit( );
                }
                catch( Exception $e )
                {
                    $db->rollback( );
                    $logger->writeLog( $e->getMessage( ));
                }

                // den Mutex zurückgeben
                mutex::releaseMutex( $db, $logger, 'TEMPLATE', $mySelf['TEMPLATE_ID'] );
            }

            if( $delDeamon )
            {
                $db->dbRequest( "delete
                                   from X2_DEAMON
                                  where OID = ?",
                                array( array( 'i', $msg['OID'] )));

                $db->commit( );
            }
        }
    }

    function finishWorkJob( $db, $wid, $message, &$logger )
    {
    }

}

?>
