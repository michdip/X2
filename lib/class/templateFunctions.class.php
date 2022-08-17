<?php

require_once( ROOT_DIR . '/conf.d/x2.conf' );
require_once( ROOT_DIR . '/lib/class/mutex.class.php' );
require_once( ROOT_DIR . '/lib/class/actionlog.class.php' );
require_once( ROOT_DIR . '/lib/class/permission.class.php' );
require_once( ROOT_DIR . '/lib/class/description.class.php' );
require_once( ROOT_DIR . '/lib/class/jobFunctions.class.php' );
require_once( ROOT_DIR . '/lib/class/logger.class.php' );
require_once( ROOT_DIR . '/lib/class/modulFunctions.class.php' );

class templateFunctions
{
    public static function createParent( $db, $oid, $parent, $user )
    {
        // den direkten Vater eintragen
        if( $parent != 0 )
        {
            $db->dbRequest( "insert into X2_TEMPLATE_TREE (OID, PARENT, VGRAD)
                             values ( ?, ?, 1 )",
                            array( array( 'i', $oid ),
                                   array( 'i', $parent )),
                            true );

            // die Großväter eintragen, wenn nicht Root
            $db->dbRequest( "insert into X2_TEMPLATE_TREE (OID, PARENT, VGRAD)
                             select ?, PARENT, VGRAD + 1
                               from X2_TEMPLATE_TREE
                              where OID = ?",
                           array( array( 'i', $oid ),
                                  array( 'i', $parent )),
                           true );
        }

        // das ActionLog füllen
        actionlog::logAction( $db, 2, $oid, $user, $parent );
    }

    public static function setTemplatePermission( $db, $tid, $grpName, $permission, $user, $automatic = false )
    {
        try
        {
            // das Recht speichern
            $db->dbRequest( "insert into PERMISSION (OBJECT_TYPE, OBJECT_ID, PERM_GROUP, PERM_TYPE)
                             values ( ?, ?, ?, ? )",
                            array( array( 'i', PERM_OBJECT_TEMPLATE ),
                                   array( 'i', $tid ),
                                   array( 's', $grpName ),
                                   array( 'i', $permission )),
                            true );

            // das ActionLog füllen
            actionlog::logAction( $db, 21, $tid, $user, 'TEMPLATE / ' . $grpName . ' / ' . $permission );

            if( !$automatic )
                $db->commit( );
        }
        catch( Exception $e )
        {
            $db->rollback();

            if( $automatic )
                throw $e;

            else
                print $e->getMessage();
        }
    }

    public static function revokeTemplatePermission( $db, $tid, $grpName, $permission, $user, $automatic = false )
    {
        // das Recht löschen
        $db->dbRequest( "delete
                           from PERMISSION
                          where OBJECT_TYPE = ?
                            and OBJECT_ID = ?
                            and PERM_GROUP = ?
                            and PERM_TYPE = ?",
                        array( array( 'i', PERM_OBJECT_TEMPLATE ),
                               array( 'i', $tid ),
                               array( 's', $grpName ),
                               array( 'i', $permission )));

        // das ActionLog füllen
        Actionlog::logAction( $db, 22, $tid, $user, 'TEMPLATE / ' . $grpName . ' / ' . $permission );

        if( !$automatic )
            $db->commit( );
    }

    public static function nativeCreateNotifier( $db, $tid, $state, $recipient, $user )
    {
        // den Notifier erstellen
        $db->dbRequest( "insert into X2_TEMPLATE_NOTIFIER (TEMPLATE_ID, STATE, RECIPIENT)
                         values ( ?, ?, ? )",
                        array( array( 'i', $tid ),
                               array( 'i', $state ),
                               array( 's', $recipient )),
                        true );

        // Actionlog füllen
        Actionlog::logAction( $db, 24, $tid, $user, REVERSE_JOB_STATES[ $state ]['name'] . ' / ' . $recipient );
    }

    public static function nativeDeleteAllNotifier( $db, $tid, $user )
    {
        // Alle Notifier löschen
        $db->dbRequest( "delete
                           from X2_TEMPLATE_NOTIFIER
                          where TEMPLATE_ID = ?",
                        array( array( 'i', $tid )),
                        true );

        // das ActionLog füllen
        Actionlog::logAction( $db, 23, $tid, $user );
    }

    public static function editNotifier( $db, $tid, $post, $user )
    {
        try
        {
            // Alle Notifier löschen
            templateFunctions::nativeDeleteAllNotifier( $db, $tid, $user );

            $max = 0;

            if( isset( $post['maxNotis'] ))
                $max = $post['maxNotis'];

            for( $i = 0; $i <= $max; $i++ )
                if( isset( $post['nMail_' . $i ] ) && $post['nMail_' . $i ] != '' &&
                    isset( $post['mailOnReturnState_' . $i ] ) && $post['mailOnReturnState_' . $i ] != '' )
                    templateFunctions::nativeCreateNotifier( $db,
                                                             $tid,
                                                             $post['mailOnReturnState_' . $i ],
                                                             $post['nMail_' . $i ],
                                                             $user );

            $db->commit( );
        }
        catch( Exception $e )
        {
            $db->rollback();
            print $e->getMessage();
        }
    }

    public static function nativeCreateTemplate( $db, $objectType, $templateName, $user )
    {
        // den Namen des Templates auf 100 Zeichen begrenzen
        if( strlen( $templateName ) > 100 )
            $templateName = substr( $templateName, 0, 100 );

        // Template anlegen
        $nTemplate = $db->dbRequest( "insert into X2_TEMPLATE (OBJECT_TYPE, OBJECT_NAME)
                                      values ( ?, ? )",
                                     array( array( 's', $objectType ),
                                            array( 's', $templateName )),
                                     true );

        // Mutex erstellen
        mutex::createMutex( $db, 'TEMPLATE', $nTemplate->autoincrement );

        // das ActionLog füllen
        actionlog::logAction( $db, 1, $nTemplate->autoincrement, $user, $objectType . ' / ' . $templateName );

        return $nTemplate->autoincrement;
    }

