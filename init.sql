PRAGMA foreign_keys=OFF;
BEGIN TRANSACTION;
CREATE TABLE whoodoo_users (
			id INTEGER PRIMARY KEY, 
			username VARCHAR( 50 ) NOT NULL,
			firstname TEXT, 
			lastname TEXT,
			state INTEGER );
INSERT INTO whoodoo_users VALUES(1,'foo','Alice','Smith',1);
CREATE TABLE whoodoo_workzone (
			id INTEGER PRIMARY KEY, 
			name VARCHAR( 200 ) NOT NULL );
INSERT INTO whoodoo_workzone VALUES(1,'customer.project');
INSERT INTO whoodoo_workzone VALUES(2,'customer.project.build');
INSERT INTO whoodoo_workzone VALUES(3,'customer.project.build.event');
CREATE TABLE whoodoo_jobnames (
			id INTEGER PRIMARY KEY, 
			name VARCHAR( 200 ) NOT NULL );
CREATE TABLE whoodoo_joblist (
			id INTEGER PRIMARY KEY, 
			workzoneid INTEGER NOT NULL,
			jobnameid INTEGER NOT NULL,
			userid INTEGER NOT NULL,
			content TEXT,
			state INTEGER NOT NULL);
CREATE TABLE whoodoo_edgelist (
			id INTEGER PRIMARY KEY, 
			workzoneid INTEGER NOT NULL,
			fromjobid INTEGER NOT NULL,
			tojobid INTEGER NOT NULL,
			state INTEGER NOT NULL);
			

COMMIT;
