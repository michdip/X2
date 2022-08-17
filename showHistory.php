<?php

require_once 'conf.d/base.conf';
require_once( ROOT_DIR . '/lib/class/dbLink.class.php' );
require_once( ROOT_DIR . '/lib/class/mySmarty.class.php' );

if( isset( $_POST['limitpost'] ))
{
    if( $_POST['limitpost'] > 0 )
        $_GET['limit'] = $_POST['limitpost'];

    $_GET['limitoffset'] = $_POST['limitoffset'];
    $_GET['tid'] = $_POST['tid'];
    $_GET['eid'] = $_POST['eid'];
}

if( isset( $_GET['tid'] ))
{
    $db = new dbMySQL( 'X2', false );

    $smarty = new mySmarty( );
    $smarty->assign( 'ROOT_URL', ROOT_URL );
    $smarty->assign( 'tid', $_GET['tid'] );

    $params = array( array( 'i', $_GET['tid'] ));
    $where = 'l.TEMPLATE_ID = ?';

    if( isset( $_GET['eid'] ) && $_GET['eid'] != '' && $_GET['eid'] != 0 )
    {
        $where .= ' and l.TEMPLATE_EXE_ID = ?';
        array_push( $params, array( 'i', $_GET['eid'] ));
        $smarty->assign( 'eid', $_GET['eid'] );
    }
    else
        $smarty->assign( 'eid', 0 );

    if( isset( $_GET['limit'] ))
    {
        $limitn = $_GET['limit'];
        $limitoffset = $_GET['limitoffset'];
    }
    else
    {
        $limitn = 50;
        $limitoffset = 0;
    }

    $smarty->assign( 'limitoffset', $limitoffset + $limitn );
    $smarty->assign( 'limitoffsetminus', $limitoffset - $limitn );
    $smarty->assign( 'dblimit', $limitn );

    $actions = $db->dbRequest( "select l.X2_USER,
                                       date_format( l.CTS, '%d.%m.%Y %H:%i:%s' ) as CTS,
                                       a.DESCRIPTION,
                                       l.ACTION_TEXT,
                                       l.TEMPLATE_EXE_ID
                                  from X2_ACTIONLOG l
                                       inner join X2_ACTION a
                                          on a.OID = l.ACTION_ID
                                 where " . $where . "
                                 order by l.OID desc
                                 LIMIT ". $limitoffset . ", " . $limitn . " ",
                               $params );

    $smarty->assign( 'actions', $actions->resultset );

    $smarty->assign( 'css', array( 'basic.css', 'icons.css' ));
    $smarty->assign( 'js', array( ));

    $smarty->display( 'showHistory.tpl' );
}

?>
