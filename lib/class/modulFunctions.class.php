<?php

require_once( ROOT_DIR . '/conf.d/x2.conf' );
require_once( ROOT_DIR . '/lib/modul/modulInterface.class.php' );

class modulFunctions
{
    public static function getAllModulInstances( )
    {
        $instances = array( );

        foreach( MODULES as $modulName => $modulDef )
            if( $modulDef['active'] )
            {
                require_once( $modulDef['classFile'] );

                $className = $modulDef['className'];

                $instances[ $modulName ] = new $className( );
            }

        return $instances;
    }

    public static function getModulInstance( $modulName )
    {
        require_once( MODULES[ $modulName ]['classFile'] );

        $className = MODULES[ $modulName ]['className'];

        return new $className( );
    }
}

?>
