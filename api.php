<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
session_set_cookie_params(['lifetime'=>31536000,'path'=>'/','httponly'=>true,'secure'=>(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off'),'samesite'=>'Lax']);
session_start();
$db=new PDO('sqlite:'.__DIR__.'/orders.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('PRAGMA busy_timeout = 5000');
$db->exec("CREATE TABLE IF NOT EXISTS orders (id INTEGER PRIMARY KEY AUTOINCREMENT, order_no TEXT UNIQUE, customer_name TEXT, phone TEXT, address TEXT, payment TEXT, note TEXT, subtotal REAL, discount REAL, total REAL, price_pending INTEGER DEFAULT 1, status TEXT DEFAULT 'new', created_at TEXT DEFAULT CURRENT_TIMESTAMP, lat REAL, lng REAL, location_accuracy REAL, location_url TEXT, branch_id TEXT DEFAULT 'zayed')");
try{$db->exec("ALTER TABLE orders ADD COLUMN price_pending INTEGER DEFAULT 1");}catch(Throwable $e){}
$db->exec("CREATE TABLE IF NOT EXISTS order_items (id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER, item_id TEXT, item_name TEXT, qty REAL, weight REAL, note TEXT, unit_price REAL, line_total REAL, choice TEXT)");
try{$db->exec("ALTER TABLE order_items ADD COLUMN pricing_mode TEXT DEFAULT 'qty'");}catch(Throwable $e){}
// Backfill pricing mode from the menu definition: only fish and shrimp can be weight-based.
try{ $mj=json_decode(@file_get_contents(__DIR__.'/menu.json'),true); $mm=[]; foreach(($mj['items']??[]) as $mi){$mm[(string)($mi['id']??'')]=in_array((string)($mi['pricing_mode']??'qty'),['qty','plate','weight'],true)?(string)$mi['pricing_mode']:'qty';} foreach($mm as $iid=>$pm){$u=$db->prepare("UPDATE order_items SET pricing_mode=? WHERE item_id=? AND (pricing_mode IS NULL OR pricing_mode='')");$u->execute([$pm,$iid]);}}catch(Throwable $e){}
$db->exec("CREATE TABLE IF NOT EXISTS settings (name TEXT PRIMARY KEY,value TEXT NOT NULL)");
$db->exec("CREATE TABLE IF NOT EXISTS inventory (item_id TEXT PRIMARY KEY, stock REAL DEFAULT NULL, unit TEXT DEFAULT 'pcs', reorder_level REAL DEFAULT 0)");
$db->exec("CREATE TABLE IF NOT EXISTS audit_log (id INTEGER PRIMARY KEY AUTOINCREMENT, action TEXT, order_id INTEGER, details TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP)");
$db->exec("CREATE TABLE IF NOT EXISTS order_events (id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER NOT NULL, from_status TEXT, to_status TEXT NOT NULL, actor TEXT, details TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP)");
$db->exec("CREATE TABLE IF NOT EXISTS price_history (id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER NOT NULL, old_total REAL, new_total REAL NOT NULL, actor TEXT, reason TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP)");
$db->exec("CREATE TABLE IF NOT EXISTS ingredients (id INTEGER PRIMARY KEY AUTOINCREMENT, name_ar TEXT NOT NULL, name_en TEXT DEFAULT '', unit TEXT DEFAULT 'g', stock REAL DEFAULT 0, reorder_level REAL DEFAULT 0, cost_per_unit REAL DEFAULT 0, created_at TEXT DEFAULT CURRENT_TIMESTAMP)");
$db->exec("CREATE TABLE IF NOT EXISTS recipe_ingredients (id INTEGER PRIMARY KEY AUTOINCREMENT, item_id TEXT NOT NULL, method_id TEXT NOT NULL DEFAULT '', ingredient_id INTEGER NOT NULL, qty_per_unit REAL NOT NULL DEFAULT 0)");
$db->exec("CREATE TABLE IF NOT EXISTS ingredient_movements (id INTEGER PRIMARY KEY AUTOINCREMENT, ingredient_id INTEGER NOT NULL, delta REAL NOT NULL, reason TEXT NOT NULL, order_id INTEGER DEFAULT NULL, actor TEXT DEFAULT '', created_at TEXT DEFAULT CURRENT_TIMESTAMP)");
$db->exec("CREATE TABLE IF NOT EXISTS reviews (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, rating INTEGER, service_rating INTEGER, food_rating INTEGER, comment TEXT, approved INTEGER DEFAULT 1, created_at TEXT DEFAULT CURRENT_TIMESTAMP)");
function ensure_column($db,$table,$column,$definition){$cols=$db->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);foreach($cols as $c)if($c['name']===$column)return;$db->exec("ALTER TABLE $table ADD COLUMN $column $definition");}
ensure_column($db,'orders','lat','REAL');
ensure_column($db,'orders','lng','REAL');
ensure_column($db,'orders','location_accuracy','REAL');
ensure_column($db,'orders','location_url','TEXT');
ensure_column($db,'orders','receipt_file','TEXT');
ensure_column($db,'orders','branch_id',"TEXT DEFAULT 'zayed'");
ensure_column($db,'orders','tax_rate','REAL DEFAULT 14');
ensure_column($db,'orders','tax_amount','REAL DEFAULT 0');
ensure_column($db,'orders','delivery_fee','REAL DEFAULT 0');
ensure_column($db,'orders','delivery_distance_km','REAL DEFAULT 0');
ensure_column($db,'orders','delivery_rate','REAL DEFAULT 10');
ensure_column($db,'orders','delivery_manual_override','INTEGER DEFAULT 0');
ensure_column($db,'orders','delivery_override_by','TEXT');
ensure_column($db,'orders','delivery_override_at','TEXT');
ensure_column($db,'orders','discount_rate','REAL DEFAULT 10');
ensure_column($db,'orders','customer_confirmed','INTEGER DEFAULT 0');
ensure_column($db,'orders','customer_confirmed_at','TEXT');
ensure_column($db,'orders','confirmed_by','TEXT');
ensure_column($db,'orders','last_modified_at','TEXT');
ensure_column($db,'orders','customer_token_hash','TEXT');
ensure_column($db,'orders','cancel_requested','INTEGER DEFAULT 0');
ensure_column($db,'orders','cancel_reason','TEXT');
ensure_column($db,'orders','cancel_requested_at','TEXT');
ensure_column($db,'orders','customer_edit_count','INTEGER DEFAULT 0');
ensure_column($db,'orders','order_day','TEXT');
ensure_column($db,'orders','daily_serial','INTEGER DEFAULT 0');
ensure_column($db,'orders','accepted_at','TEXT');
ensure_column($db,'orders','preparing_at','TEXT');
ensure_column($db,'orders','ready_at','TEXT');
ensure_column($db,'orders','out_for_delivery_at','TEXT');
ensure_column($db,'orders','delivered_at','TEXT');
ensure_column($db,'orders','cancelled_at','TEXT');
ensure_column($db,'order_items','actual_qty','REAL DEFAULT NULL');
ensure_column($db,'order_items','actual_weight','REAL DEFAULT NULL');
ensure_column($db,'order_items','actual_line_total','REAL DEFAULT NULL');
ensure_column($db,'order_items','pricing_mode',"TEXT DEFAULT 'qty'");
ensure_column($db,'order_items','method_id','TEXT DEFAULT NULL');
ensure_column($db,'order_items','method_name','TEXT DEFAULT NULL');
ensure_column($db,'order_items','method_price','REAL DEFAULT 0');
$db->exec("CREATE TABLE IF NOT EXISTS branches (id TEXT PRIMARY KEY,name TEXT NOT NULL,password TEXT NOT NULL,active INTEGER DEFAULT 1)");
ensure_column($db,'branches','lat','REAL');
ensure_column($db,'branches','lng','REAL');
ensure_column($db,'branches','open_time','TEXT');
ensure_column($db,'branches','close_time','TEXT');
$defaults=[
 'whatsapp'=>'01021212601','driver_whatsapp'=>'01021212601','discount'=>'10','branch'=>'فرع الشيخ زايد - Centrada Plaza','auto_refresh'=>'2',
 'open_time'=>'12:00','close_time'=>'00:00','delivery_rate'=>'10','payment_cash'=>'1','payment_visa'=>'1','payment_instapay'=>'1','payment_wallet'=>'1',
 'instapay'=>'01021212601','wallet_number'=>'01021212601','restaurant_name'=>'Sea Gull Restaurant','alert_volume'=>'0.16','auto_print'=>'1','delivery_note'=>'اتصل بالعميل قبل التحرك',
 'instagram'=>'https://www.instagram.com/seagullrestaurantegy?igsi=MWYwNjc1azllYTIzcg==','facebook'=>'https://www.facebook.com/share/1VBDh8SzmR/'
];
foreach($defaults as $k=>$v){$s=$db->prepare('INSERT OR IGNORE INTO settings(name,value) VALUES(?,?)');$s->execute([$k,$v]);}
$branches=[['zayed','فرع الشيخ زايد','4854'],['dokki','فرع الدقي','9753'],['gleem','فرع جليم','1357'],['max','فرع الماكس','8654'],['marina','فرع مارينا','27857'],['madinaty','فرع مدينتي','0875'],['tagamoa','فرع التجمع','4526']];
// IMPORTANT: only seed branches the first time (INSERT OR IGNORE). The previous ON CONFLICT DO UPDATE
// rewrote every branch's name AND password on every single API request, silently reverting any
// password/name change an owner made — this was the root cause of branch-dashboard login problems.
$bi=$db->prepare('INSERT OR IGNORE INTO branches(id,name,password,active) VALUES(?,?,?,1)'); foreach($branches as $br)$bi->execute($br);
// Built-in branch map points: used by "find nearest branch". Only fill missing coordinates; Owner edits remain authoritative.
$defaultCoords=[
 'zayed'=>[30.008135,30.983779],
 'dokki'=>[30.044600,31.219300],
 'gleem'=>[31.241570,29.957420],
 'max'=>[31.145034,29.843528],
 'marina'=>[30.823290,29.028470],
 'madinaty'=>[30.078050,31.665790],
 'tagamoa'=>[30.003805,31.424936]
];
$bc=$db->prepare('UPDATE branches SET lat=?,lng=? WHERE id=? AND (lat IS NULL OR lng IS NULL)');
foreach($defaultCoords as $bid=>$xy)$bc->execute([$xy[0],$xy[1],$bid]);
$db->exec("CREATE TABLE IF NOT EXISTS branch_item_overrides (branch_id TEXT NOT NULL, item_id TEXT NOT NULL, available INTEGER DEFAULT 1, price REAL DEFAULT NULL, PRIMARY KEY(branch_id,item_id))");
$db->exec("CREATE TABLE IF NOT EXISTS staff_users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT UNIQUE NOT NULL, password_hash TEXT NOT NULL, role TEXT NOT NULL DEFAULT 'cashier', branch_id TEXT DEFAULT '', display_name TEXT DEFAULT '', active INTEGER DEFAULT 1, created_at TEXT DEFAULT CURRENT_TIMESTAMP, last_login_at TEXT)");
$db->exec("INSERT OR IGNORE INTO settings(name,value) VALUES('owner_username','owner')");
$db->exec("INSERT OR IGNORE INTO settings(name,value) VALUES('owner_password','SeaGull@2026')");
function out($x){echo json_encode($x,JSON_UNESCAPED_UNICODE);exit;}
function auth_user(){ return $_SESSION['seagull_user']??null; }
function admin_ok(){ return !empty($_SESSION['seagull_user']); }
function role_name(){ $u=auth_user(); return $u['role']??''; }
function can($permission){ $r=role_name(); $map=[
 'owner'=>['all'],
 'branch'=>['orders','price','status','reports','customers','inventory','settings','kds'],
 'manager'=>['orders','price','status','reports','customers','inventory','settings','kds'],
 'cashier'=>['orders','status','reports','customers'],
 'kitchen'=>['orders','status','kds'],
 'delivery'=>['orders','status','kds']
 ]; return $r==='owner'||in_array($permission,$map[$r]??[],true); }
function is_owner(){ return role_name()==='owner'; }
function branch_filter_sql(){ $u=auth_user(); if(!$u || $u['role']==='owner') return ['','']; return [' AND branch_id=?', $u['branch_id']]; }
function settings($db){return $db->query('SELECT name,value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);}
function audit($db,$action,$details='',$order_id=null){$st=$db->prepare('INSERT INTO audit_log(action,order_id,details) VALUES(?,?,?)');$st->execute([$action,$order_id,$details]);}
function menu($db){$f=__DIR__.'/menu.json';$m=file_exists($f)?json_decode(file_get_contents($f),true):null;if(!is_array($m))return ['cats'=>[],'icons'=>[],'items'=>[]];$m['cats']=is_array($m['cats']??null)?$m['cats']:[];$m['icons']=is_array($m['icons']??null)?$m['icons']:[];$m['items']=is_array($m['items']??null)?$m['items']:[];foreach($m['items'] as &$it){$it['methods']=is_array($it['methods']??null)?array_values(array_map(function($mm){return ['id'=>(string)($mm['id']??''),'ar'=>(string)($mm['ar']??''),'en'=>(string)($mm['en']??''),'extra_price'=>(float)($mm['extra_price']??0)];},$it['methods'])):[];$it['serving_info']=(string)($it['serving_info']??'');$it['ingredients_text']=(string)($it['ingredients_text']??'');}unset($it);return $m;}
function haversine_km($lat1,$lng1,$lat2,$lng2){
 $lat1=deg2rad((float)$lat1);$lat2=deg2rad((float)$lat2);$dLat=$lat2-$lat1;$dLng=deg2rad((float)$lng2-(float)$lng1);
 $a=sin($dLat/2)**2+cos($lat1)*cos($lat2)*sin($dLng/2)**2;
 return 6371*2*atan2(sqrt($a),sqrt(max(0,1-$a)));
}
function delivery_quote($db,$branchId,$lat,$lng){
 $rate=max(0,(float)(settings($db)['delivery_rate']??10));
 if($lat===null||$lng===null)return ['distance_km'=>0,'fee'=>0,'rate'=>$rate];
 $st=$db->prepare('SELECT lat,lng FROM branches WHERE id=? AND active=1');$st->execute([$branchId]);$b=$st->fetch(PDO::FETCH_ASSOC);
 if(!$b||$b['lat']===null||$b['lng']===null)return ['distance_km'=>0,'fee'=>0,'rate'=>$rate];
 $km=round(haversine_km($lat,$lng,$b['lat'],$b['lng']),1);
 $fee=round($km*$rate,2);
 return ['distance_km'=>$km,'fee'=>$fee,'rate'=>$rate];
}
function init_inventory($db){
 $m=menu($db);
 $st=$db->prepare('INSERT OR IGNORE INTO inventory(item_id,stock,unit,reorder_level) VALUES(?,?,?,?)');
 foreach($m['items'] as $it){
  $unit=(($it['cat']??'')==='shrimp')?'kg':'pcs';
  $st->execute([$it['id']??'',null,$unit,0]);
 }
}
$action=$_GET['action']??'';
if($action==='health'){
  out(['ok'=>true,'app'=>'Sea Gull Ultimate OWNER PRO','version'=>'V19 OVER THE TOP','php'=>PHP_VERSION,'pdo_sqlite'=>extension_loaded('pdo_sqlite'),'sqlite3'=>extension_loaded('sqlite3'),'writable'=>is_writable(__DIR__),'sounds_writable'=>is_dir(__DIR__.'/sounds')?is_writable(__DIR__.'/sounds'):is_writable(__DIR__)]);
}
if($action==='diagnostics'){
  $tables=[]; foreach(['orders','order_items','settings','branches','reviews','audit_log','price_history'] as $t){$q=$db->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name=".$db->quote($t));$tables[$t]=(bool)$q->fetchColumn();}
  out(['ok'=>true,'version'=>'V19','tables'=>$tables,'orders_count'=>(int)$db->query('SELECT COUNT(*) FROM orders')->fetchColumn(),'branches_count'=>(int)$db->query('SELECT COUNT(*) FROM branches')->fetchColumn(),'discount'=>(string)(settings($db)['discount']??'')]);
}
if($action==='admin_login'){
 $b=json_decode(file_get_contents('php://input'),true)?:[];
 $u=trim((string)($b['username']??'')); $pw=(string)($b['password']??'');
 $st=$db->prepare('SELECT id,username,password_hash,role,branch_id,display_name,active FROM staff_users WHERE username=? LIMIT 1');$st->execute([$u]);$su=$st->fetch(PDO::FETCH_ASSOC);
 if($su && (int)$su['active']===1 && password_verify($pw,$su['password_hash'])){
   session_regenerate_id(true);
   $branchName='الإدارة المركزية — كل الفروع';
   if($su['branch_id']){ $bs=$db->prepare('SELECT name FROM branches WHERE id=?');$bs->execute([$su['branch_id']]);$branchName=$bs->fetchColumn()?:$su['branch_id']; }
   $_SESSION['seagull_user']=['role'=>$su['role'],'username'=>$su['username'],'display_name'=>$su['display_name']?:$su['username'],'branch_id'=>$su['branch_id']??'','branch_name'=>$branchName,'staff_id'=>(int)$su['id']];
   $db->prepare('UPDATE staff_users SET last_login_at=CURRENT_TIMESTAMP WHERE id=?')->execute([(int)$su['id']]);
   audit($db,'login','Staff login: '.$su['username'].' | role='.$su['role'].' | branch='.$su['branch_id']); out(['ok'=>true,'role'=>$su['role'],'username'=>$su['username'],'display_name'=>$su['display_name']?:$su['username'],'branch_id'=>$su['branch_id']??'','branch_name'=>$branchName]);
 }
 $ownerSettings=settings($db); $ownerUser=$ownerSettings['owner_username']??'owner'; $ownerPass=$ownerSettings['owner_password']??'SeaGull@2026';
 if(hash_equals(strtolower($ownerUser),strtolower($u)) && hash_equals($ownerPass,$pw)){
   session_regenerate_id(true); $_SESSION['seagull_user']=['role'=>'owner','username'=>$ownerUser,'display_name'=>'Owner','branch_id'=>'','branch_name'=>'الإدارة المركزية — كل الفروع']; audit($db,'login','Owner login'); out(['ok'=>true,'role'=>'owner','username'=>$ownerUser,'display_name'=>'Owner','branch_id'=>'','branch_name'=>'الإدارة المركزية — كل الفروع']);
 }
 if(strtolower($u)==='admin'){
   $st=$db->prepare('SELECT id,name,password FROM branches WHERE active=1 AND password=? LIMIT 1');$st->execute([$pw]);$br=$st->fetch(PDO::FETCH_ASSOC);
   if($br){ session_regenerate_id(true); $_SESSION['seagull_user']=['role'=>'branch','username'=>'admin','display_name'=>'مدير الفرع','branch_id'=>$br['id'],'branch_name'=>$br['name']]; audit($db,'login','Branch login: '.$br['name']); out(['ok'=>true,'role'=>'branch','username'=>'admin','display_name'=>'مدير الفرع','branch_id'=>$br['id'],'branch_name'=>$br['name']]); }
 }
 out(['ok'=>false,'error'=>'Invalid credentials']);
}
if($action==='admin_session'){
 $u=auth_user(); if(!$u) out(['ok'=>false]); out(['ok'=>true,'user'=>$u]);
}
if($action==='admin_logout'){
 $_SESSION=[]; if(ini_get('session.use_cookies')){ $p=session_get_cookie_params(); setcookie(session_name(),'',['expires'=>time()-42000,'path'=>$p['path'],'domain'=>$p['domain']??'','secure'=>$p['secure'],'httponly'=>$p['httponly'],'samesite'=>$p['samesite']??'Lax']); } session_destroy(); out(['ok'=>true]);
}
if($action==='list_staff'){
 if(!is_owner())out(['ok'=>false,'error'=>'Owner only']);
 $rows=$db->query("SELECT id,username,role,branch_id,display_name,active,created_at,last_login_at FROM staff_users ORDER BY branch_id,role,username")->fetchAll(PDO::FETCH_ASSOC);out(['ok'=>true,'staff'=>$rows]);
}
if($action==='save_staff'){
 if(!is_owner())out(['ok'=>false,'error'=>'Owner only']); $b=json_decode(file_get_contents('php://input'),true)?:[];
 $id=(int)($b['id']??0);$username=trim((string)($b['username']??''));$password=(string)($b['password']??'');$role=(string)($b['role']??'cashier');$branch=trim((string)($b['branch_id']??''));$name=trim((string)($b['display_name']??''));$active=!empty($b['active'])?1:0;
 if(!preg_match('/^[A-Za-z0-9._-]{3,40}$/',$username))out(['ok'=>false,'error'=>'اسم المستخدم غير صالح']);
 if(!in_array($role,['manager','cashier','kitchen','delivery'],true))out(['ok'=>false,'error'=>'الدور غير صالح']);
 if($role!=='owner' && $branch==='')out(['ok'=>false,'error'=>'اختر الفرع']);
 if($branch!==''){ $q=$db->prepare('SELECT 1 FROM branches WHERE id=?');$q->execute([$branch]);if(!$q->fetchColumn())out(['ok'=>false,'error'=>'الفرع غير موجود']); }
 if($id){
   $q=$db->prepare('SELECT id FROM staff_users WHERE username=? AND id<>?');$q->execute([$username,$id]);if($q->fetchColumn())out(['ok'=>false,'error'=>'اسم المستخدم مستخدم بالفعل']);
   if($password!==''){$st=$db->prepare('UPDATE staff_users SET username=?,password_hash=?,role=?,branch_id=?,display_name=?,active=? WHERE id=?');$st->execute([$username,password_hash($password,PASSWORD_DEFAULT),$role,$branch,$name,$active,$id]);}
   else{$st=$db->prepare('UPDATE staff_users SET username=?,role=?,branch_id=?,display_name=?,active=? WHERE id=?');$st->execute([$username,$role,$branch,$name,$active,$id]);}
   audit($db,'staff_update','User='.$username.' Role='.$role.' Branch='.$branch); out(['ok'=>true]);
 }
 if($password==='')out(['ok'=>false,'error'=>'كلمة المرور مطلوبة']);$q=$db->prepare('SELECT id FROM staff_users WHERE username=?');$q->execute([$username]);if($q->fetchColumn())out(['ok'=>false,'error'=>'اسم المستخدم مستخدم بالفعل']);
 $st=$db->prepare('INSERT INTO staff_users(username,password_hash,role,branch_id,display_name,active) VALUES(?,?,?,?,?,?)');$st->execute([$username,password_hash($password,PASSWORD_DEFAULT),$role,$branch,$name,$active]);audit($db,'staff_create','User='.$username.' Role='.$role.' Branch='.$branch);out(['ok'=>true,'id'=>$db->lastInsertId()]);
}
if($action==='toggle_staff'){
 if(!is_owner())out(['ok'=>false,'error'=>'Owner only']);$b=json_decode(file_get_contents('php://input'),true)?:[];$id=(int)($b['id']??0);$st=$db->prepare('SELECT username,active FROM staff_users WHERE id=?');$st->execute([$id]);$x=$st->fetch(PDO::FETCH_ASSOC);if(!$x)out(['ok'=>false,'error'=>'المستخدم غير موجود']);$new=$x['active']?0:1;$db->prepare('UPDATE staff_users SET active=? WHERE id=?')->execute([$new,$id]);audit($db,'staff_toggle','User='.$x['username'].' Active='.$new);out(['ok'=>true,'active'=>$new]);
}
if($action==='owner_dashboard'){ if(!admin_ok() || auth_user()['role']!=='owner')out(['ok'=>false,'error'=>'Forbidden']); $rows=$db->query("SELECT b.id,b.name,b.active,COUNT(o.id) orders_count,COALESCE(SUM(o.total),0) sales,SUM(CASE WHEN o.status='new' THEN 1 ELSE 0 END) new_orders FROM branches b LEFT JOIN orders o ON o.branch_id=b.id AND date(o.created_at)=date('now','localtime') GROUP BY b.id ORDER BY b.id")->fetchAll(PDO::FETCH_ASSOC); $all=$db->query("SELECT COUNT(*) orders_count,COALESCE(SUM(total),0) sales,SUM(CASE WHEN status='new' THEN 1 ELSE 0 END) new_orders FROM orders WHERE date(created_at)=date('now','localtime')")->fetch(PDO::FETCH_ASSOC); out(['ok'=>true,'summary'=>$all,'branches'=>$rows]);}
if($action==='list_branches'){if(!admin_ok()||auth_user()['role']!=='owner')out(['ok'=>false,'error'=>'Forbidden']);out(['ok'=>true,'branches'=>$db->query('SELECT id,name,active,lat,lng,open_time,close_time FROM branches ORDER BY name')->fetchAll(PDO::FETCH_ASSOC)]);}
if($action==='toggle_branch_active'){
 if(!admin_ok() || auth_user()['role']!=='owner')out(['ok'=>false,'error'=>'Owner only']);
 $b=json_decode(file_get_contents('php://input'),true)?:[]; $bid=trim((string)($b['branch_id']??'')); if($bid==='')out(['ok'=>false,'error'=>'Branch required']);
 $st=$db->prepare('SELECT active FROM branches WHERE id=?'); $st->execute([$bid]); $cur=$st->fetch(PDO::FETCH_ASSOC); if(!$cur)out(['ok'=>false,'error'=>'الفرع غير موجود']);
 $new=$cur['active']?0:1; $u=$db->prepare('UPDATE branches SET active=? WHERE id=?'); $u->execute([$new,$bid]); audit($db,'branch_toggle_active','Branch='.$bid.' Active='.$new); out(['ok'=>true,'branch_id'=>$bid,'active'=>$new]);
}
if($action==='save_branch_details'){
 if(!admin_ok() || auth_user()['role']!=='owner')out(['ok'=>false,'error'=>'Owner only']);
 $b=json_decode(file_get_contents('php://input'),true)?:[]; $bid=trim((string)($b['branch_id']??'')); if($bid==='')out(['ok'=>false,'error'=>'Branch required']);
 $valid=$db->prepare('SELECT 1 FROM branches WHERE id=?');$valid->execute([$bid]);if(!$valid->fetchColumn())out(['ok'=>false,'error'=>'الفرع غير موجود']);
 $lat=($b['lat']??'')===''?null:(float)$b['lat']; $lng=($b['lng']??'')===''?null:(float)$b['lng'];
 $ot=trim((string)($b['open_time']??'')); $ct=trim((string)($b['close_time']??'')); $ot=$ot===''?null:$ot; $ct=$ct===''?null:$ct;
 $st=$db->prepare('UPDATE branches SET lat=?,lng=?,open_time=?,close_time=? WHERE id=?'); $st->execute([$lat,$lng,$ot,$ct,$bid]);
 audit($db,'branch_details_saved','Branch='.$bid); out(['ok'=>true]);
}
if($action==='set_branch_password'){
 if(!admin_ok() || auth_user()['role']!=='owner')out(['ok'=>false,'error'=>'Owner only']);
 $b=json_decode(file_get_contents('php://input'),true)?:[]; $bid=trim((string)($b['branch_id']??'')); $pw=trim((string)($b['password']??''));
 if($bid==='')out(['ok'=>false,'error'=>'Branch required']); if(mb_strlen($pw)<4)out(['ok'=>false,'error'=>'كلمة المرور يجب ألا تقل عن 4 حروف/أرقام']);
 $valid=$db->prepare('SELECT 1 FROM branches WHERE id=?');$valid->execute([$bid]);if(!$valid->fetchColumn())out(['ok'=>false,'error'=>'الفرع غير موجود']);
 $st=$db->prepare('UPDATE branches SET password=? WHERE id=?'); $st->execute([$pw,$bid]);
 audit($db,'branch_password_changed','Branch='.$bid); out(['ok'=>true]);
}
if($action==='set_owner_credentials'){
 if(!admin_ok() || auth_user()['role']!=='owner')out(['ok'=>false,'error'=>'Owner only']);
 $b=json_decode(file_get_contents('php://input'),true)?:[]; $cur=(string)($b['current_password']??''); $newU=trim((string)($b['new_username']??'')); $newP=(string)($b['new_password']??'');
 $s=settings($db); $ownerUser=$s['owner_username']??'owner'; $ownerPass=$s['owner_password']??'SeaGull@2026';
 if(!hash_equals($ownerPass,$cur))out(['ok'=>false,'error'=>'كلمة المرور الحالية غير صحيحة']);
 if(!preg_match('/^[A-Za-z0-9._-]{3,40}$/',$newU))out(['ok'=>false,'error'=>'اسم المستخدم الجديد غير صالح']);
 if(mb_strlen($newP)<6)out(['ok'=>false,'error'=>'كلمة المرور الجديدة يجب ألا تقل عن 6 حروف/أرقام']);
 $u1=$db->prepare('INSERT INTO settings(name,value) VALUES(?,?) ON CONFLICT(name) DO UPDATE SET value=excluded.value');
 $u1->execute(['owner_username',$newU]); $u1->execute(['owner_password',$newP]);
 $_SESSION['seagull_user']['username']=$newU;
 audit($db,'owner_credentials_changed','Owner login username/password updated'); out(['ok'=>true]);
}
if($action==='list_public_branches'){
 out(['ok'=>true,'branches'=>$db->query('SELECT id,name,active,lat,lng,open_time,close_time FROM branches ORDER BY name')->fetchAll(PDO::FETCH_ASSOC)]);
}
if($action==='get_public_settings'){
 $s=settings($db); $public=[]; foreach(['discount','branch','open_time','close_time','payment_cash','payment_visa','payment_instapay','payment_wallet','instapay','wallet_number','instagram','facebook','restaurant_name'] as $k)$public[$k]=$s[$k]??''; out(['ok'=>true,'settings'=>$public]);
}
if($action==='get_public_menu'){
 $branchId=trim((string)($_GET['branch_id']??'')); $m=menu($db);
 if($branchId!==''){
   $st=$db->prepare('SELECT item_id,available,price FROM branch_item_overrides WHERE branch_id=?'); $st->execute([$branchId]); $ov=[]; foreach($st->fetchAll(PDO::FETCH_ASSOC) as $x)$ov[(string)$x['item_id']]=$x;
   foreach($m['items'] as &$it){$id=(string)$it['id']; if(isset($ov[$id])){ if((int)$ov[$id]['available']===0)$it['available']=false; else $it['available']=($it['available']??true)!==false; if($ov[$id]['price']!==null)$it['price']=(float)$ov[$id]['price']; } } unset($it);
 }
 out(['ok'=>true,'menu'=>$m,'branch_id'=>$branchId]);
}
if($action==='get_branch_menu'){
 if(!admin_ok() || auth_user()['role']!=='owner')out(['ok'=>false,'error'=>'Owner only']);
 $branchId=trim((string)($_GET['branch_id']??'')); if($branchId==='')out(['ok'=>false,'error'=>'Branch required']);
 $m=menu($db); $st=$db->prepare('SELECT item_id,available,price FROM branch_item_overrides WHERE branch_id=?');$st->execute([$branchId]);$ov=[];foreach($st->fetchAll(PDO::FETCH_ASSOC) as $x)$ov[(string)$x['item_id']]=$x;
 foreach($m['items'] as &$it){$id=(string)$it['id'];$it['branch_available']=isset($ov[$id])?(int)$ov[$id]['available']:1;$it['branch_price']=isset($ov[$id])&&$ov[$id]['price']!==null?(float)$ov[$id]['price']:null;}unset($it);out(['ok'=>true,'branch_id'=>$branchId,'menu'=>$m]);
}
if($action==='save_branch_override'){
 if(!admin_ok() || auth_user()['role']!=='owner')out(['ok'=>false,'error'=>'Owner only']);
 $b=json_decode(file_get_contents('php://input'),true)?:[];$branchId=trim((string)($b['branch_id']??''));$itemId=trim((string)($b['item_id']??''));if($branchId===''||$itemId==='')out(['ok'=>false,'error'=>'بيانات ناقصة']);
 $valid=$db->prepare('SELECT 1 FROM branches WHERE id=?');$valid->execute([$branchId]);if(!$valid->fetchColumn())out(['ok'=>false,'error'=>'الفرع غير موجود']);
 $m=menu($db);$exists=false;foreach($m['items'] as $it)if((string)$it['id']===$itemId){$exists=true;break;}if(!$exists)out(['ok'=>false,'error'=>'الصنف غير موجود']);
 $available=!empty($b['available'])?1:0;$price=($b['use_master']??true)?null:max(0,(float)($b['price']??0));
 $st=$db->prepare('INSERT INTO branch_item_overrides(branch_id,item_id,available,price) VALUES(?,?,?,?) ON CONFLICT(branch_id,item_id) DO UPDATE SET available=excluded.available,price=excluded.price');$st->execute([$branchId,$itemId,$available,$price]);audit($db,'branch_menu_override','Branch='.$branchId.' Item='.$itemId.' Available='.$available.' Price='.($price===null?'MASTER':$price));out(['ok'=>true]);
}
if($action==='reset_branch_menu'){
 if(!admin_ok() || auth_user()['role']!=='owner')out(['ok'=>false,'error'=>'Owner only']);$branchId=trim((string)($_GET['branch_id']??''));$st=$db->prepare('DELETE FROM branch_item_overrides WHERE branch_id=?');$st->execute([$branchId]);audit($db,'branch_menu_reset','Branch='.$branchId);out(['ok'=>true]);
}
if($action==='create_order'){
 try{
  $b=json_decode(file_get_contents('php://input'),true);
  if(!is_array($b)) out(['ok'=>false,'error'=>'بيانات الطلب غير صالحة']);
  foreach(['name','phone','address','items'] as $k)if(empty($b[$k]))out(['ok'=>false,'error'=>'بيانات ناقصة: '.$k]);
  $branchId=trim((string)($b['branch_id']??''));
  if($branchId==='')out(['ok'=>false,'error'=>'اختيار الفرع مطلوب']);
  $bst=$db->prepare('SELECT id,name FROM branches WHERE id=? AND active=1 LIMIT 1');$bst->execute([$branchId]);$branch=$bst->fetch(PDO::FETCH_ASSOC);
  if(!$branch)out(['ok'=>false,'error'=>'الفرع المختار غير متاح حاليًا']);
  $m=menu($db); $ovst=$db->prepare('SELECT item_id,available,price FROM branch_item_overrides WHERE branch_id=?');$ovst->execute([$branchId]);$ov=[];foreach($ovst->fetchAll(PDO::FETCH_ASSOC) as $z)$ov[(string)$z['item_id']]=$z; foreach($m['items'] as &$mi){$mid=(string)$mi['id'];if(isset($ov[$mid])){if((int)$ov[$mid]['available']===0)$mi['available']=false;if($ov[$mid]['price']!==null)$mi['price']=(float)$ov[$mid]['price'];}}unset($mi); $map=[];foreach($m['items'] as $it)$map[$it['id']]=$it;
  $sub=0;$prep=[];
  foreach($b['items'] as $x){
   $id=(string)($x['id']??''); if($id==='' || !isset($map[$id])) out(['ok'=>false,'error'=>'أحد الأصناف لم يعد متاحًا. حدّث الصفحة وجرب مرة أخرى.']); $it=$map[$id]; if(array_key_exists('available',$it) && $it['available']===false) out(['ok'=>false,'error'=>'الصنف غير متاح حاليًا: '.($it['ar']??$id)]); $entry=$x['entry']??0; $qty=0;$weight=0;$choice='';$line=0;
   $cat=(string)($it['cat']??'');
   $pm=(string)($it['pricing_mode']??'qty'); $mode=($pm==='weight')?'weight':(($pm==='plate')?'plate':'qty');
   if($mode==='weight'){
     $qty=($cat==='fish') ? max(1,(float)($entry['count']??1)) : 1;
     $weight=max(0,(float)($entry['weight']??0));
     if(in_array($cat,['fish','shrimp'],true) && $weight<150) out(['ok'=>false,'error'=>'الحد الأدنى لوزن السمك والجمبري هو 150 جرام']);
     $weight=round($weight/50)*50;
     $choice=number_format($weight/1000,3,'.','').' كجم';
     $line=round((float)$it['price']*$weight/1000*($cat==='shrimp'?1:max(1,$qty)),2);
   } else {
     $qty=max(0,(float)$entry); $line=round((float)$it['price']*$qty,2);
   }
   if($qty<=0 || $line<=0) continue;
   $methodId=trim((string)($x['method_id']??'')); $methodName=''; $methodPrice=0;
   if($methodId!==''){
     $methods=is_array($it['methods']??null)?$it['methods']:[]; $foundM=null; foreach($methods as $mm) if((string)($mm['id']??'')===$methodId){$foundM=$mm;break;}
     if(!$foundM) out(['ok'=>false,'error'=>'طريقة التحضير المختارة لم تعد متاحة لهذا الصنف: '.($it['ar']??$id)]);
     $methodName=(string)($foundM['ar']??''); $methodPrice=max(0,round((float)($foundM['extra_price']??0),2));
     $line=round($line+$methodPrice*max(1,$qty),2);
   }
   $sub+=$line;$prep[]=['id'=>$id,'name'=>$it['ar'],'qty'=>$qty,'weight'=>$weight,'choice'=>$choice,'note'=>trim((string)($x['note']??'')),'price'=>(float)$it['price'],'line'=>$line,'mode'=>$mode,'method_id'=>$methodId?:null,'method_name'=>$methodName?:null,'method_price'=>$methodPrice];
  }
  if(!$prep)out(['ok'=>false,'error'=>'لم يتم اختيار أصناف صالحة في الطلب']);
  $s=settings($db);$rate=max(0,min(100,(float)($s['discount']??10)));$taxRate=14; $tax=round($sub*$taxRate/100,2);
  $pay=$b['payment']??'cash';if(!in_array($pay,['cash','visa','instapay','wallet'],true))out(['ok'=>false,'error'=>'طريقة الدفع غير صالحة']);
  $lat=isset($b['lat'])&&$b['lat']!==''?(float)$b['lat']:null;$lng=isset($b['lng'])&&$b['lng']!==''?(float)$b['lng']:null;$acc=isset($b['location_accuracy'])&&$b['location_accuracy']!==''?(float)$b['location_accuracy']:null;
  $quote=delivery_quote($db,$branchId,$lat,$lng); $delivery=$quote['fee']; $gross=round($sub+$tax+$delivery,2); $disc=round($gross*$rate/100,2); $total=round($gross-$disc,2);
  $loc=($lat!==null&&$lng!==null)?'https://www.google.com/maps?q='.rawurlencode($lat.','.$lng):'';
  $receiptFile='';
  // Private, unguessable customer access token. Only its SHA-256 hash is stored.
  $customerToken=bin2hex(random_bytes(32));
  $customerTokenHash=hash('sha256',$customerToken);
  $db->beginTransaction();
  try{
  $orderDay=(new DateTime('now',new DateTimeZone('Africa/Cairo')))->format('Y-m-d');
  $serialSt=$db->prepare('SELECT COALESCE(MAX(daily_serial),0)+1 FROM orders WHERE order_day=?');$serialSt->execute([$orderDay]);$dailySerial=(int)$serialSt->fetchColumn();
  $st=$db->prepare('INSERT INTO orders(order_no,order_day,daily_serial,customer_name,phone,address,payment,note,subtotal,discount,total,price_pending,status,lat,lng,location_accuracy,location_url,receipt_file,branch_id,tax_rate,tax_amount,delivery_fee,delivery_distance_km,delivery_rate,discount_rate,customer_token_hash) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
  $no='SG-'.date('ymd').'-'.strtoupper(bin2hex(random_bytes(3)));
  $st->execute([$no,$orderDay,$dailySerial,$b['name'],$b['phone'],$b['address'],$pay,$b['note']??'',$sub,$disc,$total,1,'new',$lat,$lng,$acc,$loc,$receiptFile,$branchId,$taxRate,$tax,$delivery,$quote['distance_km'],$quote['rate'],$rate,$customerTokenHash]);
  $oid=(int)$db->lastInsertId();
  $si=$db->prepare('INSERT INTO order_items(order_id,item_id,item_name,qty,weight,note,unit_price,line_total,choice,pricing_mode,method_id,method_name,method_price) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)');
  foreach($prep as $p)$si->execute([$oid,$p['id'],$p['name'],$p['qty'],$p['weight'],$p['note'],$p['price'],$p['line'],$p['choice'],$p['mode'],$p['method_id'],$p['method_name'],$p['method_price']]);
  foreach($prep as $p){$uq=$db->prepare('UPDATE order_items SET actual_qty=?, actual_weight=?, actual_line_total=? WHERE order_id=? AND item_id=?');$uq->execute([$p['qty'],$p['weight']?:null,$p['line'],$oid,$p['id']]);}
  $al=$db->prepare('INSERT INTO audit_log(action,order_id,details) VALUES(?,?,?)');$al->execute(['order_created',$oid,$no.' | '.$branchId.' | daily_serial='.$dailySerial]);
  audit($db,'customer_access_token_issued','Private customer token issued',$oid);
  $db->commit();
  foreach($prep as $p){ $units=($p['mode']==='weight')?round(($p['weight']/1000)*max(1,$p['qty']),3):$p['qty']; deduct_recipe_ingredients($db,$p['id'],$p['method_id'],$units,$oid,'customer'); }
  out(['ok'=>true,'order_no'=>$no,'daily_serial'=>$dailySerial,'total'=>$total,'id'=>$oid,'location_url'=>$loc,'branch_id'=>$branchId,'branch_name'=>$branch['name'],'delivery_distance_km'=>$quote['distance_km'],'delivery_fee'=>$delivery,'delivery_rate'=>$quote['rate'],'access_token'=>$customerToken]);
  }catch(Throwable $tx){if($db->inTransaction())$db->rollBack();throw $tx;}
 }catch(Throwable $e){
  http_response_code(200);
  out(['ok'=>false,'error'=>'تعذر حفظ الطلب على السيرفر. '.$e->getMessage()]);
 }
}
if($action==='list_orders'){
 if(!admin_ok())out(['ok'=>false,'error'=>'Unauthorized']);$u=auth_user(); if($u['role']==='owner') $rows=$db->query('SELECT * FROM orders ORDER BY id DESC LIMIT 1000')->fetchAll(PDO::FETCH_ASSOC); else {$st=$db->prepare('SELECT * FROM orders WHERE branch_id=? ORDER BY id DESC LIMIT 500');$st->execute([$u['branch_id']]);$rows=$st->fetchAll(PDO::FETCH_ASSOC);}
 foreach($rows as &$r){$q=$db->prepare('SELECT * FROM order_items WHERE order_id=? ORDER BY id');$q->execute([$r['id']]);$r['items']=$q->fetchAll(PDO::FETCH_ASSOC);}out(['ok'=>true,'orders'=>$rows]);
}
if($action==='kds'){
 if(!admin_ok())out(['ok'=>false,'error'=>'Unauthorized']); $u=auth_user();
 $where=$u['role']==='owner'?'1=1':'branch_id=?'; $params=$u['role']==='owner'?[]:[$u['branch_id']];
 $st=$db->prepare("SELECT * FROM orders WHERE $where AND status IN ('accepted','preparing','ready') ORDER BY CASE status WHEN 'preparing' THEN 1 WHEN 'accepted' THEN 2 WHEN 'ready' THEN 3 ELSE 4 END, id ASC LIMIT 200");$st->execute($params);$rows=$st->fetchAll(PDO::FETCH_ASSOC);
 foreach($rows as &$r){$q=$db->prepare('SELECT item_name,qty,weight,actual_qty,actual_weight,note,choice,pricing_mode,method_name FROM order_items WHERE order_id=? ORDER BY id');$q->execute([(int)$r['id']]);$r['items']=$q->fetchAll(PDO::FETCH_ASSOC);}
 out(['ok'=>true,'orders'=>$rows,'server_time'=>date('c')]);
}
if($action==='quote_delivery'){
 if(!admin_ok())out(['ok'=>false,'error'=>'Unauthorized']);$id=(int)($_GET['id']??0);$u=auth_user();$st=$db->prepare($u['role']==='owner'?'SELECT branch_id,lat,lng FROM orders WHERE id=?':'SELECT branch_id,lat,lng FROM orders WHERE id=? AND branch_id=?');$u['role']==='owner'?$st->execute([$id]):$st->execute([$id,$u['branch_id']]);$o=$st->fetch(PDO::FETCH_ASSOC);if(!$o)out(['ok'=>false,'error'=>'Order not found']);$q=delivery_quote($db,$o['branch_id'],$o['lat']!==null?(float)$o['lat']:null,$o['lng']!==null?(float)$o['lng']:null);out(['ok'=>true,'distance_km'=>$q['distance_km'],'fee'=>$q['fee'],'rate'=>$q['rate']]);
}
if($action==='set_delivery_fee'){
 if(!admin_ok()||!can('price'))out(['ok'=>false,'error'=>'تعديل خدمة التوصيل متاح للمدير فقط']);
 $b=json_decode(file_get_contents('php://input'),true)?:[]; $id=(int)($b['id']??0); $mode=($b['mode']??'manual')==='auto'?'auto':'manual';
 $u=auth_user(); $st=$db->prepare($u['role']==='owner'?'SELECT * FROM orders WHERE id=?':'SELECT * FROM orders WHERE id=? AND branch_id=?'); $u['role']==='owner'?$st->execute([$id]):$st->execute([$id,$u['branch_id']]); $o=$st->fetch(PDO::FETCH_ASSOC); if(!$o)out(['ok'=>false,'error'=>'Order not found']);
 $q=delivery_quote($db,$o['branch_id'],$o['lat']!==null?(float)$o['lat']:null,$o['lng']!==null?(float)$o['lng']:null); $fee=$mode==='auto'?$q['fee']:max(0,(float)($b['fee']??0));
 $gross=round((float)$o['subtotal']+(float)$o['tax_amount']+$fee,2); $discRate=max(0,min(100,(float)($o['discount_rate']??10))); $disc=round($gross*$discRate/100,2); $total=round($gross-$disc,2); $actor=$u['role']==='owner'?'owner':($u['username']??'staff');
 $st=$db->prepare('UPDATE orders SET delivery_fee=?,delivery_distance_km=?,delivery_rate=?,delivery_manual_override=?,delivery_override_by=?,delivery_override_at=CURRENT_TIMESTAMP,discount=?,total=?,price_pending=1,customer_confirmed=0,customer_confirmed_at=NULL,confirmed_by=NULL,last_modified_at=CURRENT_TIMESTAMP WHERE id=?');$st->execute([$fee,$q['distance_km'],$q['rate'],$mode==='manual'?1:0,$mode==='manual'?$actor:null,$disc,$total,$id]);
 audit($db,'delivery_fee_changed','Delivery '.$mode.' | fee='.$fee.' | distance='.$q['distance_km'].' km | actor='.$actor,$id); out(['ok'=>true,'mode'=>$mode,'fee'=>$fee,'distance_km'=>$q['distance_km'],'rate'=>$q['rate'],'discount'=>$disc,'total'=>$total]);
}
if($action==='price_history'){
 if(!admin_ok())out(['ok'=>false,'error'=>'Unauthorized']);$id=(int)($_GET['id']??0);$u=auth_user();
 $q=$db->prepare($u['role']==='owner'?'SELECT ph.* FROM price_history ph JOIN orders o ON o.id=ph.order_id WHERE ph.order_id=? ORDER BY ph.id DESC':'SELECT ph.* FROM price_history ph JOIN orders o ON o.id=ph.order_id WHERE ph.order_id=? AND o.branch_id=? ORDER BY ph.id DESC');
 $u['role']==='owner'?$q->execute([$id]):$q->execute([$id,$u['branch_id']]);out(['ok'=>true,'history'=>$q->fetchAll(PDO::FETCH_ASSOC)]);
}
if($action==='confirm_price'){
 if(!admin_ok()||!can('price'))out(['ok'=>false,'error'=>'ليست لديك صلاحية اعتماد الأسعار']);
 $b=json_decode(file_get_contents('php://input'),true)?:[]; $id=(int)($b['id']??0); $final=(float)($b['final_price']??-1); if($id<1||$final<0)out(['ok'=>false,'error'=>'Invalid price']);
 $u=auth_user(); $qOld=$db->prepare($u['role']==='owner'?'SELECT total FROM orders WHERE id=?':'SELECT total FROM orders WHERE id=? AND branch_id=?'); $u['role']==='owner'?$qOld->execute([$id]):$qOld->execute([$id,$u['branch_id']]); $old_total=(float)($qOld->fetchColumn()??0); if($u['role']==='owner'){$st=$db->prepare('UPDATE orders SET total=?, price_pending=0,customer_confirmed=0,customer_confirmed_at=NULL,confirmed_by=NULL,last_modified_at=CURRENT_TIMESTAMP WHERE id=?');$st->execute([$final,$id]);}
 else{$st=$db->prepare('UPDATE orders SET total=?, price_pending=0,customer_confirmed=0,customer_confirmed_at=NULL,confirmed_by=NULL,last_modified_at=CURRENT_TIMESTAMP WHERE id=? AND branch_id=?');$st->execute([$final,$id,$u['branch_id']]);}
 $actor=($u['role']==='owner'?'owner':'branch:'.$u['branch_id']); $ph=$db->prepare('INSERT INTO price_history(order_id,old_total,new_total,actor,reason) VALUES(?,?,?,?,?)');$ph->execute([$id,$old_total,$final,$actor,'Final price approval']); audit($db,'price_confirmed',$id,'Final price confirmed: '.$final.' | actor='.$actor); out(['ok'=>true,'final_price'=>$final]);
}
if($action==='update_order_items'){
 if(!admin_ok()||!can('price'))out(['ok'=>false,'error'=>'تعديل الوزن والسعر متاح للمدير فقط']);
 $b=json_decode(file_get_contents('php://input'),true)?:[]; $id=(int)($b['id']??0); if($id<1)out(['ok'=>false,'error'=>'Invalid order']);
 $u=auth_user();
 $st=$db->prepare($u['role']==='owner'?'SELECT * FROM orders WHERE id=?':'SELECT * FROM orders WHERE id=? AND branch_id=?');
 $u['role']==='owner'?$st->execute([$id]):$st->execute([$id,$u['branch_id']]); $order=$st->fetch(PDO::FETCH_ASSOC); if(!$order)out(['ok'=>false,'error'=>'Order not found']);
 $items=$b['items']??[]; $subtotal=0;
 foreach($items as $x){$iid=(int)($x['id']??0);$qty=max(0,(float)($x['qty']??0));$aw=$x['actual_weight'];$aw=($aw===null||$aw==='')?null:max(0,(float)$aw);$q=$db->prepare('SELECT oi.unit_price,oi.item_name,oi.weight,oi.choice,oi.qty,oi.pricing_mode,oi.item_id,oi.method_price FROM order_items oi WHERE oi.id=? AND oi.order_id=?');$q->execute([$iid,$id]);$it=$q->fetch(PDO::FETCH_ASSOC);if(!$it)continue;
   $pm=(string)($it['pricing_mode']??'qty'); $mode=$pm==='weight'?'weight':($pm==='plate'?'plate':'qty'); $methodPrice=(float)($it['method_price']??0);
   $menuMap=[];foreach(menu($db)['items'] as $mi)$menuMap[$mi['id']]=$mi; $cat=(string)($menuMap[(string)$it['item_id']]['cat']??'');
   if($mode==='weight'){if($aw===null)out(['ok'=>false,'error'=>'أدخل الوزن بعد المراجعة']);if(in_array($cat,['fish','shrimp'],true) && $aw<150)out(['ok'=>false,'error'=>'الحد الأدنى لوزن السمك والجمبري هو 150 جرام']);$aw=round($aw/50)*50;$qty=($cat==='fish')?max(1,$qty):1;$line=round((float)$it['unit_price']*$aw/1000*$qty+$methodPrice*max(1,$qty),2);} else {$aw=null;$line=round((float)$it['unit_price']*$qty+$methodPrice*max(1,$qty),2);}
   $up=$db->prepare('UPDATE order_items SET actual_qty=?,actual_weight=?,actual_line_total=?,qty=?,weight=?,line_total=? WHERE id=? AND order_id=?');$up->execute([$qty,$aw,$line,$qty,$aw??$it['weight'],$line,$iid,$id]);$subtotal+=$line;
 }
 $taxRate=14; $tax=round($subtotal*$taxRate/100,2); $autoDelivery=!empty($b['auto_delivery']); $quote=delivery_quote($db,$order['branch_id'],isset($order['lat'])&&$order['lat']!==''?(float)$order['lat']:null,isset($order['lng'])&&$order['lng']!==''?(float)$order['lng']:null); $manualOverride=$autoDelivery?0:1; $delivery=$autoDelivery?$quote['fee']:max(0,(float)($b['delivery_fee']??0)); $discRate=10; $gross=round($subtotal+$tax+$delivery,2); $disc=round($gross*$discRate/100,2); $total=round($gross-$disc,2);
 $actor=($u['role']==='owner'?'owner':($u['username']??'staff')); $up=$db->prepare('UPDATE orders SET subtotal=?,tax_rate=?,tax_amount=?,delivery_fee=?,delivery_distance_km=?,delivery_rate=?,delivery_manual_override=?,delivery_override_by=?,delivery_override_at=CASE WHEN ?=1 THEN CURRENT_TIMESTAMP ELSE NULL END,discount_rate=?,discount=?,total=?,price_pending=1,customer_confirmed=0,customer_confirmed_at=NULL,confirmed_by=NULL,last_modified_at=CURRENT_TIMESTAMP WHERE id=?');$up->execute([$subtotal,$taxRate,$tax,$delivery,$quote['distance_km'],$quote['rate'],$manualOverride,$manualOverride?$actor:null,$manualOverride,$discRate,$disc,$total,$id]);$db->prepare('UPDATE orders SET last_modified_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$id]);
 $old_total=(float)$order['total']; $ph=$db->prepare('INSERT INTO price_history(order_id,old_total,new_total,actor,reason) VALUES(?,?,?,?,?)');$ph->execute([$id,$old_total,$total,$actor,'Weight/quantity review']); audit($db,'order_repriced',$id,'Repriced with actual weights | subtotal='.$subtotal.' | tax='.$tax.' | delivery='.$delivery.' | discount='.$disc.' | total='.$total.' | actor='.$actor);out(['ok'=>true,'subtotal'=>$subtotal,'tax_rate'=>$taxRate,'tax_amount'=>$tax,'delivery_fee'=>$delivery,'delivery_distance_km'=>$quote['distance_km'],'delivery_rate'=>$quote['rate'],'discount_rate'=>$discRate,'discount'=>$disc,'total'=>$total]);
}
if($action==='update_status'){
 if(!admin_ok()||!can('status'))out(['ok'=>false,'error'=>'ليست لديك صلاحية تغيير حالة الطلب']);
 $b=json_decode(file_get_contents('php://input'),true)?:[];
 $allowed=['new','accepted','preparing','ready','out_for_delivery','delivered','cancelled'];
 $next=$b['status']??''; $id=(int)($b['id']??0);
 if(!in_array($next,$allowed,true))out(['ok'=>false,'error'=>'Bad status']);
 $u=auth_user();
 $st=$db->prepare($u['role']==='owner'?'SELECT * FROM orders WHERE id=?':'SELECT * FROM orders WHERE id=? AND branch_id=?');
 if($u['role']==='owner')$st->execute([$id]);else $st->execute([$id,$u['branch_id']]);
 $o=$st->fetch(PDO::FETCH_ASSOC);if(!$o)out(['ok'=>false,'error'=>'Order not found']);
 $current=(string)$o['status'];
 if($current===$next)out(['ok'=>true,'unchanged'=>true]);
 $transitions=[
   'new'=>['accepted','cancelled'],
   'accepted'=>['preparing','cancelled'],
   'preparing'=>['ready','cancelled'],
   'ready'=>['out_for_delivery','cancelled'],
   'out_for_delivery'=>['delivered','cancelled'],
   'delivered'=>[],
   'cancelled'=>[]
 ];
 $r=role_name();
 if($r==='kitchen' && !in_array($next,['preparing','ready'],true))out(['ok'=>false,'error'=>'موظف المطبخ يغيّر فقط مراحل التجهيز']);
 if($r==='delivery' && !in_array($next,['out_for_delivery','delivered'],true))out(['ok'=>false,'error'=>'موظف التوصيل يغيّر فقط مراحل التوصيل']);
 if($r==='cashier' && !in_array($next,['accepted','cancelled'],true))out(['ok'=>false,'error'=>'الكاشير لا يملك صلاحية هذه المرحلة']);
 if(in_array($next,['cancelled'],true) && !in_array($r,['owner','branch','manager','cashier'],true))out(['ok'=>false,'error'=>'لا تملك صلاحية إلغاء الطلب']);
 if($r==='cashier' && $next==='cancelled' && $current!=='new')out(['ok'=>false,'error'=>'الكاشير يستطيع إلغاء الطلب الجديد فقط']);
 if(!in_array($next,$transitions[$current]??[],true)){
   $names=['new'=>'جديد','accepted'=>'مقبول','preparing'=>'قيد التجهيز','ready'=>'جاهز','out_for_delivery'=>'خرج للتوصيل','delivered'=>'تم التسليم','cancelled'=>'ملغي'];
   out(['ok'=>false,'error'=>'لا يمكن نقل الطلب من «'.($names[$current]??$current).'» إلى «'.($names[$next]??$next).'». يجب اتباع خطوات التشغيل بالترتيب.']);
 }
 if($next==='accepted' && !(int)$o['customer_confirmed'])out(['ok'=>false,'error'=>'لا يمكن قبول الطلب قبل تأكيد العميل للسعر النهائي']);
 if(in_array($next,['preparing','ready','out_for_delivery','delivered'],true)){
   if((int)$o['price_pending'])out(['ok'=>false,'error'=>'السعر النهائي ما زال معلقًا. أعد حساب الوزن واعتمد السعر أولًا.']);
   if(!(int)$o['customer_confirmed'])out(['ok'=>false,'error'=>'العميل لم يؤكد السعر النهائي بعد.']);
 }
 $colMap=['accepted'=>'accepted_at','preparing'=>'preparing_at','ready'=>'ready_at','out_for_delivery'=>'out_for_delivery_at','delivered'=>'delivered_at','cancelled'=>'cancelled_at'];
 $actor=($u['role']==='owner'?'owner':('branch:'.$u['branch_id']));
 $db->beginTransaction();
 try{
   if(isset($colMap[$next])){
     $col=$colMap[$next];
     $st=$db->prepare("UPDATE orders SET status=?,{$col}=CURRENT_TIMESTAMP,last_modified_at=CURRENT_TIMESTAMP WHERE id=?" );$st->execute([$next,$id]);
   }else{$st=$db->prepare('UPDATE orders SET status=?,last_modified_at=CURRENT_TIMESTAMP WHERE id=?');$st->execute([$next,$id]);}
   $ev=$db->prepare('INSERT INTO order_events(order_id,from_status,to_status,actor,details) VALUES(?,?,?,?,?)');$ev->execute([$id,$current,$next,$actor,'Workflow transition']);
   audit($db,'order_status','Status: '.$current.' -> '.$next.' | actor='.$actor,$id);
   $db->commit();
   if($next==='cancelled') reverse_recipe_ingredients($db,$id);
 }catch(Throwable $e){$db->rollBack();out(['ok'=>false,'error'=>'تعذر تحديث حالة الطلب']);}
 out(['ok'=>true,'from'=>$current,'status'=>$next]);
}
if($action==='upload_alert_sound'){
 out(['ok'=>false,'error'=>'نغمة الإنذار ثابتة ولا يمكن تغييرها']);
}
if($action==='get_settings'){
 if(!admin_ok())out(['ok'=>false,'error'=>'Unauthorized']);$ss=settings($db);unset($ss['owner_password']);unset($ss['admin_password']);$u=auth_user();if($u['role']==='branch'){$ss['branch']=$u['branch_name'];$bi=$db->prepare('SELECT open_time,close_time FROM branches WHERE id=?');$bi->execute([$u['branch_id']]);$br=$bi->fetch(PDO::FETCH_ASSOC);if($br){if(!empty($br['open_time']))$ss['open_time']=$br['open_time'];if(!empty($br['close_time']))$ss['close_time']=$br['close_time'];}}if($u['role']==='owner'){$ss['owner_username']=$ss['owner_username']??'owner';}out(['ok'=>true,'settings'=>$ss]);
}
if($action==='save_settings'){
 if(!admin_ok())out(['ok'=>false,'error'=>'Unauthorized']);$b=json_decode(file_get_contents('php://input'),true)?:[];$u=auth_user();$allowed=array_keys(settings($db));$allowed=array_values(array_diff($allowed,['admin_password','owner_password','owner_username']));if($u['role']==='branch')$allowed=['whatsapp','driver_whatsapp','alert_volume','auto_refresh','open_time','close_time','payment_cash','payment_visa','payment_instapay','payment_wallet','instapay','wallet_number','auto_print','instagram','facebook','delivery_note'];foreach($allowed as $k)if(array_key_exists($k,$b)){ $v=(string)$b[$k]; if($k==='discount')$v=(string)max(0,min(100,(float)$v)); if($k==='auto_refresh')$v=(string)max(1,min(300,(int)$v)); if($k==='alert_volume')$v=(string)max(0.03,min(0.8,(float)$v));$st=$db->prepare('INSERT INTO settings(name,value) VALUES(?,?) ON CONFLICT(name) DO UPDATE SET value=excluded.value');$st->execute([$k,$v]);}
 if($u['role']==='branch' && $u['branch_id'] && (array_key_exists('open_time',$b) || array_key_exists('close_time',$b))){
   if(array_key_exists('open_time',$b)) $db->prepare('UPDATE branches SET open_time=? WHERE id=?')->execute([(string)$b['open_time'],$u['branch_id']]);
   if(array_key_exists('close_time',$b)) $db->prepare('UPDATE branches SET close_time=? WHERE id=?')->execute([(string)$b['close_time'],$u['branch_id']]);
 }
 out(['ok'=>true,'settings'=>settings($db)]);
}
if($action==='get_menu'){if(!admin_ok())out(['ok'=>false,'error'=>'Unauthorized']);out(['ok'=>true,'menu'=>menu($db)]);}
if($action==='save_item'){
 if(!admin_ok() || auth_user()['role']!=='owner')out(['ok'=>false,'error'=>'Owner only']);$b=json_decode(file_get_contents('php://input'),true)?:[];$m=menu($db);$id=trim($b['id']??'');$methods=null;
 if(array_key_exists('methods',$b) && is_array($b['methods'])){
   $methods=[]; foreach($b['methods'] as $mm){ $name=trim((string)($mm['ar']??'')); if($name==='')continue; $mid=trim((string)($mm['id']??'')); if($mid==='')$mid='m'.bin2hex(random_bytes(3)); $methods[]=['id'=>$mid,'ar'=>$name,'en'=>trim((string)($mm['en']??$name)),'extra_price'=>max(0,round((float)($mm['extra_price']??0),2))]; }
 }
 $servingInfo=trim((string)($b['serving_info']??'')); $ingredientsText=trim((string)($b['ingredients_text']??''));
 if(!$id)$id='item'.time().bin2hex(random_bytes(2));$found=false;foreach($m['items'] as &$it)if($it['id']===$id){$it=array_merge($it,$b);if($methods!==null)$it['methods']=$methods;$it['serving_info']=$servingInfo;$it['ingredients_text']=$ingredientsText;$found=true;break;}unset($it);$cat=$b['cat']??array_key_first($m['cats']); $pm=$b['pricing_mode']??'qty'; if(!in_array($pm,['qty','plate','weight'],true)) $pm='qty'; $avail=array_key_exists('available',$b)?(bool)$b['available']:true; if(!$found){$m['items'][]=['id'=>$id,'ar'=>$b['ar']??'','en'=>$b['en']??'','price'=>(float)($b['price']??0),'cat'=>$cat,'dar'=>$b['dar']??'','den'=>$b['den']??'','pricing_mode'=>$pm,'available'=>$avail,'methods'=>$methods??[],'serving_info'=>$servingInfo,'ingredients_text'=>$ingredientsText];}else{foreach($m['items'] as &$it2)if($it2['id']===$id){$it2['pricing_mode']=$pm;$it2['available']=$avail;$it2['cat']=$cat;}unset($it2);} file_put_contents(__DIR__.'/menu.json',json_encode($m,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));audit($db,'menu_save','Item: '.$id);out(['ok'=>true,'menu'=>$m]);
}
if($action==='delete_item'){
 if(!admin_ok() || auth_user()['role']!=='owner')out(['ok'=>false,'error'=>'Owner only']);$b=json_decode(file_get_contents('php://input'),true)?:[];$m=menu($db);$m['items']=array_values(array_filter($m['items'],fn($x)=>$x['id']!==($b['id']??'')));file_put_contents(__DIR__.'/menu.json',json_encode($m,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));audit($db,'menu_delete','Item: '.($b['id']??''));out(['ok'=>true,'menu'=>$m]);
}
if($action==='save_category'){
 if(!admin_ok() || auth_user()['role']!=='owner')out(['ok'=>false,'error'=>'Owner only']);$b=json_decode(file_get_contents('php://input'),true)?:[];$m=menu($db);$id=preg_replace('/[^a-zA-Z0-9_]/','_',trim($b['id']??''));if(!$id)out(['ok'=>false,'error'=>'Category ID required']);$m['cats'][$id]=[$b['ar']??$id,$b['en']??$id];$m['icons'][$id]=$b['icon']??'🍽️';file_put_contents(__DIR__.'/menu.json',json_encode($m,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));out(['ok'=>true,'menu'=>$m]);
}
if($action==='delete_category'){
 if(!admin_ok() || auth_user()['role']!=='owner')out(['ok'=>false,'error'=>'Owner only']);$b=json_decode(file_get_contents('php://input'),true)?:[];$m=menu($db);$id=$b['id']??'';if(isset($m['cats'][$id]))unset($m['cats'][$id]);unset($m['icons'][$id]);foreach($m['items'] as &$it)if($it['cat']===$id)$it['cat']=array_key_first($m['cats']);file_put_contents(__DIR__.'/menu.json',json_encode($m,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));out(['ok'=>true,'menu'=>$m]);
}
if($action==='customers'){
 if(!admin_ok())out(['ok'=>false,'error'=>'Unauthorized']);$u=auth_user(); if($u['role']==='owner') $rows=$db->query("SELECT customer_name,phone,address,COUNT(*) orders_count,SUM(total) total_spent,MAX(created_at) last_order FROM orders GROUP BY phone ORDER BY last_order DESC")->fetchAll(PDO::FETCH_ASSOC); else {$st=$db->prepare("SELECT customer_name,phone,address,COUNT(*) orders_count,SUM(total) total_spent,MAX(created_at) last_order FROM orders WHERE branch_id=? GROUP BY phone ORDER BY last_order DESC");$st->execute([$u['branch_id']]);$rows=$st->fetchAll(PDO::FETCH_ASSOC);}out(['ok'=>true,'customers'=>$rows]);
}
if($action==='reports'){
 if(!admin_ok())out(['ok'=>false,'error'=>'Unauthorized']);$from=$_GET['from']??date('Y-m-d');$to=$_GET['to']??date('Y-m-d');$u=auth_user();$where="date(created_at)>=date(?) AND date(created_at)<=date(?)";$params=[$from,$to];if($u['role']==='branch'){$where.=' AND branch_id=?';$params[]=$u['branch_id'];}
 $st=$db->prepare("SELECT COUNT(*) orders_count,COALESCE(SUM(subtotal),0) subtotal,COALESCE(SUM(discount),0) discount,COALESCE(SUM(total),0) total FROM orders WHERE $where");$st->execute($params);$summary=$st->fetch(PDO::FETCH_ASSOC);
 $st=$db->prepare("SELECT status,COUNT(*) count FROM orders WHERE $where GROUP BY status");$st->execute($params);$statuses=$st->fetchAll(PDO::FETCH_ASSOC);
 $st=$db->prepare("SELECT payment,COUNT(*) count,COALESCE(SUM(total),0) total FROM orders WHERE $where GROUP BY payment");$st->execute($params);$payments=$st->fetchAll(PDO::FETCH_ASSOC);
 $itemWhere="date(o.created_at)>=date(?) AND date(o.created_at)<=date(?)";$itemParams=[$from,$to];if($u['role']==='branch'){$itemWhere.=' AND o.branch_id=?';$itemParams[]=$u['branch_id'];}
 $st=$db->prepare("SELECT item_name,SUM(qty) qty,SUM(weight) weight,SUM(line_total) sales FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE $itemWhere GROUP BY item_id,item_name ORDER BY qty DESC,sales DESC");$st->execute($itemParams);$items=$st->fetchAll(PDO::FETCH_ASSOC);out(['ok'=>true,'from'=>$from,'to'=>$to,'summary'=>$summary,'statuses'=>$statuses,'payments'=>$payments,'items'=>$items]);
}
if($action==='get_inventory'){if(!admin_ok())out(['ok'=>false,'error'=>'Unauthorized']);$m=menu($db);$rows=$db->query('SELECT * FROM inventory')->fetchAll(PDO::FETCH_ASSOC);$map=[];foreach($m['items'] as $it)$map[$it['id']]=$it;foreach($rows as &$r){$r['name']=$map[$r['item_id']]['ar']??$r['item_id'];$r['name_en']=$map[$r['item_id']]['en']??'';$r['cat']=$map[$r['item_id']]['cat']??'';}out(['ok'=>true,'inventory'=>$rows]);}
if($action==='save_inventory'){if(!admin_ok())out(['ok'=>false,'error'=>'Unauthorized']);$b=json_decode(file_get_contents('php://input'),true)?:[];$id=$b['item_id']??'';$stock=($b['stock']===''||$b['stock']===null)?null:max(0,(float)$b['stock']);$unit=$b['unit']??'pcs';$re=max(0,(float)($b['reorder_level']??0));$st=$db->prepare('INSERT INTO inventory(item_id,stock,unit,reorder_level) VALUES(?,?,?,?) ON CONFLICT(item_id) DO UPDATE SET stock=excluded.stock,unit=excluded.unit,reorder_level=excluded.reorder_level');$st->execute([$id,$stock,$unit,$re]);audit($db,'inventory_save','Item: '.$id);out(['ok'=>true]);}
if($action==='adjust_stock'){if(!admin_ok())out(['ok'=>false,'error'=>'Unauthorized']);$b=json_decode(file_get_contents('php://input'),true)?:[];$id=$b['item_id']??'';$delta=(float)($b['delta']??0);$st=$db->prepare('UPDATE inventory SET stock=CASE WHEN stock IS NULL THEN ? ELSE MAX(0,stock+?) END WHERE item_id=?');$st->execute([$delta,$delta,$id]);out(['ok'=>true]);}

// ---- Real ingredient-based inventory & recipes ----
if($action==='list_ingredients'){
 if(!admin_ok())out(['ok'=>false,'error'=>'Unauthorized']);
 $rows=$db->query('SELECT * FROM ingredients ORDER BY name_ar')->fetchAll(PDO::FETCH_ASSOC);
 foreach($rows as &$r){$r['low']=((float)$r['reorder_level']>0 && (float)$r['stock']<=(float)$r['reorder_level'])?1:0;}
 out(['ok'=>true,'ingredients'=>$rows]);
}
if($action==='save_ingredient'){
 if(!admin_ok() || auth_user()['role']!=='owner')out(['ok'=>false,'error'=>'Owner only']);
 $b=json_decode(file_get_contents('php://input'),true)?:[]; $id=(int)($b['id']??0); $name=trim((string)($b['name_ar']??'')); if($name==='')out(['ok'=>false,'error'=>'اسم المكوّن مطلوب']);
 $nameEn=trim((string)($b['name_en']??'')); $unit=trim((string)($b['unit']??'g'))?:'g'; $reorder=max(0,(float)($b['reorder_level']??0)); $cost=max(0,(float)($b['cost_per_unit']??0));
 if($id>0){$st=$db->prepare('UPDATE ingredients SET name_ar=?,name_en=?,unit=?,reorder_level=?,cost_per_unit=? WHERE id=?');$st->execute([$name,$nameEn,$unit,$reorder,$cost,$id]);}
 else{$st=$db->prepare('INSERT INTO ingredients(name_ar,name_en,unit,stock,reorder_level,cost_per_unit) VALUES(?,?,?,?,?,?)');$st->execute([$name,$nameEn,$unit,max(0,(float)($b['stock']??0)),$reorder,$cost]);$id=(int)$db->lastInsertId();}
 audit($db,'ingredient_saved','Ingredient #'.$id.': '.$name); out(['ok'=>true,'id'=>$id]);
}
if($action==='delete_ingredient'){
 if(!admin_ok() || auth_user()['role']!=='owner')out(['ok'=>false,'error'=>'Owner only']);
 $b=json_decode(file_get_contents('php://input'),true)?:[]; $id=(int)($b['id']??0); if($id<1)out(['ok'=>false,'error'=>'Invalid']);
 $db->prepare('DELETE FROM recipe_ingredients WHERE ingredient_id=?')->execute([$id]);
 $db->prepare('DELETE FROM ingredients WHERE id=?')->execute([$id]);
 audit($db,'ingredient_deleted','Ingredient #'.$id); out(['ok'=>true]);
}
if($action==='restock_ingredient'){
 if(!admin_ok())out(['ok'=>false,'error'=>'Unauthorized']);
 $b=json_decode(file_get_contents('php://input'),true)?:[]; $id=(int)($b['id']??0); $qty=(float)($b['qty']??0); if($id<1||$qty===0.0)out(['ok'=>false,'error'=>'بيانات غير صالحة']);
 $u=auth_user(); $reason=trim((string)($b['reason']??'')) ?: ($qty>0?'شراء/توريد':'تصحيح/هالك');
 $st=$db->prepare('UPDATE ingredients SET stock=MAX(0,stock+?) WHERE id=?'); $st->execute([$qty,$id]);
 $mv=$db->prepare('INSERT INTO ingredient_movements(ingredient_id,delta,reason,actor) VALUES(?,?,?,?)'); $mv->execute([$id,$qty,$reason,$u['username']??role_name()]);
 audit($db,'ingredient_restock','Ingredient #'.$id.' delta='.$qty.' reason='.$reason); out(['ok'=>true]);
}
if($action==='ingredient_movements'){
 if(!admin_ok())out(['ok'=>false,'error'=>'Unauthorized']);
 $iid=(int)($_GET['ingredient_id']??0);
 $st=$iid>0?$db->prepare('SELECT m.*,i.name_ar FROM ingredient_movements m JOIN ingredients i ON i.id=m.ingredient_id WHERE m.ingredient_id=? ORDER BY m.id DESC LIMIT 300'):$db->prepare('SELECT m.*,i.name_ar FROM ingredient_movements m JOIN ingredients i ON i.id=m.ingredient_id ORDER BY m.id DESC LIMIT 300');
 $iid>0?$st->execute([$iid]):$st->execute();
 out(['ok'=>true,'movements'=>$st->fetchAll(PDO::FETCH_ASSOC)]);
}
if($action==='get_item_recipe'){
 if(!admin_ok() || auth_user()['role']!=='owner')out(['ok'=>false,'error'=>'Owner only']);
 $itemId=trim((string)($_GET['item_id']??'')); if($itemId==='')out(['ok'=>false,'error'=>'Item required']);
 $st=$db->prepare('SELECT r.*,i.name_ar,i.name_en,i.unit FROM recipe_ingredients r JOIN ingredients i ON i.id=r.ingredient_id WHERE r.item_id=? ORDER BY r.method_id,i.name_ar'); $st->execute([$itemId]);
 out(['ok'=>true,'recipe'=>$st->fetchAll(PDO::FETCH_ASSOC)]);
}
if($action==='save_item_recipe'){
 if(!admin_ok() || auth_user()['role']!=='owner')out(['ok'=>false,'error'=>'Owner only']);
 $b=json_decode(file_get_contents('php://input'),true)?:[]; $itemId=trim((string)($b['item_id']??'')); if($itemId==='')out(['ok'=>false,'error'=>'Item required']);
 $rows=is_array($b['rows']??null)?$b['rows']:[];
 $db->prepare('DELETE FROM recipe_ingredients WHERE item_id=?')->execute([$itemId]);
 $ins=$db->prepare('INSERT INTO recipe_ingredients(item_id,method_id,ingredient_id,qty_per_unit) VALUES(?,?,?,?)');
 foreach($rows as $r){$ingId=(int)($r['ingredient_id']??0); $qpu=(float)($r['qty_per_unit']??0); if($ingId<1||$qpu<=0)continue; $mid=trim((string)($r['method_id']??'')); $ins->execute([$itemId,$mid,$ingId,$qpu]);}
 audit($db,'recipe_saved','Item: '.$itemId); out(['ok'=>true]);
}
function deduct_recipe_ingredients($db,$itemId,$methodId,$units,$orderId,$actor='system'){
 $st=$db->prepare("SELECT * FROM recipe_ingredients WHERE item_id=? AND (method_id='' OR method_id=?)");
 $st->execute([$itemId,$methodId?:'__none__']);
 foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r){
   $consume=round((float)$r['qty_per_unit']*$units,3); if($consume<=0)continue;
   $db->prepare('UPDATE ingredients SET stock=MAX(0,stock-?) WHERE id=?')->execute([$consume,$r['ingredient_id']]);
   $db->prepare('INSERT INTO ingredient_movements(ingredient_id,delta,reason,order_id,actor) VALUES(?,?,?,?,?)')->execute([$r['ingredient_id'],-$consume,'sale',$orderId,$actor]);
 }
}
function reverse_recipe_ingredients($db,$orderId){
 $st=$db->prepare("SELECT ingredient_id,SUM(delta) total FROM ingredient_movements WHERE order_id=? AND reason='sale' GROUP BY ingredient_id");
 $st->execute([$orderId]);
 foreach($st->fetchAll(PDO::FETCH_ASSOC) as $row){
   $restore=-1*(float)$row['total']; if($restore<=0)continue;
   $db->prepare('UPDATE ingredients SET stock=stock+? WHERE id=?')->execute([$restore,$row['ingredient_id']]);
   $db->prepare('INSERT INTO ingredient_movements(ingredient_id,delta,reason,order_id) VALUES(?,?,?,?)')->execute([$row['ingredient_id'],$restore,'cancel_restock',$orderId]);
 }
}
if($action==='confirm_customer_price'){
 $b=json_decode(file_get_contents('php://input'),true)?:[];$no=trim((string)($b['order_no']??''));$token=trim((string)($b['access_token']??''));$phone=preg_replace('/\D/','',(string)($b['phone']??''));
 if(!$no || (!$token && !$phone))out(['ok'=>false,'error'=>'بيانات التأكيد ناقصة']);
 if($token){$hash=hash('sha256',$token);$st=$db->prepare('SELECT id,total,status,price_pending FROM orders WHERE order_no=? AND customer_token_hash=?');$st->execute([$no,$hash]);}
 else {$st=$db->prepare('SELECT id,total,status,price_pending FROM orders WHERE order_no=? AND phone=?');$st->execute([$no,$phone]);}
 $o=$st->fetch(PDO::FETCH_ASSOC);if(!$o)out(['ok'=>false,'error'=>'بيانات الوصول غير صحيحة']);if((int)$o['price_pending']===1)out(['ok'=>false,'error'=>'السعر النهائي لم يتم إرساله بعد']);if(in_array($o['status'],['preparing','ready','out_for_delivery','delivered'],true))out(['ok'=>true,'already'=>true,'message'=>'الطلب مؤكد بالفعل']);
 $st=$db->prepare("UPDATE orders SET customer_confirmed=1,customer_confirmed_at=CURRENT_TIMESTAMP,confirmed_by='customer',status='accepted',last_modified_at=CURRENT_TIMESTAMP WHERE id=? AND status IN ('new','accepted') AND price_pending=0");$st->execute([$o['id']]);
 audit($db,'customer_price_confirmed','Customer confirmed final price: '.$o['total'],$o['id']);out(['ok'=>true,'order_id'=>$o['id'],'total'=>$o['total'],'status'=>'accepted']);
}
if($action==='upload_receipt'){
 $b=json_decode(file_get_contents('php://input'),true)?:[];
 $no=trim((string)($b['order_no']??'')); $phone=preg_replace('/\D/','',(string)($b['phone']??''));
 $data=(string)($b['image_data']??'');
 if(!$no||!$phone||!$data)out(['ok'=>false,'error'=>'بيانات ناقصة']);
 $st=$db->prepare('SELECT id,payment FROM orders WHERE order_no=? AND phone=?'); $st->execute([$no,$phone]); $o=$st->fetch(PDO::FETCH_ASSOC);
 if(!$o)out(['ok'=>false,'error'=>'الطلب غير موجود']);
 if(!in_array($o['payment'],['instapay','wallet'],true))out(['ok'=>false,'error'=>'رفع الإيصال متاح فقط لطلبات إنستاباي أو المحفظة']);
 if(!preg_match('/^data:image\/(png|jpe?g|webp);base64,(.+)$/', $data, $m))out(['ok'=>false,'error'=>'صيغة الصورة غير صالحة']);
 $ext=$m[1]==='jpeg'?'jpg':$m[1]; $raw=base64_decode($m[2]);
 if(!$raw || strlen($raw) > 8*1024*1024)out(['ok'=>false,'error'=>'حجم الصورة غير صالح']);
 $dir=__DIR__.'/uploads/receipts'; if(!is_dir($dir))@mkdir($dir,0755,true);
 $fname='receipt_'.$no.'_'.substr(bin2hex(random_bytes(4)),0,8).'.'.$ext;
 if(!@file_put_contents($dir.'/'.$fname,$raw))out(['ok'=>false,'error'=>'تعذر حفظ الصورة على السيرفر']);
 $rel='uploads/receipts/'.$fname;
 $up=$db->prepare('UPDATE orders SET receipt_file=? WHERE id=?'); $up->execute([$rel,$o['id']]);
 audit($db,'receipt_uploaded','Order='.$no,$o['id']);
 out(['ok'=>true,'receipt_file'=>$rel]);
}
if($action==='order_history'){
 if(!admin_ok())out(['ok'=>false,'error'=>'Unauthorized']);$id=(int)($_GET['id']??0);$u=auth_user();$st=$db->prepare($u['role']==='owner'?'SELECT * FROM orders WHERE id=?':'SELECT * FROM orders WHERE id=? AND branch_id=?');if($u['role']==='owner')$st->execute([$id]);else $st->execute([$id,$u['branch_id']]);if(!$st->fetch(PDO::FETCH_ASSOC))out(['ok'=>false,'error'=>'Order not found']);$st=$db->prepare('SELECT id,action,order_id,details,created_at FROM audit_log WHERE order_id=? ORDER BY id DESC');$st->execute([$id]);out(['ok'=>true,'logs'=>$st->fetchAll(PDO::FETCH_ASSOC)]);
}
if($action==='customer_edit_order'){
 $b=json_decode(file_get_contents('php://input'),true)?:[];$no=trim((string)($b['order_no']??''));$token=trim((string)($b['access_token']??''));$items=$b['items']??[];
 if(!$no||!$token||!is_array($items))out(['ok'=>false,'error'=>'بيانات التعديل غير مكتملة']);
 $hash=hash('sha256',$token);$st=$db->prepare('SELECT * FROM orders WHERE order_no=? AND customer_token_hash=?');$st->execute([$no,$hash]);$o=$st->fetch(PDO::FETCH_ASSOC);if(!$o)out(['ok'=>false,'error'=>'بيانات الوصول غير صحيحة']);
 if(!in_array($o['status'],['new','accepted'],true) || (int)$o['cancel_requested'])out(['ok'=>false,'error'=>'لا يمكن تعديل الطلب في هذه المرحلة']);
 $menuMap=[];foreach(menu($db)['items'] as $mi)$menuMap[(string)$mi['id']]=$mi;
 $ovst=$db->prepare('SELECT item_id,available,price FROM branch_item_overrides WHERE branch_id=?');$ovst->execute([$o['branch_id']]);foreach($ovst->fetchAll(PDO::FETCH_ASSOC) as $z){if(isset($menuMap[(string)$z['item_id']])){if((int)$z['available']===0)$menuMap[(string)$z['item_id']]['available']=false;if($z['price']!==null)$menuMap[(string)$z['item_id']]['price']=(float)$z['price'];}}
 $normalized=[];$subtotal=0;
 foreach($items as $x){$iid=(string)($x['item_id']??$x['id']??'');if(!$iid||!isset($menuMap[$iid]))out(['ok'=>false,'error'=>'أحد الأصناف غير متاح']);$it=$menuMap[$iid];if(isset($it['available'])&&$it['available']===false)out(['ok'=>false,'error'=>'أحد الأصناف غير متاح حاليًا']);$pm=(string)($it['pricing_mode']??'qty');$mode=$pm==='weight'?'weight':($pm==='plate'?'plate':'qty');$qty=max(0,(float)($x['qty']??0));$weight=$x['weight']??null;$weight=($weight===null||$weight==='')?null:max(0,(float)$weight);$note=trim((string)($x['note']??''));
  if($mode==='weight'){if($weight===null||$weight<150)out(['ok'=>false,'error'=>'أدخل وزنًا صحيحًا (150 جم على الأقل)']);$weight=round($weight/50)*50;$qty=($it['cat']==='fish')?max(1,$qty):1;$line=round((float)$it['price']*$weight/1000*$qty,2);$choice=number_format($weight/1000,3,'.','').' كجم';}else{$weight=null;$qty=min(99,$qty);$line=round((float)$it['price']*$qty,2);$choice='';}
  if($qty<=0)continue;$subtotal+=$line;$normalized[]=['item_id'=>$iid,'item_name'=>$it['ar']??$it['en']??$iid,'qty'=>$qty,'weight'=>$weight,'note'=>$note,'unit_price'=>(float)$it['price'],'line_total'=>$line,'choice'=>$choice,'pricing_mode'=>$mode];
 }
 if(!$normalized)out(['ok'=>false,'error'=>'يجب الاحتفاظ بصنف واحد على الأقل']);
 $taxRate=max(0,(float)($o['tax_rate']??14));$tax=round($subtotal*$taxRate/100,2);$quote=delivery_quote($db,$o['branch_id'],isset($o['lat'])&&$o['lat']!==''?(float)$o['lat']:null,isset($o['lng'])&&$o['lng']!==''?(float)$o['lng']:null);$manualDelivery=(int)($o['delivery_manual_override']??0)===1;$delivery=$manualDelivery?max(0,(float)($o['delivery_fee']??0)):$quote['fee'];$discRate=max(0,min(100,(float)($o['discount_rate']??10)));$gross=round($subtotal+$tax+$delivery,2);$disc=round($gross*$discRate/100,2);$total=round($gross-$disc,2);
 $db->beginTransaction();try{
  $del=$db->prepare('DELETE FROM order_items WHERE order_id=?');$del->execute([(int)$o['id']]);$ins=$db->prepare('INSERT INTO order_items(order_id,item_id,item_name,qty,weight,note,unit_price,line_total,choice,pricing_mode,actual_qty,actual_weight,actual_line_total) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)');foreach($normalized as $n)$ins->execute([(int)$o['id'],$n['item_id'],$n['item_name'],$n['qty'],$n['weight'],$n['note'],$n['unit_price'],$n['line_total'],$n['choice'],$n['pricing_mode'],$n['qty'],$n['weight'],$n['line_total']]);
  $up=$db->prepare("UPDATE orders SET subtotal=?,tax_amount=?,delivery_fee=?,delivery_distance_km=?,delivery_rate=?,discount=?,total=?,price_pending=1,customer_confirmed=0,customer_confirmed_at=NULL,confirmed_by=NULL,status='new',last_modified_at=CURRENT_TIMESTAMP,customer_edit_count=COALESCE(customer_edit_count,0)+1,cancel_requested=0,cancel_reason=NULL,cancel_requested_at=NULL WHERE id=?");$up->execute([$subtotal,$tax,$delivery,$quote['distance_km'],$quote['rate'],$disc,$total,$o['id']]);
  audit($db,'customer_order_edited','Customer edited order; old_total='.$o['total'].' new_total='.$total,$o['id']);$db->commit();
 }catch(Throwable $e){$db->rollBack();out(['ok'=>false,'error'=>'تعذر حفظ تعديل الطلب']);}
 out(['ok'=>true,'status'=>'new','price_pending'=>1,'total'=>$total,'message'=>'تم تعديل الطلب وإعادته لمراجعة الوزن والسعر']);
}
if($action==='customer_cancel_order'){
 $b=json_decode(file_get_contents('php://input'),true)?:[];$no=trim((string)($b['order_no']??''));$token=trim((string)($b['access_token']??''));$reason=trim((string)($b['reason']??''));if(!$no||!$token||mb_strlen($reason)<3)out(['ok'=>false,'error'=>'رقم الطلب والسبب مطلوبان']);$hash=hash('sha256',$token);$st=$db->prepare('SELECT * FROM orders WHERE order_no=? AND customer_token_hash=?');$st->execute([$no,$hash]);$o=$st->fetch(PDO::FETCH_ASSOC);if(!$o)out(['ok'=>false,'error'=>'بيانات الوصول غير صحيحة']);
 if(in_array($o['status'],['preparing','ready','out_for_delivery','delivered','cancelled'],true))out(['ok'=>false,'error'=>'لا يمكن إلغاء الطلب في هذه المرحلة']);
 if($o['status']==='new' && !(int)$o['customer_confirmed']){$up=$db->prepare("UPDATE orders SET status='cancelled',cancelled_at=CURRENT_TIMESTAMP,cancel_reason=?,last_modified_at=CURRENT_TIMESTAMP WHERE id=? AND status='new'");$up->execute([$reason,$o['id']]);audit($db,'customer_order_cancelled','Customer cancelled directly: '.$reason,$o['id']);reverse_recipe_ingredients($db,$o['id']);out(['ok'=>true,'status'=>'cancelled','direct'=>true]);}
 $up=$db->prepare("UPDATE orders SET cancel_requested=1,cancel_reason=?,cancel_requested_at=CURRENT_TIMESTAMP,last_modified_at=CURRENT_TIMESTAMP WHERE id=? AND status='accepted'");$up->execute([$reason,$o['id']]);audit($db,'customer_cancel_requested','Customer requested cancellation: '.$reason,$o['id']);out(['ok'=>true,'status'=>'accepted','cancel_requested'=>1,'message'=>'تم إرسال طلب الإلغاء للمسؤول للمراجعة']);
}
if($action==='approve_customer_cancel'){
 if(!admin_ok() || !in_array(role_name(),['owner','manager','branch'],true))out(['ok'=>false,'error'=>'لا تملك صلاحية اعتماد إلغاء العميل']);$b=json_decode(file_get_contents('php://input'),true)?:[];$id=(int)($b['id']??0);$u=auth_user();$st=$db->prepare($u['role']==='owner'?'SELECT * FROM orders WHERE id=?':'SELECT * FROM orders WHERE id=? AND branch_id=?');$u['role']==='owner'?$st->execute([$id]):$st->execute([$id,$u['branch_id']]);$o=$st->fetch(PDO::FETCH_ASSOC);if(!$o||!(int)$o['cancel_requested'])out(['ok'=>false,'error'=>'لا يوجد طلب إلغاء معلق']);$reason=trim((string)($b['reason']??''));$finalReason=$reason!==''?$reason:($o['cancel_reason']??'Customer cancellation approved');$up=$db->prepare("UPDATE orders SET status='cancelled',cancelled_at=CURRENT_TIMESTAMP,cancel_reason=?,last_modified_at=CURRENT_TIMESTAMP WHERE id=? AND cancel_requested=1");$up->execute([$finalReason,$id]);audit($db,'customer_cancel_approved','Cancellation approved by '.($u['username']??role_name()).': '.$finalReason,$id);reverse_recipe_ingredients($db,$id);out(['ok'=>true,'status'=>'cancelled']);
}
if($action==='track_order'){
 $no=trim($_GET['order_no']??'');$token=trim((string)($_GET['access_token']??''));$phone=preg_replace('/\D/','',$_GET['phone']??'');if(!$no||(!$token&&!$phone))out(['ok'=>false,'error'=>'Missing']);
 if($token){$hash=hash('sha256',$token);$st=$db->prepare('SELECT id,order_no,order_day,daily_serial,customer_name,payment,total,status,created_at,price_pending,customer_confirmed,customer_confirmed_at,branch_id,subtotal,tax_rate,tax_amount,delivery_fee,discount_rate,discount,receipt_file,cancel_requested,cancel_reason,cancel_requested_at,customer_edit_count FROM orders WHERE order_no=? AND customer_token_hash=?');$st->execute([$no,$hash]);}
 else {$st=$db->prepare('SELECT id,order_no,order_day,daily_serial,customer_name,payment,total,status,created_at,price_pending,customer_confirmed,customer_confirmed_at,branch_id,subtotal,tax_rate,tax_amount,delivery_fee,discount_rate,discount,receipt_file,cancel_requested,cancel_reason,cancel_requested_at,customer_edit_count FROM orders WHERE order_no=? AND phone=?');$st->execute([$no,$phone]);}
 $o=$st->fetch(PDO::FETCH_ASSOC);if(!$o)out(['ok'=>false,'error'=>'Order not found']);$qi=$db->prepare('SELECT item_id,item_name,qty,weight,actual_qty,actual_weight,unit_price,line_total,actual_line_total,choice,pricing_mode,note,method_name,method_price FROM order_items WHERE order_id=? ORDER BY id');$qi->execute([(int)$o['id']]);$o['items']=$qi->fetchAll(PDO::FETCH_ASSOC);unset($o['id']);out(['ok'=>true,'order'=>$o]);
}

if($action==='admin_events'){
 if(!admin_ok())out(['ok'=>false,'error'=>'Unauthorized']); $after=max(0,(int)($_GET['after']??0)); $u=auth_user();
 if($u['role']==='owner'){$st=$db->prepare('SELECT al.id,al.action,al.order_id,al.details,al.created_at,o.order_no,o.customer_name,o.branch_id FROM audit_log al LEFT JOIN orders o ON o.id=al.order_id WHERE al.id>? ORDER BY al.id ASC LIMIT 100');$st->execute([$after]);}
 else{$st=$db->prepare('SELECT al.id,al.action,al.order_id,al.details,al.created_at,o.order_no,o.customer_name,o.branch_id FROM audit_log al LEFT JOIN orders o ON o.id=al.order_id WHERE al.id>? AND (o.branch_id=? OR al.order_id IS NULL) ORDER BY al.id ASC LIMIT 100');$st->execute([$after,$u['branch_id']]);}
 out(['ok'=>true,'events'=>$st->fetchAll(PDO::FETCH_ASSOC)]);
}
if($action==='backup'){if(!admin_ok())out(['ok'=>false,'error'=>'Unauthorized']);$zip=new ZipArchive();$file=sys_get_temp_dir().'/seagull_backup_'.date('Ymd_His').'.zip';if($zip->open($file,ZipArchive::CREATE)!==TRUE)out(['ok'=>false,'error'=>'Backup failed']);foreach(['orders.sqlite','menu.json','api.php','admin.html','index.php'] as $f)if(file_exists(__DIR__.'/'.$f))$zip->addFile(__DIR__.'/'.$f,$f);if(is_dir(__DIR__.'/receipts'))foreach(glob(__DIR__.'/receipts/*') as $f)$zip->addFile($f,'receipts/'.basename($f));if(is_dir(__DIR__.'/uploads/receipts'))foreach(glob(__DIR__.'/uploads/receipts/*') as $f)$zip->addFile($f,'uploads/receipts/'.basename($f));$zip->close();header('Content-Type: application/zip');header('Content-Disposition: attachment; filename="SeaGull_Backup_'.date('Ymd_His').'.zip"');readfile($file);unlink($file);exit;}
if($action==='audit_logs'){
 if(!admin_ok() || auth_user()['role']!=='owner')out(['ok'=>false,'error'=>'Forbidden']);
 $rows=$db->query("SELECT id,action,order_id,details,created_at FROM audit_log ORDER BY id DESC LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);
 out(['ok'=>true,'logs'=>$rows]);
}
if($action==='reset_branch_data'){
 if(!admin_ok() || auth_user()['role']!=='owner')out(['ok'=>false,'error'=>'Forbidden']); $bid=trim((string)($_GET['branch_id']??'')); if($bid==='')out(['ok'=>false,'error'=>'Branch required']);
 $st=$db->prepare('SELECT id,name FROM branches WHERE id=?');$st->execute([$bid]);$br=$st->fetch(PDO::FETCH_ASSOC);if(!$br)out(['ok'=>false,'error'=>'Branch not found']);
 $st=$db->prepare('DELETE FROM order_items WHERE order_id IN (SELECT id FROM orders WHERE branch_id=?)');$st->execute([$bid]);$st=$db->prepare('DELETE FROM orders WHERE branch_id=?');$st->execute([$bid]);$st=$db->prepare('DELETE FROM reviews WHERE 1=0');$st->execute();audit($db,'branch_reset','Branch reset: '.$br['name']);out(['ok'=>true,'scope'=>'branch','branch_id'=>$bid,'branch_name'=>$br['name']]);
}
if($action==='reset_data'){
 if(!admin_ok() || auth_user()['role']!=='owner')out(['ok'=>false,'error'=>'Forbidden']);$db->exec('DELETE FROM order_items');$db->exec('DELETE FROM orders');$db->exec('DELETE FROM reviews');$db->exec("DELETE FROM sqlite_sequence WHERE name IN ('orders','order_items','reviews')");audit($db,'global_reset','Owner cleared orders and reviews for all branches');out(['ok'=>true,'scope'=>'all_branches']);
}
if($action==='create_review'){
 $b=json_decode(file_get_contents('php://input'),true)?:[];$rating=(int)($b['rating']??0);if($rating<1||$rating>5)out(['ok'=>false,'error'=>'Invalid rating']);$service=max(1,min(5,(int)($b['service_rating']??$rating)));$food=max(1,min(5,(int)($b['food_rating']??$rating)));$st=$db->prepare('INSERT INTO reviews(name,rating,service_rating,food_rating,comment,approved) VALUES(?,?,?,?,?,1)');$st->execute([$b['name']??'عميل',$rating,$service,$food,trim($b['comment']??'')]);out(['ok'=>true]);
}
if($action==='list_reviews'){
 $admin=admin_ok();$sql=$admin?'SELECT * FROM reviews ORDER BY id DESC':'SELECT * FROM reviews WHERE approved=1 ORDER BY id DESC';$rows=$db->query($sql)->fetchAll(PDO::FETCH_ASSOC);out(['ok'=>true,'reviews'=>$rows]);
}
if($action==='approve_review' || $action==='delete_review'){
 if(!admin_ok())out(['ok'=>false,'error'=>'Unauthorized']);$b=json_decode(file_get_contents('php://input'),true)?:[];$id=(int)($b['id']??0);if($action==='approve_review'){$st=$db->prepare('UPDATE reviews SET approved=? WHERE id=?');$st->execute([(int)($b['approved']??1),$id]);}else{$st=$db->prepare('DELETE FROM reviews WHERE id=?');$st->execute([$id]);}out(['ok'=>true]);
}
out(['ok'=>false,'error'=>'Unknown action']);
?>
