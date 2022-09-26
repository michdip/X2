<?php

require_once( ROOT_DIR . '/lib/class/ssh.class.php' );
require_once( ROOT_DIR . '/lib/class/logger.class.php' );
require_once( ROOT_DIR . '/lib/class/workFunctions.class.php' );
require_once( ROOT_DIR . '/lib/class/jobFunctions.class.php' );

class modulCommand implements modulInterface
{
    public $opts = array( 'isEditable' => true,
                          'isKillable' => true,
                          'hasDelete'  => true,
                          'hasRetry'   => true
                        );

    private function nativeCreateCommand( $db, $oid, $host, $source, $exePath, $cmd, $instances, $retries, $retryTime, $user )
    {
        $db->dbRequest( "insert into X2_JOB_COMMAND (OID,
                                                     HOST,
                                                     SOURCE,
                                                     EXEC_PATH,
                                                     COMMAND,
                                                     INSTANCES,
                                                     RETRIES,
                                                     RETRY_TIME)
                         values ( ?, ?, ?, ?, ?, ?, ?, ? )",
                        array( array( 'i', $oid ),
                               array( 's', $host ),
                               array( 's', $source ),
                               array( 's', $exePath ),
                               array( 's', $cmd ),
                               array( 's', $instances ),
                               array( 's', $retries ),
                               array( 's', $retryTime )),
                        true );

