<?php
define('API_KEY', '');
define('MODE', 'dialog');
define('CHANNEL_ID', );
define('CHANNEL_SECRET', '');
define('MID', '');
require_once(__DIR__ . '/vendor/autoload.php');
use jp3cki\docomoDialogue\Dialogue;
use Symfony\Component\HttpFoundation\Request;
error_log('aga');
$app = new Silex\Application();
$app->post('/callback', function (Request $request) use ($app) {


$request = file_get_contents("php://input");
$json = json_decode($request);
$content = $json->result[0]->content;
$message = $content->text;

// コンテキストIDを取得
$from = $content->from;
$redis = new Redis();
$redis->connect("127.0.0.1",6379);
$context = $redis->get($from);
$dialog = new Dialogue(API_KEY);

// 送信パラメータの準備
$dialog->parameter->reset();
$dialog->parameter->utt = $message;
$dialog->parameter->context = $context;
$dialog->parameter->mode = MODE;

// 対話
$ret = $dialog->request();
// コンテキストIDを保存
$redis->set($from, $ret->context);

$response_format_text = ['contentType'=>1, "toType"=>1, "text"=>"$ret->utt"];
$post_data = ["to"=>[$from],"toChannel"=>"1383378250","eventType"=>"138311608800106203","content"=>$response_format_text];

// LINEに送信
$ch = curl_init("https://trialbot-api.line.me/v1/events");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json; charser=UTF-8',
    "X-Line-ChannelID: " . CHANNEL_ID,
    "X-Line-ChannelSecret: " . CHANNEL_SECRET,
    "X-Line-Trusted-User-With-ACL: " . MID,
]);
curl_exec($ch);
curl_close($ch);
});
$app->run();
