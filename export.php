<?php

require_once 'conf.d/base.conf';
require_once( ROOT_DIR . '/conf.d/x2.conf' );
require_once( ROOT_DIR . '/lib/class/dbLink.class.php' );
require_once( ROOT_DIR . '/lib/modul/modulInterface.class.php' );

// Eine Description exportieren
function exportDescription( $db, $objectType, $objectId, &$objects )
{
    $myDesc = $db->dbRequest( "select DESCRIPTION, OWNER
                                 from X2_DESCRIPTION
                                where OBJECT_TYPE = ?
                                  and OBJECT_ID = ?",
                              array( array( 's', $objectType ),
                                     array( 'i', $objectId )));

    foreach( $myDesc->resultset as $row )
        $objects['desc'] = array( 'desc'  => $row['DESCRIPTION'],
                                  'owner' => $row['OWNER'] );
}

// einen Job exportieren
function exportJob( $db, &$modules, &$refMap, $oid )
{
    $mySelf = null;

    // die Basisdaten exportieren
    $mySelfs = $db->dbRequest( "select JOB_TYPE, JOB_NAME, BREAKPOINT
                                  from X2_JOBLIST
                                 where OID = ?",
                               array( array( 'i', $oid )));

    foreach( $mySelfs->resultset as $row )
        $mySelf = array( 'jid'    => $oid,
                         'type'   => $row['JOB_TYPE'],
                         'name'   => $row['JOB_NAME'],
                         'brkp'   => $row['BREAKPOINT'] );

    // mein Modul exportieren
    $modules[ $mySelf['type']]->exportJob( $db, $oid, $mySelf, $refMap );

    // die Beschreibung exportieren
    exportDescription( $db, 'JOB', $oid, $mySelf );

    return $mySelf;
}

function exportTemplate( $db, &$modules, &$refMap, $tid )
{
    $mySelf = null;

    // in die refMap eintragen
    if( !isset( $refMap['tid'][ $tid ] ))
        $refMap['tid'][ $tid ] = true;

    // das Template exportieren
    $mySelfs = $db->dbRequest( "select OBJECT_TYPE, OBJECT_NAME
                                  from X2_TEMPLATE
                                 where OID = ?",
                               array( array( 'i', $tid )));

    foreach( $mySelfs->resultset as $row )
        $mySelf = array( 'tid'    => $tid,
                         'type'   => $row['OBJECT_TYPE'],
                         'name'   => $row['OBJECT_NAME'],
                         'var'    => array( ),
                         'notis'  => array( ));

    // meine Variablen exportieren
    $myVars = $db->dbRequest( "select VAR_NAME, VAR_VALUE
                                 from X2_JOB_VARIABLE
                                where TEMPLATE_ID = ?",
                              array( array( 'i', $tid )));

    foreach( $myVars->resultset as $row )
        array_push( $mySelf['var'], array( 'name'  => $row['VAR_NAME'],
                                           'value' => $row['VAR_VALUE'] ));

    // meine Beschreibung exportieren
    exportDescription( $db, 'TEMPLATE', $tid, $mySelf );

    // Notifier exportieren
    $myNotis = $db->dbRequest( "select STATE, RECIPIENT
                                  from X2_TEMPLATE_NOTIFIER
                                 where TEMPLATE_ID = ?",
                               array( array( 'i', $tid )));

    foreach( $myNotis->resultset as $row )
        array_push( $mySelf['notis'], array( 'state'     => $row['STATE'],
                                             'recipient' => $row['RECIPIENT'] ));

    switch( $mySelf['type'] )
    {
        // bei einem Template alle Jobs exportieren
        case 'T': $mySelf['jobs'] = array( );
                  $mySelf['jobTree'] = array( );

                  $jobs = $db->dbRequest( "select OID
                                             from X2_JOBLIST
                                            where TEMPLATE_ID = ?",
                                          array( array( 'i', $tid )));

                  foreach( $jobs->resultset as $job )
                      array_push( $mySelf['jobs'], exportJob( $db, $modules, $refMap, $job['OID'] ));

                  // den Baum der Jobs exportieren
                  $jTree = $db->dbRequest( "select jt.OID, jt.PARENT
                                              from X2_JOB_TREE jt
                                                   inner join X2_JOBLIST jl
                                                      on jl.OID = jt.OID
                                             where jl.TEMPLATE_ID = ?",
                                           array( array( 'i', $tid )));

                  foreach( $jTree->resultset as $link )
                      array_push( $mySelf['jobTree'], array( 'jid' => $link['OID'],
                                                             'pid' => $link['PARENT'] ));

                  break;

        // bei einer Gruppe alle Kinder exportieren
        case 'G': $mySelf['childs'] = array( );

                  $childs = $db->dbRequest( "select OID
                                               from X2_TEMPLATE_TREE
                                              where PARENT = ?
                                                and VGRAD = 1",
                                            array( array( 'i', $tid )));

                  foreach( $childs->resultset as $row )
                      array_push( $mySelf['childs'], exportTemplate( $db, $modules, $refMap, $row['OID'] ));

                  break;
    }

    return $mySelf;
}

if( isset( $_GET['tid'] ))
{
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachement; filename=x2_export_' . $_GET['tid'] . '.json' );

    $db = new dbMysql( 'X2', false );

    // alle Module instanziieren
    $modules = array( );

    foreach( MODULES as $moduleName => $moduleDef )
        if( $moduleDef['active'] )
        {
            require_once( $moduleDef['classFile'] );

            $className = $moduleDef['className'];
            $modules[ $moduleName ] = new $className( );
        }

    $refMap = array( 'tid'    => array( ),
                     'refTid' => array( ),
                     'hosts'  => array( ));

    $refMap['tpl'] = exportTemplate( $db, $modules, $refMap, $_GET['tid'] );

    print json_encode( $refMap );
}

?>
