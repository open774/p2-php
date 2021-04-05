<?php
/*
	+live - 実況表示用2ペインフレーム
*/

require_once __DIR__ . '/../init.php';

$_login->authorize(); // ユーザ認証

// 変数
if (empty($_GET['host'])) {
	// 引数エラー
	die('p2 error: host が指定されていません');
} else {
	$host = $_GET['host'];
}
$bbs = isset($_GET['bbs']) ? $_GET['bbs'] : '';
$key = isset($_GET['key']) ? $_GET['key'] : '';
$rescount = isset($_GET['rescount']) ? intval($_GET['rescount']) : 1;

$itaj = P2Util::getItaName($host, $bbs);
if (!$itaj) { $itaj = $bbs; }

$ttitle_en = isset($_GET['ttitle_en']) ? $_GET['ttitle_en'] : '';
$ttitle = (strlen($ttitle_en) > 0) ? UrlSafeBase64::decode($ttitle_en) : '';
$ttitle_hd = htmlspecialchars($ttitle, ENT_QUOTES);
$ttitle_urlen = rawurlencode($ttitle_en);
$ttitle_en_q = "&amp;ttitle_en=" . $ttitle_urlen;

$live_read = "live_read.php";
$live_q = "&amp;live=1";

$offline_q = '';
if ($_GET['offline']) {
	$offline_l = isset($_GET['offline']) ? $_GET['offline'] : '';
	$offline_q = "&amp;offline=" . $offline_l;
	$live_read = "read.php";
	$live_q = "&amp;live=0";
}

$word_q = '';
if ($_GET['word']) {
	$word_l = isset($_GET['word']) ? $_GET['word'] : '';
	$method_l = isset($_GET['method']) ? $_GET['method'] : '';
	$word_q = "&amp;word=" . $word_l . "&amp;method=" . $method_l;
	$live_read = "read.php";
	$live_q = "&amp;live=0";
}

// HTMLプリント
P2Util::header_nocache();
P2Util::header_content_type();

if ($_conf['doctype']) { echo $_conf['doctype']; }

echo <<<LIVE
<html lang="ja">
	<head>
		<meta name="ROBOTS" content="NOINDEX, NOFOLLOW">
		<meta http-equiv="Content-Style-Type" content="text/css">
		<meta http-equiv="Content-Script-Type" content="text/javascript">
		<title>{$itaj} / {$ttitle_hd}</title>
		<link href="favicon.ico" type="image/x-icon" rel="shortcut icon">
	</head>
	
	<frameset rows="*,{$_conf['live.post_width']}" frameborder="1" border="1">
		<frame src="{$live_read}?host={$host}&amp;bbs={$bbs}&amp;key={$key}{$ttitle_en_q}&amp;rescount={$rescount}{$offline_q}{$word_q}{$live_q}#r{$rescount}" name="liveread" scrolling="auto">
		<frameset cols="*,100" border="0" frameborder="no" framespacing="0">
			<frame src="live_post_form.php?host={$host}&amp;bbs={$bbs}&amp;key={$key}{$ttitle_en_q}" name="livepost" scrolling="auto">
			<frame src="live_control.php?{$word_q}{$live_q}" name="livecontrol" scrolling="no">
		</frameset>
	</frameset>
</html>
LIVE;

?>