<?php

class viewOption
{
    public static $view = array( 'X2_SEARCH_TEMPLATE',
                                 'X2_DUPLICATE_TEMPLATE',
                                 'X2_EDIT_TEMPLATE',
                                 'X2_EDIT_TEMPLATE_NAME',
                                 'X2_EDIT_VARIABLE',
                                 'X2_EXPORT_TEMPLATE',
                                 'X2_MOVE_TEMPLATE',
                                 'X2_REMOVE_TEMPLATE',
                                 'X2_RUN_CONTROLS',
                                 'X2_MUTEX_STATE',
                                 'X2_SWITCH_POWER',
                                 'X2_VIEW_CONTROLS',
                                 'X2_VIEW_RECHT',
                                 'X2_VIEW_TAG',
                                 'X2_TEMPLATE_NOTIFIER',
                                 'X2_VIEW_LINKED_TEMPLATE',
                                 'X2_VIEW_HISTORY' );

    public static function getUserViewOption( $db, $user )
    {
        $result = array( );

        // die initialen Werte setzen
        foreach( viewOption::$view as $option )
            if( $option == 'X2_SEARCH_TEMPLATE' )
                $result[ $option ] = 0;
            else
                $result[ $option ] = 1;

        // alle Einstellungen laden
        $options = $db->dbRequest( "select VIEW_NAME, VIEW_VALUE
                                      from X2_VIREW_OPTION
                                     where X2_USER = ?",
                                   array( array( 's', $user )));

        foreach( $options->resultset as $option )
            $result[ $option['VIEW_NAME']] = ( $option['VIEW_VALUE'] == 1 );

        return $result;
    }

    public static function setUserView( $db, $user, $postFields )
    {
        // alle Rechte löschen
        $db->dbRequest( "delete
                           from X2_VIREW_OPTION
                          where X2_USER = ?",
                        array( array( 's', $user )));

        // alle Rechte schreiben
        foreach( viewOption::$view as $option )
            $db->dbRequest( "insert into X2_VIREW_OPTION (X2_USER, VIEW_NAME, VIEW_VALUE)
                             values ( ?, ?, ? )",
                            array( array( 's', $user ),
                                   array( 's', $option ),
                                   array( 'i', isset( $postFields[ 'set_' . $option ] ))));

        $db->commit( );
    }
}

?>
