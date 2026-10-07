<?php
require __DIR__.'/includes/bootstrap.php';
function customers($s=''){
  $w=$s?'WHERE c.full_name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?':'';
  $rows=q("SELECT c.id,c.full_name,c.email,c.phone,c.created_at,COALESCE(s.spent,0) spent,COALESCE(v.n,0) visits,
    GREATEST(COALESCE(s.lst,'1970-01-01'),COALESCE(v.lst,'1970-01-01')) last_activity FROM customers c
    LEFT JOIN (SELECT customer_id,SUM(total) spent,MAX(created_at) lst FROM transactions GROUP BY customer_id) s ON s.customer_id=c.id
    LEFT JOIN (SELECT customer_id,COUNT(*) n,MAX(check_in) lst FROM visits GROUP BY customer_id) v ON v.customer_id=c.id $w ORDER BY c.full_name",
    $s?["%$s%","%$s%","%$s%"]:[])->fetchAll();
  foreach($rows as &$r){ $r['spent']=(float)$r['spent']; $r['visits']=(int)$r['visits'];
    $r['status']=strtotime($r['last_activity'])>time()-30*86400?'Active':(strtotime($r['created_at'])>time()-30*86400?'Trial':'Inactive'); }
  return $rows;
}
$act=$_GET['action']??''; $in=json_decode(file_get_contents('php://input'),true)??[];
try { switch($act){
case 'info': out(['name'=>$cfg['app_name']]);
case 'login':
  $u=q('SELECT * FROM users WHERE username=?',[$in['username']??''])->fetch();
  if($u && password_verify($in['password']??'',$u['password_hash'])){ session_regenerate_id(true);
    $_SESSION['u']=['id'=>$u['id'],'name'=>$u['full_name'],'role'=>$u['role']]; out(['ok'=>1]); }
  out(['error'=>'Wrong username or password'],401);
case 'logout': session_destroy(); out(['ok'=>1]);
case 'me':
  $u=auth(); $v=q('SELECT * FROM venues ORDER BY id')->fetchAll();
  foreach($v as &$x){ $x['activities']=array_values(array_filter(explode(',',$x['activities'])));
    $x['items']=q('SELECT id,name,price FROM items WHERE venue_id=?',[$x['id']])->fetchAll(); }
  out(['user'=>$u,'app'=>['name'=>$cfg['app_name'],'currency'=>$cfg['currency'],'tax_pct'=>$cfg['tax_pct']],'venues'=>$v]);
case 'customers':
  auth();
  if($_SERVER['REQUEST_METHOD']==='POST'){
    $n=trim($in['full_name']??''); $e=trim($in['email']??'');
    if(!$n) out(['error'=>'Enter the customer\'s full name'],400);
    if($e && !filter_var($e,FILTER_VALIDATE_EMAIL)) out(['error'=>'Enter a valid email address'],400);
    q('INSERT INTO customers(full_name,email,phone) VALUES(?,?,?)',[$n,$e?:null,trim($in['phone']??'')]);
    out(['id'=>db()->lastInsertId()],201);
  }
  out(['customers'=>customers($_GET['q']??'')]);
case 'order':
  $u=auth(); $items=$in['items']??[];
  if(!$items||empty($in['customer_id'])||empty($in['venue_id'])) out(['error'=>'Choose a customer, an establishment and at least one item'],400);
  $pm=in_array($in['payment_method']??'',['Cash','Mobile Money','Card'])?$in['payment_method']:'Cash';
  $pdo=db(); $pdo->beginTransaction();
  try{ $sub=0; $lines=[];
    foreach($items as $i){ $qty=(int)($i['qty']??0);
      $r=q('SELECT id,price FROM items WHERE id=? AND venue_id=?',[$i['item_id']??0,$in['venue_id']])->fetch();
      if(!$r||$qty<1) throw new Exception('Invalid item or quantity');
      $sub+=$r['price']*$qty; $lines[]=[$r['id'],$qty,$r['price']]; }
    $disc=max(0,min(100,(float)($in['discount_pct']??0)));
    $total=round($sub*(1-$disc/100)*(1+$cfg['tax_pct']/100),2);
    q('INSERT INTO transactions(customer_id,venue_id,user_id,total,payment_method) VALUES(?,?,?,?,?)',[$in['customer_id'],$in['venue_id'],$u['id'],$total,$pm]);
    $id=$pdo->lastInsertId();
    foreach($lines as $l) q('INSERT INTO transaction_items(transaction_id,item_id,qty,price) VALUES(?,?,?,?)',[$id,$l[0],$l[1],$l[2]]);
    $pdo->commit();
  } catch(Throwable $e){ $pdo->rollBack(); out(['error'=>'Order not saved: check customer and items'],400); }
  out(['id'=>$id,'total'=>$total],201);
case 'visit':
  auth(); $dur=$in['duration_min']??0;
  if(!is_numeric($dur)||$dur<0) out(['error'=>'Duration must be 0 or more minutes'],400);
  if(empty($in['customer_id'])||empty($in['venue_id'])||empty($in['activity'])) out(['error'=>'Choose a customer, establishment and activity'],400);
  q('INSERT INTO visits(customer_id,venue_id,activity,duration_min) VALUES(?,?,?,?)',[$in['customer_id'],$in['venue_id'],$in['activity'],(int)$dur]);
  out(['ok'=>1],201);
case 'dashboard':
  auth(['admin','manager']); $d=in_array((int)($_GET['days']??7),[7,30,90])?(int)$_GET['days']:7; $risk=(int)$cfg['at_risk_days'];
  $since="DATE_SUB(CURDATE(),INTERVAL $d DAY)"; $r=[];
  $rows=q("SELECT DATE(created_at) d,SUM(total) s FROM transactions WHERE created_at>=$since GROUP BY d")->fetchAll(PDO::FETCH_KEY_PAIR);
  $labels=[];$vals=[];
  for($i=$d-1;$i>=0;$i--){ $k=date('Y-m-d',strtotime("-$i day")); $labels[]=substr($k,5); $vals[]=(float)($rows[$k]??0); }
  $r['trend']=['labels'=>$labels,'values'=>$vals]; $r['risk_days']=$risk;
  $r['kpi']=q("SELECT (SELECT COALESCE(SUM(total),0) FROM transactions WHERE created_at>=$since) revenue,
    (SELECT COUNT(*) FROM transactions WHERE created_at>=$since) orders,(SELECT COUNT(*) FROM visits WHERE check_in>=$since) visits,
    (SELECT COUNT(*) FROM customers) customers,(SELECT COUNT(*) FROM customers c WHERE EXISTS(SELECT 1 FROM visits WHERE customer_id=c.id) AND EXISTS(SELECT 1 FROM transactions WHERE customer_id=c.id)) cross_customers")->fetch();
  $r['venues']=q("SELECT v.name,COALESCE(SUM(t.total),0) revenue FROM venues v LEFT JOIN transactions t ON t.venue_id=v.id AND t.created_at>=$since GROUP BY v.id ORDER BY v.id")->fetchAll();
  $r['activity']=q("SELECT CONCAT(v.name,': ',x.activity) label,COUNT(*) n FROM visits x JOIN venues v ON v.id=x.venue_id WHERE x.check_in>=$since GROUP BY x.venue_id,x.activity ORDER BY n DESC")->fetchAll();
  $pk=q("SELECT HOUR(check_in) h,COUNT(*) n FROM visits WHERE check_in>=$since GROUP BY h")->fetchAll(PDO::FETCH_KEY_PAIR);
  $r['peak']=array_map(fn($h)=>(int)($pk[$h]??0),range(0,23));
  $r['at_risk']=q("SELECT c.full_name name,c.phone,MAX(x.check_in) lv,DATEDIFF(NOW(),MAX(x.check_in)) days FROM customers c JOIN visits x ON x.customer_id=c.id GROUP BY c.id HAVING lv<DATE_SUB(NOW(),INTERVAL $risk DAY) ORDER BY lv DESC LIMIT 20")->fetchAll();
  $c=customers(); $n=max(1,count($c)); $as=array_sum(array_column($c,'spent'))/$n; $av=array_sum(array_column($c,'visits'))/$n;
  $r['whales']=array_values(array_filter($c,fn($x)=>$x['spent']>$as*1.5&&$x['visits']<=$av));
  usort($c,fn($a,$b)=>$b['spent']<=>$a['spent']); $r['top']=array_slice($c,0,10);
  out($r);
default: out(['error'=>'Unknown action'],404);
}} catch(PDOException $e){ $dup=$e->getCode()=='23000'; out(['error'=>$dup?'That record already exists or points to something missing (is the email already used?)':'Database error'],$dup?409:500);
} catch(Throwable $e){ out(['error'=>'Server error'],500); }
