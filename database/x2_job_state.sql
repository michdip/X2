CREATE TABLE X2_JOB_STATE
(
    JOB_STATE       INT          NOT NULL,
    JOB_NAME        VARCHAR(20)  NOT NULL,
    JOB_ICON        varchar(10)  NOT NULL,
    JOB_COLOR       VARCHAR(10)  NOT NULL,
    SET_OK_BY_USER  TINYINT      NOT NULL,
    SET_OK_BY_ADMIN TINYINT      NOT NULL,
    IS_KILLABLE     TINYINT      NOT NULL,
    IS_RESTARTABLE  TINYINT      NOT NULL,
    IS_EDITABLE     TINYINT      NOT NULL,
    STATE_ORDER     INT          NOT NULL,
    CONSTRAINT PK_X2_JOB_STATE PRIMARY KEY (JOB_STATE)
);

CREATE UNIQUE INDEX IND_X2_JOB_STATE_NMN ON X2_JOB_STATE (JOB_NAME);

INSERT INTO X2_JOB_STATE (JOB_STATE,
                          JOB_NAME,
                          JOB_ICON,
                          JOB_COLOR,
                          SET_OK_BY_USER,
                          SET_OK_BY_ADMIN,
                          IS_KILLABLE,
                          IS_RESTARTABLE,
                          IS_EDITABLE,
                          STATE_ORDER)
values (  0, 'CREATED',      '&#xe109;', 'black',     1, 0, 0, 0, 1, 0 ),
       (  1, 'READY_TO_RUN', '&#xe023;', 'black',     1, 0, 0, 0, 1, 0 ),
       (  2, 'DEAMONIZED',   '&#xe031;', 'blue',      0, 1, 0, 0, 0, 3 ),
       (  3, 'RUNNING',      '&#xe031;', 'blue',      0, 1, 1, 0, 0, 3 ),
       (  4, 'RUNNING_6',    '&#xe031;', 'purple',    0, 1, 1, 0, 0, 3 ),
       (  5, 'OK',           '&#xe125;', 'darkgreen', 0, 0, 0, 0, 0, 5 ),
       (  6, 'OK_BY_USER',   '&#xe125;', 'orange',    0, 0, 0, 0, 0, 5 ),
       (  7, 'ERROR',        '&#xe104;', 'red',       1, 0, 0, 1, 0, 7 ),
       (  8, 'RETRY',        '&#xe104;', 'red',       1, 0, 0, 0, 0, 7 ),
       (  9, 'WAIT_4_REF',   '&#xe031;', 'blue',      0, 0, 0, 0, 0, 3 ),
       ( 10, 'RUNNING_REF',  '&#xe031;', 'blue',      0, 0, 0, 0, 0, 3 ),
       ( 11, 'ERROR_REF',    '&#xe104;', 'red',       0, 0, 0, 0, 0, 7 );
