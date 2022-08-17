<?php

require_once 'conf.d/base.conf';
require_once( ROOT_DIR . '/lib/class/mySmarty.class.php' );
require_once( ROOT_DIR . '/lib/class/permission.class.php' );
require_once( ROOT_DIR . '/lib/class/dbLink.class.php' );
require_once( ROOT_DIR . '/lib/class/pageID.class.php' );

$db = new dbMysql( 'X2', false );
$smarty = new mySmarty( );
$perm = new permission( );

// die Session prüfen
if( $perm->isValidSession( ))
{
    // arrays für die Includes im HTML
    $css = array( 'basic.css', 'icons.css' );
    $js = array( );

    // meine Position im Baum festlegen
    $parentID = 0;

    if( isset( $_GET['parentID'] ))
        $parentID = $_GET['parentID'];

    $smarty->assign( 'parentID', $parentID );
    $smarty->assign( 'move', $_GET['move'] );

    // die CSS laden
    $smarty->assign( 'ROOT_URL', ROOT_URL );
    $smarty->assign( 'css', $css );
    $smarty->assign( 'js', $js );

    $smarty->display( 'templateTree.tpl' );
}
else
{
    $smarty->display( 'noSession.tpl' );
}


?>
