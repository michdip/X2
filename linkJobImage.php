<?php

require_once 'conf.d/base.conf';
require_once "Image/GraphViz.php";
require_once( ROOT_DIR . '/lib/class/dbLink.class.php' );
require_once( ROOT_DIR . '/lib/class/pageID.class.php' );
require_once( ROOT_DIR . '/lib/class/permission.class.php' );
require_once( ROOT_DIR . '/lib/class/jobFunctions.class.php' );
require_once( ROOT_DIR . '/lib/class/workFunctions.class.php' );

function getJobLabel( $db, $get, $job )
{
    $label = '<TABLE>'
           . '<TR>';

    $link = false;

    if( $job['OID'] != $get['oid'] )
    {
        if( $get['mode'] == 'JOB' )
            $link = jobFunctions::isValidLinkTarget( $db, $get['oid'], $job['OID'] );

        else
            $link = workFunctions::isValidLinkTarget( $db, $get['oid'], $job['OID'] );
    }

    if( $link )
    {
        $label .= '<TD   ALIGN="center" '
                    . 'BGCOLOR="white" '
                    .  'TARGET="_parent" '
                    .   'TITLE="als Folgejob verwenden" ';

        if( $get['mode'] == 'JOB' )
            $label .= 'HREF="buildTemplate.php?pageID=' . $get['pageID']
                                      . '&amp;amp;tid=' . $get['tid'] .
                                       '&amp;amp;link=' . $get['oid'] .
                                     '&amp;amp;linkTo=' . $job['OID'] . '"';

        else
            $label .= 'HREF="worklist.php?pageID=' . $get['pageID'] .
                                   '&amp;amp;wid=' . $get['exeID'] .
                                  '&amp;amp;link=' . $get['oid']
                              . '&amp;amp;linkTo=' . $job['OID'] . '"';

        $label .= '>' . $job['JOB_NAME'] . '</TD>';
    }
    else
        $label .= '<TD ALIGN="center" BGCOLOR="#009FE3">' . $job['JOB_NAME'] . '</TD>';

    $label .= '</TR>'
            . '</TABLE>';

    return $label;
}

$db = new dbMysql( 'X2', false );
$perm = new permission( );

// Habe ich ein Schreibrecht auf das Template
$writeable = $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $_GET['tid'], PERM_WRITE );

$gv = new Image_GraphViz();

$gv->addAttributes( array( 'bgcolor'    => 'transparent',
                           'stylesheet' => ROOT_URL . '/lib/css/graph.css' ));

$jobs = array( );
$tree = array( );

if( $_GET['mode'] == 'JOB' )
{
    $jobs = $db->dbRequest( "select OID, JOB_NAME
                               from X2_JOBLIST
                              where TEMPLATE_ID = ?",
                            array( array( 'i', $_GET['tid'] )));

    $tree = $db->dbRequest( "select jt.OID, jt.PARENT
                               from X2_JOB_TREE jt
                                    inner join X2_JOBLIST jl
                                       on     jl.OID = jt.OID
                                          and jl.TEMPLATE_ID = ?",
                            array( array( 'i', $_GET['tid'] )));
}
else if( $_GET['mode'] == 'WORK' )
{
    $jobs = $db->dbRequest( "select OID, JOB_NAME
                               from X2_WORKLIST
                              where TEMPLATE_EXE_ID = ?",
                            array( array( 'i', $_GET['exeID'] )));

    $tree = $db->dbRequest( "select OID, PARENT
                               from X2_WORK_TREE
                              where TEMPLATE_EXE_ID = ?",
                            array( array( 'i', $_GET['exeID'] )));
}

// alle Jobs ausgeben
foreach( $jobs->resultset as $job )
    $gv->addNode( $job['OID'], array( 'fontsize'    => 14,
                                      'fontname'    => 'Arial',
                                      'fillcolor'   => '#ffffff',
                                      'style'       => 'filled,rounded',
                                      'shape'       => 'box',
                                      'label'       => getJobLabel( $db, $_GET, $job )));

// alle Verknüpfungen
foreach( $tree->resultset as $edge )
    $gv->addEdge( array( $edge['PARENT'] => $edge['OID'] ));

$gv->image();

?>