    public static function createTemplate( $db, $objectType, $templateName, $parent, $user, $rootTreeRights )
    {
        if( $db->getAutocommitState( ))
            throw new Exception( 'Die Datenbank wurde als autocommit = true geöffnet!' );

        try
        {
            // Template anlegen
            $nTemplate = templateFunctions::nativeCreateTemplate( $db, $objectType, $templateName, $user );

            if( $parent > 0 )
            {
                // meinen Vater als Parent eintragen
                templateFunctions::createParent( $db, $nTemplate, $parent, $user );

                // die Rechte meines Vaters übernehmen
                $rights = templateFunctions::getTemplatePermission( $db, $parent );

                foreach( $rights as $grpName => $perms )
                    foreach( $perms as $perm => $pValue )
                        if( $pValue )
                            templateFunctions::setTemplatePermission( $db, $nTemplate, $grpName, $perm, $user, true );
            }
            else
                // die Root-Tree-Rechte eintragen
                foreach( $rootTreeRights as $grpName => $perms )
                    foreach( $perms as $perm )
                        templateFunctions::setTemplatePermission( $db, $nTemplate, $grpName, $perm, $user, true );

            // einen Starter anlegen
            if( $objectType == 'T' )
            {
                $db->dbRequest( "insert into X2_JOBLIST (TEMPLATE_ID, JOB_TYPE, JOB_NAME)
                                 values ( ?, 'START', 'Start' )",
                                array( array( 'i', $nTemplate )),
                                true );

                // das ActionLog füllen
                actionlog::logAction( $db, 3, $nTemplate, $user );
            }

            $db->commit( );
        }
        catch( Exception $e )
        {
            $db->rollback();
            print $e->getMessage();
        }
    }

    public static function duplicateTemplate( $db, $tid, $user )
    {
        try
        {
            // das original laden
            $orig = $db->dbRequest( "select OBJECT_NAME
                                       from X2_TEMPLATE
                                      where OBJECT_TYPE = 'T'
                                        and OID = ?",
                                    array( array( 'i', $tid )));

            if( $orig->numRows == 0 )
                return;

            // ein Template erstellen
            $mySelf = templateFunctions::nativeCreateTemplate( $db, 'T', 'Kopie ' . $orig->resultset[0]['OBJECT_NAME'], $user );

            // den Vater kopiern
            $parents = $db->dbRequest( "select PARENT
                                          from X2_TEMPLATE_TREE
                                         where VGRAD = 1
                                           and OID = ?",
                                       array( array( 'i', $tid )));

            foreach( $parents->resultset as $parent )
                templateFunctions::createParent( $db, $mySelf, $parent['PARENT'], $user );

            // die Rechte kopieren
            $rights = templateFunctions::getTemplatePermission( $db, $tid );

            foreach( $rights as $grpName => $perms )
                foreach( $perms as $perm => $pValue )
                    if( $pValue )
                        templateFunctions::setTemplatePermission( $db, $mySelf, $grpName, $perm, $user, true );

            // die Variablen kopieren
            $vars = $db->dbRequest( "select VAR_NAME, VAR_VALUE
                                       from X2_JOB_VARIABLE
                                      where TEMPLATE_ID = ?",
                                    array( array( 'i', $tid )));

            foreach( $vars->resultset as $row )
                templateFunctions::setVar( $db, $mySelf, $row['VAR_NAME'], $row['VAR_VALUE'], $user );

            // die Beschreibung kopieren
            $myDesc = description::getDescription( $db, 'TEMPLATE', $tid );

            if( $myDesc['DESCRIPTION'] != '' || $myDesc['OWNER'] != '' )
                description::setDescription( $db, 'TEMPLATE', $mySelf, $myDesc['OWNER'], $myDesc['DESCRIPTION'], $user, true );

            // die Notifier kopieren
            $notis = $db->dbRequest( "select STATE, RECIPIENT
                                        from X2_TEMPLATE_NOTIFIER
                                       where TEMPLATE_ID = ?",
                                     array( array( 'i', $tid )));

            foreach( $notis->resultset as $row )
                templateFunctions::nativeCreateNotifier( $db, $mySelf, $row['STATE'], $row['RECIPIENT'], $user );

            // alle Jobs laden
            $myJobs = $db->dbRequest( "select OID, JOB_TYPE, JOB_NAME, BREAKPOINT
                                         from X2_JOBLIST
                                        where TEMPLATE_ID = ?",
                                      array( array( 'i', $tid )));

            $migMap = array( );
            $modules = modulFunctions::getAllModulInstances( );

            foreach( $myJobs->resultset as $job )
            {
                // den Job erstellen
                $newJob = jobFunctions::nativeCreateJob( $db, $mySelf, $job['JOB_TYPE'], $job['JOB_NAME'], $user );

                // in der Map das Mapping merken
                $migMap[ $job['OID']] = array( 'newID'   => $newJob,
                                               'jobType' => $job['JOB_TYPE'] );

                // den Breakpoint setzen
                if( $job['BREAKPOINT'] == 1 )
                    jobFunctions::setBreakpoint( $db, $newJob, $user, 1, true );
            }

            // den Job duplizieren
            foreach( $migMap as $oldId => $job )
                $modules[ $job['jobType']]->duplicateJob( $db, $mySelf, $oldId, $job['newID'], $user );

            // den Graphen laden
            $graph = $db->dbRequest( "select jt.OID, jt.PARENT
                                        from X2_JOB_TREE jt
                                             inner join X2_JOBLIST jl
                                                on     jl.OID = jt.OID
                                                   and jl.TEMPLATE_ID = ?",
                                     array( array( 'i', $tid )));

            foreach( $graph->resultset as $link )
                jobFunctions::linkJob( $db, $migMap[ $link['OID']]['newID'], $migMap[ $link['PARENT']]['newID'], $user, true );

            $db->commit( );
        }
        catch( Exception $e )
        {
            $db->rollback();
            print $e->getMessage();
        }
    }

