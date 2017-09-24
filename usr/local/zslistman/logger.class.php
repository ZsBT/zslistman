<?php	/*

  simple class to log somewhere
  
  */


abstract class logger {

  static public function info($msg, $trace=true, $debug=false){
    if(!is_string($msg))$msg = json_encode($msg);
    $msg = str_replace("\n", "\\n", $msg);
    
    $line = date("Y-m-d H:i:s ")."{ $msg } ";
    
    if( isset($_SERVER["REMOTE_ADDR"]) )$line.=sprintf("<%s>", $_SERVER["REMOTE_ADDR"]);
    
    if($trace){
      $trca = debug_backtrace();

      foreach($trca as $trc){
        $ob = (object)$trc;
        $fun = $ob->function;
        
        if($ob->file == __FILE__ ) continue;
        
        if($debug)$fun.= json_encode($ob->args);else $fun.="()";
        $fun.=sprintf("@%s:{$ob->line}",basename($ob->file));
        
        $line.= "$fun; ";
        break;
      }
    }
    
    // here you can set any other output you would like
    file_put_contents("php://stderr", "$line\n");
    zslist::db()->insert("log",array("message"=>$msg));
  }
  

  static public function error($msg){
    if(!is_string($msg))$msg = json_encode($msg);
    logger::info("ERROR: $msg");
    return false;
  }
  
  
  static public function warn($msg){
    if(!is_string($msg))$msg = json_encode($msg);
    logger::info("WARNING: $msg");
    return $msg;
  }
  
  
  
}

