<?php

define("ZSLIST_LISTNAME", "proba");
define("ZSLIST_DOMAIN", "zsombor.net");
define("ZSLIST_HOST", "lists.zsombor.net");

define("ZSLIST_FOOTER_TEXT", "---\r\nleiratkozas: <".ZSLIST_LISTNAME."+unsubscribe@".ZSLIST_HOST.">");
define("ZSLIST_FOOTER_HTML", "leiratkozas: ".ZSLIST_LISTNAME."+unsubscribe@".ZSLIST_HOST);

define("ZSLIST_DICT", __DIR__."/dict.json");
define("ZSLIST_DB", "mysql:hostname=localhost;dbname=zslist_proba");
define("ZSLIST_DBUSER","zslistman");
define("ZSLIST_DBPASS","almafa");
define("ZSLIST_TRACEDIR", __DIR__."/../trace");

define("ZSLIST_PRIVATE", 0);
define("ZSLIST_LOCKED", 0);

date_default_timezone_set("Europe/Budapest");
error_reporting(E_ERROR+E_WARNING);
mb_internal_encoding("UTF-8");

/* --- */
define("ZSLIST_FROM_ADDR", ZSLIST_LISTNAME.'@'.ZSLIST_DOMAIN);
define("ZSLIST_SENDER_ADDR", ZSLIST_LISTNAME.'@'.ZSLIST_HOST);
define("ZSLIST_SUB_ADDR", ZSLIST_LISTNAME.'+subscribe@'.ZSLIST_HOST);
define("ZSLIST_UNSUB_ADDR", ZSLIST_LISTNAME.'+unsubscribe@'.ZSLIST_HOST);
define("ZSLIST_HELP_ADDR", ZSLIST_LISTNAME.'+help@'.ZSLIST_HOST);
define("ZSLIST_BLOCK_ADDR", ZSLIST_LISTNAME.'+block@'.ZSLIST_HOST);
define("ZSLIST_BOUNCE_ADDR", ZSLIST_LISTNAME.'-bounce@'.ZSLIST_HOST);
define("ZSLIST_ADMIN_ADDR", ZSLIST_LISTNAME.'-admin@'.ZSLIST_HOST);

