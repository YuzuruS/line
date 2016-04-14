<?php
// 設定
define('DOCOMO_API_KEY', '');
define('DOCOMO_MODE', 'dialog');
define('LINE_CHANNEL_ID', '');
define('LINE_CHANNEL_SECRET', '');
define('LINE_MID', '');

require_once(__DIR__ . '/vendor/autoload.php');
use jp3cki\docomoDialogue\Dialogue;

$app = new Silex\Application();
$app->post('/callback', function (Request $request) use ($app) {
    // リクエスト取得
    $request = file_get_contents("php://input");
    $json = json_decode($request);
    $content = $json->result[0]->content;
    $message = $content->text;

    // コンテキストIDを取得
    $from = $content->from;
    $redis = new Redis();
    $redis->connect("127.0.0.1",6379);
    $context = $redis->get($from);

    // 送信パラメータの準備
    $dialog = new Dialogue(DOCOMO_API_KEY);
    $dialog->parameter->reset();
    $dialog->parameter->utt = $message;
    $dialog->parameter->context = $context;
    $dialog->parameter->mode = DOCOMO_MODE;

    // 対話
    $ret = $dialog->request();
    // コンテキストIDを保存
    $redis->set($from, $ret->context);

    $response_format_text = [
        'contentType' => 1,
        "toType" => 1,
        "text" => "$ret->utt"
    ];
    $post_data = [
        "to" => [
            $from
        ],
        "toChannel" => "1383378250", // 固定値
        "eventType" => "138311608800106203", // 固定値
        "content" => $response_format_text
    ];

    // LINEに送信
    $ch = curl_init("https://trialbot-api.line.me/v1/events");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json; charser=UTF-8',
        "X-Line-ChannelID: " . LINE_CHANNEL_ID,
        "X-Line-ChannelSecret: " . LINE_CHANNEL_SECRET,
        "X-Line-Trusted-User-With-ACL: " . LINE_MID,
    ]);
    curl_exec($ch);
    curl_close($ch);
});
$app->run();
