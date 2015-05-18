<?php
/**
 * rep2 - 設定管理
 */

require_once __DIR__ . '/../init.php';

$_login->authorize(); // ユーザ認証

$deltitles = array(
    'cookie'    => 'Cookie',
    'matome'    => '新着まとめ読み',
    'recent'    => '最近読んだスレ',
    'reshist'  => '書き込み履歴',
    'autong' => '自動 NG・あぼーん',
    'boardlist' => '板一覧 (Online)',
);

// {{{ ホストの同期用設定

$synctitles = array(
    'favita'    => 'お気に板',
    'fav'       => 'お気にスレ',
    'recent'    => '最近読んだスレ',
    'res_hist'  => '書き込み履歴',
    'palace'    => 'スレの殿堂'
);

// }}}
// {{{ 設定変更処理

// ホストの同期
if (isset($_POST['sync'])) {
    $sync_boards = array();
    $sync_indexes = array();

    switch ($_POST['sync']) {
    case 'favita':
        if ($_conf['expack.misc.multi_favs']) {
            $sync_boards[] = $_conf['orig_favita_brd'];
            for ($i = 1; $i <= $_conf['expack.misc.favset_num']; $i++) {
                $sync_boards[] = sprintf('%s/p2_favita%d.brd', $_conf['pref_dir'], $i);
            }
        } else {
            $sync_boards[] = $_conf['favita_brd'];
        }
        break;
    case 'fav':
        if ($_conf['expack.misc.multi_favs']) {
            $sync_indexes[] = $_conf['orig_favlist_idx'];
            for ($i = 1; $i <= $_conf['expack.misc.favset_num']; $i++) {
                $sync_indexes[] = sprintf('%s/p2_favlist%d.idx', $_conf['pref_dir'], $i);
            }
        } else {
            $sync_indexes[] = $_conf['favlist_idx'];
        }
        break;
    case 'recent':
        $sync_indexes[] = $_conf['recent_idx'];
        break;
    case 'res_hist':
        $sync_indexes[] = $_conf['res_hist_idx'];
        break;
    case 'palace':
        $sync_indexes[] = $_conf['palace_idx'];
        break;
    case 'all':
        if ($_conf['expack.misc.multi_favs']) {
            $sync_boards[] = $_conf['orig_favita_brd'];
            $sync_indexes[] = $_conf['orig_favlist_idx'];
            for ($i = 1; $i <= $_conf['expack.misc.favset_num']; $i++) {
                $sync_boards[] = sprintf('%s/p2_favita%d.brd', $_conf['pref_dir'], $i);
                $sync_indexes[] = sprintf('%s/p2_favlist%d.idx', $_conf['pref_dir'], $i);
            }
        } else {
            $sync_boards[] = $_conf['favita_brd'];
            $sync_indexes[] = $_conf['favlist_idx'];
        }
        $sync_indexes[] = $_conf['recent_idx'];
        $sync_indexes[] = $_conf['res_hist_idx'];
        $sync_indexes[] = $_conf['palace_idx'];
        break;
    }

    foreach ($sync_boards as $brd) {
        if (file_exists($brd)) {
            BbsMap::syncBrd($brd);
        }
    }

    foreach ($sync_indexes as $idx) {
        if (file_exists($idx)) {
            BbsMap::syncIdx($idx);
        }
    }

// お気に入りセット変更があれば、設定ファイルを書き換える
} elseif ($_conf['expack.misc.multi_favs'] && isset($_POST['favsetlist'])) {
    updateFavSetList();

} elseif (isset($_POST['delete']) && isset($_POST['submit'])) { // 履歴の削除処理
    switch ($_POST['delete']) {
        case cookie:
            // cookie
            $delflag = CookieDataStore::clear() === flase ? false : true;
            break;
        case matome:
            // 新着まとめ読み
            $delflag = MatomeCacheList::trim(0) === flase ? false : true;
            break;
        case recent:
            // 最近読んだスレ
            $delflag = deleteFile($_conf['recent_idx']);
            break;
        case reshist:
            // 書込履歴
            if(deleteFile($_conf['res_hist_idx']) &&
            deleteFile($_conf['res_hist_dat']) &&
            deleteFile($_conf['res_hist_dat_php']) ) {
                $delflag = true;
            }
            break;
        case autong:
            if(deleteFile($_conf['pref_dir'] . '/p2_aborn_auto.txt') &&
            deleteFile($_conf['pref_dir'] . '/p2_ng_auto.txt') ) {
                $delflag = true;
            }
            break;
        case boardlist:
            $cachefile = P2Util::cacheFileForDL($_conf['brdfile_online']);
            if(deleteFile($_conf['cache_dir'] . '/host_bbs_map.txt') &&
                deleteFile($cachefile) &&
                deleteFile($cachefile . '.p2.brd') ) {
                $delflag = true;
            }
            break;
        default:
            $delflag = false;
            P2Util::pushInfoHtml("<p>p2 error: 引数 {$_POST['delete']} が不正です。 ");
            break;
    }
    if ($delflag) {
        P2Util::pushInfoHtml("<p>p2 info: {$deltitles[$_POST['delete']]}を削除しました。");
    } else {
        P2Util::pushInfoHtml("<p>p2 error: {$deltitles[$_POST['delete']]}の削除に失敗しました。");
    }
}

