CREATE TABLE X2_ACTIONLOG
(
    OID              INT UNSIGNED                NOT NULL AUTO_INCREMENT,
    TEMPLATE_ID      INT                         NOT NULL,
    TEMPLATE_EXE_ID  INT,
    ACTION_ID        INT                         NOT NULL,
    ACTION_TEXT      VARCHAR(500),
    X2_USER          VARCHAR(255)                NOT NULL,
    CTS              DATETIME     DEFAULT NOW()  NOT NULL,
    CONSTRAINT PK_X2_ACTIONLOG PRIMARY KEY (OID)
);

CREATE INDEX IND_X2_ACTIONLOG_TID ON X2_ACTIONLOG (TEMPLATE_ID);