    public static function renameTemplate( $db, $oid, $newTemplateName, $user )
    {
        if( strlen( $newTemplateName ) > 100 )
            $newTemplateName = substr( $newTemplateName, 0, 100 );

        $db->dbRequest( "update X2_TEMPLATE
                            set OBJECT_NAME = ?
                          where OID = ?",
                        array( array( 's', $newTemplateName ),
                               array( 'i', $oid )),
                        true );

        // ActionLog
        actionlog::logAction( $db, 4, $oid, $user, $newTemplateName );

        $db->commit();
    }

    public static function moveTemplate( $db, $tid, $parent, $user )
    {
        try
        {
            // die älten Väter löschen
            $db->dbRequest( "delete
                               from X2_TEMPLATE_TREE
                              where OID = ?",
                            array( array( 'i', $tid )),
                            true );

            // den neuen Vater eintragen
            templateFunctions::createParent( $db, $tid, $parent, $user );

            $db->commit( );
        }
        catch( Exception $e )
        {
            $db->rollback();
            print $e->getMessage();
        }
    }

    public static function deleteTemplate( $db, $tid, $user, $force = false )
    {
        // ist das Template editierbar
        if( !templateFunctions::isEditable( $db, $tid ))
        {
            print "Das Template ist nicht editierbar";
            return;
        }

        // im Fore-Modus erst alle Jobs von unten nach oben löschen
        if( $force )
        {
            do
            {
                $myJobs = $db->dbRequest( "select jl.OID
                                             from X2_JOBLIST jl
                                                  left join X2_JOB_TREE jt
                                                    on jl.OID = jt.PARENT
                                            where jl.TEMPLATE_ID = ?
                                              and jt.OID is null
                                              and jl.JOB_TYPE != 'START'",
                                          array( array( 'i', $tid )));

                foreach( $myJobs->resultset as $job )
                    jobFunctions::removeJob( $db, $job['OID'], $user, true );

            } while( $myJobs->numRows != 0 );
        }

        // darf ich gelöscht werden
        if( !templateFunctions::isDeletable( $db, $tid ))
        {
            print "Das Template kann nicht gelöscht werden";
            return;
        }

        // logger erstellen
        $logger = new logger( $db, 'templateFunctions::deleteTemplate( tid: ' . $tid . ' user: ' . $user . ' )' );

        // den Mutex holen
        if( !mutex::requestMutex( $db, $logger, 'TEMPLATE', $tid, $user, 5, 1 ))
        {
            print "Kein Mutex erhalten";
            return;
        }

        try
        {
            // Alle EXE-IDs des Templates archivieren und löschen
            $maxExe = $db->dbRequest( "select max( TEMPLATE_EXE_ID ) MAX_EXE
                                         from X2_WORKLIST
                                        where TEMPLATE_ID = ?",
                                      array( array( 'i', $tid )));

            if( $maxExe->numRows > 0 )
            {
                $modules = modulFunctions::getAllModulInstances( );
                workFunctions::cleanUpTemplateExe( $db, $tid, $maxExe->resultset[0]['MAX_EXE'], $modules );
            }

            // den Starter löschen
            $start = $db->dbRequest( "select OID
                                        from X2_JOBLIST
                                       where TEMPLATE_ID = ?
                                         and JOB_TYPE = 'START'",
                                     array( array( 'i', $tid )));

            // das Modul instanziieren
            foreach( $start->resultset as $job )
                jobFunctions::removeJob( $db, $job['OID'], $user, true );

            // Notifier löschen
            templateFunctions::nativeDeleteAllNotifier( $db, $tid, $user );

            // Beschreibung löschen
            description::setDescription( $db, 'TEMPLATE', $tid, '', '', $user, true );

            // Variablen löschen
            templateFunctions::nativeDeleteAllVariables( $db, $tid, $user );

            // Permissions löschen
            $rights = templateFunctions::getTemplatePermission( $db, $tid );

            foreach( $rights as $grpName => $perms )
                foreach( $perms as $perm => $pValue )
                    if( $pValue )
                        templateFunctions::revokeTemplatePermission( $db, $tid, $grpName, $perm, $user, true );

            // den Mutex löschen
            mutex::removeMutex( $db, 'TEMPLATE', $tid );

            // den Tree löschen
            $db->dbRequest( "delete
                               from X2_TEMPLATE_TREE
                              where OID = ?",
                            array( array( 'i', $tid )),
                            true );

            // das Template löschen
            $db->dbRequest( "delete
                               from X2_TEMPLATE
                              where OID = ?",
                            array( array( 'i', $tid )),
                            true );

            // ActionLog
            actionlog::logAction( $db, 25, $tid, $user );

            $db->commit( );
        }
        catch( Exception $e )
        {
            $db->rollback();
            print $e->getMessage();
        }
    }

    public static function setVar( $db, $tid, $varName, $varValue, $user )
    {
        // die Variable erstellen
        $db->dbRequest( "insert into X2_JOB_VARIABLE (TEMPLATE_ID, VAR_NAME, VAR_VALUE)
                         values ( ?, ?, ? )",
                        array( array( 'i', $tid ),
                               array( 's', $varName ),
                               array( 's', $varValue )),
                        true );

        // ActionLog
        actionlog::logAction( $db, 19, $tid, $user, $varName . ' / ' . $varValue );
    }

    public static function nativeDeleteAllVariables( $db, $tid, $user )
    {
        // alle Variablen löschen
        $db->dbRequest( "delete
                           from X2_JOB_VARIABLE
                          where TEMPLATE_ID = ?",
                        array( array( 'i', $tid )),
                        true );

        // ActionLog
        actionlog::logAction( $db, 18, $tid, $user );
    }

