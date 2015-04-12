<?php
/**
 * rep2expack - tGrep 検索結果のレンダリング for PC
 */
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="ja" lang="ja">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=Shift_JIS" />
    <meta http-equiv="Content-Style-Type" content="text/css" />
    <meta http-equiv="Content-Script-Type" content="text/javascript" />
    <meta name="ROBOTS" content="NOINDEX, NOFOLLOW" />
    <?php echo $_conf['extra_headers_xht']; ?>
    <title>tGrep<?php if (strlen($htm['query']) > 0) { echo ' - ', $htm['query']; } ?></title>
    <link rel="stylesheet" type="text/css" href="css.php?css=style&amp;<?php echo $htm['skin_q']; ?>" />
    <link rel="stylesheet" type="text/css" href="css.php?css=subject&amp;<?php echo $htm['skin_q']; ?>" />
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico" />
    <style type="text/css">
    /* <![CDATA[ */
    div.tgrep_message {
        margin:0; padding:0; line-height:120%;
        <?php echo $htm['message_background'], $htm['message_border'], $htm['message_color']; ?>
    }
    div.tgrep_message h4 {
        margin:1em;
    }
    div.tgrep_message ul {
        margin:1em; padding:0 0 0 2em;
    }
    div.tgrep_result {
        margin:0; padding:2px 8px; line-height:100%; white-space:nowrap;
        <?php echo $htm['message_background'], $htm['message_color']; ?>
    }
    tr.tablefooter td {
        padding:2px; text-align:center;
        border-top:<?php echo $STYLE['sb_th_bgcolor']; ?> solid 1px;
        <?php echo $htm['message_background'], $htm['message_color']; ?>
    }
    /* ]]> */
    </style>
    <script type="text/javascript" src="js/basic.js?<?php echo P2_VERSION_ID; ?>"></script>
    <script type="text/javascript" src="js/respopup.js?<?php echo P2_VERSION_ID; ?>"></script>
    <script type="text/javascript" src="js/motolspopup.js?<?php echo P2_VERSION_ID; ?>"></script>
    <script type="text/javascript">
    //<![CDATA[
    function setWinTitle() {
        if (top != self) {top.document.title=self.document.title;}
    }
    function sf() {
        <?php if (strlen($htm['query']) == 0) { echo 'document.getElementById("Q").focus();'; } ?>
    }
    function moveSubject(href, serachWord, query) {
        if(query == ""){
            return ;
        }
        location.href = href + '?word=' + serachWord + query;
    }
    //]]>
    </script>
</head>
<body onload="sf();setWinTitle();gIsPageLoaded=true;">

<!-- Toolbar1 -->
<table id="sbtoolbar1" class="toolbar" cellspacing="0">
<tr>
    <td class="toolbar-title"><span class="itatitle" id="top"><a class="aitatitle" href="<?php echo $htm['tgrep_url']; ?>" target="_blank"><b>tGrep for rep2</b></a></span></td>
    <td class="toolbar-filter">
        <form id="searchForm" name="searchForm" action="<?php echo $htm['php_self']; ?>" method="get" accept-charset="<?php echo $_conf['accept_charset']; ?>">
        <input id="Q" name="Q" <?php echo $htm['search_attr']; ?> />
        <input type="hidden" name="maxResult" value="<?php echo $_GET['maxResult']; ?>">
        <input type="hidden" name="AndOr" value="<?php echo $_GET['AndOr']; ?>">
        <input type="hidden" name="Sort" value="<?php echo $_GET['Sort']; ?>">
        <input type="hidden" name="924" value="<?php echo $_GET['924']; ?>">
        <input type="submit" value="検索" />
        <?php echo $_conf['detect_hint_input_xht'], $_conf['k_input_ht']; ?>
        </form>
    </td>
    <td class="toolbar-anchor"><?php if ($threads) { ?><a class="toolanchor" href="#sbtoolbar2" target="_self">▼</a><?php } else { ?>　<?php } ?></td>
</tr>
</table>

