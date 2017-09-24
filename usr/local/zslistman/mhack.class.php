<?php

require_once("logger.class.php");
require_once("zsPDO.class.php");

openlog("zsl-mhack", LOG_PID, LOG_USER);

abstract class mhack {

  static function explode(&$raw){	// divide into header + body parts
    
    $raw = @str_replace("\r\n","\n", $raw);

    $delimpos = strpos($raw, "\n\n");
    $header = @substr($raw,0,$delimpos);
    $body = @substr($raw,2+$delimpos);
    
    $header = @str_replace("\n\t", " ", $header);
    
    if(preg_match("/References: ([^\s]+)/sim", $header, $ma))$thread = $ma[1];
    if(preg_match("/Thread-Index: ([^\s]+)/sim", $header, $ma))$thread = $ma[1];
      
    if(preg_match("/content-type.+ boundary=([^\s]+)/is",str_replace('"','',$header),$ma)){
      $boundary = $ma[1];
      $parts = @explode("--$boundary", $body);
      array_shift($parts);
      array_pop($parts);
    }
    
    if(preg_match("/content-type: ([^\s]+; [^\s]+)/sim", $header, $ma))
      $ContentType = $ma[1];
    else
    if(preg_match("/content-type: ([^\s]+)/sim", $header, $ma))
      $ContentType = $ma[1];
      
    $reta = array(
      "head"	=>&$header,
      "body"	=>isset($parts) ? NULL : $body,
      "parts"	=>&$parts,
      "boundary"=>$boundary,
      "ctype"	=>trim($ContentType),
      "thread"	=>$thread,
    );

    return (object)$reta;
  }
  
  
  static function ctype(&$s){	// detect content-type
    if(preg_match("/^content-type: ([^s;]+)/sim", @str_replace('"','',@str_replace("'",'',$s)) , $ma) )
      return strtolower($ma[1]);
    return false;
  }


  // prepare outgoing email
  static function rebuild(&$raw, $headers=array(), $texfooter="", $htmlfooter=""){	
    $expl = @mhack::explode($raw);
    
    if($texfooter && strpos($raw,$texfooter))$texfooter='';
    if($htmlfooter && strpos($raw,$htmlfooter))$htmlfooter='';
    
    if(preg_match("/^from:([^<]+)/sim", $expl->head,$ma))$fromname = trim($ma[1]);else
    if(preg_match("/^from:.+[ <]([^@]+)/sim", $expl->head,$ma))$fromname = $ma[1];else
    return logger::error("felado ismeretlen!");
    
    if(preg_match("/^from: ([^\n]+)/sim", $expl->head,$ma))$fromline = trim($ma[1]);
    
    
    $headers[] = "From: $fromname <".ZSLIST_FROM_ADDR.">";
    
    if($expl->boundary && !preg_match("/boundary/i", $expl->ctype))
      $headers[] = "Content-Type: {$expl->ctype} boundary=\"{$expl->boundary}\"";
    else
      $headers[] = "Content-Type: {$expl->ctype}";
    
    if(preg_match("/^Content-Transfer-Encoding: ([^\r\n]+)/sim", $expl->head,$ma))
      $headers[] = "Content-Transfer-Encoding: ".$ma[1];
      
    if($expl->thread) $headers[] = "Thread-Index: ".$expl->thread;
    
    if(preg_match("/^subject: ([^\r\n]+)/sim", $expl->head, $ma))
      $subject = imap_mime_header_decode($ma[1])[0]->text;
    else $subject = "(nincs tárgy)";
    
    $sprefx = "[".ZSLIST_LISTNAME."]";
    
    $subject = str_replace($sprefx, "", $subject);
    $subject = "$sprefx ".trim(str_replace("  "," ",$subject));

    syslog(LOG_INFO, "subject elotte: $subject");
    
    $subject = str_replace("Re: ", "", $subject);
    $subject = str_replace("RE: ", "", $subject);
    $subject = str_replace("Fwd: ", "", $subject);

    syslog(LOG_INFO, "subject utana: $subject");
    
    if(!count($expl->parts)){	// plain message
      $expl->body.="\n\n$texfooter";
    } else {	// multipart message
      $expl->body = "";
      foreach($expl->parts as $i=>$part){
      switch($ctype=mhack::ctype($part)){
          case "text/plain":
            $expl->parts[$i].="\n$texfooter";
            break;
          case "text/html":
            $expl->parts[$i].="<div>$htmlfooter</div>";
            break;
          default:
            echo "unhandled type: $ctype\n";
        }
        $expl->body .= "--{$expl->boundary}{$expl->parts[$i]}\r\n";
      }
      $expl->body .= "--{$expl->boundary}--\r\n\r\n";
    }
    
    $headers[] = "Precedence: list";
    $headers[] = "X-BeenThere: ".ZSLIST_SENDER_ADDR;
    $headers[] = "Sender: <".ZSLIST_SENDER_ADDR.">";
    $headers[] = "Reply-To: ".ZSLIST_FROM_ADDR;
    $headers[] = sprintf("List-ID: <%s.%s>", ZSLIST_LISTNAME, ZSLIST_DOMAIN);
    $headers[] = "List-Unsubscribe: <mailto:".ZSLIST_UNSUB_ADDR.">";
    $headers[] = "List-Help: <mailto:".ZSLIST_HELP_ADDR.">";
    $headers[] = "List-Post: <mailto:".ZSLIST_FROM_ADDR.">";
    $headers[] = "Errors-To: ".ZSLIST_ADMIN_ADDR;
    $headers[] = "X-AntiAbuse: Please notify admin person at ".ZSLIST_ADMIN_ADDR;
    $headers[] = "Return-Path: <".ZSLIST_BOUNCE_ADDR.">";
    $headers[] = "Date: ".str_replace("-","+",date("r"));
#    $headers[] = "X-Mailer: Zsombor MailList php class!";
    
    return array(
      "head"	=>implode("\r\n", $headers),
      "subj"	=>$subject,
      "body"	=>&$expl->body,
    );
    
  }
  
  
  function decode($encoded){
    return imap_mime_header_decode($encoded)[0]->text;
  }
  
  function encode($decoded){
    return sprintf("=?UTF-8?B?%s?=", base64($decoded) );
  }
  
}