    public static function editVars( $db, $post, $user )
    {
        try
        {
            // Alle Variablen löschen
            templateFunctions::nativeDeleteAllVariables( $db, $post['templateID'], $user );

            $max = 0;

            if( isset( $post['maxVars'] ))
                $max = $post['maxVars'];

            for( $i = 0; $i <= $max; $i++ )
                if( isset( $post['varName_' . $i ] ) && $post['varName_' . $i ] != '' &&
                    isset( $post['varValue_' . $i ] ) && $post['varValue_' . $i ] != '' )
                    templateFunctions::setVar( $db, $post['templateID'], $post['varName_' . $i ], $post['varValue_' . $i ], $user );

            $db->commit();
        }
        catch( Exception $e )
        {
            $db->rollback();
            print $e->getMessage();
        }
    }

    public static function removeVar( $db, $oid, $user )
    {
        try
        {
            // die Variable für's Log laden
            $mySelf = $db->dbRequest( "select TEMPLATE_ID, VAR_NAME, VAR_VALUE
                                         from X2_JOB_VARIABLE
                                        where OID = ?",
                                      array( array( 'i', $oid )));

            foreach( $mySelf->resultset as $row )
            {
                // die Variable löschen
                $db->dbRequest( "delete
                                   from X2_JOB_VARIABLE
                                  where OID = ?",
                                array( array( 'i', $oid )),
                                true );

                // ActionLog
                actionlog::logAction( $db,
                                      20,
                                      $row['TEMPLATE_ID'],
                                      $user,
                                      $oid . ' / ' . $row['VAR_NAME'] . ' / ' . $row['VAR_VALUE'] );
            }

            $db->commit();
        }
        catch( Exception $e )
        {
            $db->rollback();
            print $e->getMessage();
        }
    }

    public static function powerUp( $db, $oid, $user )
    {
        // Darf ich eingeschaltet werden ?
        if( !templateFunctions::powerMode( $db, $oid ) == 'powerOn' )
            throw new Exception( 'Das Template kann nicht gestartet werden.' );

        /* Wird ein Template wieder eingeschaltet, so kommt es darauf an, in welchem Zustand die Jobs sind
         * es laufen noch Jobs in CREATED, RUNNING => TEMPLATE_STATES.PAUSED
         * es ist keine Startzeit vorhanden => TEMPLATE_STATES.RUNNING
         * es ist eine Startzeit vorhanden => TEMPLATE_STATES.POWER_ON
         */
        $runstate = $db->dbRequest( "select 1
                                       from X2_WORKLIST w
                                            inner join X2_JOB_STATE j
                                               on     w.STATE = j.JOB_STATE
                                                  and j.STATE_ORDER in ( ?, ? )
                                      where w.TEMPLATE_ID = ?",
                                    array( array( 'i', JOB_STATES['CREATED']['id'] ),
                                           array( 'i', JOB_STATES['RUNNING']['id'] ),
                                           array( 'i', $oid )));

        $hasStart = $db->dbRequest( "select 1
                                       from X2_JOB_START
                                      where TEMPLATE_ID = ?
                                        and START_MODE != 'manual'",
                                    array( array( 'i', $oid )));

        if( $runstate->numRows != 0 )
            $newState = TEMPLATE_STATES['PAUSED'];

        else if( $hasStart->numRows == 0 )
            $newState = TEMPLATE_STATES['RUNNING'];

        else
        {
            $newState = TEMPLATE_STATES['POWER_ON'];

            // Startzeit berechnen
            jobFunctions::calculateNextStartTime( $db, $oid );
        }

        $db->dbRequest( "update X2_TEMPLATE
                            set STATE = ?
                          where OBJECT_TYPE = 'T'
                            and OID = ?",
                        array( array( 'i', $newState ),
                               array( 'i', $oid )));

        // ActionLog
        actionlog::logAction( $db, 27, $oid, $user, REVERSE_TEMPLATE_STATES[ $newState ] );

        $db->commit();
    }

    public static function powerOff( $db, $oid, $user )
    {
        // Darf ich ausgeschaltet werden ?
        if( !templateFunctions::powerMode( $db, $oid ) == 'powerOff' )
            throw new Exception( 'Das Template kann nicht gestoppt werden.' );

        // State setzen und nächste Startzeit entfernen
        $db->dbRequest( "update X2_TEMPLATE
                            set STATE = ?,
                                NEXT_START_TIME = null
                          where OBJECT_TYPE = 'T'
                            and OID = ?",
                        array( array( 'i', TEMPLATE_STATES['POWER_OFF'] ),
                               array( 'i', $oid )));

        // ActionLog
        actionlog::logAction( $db, 27, $oid, $user, 'POWER_OFF' );

        $db->commit();
    }

    public static function shedule( $db, $oid, $user )
    {
        if( !templateFunctions::isShedulable( $db, $oid ))
            throw new Exception( 'Das Template kann nicht in den Modus RUNNING wechseln' );

        $db->dbRequest( "update X2_TEMPLATE
                            set STATE = ?
                          where OID = ?",
                        array( array( 'i', TEMPLATE_STATES['RUNNING'] ),
                               array( 'i', $oid )));

        // ActionLog
        actionlog::logAction( $db, 27, $oid, $user, 'RUNNING' );

        $db->commit();
    }

    public static function pause( $db, $oid, $user, $automatic = false )
    {
        // Darf die Ausführung des Templates pausiert werden
        if( !templateFunctions::pausable( $db, $oid ))
            throw new Exception( 'Die Ausführung des Templates kann nicht angehalten werden' );

        $db->dbRequest( "update X2_TEMPLATE
                            set STATE = ?
                          where OID = ?",
                        array( array( 'i', TEMPLATE_STATES['PAUSED'] ),
                               array( 'i', $oid )),
                        $automatic );

        // ActionLog
        actionlog::logAction( $db, 27, $oid, $user, 'PAUSED' );

        if( !$automatic )
            $db->commit();
    }