<?php if (!$query) { ?>
<!-- HowTo -->
<div class="tgrep_message">
<h4>スレタイ検索について</h4>
<ul>
    <li>キーワードはスペース区切りで3つまで指定でき、すべてを含むものが抽出されます。</li>
    <li>2つ目以降のキーワードで頭に&quot;-&quot;をつけると、それを含まないものが抽出されます。</li>
    <li>&quot; または &#39; で囲まれた部分はスペースが入っていても一つのキーワードとして扱います。</li>
    <li>キーワードの全角半角、大文字小文字は無視されます。</li>
    <li>データベースの更新頻度は1時間に1回で、レス数・勢い・活発さは更新時点での値です。</li>
</uL>
</div>
<?php } ?>
<?php if ($errors) { ?>
<!-- Errors -->
<div class="tgrep_message">
<h4>エラー</h4>
<ul><?php foreach ($errors as $error) { ?><li><?php echo p2h($error); ?></li><?php } ?></ul>
</div>
<?php } ?>

<?php if (!$errors && $profile) { ?>

<!-- Result and Filter -->
<div class="tgrep_result">
<?php if ($htm['category'] && isset($profile['categories'][$htm['category']])) { ?>
<b><?php echo p2h($profile['categories'][$htm['category']]->name); ?></b> から <b><?php echo $htm['query']; ?></b> を検索: <?php echo $htm['hits']; ?> hit! (all: <?php echo $htm['allhits']; ?>)
<?php } else { ?>
<b><?php echo $htm['query']; ?></b> で検索: <?php echo $htm['hits']; ?> hit!
<?php } ?>
<input id="h_php_self" type="hidden" value="<?php echo $htm['php_self']; ?>" />
<input id="h_query_en" type="hidden" value="<?php echo $htm['query_en']; ?>" />
<input id="h_read_php" type="hidden" value="<?php echo $_conf['read_php']; ?>" />
<input id="h_subject_php" type="hidden" value="<?php echo $_conf['subject_php']; ?>" />
| カテゴリで絞り込む:
<select onchange="location.href=document.getElementById('h_php_self').value+'?Q='+document.getElementById('h_query_en').value+this.options[this.selectedIndex].value">
<option value="">-</option>
<?php if(isset($profile['categories'])){ foreach ($profile['categories'] as $c) { ?><option value="&amp;C=<?php echo $c->id; ?>"<?php if ($c->id == $htm['category']) { echo ' selected="selected"'; } ?>><?php echo p2h($c->name); ?> (<?php echo $c->hits; ?>)</option><?php } } ?>
</select>
| 板で絞り込む:
<select onchange="moveSubject(document.getElementById('h_subject_php').value, document.getElementById('h_query_en').value, this.options[this.selectedIndex].value);">
<option value="">-</option>
<?php $m = ($htm['category'] && isset($profile['categories'][$htm['category']])) ? $profile['categories'][$htm['category']]->member : null; ?>
<?php foreach ($profile['boards'] as $n => $b) { if (!$m || in_array($n, $m)) { ?><option value="<?php printf('&amp;host=%s&amp;bbs=%s&amp;itaj_en=%s', $b->host, $b->bbs, UrlSafeBase64::encode($b->name)); ?>"><?php echo p2h($b->name); ?> (<?php echo $b->hits; ?>)</option><?php } } ?>
</select>
</div>
<?php } ?>

