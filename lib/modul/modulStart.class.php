<?php

require_once( ROOT_DIR . '/conf.d/x2.conf' );

class modulStart implements modulInterface
{
    public $opts = array( 'isEditable' => false,
                          'isKillable' => false,
                          'hasDelete'  => false,
                          'hasRetry'   => false 
                        );

    private function setStartTime( $db,
                                   $template,
                                   $jobID,
                                   $sTime,
                                   $type,
                                   $sekunden,
                                   $weekDay,
                                   $dayOfMonth,
                                   $hour,
                                   $minute,
                                   $vFrom,
                                   $vUntil,
                                   $user )
    {
        $vFromStr = "str_to_date( ?, '%d.%m.%Y' )";
        $vUntilStr = "str_to_date( ?, '%d.%m.%Y' )";

        if( $vFrom == '' || $vFrom == null )
        {
            $vFromStr = "?";
            $vFrom = null;
        }

        if( $vUntil == '' || $vUntil == null )
        {
            $vUntilStr = "?";
            $vUntil = null;
        }

        $sTimeP = null;

        if( $sTime > 0 )
            $sTimeP = $sTime;

        switch( $type )
        {
            case 'none'    : if( $sTime > 0 )
                             {
                                 $db->dbRequest( "delete
                                                    from X2_JOB_START
                                                   where OID = ?",
                                                 array( array( 'i', $sTime )));

                                 // ActionLog
                                 actionlog::logAction4Job( $db, 7, $jobID, $user, ' (' . $sTimeP . ')' );
                             }

                             break;

            case 'manual'  : $db->dbRequest( "insert into X2_JOB_START (OID, JOBLIST_ID, TEMPLATE_ID, START_MODE, VALIDFROM, VALIDUNTIL)
                                              values ( ?, ?, ?, 'manual', " . $vFromStr . ", " . $vUntilStr . " )
                                              on duplicate key update START_MODE = 'manual',
                                                                      VALIDFROM = " . $vFromStr . ",
                                                                      VALIDUNTIL = " . $vUntilStr . ",
                                                                      SEKUNDEN = null,
                                                                      DAY_OF_WEEK = null,
                                                                      DAY_OF_MONTH = null,
                                                                      START_HOUR = null,
                                                                      START_MINUTES = null",
                                             array( array( 'i', $sTimeP ),
                                                    array( 'i', $jobID ),
                                                    array( 'i', $template ),
                                                    array( 's', $vFrom ),
                                                    array( 's', $vUntil ),
                                                    array( 's', $vFrom ),
                                                    array( 's', $vUntil )));

                             // ActionLog
                             actionlog::logAction4Job( $db,
                                                       8,
                                                       $jobID,
                                                       $user,
                                                       ' (' . $sTimeP . ') manuell von ' . $vFrom . ' bis ' . $vUntil );

                             break;

            case 'int'     : $db->dbRequest( "insert into X2_JOB_START (OID,
                                                                        JOBLIST_ID,
                                                                        TEMPLATE_ID,
                                                                        START_MODE,
                                                                        SEKUNDEN,
                                                                        VALIDFROM,
                                                                        VALIDUNTIL)
                                              values ( ?, ?, ?, 'int', ?, " . $vFromStr . ", " . $vUntilStr . " )
                                              on duplicate key update START_MODE = 'int',
                                                                      SEKUNDEN = ?,
                                                                      VALIDFROM = " . $vFromStr . ",
                                                                      VALIDUNTIL = " . $vUntilStr . ",
                                                                      DAY_OF_WEEK = null,
                                                                      DAY_OF_MONTH = null,
                                                                      START_HOUR = null,
                                                                      START_MINUTES = null",
                                             array( array( 'i', $sTimeP ),
                                                    array( 'i', $jobID ),
                                                    array( 'i', $template ),
                                                    array( 'i', $sekunden ),
                                                    array( 's', $vFrom ),
                                                    array( 's', $vUntil ),
                                                    array( 'i', $sekunden ),
                                                    array( 's', $vFrom ),
                                                    array( 's', $vUntil )));

                             // ActionLog
                             actionlog::logAction4Job( $db,
                                                       8,
                                                       $jobID,
                                                       $user,
                                                       ' (' . $sTimeP . ') alle ' . $sekunden . ' Sekunden von ' . $vFrom . ' bis ' . $vUntil );

                             break;

            case 'weekday' : $db->dbRequest( "insert into X2_JOB_START (OID,
                                                                        JOBLIST_ID,
                                                                        TEMPLATE_ID,
                                                                        START_MODE,
                                                                        DAY_OF_WEEK,
                                                                        START_HOUR,
                                                                        START_MINUTES,
                                                                        VALIDFROM,
                                                                        VALIDUNTIL)
                                              values ( ?, ?, ?, 'weekday', ?, ?, ?, " . $vFromStr . ", " . $vUntilStr . " )
                                              on duplicate key update START_MODE = 'weekday',
                                                                      SEKUNDEN = null,
                                                                      DAY_OF_WEEK = ?,
                                                                      START_HOUR = ?,
                                                                      START_MINUTES = ?,
                                                                      VALIDFROM = " . $vFromStr . ",
                                                                      VALIDUNTIL = " . $vUntilStr . ",
                                                                      DAY_OF_MONTH = null",
                                             array( array( 'i', $sTimeP ),
                                                    array( 'i', $jobID ),
                                                    array( 'i', $template ),
                                                    array( 'i', $weekDay ),
                                                    array( 'i', $hour ),
                                                    array( 'i', $minute ),
                                                    array( 's', $vFrom ),
                                                    array( 's', $vUntil ),
                                                    array( 'i', $weekDay ),
                                                    array( 'i', $hour ),
                                                    array( 'i', $minute ),
                                                    array( 's', $vFrom ),
                                                    array( 's', $vUntil )));

