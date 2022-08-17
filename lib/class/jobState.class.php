<?php

class jobState
{
    public static function jobStateArray( $db, $keyName )
    {
        $jobStates = array( );

        $states = $db->dbRequest( "select JOB_STATE,
                                          JOB_NAME,
                                          JOB_ICON,
                                          JOB_COLOR,
                                          SET_OK_BY_USER,
                                          SET_OK_BY_ADMIN,
                                          IS_KILLABLE,
                                          IS_RESTARTABLE,
                                          IS_EDITABLE
                                     from X2_JOB_STATE" );

        foreach( $states->resultset as $state )
            $jobStates[ $state[ $keyName ]] = array( 'id'           => $state['JOB_STATE'],
                                                     'name'         => $state['JOB_NAME'],
                                                     'icon'         => $state['JOB_ICON'],
                                                     'color'        => $state['JOB_COLOR'],
                                                     'setOkbyUser'  => ( $state['SET_OK_BY_USER']  == 1 ),
                                                     'setOkbyAdmin' => ( $state['SET_OK_BY_ADMIN'] == 1 ),
                                                     'killable'     => ( $state['IS_KILLABLE']     == 1 ),
                                                     'restartable'  => ( $state['IS_RESTARTABLE']  == 1 ),
                                                     'isEditable'   => ( $state['IS_EDITABLE']     == 1 ));

        return $jobStates;
    }

    public static function getJobStates( $db )
    {
        define( 'JOB_STATES', jobState::jobStateArray( $db, 'JOB_NAME' ));
    }

    public static function getReverseJobStates( $db )
    {
        define( 'REVERSE_JOB_STATES', jobState::jobStateArray( $db, 'JOB_STATE' ));
    }
}

?>