<?php if ($threads) { ?>
<!-- ThreadList and Pager -->
<table class="threadlist" cellspacing="0">
<thead>
<tr class="tableheader">
    <th class="ti">新着</th>
    <th class="ti">レス</th>
    <th class="ti">No.</th>
    <th class="tl">タイトル</th>
    <th class="t">板</th>
    <th class="t">Birthday</th>
    <th class="ti">勢い</th>
</tr>
</thead>
<tbody>
<?php
$R = true;
foreach ($threads as $o => $t) {
    $new = '';
    $turl = sprintf('%s?host=%s&amp;bbs=%s&amp;key=%d', $_conf['read_php'], $t->host, $t->bbs, $t->tkey);
    $burl = sprintf('%s?host=%s&amp;bbs=%s&amp;itaj_en=%s&amp;word=%s', $_conf['subject_php'], $t->host, $t->bbs, UrlSafeBase64::encode($t->ita), $htm['query_en']);
    if (P2Util::isHostMachiBbs($t->host)) {
        $ourl = sprintf('http://%s/bbs/read.cgi/%s/%s/', $t->host, $t->bbs, $t->tkey);
    } else {
        $ourl = sprintf('http://%s/test/read.cgi/%s/%s/', $t->host, $t->bbs, $t->tkey);
    }
    $iurl = P2Util::throughIme($ourl);
    $aThread = new Thread();
    $aThread->setThreadPathInfo($t->host, $t->bbs, $t->tkey);
    if ($aThread->getThreadInfoFromIdx() && $aThread->isKitoku()) {
        $rnum = max($t->resnum, $aThread->readnum);
        $nnum = max(0, $rnum - $aThread->readnum);
    } else {
        $rnum = $t->resnum;
        $nnum = '';
    }
?>
<tr class="<?php echo $R ? 'r1 r_odd' : 'r2 r_even'; $R = !$R; ?>">
    <td class="ti"><?php echo $nnum; ?></td>
    <td class="ti"><?php echo $rnum; ?></td>
    <td class="ti"><?php echo $o+1; ?></td>
    <td class="tl"><a href="<?php echo $ourl; ?>" class="moto_thre" onmouseover="showMotoLsPopUp(event, this, this.nextSibling.innerText)" onmouseout="hideMotoLsPopUp()" target="read">・</a><a href="<?php echo $turl; ?>" target="read"><?php echo $t->title; ?></a></td>
    <td class="t"><a href="<?php echo $burl; ?>"><?php echo $t->ita; ?></a></td>
    <td class="t"><?php echo date('y/m/d', $t->tkey); ?></td>
    <td class="ti"><?php echo round($t->dayres, 2); ?></td>
</tr>
<?php } ?>
</tbody>
<?php if ($htm['pager']) { ?>
<tfoot>
<tr class="tablefooter">
    <td colspan="7"><?php echo $htm['pager']; ?></td>
</tr>
</tfoot>
<?php } ?>
</table>
<table>
<tr>
    <!--dig2chの使用条件のため表示-->
    <td><?php if ($_conf['test.search_dig2ch']) { echo $profile['cm0'];}?></td>
    <td><?php if ($_conf['test.search_dig2ch']) { echo $profile['cm1'];}?></td>
    <td><?php if ($_conf['test.search_dig2ch']) { echo $profile['cm2'];}?></td>
</tr>
</table>
<?php } ?>

<?php if ($threads) { ?>
<!-- Toolbar2 -->
<table id="sbtoolbar2" class="toolbar" cellspacing="0">
<tr>
    <td class="toolbar-title"><span class="itatitle" id="bottom"><a class="aitatitle" href="<?php echo $htm['tgrep_url']; ?>" target="_blank"><b>tGrep for rep2</b></a></span></td>
    <td class="toolbar-filter">
        <form id="searchForm2" name="searchForm2" action="<?php echo $htm['php_self']; ?>" method="get" accept-charset="<?php echo $_conf['accept_charset']; ?>">
        <input id="Q2" name="Q" <?php echo $htm['search_attr']; ?> />
        <input type="hidden" name="maxResult" value="<?php echo $_GET['maxResult']; ?>">
        <input type="hidden" name="AndOr" value="<?php echo $_GET['AndOr']; ?>">
        <input type="hidden" name="Sort" value="<?php echo $_GET['Sort']; ?>">
        <input type="hidden" name="924" value="<?php echo $_GET['924']; ?>">
        <input type="submit" value="検索" />
        <?php echo $_conf['detect_hint_input_xht'], $_conf['k_input_ht']; ?>
        </form>
    </td>
    <td class="toolbar-anchor"><a class="toolanchor" href="#sbtoolbar1" target="_self">▲</a></td>
</tr>
</table>
<?php } ?>

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
