<?php

require_once("mhack.class.php");
require_once("zsPDO.class.php");
require_once("dict.class.php");

##if(!defined("ZSLIST_LISTNAME"))throw new Exception("No configuration");

abstract class zslist {
  
  static function db(){
    $db = new zsPDO(ZSLIST_DB, '','', array(PDO::ATTR_PERSISTENT => true) );
    if(!$db)throw new Exception("DB error: ".ZSLIST_DB);
    return $db;
  }
  

  static function subscribe($email, $name=''){	// subscribe
    $db = self::db();
    
    if(self::blocked($email)){
      logger::warn("$email blocked, cannot re-subscribe");
      return false;
    }
    
    if(self::subscribed($email)){
      logger::warn("$email already subscribed");
      return false;
    }
    
    if(!$db->insert("person",array("email"=>strtolower($email),"name"=>$name)))return
      logger::error( $db->lastError() );
    
    logger::info("$email subscribed"); 
    self::notifyadmin( mhack::decode($name)." <$email> subscribed");
    if($name)self::sysmail($email, sprintf(dict::get("subscribed"),ZSLIST_BLOCK_ADDR) );
    return true;
  }
  
  
  static function unsubscribe($email){	// unsubscribe
    $db = self::db();
    
    if(self::blocked($email)){
      logger::warn("$email blocked, cannot unsubscribe");
      return false;
    }
    
    if(!self::subscribed($email)){
      logger::warn("$email not subscribed");
      return false;
    }
    
    if(!$db->exec("delete from person where email='$email'"))
      return logger::error( $db->lastError() );

    logger::info("$email unsubscribed");
    self::notifyadmin("$name <$email> unsubscribed");
    
    self::sysmail($email, dict::get("unsubscribed") );
    return true;
  }
  
  
  static function block($email){	// block email
    if( self::db()->exec("update person set blocked=1 where email='$email'") ){
      logger::info("$email blocked");
      self::sysmail($email, sprintf(dict::get("blocked"),ZSLIST_ADMIN_ADDR) );
      return true;
    }else{
      logger::warn("$email block FAILED");
    }
    return false;
  }
  
  
  static function blocked($email){	// is blocked?
    return 0 + self::db()->oneValue("select blocked from person where email='$email'");
  }
  
  
  static function subscribed($email){	// is subscribed?
    return self::db()->oneValue("select count(1) from person where email=lower('$email')");
  }
  
  
  static function memberlist($email){
    $mlist = [];
    self::db()->iterate("select * from person where blocked=0 order by creats desc", function($rec) use (&$mlist) {
      $mlist[] = sprintf("%s: %s <%s>", $rec->creats, mhack::decode($rec->name), $rec->email );
    });
    self::sysmail($email, sprintf(dict::get("memberlist"), implode("\n", $mlist)));
  }
  
  

  static function sysmail($to, $body){	// send system message
    return mail($to, mb_encode_mimeheader(sprintf(dict::get("maillist"), ZSLIST_LISTNAME)), $body
      ,"From: ".ZSLIST_LISTNAME." lev.lista <".ZSLIST_FROM_ADDR.">\r\n"
      ."Content-Type: text/plain; charset=utf-8\r\n"
      ,"-t ".ZSLIST_FROM_ADDR
      );
  }
  
  
  static function notifyadmin($body){
    self::db()->iterate("select * from person where admin=1", function($rec)use($body){
      self::sysmail($rec->email, $body);
    }); 
  }
  

