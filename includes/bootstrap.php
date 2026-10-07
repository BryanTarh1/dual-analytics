<?php
$cfg = require __DIR__.'/../config.php';
date_default_timezone_set($cfg['timezone']);
session_start(['cookie_httponly'=>true,'cookie_samesite'=>'Lax']);
function db(){ global $cfg; static $p; if(!$p){ $d=$cfg['db'];
  $p=new PDO("mysql:host={$d['host']};port={$d['port']};dbname={$d['name']};charset=utf8mb4",$d['user'],$d['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]); } return $p; }function q($sql,$a=[]){ $s=db()->prepare($sql); $s->execute($a); return $s; }
function out($d,$c=200){ http_response_code($c); header('Content-Type: application/json'); echo json_encode($d); exit; }
function auth($roles=null){ if(empty($_SESSION['u'])) out(['error'=>'Please sign in'],401);
  if($roles && !in_array($_SESSION['u']['role'],$roles)) out(['error'=>'You do not have access to this'],403); return $_SESSION['u']; }
