<?php
// One-time installer. CLI: php setup.php [--demo]   Browser: setup.php?demo=1   Delete this file afterwards.
$cfg=require __DIR__.'/config.php'; $d=$cfg['db']; $nl=PHP_SAPI==='cli'?"\n":"<br>";
$p=new PDO("mysql:host={$d["host"]};port={$d["port"]};charset=utf8mb4",$d['user'],$d['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$p->exec("CREATE DATABASE IF NOT EXISTS `{$d['name']}` CHARACTER SET utf8mb4"); $p->exec("USE `{$d['name']}`");
$p->exec(file_get_contents(__DIR__.'/database.sql'));
if($p->query('SELECT COUNT(*) FROM users')->fetchColumn()) exit("Already installed. Delete setup.php.$nl");
$iv=$p->prepare('INSERT INTO venues(name,activities) VALUES(?,?)'); $ii=$p->prepare('INSERT INTO items(venue_id,name,price) VALUES(?,?,?)');
foreach(array_slice($cfg['venues'],0,2) as $v){ $iv->execute([$v['name'],implode(',',$v['activities'])]); $vid=$p->lastInsertId(); foreach($v['items'] as $n=>$pr) $ii->execute([$vid,$n,$pr]); }
$p->prepare("INSERT INTO users(username,password_hash,full_name,role) VALUES('admin',?,'Administrator','admin')")->execute([password_hash('admin123',PASSWORD_BCRYPT)]);
if(isset($_GET['demo'])||in_array('--demo',$argv??[])){
  $names=['Alice M.','John D.','Sarah K.','Mike O.','Fatima N.','Paul E.','Grace T.','Brian A.','Linda C.','Samuel B.','Ruth N.','David F.'];
  $c=$p->prepare('INSERT INTO customers(full_name,email,phone,created_at) VALUES(?,?,?,DATE_SUB(NOW(),INTERVAL ? DAY))');
  foreach($names as $i=>$n) $c->execute([$n,"c$i@example.com",'67700000'.str_pad($i,2,'0',STR_PAD_LEFT),rand(30,100)]);
  $ids=$p->query('SELECT id FROM customers')->fetchAll(PDO::FETCH_COLUMN); $V=$p->query('SELECT id,activities FROM venues')->fetchAll(PDO::FETCH_ASSOC);
  $t=$p->prepare('INSERT INTO transactions(customer_id,venue_id,total,payment_method,created_at) VALUES(?,?,?,?,DATE_SUB(NOW(),INTERVAL ? HOUR))');
  $ti=$p->prepare('INSERT INTO transaction_items(transaction_id,item_id,qty,price) VALUES(?,?,?,?)'); $vs=$p->prepare('INSERT INTO visits(customer_id,venue_id,activity,duration_min,check_in) VALUES(?,?,?,?,DATE_SUB(NOW(),INTERVAL ? HOUR))');
  for($k=0;$k<160;$k++){ $cid=$ids[(int)(pow(mt_rand()/mt_getrandmax(),2)*count($ids))]; $v=$V[array_rand($V)];
    $its=$p->query("SELECT id,price FROM items WHERE venue_id={$v['id']}")->fetchAll(PDO::FETCH_ASSOC); $it=$its[array_rand($its)]; $q=rand(1,3);
    $t->execute([$cid,$v['id'],$it['price']*$q,['Cash','Mobile Money','Card'][rand(0,2)],rand(0,90*24)]); $ti->execute([$p->lastInsertId(),$it['id'],$q,$it['price']]); }
  for($k=0;$k<260;$k++){ $i=array_rand($ids); $v=$V[array_rand($V)]; $a=explode(',',$v['activities']);
    $vs->execute([$ids[$i],$v['id'],$a[array_rand($a)],rand(20,90),$i>=9?rand(20*24,80*24):rand(0,60*24)]); }
  echo "Demo data added.$nl";
}
echo "Installed. Sign in at index.html with admin / admin123 and change the password. Delete setup.php.$nl";
