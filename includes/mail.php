<?php
/* ============================================================================
   MAIL — SMTP via raw socket, fallback mail()
   ========================================================================== */

/* --- Email (SMTP thuần PHP, fallback mail()) --- */
function send_mail($to,$subject,$html){
  global $CFG;
  if($CFG['mail_method']==='mail'){
    $headers="MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n".
             "From: ".$CFG['mail_from_name']." <".$CFG['mail_from'].">\r\n";
    return @mail($to,'=?UTF-8?B?'.base64_encode($subject).'?=',$html,$headers);
  }
  $host=$CFG['smtp_host']; $port=(int)$CFG['smtp_port'];
  $transport=($CFG['smtp_secure']==='ssl'?'ssl://':'').$host;
  $fp=@stream_socket_client("$transport:$port",$e,$es,15);
  if(!$fp) return false;
  $get=function() use($fp){ $d=''; while(($s=fgets($fp,515))!==false){ $d.=$s; if(isset($s[3])&&$s[3]===' ') break; } return $d; };
  $put=function($c) use($fp,$get){ fwrite($fp,$c."\r\n"); return $get(); };
  $get();
  $put("EHLO dogeland");
  if($CFG['smtp_secure']==='tls'){ $put("STARTTLS"); @stream_socket_enable_crypto($fp,true,STREAM_CRYPTO_METHOD_TLS_CLIENT); $put("EHLO dogeland"); }
  $put("AUTH LOGIN"); $put(base64_encode($CFG['smtp_user'])); $r=$put(base64_encode($CFG['smtp_pass']));
  if(strpos($r,'235')===false){ fclose($fp); return false; }
  $put("MAIL FROM:<".$CFG['mail_from'].">");
  $put("RCPT TO:<$to>");
  $put("DATA");
  $msg="From: ".$CFG['mail_from_name']." <".$CFG['mail_from'].">\r\nTo: <$to>\r\n".
       "Subject: =?UTF-8?B?".base64_encode($subject)."?=\r\nMIME-Version: 1.0\r\n".
       "Content-Type: text/html; charset=UTF-8\r\n\r\n".$html."\r\n.";
  $put($msg); $put("QUIT"); fclose($fp);
  return true;
}