// }}}
// {{{ 書き出し用変数

$ptitle = '設定管理';

if ($_conf['ktai']) {
    $status_st      = 'ｽﾃｰﾀｽ';
    $autho_user_st  = '認証ﾕｰｻﾞ';
    $client_host_st = '端末ﾎｽﾄ';
    $client_ip_st   = '端末IPｱﾄﾞﾚｽ';
    $browser_ua_st  = 'ﾌﾞﾗｳｻﾞUA';
    $p2error_st     = 'rep2 ｴﾗｰ';
} else {
    $status_st      = 'ステータス';
    $autho_user_st  = '認証ユーザ';
    $client_host_st = '端末ホスト';
    $client_ip_st   = '端末IPアドレス';
    $browser_ua_st  = 'ブラウザUA';
    $p2error_st     = 'rep2 エラー';
}

$autho_user_ht = '';

// }}}

//=========================================================
// HTMLプリント
//=========================================================
P2Util::header_nocache();
echo $_conf['doctype'];
echo <<<EOP
<html lang="ja">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=Shift_JIS">
    <meta http-equiv="Content-Style-Type" content="text/css">
    <meta http-equiv="Content-Script-Type" content="text/javascript">
    <meta name="ROBOTS" content="NOINDEX, NOFOLLOW">
    {$_conf['extra_headers_ht']}
    <title>{$ptitle}</title>\n
EOP;

if (!$_conf['ktai']) {
    echo <<<EOP
    <link rel="stylesheet" type="text/css" href="css.php?css=style&amp;skin={$skin_en}">
    <link rel="stylesheet" type="text/css" href="css.php?css=editpref&amp;skin={$skin_en}">
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico">
    <script type="text/javascript" src="js/jquery-{$_conf['jquery_version']}.min.js"></script>
    <script type="text/javascript" src="js/basic.js?{$_conf['p2_version_id']}"></script>
    <script type="text/javascript" src="js/changeskin.js?{$_conf['p2_version_id']}"></script>
    <script type="text/javascript" src="js/respopup.js?{$_conf['p2_version_id']}"></script>
    <script type="text/javascript" src="js/editpref.js?{$_conf['p2_version_id']}"></script>\n
EOP;
    $body_at = ' onload="window.top.document.title=window.self.document.title;"';
} elseif (!$_conf['iphone']) {
    $body_at = $_conf['k_colors'];
} else {
    $body_at = '';
}

echo <<<EOP
</head>
<body{$body_at}>\n
EOP;