                             // ActionLog
                             actionlog::logAction4Job( $db,
                                                       8,
                                                       $jobID,
                                                       $user,
                                                       ' (' . $sTimeP . ') ' . WORKDAYS[ $weekDay ]['long'] . ' um ' . $hour . ':' . $minute . ' von ' . $vFrom . ' bis ' . $vUntil );

                             break;

            case 'day'     : $db->dbRequest( "insert into X2_JOB_START (OID,
                                                                        JOBLIST_ID,
                                                                        TEMPLATE_ID,
                                                                        START_MODE,
                                                                        DAY_OF_MONTH,
                                                                        START_HOUR,
                                                                        START_MINUTES,
                                                                        VALIDFROM,
                                                                        VALIDUNTIL)
                                              values ( ?, ?, ?, 'day', ?, ?, ?, " . $vFromStr . ", " . $vUntilStr . " )
                                              on duplicate key update START_MODE = 'day',
                                                                      SEKUNDEN = null,
                                                                      DAY_OF_WEEK = null,
                                                                      DAY_OF_MONTH = ?,
                                                                      START_HOUR = ?,
                                                                      START_MINUTES = ?,
                                                                      VALIDFROM = " . $vFromStr . ",
                                                                      VALIDUNTIL = " . $vUntilStr,
                                             array( array( 'i', $sTimeP ),
                                                    array( 'i', $jobID ),
                                                    array( 'i', $template ),
                                                    array( 'i', $dayOfMonth ),
                                                    array( 'i', $hour ),
                                                    array( 'i', $minute ),
                                                    array( 's', $vFrom ),
                                                    array( 's', $vUntil ),
                                                    array( 'i', $dayOfMonth ),
                                                    array( 'i', $hour ),
                                                    array( 'i', $minute ),
                                                    array( 's', $vFrom ),
                                                    array( 's', $vUntil )));

                             // ActionLog
                             actionlog::logAction4Job( $db,
                                                       8,
                                                       $jobID,
                                                       $user,
                                                       ' (' . $sTimeP . ') am ' . $dayOfMonth . ' Tag um ' . $hour . ':' . $minute . ' von ' . $vFrom . ' bis ' . $vUntil );

                             break;
        }
    }

    // dupliziert einen Job
    function duplicateJob( $db, $tid, $origID, $newID, $user )
    {
        // die Startzeiten des original laden
        $sTimes = $db->dbRequest( "select START_MODE,
                                          SEKUNDEN,
                                          DAY_OF_WEEK,
                                          DAY_OF_MONTH,
                                          START_HOUR,
                                          START_MINUTES,
                                          date_format( VALIDFROM, '%d.%m.%Y' ) VALIDFROM,
                                          date_format( VALIDUNTIL, '%d.%m.%Y' ) VALIDUNTIL
                                     from X2_JOB_START
                                    where JOBLIST_ID = ?",
                                  array( array( 'i', $origID )));

        foreach( $sTimes->resultset as $sTime )
            $this->setStartTime( $db,
                                 $tid,
                                 $newID,
                                 0,
                                 $sTime['START_MODE'],
                                 $sTime['SEKUNDEN'],
                                 $sTime['DAY_OF_WEEK'],
                                 $sTime['DAY_OF_MONTH'],
                                 $sTime['START_HOUR'],
                                 $sTime['START_MINUTES'],
                                 $sTime['VALIDFROM'],
                                 $sTime['VALIDUNTIL'],
                                 $user );
    }

    function duplicateWorkJob( $db, $tid, $origId, $newId, $user )
    {
    }

    // lösche den Job
    function deleteJob( $db, $oid )
    {
        // alle Startzeiten löschen
        $db->dbRequest( "delete
                           from X2_JOB_START
                          where JOBLIST_ID = ?",
                        array( array( 'i', $oid )));
    }

    function deleteWorkJob( $db, $oid )
    {
    }

    function archiveWorkJob( $db, $wid )
    {
    }

    function createWorkCopy( $db, $jid, $wid, $exeid, &$logger )
    {
    }

    // den Job exportieren
    function exportJob( $db, $oid, &$mySelf, &$refMap )
    {
        $mySelf['sTime'] = array( );

        $sTimes = $db->dbRequest( "select START_MODE,
                                          SEKUNDEN,
                                          DAY_OF_WEEK,
                                          DAY_OF_MONTH,
                                          START_HOUR,
                                          START_MINUTES,
                                          date_format( VALIDFROM, '%d.%m.%Y' ) VALIDFROM,
                                          date_format( VALIDUNTIL, '%d.%m.%Y' ) VALIDUNTIL
                                     from X2_JOB_START
                                    where JOBLIST_ID = ?
                                    order by OID",
                                  array( array( 'i', $oid )));

        foreach( $sTimes->resultset as $sTime )
            array_push( $mySelf['sTime'], array( 'type'    => $sTime['START_MODE'],
                                                 'sec'     => $sTime['SEKUNDEN'],
                                                 'dow'     => $sTime['DAY_OF_WEEK'],
                                                 'dom'     => $sTime['DAY_OF_MONTH'],
                                                 'sh'      => $sTime['START_HOUR'],
                                                 'sm'      => $sTime['START_MINUTES'],
                                                 'vFrom'   => $sTime['VALIDFROM'],
                                                 'vUntil'  => $sTime['VALIDUNTIL'] ));
    }

    // den Job importieren
    function importJob( $db, $tid, $oid, $job, $user )
    {
        foreach( $job['sTime'] as $sTime )
            $this->setStartTime( $db,
                                 $tid,
                                 $oid,
                                 -1,
                                 $sTime['type'],
                                 $sTime['sec'],
                                 $sTime['dow'],
                                 $sTime['dom'],
                                 $sTime['sh'],
                                 $sTime['sm'],
                                 $sTime['vFrom'],
                                 $sTime['vUntil'],
                                 $user );
    }

    // erstellt die GV-Ausgabe in der Job-Ansicht
    function getJobLabel( $db, $template, $oid, $writeable, $pageID )
    {
        $label = '';

        // die Startzeiten laden
        $startTimes = $db->dbRequest( "select OID,
                                              START_MODE,
                                              SEKUNDEN,
                                              DAY_OF_WEEK,
                                              DAY_OF_MONTH,
                                              case when START_HOUR < 10 then concat( '0', START_HOUR )
                                                   else START_HOUR
                                               end START_HOUR,
                                              case when START_MINUTES < 10 then concat( '0', START_MINUTES )
                                                   else START_MINUTES
                                               end START_MINUTES,
                                              case when VALIDFROM is not null
                                                   then concat( ' vom ', date_format( VALIDFROM, '%d.%m.%Y' ))
                                                   else ''
                                               end VALIDFROM,
                                              case when VALIDUNTIL is not null
                                                   then concat( ' bis ', date_format( VALIDUNTIL, '%d.%m.%Y' ))
                                                   else ''
                                               end VALIDUNTIL
                                         from X2_JOB_START
                                        where JOBLIST_ID = ?
                                        order by OID",
                                      array( array( 'i', $oid )));

        foreach( $startTimes->resultset as $sTime )
        {
            $label .= '<TR>'
                    . '<TD ALIGN="left" COLSPAN="2">Startzeit:</TD>';

           if( $sTime['START_MODE'] == 'manual' )
                $label .= '<TD ALIGN="left">manuell' . $sTime['VALIDFROM'] . $sTime['VALIDUNTIL'] . '</TD>';

            else if( $sTime['SEKUNDEN'] != '' )
                $label .= '<TD ALIGN="left">alle ' . $sTime['SEKUNDEN'] . ' Sekunden' . $sTime['VALIDFROM'] . $sTime['VALIDUNTIL'] . '</TD>';

            else if( $sTime['DAY_OF_WEEK'] != '' )
                $label .= '<TD ALIGN="left">' . WORKDAYS[ $sTime['DAY_OF_WEEK']]['long'] . ' um ' . $sTime['START_HOUR'] . ':' . $sTime['START_MINUTES']
                            . $sTime['VALIDFROM'] . $sTime['VALIDUNTIL']
                        . '</TD>';

            else if( $sTime['DAY_OF_MONTH'] != '' )
                $label .= '<TD ALIGN="left">am ' . $sTime['DAY_OF_MONTH'] . '. Tag im Monat um ' . $sTime['START_HOUR'] . ':' . $sTime['START_MINUTES']
                            . $sTime['VALIDFROM'] . $sTime['VALIDUNTIL']
                        . '</TD>';

            // mit bearbeitungsrechten bearbeiten
            if( $writeable )
            {
                $label .= '<TD HREF="buildTemplate.php?pageID=' . $pageID
                                                   . '&amp;amp;tid=' . $template
                                                   . '&amp;amp;edit=' . $oid
                                                   . '&amp;amp;sTime=' . $sTime['OID'] . '" '
                            . 'TARGET="_parent" TITLE="Startzeit bearbeiten" '
                            . 'HEIGHT="25" WIDTH="20" VALIGN="BOTTOM">'
                            . '<FONT FACE="Glyphicons Halflings">&#x270f;</FONT>'
                        . '</TD>'
                        . '<TD HREF="buildTemplate.php?pageID=' . $pageID
                                                   . '&amp;amp;tid=' . $template
                                                   . '&amp;amp;jobID=' . $oid
                                                   . '&amp;amp;removeSTime=' . $sTime['OID'] . '" '
                            . 'TARGET="_parent" TITLE="Startzeit löschen" '
                            . 'HEIGHT="25" WIDTH="20" VALIGN="BOTTOM">'
                            . '<FONT FACE="Glyphicons Halflings">&#xe020;</FONT>'
                        . '</TD>';
            }
            else
                $label .= '<TD>&nbsp;</TD>'
                        . '<TD>&nbsp;</TD>';

            $label .= '</TR>';
        }

        // eine neue Startzeit hinzufügen
        if( $writeable )
            $label .= '<TR>'
                    . '<TD ALIGN="left" COLSPAN="2">Startzeit:</TD>'
                    . '<TD COLSPAN="3" '
                        . 'ALIGN="center" '
                        . 'HREF="buildTemplate.php?pageID=' . $pageID
                                               . '&amp;amp;tid=' . $template
                                               . '&amp;amp;edit=' . $oid
                                               . '&amp;amp;sTime=-1" '
                        . 'TARGET="_parent" TITLE="Startzeit hinzufügen" '
                        . 'HEIGHT="25" WIDTH="20" VALIGN="BOTTOM">'
                        .'<FONT FACE="Glyphicons Halflings">&#x002b;</FONT>'
                    . '</TD>'
                    . '</TR>';

        return $label;
    }

    function getWorkJobLabel( $db, $oid )
    {
        return '';
    }

    // diese Funktion reichert den Job zum Editieren durch die TPL-Datei an
    function getJob4Edit( $db, $get, &$eJob, &$smarty )
    {
        $smarty->assign( 'dows', WORKDAYS );

        if( $get['sTime'] == -1 )
        {
            $eJob['STID'] = -1;
            $eJob['SEKUNDEN'] = '';
            $eJob['DAY_OF_WEEK'] = '';
            $eJob['DAY_OF_MONTH'] = '';
            $eJob['START_HOUR'] = '';
            $eJob['START_MINUTES'] = '';
            $eJob['START_MODE'] = '';
            $eJob['VALIDFROM'] = '';
            $eJob['VALIDUNTIL'] = '';
        }
        else
        {
            $mySelf = $db->dbRequest( "select OID STID,
                                              SEKUNDEN,
                                              coalesce( DAY_OF_WEEK, 1) DAY_OF_WEEK,
                                              DAY_OF_MONTH,
                                              case when START_HOUR < 10 then concat( '0', START_HOUR )
                                                   else START_HOUR
                                               end START_HOUR,
                                              case when START_MINUTES < 10 then concat( '0', START_MINUTES )
                                                   else START_MINUTES
                                               end START_MINUTES,
                                              START_MODE,
                                              coalesce( date_format( VALIDFROM, '%d.%m.%Y' ), '' ) VALIDFROM,
                                              coalesce( date_format( VALIDUNTIL, '%d.%m.%Y' ), '' ) VALIDUNTIL
                                         from X2_JOB_START
                                        where OID = ?",
                                      array( array( 'i', $get['sTime'] )));

            foreach( $mySelf->resultset as $row )
                foreach( $row as $key => $value )
                    $eJob[ $key ] = $value;
        }
    }

    function getWorkJob4Edit( $db, $get, &$eJob, &$smarty )
    {
    }

    // die Änderungen von der GV-Ansicht kommen hier an
    function processJobChanges( $db, $post, $user )
    {
        if( isset( $post['editStartTime'] ))
            $this->setStartTime( $db,
                                 $post['templateID'],
                                 $post['jobID'],
                                 $post['editStartTime'],
                                 $post['startTime'],
                                 $post['timeDelta'],
                                 $post['dow'],
                                 $post['dom'],
                                 $post['startH'],
                                 $post['startM'],
                                 $post['vFrom'],
                                 $post['vUntil'],
                                 $user );

        else if( isset( $post['removeSTime'] ))
            $this->setStartTime( $db,
                                 $post['tid'],
                                 $post['jobID'],
                                 $post['removeSTime'],
                                 'none',
                                 null,
                                 null,
                                 null,
                                 null,
                                 null,
                                 null,
                                 null,
                                 $user );

        $db->commit( );

        return array( );
    }

    function processWorkJobChanges( $db, $post, $user )
    {
        return array( );
    }

    function runJob( $db, $wid, &$logger )
    {
    }

    function processDeamonMessages( $db, &$logger )
    {
    }

    function finishWorkJob( $db, $wid, $message, &$logger )
    {
    }
}

?>
