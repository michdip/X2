<?php

define( 'PERM_READ', 0 );
define( 'PERM_WRITE', 1 );
define( 'PERM_EXE', 2 );
define( 'PERM_ADMIN', 3 );

define( 'PERM_OBJECT_TEMPLATE', 0 );
define( 'PERM_OBJECT_SETTINGS', 1 );

class permission
{
    function getAllGroups( )
    {
        return array( 'public', 'private' );
    }

    function getRootTreeRights( )
    {
        return array( 'public' => array( PERM_READ, PERM_WRITE, PERM_EXE ));
    }

    function getMyUserName( )
    {
        return 'Human';
    }

    function isValidSession( )
    {
        return true;
    }

    function canIDo( $db, $object, $objectId, $permission )
    {
        return true;
    }
}

?>