if (!$_conf['ktai']) {
//<p id="pan_menu"><a href="setting.php">設定</a> &gt; {$ptitle}</p>
    echo "<p id=\"pan_menu\">{$ptitle}</p>\n";
}

P2Util::printInfoHtml();

// 設定プリント
$aborn_thread_txt   = 'p2_aborn_thread.txt';
$aborn_res_txt      = 'p2_aborn_res.txt';
$aborn_name_txt     = 'p2_aborn_name.txt';
$aborn_mail_txt     = 'p2_aborn_mail.txt';
$aborn_msg_txt      = 'p2_aborn_msg.txt';
$aborn_id_txt       = 'p2_aborn_id.txt';
$ng_name_txt        = 'p2_ng_name.txt';
$ng_mail_txt        = 'p2_ng_mail.txt';
$ng_msg_txt         = 'p2_ng_msg.txt';
$ng_id_txt          = 'p2_ng_id.txt';
// +Wiki
$aborn_be_txt       = 'p2_aborn_be.txt';
$ng_be_txt          = 'p2_ng_be.txt';
// +live ハイライトワード用
$highlight_name_txt = 'p2_highlight_name.txt';
$highlight_mail_txt = 'p2_highlight_mail.txt';
$highlight_msg_txt  = 'p2_highlight_msg.txt';
$highlight_id_txt   = 'p2_highlight_id.txt';

echo '<div>';
echo <<<EOP
<a href="edit_conf_user.php{$_conf['k_at_q']}" class="button">ユーザ設定編集</a>
EOP;
if (!$_conf['ktai'] && $_conf['expack.skin.enabled']) {
    $skin_options = array('conf_user_style' => '標準');
    foreach (array('./skin', './user_skin') as $skin_dir) {
        if (is_dir($skin_dir)) {
            foreach (glob("{$skin_dir}/*.php") as $skin_file) {
                $_name = basename($skin_file, '.php');
                if (!array_key_exists($_name, $skin_options) &&
                    is_file($skin_file) &&
                    preg_match('/^[0-9A-Za-z_\\-]+$/', $_name))
                {
                    $skin_options[$_name] = $_name;
                }
            }
        }
    }
    uksort($skin_options, 'compareSkinNames');
    $skin_options_ht = '';
    foreach ($skin_options as $_name => $_title) {
        $skin_options_ht .= sprintf('<option value="%s"%s>%s</option>',
                                    p2h($_name),
                                    ($_name == $skin_name) ? ' selected' : '',
                                    p2h($_title));
    }
    echo <<<EOP
 ｜ <a href="edit_user_font.php">フォント設定編集</a>
 ｜ スキン:<form class="inline-form" method="get" action="{$_SERVER['SCRIPT_NAME']}"
 onsubmit="changeSkinAll(this.skin.options[this.skin.selectedIndex].value, '{$_conf['p2_version_id']}'); return false;">
<select name="skin">{$skin_options_ht}</select><input type="submit" value="変更">
</form>
EOP;
}
echo '</div>';

