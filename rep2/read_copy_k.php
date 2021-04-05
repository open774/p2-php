<?php
/**
 * rep2 - 携帯版レスコピー
 */

require_once __DIR__ . '/../init.php';

$_login->authorize(); // ユーザ認証

$name_txt = '';
$mail_txt = '';
$date_txt = '';
$id_txt = '';
$msg_txt = '';
$url_k_ht = '';
$id_ht = '';
$back_link = '';
$post_link = '';
$moto_link = '';

//=====================================================
// スレッド情報
//=====================================================
$host = $_GET['host'];
$bbs  = $_GET['bbs'];
$key  = $_GET['key'];
$resid = $_GET['copy'];
$quote = !empty($_GET['inyou']);

if (isset($_SERVER['HTTP_REFERER'])) {
    $back_link = '<a href="' . p2h($_SERVER['HTTP_REFERER']) . '" title="戻る">戻る</a> ';
}

//=================================================
// レス読み込み
//=================================================
$aThread = new ThreadRead();
$aThread->setThreadPathInfo($host, $bbs, $key);
if (file_exists($aThread->keydat)) {
    // スレッド情報
    $aThread->readDat($aThread->keydat);
    $one = $aThread->explodeDatLine($aThread->datlines[0]);
    $ttitle = trim($one[4]);
    $ttitle_en = UrlSafeBase64::encode($ttitle);
    $ttitle_ht = p2h($ttitle);
    $url_txt = $aThread->getMotoThread(true);
    $url_k_txt = $aThread->getMotoThread();
    if ($quote) {
        $url_txt .= $resid;
        $url_k_txt .= $resid;
    }
    if ($url_txt != $url_k_txt) {
        $url_k_ht = "<input type=\"text\" name=\"url_k_txt\" value=\"{$url_k_txt}\"><br>";
    }
    // 投稿フォームへのリンク
    $post_url = "post_form.php?host={$host}&amp;bbs={$bbs}&amp;key={$key}";
    $post_url .= "&amp;rescount={$aThread->rescount}&amp;ttitle_en={$ttitle_en}&amp;b=k";
    $post_link = "<a href=\"{$post_url}\">レス</a> ";
    // 元スレへのリンク
    $moto_link = '<a href="' . P2Util::throughIme($url_k_txt) . '">元スレ</a> ';
    // 指定番号のレスをパース
    $p = $resid - 1;
    if (isset($aThread->datlines[$p])) {
        $resar = $aThread->explodeDatLine($aThread->datlines[$p]);
        $name_txt = trim(strip_tags($resar[0]));
        $mail_txt = trim(strip_tags($resar[1]));
        if (strpos($resar[2], 'ID:') !== false) {
            $date_txt = preg_replace('/ ?ID: ?([0-9A-Za-z\/.+?]+)([.,]|†)?.*$/', '', $resar[2]);
            $id_txt = preg_replace('/^.*ID: ?([0-9A-Za-z\/.+?]+)([.,]|†)?.*$/', 'ID:$1', $resar[2]);
            $id_ht = "<input type=\"text\" name=\"id_txt\" value=\"{$id_txt}\"><br>";
        } else {
            $date_txt = $resar[2];
        }
        $msg_txt = trim(strip_tags($resar[3], '<br>'));
        if ($quote) {
            $msg_txt = "&gt;&gt;{$resid}\r\n&gt; " . preg_replace('/ *<br[^>]*> */i', "\n&gt; ", $msg_txt);
        } else {
            $msg_txt = preg_replace('/ *<br[^>]*> */i', "\n", $msg_txt);
        }
    } else {
        P2Util::pushInfoHtml('<p>p2 error: ﾚｽ番号の指定が変です｡</p>');
    }
} else {
    P2Util::pushInfoHtml('<p>p2 error: ｽﾚｯﾄﾞの指定が変です。</p>');
}

