<?php

require_once '../../conf.d/base.conf';
require_once( ROOT_DIR . '/conf.d/databases.conf' );
require_once( ROOT_DIR . '/lib/class/dbLink.class.php' );

if( isset( $_GET['bo'] ) && isset( $_GET['oid'] ))
{
    $db = new dbMySQL( 'X2', false );

    header("Content-Type: application/javascript");

    print "function copyToClipboard( id )\n";
    print "{\n";
    print "    var strs = [];\n";

    if( $_GET['bo'] == 'wl' )
        $strs = $db->dbRequest( "select wl.OID, wc.SOURCE, wc.EXEC_PATH, wc.COMMAND
                                   from X2_WORK_COMMAND wc
                                        inner join X2_WORKLIST wl
                                           on wl.OID = wc.OID
                                  where wl.TEMPLATE_EXE_ID = ?",
                                array( array( 'i', $_GET['oid'] )));

    else if( $_GET['bo'] == 'bt' )
        $strs = $db->dbRequest( "select c.OID, c.SOURCE, c.EXEC_PATH, c.COMMAND
                                   from X2_JOB_COMMAND c
                                        inner join X2_JOBLIST l
                                           on l.OID = c.OID
                                  where l.TEMPLATE_ID = ?",
                                array( array( 'i', $_GET['oid'] )));

    foreach( $strs->resultset as $row )
    {
        print '    strs["S' . $row['OID'] . '"] = "' . $row['SOURCE'] . "\";\n";
        print '    strs["P' . $row['OID'] . '"] = "' . $row['EXEC_PATH'] . "\";\n";
        print '    strs["C' . $row['OID'] . '"] = "' . $row['COMMAND'] . "\";\n";
    }

    print "    const el = document.createElement( 'textarea' );\n";
    print "    el.value = strs[ id ];\n";
    print "    document.body.appendChild( el );\n";
    print "    el.select();\n";
    print "    document.execCommand( 'copy' );\n";
    print "    document.body.removeChild( el );\n";
    print "}\n";
}

?>
