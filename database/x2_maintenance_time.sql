create table X2_MAINTENANCE_TIME
(
    OID                    int   auto_increment not null,
    MAINTENANCE_DATE       date                     null,
    MAINTENANCE_DAY        int                      null,
    MAINTENANCE_START_TIME time                 not null,
    MAINTENANCE_END_TIME   time                 not null,
    constraint primary key (OID)
);
