CREATE TABLE X2_ACTION
(
    OID         INT          NOT NULL,
    DESCRIPTION VARCHAR(255) NOT NULL,
    CONSTRAINT PK_X2_ACTION PRIMARY KEY (OID)
);

INSERT INTO X2_ACTION (OID, DESCRIPTION) VALUES ( 1, 'Das Template wurde angelegt (Typ / Name)' );
INSERT INTO X2_ACTION (OID, DESCRIPTION) VALUES ( 2, 'Dem Template wurde ein Vater zugeordnet (Parent ID)' );
INSERT INTO X2_ACTION (OID, DESCRIPTION) VALUES ( 3, 'Ein Starter wurde erstellt' );
INSERT INTO X2_ACTION (OID, DESCRIPTION) VALUES ( 4, 'Das Template wurde umbenannt (Name)' );
INSERT INTO X2_ACTION (OID, DESCRIPTION) VALUES ( 5, 'Beschreibung des Templates geändert ( Beschreibung )' );
INSERT INTO X2_ACTION (OID, DESCRIPTION) VALUES ( 6, 'Beschreibung des Templates gelöscht' );
INSERT INTO X2_ACTION (OID, DESCRIPTION) VALUES ( 7, 'Die Startzeit wurde entfernt' );
INSERT INTO X2_ACTION (OID, DESCRIPTION) VALUES ( 8, 'Die Startzeit wurde geändert' );
INSERT INTO X2_ACTION (OID, DESCRIPTION) VALUES ( 9, 'Der Job wurde angelegt (ID)' );
INSERT INTO X2_ACTION (OID, DESCRIPTION) VALUES (10, 'Der Job wurde verlinkt (QuellId / ZielId)' );
INSERT INTO X2_ACTION (OID, DESCRIPTION) VALUES (11, 'Der Job-Command wurde geändert (ID / Host / Source / Pfad / Befehl)' );
INSERT INTO X2_ACTION (OID, DESCRIPTION) VALUES (12, 'Der Job wurde gelöscht (ID)' );
INSERT INTO X2_ACTION (OID, DESCRIPTION) VALUES (13, 'Der Breakpoint wurde gesetzt auf (ID / Wert)' );
INSERT INTO X2_ACTION (OID, DESCRIPTION) VALUES (14, 'Der Link wurde gelöscht (QuellId / ZielId)' );
INSERT INTO X2_ACTION (OID, DESCRIPTION) VALUES (15, 'Der Job wurde geändert (Typ / Name)' );
INSERT INTO X2_ACTION (OID, DESCRIPTION) VALUES (16, 'Der Typ des Job wurde geändert (OID / Typ)' );
INSERT INTO X2_ACTION (OID, DESCRIPTION) VALUES (17, 'Der Job-StartTemplate wurde geändert (OID / Template / StartMode / VarMode / RunMode)' );
INSERT INTO X2_ACTION (OID, DESCRIPTION) VALUES (18, 'Es wurden alle Variablen gelöscht' );
INSERT INTO X2_ACTION (OID, DESCRIPTION) VALUES (19, 'Die Variable wurde angelegt (Name / Wert)' );
INSERT INTO X2_ACTION (OID, DESCRIPTION) VALUES (20, 'Die Variable wurde gelöscht (OID / Name / Wert)' );
INSERT INTO X2_ACTION (OID, DESCRIPTION) VALUES (21, 'Das Recht wurde gesetzt (Objekt / Gruppe / Recht)' );
INSERT INTO X2_ACTION (OID, DESCRIPTION) VALUES (22, 'Das Recht wurde entfernt (Objekt / Gruppe / Recht)' );
INSERT INTO X2_ACTION (OID, DESCRIPTION) VALUES (23, 'Es wurden alle Notifier gelöscht' );
INSERT INTO X2_ACTION (OID, DESCRIPTION) VALUES (24, 'Der Notifier wurde angelegt (Status / Empfänger)' );
INSERT INTO X2_ACTION (OID, DESCRIPTION) VALUES (25, 'Das Template wurde gelöscht' );
INSERT INTO X2_ACTION (OID, DESCRIPTION) VALUES (26, 'Der Job-StartTemplate wurde geändert (OID / Template)' );
INSERT INTO X2_ACTION (OID, DESCRIPTION) VALUES (27, 'Änderung des Status (Name)' );
INSERT INTO X2_ACTION (OID, DESCRIPTION) VALUES (28, 'Die Ausführung des Templates wurde abgebrochen' );
INSERT INTO X2_ACTION (OID, DESCRIPTION) VALUES (29, 'Das Template wurde zur Ausführung gebracht' );
INSERT INTO X2_ACTION (OID, DESCRIPTION) VALUES (30, 'Die PID des Jobs wurde gesetzt (ID / PID)' );
INSERT INTO X2_ACTION (OID, DESCRIPTION) VALUES (31, 'Der Billit-Adapter wurde geändert (ID / Modul / Instanz / min Messages / Erzeuger )' );
