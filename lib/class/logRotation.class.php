<?php

class logRotation
{
    public static function reportLogFile( $db, $remoteHost, $filename, $logDate )
    {
        // ist das File bereits bekannt
        $known = $db->dbRequest( "select 1
                                    from X2_NATIVE_LOG
                                   where REMOTE_HOST = ?
                                     and FILENAME = ?",
                                 array( array( 's', $remoteHost ),
                                        array( 's', $filename )));

        if( $known->numRows == 0 )
        {
            $db->dbRequest( "insert into X2_NATIVE_LOG (REMOTE_HOST, FILENAME, LOGDATE)
                             values ( ?, ?, STR_TO_DATE( ?, '%Y-%m-%d' ))",
                            array( array( 's', $remoteHost ),
                                   array( 's', $filename ),
                                   array( 's', $logDate )));

            $db->commit();
        }
    }

    public static function getFilesToCompress( $db )
    {
        $result = array( );

        $comp = $db->dbRequest( "select REMOTE_HOST,
                                        FILENAME
                                   from X2_NATIVE_LOG
                                  where COMPRESSED = 0
                                    and LOGDATE < curdate()" );

        foreach( $comp->resultset as $file )
        {
            if( !isset( $result[ $file['REMOTE_HOST'] ] ))
                $result[ $file['REMOTE_HOST'] ] = array( 'name'  => $file['REMOTE_HOST'],
                                                         'files' => array( ));

            array_push( $result[ $file['REMOTE_HOST'] ]['files'], $file['FILENAME'] );
        }

        return $result;
    }

    public static function compressFile( $db, $remoteHost, $filename )
    {
        $db->dbRequest( "update X2_NATIVE_LOG
                            set COMPRESSED = 1
                          where REMOTE_HOST = ?
                            and FILENAME = ?",
                        array( array( 's', $remoteHost ),
                               array( 's', $filename )));
    }

    public static function getFilesToDelete( $db )
    {
        $result = array( );

        $dels = $db->dbRequest( "select REMOTE_HOST,
                                        FILENAME
                                   from X2_NATIVE_LOG
                                  where LOGDATE < date_add( curdate(), interval ? month)",
                                array( array( 'i', -HOUSEKEEPING_KEEP_NATIVE_LOG_FILES_MONTH )));

        foreach( $dels->resultset as $file )
        {
            if( !isset( $result[ $file['REMOTE_HOST'] ] ))
                $result[ $file['REMOTE_HOST'] ] = array( 'name'  => $file['REMOTE_HOST'],
                                                         'files' => array( ));

            array_push( $result[ $file['REMOTE_HOST'] ]['files'], $file['FILENAME'] );
        }

        return $result;
    }

    public static function deleteFile( $db, $remoteHost, $filename )
    {
        $db->dbRequest( "delete
                           from X2_NATIVE_LOG
                          where REMOTE_HOST = ?
                            and FILENAME = ?",
                        array( array( 's', $remoteHost ),
                               array( 's', $filename )));
    }
}

?>
