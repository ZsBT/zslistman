#!/usr/bin/php
<?php

// process incoming emails

require_once("conf/list.php");
require_once("zslistman/zslist.class.php");
zslist::incoming( @file_get_contents("php://stdin") );

