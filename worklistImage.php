<?php

define( 'RUN_TIMES_NORMAL', 0 );
define( 'RUN_TIMES_NORMAL_P', 1 );
define( 'RUN_TIMES_NORMAL_PP', 2 );
define( 'RUN_TIMES_SINGLE', 3 );
define( 'RUN_TIMES_GRUPPE', 4 );

require_once "Image/GraphViz.php";
require_once 'conf.d/base.conf';
require_once( ROOT_DIR . '/conf.d/x2.conf' );
require_once( ROOT_DIR . '/lib/class/dbLink.class.php' );
require_once( ROOT_DIR . '/lib/class/pageID.class.php' );
require_once( ROOT_DIR . '/lib/class/permission.class.php' );
require_once( ROOT_DIR . '/lib/class/description.class.php' );
require_once( ROOT_DIR . '/lib/class/modulFunctions.class.php' );
require_once( ROOT_DIR . '/lib/class/jobState.class.php' );

function getViewMode( $jobGroups, $jid, $wid )
{
    // Der Job ist in keiner Gruppe
    if( !isset( $jobGroups[ $jid ] ))
        return 'normal';

    // der Job gehört nicht zur Gruppe
    if( !isset( $jobGroups[ $jid ][ $wid ] ))
        return 'normal';

    // die Gruppe wurde ausgeblendet
    if( isset( $jobGroups[ $jid ]['ungroup'] ))
        return 'ungrouped';

    // der Job ist der Representant der Gruppe
    if( array_key_first( $jobGroups[ $jid ] ) == $wid )
        return 'gruppe';

    return 'none';
}

function mapGroupMember( $jobGroups, $jid, $wid )
{
    if( isset( $jobGroups[ $jid ] ) &&           // JOB_OID in einer Gruppe
        isset( $jobGroups[ $jid ][ $wid ] ) &&   // OID in einer Gruppe
        !isset( $jobGroups[ $jid ]['ungroup'] )  // Gruppe wurde nicht ausgeblendet
      )
        return array_key_first( $jobGroups[ $jid ] );

    return $wid;
}

function getEdges( $edges, $jobGroups )
{
    $result = array( );

    foreach( $edges as $link )
    {
        $eStart = mapGroupMember( $jobGroups, $link['P_JOB_OID'], $link['PARENT'] );
        $eTarget = mapGroupMember( $jobGroups, $link['JOB_OID'], $link['OID'] );

        $link = ( $eStart == $link['PARENT'] && $eTarget == $link['OID'] );

        if( $eStart != $eTarget )
        {
            if( !isset( $result[ $eTarget ] ))
                $result[ $eTarget ] = array( $eStart => $link );

            else if( !isset( $result[ $eTarget ][ $eStart ] ))
                $result[ $eTarget ][ $eStart ] = $link;

            else
                $result[ $eTarget ][ $eStart ] = ( $result[ $eTarget ][ $eStart ] && $link );
        }
    }

    return $result;
}

function getEdgeLabel( $link, $src, $target, $tState, $pageID, $exeID )
{
    $label = array( );

    if( $link && $tState['pause'] && $tState['write'] )
    {
        $label['edgetarget']  = '_parent';
        $label['edgetooltip'] = 'Link auflösen';
        $label['edgeURL'] = 'worklist.php?pageID=' . $pageID
                                     . '&amp;wid=' . $exeID .
                                   '&amp;remLink=' . $src .
                                    '&amp;linkTo=' . $target;
    }

    return $label;
}

function getButtonLabel( $linkTarget, $pageID, $exeID, $actionNme, $actionVal, $title, $icon, $tdOps = '' )
{
    $label = '<TD   HREF="' . $linkTarget . '.php?pageID=' . $pageID
                                      . '&amp;amp;wid=' . $exeID
                                      . '&amp;amp;' . $actionNme . '=' . $actionVal . '" '
               . 'TARGET="_parent" '
               .  'TITLE="' . $title . '" '
               . 'HEIGHT="25" '
               .  'WIDTH="20" '
               . 'VALIGN="BOTTOM" '
               . $tdOps
              . '>'
           . '<FONT FACE="Glyphicons Halflings">' . $icon . '</FONT>'
           . '</TD>';

    return $label;
}

