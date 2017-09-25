CREATE table person(email varchar(50) primary key, name varchar(50), admin smallint default 0, blocked smallint default 0, creats timestamp default CURRENT_TIMESTAMP);
CREATE table log(id integer AUTO_INCREMENT primary key, ts timestamp default CURRENT_TIMESTAMP, message text);
CREATE table voting(id integer AUTO_INCREMENT primary key, name varchar(100) not null, fromts timestamp not null default CURRENT_TIMESTAMP, tots timestamp not null, selections text);
CREATE table votes( votid integer not null, vote varchar(50) not null, email varchar(50) not null, votets timestamp default CURRENT_TIMESTAMP, primary key(votid,email) );