// PC用表示
if (!$_conf['ktai']) {

    echo "<table id=\"editpref\">\n";

    // {{{ PC - NGワード編集
    echo "<tr><td>\n\n";

    echo <<<EOP
<fieldset>
<legend><a href="http://akid.s17.xrea.com/p2puki/pukiwiki.php?%5B%5BNG%A5%EF%A1%BC%A5%C9%A4%CE%C0%DF%C4%EA%CA%FD%CB%A1%5D%5D" target="read">NGワード</a>編集</legend>
EOP;
    printEditFileForm($ng_name_txt, '名前');
    printEditFileForm($ng_mail_txt, 'メール');
    printEditFileForm($ng_msg_txt, '本文');
    printEditFileForm($ng_id_txt, 'ID');
    // +Wiki
    printEditFileForm($ng_be_txt, 'ＢＥ');
    echo <<<EOP
</fieldset>\n\n
EOP;

    echo "</td>";

    // }}}
    // {{{ PC - あぼーんワード編集

    echo "<td>\n\n";

    echo <<<EOP
<fieldset>
<legend>あぼーんワード編集</legend>\n
EOP;
    printEditFileForm($aborn_res_txt, 'レス');
    printEditFileForm($aborn_name_txt, '名前');
    printEditFileForm($aborn_mail_txt, 'メール');
    printEditFileForm($aborn_msg_txt, '本文');
    printEditFileForm($aborn_id_txt, 'ID');
    printEditFileForm($aborn_thread_txt, 'スレッド');
    // +Wiki
    printEditFileForm($aborn_be_txt, 'ＢＥ');
    echo <<<EOP
</fieldset>\n
EOP;

    echo "</td></tr>";

    // }}}
	// {{{ PC - +live ハイライトワード編集

	echo "<tr><td>\n\n";

	echo <<<EOP
<fieldset>
<legend>ハイライトワード編集</legend>\n
EOP;
	printEditFileForm($highlight_name_txt, '名前');
	printEditFileForm($highlight_mail_txt, 'メール');
	printEditFileForm($highlight_msg_txt, '本文');
	printEditFileForm($highlight_id_txt, 'ID');
	echo <<<EOP
</fieldset>\n
EOP;

	echo "</td>";

	// }}}
    // {{{ PC - 2ch774 履歴・キャッシュの削除

    echo "<td>\n\n";

    echo <<<EOP
<fieldset>
<legend>履歴・キャッシュの削除</legend>\n
EOP;
    echo getDeleteHistoryFormHt();
    echo <<<EOP
</fieldset>\n
EOP;

    echo "</td></tr>";

    // }}}
    // {{{ PC - ホストの同期 HTMLのセット

    echo <<<EOP
<tr><td colspan="2">
<fieldset>
<legend>ホストの同期 （2chの板移転に対応します）</legend>
EOP;
    echo getSyncFavoritesFormHt('all', 'すべて');
    foreach ($synctitles as $syncmode => $syncname) {
        echo getSyncFavoritesFormHt($syncmode, $syncname);
    }
    echo <<<EOP
</fieldset>
</td></tr>\n
EOP;

    // }}}
    // {{{ PC - セット切り替え・名称変更

    if ($_conf['expack.misc.multi_favs']) {
        echo "<tr><td colspan=\"2\">\n\n";

        echo <<<EOP
<form action="editpref.php" method="post" accept-charset="{$_conf['accept_charset']}" target="_self" style="margin:0">
    <input type="hidden" name="favsetlist" value="1">
    <fieldset>
        <legend>セット切り替え・名称変更（セット名を空にするとデフォルトの名前に戻ります）</legend>
        <table>
            <tr>\n
EOP;
        echo "<td>\n";
        echo getFavSetListFormHt('m_favlist_set', 'お気にスレ');
        echo "</td><td>\n";
        echo getFavSetListFormHt('m_favita_set', 'お気に板');
        echo "</td><td>\n";
        echo getFavSetListFormHt('m_rss_set', 'RSS');
        echo "</td>\n";
        echo <<<EOP
            </tr>
        </table>
        <div>
            <input type="submit" value="変更">
        </div>
    </fieldset>
    {$_conf['detect_hint_input_ht']}{$_conf['k_input_ht']}
</form>\n\n
EOP;

        echo "</td></tr>\n\n";
    }

    // }}}
    // +Wiki
    // {{{ PC - +Wiki
    echo "<tr><td>\n\n";
    echo <<<EOP
<fieldset>
<legend>+Wiki</legend>
    <a href="edit_link_plugin.php{$_conf['k_at_q']}" target="_self">リンクプラグイン編集</a> ｜
    <a href="edit_dat_plugin.php{$_conf['k_at_q']}" target="_self">DAT取得プラグイン編集</a> ｜
    <a href="edit_replace_imageurl.php{$_conf['k_at_q']}" target="_self">置換画像URLプラグイン編集</a>
</fieldset>\n
EOP;
    echo "</td></tr>\n\n";

    // }}}
}