function getRunTimes( $db, $jid, $oid, $get, $mode, $tState )
{
    $query = '';
    $rowspan = 0;

    // Das Basis-Select zur Ausgabe einer Laufzeit
    $sClause = "coalesce( date_format( w.PROCESS_START, '%d.%m.%Y %H:%i:%s' ), '&nbsp;' ) PROCESS_START,
                coalesce( date_format( w.PROCESS_STOP, '%d.%m.%Y %H:%i:%s' ), '&nbsp;' ) PROCESS_STOP,
                coalesce( sec_to_time( time_to_sec( timediff( coalesce( w.PROCESS_STOP, now() ), w.PROCESS_START ))), '&nbsp;' ) RUNTIME,
                w.STATE,
                w.TEMPLATE_ID,
                w.TEMPLATE_EXE_ID";

    /* die Laufzeiten gibt es in verschiedenen Modies
     * 
     * normal  : die aktuelle Laufzeit der JOB_OID ( + )
     * normal+ : alle Laufzeiten der WORKLIST einer JOB_OID ( -, ++ )
     * normal++: alle Laufzeiten der WORKLIST + ARCHIV ( -, -- )
     * single  : die Laufzeit der OID
     * gruppe  : die Laufzeiten aller Members
     */
    switch( $mode )
    {
        case RUN_TIMES_NORMAL: $query = "select w.OID,
                                                w.OID ORDERCOL,
                                                'A' ORDERTABLE,
                                                case when l.DATA is null
                                                     then 0
                                                     else 1
                                                 end LOGFILE,
                                            " . $sClause . "
                                           from X2_WORKLIST w
                                                left join X2_LOGFILE l
                                                  on w.OID = l.OID
                                          where w.JOB_OID = ?
                                          order by ORDERCOL desc
                                          limit 1";

                               $qParams = array( array( 'i', $jid ));
                               $rowspan = 1;

                               break;

        case RUN_TIMES_NORMAL_P: $query = "select w.OID,
                                                w.OID ORDERCOL,
                                                'A' ORDERTABLE,
                                                case when l.DATA is null
                                                     then 0
                                                     else 1
                                                 end LOGFILE,
                                            " . $sClause . "
                                           from X2_WORKLIST w
                                                left join X2_LOGFILE l
                                                  on w.OID = l.OID
                                          where w.JOB_OID = ?
                                          order by ORDERCOL desc";

                               $qParams = array( array( 'i', $jid ));
                               $rowspan = 2;

                               break;

        case RUN_TIMES_NORMAL_PP: $query = "select w.OID,
                                                   w.OID ORDERCOL,
                                                   'A' ORDERTABLE,
                                                   case when l.DATA is null
                                                        then 0
                                                        else 1
                                                    end LOGFILE,
                                               " . $sClause . "
                                              from X2_WORKLIST w
                                                   left join X2_LOGFILE l
                                                     on w.OID = l.OID
                                             where w.JOB_OID = ?
                                            union
                                            select 0 OID,
                                                   w.TEMPLATE_EXE_ID ORDERCOL,
                                                   'B' ORDERTABLE,
                                                   case when LOGDATA is null
                                                        then 0
                                                        else 1
                                                    end LOGFILE,
                                               " . $sClause . "
                                              from X2_ARCHIV w
                                             where w.JOB_OID = ?
                                             order by ORDERTABLE, ORDERCOL desc
                                             limit 100";

                                  $qParams = array( array( 'i', $jid ),
                                                    array( 'i', $jid ));

                                  $rowspan = 2;

                                  break;

        case RUN_TIMES_SINGLE: $query = "select w.OID,
                                                w.OID ORDERCOL,
                                                'A' ORDERTABLE,
                                                case when l.DATA is null
                                                     then 0
                                                     else 1
                                                 end LOGFILE,
                                            " . $sClause . "
                                           from X2_WORKLIST w
                                                left join X2_LOGFILE l
                                                  on w.OID = l.OID
                                          where w.OID = ?";

                               $qParams = array( array( 'i', $oid ));
                               $rowspan = 0;

                               break;

        case RUN_TIMES_GRUPPE: $query = "select w.OID,
                                                w.OID ORDERCOL,
                                                'A' ORDERTABLE,
                                                case when l.DATA is null
                                                     then 0
                                                     else 1
                                                 end LOGFILE,
                                            " . $sClause . "
                                           from X2_WORKLIST w
                                                inner join X2_WORK_GROUP wg
                                                   on wg.MEMBER_OID = w.OID
                                                      and wg.GROUP_OID = ?
                                                left join X2_LOGFILE l
                                                  on w.OID = l.OID
                                          where w.TEMPLATE_EXE_ID = ?";

                               $qParams = array( array( 'i', $jid ),
                                                 array( 'i', $get['wid'] ));

                               $rowspan = 1;

                               break;
    }


    $rTimes = $db->dbRequest( $query, $qParams );

    $label = '';

    $rowspan += $rTimes->numRows;

    foreach( $rTimes->resultset as $i => $rTime )
    {
        $label .= '<TR>';

        if( $i == 0 )
            $label .= '<TD ROWSPAN="' . $rowspan . '" ALIGN="left">Laufzeiten</TD>';

        $label .= '<TD>' . $rTime['PROCESS_START'] . '</TD>'
                . '<TD>' . $rTime['PROCESS_STOP'] . '</TD>'
                . '<TD>' . $rTime['RUNTIME'] . '</TD>'
                . '<TD BGCOLOR="' . REVERSE_JOB_STATES[ $rTime['STATE']]['color'] . '"'
                     . ' WIDTH="20" ALIGN="left">'
                    . '<FONT COLOR="white" FACE="Glyphicons Halflings">'
                         . REVERSE_JOB_STATES[ $rTime['STATE']]['icon']
                    . '</FONT>'
                . '</TD>'
                . '<TD HREF="showHistory.php?tid=' . $rTime['TEMPLATE_ID'] . '&amp;amp;eid=' . $rTime['TEMPLATE_EXE_ID'] . '" TARGET="_blank" '
                   . 'TITLE="Ausführungshistorie anzeigen" '
                   . 'WIDTH="20" HEIGHT="25" VALIGN="BOTTOM">'
                    . '<FONT FACE="Glyphicons Halflings">&#xe009;</FONT>'
                . '</TD>';

        // LogFile
        if( $rTime['LOGFILE'] )
        {
            if( $rTime['ORDERTABLE'] == 'A' )
                $label .= '<TD   HREF="showLogfile.php?oid=' . $rTime['OID'] . '" '
                            . 'TARGET="_blank" '
                            .  'TITLE="Logfile anzeigen" '
                            . 'HEIGHT="25" '
                            .  'WIDTH="20" '
                            . 'VALIGN="BOTTOM" '
                           . '>'
                        . '<FONT FACE="Glyphicons Halflings">&#xe139;</FONT>'
                        . '</TD>';

            else
                $label .= '<TD   HREF="showLogfile.php?jid=' . $jid . '&amp;amp;exe=' . $rTime['TEMPLATE_EXE_ID'] . '" '
                            . 'TARGET="_blank" '
                            .  'TITLE="Logfile anzeigen" '
                            . 'HEIGHT="25" '
                            .  'WIDTH="20" '
                            . 'VALIGN="BOTTOM" '
                           . '>'
                        . '<FONT FACE="Glyphicons Halflings">&#xe139;</FONT>'
                        . '</TD>';
        }

        // OK By Admin
        else if( $tState['admin'] && REVERSE_JOB_STATES[ $rTime['STATE']]['setOkbyAdmin'] )
            $label .= getButtonLabel( 'worklist',
                                      $get['pageID'],
                                      $get['wid'],   
                                      'setAOk',
                                      $rTime['OID'],
                                      'auf OK setzen',
                                      '&#xe125;',    
                                      'BGCOLOR="red"' );
        else
            $label .= '<TD>&nbsp;</TD>';

        $label .= '</TR>';
    }

    // die Laufzeiten
    switch( $mode )
    {
        // +
        case RUN_TIMES_NORMAL: $label .= '<TR>'
                                       . getButtonLabel( 'worklist',
                                                         $get['pageID'],
                                                         $get['wid'],
                                                         'showRT',
                                                         $jid,
                                                         'Historie anzeigen',
                                                         '&#x002b;',
                                                         'COLSPAN="6"' )
                                       . '</TR>';

                               break;

        // - ++
        case RUN_TIMES_NORMAL_P: $label .= '<TR>'
                                         . getButtonLabel( 'worklist',
                                                           $get['pageID'],
                                                           $get['wid'],
                                                           'noRT',
                                                           $jid,
                                                           'Historie ausblenden',
                                                           '&#x2212;',
                                                           'COLSPAN="6"' )
                                         . '</TR>'
                                         . '<TR>'
                                         . getButtonLabel( 'worklist',
                                                           $get['pageID'],
                                                           $get['wid'],
                                                           'showERT',
                                                           $jid,
                                                           'Archiv anzeigen',
                                                           '&#x002b;&#x002b;',
                                                           'COLSPAN="6"' )
                                         . '</TR>';

                                 break;

        // - --
        case RUN_TIMES_NORMAL_PP: $label .= '<TR>'
                                          . getButtonLabel( 'worklist',
                                                           $get['pageID'],
                                                           $get['wid'],
                                                           'noRT',
                                                           $jid,
                                                           'Historie ausblenden',
                                                           '&#x2212;',
                                                           'COLSPAN="6"' )
                                         . '</TR>'
                                         . '<TR>'
                                         . getButtonLabel( 'worklist',
                                                           $get['pageID'],
                                                           $get['wid'],
                                                           'showRT',
                                                           $jid,
                                                           'Archiv anzeigen',
                                                           '&#x2212;&#x2212;',
                                                           'COLSPAN="6"' )
                                         . '</TR>';

                                  break;

        // +
        case RUN_TIMES_GRUPPE: $label .= '<TR>'
                                       . getButtonLabel( 'worklist',
                                                         $get['pageID'],
                                                         $get['wid'],
                                                         'showRT',
                                                         $jid,
                                                         'Historie anzeigen',
                                                         '&#x002b;',
                                                         'COLSPAN="6"' )
                                       . '</TR>';

                               break;
    }

    return $label;
}

