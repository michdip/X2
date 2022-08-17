<?php

require_once( ROOT_DIR . '/lib/class/actionlog.class.php' );

class description
{
    public static function getDescription( $db, $objectType, $objectId )
    {
        $desc = $db->dbRequest( "select DESCRIPTION,
                                        OWNER
                                   from X2_DESCRIPTION
                                  where OBJECT_TYPE = ?
                                    and OBJECT_ID = ?",
                                array( array( 's', $objectType ),
                                       array( 'i', $objectId )));

        if( $desc->numRows == 0 )
            return array( 'DESCRIPTION' => '',
                          'OWNER'       => '' );

        else
            return $desc->resultset[0];
    }

    public static function setDescription( $db, $objectType, $objectId, $descOwner, $desc, $user, $automatic = false )
    {
        try
        {
            // die alten Beschreibungen löschen
            $db->dbRequest( "delete
                               from X2_DESCRIPTION
                              where OBJECT_TYPE = ?
                                and OBJECT_ID = ?",
                            array( array( 's', $objectType ),
                                   array( 'i', $objectId )),
                            true );

            // die neue Beschreibung anlegen
            if( $descOwner != '' ||  $desc != '' )
            {
                $db->dbRequest( "insert into X2_DESCRIPTION (OBJECT_TYPE, OBJECT_ID, DESCRIPTION, OWNER)
                                 values ( ?, ?, ?, ? )",
                                array( array( 's', $objectType ),
                                       array( 'i', $objectId ),
                                       array( 's', $desc ),
                                       array( 's', $descOwner )),
                                true );

                // das ActionLog füllen
                actionlog::logAction( $db, 5, $objectId, $user, $descOwner . ' / ' . $desc );
            }
            else
                actionlog::logAction( $db, 6, $objectId, $user );

            if( !$automatic )
                $db->commit( );
        }
        catch( Exception $e )
        {
            $db->rollback();

            if( !$automatic )
                print $e->getMessage();

            else
                throw $e;
        }
    }
}

?>
