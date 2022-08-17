<?php

require_once( ROOT_DIR . '/lib/smarty/Smarty.class.php' );

class mySmarty extends Smarty
{
    function __construct( )
    {
        parent::__construct( );

        $this->setTemplateDir( ROOT_DIR . '/templates' );
        $this->setCompileDir( ROOT_DIR . '/templates/templates_c' );
        $this->setCacheDir( ROOT_DIR . '/templates/cache' );
    }
}

?>