function getJobLabel( $db, $job, $get, $modules, $tState )
{
    $label = '<TABLE>'
           . '<TR>';

    $label .= '<TD COLSPAN="7" BGCOLOR="' . REVERSE_JOB_STATES[ $job['STATE']]['color'] . '">'
            . '<FONT COLOR="white" '
                   . 'FACE="Glyphicons Halflings">' . REVERSE_JOB_STATES[ $job['STATE']]['icon']
            . '</FONT>'
            . '<FONT COLOR="white">&nbsp;&nbsp;' . $job['JOB_NAME'] . '</FONT>'
            . '</TD>'
            . '</TR>';

    // das Fleisch vom Modul holen
    $label .= $modules[ $job['JOB_TYPE']]->getWorkJobLabel( $db, $job['OID'] );

    // die Laufzeiten ausgeben
    if( $job['JOB_OID'] < 0 )
        $label .= getRunTimes( $db, $job['JOB_OID'], $job['OID'], $get, RUN_TIMES_SINGLE, $tState );

    else if( isset( $get['showRT'] ) && $get['showRT'] == $job['JOB_OID'] )
        $label .= getRunTimes( $db, $job['JOB_OID'], $job['OID'], $get, RUN_TIMES_NORMAL_P, $tState );

    else if( isset( $get['showERT'] ) && $get['showERT'] == $job['JOB_OID'] )
        $label .= getRunTimes( $db, $job['JOB_OID'], $job['OID'], $get, RUN_TIMES_NORMAL_PP, $tState );

    else
        $label .= getRunTimes( $db, $job['JOB_OID'], $job['OID'], $get, RUN_TIMES_NORMAL, $tState );

    // die Beschreibung
    $desc = description::getDescription( $db, 'JOB', $job['JOB_OID'] );

    $label .= '<TR>'
            . '<TD COLSPAN="7">' . $desc['DESCRIPTION'] . '&nbsp;</TD>'
            . '</TR>';

    // Aktionsleiste
    $label .= '<TR>';

    // OK_BY_USER
    if( $tState['exe'] &&
        REVERSE_JOB_STATES[ $job['STATE']]['setOkbyUser']
      )
        $label .= getButtonLabel( 'worklist', $get['pageID'], $get['wid'], 'setOk', $job['OID'], 'auf OK setzen', '&#xe125;' );

    // Kill
    else if( $modules[ $job['JOB_TYPE']]->opts['isKillable'] &&
             $tState['exe'] &&
             REVERSE_JOB_STATES[ $job['STATE']]['killable'] )
        $label .= getButtonLabel( 'worklist',
                                  $get['pageID'],
                                  $get['wid'],
                                  'kill=' . $job['OID'] . '&amp;amp;getModul',
                                  $job['OID'],
                                  'Job abbrechen',
                                  '&#xe090;' );

    else
        $label .= '<TD>&nbsp;</TD>';

    /* Veränderungen am Job sind nur möglich, wenn
     * * das Template pausiert / ausgeschaltet ist
     * * das Schreibrecht besitzt
     * * die Ansicht keine Gruppe ist
     * * dieser noch nicht beendet / fehlerhaft ist
     */
    if( $tState['pause'] && $tState['write'] && 
        $job['STATE_ORDER'] != JOB_STATES['OK']['id'] &&
        $job['STATE_ORDER'] != JOB_STATES['ERROR']['id'] )
    {
        // verknüpfen
        $label .= getButtonLabel( 'linkJob',
                                  $get['pageID'],
                                  $get['wid'],
                                  'linkJob',
                                  $job['OID'],
                                  'weiteren Folgejob verlinken',
                                  '&#xe097;' );

        // Command bearbeiten
        if( $modules[ $job['JOB_TYPE']]->opts['isEditable'] &&
            REVERSE_JOB_STATES[ $job['STATE']]['isEditable'] 
          )
            $label .= getButtonLabel( 'worklist', $get['pageID'], $get['wid'], 'edit', $job['OID'], 'Job bearbeiten', '&#x270f;' );

        else
            $label .= '<TD>&nbsp;</TD>';

        // neuen Job einfügen
        $label .= getButtonLabel( 'worklist', $get['pageID'], $get['wid'], 'newJob', $job['OID'], 'neuer Folgejob', '&#x002b;' );
        
        // Job löschen
        if( $modules[ $job['JOB_TYPE']]->opts['hasDelete'] && 
            $job['STATE_ORDER'] == JOB_STATES['CREATED']['id'] &&
            workFunctions::isDeletable( $db, $job['OID'] )
          )
            $label .= getButtonLabel( 'worklist', $get['pageID'], $get['wid'], 'remJob', $job['OID'], 'Job löschen', '&#xe020;' );

        else
            $label .= '<TD>&nbsp;</TD>';
    }

    // Retry
    else if( $tState['exe'] &&
             $modules[ $job['JOB_TYPE']]->opts['hasRetry'] &&
             REVERSE_JOB_STATES[ $job['STATE']]['restartable'] )
        $label .= getButtonLabel( 'worklist',
                                  $get['pageID'],
                                  $get['wid'],
                                  'restart',
                                  $job['OID'],
                                  'Job erneut ausführen',
                                  '&#xe030;',
                                  'COLSPAN="4"' );

    else
        $label .= '<TD COLSPAN="4">&nbsp;</TD>';

    // Breakpoint
    if( $tState['exe'] )
    {
        // setzen
        if( $job['BREAKPOINT'] )
            $label .= getButtonLabel( 'worklist',
                                      $get['pageID'],
                                      $get['wid'],
                                      'unsetBp',
                                      $job['OID'],
                                      'Breakpoint entfernen',
                                      '&#x26fa;',
                                      'BGCOLOR="red" COLSPAN="2"' );

        else
            $label .= getButtonLabel( 'worklist',
                                      $get['pageID'],
                                      $get['wid'],
                                      'setBp',
                                      $job['OID'],
                                      'einen Breakpoint setzen',
                                      '&#x26fa;',
                                      'COLSPAN="2"' );
    }
    else
        $label .= '<TD COLSPAN="2">&nbsp;</TD>';

    $label .= '</TR>'
            . '</TABLE>';

    return $label;
}

