<?php

define( 'SSH_KEY_TYPE_RSA', 0 );

class sshKey
{
    public $privKeyFile;
    public $pubKeyFile;
    public $keyType;

    function __construct( $keyType, $privFile, $pubFile )
    {
        $this->keyType = $keyType;
        $this->pubKeyFile = $pubFile;
        $this->privKeyFile = $privFile;
    }
}

?>
