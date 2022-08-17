<?php

require_once 'conf.d/base.conf';
require_once( ROOT_DIR . '/lib/class/dbLink.class.php' );

header( "Content-Type: text/plain; charset=utf-8" );

if( isset( $_GET['oid'] ))
{
    $db = new dbMySQL( 'X2', false );
    $file = $db->dbRequest( "select DATA
                               from X2_LOGFILE
                              where OID = ?",
                            array( array( 'i', $_GET['oid'] )));

    if( $file->numRows > 0 )
        print $file->resultset[0]['DATA'];
}
else if( isset( $_GET['jid'] ) && isset( $_GET['exe'] ))
{
    $db = new dbMySQL( 'X2', false );
    $file = $db->dbRequest( "select uncompress(LOGDATA) DATA
                               from X2_ARCHIV
                              where JOB_OID = ?
                                and TEMPLATE_EXE_ID = ?",
                            array( array( 'i', $_GET['jid'] ),
                                   array( 'i', $_GET['exe'] )));

    if( $file->numRows > 0 )
        print $file->resultset[0]['DATA'];
}

?>