function getGroupLabel( $db, $job, $get, $modules, $tState, $ungrouped = false )
{
    $label = '<TABLE>'
           . '<TR>'
           . '<TD COLSPAN="7">Gruppe ' . $job['JOB_OID'] . '</TD>'
           . '</TR>';

    // das Fleisch vom Modul holen
    $label .= $modules[ $job['JOB_TYPE']]->getWorkJobLabel( $db, $job['OID'] );

    // die Laufzeiten ausgeben
    if( $ungrouped )
        $label .= getRunTimes( $db, $job['JOB_OID'], $job['OID'], $get, RUN_TIMES_SINGLE, $tState );

    else if( isset( $get['showRT'] ) && $get['showRT'] == $job['JOB_OID'] ) 
        $label .= getRunTimes( $db, $job['JOB_OID'], $job['OID'], $get, RUN_TIMES_NORMAL_P, $tState );

    else if( isset( $get['showERT'] ) && $get['showERT'] == $job['JOB_OID'] )
        $label .= getRunTimes( $db, $job['JOB_OID'], $job['OID'], $get, RUN_TIMES_NORMAL_PP, $tState );

    else
        $label .= getRunTimes( $db, $job['JOB_OID'], $job['OID'], $get, RUN_TIMES_GRUPPE, $tState );

    // Gruppenaktionen
    if( $tState['exe'] )
    {
        $label .= '<TR>';

        if( !$ungrouped )
        {
            // Gruppeninfos laden
            $gInfos = $db->dbRequest( "with data as ( select wl.BREAKPOINT,
                                                             case when j.STATE_ORDER = ?
                                                                  then j.SET_OK_BY_USER
                                                                  else 0
                                                              end OBU_ERR,
                                                             case when j.STATE_ORDER = ?
                                                                  then j.SET_OK_BY_USER
                                                                  else 0
                                                              end OBU_ALL
                                                        from X2_WORKLIST wl
                                                             inner join X2_WORK_GROUP wg
                                                                on     wg.TEMPLATE_EXE_ID = wl.TEMPLATE_EXE_ID
                                                                   and wg.MEMBER_OID = wl.OID
                                                                   and wg.GROUP_OID = ?
                                                             inner join X2_JOB_STATE j
                                                                on j.JOB_STATE = wl.STATE
                                                       where wl.TEMPLATE_EXE_ID = ? )
                                       select sum( BREAKPOINT ) BPNT,
                                              max( OBU_ERR ) OBU_ERR,
                                              max( OBU_ALL ) OBU_ALL,
                                              count(*) ANZ
                                         from data",
                                      array( array( 'i', JOB_STATES['ERROR']['id'] ),
                                             array( 'i', JOB_STATES['CREATED']['id'] ),
                                             array( 'i', $job['JOB_OID'] ),
                                             array( 'i', $get['wid'] )));

            $gInfo = $gInfos->resultset[0];

            // OK_BY_USER ( ERR )
            if( $gInfo['OBU_ERR'] == 1 )
                $label .= getButtonLabel( 'worklist',
                                          $get['pageID'],
                                          $get['wid'],
                                          'setOkG=' . $job['JOB_OID'] . '&amp;amp;state',
                                          JOB_STATES['ERROR']['id'],
                                          'fehlerhafte auf OK setzen',
                                          '&#xe125;',
                                          'BGCOLOR="red"' );

            else if( $gInfo['OBU_ALL'] == 1 )
                $label .= getButtonLabel( 'worklist',
                                          $get['pageID'],
                                          $get['wid'],
                                          'setOkG=' . $job['JOB_OID'] . '&amp;amp;state',
                                          JOB_STATES['CREATED']['id'],
                                          'alle auf OK setzen',
                                          '&#xe125;' );

            else
                $label .= '<TD>&nbsp;</TD>';

            // Gruppe öffnen
            $label .= getButtonLabel( 'worklist',
                                      $get['pageID'],
                                      $get['wid'],
                                      'ungroup',
                                      $job['JOB_OID'],
                                      'Gruppe öffnen',
                                      '&#xe105;',
                                      'COLSPAN="4"' );

            /* Breakpoint
             *
             * haben nicht alle Jobs der Gruppe den gleichen BREAKPOINT
             * dann wird das Zelt orange OHNE Link ausgegeben
             */
            if( $gInfo['BPNT'] && $gInfo['ANZ'] != $gInfo['BPNT'] )
                $label .= '<TD BGCOLOR="orange" COLSPAN="2">'
                        . '<FONT FACE="Glyphicons Halflings" COLOR="black">&#x26fa;</FONT>'
                        . '</TD>';

            else if( $job['BREAKPOINT'] )
                $label .= getButtonLabel( 'worklist',
                                          $get['pageID'],
                                          $get['wid'],
                                          'unsetBpG',
                                          $job['JOB_OID'],
                                          'Breakpoint entfernen',
                                          '&#x26fa;',
                                          'BGCOLOR="red" COLSPAN="2"' );

            else
                $label .= getButtonLabel( 'worklist',
                                          $get['pageID'],
                                          $get['wid'],
                                          'setBpG',
                                          $job['JOB_OID'],
                                          'einen Breakpoint setzen',
                                          '&#x26fa;',
                                          'COLSPAN="2"' );
        }

        // Einzelaktionen
        else
        {
            // OK_BY_USER
            if( REVERSE_JOB_STATES[ $job['STATE']]['setOkbyUser'] )
                $label .= getButtonLabel( 'worklist',
                                          $get['pageID'],
                                          $get['wid'],
                                          'setOk=' . $job['OID'] . '&amp;amp;ungroup',
                                          $job['JOB_OID'],
                                          'auf OK setzen',
                                          '&#xe125;' );

            // Kill
            else if( $modules[ $job['JOB_TYPE']]->opts['isKillable'] &&
                     REVERSE_JOB_STATES[ $job['STATE']]['killable'] )
                $label .= getButtonLabel( 'worklist',
                                          $get['pageID'],
                                          $get['wid'],
                                          'kill=' . $job['OID'] . '&amp;amp;getModul=' . $job['OID'] . '&amp;amp;ungroup',
                                          $job['JOB_OID'],
                                          'Job abbrechen',
                                          '&#xe090;' );

            else
                $label .= '<TD>&nbsp;</TD>';

            // Gruppe schliessen
            $label .= getButtonLabel( 'worklist',
                                      $get['pageID'],
                                      $get['wid'],
                                      'group',
                                      $job['JOB_OID'],
                                      'Gruppe schliessen',
                                      '&#xe106;',
                                      'COLSPAN="3"' );

            // Retry
            if( $modules[ $job['JOB_TYPE']]->opts['hasRetry'] &&
                REVERSE_JOB_STATES[ $job['STATE']]['restartable'] )
                $label .= getButtonLabel( 'worklist',
                                          $get['pageID'],
                                          $get['wid'],
                                          'restart=' . $job['OID'] . '&amp;amp;ungroup',
                                          $job['JOB_OID'],
                                          'Job erneut ausführen',
                                          '&#xe030;',
                                          'COLSPAN="2"' );

            else
                $label .= '<TD COLSPAN="2">&nbsp;</TD>';

            // Breakpoint
            if( $job['BREAKPOINT'] )
                $label .= getButtonLabel( 'worklist',
                                          $get['pageID'],
                                          $get['wid'],
                                          'unsetBp=' . $job['OID'] . '&amp;amp;ungroup',
                                          $job['JOB_OID'],
                                          'Breakpoint entfernen',
                                          '&#x26fa;',
                                          'BGCOLOR="red" COLSPAN="2"' );
                                          
            else
                $label .= getButtonLabel( 'worklist',
                                          $get['pageID'],
                                          $get['wid'],
                                          'setBp=' . $job['OID'] . '&amp;amp;ungroup',
                                          $job['JOB_OID'],
                                          'einen Breakpoint setzen',
                                          '&#x26fa;',
                                          'COLSPAN="2"' );
        }

        $label .= '</TR>';
    }

    $label .= '</TABLE>';

    return $label;
}

