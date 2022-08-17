<?php

class actionlog
{
    public static function logAction( $db, $actionId, $templateId, $user, $message = null, $exeId = null )
    {
        $db->dbRequest( "insert into X2_ACTIONLOG (TEMPLATE_ID, ACTION_ID, ACTION_TEXT, X2_USER, TEMPLATE_EXE_ID)
                         values (?, ?, ?, ?, ?)",
                        array( array( 'i', $templateId ),
                               array( 'i', $actionId ),
                               array( 's', substr( $message, 0, 500 )),
                               array( 's', $user ),
                               array( 'i', $exeId )),
                        true );
    }

    public static function logAction4Job( $db, $actionId, $jobId, $user, $message = null, $exeId = null )
    {
        $template = $db->dbRequest( "select TEMPLATE_ID
                                       from X2_JOBLIST
                                      where OID = ?",
                                    array( array( 'i', $jobId )));

        foreach( $template->resultset as $value )
            self::logAction( $db, $actionId, $value['TEMPLATE_ID'], $user, $jobId . $message, $exeId );
    }

    public static function logAction4WJob( $db, $actionId, $workId, $user, $message = null )
    {
        $template = $db->dbRequest( "select TEMPLATE_ID,
                                            TEMPLATE_EXE_ID
                                       from X2_WORKLIST
                                      where OID = ?",
                                    array( array( 'i', $workId )));

        foreach( $template->resultset as $value )
            self::logAction( $db, $actionId, $value['TEMPLATE_ID'], $user, $workId . $message, $value['TEMPLATE_EXE_ID'] );
    }
}

?>
