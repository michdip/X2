<?php

require_once( ROOT_DIR . '/lib/class/myPHPMailer.class.php' );

class mutex
{
    // diese Funktion wird immer innerhalb eines TRY aufgerufen, und bedarf keinem Commit
    public static function createMutex( $db, $objectType, $objectId )
    {
        $db->dbRequest( "insert into MUTEX (OBJECT, OBJECT_ID)
                         values ( ?, ? )",
                        array( array( 's', $objectType ),
                               array( 'i', $objectId )),
                        true );
    }

    // den Mutex löschen, wird immer innerhalb des Mutex aufgerufen
    public static function removeMutex( $db, $objectType, $objectId )
    {
        $db->dbRequest( "delete
                           from MUTEX
                          where OBJECT = ?
                            and OBJECT_ID = ?",
                        array( array( 's', $objectType ),
                               array( 'i', $objectId )),
                        true );
    }

    public static function getMutexState( $db, $objectType, $objectId )
    {
        $state = $db->dbRequest( "select 1
                                    from MUTEX
                                   where OBJECT = ?
                                     and OBJECT_ID = ?
                                     and STATE = 'FREE'",
                                 array( array( 's', $objectType ),
                                        array( 'i', $objectId )));

        return ( $state->numRows > 0 );
    }

    public static function getMutex( $db, &$logger, $objectType, $objectId, $owner )
    {
        // prüfen, ob die DB-Verbindung auf autocommit false steht
        if( $db->getAutocommitState() )
            throw new Exception( 'Die Datenbankverbindung wurde als autocommit = true geöffnet' );

        // versuchen den Mutex zu bekommen
        $mutex = $db->dbRequest( "select 1
                                    from MUTEX
                                   where OBJECT = ?
                                     and OBJECT_ID = ?
                                     and STATE = 'FREE'
                                      for update",
                                 array( array( 's', $objectType ),
                                        array( 'i', $objectId )));

        if( $mutex->numRows == 1 )
        {
            $db->dbRequest( "update MUTEX
                                set STATE = 'TAKEN',
                                    DTS = now(),
                                    OWNER = ?
                               where OBJECT = ?
                                 and OBJECT_ID = ?",
                            array( array( 's', $owner ),
                                   array( 's', $objectType ),
                                   array( 'i', $objectId )));

            $db->commit();

            $logger->writeLog( "... Mutex erhalten" );

            return true;
        }

        $logger->writeLog( "... keinen Mutex erhalten" );

        return false;
    }

    public static function requestMutex( $db, &$logger, $objectType, $objectId, $owner, $tries, $sleepTime )
    {
        // Diese Function kann nur im Autocommit-false-Modus ausgeführt werden
        if( $db->getAutocommitState() )
            throw new Exception( 'Die Datenbank wurde als autocommit = true geöffnet!' );

        $logger->writeLog( "Mutex holen für " . $objectType . " / " . $objectId . " von " . $owner . " ..." );

        // Mutex erhalten
        $mutex = false;

        while( $tries > 0 && ! $mutex )
        {
            $tries--;
            $mutex = mutex::getMutex( $db, $logger, $objectType, $objectId, $owner );

            if( ! $mutex )
            {
                $db->rollback();
                sleep( $sleepTime );
            }
        }

        return $mutex;
    }

    public static function releaseMutex( $db, &$logger, $objectType, $objectId )
    {
        $db->dbRequest( "update MUTEX
                            set STATE = 'FREE',
                                DTS = now(),
                                OWNER = null
                          where OBJECT = ?
                            and OBJECT_ID = ?",
                        array( array( 's', $objectType ),
                               array( 'i', $objectId )));

        $db->commit();

        $logger->writeLog( "... Mutex released" );
    }

    public static function resetMutex( $db, &$logger )
    {
        $mtx = $db->dbRequest( "select OID,
                                       OBJECT,
                                       OBJECT_ID,
                                       OWNER,
                                       date_format( DTS, '%d.%m.%Y %H:%i:%s' ) LAST_USE
                                  from MUTEX
                                 where DTS < date_add( now( ), interval -10 minute )
                                   and STATE = 'TAKEN'" );

        foreach( $mtx->resultset as $row )
        {
            if( USE_MAIL_DROP )
                myPhpMailer::sendMail( 'Der Mutex ' . $row['OID'] . ' des ' . $row['OBJECT'] . ' Nr ' . $row['OBJECT_ID']
                                     . ' wurde zurückgesetzt. Die letzte Verwendung ' . $row['LAST_USE'] . ' war von ' . $row['OWNER']
                                     . "\n\nDiese Mail wurde versendet von " . ROOT_URL,
                                       'X2 automatischer Mutex-Reset',
                                       ADMIN_MAIL,
                                       $logger );

            $logger->writeLog( 'Automatischer Mutex-Reset für ' . $row['OBJECT'] . ' No ' . $row['OBJECT_ID'] );

            mutex::releaseMutex( $db, $logger, $row['OBJECT'], $row['OBJECT_ID'] );
        }
    }
}

?>