// 携帯用表示
if ($_conf['ktai']) {
    echo <<<EOP
<hr>
<p>ｱﾎﾞﾝ/NGﾜｰﾄﾞ編集</p>
<form method="GET" action="edit_aborn_word.php">
{$_conf['k_input_ht']}
<select name="file">
<option value="{$aborn_thread_txt}">ｱﾎﾞﾝ:ｽﾚ</option>
<option value="{$aborn_name_txt}">ｱﾎﾞﾝ:名前</option>
<option value="{$aborn_mail_txt}">ｱﾎﾞﾝ:ﾒｰﾙ</option>
<option value="{$aborn_msg_txt}">ｱﾎﾞﾝ:本文</option>
<option value="{$aborn_id_txt}">ｱﾎﾞﾝ:ID</option>
<option value="{$aborn_be_txt}">ｱﾎﾞﾝ:BE</option>
<option value="{$ng_name_txt}">NG:名前</option>
<option value="{$ng_mail_txt}">NG:ﾒｰﾙ</option>
<option value="{$ng_msg_txt}">NG:本文</option>
<option value="{$ng_id_txt}">NG:ID</option>
<option value="{$ng_id_txt}">NG:BE</option>
</select>
<input type="submit" value="編集">
</form>
<form method="GET" action="editfile.php">
{$_conf['k_input_ht']}
<input type="hidden" name="file" value="{$aborn_res_txt}">
<input type="submit" value="ｱﾎﾞﾝﾚｽ編集">
</form>
<hr>
EOP;
    echo "<p>ﾎｽﾄの同期（2chの板移転に対応します）</p>\n";
    echo getSyncFavoritesFormHt('all', 'すべて');
    foreach ($synctitles as $syncmode => $syncname) {
        echo getSyncFavoritesFormHt($syncmode, $syncname);
    }

    // {{{ 携帯 - セット切り替え

    if ($_conf['expack.misc.multi_favs']) {
        echo <<<EOP
<hr>
<p>お気にｽﾚ･お気に板･RSSのｾｯﾄを選択</p>
<form action="editpref.php" method="post" accept-charset="{$_conf['accept_charset']}" target="_self">
{$_conf['k_input_ht']}
EOP;
        echo getFavSetListFormHtK('m_favlist_set', 'お気にｽﾚ'), '<br>';
        echo getFavSetListFormHtK('m_favita_set', 'お気に板'), '<br>';
        echo getFavSetListFormHtK('m_rss_set', 'RSS'), '<br>';
        echo <<<EOP
<input type="submit" value="変更">
</form>
EOP;
    }

    // }}}

}

// {{{ 新着まとめ読みのキャッシュ表示