// Alle aktiven Module instanziieren
$modules = modulFunctions::getAllModulInstances( );

$db = new dbMysql( 'X2', false );
$perm = new permission( );

// eine pageID erzeugen
$pageID = $_GET['pageID'];

$gv = new Image_GraphViz();

$gv->addAttributes( array( 'bgcolor'    => 'transparent',
                           'stylesheet' => ROOT_URL . '/lib/css/graph.css' ));

// Alle Jobs laden
$jobs = $db->dbRequest( "select wl.OID,
                                wl.JOB_OID,
                                wl.JOB_NAME,
                                wl.JOB_TYPE,
                                wl.STATE,
                                wl.BREAKPOINT,
                                j.STATE_ORDER
                           from X2_WORKLIST wl
                                inner join X2_JOB_STATE j
                                   on j.JOB_STATE = wl.STATE
                          where wl.TEMPLATE_EXE_ID = ?",
                        array( array( 'i', $_GET['wid'] )));

// Gruppierbare Jobs abfragen
$jobGroups = workFunctions::getWorkGroups( $db, $_GET['wid'] );

if( isset( $_GET['ungroup'] ) && isset( $jobGroups[ $_GET['ungroup']] ))
    $jobGroups[ $_GET['ungroup']]['ungroup'] = true;

