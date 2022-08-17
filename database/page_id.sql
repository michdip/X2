CREATE TABLE PAGE_ID
(
    PAGE_ID          INT                         NOT NULL,
    X2_USER          VARCHAR(50)                 NOT NULL,
    CTS              DATETIME      DEFAULT NOW() NOT NULL,
    CONSTRAINT PK_USED_PAGE_ID PRIMARY KEY (PAGE_ID)
);