$matome_cache_list = MatomeCacheList::getList();
if ($matome_cache_list) {
    $ckeyprefixlen = strlen(MatomeCacheList::getKeyPrefix());
    $i = 0;

    if ($_conf['ktai']) {
        echo "<hr>\n<p>新着まとめ読みキャッシュ</p>\n";
    } else {
        echo "<tr><td colspan=\"2\">\n";
        echo "<fieldset>\n<legend>新着まとめ読みキャッシュ</legend>\n";
        echo "<ul id=\"matome_cache\">\n";
    }

    foreach ($matome_cache_list as $cache_key => $cache_info) {
        // PC・携帯共通変数
        $i++;
        $ckey = substr($cache_key, $ckeyprefixlen);
        $clink = 'read_new_cache.php?ckey=' . rawurlencode($ckey) . $_conf['k_at_a'];
        if ($cache_info['title']) {
            $ctitle = $cache_info['title'];
        } else {
            $ctitle = $ckey;
        }
        $cnumthreads = count($cache_info['threads']);

        if ($_conf['ktai']) {
            // キャッシュへのリンク、携帯用
            if ($_conf['iphone']) {
                $cdate = date('y/m/d H:i', $cache_info['time']);
                echo "<p>{$cdate} [<a href=\"{$clink}\">{$ctitle}</a>]</p>";
            } else {
                $cdate = date('y/m/d G:i', $cache_info['time']);
                echo "<p>{$cdate}<br>[<a href=\"{$clink}\">{$ctitle}</a>]</p>";
            }
        } else {
            // キャッシュへのリンク、PC用
            $cid = 'matome_cache_meta' . $i;
            $cdate = date('Y/m/d H:i:s', $cache_info['time']);
            if ($cnumthreads) {
                $cpopup_at = " onmouseover=\"showCacheMetaData('{$cid}', event)\"";
                $cpopup_at .= " onmouseout=\"hideCacheMetaData('{$cid}')\"";
            } else {
                $cpopup_at = '';
            }
            echo "<li><span class=\"matome_cache_date\">{$cdate}</span> ";
            echo "[<a href=\"{$clink}\" target=\"read\"{$cpopup_at}>{$ctitle}</a>]";
            if ($cnumthreads) {
                echo "<div class=\"popup_element\" id=\"{$cid}\"{$cpopup_at}>";
            }
        }

        // まとめ読みに含まれる各スレッドへのリンク
        if ($cnumthreads && (!$_conf['ktai'] || $_conf['iphone'])) {
            if ($_conf['iphone']) {
                $ctarget_at = '';
            } else {
                $ctarget_at = ' target="read"';
            }

            echo '<ul>';
            foreach ($cache_info['threads'] as $cthread) {
                $cthreadlink = $_conf['read_php']
                             . '?host=' . rawurlencode($cthread['host'])
                             . '&amp;bbs=' . rawurlencode($cthread['bbs'])
                             . '&amp;key=' . rawurlencode($cthread['key'])
                             . '&amp;ls=' . rawurlencode($cthread['ls'])
                             . '&amp;offline=true' . $_conf['k_at_a'];
                echo "<li><a href=\"{$cthreadlink}\"{$ctarget_at}>{$cthread['title']}</a></li>";
            }
            echo '</ul>';
        }

        // 閉じタグ、PC用
        if (!$_conf['ktai']) {
            if ($cnumthreads) {
                echo '</div>';
            }
            echo "</li>\n";
        }
    }

    // 閉じタグ、PC用
    if (!$_conf['ktai']) {
        echo "</ul>\n";
        echo "</fieldset>\n";
        echo "</td></tr>\n";
    }
}

// }}}

if ($_conf['ktai']) {
    echo "<hr><div class=\"center\">{$_conf['k_to_index_ht']}</div>";
} else {
    echo "</table>\n";
}

echo '</body></html>';

exit;

//==============================================================================
// 関数
//==============================================================================
// {{{ printEditFileForm()

/**
 * 設定ファイル編集ウインドウを開くフォームHTMLをプリントする
 *
 * @param   string  $filename       編集するファイル名
 * @param   string  $submit_value   submitボタンの値
 * @return  void
 */
function printEditFileForm($filename, $submit_value)
{
    global $_conf;

    $path = $_conf['pref_dir'] . '/' . $filename;

    if ((file_exists($path) && is_writable($path)) ||
        (!file_exists($path) && is_writable(dirname($path)))
    ) {
        $onsubmit = '';
        $disabled = '';
    } else {
        $onsubmit = ' onsubmit="return false;"';
        $disabled = ' disabled';
    }

    $rows = 36; // 18
    $cols = 92; // 90

    // +Wiki
    if ($filename == 'p2_aborn_thread.txt' ||
        preg_match('/^p2_(aborn|ng|highlight)_(name|mail|id|msg)\\.txt$/', $filename))
    {
        $edit_php = 'edit_aborn_word.php';
        $target = '_self';
    } else {
        $edit_php = 'editfile.php';
        $target = 'editfile';
    }
    $filename_ht = p2h($filename);

    $ht = <<<EOFORM
<form action="{$edit_php}" method="GET" target="{$target}" class="inline-form"{$onsubmit}>
    {$_conf['k_input_ht']}
    <input type="hidden" name="file" value="{$filename_ht}">
    <input type="hidden" name="encode" value="Shift_JIS">
    <input type="hidden" name="rows" value="{$rows}">
    <input type="hidden" name="cols" value="{$cols}">
    <input type="submit" value="{$submit_value}"{$disabled}>
</form>\n
EOFORM;

    if (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false) {
        $ht = '&nbsp;' . preg_replace('/>\s+</', '><', $ht);
    }
    echo $ht;
}