  static function incoming(&$raw){	// process incoming messages
    $head = mhack::explode($raw)->head;
    
    if(!preg_match("/from ([^\s]+)/i", $head,$ma))
      return logger::warn("no 'from' in header");
    
    $from = strtolower(trim($ma[1]));
    
    if(ZSLIST_LOCKED)return self::sysmail($from, dict::get("locked") );
    
    if(defined("ZSLIST_TRACEDIR")){	// save message
      $path = ZSLIST_TRACEDIR.date("/Y/m/d");
      if(!file_exists($path))
      if(!mkdir($path, 0775, true))
        logger::error("Cannot create directory $path");
      $uid = uniqid();
      if(!file_put_contents($fn="$path/$uid,$from", $raw))logger::error("Cannot write file $fn");
    }

    // parse name/email
    $name='';
    if(preg_match("/^from: ([^<]+)<([^>]+)/sim",$head,$ma)){
      $name = trim($ma[1]);
      $email = strtolower(trim($ma[2]));
    }else $email=$from;
    
    if($from=='nobody@zsombor.net')return;

    // a command?
    if(preg_match("/^delivered-to: ([^@]+)/sim", $head,$ma)
      && (list($listname,$command) = explode("+",$ma[1]))
      && $command
      )switch(strtolower($command)){
      
      case "subscribe":
        if(ZSLIST_PRIVATE)return self::sysmail("$name <$email>", sprintf(dict::get("private"),ZSLIST_ADMIN_ADDR) );
        self::subscribe($email,$name);
      
      case "help":
        self::sysmail("$name <$email>", dict::get("help") );
        return true;
      
      case "unsubscribe":
        self::unsubscribe($email,$name);
        return true;
      
      case "block":
        self::block($email);
        return true;
      
      case "vote":
        self::vote($email, $head);
        self::notifyadmin( mhack::decode($name)." <$email> voted");
        return true;
      
      case "votings":
        self::send_votings($email);
        return true;
      
      case "memberlist":
        self::memberlist($email);
        return true;
      
      default:
        logger::warn("ismeretlen parancs: $command");
        return false;
    }
    
    // send to members?
    if(self::blocked($from)){
      logger::warn("$from blocked, cannot post");
      return self::sysmail($from, sprintf(dict::get("blocked"),ZSLIST_ADMIN_ADDR) );
    }
    
    if(!self::subscribed($from)){
      logger::warn("$from not subscribed");
      return self::sysmail($from, dict::get("not_subscribed"));
    }
    
    logger::info("$from posted to list");
    self::sendbulk($raw);
  }
  
  
  static function sendbulk(&$raw){	// send this to everyone

    $outmail = mhack::rebuild($raw, array(), ZSLIST_FOOTER_TEXT, ZSLIST_FOOTER_HTML);
    
#    print_R($outmail);exit;
    
    $iter = function($person) use ($outmail) {
      $sr = mail("{$person->name} <{$person->email}>", $outmail["subj"], $outmail["body"], $outmail["head"] );
      if($sr)logger::info("mail sent to {$person->email}");else
      logger::error("mail to {$person->email} FAILED");
    };
    
    self::db()->iterate("select * from person where blocked=0", $iter);
  }
  
  
  static function vote($from, &$head){
  
    if(self::blocked($from)){
      logger::warn("$from blocked, cannot vote");
      return self::sysmail($from, sprintf(dict::get("blocked"),ZSLIST_ADMIN_ADDR) );
    }
    
    $db = self::DB();
    
    $voting = $db->oneRow("select * from voting where CURRENT_TIMESTAMP between fromts and tots");
    if(!$voting){
      logger::warn("$from : no open voting");
      return self::sysmail($from, dict::get("no_open_vote"));
    }
    
    if(!preg_match("/^subject: ([^\r\n]+)/sim", $head, $ma)){
      logger::warn("$from vote : empty subject");
      return self::sysmail($from, dict::get("invalid_vote"));
    }
    
    $subject = strtolower(imap_mime_header_decode($ma[1])[0]->text);
    
    $vote = false;
    foreach( json_decode(strtolower($voting->selections)) as $selection )
      if(preg_match("/\b$selection\b/", $subject))
        $vote = $selection;
    
    if(!$vote){
      logger::warn("$from vote invalid: [$subject]");
      return self::sysmail($from, dict::get("invalid_vote"));
    }
    
    if($db->oneValue("select count(1) from votes where email='$from' and votid={$voting->id}")){
      logger::warn("$from already voted");
      return self::sysmail($from, dict::get("already_voted"));
    }
    
    if($db->insert("votes", array("votid"=>$voting->id, "vote"=>$vote, "email"=>$from ))){
      logger::info("$from voted [$vote]");
      return self::sysmail($from, dict::get("vote_succ"));
    }else{
      logger::warn("$from voted [$vote] ".$db->lastError());
      return self::sysmail($from, dict::get("vote_err"));
    }
  }
  
   
  static function send_votings($from){	// send list of votings
    $db = self::DB();
    
    $body = dict::get("votings")."\n\n";
    foreach($db->allRow("select id, name, tots, selections from voting")as $voting)$body.=sprintf( dict::get("votings_vote")."\n\n"
      ,$voting->tots, $voting->name, implode(", ", json_decode($voting->selections))
      ,implode(", ", $db->oneCol("select vote||':'||count(1) from votes where votid={$voting->id} group by vote") )
    );
    
    logger::info("$from listed votings");
    return self::sysmail($from, $body);
  }
  
  
  static function close_votings(){	// when a voting ends, send results
    
  }
  
  
}
