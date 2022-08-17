<?php

require_once( ROOT_DIR . '/lib/class/sequence.class.php' );
require_once( ROOT_DIR . '/lib/class/logger.class.php' );

class pageID
{
    public static function getMyPageID( $db, $user )
    {
        # Das Verfahren funktioniert nur wenn das autocommit aus ist.
        if( $db->getAutocommitState())
            return null;

        // den Logger erstellen
        $logger = new logger( $db, 'pageID::getMyPageID( ' . $user . ' )' );

        // eine PageID aus der Sequenz holen
        $myID = sequence::getNextValue( $db, 'PAGE_ID', $logger, $user );

        // die PageID zuweisen
        $db->dbRequest( "insert into PAGE_ID (PAGE_ID, X2_USER)
                         values (?, ?)",
                        array( array( 'i', $myID ),
                               array( 's', $user )));

        $db->commit();

        return $myID;
    }

    public static function usePageID( $db, $pageID, $user )
    {
        $res = $db->dbRequest( "delete from PAGE_ID
                                 where PAGE_ID = ?
                                   and X2_USER = ?",
                               array( array( 'i', $pageID ),
                                      array( 's', $user )));

        if( ! $db->getAutocommitState())
            $db->commit();

        if( $res->affectedRows == 1 )
            return true;

        return false;
    }
}
