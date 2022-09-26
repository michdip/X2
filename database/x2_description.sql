CREATE TABLE X2_DESCRIPTION
(
    OBJECT_TYPE ENUM( 'TEMPLATE', 'JOB' ) NOT NULL,
    OBJECT_ID   INT                       NOT NULL,
    DESCRIPTION VARCHAR(8000),
    OWNER       VARCHAR(255),
    CONSTRAINT PK_X2_DESCRIPTION PRIMARY KEY (OBJECT_TYPE, OBJECT_ID)
);
