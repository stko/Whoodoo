PRAGMA foreign_keys=OFF;
BEGIN TRANSACTION;
CREATE TABLE whoodoo_users (
			id INTEGER PRIMARY KEY, 
			username VARCHAR( 50 ) NOT NULL,
			firstname TEXT, 
			lastname TEXT,
			state INTEGER );
INSERT INTO whoodoo_users VALUES(1,'foo','Any','Nobody',1);
INSERT INTO whoodoo_users VALUES(2,'foo2','Alice','Smith',1);
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
			ownerid INTEGER NOT NULL,
			startdate INTEGER NOT NULL,
			enddate INTEGER NOT NULL,
			duration INTEGER NOT NULL,
			ismilestone INTEGER NOT NULL,
			title VARCHAR( 200 ) NOT NULL,
			content TEXT,
			validated INTEGER NOT NULL,
			state INTEGER NOT NULL);
CREATE TABLE whoodoo_edgelist (
			id INTEGER PRIMARY KEY, 
			workzoneid INTEGER NOT NULL,
			fromjobid INTEGER NOT NULL,
			tojobid INTEGER NOT NULL,
			state INTEGER NOT NULL);
CREATE TABLE whoodoo_changelog (
			id INTEGER PRIMARY KEY, 
			jobid INTEGER NOT NULL,
			timestamp INTEGER NOT NULL,
			userid INTEGER NOT NULL,
			jobowner INTEGER NOT NULL,
			predecessorState INTEGER NOT NULL,
			validated INTEGER NOT NULL,
			changetype INTEGER NOT NULL,
			content TEXT,
			state INTEGER NOT NULL);
CREATE TABLE whoodoo_statecodes (
			id INTEGER PRIMARY KEY, 
			statename VARCHAR( 30 ) NOT NULL,
			statecolor VARCHAR( 30 ) NOT NULL,
			statecolorcode VARCHAR( 10 ) NOT NULL,
			state INTEGER NOT NULL);
INSERT INTO whoodoo_statecodes VALUES(1,'Requested',"Gainsboro","#DCDCDC",0);
INSERT INTO whoodoo_statecodes VALUES(2,'Done',"Lime","#00FF00",1);
INSERT INTO whoodoo_statecodes VALUES(3,'In Work',"Aqua","#00FFFF",2);
INSERT INTO whoodoo_statecodes VALUES(4,'Rework',"Gold","#FFD700",3);
INSERT INTO whoodoo_statecodes VALUES(5,'Unclear',"Orange","#FFA500",4);
INSERT INTO whoodoo_statecodes VALUES(6,'Faulty',"OrangeRed","#FF4500",5);
INSERT INTO whoodoo_statecodes VALUES(7,'Ignore',"Magenta","#FF00FF",6);
			

COMMIT;