$msg_len = mb_strlen($msg_txt);
$len = $GLOBALS['_conf']['mobile.copy_divide_len'] ? $GLOBALS['_conf']['mobile.copy_divide_len'] : 10000;
$msg_txts = array();
for ($i = 0; $i < $msg_len; $i += $len) {
    $msg_txts[] = mb_substr($msg_txt, $i, $len);
}

//=====================================================
// コピー用フォームを表示
//=====================================================
$action_ht = p2h($_SERVER['SCRIPT_NAME'] . '?host=' . $_GET['host'] . '&bbs=' . $_GET['bbs'] . '&key=' . $_GET['key'] . '&copy=' . $_GET['copy']);

// willcom はtextareaのサイズが小さいと使いにくいらしい
/*
JavaScriptにしてしまった方がいいかも？
javascript:(function(){for (var j=0;j<document.forms.length;j++){for (var i=0;i<document.forms[j].elements.length;i++) {k=document.forms[j].elements[i];if(k.type=="textarea"){k.rows=10;k.cols=34;}}}})(); 
*/
$kyopon_size = '';
$mobile = (new Net_UserAgent_Mobile)->singleton();
if ($mobile->isAirHPhone()) {
    $kyopon_size = ' rows="10" cols="34"';
}

P2Util::header_nocache();
echo $_conf['doctype'];
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=Shift_JIS">
<meta name="ROBOTS" content="NOINDEX, NOFOLLOW">
<?php echo $_conf['extra_headers_ht']; ?>
<?php if ($_conf['iphone']) {
echo <<<EOS
<script type="text/javascript">
// <![CDATA[
window.addEventListener('load', function(event) {
    var read_copy_adjsut_text_width

    window.removeEventListener(event.type, arguments.callee, false);

    read_copy_adjsut_text_width = function() {
        var texts, i, l, node, width;

        texts = document.evaluate('.//input[@type="text"]',
                                  document.body,
                                  null,
                                  XPathResult.ORDERED_NODE_SNAPSHOT_TYPE,
                                  null);
        l = texts.snapshotLength;

        for (i = 0; i < l; i++) {
            node = texts.snapshotItem(i);
            width = node.parentNode.clientWidth;
            if (width > 100) {
                width -= 18; // 適当
                if (width > 480) {
                    width = 480; // maxWidth
                }
                node.style.width = width + 'px';
            }
        }
    };

    read_copy_adjsut_text_width();

    document.body.addEventListener('orientationchange', read_copy_adjsut_text_width, false);
}, false);
// ]]>
</script>\n
EOS;
} ?>
<title><?php echo $ttitle_ht; ?>/<?php echo $resid; ?></title>
</head>
<body<?php echo $k_color_settings; ?>>
<?php P2Util::printInfoHtml(); ?>
<form action="<?php echo $action_ht; ?>" method="post">
スレ:<br>
<input type="text" name="ttitle_txt" value="<?php echo $ttitle_ht; ?>"><br>
<input type="text" name="url_txt" value="<?php echo $url_txt; ?>"><br>
<?php echo $url_k_ht; ?>
<?php echo $resid; ?>:<br>
<input type="text" name="name_txt" value="<?php echo $name_txt; ?>"><br>
<input type="text" name="mail_txt" value="<?php echo $mail_txt; ?>"><br>
<input type="text" name="date_txt" value="<?php echo $date_txt; ?>"><br>
<?php echo $id_ht; ?>
<?php foreach ($msg_txts as $msg_txt) { ?>
<textarea<?php echo $kyopon_size; ?>><?php echo $msg_txt; ?></textarea><br>
<?php } ?>
フリー:<br>
<textarea name="free" rows="2"></textarea>
</form>
<div class="center navi">
<?php echo $back_link, $post_link, $moto_link; ?>
</div>
</body>
</html>
<?php
/*
 * Local Variables:
 * mode: php
 * coding: cp932
 * tab-width: 4
 * c-basic-offset: 4
 * indent-tabs-mode: nil
 * End:
 */
// vim: set syn=php fenc=cp932 ai et ts=4 sw=4 sts=4 fdm=marker:
