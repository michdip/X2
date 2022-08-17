<?php

class maintenance
{
    public static $weekDays = array( array( 'name' => 'Datum',      'value' => -1 ),
                                     array( 'name' => 'Montag',     'value' => 2 ),
                                     array( 'name' => 'Dienstag',   'value' => 3 ),
                                     array( 'name' => 'Mittwoch',   'value' => 4 ),
                                     array( 'name' => 'Donnerstag', 'value' => 5 ),
                                     array( 'name' => 'Freitag',    'value' => 6 ),
                                     array( 'name' => 'Samstag',    'value' => 7 ),
                                     array( 'name' => 'Sonntag',    'value' => 1 ));

    public static function getMaintenance( $db )
    {
        $maintenance = $db->dbRequest( "select case when MAINTENANCE_DAY = 1 then 'Sonntag'
                                                    when MAINTENANCE_DAY = 2 then 'Montag'
                                                    when MAINTENANCE_DAY = 3 then 'Dienstag'
                                                    when MAINTENANCE_DAY = 4 then 'Mittwoch'
                                                    when MAINTENANCE_DAY = 5 then 'Donnerstag'
                                                    when MAINTENANCE_DAY = 6 then 'Freitag'
                                                    when MAINTENANCE_DAY = 7 then 'Samstag'
                                                    else ''
                                                end WDAY,
                                               date_format( MAINTENANCE_DATE, '%d.%m.%Y') MAINTENANCE_DATE,
                                               date_format( MAINTENANCE_START_TIME, '%H:%i' ) START,
                                               date_format( MAINTENANCE_END_TIME, '%H:%i' ) ENDE,
                                               OID
                                          from X2_MAINTENANCE_TIME" );

        return $maintenance->resultset;
    }

    public static function createMaintenanceTime( $db, $wDay, $wStart, $wEnd, $wDate )
    {
        if( $wDay == -1 )
        {
            $wDay = null;
            $wDateStr = $wDate;
            $wDateFormat = "str_to_date( ?, '%d.%m.%Y' )";
        }
        else
        {
            $wDateStr = null;
            $wDateFormat = '?';
        }

        $db->dbRequest( "insert into X2_MAINTENANCE_TIME (MAiNTENANCE_DAY,
                                                          MAINTENANCE_DATE,
                                                          MAINTENANCE_START_TIME,
                                                          MAINTENANCE_END_TIME)
                         values ( ?, " . $wDateFormat . ", ?, ? )",
                        array( array( 'i', $wDay ),
                               array( 's', $wDateStr ),
                               array( 's', $wStart ),
                               array( 's', $wEnd )));

        $db->commit( );
    }

    public static function updateMaintenanceTime( $db, $oid, $wDay, $wStart, $wEnd, $wDate )
    {
        if( $wDay == -1 )
        {
            $wDay = null;
            $wDateStr = $wDate;
            $wDateFormat = "str_to_date( ?, '%d.%m.%Y' )";
        }
        else
        {
            $wDateStr = null;
            $wDateFormat = '?';
        }

        $db->dbRequest( "update X2_MAINTENANCE_TIME
                            set MAINTENANCE_DAY = ?,
                                MAINTENANCE_DATE = " . $wDateFormat . ",
                                MAINTENANCE_START_TIME = ?,
                                MAINTENANCE_END_TIME =?
                          where OID = ?",
                        array( array( 'i', $wDay ),
                               array( 's', $wDateStr ),
                               array( 's', $wStart ),
                               array( 's', $wEnd ),
                               array( 'i', $oid )));

        $db->commit( );
    }

    public static function deleteMaintenanceTime( $db, $oid )
    {
        $db->dbRequest( "delete
                           from X2_MAINTENANCE_TIME
                          where OID = ?",
                        array( array( 'i', $oid )));

        $db->commit( );
    }
}

?>
