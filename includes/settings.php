<?php
/* ============================================================================
   SETTINGS — dynamic config + promo + pricing
   ========================================================================== */

/* Lấy / lưu cấu hình động (web_settings) */
function dgl_setting($k,$def=null){
  static $cache=null;
  if($cache===null){ $cache=[]; try{ foreach(db()->query("SELECT k,v FROM web_settings")->fetchAll() as $r) $cache[$r['k']]=$r['v']; }catch(Exception $e){} }
  return array_key_exists($k,$cache) ? $cache[$k] : $def;
}
function dgl_set_setting($k,$v){ try{ db()->prepare("INSERT INTO web_settings(k,v) VALUES(?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)")->execute([$k,(string)$v]); }catch(Exception $e){} }
/* Thông tin khuyến mãi đang áp dụng */
function dgl_promo(){
  $pc=(int)dgl_setting('promo_percent',0); $until=dgl_setting('promo_until','');
  $until=$until!==''?(int)$until:0; $active = $pc>0 && ($until===0 || $until>ms());
  return ['percent'=>$pc,'until'=>$until,'active'=>$active];
}
/* Cộng % thưởng khuyến mãi vào số Kim Cương */
function apply_promo_dia($dia){ $p=dgl_promo(); return $p['active'] ? (int)round($dia*(1+$p['percent']/100)) : (int)$dia; }
/* Nạp đè $PACKAGES + tỉ giá từ DB (cho phép admin chỉnh giá) */
function dgl_apply_pricing(){
  global $PACKAGES,$CFG;
  try{
    $rows=db()->query("SELECT * FROM web_packages ORDER BY sort,id")->fetchAll();
    if($rows){ $PACKAGES=[]; foreach($rows as $r){ $PACKAGES[]=['amount'=>(int)$r['amount'],'dia'=>(int)$r['dia'],'xu'=>(int)$r['xu'],'bonus'=>$r['bonus'],'hot'=>!empty($r['hot'])]; } }
  }catch(Exception $e){}
  $map=['vnd_per_diamond'=>'int','custom_min'=>'int','custom_max'=>'int'];
  foreach($map as $k=>$t){ $v=dgl_setting($k,null); if($v!==null && $v!=='') $CFG[$k]=(int)$v; }
}