// Status-Array des Template erstellen
$tState = array( 'write' => $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $_GET['tid'], PERM_WRITE ),
                 'exe'   => $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $_GET['tid'], PERM_EXE ),
                 'admin' => $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $_GET['tid'], PERM_ADMIN ),
                 'pause' => ( $_GET['tStat'] == TEMPLATE_STATES['PAUSED'] ||
                              $_GET['tStat'] == TEMPLATE_STATES['POWER_OFF'] ));

// die JOB_STATES laden
jobState::getJobStates( $db );
jobState::getReverseJobStates( $db );

foreach( $jobs->resultset as $job )
    switch( getViewMode( $jobGroups, $job['JOB_OID'], $job['OID'] ))
    {
        case 'normal' : $gv->addNode( $job['OID'], array( 'fontsize'    => 14,
                                                          'fontname'    => 'Arial',
                                                          'fillcolor'   => '#ffffff',
                                                          'style'       => 'filled,rounded',
                                                          'shape'       => 'box',
                                                          'label'       => getJobLabel( $db,
                                                                                        $job,
                                                                                        $_GET,
                                                                                        $modules,
                                                                                        $tState )));

                        break;

        case 'gruppe' : $gv->addNode( $job['OID'], array( 'fontsize'    => 14,
                                                          'fontname'    => 'Arial',
                                                          'fillcolor'   => '#ffffff',
                                                          'style'       => 'filled,rounded',
                                                          'shape'       => 'box',
                                                          'label'       => getGroupLabel( $db,
                                                                                          $job,
                                                                                          $_GET,
                                                                                          $modules,
                                                                                          $tState )));

                        break;

        case 'ungrouped' : $gv->addNode( $job['OID'], array( 'fontsize'    => 14,
                                                             'fontname'    => 'Arial',
                                                             'fillcolor'   => '#ffffff',
                                                             'style'       => 'filled,rounded',
                                                             'shape'       => 'box',
                                                             'label'       => getGroupLabel( $db,
                                                                                             $job,
                                                                                             $_GET,
                                                                                             $modules,
                                                                                             $tState,
                                                                                             true )));

                           break;

    }

// Alle Verbindungen laden
$edges = $db->dbRequest( "select wt.OID, wt.PARENT, wl.JOB_OID, wlp.JOB_OID P_JOB_OID
                            from X2_WORK_TREE wt
                                 inner join X2_WORKLIST wl
                                    on wl.OID = wt.OID
                                 inner join X2_WORKLIST wlp
                                    on wlp.OID = wt.PARENT
                           where wt.TEMPLATE_EXE_ID = ?",
                         array( array( 'i', $_GET['wid'] )));

foreach( getEdges( $edges->resultset, $jobGroups ) as $eTarget => $eStarts )
{
    $starts = count( $eStarts ) > 1;

    foreach( $eStarts as $eStart => $link )
        $gv->addEdge( array( $eStart => $eTarget ),
                      getEdgeLabel( $link && $starts, $eStart, $eTarget, $tState, $_GET['pageID'], $_GET['wid'] ));
}

$gv->image();

?>