    public static function resume( $db, $oid, $user )
    {
        // Darf die Ausführung des Templates fortgeführt werden
        if( !templateFunctions::resumable( $db, $oid ))
            throw new Exception( 'Die Ausführung des Templates kann nicht fortgeführt werden' );

        $db->dbRequest( "update X2_TEMPLATE
                            set STATE = ?
                          where OID = ?",
                        array( array( 'i', TEMPLATE_STATES['RUNNING'] ),
                               array( 'i', $oid )));

        // ActionLog
        actionlog::logAction( $db, 27, $oid, $user, 'RUNNING' );

        $db->commit();
    }

    /******************
     * Template-Werte *
     ******************/

    public static function getVariables( $db, $oid )
    {
        $vars = $db->dbRequest( "with parent as ( select PARENT, VGRAD
                                                    from X2_TEMPLATE_TREE
                                                   where OID = ?
                                                  union
                                                  select ?, 0 )
                                 select v.OID VAR_OID,
                                        v.VAR_NAME,
                                        v.VAR_VALUE,
                                        t.OBJECT_NAME,
                                        t.OID PARENT_OID
                                   from X2_JOB_VARIABLE v
                                        inner join parent p
                                           on p.PARENT = v.TEMPLATE_ID
                                        inner join X2_TEMPLATE t
                                           on p.PARENT = t.OID
                                  order by v.VAR_NAME, p.VGRAD asc",
                                array( array( 'i', $oid ),
                                       array( 'i', $oid )));