// }}}
// {{{ getSyncFavoritesFormHt()

/**
 * ホストの同期用フォームのHTMLを取得する
 *
 * @param   string  $path_value     同期するファイルのパス
 * @param   string  $submit_value   submitボタンの値
 * @return  string
 */
function getSyncFavoritesFormHt($path_value, $submit_value)
{
    global $_conf;

    $ht = <<<EOFORM
<form action="editpref.php" method="POST" target="_self" class="inline-form">
    {$_conf['k_input_ht']}
    <input type="hidden" name="sync" value="{$path_value}">
    <input type="submit" value="{$submit_value}">
</form>\n
EOFORM;

    if (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false) {
        $ht = '&nbsp;' . preg_replace('/>\s+</', '><', $ht);
    }
    return $ht;
}

// }}}
// {{{ getDeleteHistoryFormHt()

/**
 * 履歴削除用フォームのHTMLを取得する
 *
 * @return  string
 */
function getDeleteHistoryFormHt()
{
    global $_conf, $deltitles;

    $ht = <<<EOFORM
<form action="editpref.php" method="POST" target="_self" class="inline-form" onSubmit="return deleteCheck()">
    {$_conf['k_input_ht']}

EOFORM;
    $ht .= "<select name=\"delete\">";
    foreach ($deltitles as $delmode => $delname) {
        $ht .= "<option value=\"{$delmode}\">{$delname}</option>";
    }
    $ht .= "</select><input type=\"submit\" name=\"submit\" value=\"削除\"></form>\n";

    if (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false) {
        $ht = '&nbsp;' . preg_replace('/>\s+</', '><', $ht);
    }
    return $ht;
}

// }}}
// {{{ getFavSetListFormHt()

/**
 * お気に入りセット切り替え・セット名変更用フォームのHTMLを取得する（PC用）
 *
 * @param   string  $set_name   内部処理用セット名
 * @param   string  $set_title  HTML表示用セット名
 * @return  string
 */
function getFavSetListFormHt($set_name, $set_title)
{
    global $_conf;

    if (!($titles = FavSetManager::getFavSetTitles($set_name))) {
        $titles = array();
    }

    $radio_checked = array_fill(0, $_conf['expack.misc.favset_num'] + 1, '');
    $i = (isset($_SESSION[$set_name])) ? (int)$_SESSION[$set_name] : 0;
    $radio_checked[$i] = ' checked';
    $ht = <<<EOFORM
<fieldset>
    <legend>{$set_title}</legend>\n
EOFORM;
    for ($j = 0; $j <= $_conf['expack.misc.favset_num']; $j++) {
        if (!isset($titles[$j]) || strlen($titles[$j]) == 0) {
            $titles[$j] = ($j == 0) ? $set_title : $set_title . $j;
        }
        $ht .= <<<EOFORM
    <input type="radio" name="{$set_name}" value="{$j}"{$radio_checked[$j]}>
    <input type="text" name="{$set_name}_titles[{$j}]" size="18" value="{$titles[$j]}">
    <br>\n
EOFORM;
    }
    $ht .= <<<EOFORM
</fieldset>\n
EOFORM;

    return $ht;
}

// }}}
// {{{ getFavSetListFormHtK()