        actionlog::logAction4Job( $db,
                                  11,
                                  $oid,
                                  $user,
                                  ' / ' . $host . ' / '
                                        . $source . ' / '
                                        . $exePath . ' / '
                                        . $cmd . ' / '
                                        . $instances . ' / '
                                        . $retries . ' / '
                                        . $retryTime );
    }

    private function nativeCreateWorkCommand( $db, $oid, $host, $source, $exePath, $cmd, $retries, $retryTime, $user )
    {
        $db->dbRequest( "insert into X2_WORK_COMMAND (OID,
                                                      HOST,
                                                      SOURCE,
                                                      EXEC_PATH,
                                                      COMMAND,
                                                      RETRIES,
                                                      RETRY_TIME)
                         values ( ?, ?, ?, ?, ?, ?, ? )",
                        array( array( 'i', $oid ),
                               array( 's', $host ),
                               array( 's', $source ),
                               array( 's', $exePath ),
                               array( 's', $cmd ),
                               array( 's', $retries ),
                               array( 's', $retryTime )),
                        true );

        actionlog::logAction4WJob( $db,
                                   11,
                                   $oid,
                                   $user,
                                   ' / ' . $host . ' / '
                                         . $source . ' / '
                                         . $exePath . ' / '
                                         . $cmd . ' / '
                                         . $retries . ' / '
                                         . $retryTime );
    }

    protected function getGraphLabel( $db, $oid, $cols, $opts )
    {
        // das Kommando laden
        $query = "select HOST,
                         SOURCE,
                         EXEC_PATH,
                         replace( replace( COMMAND, '>', '&gt;' ), '<', '&lt;' ) COMMAND";

        if( isset( $opts['instances'] ) && $opts['instances'] )
            $query .= ', INSTANCES';

        if( isset( $opts['retries'] ) && $opts['retries'] )
            $query .= ', RETRIES, RETRY_TIME';

        $query .= ' from ' . $opts['table' ] . '
                   where OID = ?';

        $cmds = $db->dbRequest( $query,
                                array( array( 'i', $oid )));

        if( $cmds->numRows == 0 )
            return '';

        $cmd = $cmds->resultset[0];

        // die Tabellenbreite festlegen
        $colsPart = $cols - 1;

        // das Label erstellen
        $label = '<TR>'
               . '<TD ALIGN="left">Host</TD>'
               . '<TD ALIGN="left" COLSPAN="' . $cols . '">' . $cmd['HOST'] . '</TD>'
               . '</TR>'
               . '<TR>'
               . '<TD ALIGN="left">SH-Umgebung</TD>'
               . '<TD ALIGN="left" COLSPAN="' . $colsPart . '">' . $cmd['SOURCE'] . '</TD>'
               . '<TD HREF="javascript:parent.copyToClipboard(\'S' . $oid . '\')" '
                   . 'TITLE="in die Zwischenablage kopieren" '
                   . 'WIDTH="20" HEIGHT="25" VALIGN="BOTTOM"> '
                   . '<FONT FACE="Glyphicons Halflings">&#xe224;</FONT>'
               . '</TD>'
               . '</TR>'
               . '<TR>'
               . '<TD ALIGN="left">EXE-Pfad</TD>'
               . '<TD ALIGN="left" COLSPAN="' . $colsPart . '">' . $cmd['EXEC_PATH'] . '</TD>'
               . '<TD HREF="javascript:parent.copyToClipboard(\'P' . $oid . '\')" '
                   . 'TITLE="in die Zwischenablage kopieren" '
                   . 'WIDTH="20" HEIGHT="25" VALIGN="BOTTOM"> '
                   . '<FONT FACE="Glyphicons Halflings">&#xe224;</FONT>'
               . '</TD>'
               . '</TR>';

        if( isset( $opts['instances'] ) && $opts['instances'] )
            $label .= '<TR>'
                    . '<TD ALIGN="left">Instanzen</TD>'
                    . '<TD ALIGN="left" COLSPAN="' . $cols . '">' . $cmd['INSTANCES' ] . '</TD>'
                    . '</TR>';

        if( isset( $opts['retries'] ) && $opts['retries'] )
            $label .= '<TR>'
                    . '<TD ALIGN="left">Retries</TD>'
                    . '<TD ALIGN="left" COLSPAN="' . $colsPart . '">' . $cmd['RETRIES' ] . ' ( ' . $cmd['RETRY_TIME'] . ' sec )</TD>'
                    . '<TD>&nbsp;</TD>'
                    . '</TR>';

        $label .= '<TR>'
                . '<TD ALIGN="left">Befehl</TD>'
                . '<TD ALIGN="left" COLSPAN="' . $colsPart . '">' . implode( '<BR/>', str_split( $cmd['COMMAND'], 100 )) . '</TD>'
                . '<TD HREF="javascript:parent.copyToClipboard(\'C' . $oid . '\')" '
                    . 'TITLE="in die Zwischenablage kopieren" '
                    . 'WIDTH="20" HEIGHT="25" VALIGN="BOTTOM"> '
                    . '<FONT FACE="Glyphicons Halflings">&#xe224;</FONT>'
                . '</TD>'
                . '</TR>';

        return $label;
    }

    protected function nativeGetJob4Edit( $db, $get, &$eJob, &$smarty, $cmdOpts )
    {
        $smarty->assign( 'cmdOpts', $cmdOpts );
        $smarty->assign( 'hosts', REMOTE_HOSTS );

        // wurde der Job im get übergeben diesen nehen
        if( isset( $get['jobDefinition'] ))
        {
            if( isset( $get['patternFound'] ))
                $eJob['COMMAND'] = $get['patternFound'];

            $eJob['HOST'] = $get['host'];
            $eJob['SOURCE'] = $get['source'];
            $eJob['EXEC_PATH'] = $get['execpath'];

            if( isset( $cmdOpts['instances'] ) && $cmdOpts['instances'] )
                $eJob['INSTANCES'] = $get['instances'];

            if( isset( $cmdOpts['retries'] ) && $cmdOpts['retries'] )
            {
                $eJob['RETRIES'] = $get['retries'];
                $eJob['RETRY_TIME'] = $get['retryTime'];
            }
        }

        // den Job aus der DB laden
        else
        {
            $query = 'select OID, HOST, SOURCE, EXEC_PATH, COMMAND';

            if( isset( $cmdOpts['instances'] ) && $cmdOpts['instances'] )
                $query .= ', INSTANCES';

            if( isset( $cmdOpts['retries'] ) && $cmdOpts['retries'] )
                $query .= ', RETRIES, RETRY_TIME';

            $query .= ' from ' . $cmdOpts['table'] . '
                       where OID = ?';

            $mySelf = $db->dbRequest( $query,
                                      array( array( 'i', $get['edit'] )));

            foreach( $mySelf->resultset as $row )
                foreach( $row as $key => $value )
                    $eJob[ $key ] = $value;
        }

        // bei der Suche nach einem Pattern die Properties setzen
        if( isset( $get['matches'] ))
            $smarty->assign( 'matches', $get['matches'] );
    }

    protected function nativeProcessJobChanges( $db, $post, $user, $opts, &$logger = null )
    {
        // der Job soll gepeichert werden
        if( !isset( $post['findPattern'] ) &&
            isset( $post['host'] ) && $post['host'] != '' &&
            isset( $post['execpath'] ) && $post['execpath'] != '' &&
            isset( $post['command'] ) && $post['command'] != ''
          )
        {
            try
            {
                // das Statement zusammensetzen
                $query = 'update ' . $opts['table'] . '
                             set HOST = ?,
                                 SOURCE = ?,
                                 EXEC_PATH = ?,
                                 COMMAND = ?';

                $params = array( array( 's', $post['host'] ),
                                 array( 's', $post['source'] ),
                                 array( 's', $post['execpath'] ),
                                 array( 's', $post['command'] ));

                $message = ' / ' . $post['host'] . ' / '
                                 . $post['source'] . ' / '
                                 . $post['execpath'] . ' / '
                                 . $post['command'] . ' / ';

                // Instanzen
                if( isset( $opts['instances'] ) && $opts['instances'] )
                {
                    $query .= ', INSTANCES = ?';

                    if( isset( $post['instances'] ) && $post['instances'] != '' )
                    {
                        array_push( $params, array( 's', $post['instances'] ));
                        $message .= $post['instances'] . ' / ';
                    }
                    else
                    {
                        array_push( $params, array( 's', 0 ));
                        $message .= '0 / ';
                    }
                }
                else
                    $message .= ' / ';

                // Retries
                if( isset( $opts['retries'] ) && $opts['retries'] )
                {
                    $query .= ', RETRIES = ?, RETRY_TIME = ?';

                    if( isset( $post['retries'] ) && $post['retries'] != '' )
                    {
                        array_push( $params, array( 's', $post['retries'] ));
                        $message .= $post['retries'] . ' / ';
                    }
                    else
                    {
                        array_push( $params, array( 's', 0 ));
                        $message .= '0 / ';
                    }

                    if( isset( $post['retryTime'] ) && $post['retryTime'] != '' )
                    {
                        array_push( $params, array( 's', $post['retryTime'] ));
                        $message .= $post['retryTime'] . ' / ';
                    }
                    else
                    {
                        array_push( $params, array( 's', 0 ));
                        $message .= '0 / ';
                    }
                }
                else
                    $message .= ' / ';

                // nur die OID des Job
                $query .= ' where OID = ?';
                array_push( $params, array( 's', $post['jobID'] ));

                // das Update an die DB senden
                $db->dbRequest( $query, $params, true );

                // das Actionlog schreiben
                if( !$opts['workJob'] )
                    actionlog::logAction4Job( $db, 11, $post['jobID'], $user, $message );

                else
                    actionlog::logAction4WJob( $db, 11, $post['jobID'], $user, $message );

                $db->commit( );
            }
            catch( Exception $e )
            {
                $db->rollback();
                print $e->getMessage();
            }

            return array( );
        }

        // es soll nach einem Pattern auf der Maschine gesucht werden
        else if( isset( $post['findPattern'] ))
        {
            // das Logfile öffnen
            if( $logger == null )
                $logger = new logger( $db, 'modulCommand::processJobChanges( ... )' );

            // die SSH-Verbindung erstellen
            $ssh = sshFactory( $post['host'], $logger );

            // nach Dateien des Pattern suchen
            if( $ssh->activ )
                $files = $ssh->execute( 'cd ' . $post['execpath'] . '; ls -1 ' . $post['command'] );

            $matches = explode( "\n", $files );

            // das Pattern an erste Stelle im Ergebnis setzen
            array_unshift( $matches, $post['command'] );

            if( !$workJob )
                return array( 'edit'          => $post['jobID'],
                              'jobDefinition' => true,
                              'host'          => $post['host'],
                              'source'        => $post['source'],
                              'execpath'      => $post['execpath'],
                              'instances'     => $post['instances'],
                              'retries'       => $post['retries'],
                              'retryTime'     => $post['retryTime'],
                              'matches'       => $matches );

            return array( 'edit'          => $post['jobID'],
                          'jobDefinition' => true,
                          'host'          => $post['host'],
                          'source'        => $post['source'],
                          'execpath'      => $post['execpath'],
                          'retries'       => $post['retries'],
                          'retryTime'     => $post['retryTime'],
                          'matches'       => $matches );
        }

        // ein Pattern wurde gefunden
        else if( !$workJob && isset( $post['patternFound'] ))
            return array( 'edit'          => $post['jobID'],
                          'patternFound'  => $post['patternFound'],
                          'jobDefinition' => true,
                          'host'          => $post['host'],
                          'source'        => $post['source'],
                          'execpath'      => $post['execpath'],
                          'instances'     => $post['instances'],
                          'retries'       => $post['retries'],
                          'retryTime'     => $post['retryTime'] );

        else if( isset( $post['patternFound'] ))
            return array( 'edit'          => $post['jobID'],
                          'patternFound'  => $post['patternFound'],
                          'jobDefinition' => true,
                          'host'          => $post['host'],
                          'source'        => $post['source'],
                          'execpath'      => $post['execpath'],
                          'retries'       => $post['retries'],
                          'retryTime'     => $post['retryTime'] );

        return array( );
    }

    /* diese Funktion wird aus dem x2Deamon
     * sie setzt die ProzessID auf dem Host an den laufenden JOB
     * dabei verändert sich der Status von Deamonized auf Running
     *
     * bei sehr kurzen Ausführungszeiten kann die Reihenfolge der Messages
     * durcheinander sein. Deshalb muss der aktuelle Status des Jobs vorher
     * geprüft werden
     */
    private function setHostPID( $db, $msg, &$logger )
    {
        // die Message parsen
        $myMessage = json_decode( $msg['DEAMON_MESSAGE'], true );

        // ist keine PID vorhanden
        if( !isset( $myMessage['pid'] ))
            return;

        $logger->writeLog( 'WID: ' . $msg['WORKLIST_OID'] . ' meldet PID: ' . $myMessage['pid'] );

        // die PID setzen
        $db->dbRequest( "update X2_WORK_COMMAND
                            set HOST_PID = ?
                          where OID = ?",
                        array( array( 'i', $myMessage['pid'] ),
                               array( 'i', $msg['WORKLIST_OID'] )));

        // Actionlog
        actionlog::logAction4WJob( $db, 30, $msg['WORKLIST_OID'], 'CRON', ' / ' . $myMessage['pid'] );

        // den Status prüfen und ggf setzen
        if( workFunctions::hasWJobState( $db, $msg['WORKLIST_OID'], JOB_STATES['DEAMONIZED']['id'] ))
            workFunctions::changeWorkJobState( $db, $msg['WORKLIST_OID'], JOB_STATES['RUNNING']['id'], 'CRON', $logger );
    }

    private function setNativeLogFile( $db, $msg, &$logger )
    {
        // die Message parsen
        $myMessage = json_decode( $msg['DEAMON_MESSAGE'], true );

        // ist keine PID vorhanden
        if( !isset( $myMessage['logFile'] ) || !isset( $myMessage['logDate'] ))
            return;

        // den Host des Job laden
        $host = $db->dbRequest( "select HOST
                                   from X2_WORK_COMMAND
                                  where OID = ?",
                                array( array( 'i', $msg['WORKLIST_OID'] )));

        if( $host->numRows != 1 )
        {
            $logger->writeLog( 'der Host von ' . $msg['WORKLIST_OID'] . ' wurde nicht gefunden' );
            return;
        }

        $logger->writeLog( 'Native-Log-File ' . $myMessage['logFile'] . ' für Host: ' . $host->resultset[0]['HOST'] . ' gemeldet' );

        logRotation::reportLogFile( $db, $host->resultset[0]['HOST'], $myMessage['logFile'], $myMessage['logDate'] );
    }

    private function killWorkJob( $db, $exeID, $wid, $user, &$logger )
    {
        // bin ich am laufen
        if( !workFunctions::hasWJobState( $db, $wid, JOB_STATES['RUNNING']['id'] ))
            print "Der Job wird nicht ausgeführt";

        // mich laden
        $mySelfs = $db->dbRequest( "select HOST,
                                           HOST_PID
                                      from X2_WORK_COMMAND
                                     where OID = ?",
                                   array( array( 'i', $wid )));

        foreach( $mySelfs->resultset as $mySelf )
            // habe ich eine HOST_PID
            if( $mySelf['HOST_PID'] != '' )
            {
                try
                {
                    // einen neuen Job einfügen
                    $killID = workFunctions::insertWorkJob( $db,
                                                            $exeID,
                                                            'COMMAND',
                                                            'Kill Job ' . $wid,
                                                            $wid,
                                                            WORK_JOB_POSITION_PARENT,
                                                            $user,
                                                            true,
                                                            -1 );

                    // das Kommando setzen
                    $this->nativeCreateWorkCommand( $db,
                                                    $killID,
                                                    $mySelf['HOST'],
                                                    null,
                                                    '/',
                                                    'kill -9 ' . $mySelf['HOST_PID'],
                                                    0,
                                                    0,
                                                    $user );

                    // den Status auf READY_TO_RUN setzen
                    workFunctions::changeWorkJobState( $db, $killID, JOB_STATES['READY_TO_RUN']['id'], $user, $logger );

                    $db->commit( );
                }
                catch( Exception $e )
                {
                    $db->rollback();
                    print $e->getMessage();
                }
            }
    }

    /* diese Funktion dupliziert einen Job,
     * dabei kann sie aufgerufen werden, wenn
     * * ein neuer Job in einem Graphen eingefügt wird
     * * der JobTyp sich ändert
     * * das Template dupliziert wird
     */
    function duplicateJob( $db, $tid, $origId, $newId, $user )
    {
        // ist das Original ein COMMAND, dann Parameter übernehmen
        $old = $db->dbRequest( "select jl.TEMPLATE_ID,
                                       jc.HOST,
                                       jc.SOURCE,
                                       jc.EXEC_PATH,
                                       jc.COMMAND,
                                       jc.INSTANCES,
                                       jc.RETRIES,
                                       jc.RETRY_TIME
                                  from X2_JOB_COMMAND jc
                                       inner join X2_JOBLIST jl
                                          on jl.OID = jc.OID
                                 where jl.OID = ?",
                               array( array( 'i', $origId )));

        // das Original ist kein COMMAND, dann defaults setzen
        if( $old->numRows == 0 )
            $this->nativeCreateCommand( $db, $newId, 'local', null, '/', 'sleep 1', 1, 0, 0, $user );

        else
            foreach( $old->resultset as $row )
            {
                /* bei identischem Template ( => neuer Job )
                 * nur HOST, SOURCE und EXEC_PATH übernehmen
                 *
                 * sonst => duplicateTemplate
                 */
                if( $tid == $row['TEMPLATE_ID'] )
                {
                    $row['COMMAND'] = 'sleep 1';
                    $row['INSTANCES'] = 1;
                    $row['RETRIES'] = 0;
                    $row['RETRY_TIME'] = 0;
                }

                $this->nativeCreateCommand( $db,
                                            $newId,
                                            $row['HOST'],
                                            $row['SOURCE'],
                                            $row['EXEC_PATH'],
                                            $row['COMMAND'],
                                            $row['INSTANCES'],
                                            $row['RETRIES'],
                                            $row['RETRY_TIME'],
                                            $user );
            }
    }

    /* diese Funktion dupliziert einen WorkJob,
     * dabei kann sie aufgerufen werden, wenn
     * * ein neuer Job in einem Graphen eingefügt wird
     * * der JobTyp sich ändert
     */
    function duplicateWorkJob( $db, $tid, $origId, $newId, $user )
    {
        // ist das Original ein COMMAND, dann Parameter übernehmen
        $old = $db->dbRequest( "select wl.TEMPLATE_ID,
                                       wc.HOST,
                                       wc.SOURCE,
                                       wc.EXEC_PATH,
                                       wc.COMMAND,
                                       wc.RETRIES,
                                       wc.RETRY_TIME,
                                       js.IS_RESTARTABLE
                                  from X2_WORK_COMMAND wc
                                       inner join X2_WORKLIST wl
                                          on wl.OID = wc.OID
                                       inner join X2_JOB_STATE js
                                          on js.JOB_STATE = wl.STATE
                                 where wl.OID = ?",
                               array( array( 'i', $origId )));

        // das  original ist kein COMMAND, dann defaults setzen
        if( $old->numRows == 0 )
            $this->nativeCreateWorkCommand( $db, $newId, 'local', null, '/', 'sleep 1', 0, 0, $user );

        else
            foreach( $old->resultset as $row )
            {
                // wenn IS_RESTARTABLE, dann die RETRIES reduzieren
                if( $row['IS_RESTARTABLE'] == 1 )
                    $row['RETRIES']--;

                $this->nativeCreateWorkCommand( $db,
                                                $newId,
                                                $row['HOST'],
                                                $row['SOURCE'],
                                                $row['EXEC_PATH'],
                                                $row['COMMAND'],
                                                $row['RETRIES'],
                                                $row['RETRY_TIME'],
                                                $user );
            }
    }

    function deleteJob( $db, $oid )
    {
        $db->dbRequest( "delete
                           from X2_JOB_COMMAND
                          where OID = ?",
                        array( array( 'i', $oid )),
                        true );
    }

    function deleteWorkJob( $db, $oid )
    {
        // das Kommando löschen
        $db->dbRequest( "delete
                           from X2_WORK_COMMAND
                          where OID = ?",
                        array( array( 'i', $oid )),
                        true );

        // das Logfile löschen
        $db->dbRequest( "delete
                           from X2_LOGFILE
                          where OID = ?",
                        array( array( 'i', $oid )),
                        true );
    }

    // Archiviert den Job
    function archiveWorkJob( $db, $wid )
    {
        $db->dbRequest( "insert into X2_ARCHIV (JOB_OID,
                                                TEMPLATE_ID,
                                                TEMPLATE_EXE_ID,
                                                HOST,
                                                SOURCE,
                                                EXEC_PATH,
                                                COMMAND,
                                                STATE,
                                                PROCESS_START,
                                                PROCESS_STOP,
                                                PROCESS_DURATION,
                                                LOGDATA)
                         select wl.JOB_OID,
                                wl.TEMPLATE_ID,
                                wl.TEMPLATE_EXE_ID,
                                wc.HOST,
                                wc.SOURCE,
                                wc.EXEC_PATH,
                                wc.COMMAND,
                                wl.STATE,
                                wl.PROCESS_START,
                                wl.PROCESS_STOP,
                                timestampdiff( second, wl.PROCESS_START, wl.PROCESS_STOP),
                                compress(l.DATA)
                           from X2_WORKLIST wl
                                inner join X2_WORK_COMMAND wc
                                   on wc.OID = wl.OID
                                left join X2_LOGFILE l
                                  on l.OID = wl.OID
                          where wl.PROCESS_START is not null
                            and wl.OID = ?",
                        array( array( 'i', $wid )),
                        true );
    }

    // die Work-Kopie erstellen
    function createWorkCopy( $db, $jid, $wid, $exeid, &$logger )
    {
        // mich selber laden
        $mySelf = jobFunctions::getJob4Edit( $db, $jid );

        // den Befehl aus der X2_JOB_COMMAND laden
        $cmds = $db->dbRequest( "select HOST, SOURCE, EXEC_PATH, COMMAND, INSTANCES, RETRIES, RETRY_TIME
                                   from X2_JOB_COMMAND
                                  where OID = ?",
                                array( array( 'i', $jid )));

        // alle Variablen des Templates laden
        $vars = $db->dbRequest( "select VAR_NAME, VAR_VALUE
                                      from X2_WORK_VARIABLE
                                     where TEMPLATE_EXE_ID = ?",
                                   array( array( 'i', $exeid )));

        foreach( $cmds->resultset as $cmd )
        {
            // alle Variablen auf das Kommando und des EXE-PATH anwenden
            foreach( $vars->resultset as $var )
            {
                $cmd['COMMAND'] = str_replace( $var['VAR_NAME'], $var['VAR_VALUE'], $cmd['COMMAND'] );
                $cmd['EXEC_PATH'] = str_replace( $var['VAR_NAME'], $var['VAR_VALUE'], $cmd['EXEC_PATH'] );
            }

            // mein eigenes Kommando erstellen
            $db->dbRequest( "insert into X2_WORK_COMMAND (OID, HOST, SOURCE, EXEC_PATH, COMMAND, RETRIES, RETRY_TIME)
                             values ( ?, ?, ?, ?, ?, ?, ? )",
                            array( array( 'i', $wid ),
                                   array( 's', $cmd['HOST'] ),
                                   array( 's', $cmd['SOURCE'] ),
                                   array( 's', $cmd['EXEC_PATH'] ),
                                   array( 's', $cmd['COMMAND'] ),
                                   array( 'i', $cmd['RETRIES'] ),
                                   array( 'i', $cmd['RETRY_TIME'] )),
                            true );

            // bei mehr als einer Instanz
            if( $cmd['INSTANCES'] != 1 )
            {
                // eine Gruppe gründen
                workFunctions::createWorkGroup( $db, $exeid, $jid, $wid );

                // Für alle Instanzen > 1 einen Job erzeugen
                for( $i = 1; $i < $cmd['INSTANCES']; $i++ )
                {
                    // einen Job erstellen
                    $newID = workFunctions::insertWorkJob( $db,
                                                           $exeid,
                                                           'COMMAND',
                                                           $mySelf['JOB_NAME'],
                                                           $wid,
                                                           WORK_JOB_POSITION_PARALLEL );

                    // BREAKPOINT setzen
                    if( $mySelf['BREAKPOINT'] )
                        workFunctions::nativeSetBreakpoint( $db, $newID, 1, $logger );

                    // das Kommando für den Job erstellen
                    $db->dbRequest( "insert into X2_WORK_COMMAND (OID, HOST, SOURCE, EXEC_PATH, COMMAND, RETRIES, RETRY_TIME)
                                     values ( ?, ?, ?, ?, ?, ?, ? )",
                                    array( array( 'i', $newID ),
                                           array( 's', $cmd['HOST'] ),
                                           array( 's', $cmd['SOURCE'] ),
                                           array( 's', $cmd['EXEC_PATH'] ),
                                           array( 's', $cmd['COMMAND'] ),
                                           array( 'i', $cmd['RETRIES'] ),
                                           array( 'i', $cmd['RETRY_TIME'] )),
                                    true );

                    // der WorkGroup beitreten
                    workFunctions::createWorkGroup( $db, $exeid, $jid, $newID );
                }
            }
        }
    }

    // den Job exportieren
    function exportJob( $db, $oid, &$mySelf, &$refMap )
    {
        $cmds = $db->dbRequest( "select HOST, SOURCE, EXEC_PATH, COMMAND, INSTANCES, RETRIES, RETRY_TIME
                                   from X2_JOB_COMMAND
                                  where OID = ?",
                                array( array( 'i', $oid )));

        foreach( $cmds->resultset as $cmd )
        {
            $mySelf['host']      = $cmd['HOST'];
            $mySelf['src']       = $cmd['SOURCE'];
            $mySelf['exePath']   = $cmd['EXEC_PATH'];
            $mySelf['cmd']       = $cmd['COMMAND'];
            $mySelf['insts']     = $cmd['INSTANCES'];
            $mySelf['retries']   = $cmd['RETRIES'];
            $mySelf['retryTime'] = $cmd['RETRY_TIME'];

            if( !isset( $refMap['hosts'][ $cmd['HOST']] ))
                $refMap['hosts'][ $cmd['HOST']] = true;
        }
    }

    // importiert das Kommando
    function importJob( $db, $tid, $oid, $job, $user )
    {
        $this->nativeCreateCommand( $db,
                                    $oid,
                                    $job['host'],
                                    $job['src'],
                                    $job['exePath'],
                                    $job['cmd'],
                                    $job['insts'],
                                    $job['retries'],
                                    $job['retryTime'],
                                    $user );
    }

    // erstellt die GV-Ausgabe in der Job-Ansicht
    function getJobLabel( $db, $template, $oid, $writeable, $pageID )
    {
        return $this->getGraphLabel( $db, $oid, 4, array( 'instances' => true,
                                                          'retries'   => true,
                                                          'table'     => 'X2_JOB_COMMAND' ));
    }

    function getWorkJobLabel( $db, $oid )
    {
        return $this->getGraphLabel( $db, $oid, 6, array( 'retries' => true,
                                                          'table'   => 'X2_WORK_COMMAND' ));
    }

    // diese Funktion reichert den Job zum Editieren durch die TPL-Datei an
    function getJob4Edit( $db, $get, &$eJob, &$smarty )
    {
        $this->nativeGetJob4Edit( $db, $get, $eJob, $smarty, array( 'findPattern' => true,
                                                                    'instances'   => true,
                                                                    'retries'     => true,
                                                                    'table'       => 'X2_JOB_COMMAND' ));
    }

    // diese Funktion reichert den WorkJob zum Editieren durch die TPL-Datei an
    function getWorkJob4Edit( $db, $get, &$eJob, &$smarty )
    {
        $this->nativeGetJob4Edit( $db, $get, $eJob, $smarty, array( 'findPattern' => true,
                                                                    'retries'     => true,
                                                                    'table'       => 'X2_WORK_COMMAND' ));
    }

    function processJobChanges( $db, $post, $user )
    {
        return $this->nativeProcessJobChanges( $db, $post, $user, array( 'instances'   => true,
                                                                         'retries'     => true,
                                                                         'table'       => 'X2_JOB_COMMAND',
                                                                         'workJob'     => false ));
    }

    function processWorkJobChanges( $db, $post, $user )
    {
        $result = array( );

        // Das Template laden
        $template = templateFunctions::getTemplate2GraphByWID( $db, $post['exeID'] );

        // das Logfile öffnen
        $logger = new logger( $db, 'modulCommand::processWorkJobChanges( ' . $user . ' )' );

        // Mutex holen
        if( !mutex::requestMutex( $db, $logger, 'TEMPLATE', $template['OID'], $user, 5, 1 ))
        {
            print "Kein Mutex erhalten!";
            return $result;
        }

        // Kill eines EorkJobs
        if( isset( $post['kill'] ))
        {
            $this->killWorkJob( $db, $post['exeID'], $post['kill'], $user, $logger );
        }
        else
        {
            // ist das Template angehalten
            if( $template['STATE'] != TEMPLATE_STATES['PAUSED'] &&
                $template['STATE'] != TEMPLATE_STATES['POWER_OFF'] )
            {
                print "Das Template ist nicht pausiert";

                mutex::releaseMutex( $db, $logger, 'TEMPLATE', $template['OID'] );

                return $result;
            }

            $result = $this->nativeProcessJobChanges( $db,
                                                      $post,
                                                      $user,
                                                      array( 'retries'     => true,
                                                             'table'       => 'X2_WORK_COMMAND',
                                                             'workJob'     => true ),
                                                      $logger );
        }

        // Mutex zurückgeben
        mutex::releaseMutex( $db, $logger, 'TEMPLATE', $template['OID'] );

        return $result;
    }

    // startet den Job zur Ausführung
    function runJob( $db, $wid, &$logger )
    {
        // das Kommando laden
        $cmds = $db->dbRequest( "select HOST,
                                        coalesce( SOURCE, 'NULL' ) SOURCE,
                                        EXEC_PATH,
                                        COMMAND
                                   from X2_WORK_COMMAND
                                  where OID = ?",
                                array( array( 'i', $wid )));

        foreach( $cmds->resultset as $cmd )
        {
            $myURL = base64_encode( ROOT_URL . '/x2ServerClient.php?oid=' . $wid );

            $myCmd = REMOTE_HOSTS[ $cmd['HOST'] ]['client']
                   . ' -url ' . $myURL
                   . ' -srv ' . REMOTE_HOSTS['local']['httpName']
                   . ' -oid ' . $wid
                   . ' -ep ' . base64_encode( $cmd['EXEC_PATH'] )
                   . ' -cmd ' . base64_encode( $cmd['COMMAND'] );

            if( $cmd['SOURCE'] != 'NULL' )
                $myCmd .= ' -src ' . base64_encode( $cmd['SOURCE'] );

            $logger->writeLog( "starte Job " . $wid
                               . "\n\nHost " . $cmd['HOST']
                               . "\nSH-Umgebung " . $cmd['SOURCE']
                               . "\nArbeitsverzeichnis " . $cmd['EXEC_PATH']
                               . "\nBefehl " . $cmd['COMMAND']
                               . "\nSSH " . $myCmd );

            $ssh = sshFactory( $cmd['HOST'], $logger );

            if( $ssh->activ )
            {
                $ssh->execute( $myCmd, $logfile );

                // den Job in den Status DEAMONIZE setzen
                workFunctions::changeWorkJobState( $db, $wid, JOB_STATES['DEAMONIZED']['id'], 'CRON', $logger );
            }
            else
                $logger->writeLog( "Fehler beim SSH-Aufbau" );

            unset( $ssh );

            $logger->writeLog( "\n" );
        }
    }

    // verarbeitet die Nachrichten der X2_DEAMON-Tabelle
    function processDeamonMessages( $db, &$logger )
    {
        $logger->writeLog( 'modulCommand::processDeamonMessages( )' );

        $msgs = $db->dbRequest( "select OID,
                                        WORKLIST_OID,
                                        DEAMON_MESSAGE,
                                        DEAMON_MODE
                                   from X2_DEAMON
                                  where DEAMON_MODE in ( ?, ?, ? )
                                  order by OID",
                                array( array( 'i', DEAMON_MODE_SET_PID ),
                                       array( 'i', DEAMON_MODE_NATIVE_LOG ),
                                       array( 'i', DEAMON_MODE_GET_LOG )));

        $logger->writeLog( 'Es werden ' . $msgs->numRows . ' verarbeitet' );

        $startGetLogs = false;

        foreach( $msgs->resultset as $msg )
        {
            $retCode = false;
            $mutex = false;

            switch( $msg['DEAMON_MODE'] )
            {
                // melden der ProzessID des Host
                case DEAMON_MODE_SET_PID: // das Template laden
                                          $mySelf = workFunctions::getWorkJob4Edit( $db, $msg['WORKLIST_OID'] );

                                          if( $mySelf != null )
                                          {
                                              // den Mutex für das Template holen
                                              if( mutex::requestMutex( $db, $logger, 'TEMPLATE', $mySelf['TEMPLATE_ID'], 'CRON', 1, 0 ))
                                              {
                                                  $mutex = true;
                                                  $retCode = true;

                                                  $this->setHostPID( $db, $msg, $logger );
                                              }
                                          }

                                          // das Template ist nicht mehr in der DB. Die Message ebenfalls löschen
                                          else
                                          {
                                              $retCode = true;
                                              $logger->writeLog( 'das Template von ' . $msg['WORKLIST_OID'] . ' wurde nicht gefunden' );
                                          }

                                          break;

                // ein Native-Log_File vom x2Client
                case DEAMON_MODE_NATIVE_LOG: $retCode = true;
                                             $this->setNativeLogFile( $db, $msg, $logger );

                                             break;

                // getLogs starten
                case DEAMON_MODE_GET_LOG: $retCode = true;
                                          $startGetLogs = true;

                                          break;
            }

            if( $retCode )
            {
                $db->dbRequest( "delete
                                   from X2_DEAMON
                                  where OID = ?",
                                array( array( 'i', $msg['OID'] )));

                $db->commit( );
            }

            // den Mutex zurückgeben
            if( $mutex )
                mutex::releaseMutex( $db, $logger, 'TEMPLATE', $mySelf['TEMPLATE_ID'] );
        }

        // getLogs starten
        if( $startGetLogs )
        {
            $gLogs = $db->dbRequest( "select SEQ_VALUE
                                        from SEQUENCE
                                       where SEQ_NAME = 'X2_MASHINE_ROOM_getLogs'" );

            jobFunctions::start( $db, $gLogs->resultset[0]['SEQ_VALUE'], 'CRON', $logger, true );
        }
    }

    function finishWorkJob( $db, $wid, $message, &$logger )
    {
        // das Logfile anlegen
        if( isset( $message['logFile'] ))
            $db->dbRequest( "insert into X2_LOGFILE (OID, TEMPLATE_EXE_ID, FILENAME)
                             select OID, TEMPLATE_EXE_ID, ?
                               from X2_WORKLIST
                              where OID = ?",
                            array( array( 's', $message['logFile'] ),
                                   array( 'i', $wid )));

        // das Logfile einsammeln, wenn ich selber nicht der Sammler bin
        $db->dbRequest( "insert into X2_DEAMON (WORKLIST_OID, DEAMON_MODE)
                         select w.OID, ?
                           from X2_WORKLIST w
                                inner join SEQUENCE s
                                   on     s.SEQ_NAME = 'X2_MASHINE_ROOM_getLogs'
                                      and s.SEQ_VALUE != w.TEMPLATE_ID
                          where w.OID = ?",
                        array( array( 'i', DEAMON_MODE_GET_LOG ),
                               array( 'i', $wid )));

        // Muss ein Retry erfolgen
        if( $message['return'] != 0 )
        {
            $retrys = $db->dbRequest( "select wc.RETRIES,
                                              wc.RETRY_TIME,
                                              wl.TEMPLATE_ID,
                                              wl.TEMPLATE_EXE_ID,
                                              wl.JOB_TYPE,
                                              wl.JOB_NAME,
                                              wl.JOB_OID,
                                              wl.OID
                                         from X2_WORK_COMMAND wc
                                              inner join X2_WORKLIST wl
                                                 on wl.OID = wc.OID
                                        where wc.RETRIES > 0
                                          and wc.OID = ?",
                                      array( array( 'i', $wid )));

            foreach( $retrys->resultset as $retry )
                workFunctions::nativeRetryWorkJob( $db,
                                                   $retry['TEMPLATE_EXE_ID'],
                                                   $retry['TEMPLATE_ID'],
                                                   $retry,
                                                   $retry['RETRY_TIME'],
                                                   'CRON',
                                                   $this,
                                                   $logger );
        }
    }
}

?>
