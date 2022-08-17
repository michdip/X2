<?php

require_once( ROOT_DIR . '/lib/class/mutex.class.php' );

class sequence
{
    public static function getNextValue( $db, $name, &$logger, $user )
    {
        // ID der Sequqnz laden
        $seqId = $db->dbRequest( "select OID
                                    from SEQUENCE
                                   where SEQ_NAME = ?",
                                 array( array( 's', $name )));

        if( $seqId->numRows != 1 )
            return -1;

        // Mutex für die Sequence holen
        if( !mutex::requestMutex( $db, $logger, 'SEQ', $seqId->resultset[0]['OID'], $user, 10, 1 ))
            return -1;

        // Sequence updaten
        $db->dbRequest( "update SEQUENCE
                            set SEQ_VALUE = SEQ_VALUE + 1
                          where OID = ?",
                        array( array( 'i', $seqId->resultset[0]['OID'] )));

        $db->commit( );

        $nextVal = $db->dbRequest( "select SEQ_VALUE
                                      from SEQUENCE
                                     where OID = ?",
                                   array( array( 'i', $seqId->resultset[0]['OID'] )));

        // Mutex lösen
        mutex::releaseMutex( $db, $logger, 'SEQ', $seqId->resultset[0]['OID'] );

        return $nextVal->resultset[0]['SEQ_VALUE'];
    }
}

?>