        return $vars->resultset;
    }

    public static function getScheduledStartTime( $db, $oid )
    {
        $result = '';

        $res = $db->dbRequest( "select START_MODE,
                                       SEKUNDEN,
                                       DAY_OF_WEEK,
                                       DAY_OF_MONTH,
                                       case when START_HOUR < 10 then concat( '0', START_HOUR )
                                            else START_HOUR
                                            end START_HOUR,
                                       case when START_MINUTES < 10 then concat ( '0', START_MINUTES )
                                            else START_MINUTES
                                            end START_MINUTES
                                  from X2_JOB_START
                                 where TEMPLATE_ID = ?",
                               array( array( 'i', $oid )));

        if( $res->numRows == 0 )
            return '[ ]';

        else
            foreach( $res->resultset as $sTime )
                if( $sTime['START_MODE'] == 'manual' )
                    $result .= '[manuell]';

                else if( $sTime['SEKUNDEN'] != '' )
                    $result .= '[alle ' . $sTime['SEKUNDEN'] . ' sec]';

                else if( $sTime['DAY_OF_WEEK'] != '' )
                    $result .= '[' . WORKDAYS[ $sTime['DAY_OF_WEEK'] ]['short'] . ' um ' . $sTime['START_HOUR'] . ':' . $sTime['START_MINUTES'] . ']';

                else if( $sTime['DAY_OF_MONTH'] != '' )
                    $result .= '[am ' . $sTime['DAY_OF_MONTH'] . '. um ' . $sTime['START_HOUR'] . ':' . $sTime['START_MINUTES'] . ']';

        return $result;
    }

    public static function getTemplatePermission( $db, $tid, $perm = null )
    {
        $result = array( );

        // alle existierenden Gruppen holen
        if( $perm != null )
        {
            $allGroups = $perm->getAllGroups( );

            foreach( $allGroups as $grpName )
                $result[ $grpName ] = array( PERM_READ  => false,
                                             PERM_WRITE => false,
                                             PERM_EXE   => false,
                                             PERM_ADMIN => false );
        }

        // meine Gruppenrechte laden
        $myRights = $db->dbRequest( "select PERM_GROUP, PERM_TYPE
                                       from PERMISSION
                                      where OBJECT_TYPE = ?
                                        and OBJECT_ID = ?",
                                    array( array( 'i', PERM_OBJECT_TEMPLATE ),
                                           array( 'i', $tid )));

        foreach( $myRights->resultset as $row )
        {
            if( !isset( $result[ $row['PERM_GROUP']] ))
                $result[ $row['PERM_GROUP']] = array( PERM_READ  => false,
                                                      PERM_WRITE => false,
                                                      PERM_EXE   => false,
                                                      PERM_ADMIN => false );

            $result[ $row['PERM_GROUP']][ $row['PERM_TYPE']] = true;
        }

        return $result;
    }

    public static function getTemplateNotifier( $db, $tid )
    {
        $notis = $db->dbRequest( "select STATE, RECIPIENT
                                    from X2_TEMPLATE_NOTIFIER
                                   where TEMPLATE_ID = ?
                                   order by STATE, RECIPIENT",
                                 array( array( 'i', $tid )));

        return $notis->resultset;
    }

    public static function getMutexTree( $db, $tid )
    {
        $tree = $db->dbRequest( "select 1
                                   from X2_TEMPLATE_TREE tt
                                        inner join MUTEX m
                                           on     m.OBJECT = 'TEMPLATE'
                                              and m.STATE != 'FREE'
                                              and m.OBJECT_ID = tt.OID
                                  where tt.PARENT = ?",
                                array( array( 'i', $tid )));

        return ( $tree->numRows == 0 );
    }

    /*******************
     * Template-Status *
     *******************/

    // das Template ist editierbar, wenn es sich im Status POWER_OFF befindet
    public static function isEditable( $db, $oid )
    {
        $state = $db->dbRequest( "select 1
                                    from X2_TEMPLATE
                                   where OID = ?
                                     and STATE = ?",
                                 array( array( 'i', $oid ),
                                        array( 'i', TEMPLATE_STATES['POWER_OFF'] )));

        if( $state->numRows > 0 )
            return true;

        return false;
    }

    // hat das Template Variablen
    public static function hasVariables( $db, $oid )
    {
        $res = $db->dbRequest( "select 1
                                  from X2_JOB_VARIABLE
                                 where TEMPLATE_ID = ?",
                               array( array( 'i', $oid )));

        if( $res->numRows != 0 )
            return true;

        return false;
    }

    public static function isLinked( $db, $oid )
    {
        $linked = $db->dbRequest( "select 1
                                     from X2_JOB_REFERENCE
                                    where OBJECT_TYPE = 'TEMPLATE'
                                      and OBJECT_ID = ?",
                                  array( array( 'i', $oid )));

        if( $linked->numRows != 0 )
            return true;

        return false;
    }

    public static function isShedulable( $db, $oid )
    {
        $res = $db->dbRequest( "select 1
                                  from X2_TEMPLATE
                                 where OID = ?
                                   and STATE = ?",
                               array( array( 'i', $oid ),
                                      array( 'i', TEMPLATE_STATES['POWER_ON'] )));

        if( $res->numRows > 0 )
            return true;

        return false;
    }

    /* ein Template kann gestartet werden, wenn
     * manuell: es im Status POWER_ON / RUNNING ist und eine manuelle Startzeit besitzt
     * cron: es im Status RUNNING ist
     * alle Jobs im STATUS OK, OK_BY_USER sind oder keine vorhanden
     */
    public static function startable( $db, $oid, $automatic = false )
    {
        $jStates = $db->dbRequest( "select 1
                                      from X2_WORKLIST wl
                                           inner join X2_JOB_STATE js
                                              on     wl.STATE = js.JOB_STATE
                                                 and js.STATE_ORDER != ?
                                     where wl.TEMPLATE_ID = ?",
                                   array( array( 'i', JOB_STATES['OK']['id'] ),
                                          array( 'i', $oid )));

        $state = $db->dbRequest( "select t.STATE,
                                         s.SEQ_VALUE as MASHINE_ROOM
                                    from X2_TEMPLATE t
                                         join SEQUENCE s
                                           on s.SEQ_NAME = 'X2_MASHINE_ROOM_getLogs'
                                   where t.OID = ?",
                                 array( array( 'i', $oid )));

        $manualStartTime = $db->dbRequest( "select 1
                                              from X2_JOB_START
                                             where START_MODE = 'manual'
                                               and (VALIDFROM is null or VALIDFROM < now())
                                               and (VALIDUNTIL is null or VALIDUNTIL > now())
                                               and TEMPLATE_ID = ?",
                                           array( array( 'i', $oid )));

        // automatisch 
        if( $automatic &&

              // Template ist RUNNING
            ( $state->resultset[0]['STATE'] == TEMPLATE_STATES['RUNNING'] ||

                // Ausnahme, getLog braucht kein Scheduling, so lange powerOn ist
              ( $oid == $state->resultset[0]['MASHINE_ROOM'] &&
                TEMPLATE_STATES['POWER_ON'] )
            ) &&

            // keine Jobs in der Ausführung / Error
            $jStates->numRows == 0 
          )
            return true;

        // manuell
        if( !$automatic &&

            // Manuellen Starter
            $manualStartTime->numRows > 0 &&

              // Template PowerOn || Running
            ( $state->resultset[0]['STATE'] == TEMPLATE_STATES['POWER_ON'] ||
              $state->resultset[0]['STATE'] == TEMPLATE_STATES['RUNNING']
            ) &&

            // keine Jobs in der Ausführung / Error
            $jStates->numRows == 0 
          )
            return true;

        return false;
    }

    /* Ein Template kann pausiert werden, wenn
     * es im Status POWER_ON oder RUNNING ist
     * Jobs sich in der Ausführung befinden < OK
     */
    public static function pausable( $db, $oid )
    {
        $state = $db->dbRequest( "select STATE
                                    from X2_TEMPLATE
                                   where OID = ?",
                                 array( array( 'i', $oid )));

        $jStates = $db->dbRequest( "select 1
                                      from X2_WORKLIST wl
                                           inner join X2_JOB_STATE j
                                              on     j.JOB_STATE = wl.STATE
                                                 and j.STATE_ORDER in ( ?, ? )
                                     where TEMPLATE_ID = ?",
                                   array( array( 'i', JOB_STATES['CREATED']['id'] ),
                                          array( 'i', JOB_STATES['RUNNING']['id'] ),
                                          array( 'i', $oid )));

        if(( $state->resultset[0]['STATE'] == TEMPLATE_STATES['POWER_ON'] ||
             $state->resultset[0]['STATE'] == TEMPLATE_STATES['RUNNING'] ) &&
           $jStates->numRows > 0 )
            return true;

        return false;
    }

    // Wenn ein Template im STATUS PAUSED ist, dann kann die Pause aufgehoben werden => RUNNING
    public static function resumable( $db, $oid )
    {
        $state = $db->dbRequest( "select 1
                                    from X2_TEMPLATE
                                   where OID = ?
                                     and STATE = ?",
                                 array( array( 'i', $oid ),
                                        array( 'i', TEMPLATE_STATES['PAUSED'] )));

        if( $state->numRows > 0 )
            return true;

        return false;
    }

    /* Ein Template kann gelöscht werden, wenn
     *  keine Kinder / Jobs mehr unter ihm sind
     * Es im Status POWER_OFF ist
     * Es nicht von anderen verlinkt wird
     */
    public static function isDeletable( $db, $oid )
    {
        $state = $db->dbRequest( "select 1
                                    from X2_TEMPLATE
                                   where OID = ?
                                     and STATE = ?",
                                 array( array( 'i', $oid ),
                                        array( 'i', TEMPLATE_STATES['POWER_OFF'] )));

        if( $state->numRows == 0 )
            return false;

        $childs = $db->dbRequest( "select 1
                                     from X2_TEMPLATE_TREE
                                    where PARENT = ?
                                   union
                                   select 1
                                     from X2_JOBLIST
                                    where TEMPLATE_ID = ?
                                      and JOB_TYPE != 'START'",
                                  array( array( 'i', $oid ),
                                         array( 'i', $oid )));

        if( $childs->numRows != 0 )
            return false;

        if( templateFunctions::isLinked( $db, $oid ))
            return false;

        return true;
    }

    /* Die Ausführung eines Templates kann abgebrochen werden, wenn
     * das Template nicht im Status POWER_OFF ist
     * sich in der Ausführung JOBs befinden, die noch nicht gestatet wurden
// TODO keine JOBS in der Order Running ????
     */
    public static function ejectable( $db, $oid )
    {
        $state = $db->dbRequest( "select 1
                                    from X2_TEMPLATE
                                   where OID = ?
                                     and STATE = ?",
                                 array( array( 'i', $oid ),
                                        array( 'i', TEMPLATE_STATES['POWER_OFF'] )));

        $res = $db->dbRequest( "select 1
                                  from X2_WORKLIST w
                                       inner join X2_JOB_STATE j
                                          on w.STATE = j.JOB_STATE
                                 where w.TEMPLATE_ID = ?
                                   and j.STATE_ORDER = ?",
                               array( array( 'i', $oid ),
                                      array( 'i', JOB_STATES['CREATED']['id'] )));

        if( $res->numRows > 0 && $state->numRows == 0 )
            return true;

        return false;
    }

    /* Der Power-Knopf hat 3 Zustände
     *
     * none: der Knopf ist nicht sichtbar
     *
     * powerOn wenn
     * er Kinder in der JOBLIST besitzt die nicht vom Typ START sind
     * er Im Status TEMPLATE_STATES['POWER_OFF']
     *
     * powerOff wenn
     * er Kinder in der Joblist besitzt die nicht vom Typ START sind
     * er nicht im Status TEMPLATE_STATES['POWER_ON']
     */
    public static function powerMode( $db, $oid )
    {
        // Kinder in der JOBLIST
        $childs = $db->dbRequest( "select 1
                                     from X2_JOBLIST
                                    where TEMPLATE_ID = ?
                                      and JOB_TYPE != 'START'",
                                  array( array( 'i', $oid )));

        // meinen eigenen Status
        $state = $db->dbRequest( "select STATE
                                    from X2_TEMPLATE
                                   where OID = ?",
                                 array( array( 'i', $oid )));

        if( $childs->numRows == 0 )
            return 'none';

        else if( $state->resultset[0]['STATE'] == TEMPLATE_STATES['POWER_OFF'] )
            return 'powerOn';

        else
            return 'powerOff';
    }

    public static function hasNotifier( $db, $oid )
    {
        $res = $db->dbRequest( "select 1
                                  from X2_TEMPLATE_NOTIFIER
                                 where TEMPLATE_ID = ?",
                               array( array( 'i', $oid )));

        if( $res->numRows != 0 )
            return true;

        return false;
    }

    public static function hasWorklist( $db, $oid )
    {
        $wrk = $db->dbRequest( "select max(TEMPLATE_EXE_ID) TEMPLATE_EXE_ID
                                  from X2_WORKLIST
                                 where TEMPLATE_ID = ?",
                               array( array( 'i', $oid )));

        if( $wrk->numRows == 0 )
            return 0;

        else
            return $wrk->resultset[0]['TEMPLATE_EXE_ID'];
    }

    public static function getMyRootPath( $db, $oid )
    {
        // den Pfad zum Root abfragen
        $rPath = $db->dbRequest( "select OID, OBJECT_NAME
                                    from (select t.OID,
                                                 t.OBJECT_NAME,
                                                 tt.VGRAD
                                            from X2_TEMPLATE t
                                                 inner join X2_TEMPLATE_TREE tt
                                                    on     t.OID = tt.PARENT
                                                       and tt.OID = ?
                                           union
                                          select OID, OBJECT_NAME, 0
                                            from X2_TEMPLATE
                                           where OID = ?
                                             and OBJECT_TYPE = 'G' ) a
                                   order by VGRAD desc",
                                 array( array( 'i', $oid ),
                                        array( 'i', $oid )));

        return $rPath->resultset;
    }

    public static function getTemplate2Graph( $db, $oid )
    {
        $tpl = $db->dbRequest( "select t.OID,
                                       t.OBJECT_NAME,
                                       t.STATE,
                                       coalesce( tt.PARENT, 0 ) PARENT
                                  from X2_TEMPLATE t
                                       left join X2_TEMPLATE_TREE tt
                                         on t.OID = tt.OID
                                            and tt.VGRAD = 1
                                 where t.OID = ?",
                               array( array( 'i', $oid )));

        return $tpl->resultset[0];
    }

    public static function getTemplate2GraphByWID( $db, $exeID )
    {
        $tid = $db->dbRequest( "select TEMPLATE_ID
                                  from X2_WORKLIST
                                 where TEMPLATE_EXE_ID = ?
                                 group by TEMPLATE_ID",
                               array( array( 'i', $exeID )));

        if( $tid->numRows != 1 )
            return -1;

        return templateFunctions::getTemplate2Graph( $db, $tid->resultset[0]['TEMPLATE_ID'] );
    }

    public static function getTemplates2Display( $db, $parent, $permission, $sPattern, &$templates )
    {
        // alle Templates zur Anzeige laden
        $params = array( );

        $query = "select t.OID,
                         t.OBJECT_TYPE,
                         t.OBJECT_NAME,
                         t.STATE,
                         coalesce( date_format( t.LAST_RUN, '%d.%m.%Y %H:%i:%s' ), '' ) LAST_RUN,
                         coalesce( concat( '( ', sec_to_time( t.LAST_DURATION ), ' )' ), '&nbsp;' ) LAST_DURATION_STR,
                         coalesce( concat( 'noch ', sec_to_time( time_to_sec( timediff( timestampadd( second, t.LAST_DURATION, t.ACTUAL_START_TIME ), now())))), '&nbsp;') RUN_DURATION,
                         t.RUN_STATE,
                         t.EXE_STATE,
                         case when t.NEXT_START_TIME < now() then 'jetzt'
                              else coalesce( date_format( t.NEXT_START_TIME, '%d.%m.%Y %H:%i:%s' ), '' )
                              end NEXT_START_TIME
                    from X2_TEMPLATE t ";

        // Nach Templates suchen
        if( $sPattern != null )
        {
            $query .= "where OID in ( select TEMPLATE_ID
                                        from X2_JOBLIST jl
                                             inner join X2_JOB_COMMAND jc
                                                on     jc.OID = jl.OID
                                                   and jc.COMMAND like ?
                                      union
                                      select TEMPLATE_ID
                                        from X2_JOBLIST jl
                                             inner join X2_DESCRIPTION d
                                                on     d.OBJECT_TYPE = 'JOB'
                                                   and d.OBJECT_ID = jl.OID
                                                   and (    d.DESCRIPTION like ?
                                                         or d.OWNER like ? )
                                      union
                                      select OBJECT_ID
                                        from X2_DESCRIPTION
                                       where OBJECT_TYPE = 'TEMPLATE'
                                         and (    DESCRIPTION like ?
                                               or OWNER like ? )
                                    )
                          or OBJECT_NAME like ?";

            for( $i = 0; $i < 6; $i++ )
                array_push( $params, array( 's', $sPattern ));
        }

        // Anzeige auf Root-Ebene
        else if( $parent == 0 )
            $query .= "where not exists ( select 1
                                            from X2_TEMPLATE_TREE
                                           where OID = t.OID )";

        // unter einem Vater anzeigen
        else
        {
            $query .= "inner join X2_TEMPLATE_TREE tt
                          on     t.OID = tt.OID
                             and tt.PARENT = ?
                             and tt.VGRAD = 1";

            array_push( $params, array( 'i', $parent ));
        }

        $tpls = $db->dbRequest( $query
                                . " order by t.OBJECT_TYPE, t.OBJECT_NAME",
                                $params );

        foreach( $tpls->resultset as $template )
        {
            // die Rechte auf den Vater des Templates abfragen
            $template['gPerms'] = array( 'READ'  => $permission->canIDo( $db, PERM_OBJECT_TEMPLATE, $parent, PERM_READ ),
                                         'WRITE' => $permission->canIDo( $db, PERM_OBJECT_TEMPLATE, $parent, PERM_WRITE ),
                                         'EXE'   => $permission->canIDo( $db, PERM_OBJECT_TEMPLATE, $parent, PERM_EXE ),
                                         'ADMIN' => $permission->canIDo( $db, PERM_OBJECT_TEMPLATE, $parent, PERM_ADMIN ));

            // die Rechte auf das Template abfragen
            $template['uPerms'] = array( 'READ'  => $permission->canIDo( $db, PERM_OBJECT_TEMPLATE, $template['OID'], PERM_READ ),
                                         'WRITE' => $permission->canIDo( $db, PERM_OBJECT_TEMPLATE, $template['OID'], PERM_WRITE ),
                                         'EXE'   => $permission->canIDo( $db, PERM_OBJECT_TEMPLATE, $template['OID'], PERM_EXE ),
                                         'ADMIN' => $permission->canIDo( $db, PERM_OBJECT_TEMPLATE, $template['OID'], PERM_ADMIN ));

            // kann das Template gelöscht werden
            $template['showDelete'] = templateFunctions::isDeletable( $db, $template['OID'] );

            // ist das Template verliked
            $template['isLinked'] = templateFunctions::isLinked( $db, $template['OID'] );

            // der Kommentar des Templates
            $template['desc'] = description::getDescription( $db, 'TEMPLATE', $template['OID'] );

            // hat das Template Variablen
            $template['hasVariables'] = templateFunctions::hasVariables( $db, $template['OID'] );

            // hat das Template notifier
            $template['hasNotifier'] = templateFunctions::hasNotifier( $db, $template['OID'] );

            // den Mutex-Status ermitteln
            $template['mutexState'] = mutex::getMutexState( $db, 'TEMPLATE', $template['OID'] );

            // den Mutex-Status aller kinder
            $template['mutexTreeState'] = templateFunctions::getMutexTree( $db, $template['OID'] );

            // Eigenschaften eines Templates
            if( $template['OBJECT_TYPE'] == 'T' )
            {
                // ist das Template editierbar
                $template['showEdit'] = templateFunctions::isEditable( $db, $template['OID'] );

                // den Zustand des Power-Knopfes
                $template['showPower'] = templateFunctions::powerMode( $db, $template['OID'] );

                // anzeigen des Scheduler
                $template['showSheduler'] = templateFunctions::isShedulable( $db, $template['OID'] );

                // ausgeführte Jobs vorhanden
                $template['showWork'] = templateFunctions::hasWorklist( $db, $template['OID'] );

                // kann die Ausführung abgebrochen werden
                $template['showEject'] = templateFunctions::ejectable( $db, $template['OID'] );

                // Kann das Template manuell gestartet werden
                if( templateFunctions::startable( $db, $template['OID'] ))
                    $template['showPlay'] = 'play';

                else if( templateFunctions::pausable( $db, $template['OID'] ))
                    $template['showPlay'] = 'pause';

                else if( templateFunctions::resumable( $db, $template['OID'] ))
                    $template['showPlay'] = 'resume';

                else
                    $template['showPlay'] = 'none';

                // die Startzeit des Templates laden
                $template['startzeit'] = templateFunctions::getScheduledStartTime( $db, $template['OID'] );
            }
            else
                $template['startzeit'] = '';

            // die länge vom Displaynamen ermitteln
            $template['displayLength'] = strlen( $template['OBJECT_NAME'] . ' ' . $template['startzeit'] );

            array_push( $templates, $template );
        }
    }
}

?>
