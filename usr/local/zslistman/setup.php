#!/usr/bin/env php
<?php	/*			source: https://github.com/ZsBT/zslistman
    
    setup.php
    
    - initializes & sets proper rights of the mail list
    - run as root
    - run in your list folder or give the folder as an argument
    
*/


$localadmin = "zsombor";
$mailowner = "nobody";
$listdir = ".";

if("root"!=getenv("USER"))fatal("run me as root");

if( isset($argv[1]) ) $listdir=$argv[1];

if(!file_exists($listdir))fatalne($listdir);
chdir($listdir);
if(!file_exists($confn="$listdir/conf/list.php"))fatalne($confn);
require_once($confn);
require_once("zslistman/zslist.class.php");
$N=ZSLIST_LISTNAME;


# create db
$DB = zslist::DB();
foreach(explode(";",file_get_contents(__DIR__."/sqlite3.db.schema")) as $line)
    if($line=trim($line))
        $DB->exec($line);


#add aliases
if(!preg_match("/^$N:/im", file_get_contents("/etc/aliases"),$ma)){
    foreach( ["$N: \"|/var/local/zslists/$N/proc.php\"","$N-admin: $localadmin","$N-owner: $localadmin","$N-bounces: $localadmin"] as $line)
        file_put_contents("$line\n", "/etc/aliases","a");
    echo "run command 'newaliases'\n";
}


# set permissions
if(!file_exists("trace"))mkdir("trace", 0775);
foreach(["trace","db","db/sqlite3.db"] as $fn){
    chown($fn,$mailowner);
    chmod($fn,0775);
}


function fatal($msg){echo "$msg\n";exit(1);}
function fatalne($file){fatal("$file does not exist");}