/**
 * お気に入りセット切り替え用フォームのHTMLを取得する（携帯用）
 *
 * @param   string  $set_name   内部処理用セット名
 * @param   string  $set_title  HTML表示用セット名
 * @return  string
 */
function getFavSetListFormHtK($set_name, $set_title)
{
    global $_conf;

    if (!($titles = FavSetManager::getFavSetTitles($set_name))) {
        $titles = array();
    }

    $selected = array_fill(0, $_conf['expack.misc.favset_num'] + 1, '');
    $i = (isset($_SESSION[$set_name])) ? (int)$_SESSION[$set_name] : 0;
    $selected[$i] = ' selected';
    $ht = "<select name=\"{$set_name}\">";
    for ($j = 0; $j <= $_conf['expack.misc.favset_num']; $j++) {
        if ($j == 0) {
            if (!isset($titles[$j]) || strlen($titles[$j]) == 0) {
                $titles[$j] = $set_title;
            }
            $titles[$j] .= ' (ﾃﾞﾌｫﾙﾄ)';
        } else {
            if (!isset($titles[$j]) || strlen($titles[$j]) == 0) {
                $titles[$j] = $set_title . $j;
            }
        }
        if (!empty($_conf['mobile.save_packet'])) {
            $titles[$j] = mb_convert_kana($titles[$j], 'rnsk');
        }
        $ht .= "<option value=\"{$j}\"{$selected[$j]}>{$titles[$j]}</option>";
    }
    $ht .= "</select>\n";

    return $ht;
}

// }}}
// {{{ updateFavSetList()

/**
 * お気に入りセットリストを更新する
 *
 * @return  boolean 更新に成功したらtrue, 失敗したらfalse
 */
function updateFavSetList()
{
    global $_conf;

    if (file_exists($_conf['expack.misc.favset_file'])) {
        $setlist_titles = FavSetManager::getFavSetTitles();
    } else {
        FileCtl::make_datafile($_conf['expack.misc.favset_file']);
    }
    if (empty($setlist_titles)) {
        $setlist_titles = array();
    }

    $setlist_names = array('m_favlist_set', 'm_favita_set', 'm_rss_set');
    foreach ($setlist_names as $setlist_name) {
        if (isset($_POST["{$setlist_name}_titles"]) && is_array($_POST["{$setlist_name}_titles"])) {
            $setlist_titles[$setlist_name] = array();
            for ($i = 0; $i <= $_conf['expack.misc.favset_num']; $i++) {
                if (!isset($_POST["{$setlist_name}_titles"][$i])) {
                    $setlist_titles[$setlist_name][$i] = '';
                    continue;
                }
                $newname = trim($_POST["{$setlist_name}_titles"][$i]);
                $newname = preg_replace('/\r\n\t/', ' ', $newname);
                $newname = p2h($newname);
                $setlist_titles[$setlist_name][$i] = $newname;
            }
        }
    }

    $newdata = serialize($setlist_titles);
    if (FileCtl::file_write_contents($_conf['expack.misc.favset_file'], $newdata) === false) {
        P2Util::pushInfoHtml("<p>p2 error: {$_conf['expack.misc.favset_file']} にお気に入りセット設定を書き込めませんでした。");
        return false;
    }

    return true;
}

// }}}
// {{{ compareSkinNames()

/**
 * スキンのリストをソートするためのコールバック関数
 *
 * @param string $a
 * @param string $b
 * @return int
 */
function compareSkinNames($a, $b)
{
    if ($a == 'conf_user_style') {
        return -1;
    }
    if ($b == 'conf_user_style') {
        return 1;
    }
    return strcmp($a, $b);
}

// }}}
// {{{ deleteFile()

/**
 * ファイルを削除する。
 *
 * @param string ファイル名
 * @return boolean 既に無いか削除に成功したらtrue
 */
function deleteFile($filename)
{
    return file_exists($filename) ? unlink($filename) : true;
}

// }}}

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
