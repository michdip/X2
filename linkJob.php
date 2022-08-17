<?php

require_once 'conf.d/base.conf';
require_once( ROOT_DIR . '/lib/class/mySmarty.class.php' );
require_once( ROOT_DIR . '/lib/class/permission.class.php' );
require_once( ROOT_DIR . '/lib/class/dbLink.class.php' );
require_once( ROOT_DIR . '/lib/class/pageID.class.php' );
require_once( ROOT_DIR . '/lib/class/templateFunctions.class.php' );
require_once( ROOT_DIR . '/lib/class/jobFunctions.class.php' );

$db = new dbMysql( 'X2', false );
$smarty = new mySmarty( );
$perm = new permission( );

// die Session prüfen
if( $perm->isValidSession( ))
{
    // arrays für die Includes im HTML
    $css = array( 'basic.css', 'icons.css' );
    $js = array( );

    // eine pageID holen
    $smarty->assign( 'pageID', pageID::getMyPageID( $db, $perm->getMyUserName( )));

    // JOB vs WORK
    if( isset( $_GET['tid'] ))
    {
        $smarty->assign( 'template', templateFunctions::getTemplate2Graph( $db, $_GET['tid'] ));
        $smarty->assign( 'mode', 'JOB' );
    }
    else if( isset( $_GET['wid'] ))
    {
        $template = templateFunctions::getTemplate2GraphByWID( $db, $_GET['wid'] );

        $smarty->assign( 'template', templateFunctions::getTemplate2Graph( $db, $template ));
        $smarty->assign( 'exeID', $_GET['wid'] );
        $smarty->assign( 'mode', 'WORK' );
    }

    // den Quelljob weitergeben
    $smarty->assign( 'linkJob', $_GET['linkJob'] );

    // die CSS laden
    $smarty->assign( 'ROOT_URL', ROOT_URL );
    $smarty->assign( 'css', $css );
    $smarty->assign( 'js', $js );

    $smarty->display( 'linkJob.tpl' );
}
else
{
    $smarty->display( 'noSession.tpl' );
}

?>
