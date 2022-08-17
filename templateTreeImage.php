<?php

require_once 'conf.d/base.conf';
require_once "Image/GraphViz.php";
require_once( ROOT_DIR . '/lib/class/dbLink.class.php' );
require_once( ROOT_DIR . '/lib/class/pageID.class.php' );
require_once( ROOT_DIR . '/lib/class/permission.class.php' );

$db = new dbMysql( 'X2', false );
$perm = new permission( );

// eine PageID holen
$pageID = pageID::getMyPageID( $db, $perm->getMyUserName( ));

// Habe ich ein Schreibrecht auf das Template
$writeable = $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $_GET['tid'], PERM_WRITE );

$gv = new Image_GraphViz();

$gv->addAttributes( array( 'rankdir'    => 'LR',
                           'bgcolor'    => 'transparent',
                           'stylesheet' => ROOT_URL . '/lib/css/graph.css' ));

// alle Gruppen laden
$tree = $db->dbRequest( "select t.OID, t.OBJECT_NAME, tt.PARENT
                           from X2_TEMPLATE t
                                left join X2_TEMPLATE_TREE tt
                                  on     tt.OID = t.OID
                                     and tt.VGRAD = 1
                          where t.OBJECT_TYPE = 'G'
                         union
                         select 0, 'ROOT', NULL" );

// alle Gruppen ausgeben
foreach( $tree->resultset as $grp )
    if( $writeable &&                                                        // Schreibrecht auf das zu verschiebende Template
        $grp['OID'] != $_GET['tid'] &&                                       // Gruppe != das zu verschiebende Template
        $grp['OID'] != $_GET['parentID'] &&                                  // Gruppe != jetziger Vater
        $perm->canIDo( $db, PERM_OBJECT_TEMPLATE, $grp['OID'], PERM_WRITE )) // Schreibrecht auf die Gruppe
        $gv->addNode( $grp['OID'], array( 'fontsize'    => 14,
                                          'fontname'    => 'Arial',
                                          'fillcolor'   => '#ffffff',
                                          'style'       => 'rounded,filled',
                                          'shape'       => 'box',
                                          'label'       => $grp['OBJECT_NAME'],
                                          'URL'         => 'overview.php?parentID=' . $grp['OID'] . '&amp;pageID=' . $pageID . '&amp;moveSrc=' . $_GET['parentID'] . '&amp;moveTid=' . $_GET['tid'],
                                          'target'      => '_parent' ));

    else
        $gv->addNode( $grp['OID'], array( 'fontsize'    => 14,
                                          'fontname'    => 'Arial',
                                          'fillcolor'   => 'transparent',
                                          'style'       => 'rounded',
                                          'shape'       => 'box',
                                          'label'       => $grp['OBJECT_NAME'] ));


// die Struktur ausgeben
foreach( $tree->resultset as $grp )
    if( $grp['PARENT'] != '' )
        $gv->addEdge( array( $grp['PARENT'] => $grp['OID'] ));

    else if( $grp['OID'] != 0 )
        $gv->addEdge( array( 0 => $grp['OID'] ));

$gv->image();

?>
