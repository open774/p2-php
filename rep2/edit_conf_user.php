<?php
/**
 *  rep2 - ユーザ設定編集UI
 */

require_once __DIR__ . '/../init.php';

$_login->authorize(); // ユーザ認証

$csrfid = P2Util::getCsrfId(__FILE__);

if (!empty($_POST['submit_save']) || !empty($_POST['submit_default'])) {
    if (!isset($_POST['csrfid']) || $_POST['csrfid'] != $csrfid) {
        p2die('不正なポストです');
    }
}

define('P2_EDIT_CONF_USER_DEFAULT',     0);
define('P2_EDIT_CONF_USER_LONGTEXT',    1);
define('P2_EDIT_CONF_USER_HIDDEN',      2);
define('P2_EDIT_CONF_USER_DISABLED',    4);
define('P2_EDIT_CONF_USER_SKIPPED',     8);
define('P2_EDIT_CONF_USER_PASSWORD',   16);
define('P2_EDIT_CONF_FILE_ADMIN',    1024);
define('P2_EDIT_CONF_FILE_ADMIN_EX', 2048);

include P2_CONFIG_DIR . '/conf_user_def.inc.php';

//=====================================================================
// 前処理
//=====================================================================

// {{{ 保存ボタンが押されていたら、設定を保存

if (!empty($_POST['submit_save'])) {

    // 値の適正チェック、矯正

    // トリム
    $_POST['conf_edit'] = array_map('trim', $_POST['conf_edit']);

    // 選択肢にないもの → デフォルト矯正
    notSelToDef();

    // ルールを適用する
    applyRules();

    // ポストされた値 > 現在の値 > デフォルト値 の順で新しい設定を作成する
    $conf_save = array('.' => $_conf['p2version']);
    foreach ($conf_user_def as $k => $v) {
        if (array_key_exists($k, $_POST['conf_edit'])) {
            $conf_save[$k] = $_POST['conf_edit'][$k];
        } elseif (array_key_exists($k, $_conf)) {
            $conf_save[$k] = $_conf[$k];
        } else {
            $conf_save[$k] = $v;
        }
    }

    // シリアライズして保存
    FileCtl::make_datafile($_conf['conf_user_file']);
    if (FileCtl::file_write_contents($_conf['conf_user_file'], serialize($conf_save)) === false) {
        P2Util::pushInfoHtml('<p>×設定を更新保存できませんでした</p>');
    } else {
        P2Util::pushInfoHtml('<p>○設定を更新保存しました</p>');
        // 変更があれば、内部データも更新しておく
        $_conf = array_merge($_conf, $conf_user_def, $conf_save);
    }

    unset($conf_save);

// }}}
// {{{ デフォルトに戻すボタンが押されていたら

} elseif (!empty($_POST['submit_default'])) {
    if (file_exists($_conf['conf_user_file']) and unlink($_conf['conf_user_file'])) {
        P2Util::pushInfoHtml('<p>○設定をデフォルトに戻しました</p>');
        // 変更があれば、内部データも更新しておく
        $_conf = array_merge($_conf, $conf_user_def);
        if (is_array($conf_save)) {
            $_conf = array_merge($_conf, $conf_save);
        }
    }
}

// }}}
// {{{ 携帯で表示するグループ

if ($_conf['ktai']) {
    if (isset($_REQUEST['edit_conf_user_group_en'])) {
        $selected_group = UrlSafeBase64::decode($_REQUEST['edit_conf_user_group_en']);
    } elseif (isset($_REQUEST['edit_conf_user_group'])) {
        $selected_group = $_REQUEST['edit_conf_user_group'];
    } else {
        $selected_group = null;
    }
} else {
    $selected_group = 'all';
    if (isset($_REQUEST['active_tab1'])) {
        $active_tab1 = $_REQUEST['active_tab1'];
        $active_tab1_ht = p2h($active_tab1);
        $active_tab1_js = "'" . StrCtl::toJavaScript($active_tab1) . "'";
    } else {
        $active_tab1 = null;
        $active_tab1_ht = '';
        $active_tab1_js = 'null';
    }
    if (isset($_REQUEST['active_tab2'])) {
        $active_tab2 = $_REQUEST['active_tab2'];
        $active_tab2_ht = p2h($active_tab2);
        $active_tab2_js = "'" . StrCtl::toJavaScript($active_tab2) . "'";
    } else {
        $active_tab2 = null;
        $active_tab2_ht = '';
        $active_tab2_js = 'null';
    }
    $parent_tabs_js = "['" . implode("','", array(
        StrCtl::toJavaScript('rep2基本設定'),
        StrCtl::toJavaScript('携帯端末設定'),
        StrCtl::toJavaScript('拡張パック設定'),
    )) . "']";
    $active_tab_hidden_ht = <<<EOP
<input type="hidden" id="active_tab1" name="active_tab1" value="{$active_tab1_ht}">
<input type="hidden" id="active_tab2" name="active_tab2" value="{$active_tab2_ht}">
<script type="text/javascript">
// <![CDATA[
_EDIT_CONF_USER_JS_PARENT_TABS = $parent_tabs_js;
_EDIT_CONF_USER_JS_ACTIVE_TAB1 = $active_tab1_js;
_EDIT_CONF_USER_JS_ACTIVE_TAB2 = $active_tab2_js;
// ]]>
</script>
EOP;
}

$groups = array();
$keep_old = false;

// }}}

//=====================================================================
// プリント設定
//=====================================================================
$ptitle = 'ユーザ設定編集';

$me = P2Util::getMyUrl();

//=====================================================================
// プリント
//=====================================================================
// ヘッダHTMLをプリント
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
    <script type="text/javascript" src="js/basic.js?{$_conf['p2_version_id']}"></script>
    <script type="text/javascript" src="js/tabber/tabber.js?{$_conf['p2_version_id']}"></script>
    <script type="text/javascript" src="js/edit_conf_user.js?{$_conf['p2_version_id']}"></script>
    <link rel="stylesheet" type="text/css" href="css.php?css=style&amp;skin={$skin_en}">
    <link rel="stylesheet" type="text/css" href="css.php?css=edit_conf_user&amp;skin={$skin_en}">
    <link rel="stylesheet" type="text/css" href="css/tabber/tabber.css?{$_conf['p2_version_id']}">
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico">\n
EOP;
}

$body_at = ($_conf['ktai']) ? $_conf['k_colors'] : '';
echo <<<EOP
</head>
<body{$body_at}>\n
EOP;

// PC用表示
if (!$_conf['ktai']) {
    echo <<<EOP
<p id="pan_menu"><a href="editpref.php">設定管理</a> &gt; {$ptitle} （<a href="{$me}">リロード</a>）</p>\n
EOP;
}

// 携帯用表示
if ($_conf['ktai']) {
    $htm['form_submit'] = <<<EOP
<input type="submit" name="submit_save" value="変更を保存する">\n
EOP;
}

// 情報メッセージ表示
P2Util::printInfoHtml();

echo <<<EOP
<form id="edit_conf_user_form" method="POST" action="{$_SERVER['SCRIPT_NAME']}" target="_self" accept-charset="{$_conf['accept_charset']}">
    <input type="hidden" name="csrfid" value="{$csrfid}">\n
EOP;

// PC用表示
if (!$_conf['ktai']) {
    echo $active_tab_hidden_ht;
    echo <<<EOP
<div class="tabber">
<div class="tabbertab" title="rep2基本設定">
<h3>rep2基本設定</h3>
<div class="tabber">\n
EOP;
// 携帯用表示
} else {
    if (!empty($selected_group)) {
        echo $htm['form_submit'];
    }
}

// {{{ rep2基本設定
// {{{ 'be/p2'

$groupname = 'be';
$groups[] = $groupname;
$flags = getGroupShowFlags($groupname);
if ($flags & P2_EDIT_CONF_USER_SKIPPED) {
    $keep_old = true;
} else {
    $conflist = array(
        'be',
        array('be_2ch_mail', 'be.2ch.netの登録メールアドレス', P2_EDIT_CONF_USER_LONGTEXT),
        array('be_2ch_password', '<a href="http://be.2ch.net/" target="_blank">be.2ch.net</a>のパスワード(認証コードは使えなくなりました)', P2_EDIT_CONF_USER_PASSWORD),
        array('be_2ch_DMDM', '<a href="http://be.2ch.net/" target="_blank">be.2ch.net</a>のDMDM(手動設定する場合のみ入力)', P2_EDIT_CONF_USER_LONGTEXT),
        array('be_2ch_MDMD', '<a href="http://be.2ch.net/" target="_blank">be.2ch.net</a>のMDMD(手動設定する場合のみ入力)', P2_EDIT_CONF_USER_LONGTEXT),
    );
    printEditConfGroupHtml($groupname, $conflist, $flags);
}

// }}}
// {{{ PATH

$groupname = 'PATH';
$groups[] = $groupname;
$flags = getGroupShowFlags($groupname);
if ($flags & P2_EDIT_CONF_USER_SKIPPED) {
    $keep_old = true;
} else {
    $conflist = array(
//        array('first_page', '右下部分に最初に表示されるページ。オンラインURLも可。'),
        array('brdfile_online',
'板リストの指定（オンラインURL）<br>
板リストをオンラインURLから自動で読み込む。
指定先は menu.html 形式、2channel.brd 形式のどちらでもよい。
<!-- 必要なければ、空白に。 --><br>
2ch基本 <a href="http://menu.2ch.net/bbsmenu.html" target="_blank">http://menu.2ch.net/bbsmenu.html</a><br>
2ch + 外部BBS <a href="http://azlucky.s25.xrea.com/2chboard/bbsmenu.html" target="_blank">http://azlucky.s25.xrea.com/2chboard/bbsmenu.html</a>',
            P2_EDIT_CONF_USER_LONGTEXT),
    );
    printEditConfGroupHtml($groupname, $conflist, $flags);
}

// }}}
// {{{ subject

$groupname = 'subject';
$groups[] = $groupname;
$flags = getGroupShowFlags($groupname);
if ($flags & P2_EDIT_CONF_USER_SKIPPED) {
    $keep_old = true;
} else {
    $conflist = array(
        array('refresh_time', 'スレッド一覧の自動更新間隔 (分指定。0なら自動更新しない)'),

        array('sb_show_motothre', 'スレッド一覧で未取得スレに対して元スレへのリンク（・）を表示'),
        array('sb_show_one', 'スレッド一覧（板表示）で&gt;&gt;1を表示'),
        array('sb_show_spd', 'スレッド一覧ですばやさ（レス間隔）を表示'),
        array('sb_show_ikioi', 'スレッド一覧で勢い（1日あたりのレス数）を表示'),
        array('sb_show_fav', 'スレッド一覧でお気にスレマーク★を表示'),
        array('sb_sort_ita', '板表示のスレッド一覧でのデフォルトのソート指定'),
        array('sort_zero_adjust', '新着ソートでの「既得なし」の「新着数ゼロ」に対するソート優先順位'),
        array('cmp_dayres_midoku', '勢いソート時に新着レスのあるスレを優先'),
        array('cmp_title_norm', 'タイトルソート時に全角半角・大文字小文字を無視'),
        array('viewall_kitoku', '既得スレは表示件数に関わらず表示'),
        array('delete_copyright', 'スレッドのタイトルから著作権表記を削除する'),
        array('delete_copyright.list', '削除する著作権表記の文字列(カンマ区切り)'),
    );
    printEditConfGroupHtml($groupname, $conflist, $flags);
}

// }}}
// {{{ read

$groupname = 'read';
$groups[] = $groupname;
$flags = getGroupShowFlags($groupname);
if ($flags & P2_EDIT_CONF_USER_SKIPPED) {
    $keep_old = true;
} else {
    $conflist = array(
        array('respointer', 'スレ内容表示時、未読の何コ前のレスにポインタを合わせるか'),
        array('before_respointer', 'ポインタの何コ前のレスから表示するか'),
        array('before_respointer_new', '新着まとめ読みの時、ポインタの何コ前のレスから表示するか'),
        array('rnum_all_range', '新着まとめ読みで一度に表示するレス数'),
        array('preview_thumbnail', '画像URLの先読みサムネイルを表示'),
        array('pre_thumb_limit', '画像URLの先読みサムネイルを一度に表示する制限数 (0で無制限)'),
//        array('pre_thumb_height', '画像サムネイルの縦の大きさを指定 (ピクセル)'),
//        array('pre_thumb_width', '画像サムネイルの横の大きさを指定 (ピクセル)'),
        array('iframe_popup', 'HTMLポップアップ'),
        array('iframe_popup_event', 'HTMLポップアップをする場合のイベント'),
        array('iframe_popup_type', 'HTMLポップアップの種類'),
//        array('iframe_popup_delay', 'HTMLポップアップの表示遅延時間 (秒)'),
        array('flex_idpopup', 'ID:xxxxxxxxをIDフィルタリングのリンクに変換'),
        array('ext_win_target', '外部サイト等へジャンプする時に開くウィンドウのターゲット名<br>(空なら同じウインドウ、_blank で新しいウインドウ)'),
        array('bbs_win_target', 'rep2対応BBSサイト内でジャンプする時に開くウィンドウのターゲット名<br>(空なら同じウインドウ、_blank で新しいウインドウ)'),
        array('bottom_res_form', 'スレッド下部に書き込みフォームを表示'),
        array('quote_res_view', '引用レスを表示'),
        array('quote_res_view_ng', 'NGレスを引用レス表示するか'),
        array('quote_res_view_aborn', 'あぼーんレスを引用レス表示するか'),
        array('strip_linebreaks', '文末の改行と連続する改行を除去'),
        array('link_wikipedia', '[[単語]]をWikipediaへのリンクにする'),
        array('backlink_list', '逆参照ポップアップリストの表示'),
        array('backlink_list_future_anchor', '逆参照リストで未来アンカーを有効にするか'),
        array('backlink_list_range_anchor_limit', '逆参照リストでこの値より広い範囲レスを対象外にする(0で制限なし)'),
        array('backlink_block', '逆参照ブロックを展開できるようにするか'),
        array('backlink_block_readmark', '逆参照ブロックで展開されているレスの本体に装飾するか'),
        array('backlink_coloring_track', '本文をダブルクリックすると着色してレス追跡'),
        array('backlink_coloring_track_colors', '本文をダブルクリックてレス追跡時の色リスト(カンマ区切り)'),
        array('coloredid.enable', 'IDに色を付ける'),
        array('coloredid.rate.type', '画面表示時にIDに着色しておく条件'),
        array('coloredid.rate.times', '条件が出現数の場合の数(n以上)'),
        array('coloredid.rate.hissi.times', '必死判定(IDブリンク)の出現数(0で無効。IE/Safariはblink非対応)'),
        array('coloredid.click', 'ID出現数をクリックすると着色をトグル(「しない」にするとJavascriptではなくPHPで着色)'),
        array('coloredid.marking.colors', 'ID出現数をダブルクリックしてマーキングの色リスト(カンマ区切り)'),
        array('coloredid.coloring.type', 'カラーリングのタイプ（thermon版はPHPで着色(coloredid.click=しない)の場合のみ有効）'),
    );
    printEditConfGroupHtml($groupname, $conflist, $flags);
}

// }}}
// {{{ NG/あぼーん

$groupname = 'NG/あぼーん';
$groups[] = $groupname;
$flags = getGroupShowFlags($groupname);
if ($flags & P2_EDIT_CONF_USER_SKIPPED) {
    $keep_old = true;
} else {
    $conflist = array(
        array('ngaborn_frequent', '&gt;&gt;1 以外の頻出IDをあぼーんする'),
        array('ngaborn_frequent_one', '&gt;&gt;1 も頻出IDあぼーんの対象外にする'),
        array('ngaborn_frequent_num', '頻出IDあぼーんのしきい値 (出現回数がこれ以上のIDをあぼーん)'),
        array('ngaborn_frequent_dayres', '勢いの速いスレでは頻出IDあぼーんしない<br>(総レス数/スレ立てからの日数、0なら無効)'),
        array('ngaborn_chain', '連鎖NGあぼーん<br>「する」ならあぼーんレスへのレスはあぼーん、NGレスへのレスはNG。<br>「すべてNGにする」の場合、あぼーんレスへのレスもNGにする。'),
        array('ngaborn_auto', 'NGあぼーんの対象になったレスのIDを自動的にNGあぼーんする<br>「する」ならあぼーんレスのIDはあぼーん、NGレスのIDはNG。'),
        array('ngaborn_chain_all', '表示範囲外のレスも連鎖NG/あぼーん/ハイライトの対象にする<br>(処理を軽くするため、デフォルトではしない)'),
        array('ngaborn_daylimit', 'この期間、NG/あぼーん/ハイライトにHITしなければ、登録ワードを自動的に外す (日数)'),
        array('ngaborn_purge_aborn', 'あぼーんレスは不可視divブロックも描画しない'),
    );
    printEditConfGroupHtml($groupname, $conflist, $flags);
}

// }}}
// {{{ '2ch API'

$groupname = '2ch API';
$groups[] = $groupname;
$flags = getGroupShowFlags($groupname);
if ($flags & P2_EDIT_CONF_USER_SKIPPED) {
    $keep_old = true;
} else {
    $conflist = array(
        array('2chapi_use','2ch API を使用する'),
        array('2chapi_interval','2ch API 認証する間隔(単位:時間)'),
        'API認証情報(全て必須)',
        array('2chapi_appkey','AppKey', P2_EDIT_CONF_USER_LONGTEXT),
        array('2chapi_hmkey','HMkey', P2_EDIT_CONF_USER_LONGTEXT),
        array('2chapi_appname','AppName APIに送信するアプリケーション名 例:Hoge/1.00'),
        'User-Agent',
        array('2chapi_ua.auth','API認証に使用するUser-Agent'),
        array('2chapi_ua.read','DAT取得に使用するUser-Agent'),
        'SSL通信設定',
        array('2chapi_ssl.auth','API認証にSSLを使用する'),
        array('2chapi_ssl.read','DAT取得にSSLを使用する'),
        'デバッグ用',
        array('2chapi_debug_print','デバッグ用の情報を出力する'),
        '認証情報を変更した場合再認証してください',
    );
    printEditConfGroupHtml($groupname, $conflist, $flags);
}

// }}}
// {{{ ETC

$groupname = 'ETC';
$groups[] = $groupname;
$flags = getGroupShowFlags($groupname);
if ($flags & P2_EDIT_CONF_USER_SKIPPED) {
    $keep_old = true;
} else {
    $conflist = array(
        '3ペイン画面',
        array('frame_menu_width', 'フレーム左 板メニュー の表示幅'),
        array('frame_subject_width', 'フレーム右上 スレ一覧 の表示幅'),
        array('frame_read_width', 'フレーム右下 スレ本文 の表示幅'),
        array('pane_mode', '3ペイン画面のフレームの並べ方'),
        '書き込み',
        array('my_FROM', 'レス書き込み時のデフォルトの名前'),
        array('my_mail', 'レス書き込み時のデフォルトのmail'),

        array('editor_srcfix', 'PC閲覧時、ソースコードのコピペに適した補正をするチェックボックスを表示'),

        array('get_new_res', '新しいスレッドを取得した時に表示するレス数(全て表示する場合:&quot;all&quot;)'),
        array('rct_rec_num', '最近読んだスレの記録数'),
        array('res_hist_rec_num', '書き込み履歴の記録数'),
        array('res_write_rec', '書き込み内容ログを記録'),
        array('res_popup_reload', 'ポップアップから書き込み成功したらスレを再読み込みする'),
        '外部URL・ブラクラチェッカ',
        array('through_ime', '外部URLジャンプする際に通すゲート<br>「直接」でもCookieが使えない端末では gate.php を通す'),
        array('through_ime_http_only', ' HTTPSでアクセスしているときは外部URLゲートを通さない<br>(最近のWebブラウザの多くは https → http の遷移でRefererを送出しませんが、<br>「HTTPSでは直」にする場合は、お使いのブラウザの仕様を確認してください)'),
        array('ime_manual_ext', 'ゲートで自動転送しない拡張子（カンマ区切りで、拡張子の前のピリオドは不要）'),
        array('brocra_checker_use', 'ブラクラチェッカ (つける, つけない)'),
        array('brocra_checker_url', 'ブラクラチェッカURL'),
        array('brocra_checker_query', 'ブラクラチェッカのクエリー (空の場合、PATH_INFOでURLを渡す)'),
        '板メニュー・お気に板',
//      array('join_favrank', '<a href="http://akid.s17.xrea.com/favrank/favrank.html" target="_blank">お気にスレ共有</a>に参加'),
        array('merge_favita', 'お気に板のスレ一覧をまとめて表示 (お気に板の数によっては処理に時間がかかる)'),
        array('favita_order_dnd', 'ドラッグ＆ドロップでお気に板を並べ替える'),
        array('enable_menu_new', '板メニューに新着数を表示'),
        array('menu_refresh_time', '板メニュー部分の自動更新間隔 (分指定。0なら自動更新しない)'),
        array('menu_hide_brds', '板カテゴリ一覧を閉じた状態にする'),
        'プロキシ',
        array('proxy_use', 'プロキシを利用'),
        array('proxy_host', 'プロキシホスト ex)&quot;127.0.0.1&quot;, &quot;p2proxy.example&quot;'),
        array('proxy_port', 'プロキシポート ex)&quot;8080&quot;'),
        array('proxy_user', 'プロキシユーザー名 (使用する場合のみ)'),
        array('proxy_password', 'プロキシパスワード (使用する場合のみ)'),
        'SSL通信設定',
        array('ssl_function', 'SSL通信(●ログイン等)に使用する関数<br>(関数に該当する拡張をインストールする必要がある)'),
        array('ssl_capath', 'SSL通信で接続先を検証するための証明書があるディレクトリ ex)&quot;/etc/ssl/certs&quot;<br>設定なして動く場合は設定不要<br>ssl_functionに対応した形式の証明書が必要'),
        array('2ch_ssl.maru', '2ch.netの●ログインにSSLを使用する'),
        array('2ch_ssl.subject', '2ch.netのsubjec.txtとSETTING.TXTの取得にSSLを使用する'),
        array('2ch_ssl.post', '2ch.netの書き込みにSSLを使用する'),
    );
    printEditConfGroupHtml($groupname, $conflist, $flags);
}

// }}}
// }}}

// PC用表示
if (!$_conf['ktai']) {
    echo <<<EOP
</div><!-- end of tab -->
</div><!-- end of child tabset "rep2基本設定" -->

<div class="tabbertab" title="携帯端末設定">
<h3>携帯端末設定</h3>
<div class="tabber">\n
EOP;
}

// {{{ 携帯端末設定
// {{{ Mobile

$groupname = 'mobile';
$groups[] = $groupname;
$flags = getGroupShowFlags($groupname);
if ($flags & P2_EDIT_CONF_USER_SKIPPED) {
    $keep_old = true;
} else {
    $conflist = array(
        array('mobile.background_color', '背景色'),
        array('mobile.text_color', '基本文字色'),
        array('mobile.link_color', 'リンク色'),
        array('mobile.vlink_color', '訪問済みリンク色'),
        array('mobile.newthre_color', '新着スレッドマークの色'),
        array('mobile.ttitle_color', 'スレッドタイトルの色'),
        array('mobile.newres_color', '新着レス番号の色'),
        array('mobile.ngword_color', 'NGワードの色'),
        array('mobile.onthefly_color', 'オンザフライレス番号の色'),
        array('mobile.match_color', 'フィルタリングでマッチしたキーワードの色'),
        array('mobile.display_accesskey', 'アクセスキーの番号を表示'),
        array('mobile.save_packet', 'パケット量を減らすため、全角英数・カナ・スペースを半角に変換'),
    );
    printEditConfGroupHtml($groupname, $conflist, $flags);
}

// }}}
// {{{ Mobile - subject

$groupname = 'subject (mobile)';
$groups[] = $groupname;
$flags = getGroupShowFlags($groupname);
if ($flags & P2_EDIT_CONF_USER_SKIPPED) {
    $keep_old = true;
} else {
    $conflist = array(
        array('mobile.sb_show_first', 'スレッド一覧（板表示）から初めてのスレを開く時の表示方法'),
        array('mobile.sb_disp_range', '一度に表示するスレの数'),
        array('mobile.sb_ttitle_max_len', 'スレッド一覧で表示するタイトルの長さの上限 (0で無制限)'),
        array('mobile.sb_ttitle_trim_len', 'スレッドタイトルが長さの上限を越えたとき、この長さまで切り詰める'),
        array('mobile.sb_ttitle_trim_pos', 'スレッドタイトルを切り詰める位置'),
    );
    printEditConfGroupHtml($groupname, $conflist, $flags);
}

// }}}
// {{{ Mobile - read

$groupname = 'read (mobile)';
$groups[] = $groupname;
$flags = getGroupShowFlags($groupname);
if ($flags & P2_EDIT_CONF_USER_SKIPPED) {
    $keep_old = true;
} else {
    $conflist = array(
        array('mobile.rnum_range', '一度に表示するレスの数'),
        array('mobile.res_size', '一つのレスの最大表示サイズ'),
        array('mobile.ryaku_size', 'レスを省略したときの表示サイズ'),
        array('mobile.aa_ryaku_size', 'AAらしきレスを省略するサイズ (0なら無効)'),
        array('mobile.before_respointer', 'ポインタの何コ前のレスから表示するか'),
        array('mobile.use_tsukin', '外部リンクに通勤ブラウザ(通)を利用'),
        array('mobile.use_picto', '画像リンクにpic.to(ﾋﾟ)を利用'),
        array('mobile.link_youtube', 'YouTubeのリンクをサムネイル表示'),

        array('mobile.bbs_noname_name', 'デフォルトの名無し名を表示'),
        array('mobile.date_zerosuppress', '日付の0を省略表示'),
        array('mobile.clip_time_sec', '時刻の秒を省略表示'),
        array('mobile.clip_unique_id', '重複しないIDは末尾のみの省略表示'),
        array('mobile.underline_id', 'ID末尾の&quot;O&quot;に下線を引く'),
        array('mobile.strip_linebreaks', '文末の改行と連続する改行を除去'),

        array('mobile.copy_divide_len', '「写」のコピー用テキストボックスを分割する文字数'),
        array('mobile.link_wikipedia', '[[単語]]をWikipediaへのリンクにする'),
        array('mobile.backlink_list', '逆参照リストの表示'),
        array('mobile.backlink_list.suppress', '携帯閲覧時、逆参照リストを省略表示する数（この数より多いレスは省略表示。0:省略しない）'),
        array('mobile.backlink_list.openres_navi', '携帯閲覧時、逆参照リストにレスまとめページへのリンクを表示するか'),
    );
    printEditConfGroupHtml($groupname, $conflist, $flags);
}

// }}}
// {{{ iPhone - subject
/*
$groupname = 'subject (iPhone)';
$groups[] = $groupname;
$flags = getGroupShowFlags($groupname);
if ($flags & P2_EDIT_CONF_USER_SKIPPED) {
    $keep_old = true;
} else {
    $conflist = array(
    );
    printEditConfGroupHtml($groupname, $conflist, $flags);
}
*/
// }}}
// {{{ iPhone - read
/*
$groupname = 'read (iPhone)';
$groups[] = $groupname;
$flags = getGroupShowFlags($groupname);
if ($flags & P2_EDIT_CONF_USER_SKIPPED) {
    $keep_old = true;
} else {
    $conflist = array(
    );
    printEditConfGroupHtml($groupname, $conflist, $flags);
}
*/
// }}}
// }}}

// PC用表示
if (!$_conf['ktai']) {
    echo <<<EOP
</div><!-- end of tab -->
</div><!-- end of child tabset "携帯端末設定" -->

<div class="tabbertab" title="拡張パック設定">
<h3>拡張パック設定</h3>
<div class="tabber">\n
EOP;
}

// {{{ 拡張パック設定
// {{{ expack - tGrep

$groupname = 'tGrep';
$groups[] = $groupname;
$flags = getGroupShowFlags($groupname);
if ($flags & P2_EDIT_CONF_USER_SKIPPED) {
    $keep_old = true;
} else {
    $conflist = array(
        array('expack.tgrep.quicksearch', '一発検索'),
        array('expack.tgrep.recent_num', '検索履歴を記録する数（記録しない:0）'),
        array('expack.tgrep.recent2_num', 'サーチボックスに検索履歴を記録する数、Safari専用（記録しない:0）'),
    );
    printEditConfGroupHtml($groupname, $conflist, $flags);
}

// }}}
// {{{ expack - スマートポップアップメニュー

$groupname = 'SPM';
$groups[] = $groupname;
$flags = getGroupShowFlags($groupname, 'expack.spm.enabled');
if ($flags & P2_EDIT_CONF_USER_SKIPPED) {
    $keep_old = true;
} else {
    $conflist = array(
        array('expack.spm.kokores', 'ここにレス'),
        array('expack.spm.kokores_orig', 'ここにレスで開くフォームに元レスの内容を表示する'),
        array('expack.spm.ngaborn', 'あぼーん/NG/ハイライトワード登録'),
        array('expack.spm.ngaborn_confirm', 'あぼーん/NG/ハイライトワード登録時に確認する'),
        array('expack.spm.filter', 'フィルタリング'),
        array('expack.spm.filter_target', 'フィルタリング結果を開くフレームまたはウインドウ'),
    );
    printEditConfGroupHtml($groupname, $conflist, $flags);
}

// }}}
// {{{ expack - アクティブモナー

$groupname = 'ActiveMona';
$groups[] = $groupname;
$flags = getGroupShowFlags($groupname, 'expack.am.enabled');
if ($flags & P2_EDIT_CONF_USER_SKIPPED) {
    $keep_old = true;
} else {
    if (isset($_conf['expack.am.fontfamily.orig'])) {
        $_current_am_fontfamily = $_conf['expack.am.fontfamily'];
        $_conf['expack.am.fontfamily'] = $_conf['expack.am.fontfamily.orig'];
    }
    $conflist = array(
        array('expack.am.fontfamily', 'AA用のフォント'),
        array('expack.am.fontsize', 'AA用の文字の大きさ'),
        array('expack.am.display', 'スイッチを表示する位置'),
        array('expack.am.autodetect', '自動で判定し、AA用表示をする（PC）'),
        array('expack.am.autong_k', '自動で判定し、NGワードにする。AAS が有効なら AAS のリンクも作成（携帯）'),
        array('expack.am.lines_limit', '自動判定する行数の下限'),
    );
    printEditConfGroupHtml($groupname, $conflist, $flags);
    if (isset($_conf['expack.am.fontfamily.orig'])) {
        $_conf['expack.am.fontfamily'] = $_current_am_fontfamily;
    }
}

// }}}
// {{{ expack - 入力支援

$groupname = '入力支援';
$groups[] = $groupname;
$flags = getGroupShowFlags($groupname);
if ($flags & P2_EDIT_CONF_USER_SKIPPED) {
    $keep_old = true;
} else {
    $conflist = array(
        //array('expack.editor.constant', '定型文 (使う, 使わない)'),
        array('expack.editor.dpreview', 'リアルタイム・プレビュー'),
        array('expack.editor.dpreview_chkaa', 'リアルタイム・プレビューでAA補正用のチェックボックスを表示する'),
        array('expack.editor.check_message', '本文が空でないかチェック'),
        array('expack.editor.check_sage', 'sageチェック'),
        array('expack.editor.savedraft', '下書き保存'),
        array('expack.editor.savedraft.interval', '下書きを自動保存する間隔(秒)（0で無効）'),
        array('expack.editor.mobile.savedraft', '下書き保存(携帯)'),
        array('expack.editor.mobile.savedraft.interval', '下書きを自動保存する間隔(秒)(iphone)（0で無効）'),
    );
    printEditConfGroupHtml($groupname, $conflist, $flags);
}

// }}}
// {{{ expack - RSSリーダ

$groupname = 'RSS';
$groups[] = $groupname;
$flags = getGroupShowFlags($groupname, 'expack.rss.enabled');
if ($flags & P2_EDIT_CONF_USER_SKIPPED) {
    $keep_old = true;
} else {
    $conflist = array(
        array('expack.rss.check_interval', 'RSSが更新されたかどうか確認する間隔 (分指定)'),
        array('expack.rss.target_frame', 'RSSの外部リンクを開くフレームまたはウインドウ'),
        array('expack.rss.desc_target_frame', '概要を開くフレームまたはウインドウ'),
    );
    printEditConfGroupHtml($groupname, $conflist, $flags);
}

// }}}
// {{{ expack - ImageCache2

$groupname = 'ImageCache2';
$groups[] = $groupname;
$flags = getGroupShowFlags($groupname, 'expack.ic2.enabled');
if ($flags & P2_EDIT_CONF_USER_SKIPPED) {
    $keep_old = true;
} else {
    $conflist = array(
        array('expack.ic2.viewer_default_mode', '画像キャッシュ一覧のデフォルト表示モード'),
        array('expack.ic2.through_ime', 'キャッシュに失敗したときの確認用にime経由でソースへのリンクを作成'),
        array('expack.ic2.fitimage', 'ポップアップ画像の大きさをウインドウの大きさに合わせる'),
        array('expack.ic2.pre_thumb_limit_k', '携帯でインライン・サムネイルが有効のときの表示する制限数 (0で無制限)'),
        array('expack.ic2.newres_ignore_limit', '新着レスの画像は pre_thumb_limit を無視して全て表示'),
        array('expack.ic2.newres_ignore_limit_k', '携帯で新着レスの画像は pre_thumb_limit_k を無視して全て表示'),
        array('expack.ic2.thread_imagelink', 'スレ表示時に画像キャッシュ一覧へのスレタイ検索リンクを表示する'),
        array('expack.ic2.thread_imagecount', 'スレ表示時にスレタイで検索した時の画像数を表示する'),
        array('expack.ic2.fav_auto_rank', 'お気にスレに登録されているスレの画像に自動ランクを設定する'),
        array('expack.ic2.fav_auto_rank_setting', 'お気にスレの画像を自動ランク設定する場合の設定値(カンマ区切り)[お気に0のランク値,お気に1のランク値, , ,]'),
        array('expack.ic2.fav_auto_rank_override', 'お気にスレの画像を自動ランク設定する場合に、キャッシュ済み画像に自動ランクを上書きするか'),
    );
    printEditConfGroupHtml($groupname, $conflist, $flags);
}

// }}}
// {{{ expack - AAS

$groupname = 'AAS';
$groups[] = $groupname;
$flags = getGroupShowFlags($groupname, 'expack.aas.enabled');
if ($flags & P2_EDIT_CONF_USER_SKIPPED) {
    $keep_old = true;
} else {
    $conflist = array(
        array('expack.aas.inline_enabled', '携帯で自動 AA 判定と連動し、インライン表示する'),
        'PC用',
        array('expack.aas.default.type', '画像形式 (PNG, JPEG, GIF)'),
        array('expack.aas.default.quality', 'JPEGの品質 (0-100)'),
        array('expack.aas.default.width', '画像の横幅 (ピクセル)'),
        array('expack.aas.default.height', '画像の高さ (ピクセル)'),
        array('expack.aas.default.margin', '画像のマージン (ピクセル)'),
        array('expack.aas.default.fontsize', '文字サイズ (ポイント)'),
        array('expack.aas.default.overflow', '文字が画像からはみ出る場合、リサイズして納める (非表示, リサイズ)'),
        array('expack.aas.default.bold', '太字にする'),
        array('expack.aas.default.fgcolor', '文字色 (6桁または3桁の16進数)'),
        array('expack.aas.default.bgcolor', '背景色 (6桁または3桁の16進数)'),
        '携帯用',
        array('expack.aas.mobile.type', '画像形式 (PNG, JPEG, GIF)'),
        array('expack.aas.mobile.quality', 'JPEGの品質 (0-100)'),
        array('expack.aas.mobile.width', '画像の横幅 (ピクセル)'),
        array('expack.aas.mobile.height', '画像の高さ (ピクセル)'),
        array('expack.aas.mobile.margin', '画像のマージン (ピクセル)'),
        array('expack.aas.mobile.fontsize', '文字サイズ (ポイント)'),
        array('expack.aas.mobile.overflow', '文字が画像からはみ出る場合、リサイズして納める (非表示, リサイズ)'),
        array('expack.aas.mobile.bold', '太字にする'),
        array('expack.aas.mobile.fgcolor', '文字色 (6桁または3桁の16進数)'),
        array('expack.aas.mobile.bgcolor', '背景色 (6桁または3桁の16進数)'),
        'インライン表示',
        array('expack.aas.inline.type', '画像形式 (PNG, JPEG, GIF)'),
        array('expack.aas.inline.quality', 'JPEGの品質 (0-100)'),
        array('expack.aas.inline.width', '画像の横幅 (ピクセル)'),
        array('expack.aas.inline.height', '画像の高さ (ピクセル)'),
        array('expack.aas.inline.margin', 'マージン (ピクセル)'),
        array('expack.aas.inline.fontsize', '文字サイズ (ポイント)'),
        array('expack.aas.inline.overflow', '文字が画像からはみ出る場合、リサイズして納める (非表示, リサイズ)'),
        array('expack.aas.inline.bold', '太字にする'),
        array('expack.aas.inline.fgcolor', '文字色 (6桁または3桁の16進数)'),
        array('expack.aas.inline.bgcolor', '背景色 (6桁または3桁の16進数)'),
    );
    printEditConfGroupHtml($groupname, $conflist, $flags);
}

// }}}
// {{{ expack - ツールバーアクション

$groupname = 'ツールバーアクション';
$groups[] = $groupname;
$flags = getGroupShowFlags($groupname);
if ($flags & P2_EDIT_CONF_USER_SKIPPED) {
    $keep_old = true;
} else {
    $_tba_board_uri_description = <<<EOS
iPhone/スレ一覧/URI<br>
例) beebee2seeopen://\$host/\$bbs/<br>
<table style="margin:5px auto;background:#ccc;color:#000">
<caption>置換パターン</caption>
<thead>
<tr><th>from</th><th>to</th></tr>
</thead>
<tbody>
<tr><td>\$time</td><td>タイムスタンプ</td></tr>
<tr><td>\$host</td><td>ホスト名</td></tr>
<tr><td>\$bbs</td><td>板名</td></tr>
<tr><td>\$url</td><td>板のURL</td></tr>
<tr><td>\$eurl</td><td>〃</td></tr>
</tbody>
</table>
(\$url以外の置換パラメータはURLエンコード済)
EOS;
    $_tba_thread_uri_description = <<<EOS
iPhone/スレ一覧/URI<br>
例) beebee2seeopen://\$path<br>
<table style="margin:5px auto;background:#ccc;color:#000">
<caption>置換パターン</caption>
<thead>
<tr><th>from</th><th>to</th></tr>
</thead>
<tbody>
<tr><td>\$time</td><td>タイムスタンプ</td></tr>
<tr><td>\$host</td><td>ホスト名</td></tr>
<tr><td>\$bbs</td><td>板名</td></tr>
<tr><td>\$key</td><td>スレッドキー</td></tr>
<tr><td>\$ls</td><td>表示範囲</td></tr>
<tr><td>\$url</td><td>スレッドのURL</td></tr>
<tr><td>\$eurl</td><td>〃</td></tr>
<tr><td>\$path</td><td>http://を除外したURL</td></tr>
</tbody>
</table>
(\$url, \$path以外の置換パラメータはURLエンコード済)
EOS;
    $conflist = array(
        array('expack.tba.iphone.board_uri', $_tba_board_uri_description, P2_EDIT_CONF_USER_LONGTEXT),
        array('expack.tba.iphone.board_title', 'iPhone/スレ一覧/タイトル<br>例) BB2C'),
        array('expack.tba.iphone.thread_uri', $_tba_thread_uri_description, P2_EDIT_CONF_USER_LONGTEXT),
        array('expack.tba.iphone.thread_title', 'iPhone/スレ内容/タイトル<br>例) BB2C'),
        array('expack.tba.android.board_uri', 'Android/スレ一覧/URI', P2_EDIT_CONF_USER_LONGTEXT),
        array('expack.tba.android.board_title', 'Android/スレ一覧/タイトル'),
        array('expack.tba.android.thread_uri', 'Android/スレ内容/URI', P2_EDIT_CONF_USER_LONGTEXT),
        array('expack.tba.android.thread_title', 'Android/スレ内容/タイトル'),
        array('expack.tba.mobile.board_uri', '携帯/スレ一覧/URI', P2_EDIT_CONF_USER_LONGTEXT),
        array('expack.tba.mobile.board_title', '携帯/スレ一覧/タイトル'),
        array('expack.tba.mobile.thread_uri', '携帯/スレ内容/URI', P2_EDIT_CONF_USER_LONGTEXT),
        array('expack.tba.mobile.thread_title', '携帯/スレ内容/タイトル'),
        array('expack.tba.other.board_uri', 'PC/スレ一覧/URI', P2_EDIT_CONF_USER_LONGTEXT),
        array('expack.tba.other.board_title', 'PC/スレ一覧/タイトル'),
        array('expack.tba.other.thread_uri', 'PC/スレ内容/URI', P2_EDIT_CONF_USER_LONGTEXT),
        array('expack.tba.other.thread_title', 'PC/スレ内容/タイトル'),
    );
    printEditConfGroupHtml($groupname, $conflist, $flags);
    unset($_tba_board_uri_description, $_tba_thread_uri_description);
}

// }}}
// {{{ expack - +Wiki

$groupname = '+Wiki';
$groups[] = $groupname;
$flags = getGroupShowFlags($groupname);
if ($flags & P2_EDIT_CONF_USER_SKIPPED) {
    $keep_old = true;
} else {
    $conflist = array(
        '画像置換URL',
        array('wiki.replaceimageurl.extract_cache', '画像置換URLのEXTRACTキャッシュ制御'),
        'スマートポップアップメニュー外部ツール',
        array('wiki.idsearch.spm.mimizun.enabled', 'みみずんID検索'),
        array('wiki.idsearch.spm.hissi.enabled', '必死チェッカーID検索'),
        array('wiki.idsearch.spm.stalker.enabled', 'IDストーカーID検索'),
    );
    printEditConfGroupHtml($groupname, $conflist, $flags);
}

// }}}
// }}}

// PC用表示
if (!$_conf['ktai']) {
    echo <<<EOP
</div><!-- end of tab -->
</div><!-- end of child tabset "拡張パック設定" -->

<div class="tabbertab" title="+live設定">
<h3>+live設定</h3>
<div class="tabber">\n
EOP;
}

// {{{ +live設定
// {{{ +live - 表示設定

$groupname = '表示設定';
$groups[] = $groupname;
$flags = getGroupShowFlags($groupname);
if ($flags & P2_EDIT_CONF_USER_SKIPPED) {
	$keep_old = true;
} else {
	$conflist = array(
        array('live.livebbs_list', '実況板(カンマ区切り)'),
        '実況用表示へのリンク',
        array('live.livelink_subject', 'スレッド一覧に実況用表示へのリンクを表示する'),
        array('live.livelink_thread', 'レス表示のヘッダとフッターに実況用表示へのリンクを表示する'),
        array('live.livebbs_forcelive', '実況板のスレッドを常に実況用表示で開く'),
        'レス表示',
		array('live.view_type', 'レス表示の種類'),
		array('live.id_b', 'ID末尾の O (携帯) P (公式p2) Q (フルブラウザ) i (iPhone)を太字に'),
		array('live.highlight_chain', '連鎖ハイライト (連鎖範囲は ngaborn_chain_all にて設定)'),
	);
	printEditConfGroupHtml($groupname, $conflist, $flags);
}

// }}}
// {{{ +live - 実況中設定

$groupname = '実況中設定';
$groups[] = $groupname;
$flags = getGroupShowFlags($groupname);
if ($flags & P2_EDIT_CONF_USER_SKIPPED) {
	$keep_old = true;
} else {
	$conflist = array(
		array('live.before_respointer', '表示するレス数 (100以下推奨)'),
		array('live.post_width', '下部書込フレームの高さ (px)'),
		array('live.bbs_noname', 'デフォルトの名無しの表示'),
		array('live.mail_sage', 'sage を ▼ に'),
		array('live.msg', '全ての改行とスペースの削除'),
		array('live.res_button', '[これにレス] の方法'),
		array('live.write_regulation', '書込規制用タイマー'),
		array('live.ic2_onoff', 'ImageCache2のサムネイル作成'),
	);
	printEditConfGroupHtml($groupname, $conflist, $flags);
}

// }}}
// {{{ +live - リロード/スクロール

$groupname = 'リロード/スクロール';
$groups[] = $groupname;
$flags = getGroupShowFlags($groupname);
if ($flags & P2_EDIT_CONF_USER_SKIPPED) {
	$keep_old = true;
} else {
	$conflist = array(
		array('live.reload_time', 'オートリロードの間隔'),
		array('live.scroll_move', 'オートスクロールの滑らかさ (最も滑らか 1 、スクロール無し 0)'),
		array('live.scroll_speed', 'オートスクロールの速度<br> (最速 1 、スクロール無しの場合は上の滑らかさの値を 0 に)'),
	);
	printEditConfGroupHtml($groupname, $conflist, $flags);
}

// }}}
// }}}

// PC用表示
if (empty($_conf['ktai'])) {
	echo <<<EOP
</div><!-- end of tab -->
</div><!-- end of child tabset "+live" -->
</div><!-- end of parent tabset -->\n
EOP;
// 携帯用表示
} else {
    if (!empty($selected_group)) {
        $group_en = UrlSafeBase64::encode($selected_group);
        echo "<input type=\"hidden\" name=\"edit_conf_user_group_en\" value=\"{$group_en}\">";
        echo $htm['form_submit'];
    }
}

echo <<<EOP
{$_conf['detect_hint_input_ht']}{$_conf['k_input_ht']}
</form>\n
EOP;


// 携帯なら
if ($_conf['ktai']) {
    echo <<<EOP
<hr>
<form method="GET" action="{$_SERVER['SCRIPT_NAME']}">
<select name="edit_conf_user_group_en">
EOP;
    if ($_conf['iphone']) {
        echo '<optgroup label="rep2基本設定">';
    }
    foreach ($groups as $groupname) {
        if ($_conf['iphone']) {
            if ($groupname == 'tGrep') {
                echo '</optgroup><optgroup label="拡張パック設定">';
            } elseif ($groupname == '表示設定') {
                echo '</optgroup><optgroup label="+live設定">';
            } elseif ($groupname == 'subject-i') {
                echo '</optgroup><optgroup label="iPhone設定">';
            }
        }
        $group_ht = p2h($groupname);
        $group_en = UrlSafeBase64::encode($groupname);
        $selected = ($selected_group == $groupname) ? ' selected' : '';
        echo "<option value=\"{$group_en}\"{$selected}>{$group_ht}</option>";
    }
    if ($_conf['iphone']) {
        echo '</optgroup>';
    }
    echo <<<EOP
</select>
<input type="submit" value="の設定を編集">
{$_conf['detect_hint_input_ht']}{$_conf['k_input_ht']}
</form>
<hr>
<div class="center">
<a href="editpref.php{$_conf['k_at_q']}"{$_conf['k_accesskey_at']['up']}>{$_conf['k_accesskey_st']['up']}設定編集</a>
{$_conf['k_to_index_ht']}
</div>
EOP;
}

echo '</body></html>';

exit;

//=====================================================================
// 関数（このファイル内のみの利用）
//=====================================================================

// {{{ applyRules()

/**
 * ルール設定（$conf_user_rules）に基づいて、フィルタ処理（デフォルトセット）を行う
 *
 * @return  void
 */
function applyRules()
{
    global $conf_user_rules, $conf_user_def;

    if (!array_key_exists('conf_edit', $_POST) || !is_array($_POST['conf_edit'])) {
        return;
    }

    foreach ($conf_user_rules as $key => $rules ) {
        if (array_key_exists($key, $_POST['conf_edit'])) {
            $default = array_key_exists($key, $conf_user_def) ? $conf_user_def[$key] : null;
            $value = $_POST['conf_edit'][$key];

            if ($value !== $default) {
                foreach ($rules as $rule) {
                    if (is_string($rule)) {
                        if (strncmp($rule, '/', 1) === 0) {
                            if (!preg_match($rule, $value)) {
                                $value = $default;
                            }
                        } elseif (strncmp($rule, '!/', 2) === 0) {
                            if (preg_match(substr($rule, 1), $value)) {
                                $value = $default;
                            }
                        } else {
                            $value = call_user_func($rule, $value, $default);
                        }
                    } else {
                        $value = call_user_func($rule, $value, $default);
                    }

                    if ($value === $default) {
                        break;
                    }
                }

                $_POST['conf_edit'][$key] = $value;
            }
        }
    }
}

// }}}
// {{{ フィルタ関数
// emptyToDef() などのフィルタはEditConfFiterクラスなどにまとめる予定
// {{{ emptyToDef()

/**
 * emptyの時は、デフォルトセットする
 *
 * @param   string  $val    入力された値
 * @param   mixed   $def    デフォルトの値
 * @return  mixed
 */
function emptyToDef($val, $def)
{
    if (empty($val)) {
        $val = $def;
    }
    return $val;
}

// }}}
// {{{ notIntExceptMinusToDef()

/**
 * 正の整数化できる時は正の整数化（0を含む）し、
 * できない時は、デフォルトセットする
 *
 * @param   string  $str    入力された値
 * @param   int     $def    デフォルトの値
 * @return  int
 */
function notIntExceptMinusToDef($val, $def)
{
    // 全角→半角 矯正
    $val = mb_convert_kana($val, 'a');
    // 整数化できるなら
    if (is_numeric($val)) {
        // 整数化する
        $val = intval($val);
        // 負の数はデフォルトに
        if ($val < 0) {
            $val = intval($def);
        }
    // 整数化できないものは、デフォルトに
    } else {
        $val = intval($def);
    }
    return $val;
}

// }}}
// {{{ notFloatExceptMinusToDef()

/**
 * 正の実数化できる時は正の実数化（0を含む）し、
 * できない時は、デフォルトセットする
 *
 * @param   string  $str    入力された値
 * @param   float   $def    デフォルトの値
 * @return  float
 */
function notFloatExceptMinusToDef($val, $def)
{
    // 全角→半角 矯正
    $val = mb_convert_kana($val, 'a');
    // 実数化できるなら
    if (is_numeric($val)) {
        // 実数化する
        $val = floatval($val);
        // 負の数はデフォルトに
        if ($val < 0.0) {
            $val = floatval($def);
        }
    // 実数化できないものは、デフォルトに
    } else {
        $val = floatval($def);
    }
    return $val;
}

// }}}
// {{{ notSelToDef()

/**
 * 選択肢にない値はデフォルトセットする
 */
function notSelToDef()
{
    global $conf_user_def, $conf_user_sel, $conf_user_rad;

    $conf_user_list = array_merge($conf_user_sel, $conf_user_rad);
    $names = array_keys($conf_user_list);

    if (is_array($names)) {
        foreach ($names as $n) {
            if (isset($_POST['conf_edit'][$n])) {
                if (!array_key_exists($_POST['conf_edit'][$n], $conf_user_list[$n])) {
                    $_POST['conf_edit'][$n] = $conf_user_def[$n];
                }
            }
        }
    }
    return true;
}

// }}}
// {{{ invalidUrlToDef()

/**
 * HTTPまたはHTTPSのURLでない場合はデフォルトセットする
 *
 * @param   string  $str    入力された値
 * @param   string  $def    デフォルトの値
 * @return  string
 */
function invalidUrlToDef($val, $def)
{
    $purl = @parse_url($val);
    if (is_array($purl) && array_key_exists('scheme', $purl) &&
        ($purl['scheme'] == 'http' || $purl['scheme'] == 'https'))
    {
        return $val;
    }
    return $def;
}

// }}}
// {{{ escapeHtmlExceptEntity()

/**
 * 既存のエンティティを除いて特殊文字をHTMLエンティティ化する
 *
 * @param   string  $str    入力された値
 * @param   string  $def    デフォルトの値
 * @return  string
 */
function escapeHtmlExceptEntity($val, $def)
{
    return p2h($val, false);
}

// }}}
// {{{ notHtmlColorToDef()

/**
 * 空の場合とHTMLの色として正しくない場合は、デフォルトセットする
 * W3Cの仕様で定義されていないが、ブラウザは認識する名前は許可しない
 * orangeはCSS2.1の色だけど、例外的に許可
 *
 * @param   string  $str    入力された値
 * @param   string  $def    デフォルトの値
 * @return  string
 */
function notHtmlColorToDef($val, $def)
{
    if (strlen($val) == 0) {
        return $def;
    }

    $val = strtolower($val);

    // 色名か16進数
    if (in_array($val, array('black',   // #000000
                             'silver',  // #c0c0c0
                             'gray',    // #808080
                             'white',   // #ffffff
                             'maroon',  // #800000
                             'red',     // #ff0000
                             'purple',  // #800080
                             'fuchsia', // #ff00ff
                             'green',   // #008000
                             'lime',    // #00ff00
                             'olive',   // #808000
                             'yellow',  // #ffff00
                             'navy',    // #000080
                             'blue',    // #0000ff
                             'teal',    // #008080
                             'aqua',    // #00ffff
                             'orange',  // #ffa500
                             )) ||
        preg_match('/^#[0-9a-f]{6}$/', $val))
    {
        return $val;
    }

    return $def;
}

// }}}
// {{{ notCssColorToDef()

/**
 * 空の場合とCSSの色として正しくない場合は、デフォルトセットする
 * W3Cの仕様で定義されていないが、ブラウザは認識する名前は許可しない
 * transparent,inherit,noneは許可
 *
 * @param   string  $str    入力された値
 * @param   string  $def    デフォルトの値
 * @return  string
 */
function notCssColorToDef($val, $def)
{
    if (strlen($val) == 0) {
        return $def;
    }

    $val = strtolower($val);

    // 色名か16進数
    if (in_array($val, array('black',   // #000000
                             'silver',  // #c0c0c0
                             'gray',    // #808080
                             'white',   // #ffffff
                             'maroon',  // #800000
                             'red',     // #ff0000
                             'purple',  // #800080
                             'fuchsia', // #ff00ff
                             'green',   // #008000
                             'lime',    // #00ff00
                             'olive',   // #808000
                             'yellow',  // #ffff00
                             'navy',    // #000080
                             'blue',    // #0000ff
                             'teal',    // #008080
                             'aqua',    // #00ffff
                             'orange',  // #ffa500
                             'transparent',
                             'inherit',
                             'none')) ||
        preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/', $val))
    {
        return $val;
    }

    // rgb(d,d,d)
    if (preg_match('/rgb\\(
                    [ ]*(0|[1-9][0-9]*)[ ]*,
                    [ ]*(0|[1-9][0-9]*)[ ]*,
                    [ ]*(0|[1-9][0-9]*)[ ]*
                    \\)/x', $val, $m))
    {
        return sprintf('rgb(%d, %d, %d)',
                       min(255, (int)$m[1]),
                       min(255, (int)$m[2]),
                       min(255, (int)$m[3])
                       );
    }

    // rgba(%,%,%)
    if (preg_match('/rgb\\(
                    [ ]*(0|[1-9][0-9]*)%[ ]*,
                    [ ]*(0|[1-9][0-9]*)%[ ]*,
                    [ ]*(0|[1-9][0-9]*)%[ ]*
                    \\)/x', $val, $m))
    {
        return sprintf('rgb(%d%%, %d%%, %d%%)',
                       min(100, (int)$m[1]),
                       min(100, (int)$m[2]),
                       min(100, (int)$m[3])
                       );
    }

    // rgba(d,d,d,f)
    if (preg_match('/rgba\\(
                    [ ]*(0|[1-9][0-9]*)[ ]*,
                    [ ]*(0|[1-9][0-9]*)[ ]*,
                    [ ]*(0|[1-9][0-9]*)[ ]*,
                    [ ]*([01](?:\\.[0-9]+)?)[ ]*
                    \\)/x', $val, $m))
    {
        return sprintf('rgba(%d, %d, %d, %0.2f)',
                       min(255, (int)$m[1]),
                       min(255, (int)$m[2]),
                       min(255, (int)$m[3]),
                       min(1.0, (float)$m[4])
                       );
    }

    // rgba(%,%,%,f)
    if (preg_match('/rgba\\(
                    [ ]*(0|[1-9][0-9]*)%[ ]*,
                    [ ]*(0|[1-9][0-9]*)%[ ]*,
                    [ ]*(0|[1-9][0-9]*)%[ ]*,
                    [ ]*([01](?:\\.[0-9]+)?)[ ]*
                    \\)/x', $val, $m))
    {
        return sprintf('rgba(%d%%, %d%%, %d%%, %0.2f)',
                       min(100, (int)$m[1]),
                       min(100, (int)$m[2]),
                       min(100, (int)$m[3]),
                       min(1.0, (float)$m[4])
                       );
    }

    return $def;
}

// }}}
// {{{ notCssFontSizeToDef()

/**
 * CSSのフォントの大きさとして正しくない場合は、デフォルトセットする
 * media="screen" を前提に、in,cm,mm,pt,pc等の絶対的な単位はサポートしない
 *
 * @param   string  $str    入力された値
 * @param   string  $def    デフォルトの値
 * @return  string
 */
function notCssFontSizeToDef($val, $def)
{
    if (strlen($val) == 0) {
        return $def;
    }

    $val = strtolower($val);

    // キーワード
    if (in_array($val, array('xx-large', 'x-large', 'large',
                             'larger', 'medium', 'smaller',
                             'small', 'x-small', 'xx-small')))
    {
        return $val;
    }

    // 整数
    if (preg_match('/^[1-9][0-9]*(?:em|ex|px|%)$/', $val)) {
        return $val;
    }

    // 実数 (小数点第3位で四捨五入、余分な0を切り捨て)
    if (preg_match('/^((?:0|[1-9][0-9]*)\\.[0-9]+)(em|ex|px|%)$/', $val, $m)) {
        $val = rtrim(sprintf('%0.2f', (float)$m[1]), '.0');
        if ($val !== '0') {
            return $val . $m[2];
        }
    }

    return $def;
}

// }}}
// {{{ notCssSizeToDef()

/**
 * CSSの大きさとして正しくない場合は、デフォルトセットする
 * media="screen" を前提に、in,cm,mm,pt,pc等の絶対的な単位はサポートしない
 *
 * @param   string  $str    入力された値
 * @param   string  $def    デフォルトの値
 * @param   boolean $allow_zero
 * @param   boolean $allow_negative
 * @return  string
 */
function notCssSizeToDef($val, $def, $allow_zero = true, $allow_negative = true)
{
    if (strlen($val) == 0) {
        return $def;
    }

    $val = strtolower($val);

    // 0
    if ($allow_zero && $val === '0') {
        return '0';
    }

    // 整数 (0は単位なしに)
    if (preg_match('/^(-?(?:0|[1-9][0-9]*))(?:em|ex|px|%)$/', $val, $m)) {
        $i = (int)$m[1];
        if ($i > 0 || ($i < 0 && $allow_negative) || $allow_zero) {
            if ($i === 0) {
                return '0';
            } else {
                return $val;
            }
        }
    }

    // 実数 (小数点第3位で四捨五入、余分な0を切り捨て)
    if (preg_match('/^(-?(?:0|[1-9][0-9]*)\\.[0-9]+)(em|ex|px|%)$/', $val, $m)) {
        $f = (float)$m[1];
        if ($f > 0.0 || ($f < 0.0 && $allow_negative) || $allow_zero) {
            $val = rtrim(sprintf('%0.2f', $f), '.0');
            if ($val === '0') {
                if ($allow_zero) {
                    return '0';
                }
            } else {
                return $val . $m[2];
            }
        }
    }

    return $def;
}

// }}}
// {{{ notCssPositiveSizeToDef()

/**
 * CSSの大きさとして正しくない場合か、正の値でないときは、デフォルトセットする
 *
 * @param   string  $str    入力された値
 * @param   string  $def    デフォルトの値
 * @return  string
 */
function notCssPositiveSizeToDef($val, $def)
{
    return notCssSizeToDef($val, $def, false, false);
}

// }}}
// {{{ notCssSizeExceptMinusToDef()

/**
 * CSSの大きさとして正しくない場合か、負の値のときは、デフォルトセットする
 *
 * @param   string  $str    入力された値
 * @param   string  $def    デフォルトの値
 * @return  string
 */
function notCssSizeExceptMinusToDef($val, $def)
{
    return notCssSizeToDef($val, $def, true, false);
}

// }}}
// }}}
// {{{ 表示用関数
// {{{ getGroupShowFlags()

/**
 * グループの表示モードを得る
 *
 * @param   stirng  $group_key  グループ名
 * @param   string  $conf_key   設定項目名
 * @return  int
 */
function getGroupShowFlags($group_key, $conf_key = null)
{
    global $_conf, $selected_group;

    $flags = P2_EDIT_CONF_USER_DEFAULT;

    if (empty($selected_group) || ($selected_group != 'all' && $selected_group != $group_key)) {
        $flags |= P2_EDIT_CONF_USER_HIDDEN;
        if ($_conf['ktai']) {
            $flags |= P2_EDIT_CONF_USER_SKIPPED;
        }
    }
    if (!empty($conf_key)) {
        if (empty($_conf[$conf_key])) {
            $flags |= P2_EDIT_CONF_USER_DISABLED;
        }
        if (preg_match('/^expack\\./', $conf_key)) {
            $flags |= P2_EDIT_CONF_FILE_ADMIN_EX;
        } else {
            $flags |= P2_EDIT_CONF_FILE_ADMIN;
        }
    }
    return $flags;
}

// }}}
// {{{ getGroupSepaHtml()

/**
 * グループ分け用のHTMLを得る（関数内でPC、携帯用表示を振り分け）
 *
 * @param   stirng  $title  グループ名
 * @param   int     $flags  表示モード
 * @return  string
 */
function getGroupSepaHtml($title, $flags)
{
    global $_conf;

    $admin_php = ($flags & P2_EDIT_CONF_FILE_ADMIN_EX) ? 'conf_admin_ex' : 'conf_admin';

    // PC用
    if (!$_conf['ktai']) {
        $ht = <<<EOP
<div class="tabbertab" title="{$title}">
<h4>{$title}</h4>\n
EOP;
        if ($flags & P2_EDIT_CONF_USER_DISABLED) {
            $ht .= <<<EOP
<p><i>現在、この機能は無効になっています。<br>
有効にするには conf/{$admin_php}.inc.php で {$title} を on にしてください。</i></p>\n
EOP;
        }
        $ht .= <<<EOP
<table class="edit_conf_user" cellspacing="0">
    <tr>
        <th>変数名</th>
        <th>値</th>
        <th>説明</th>
    </tr>\n
EOP;
    // 携帯用
    } else {
        if ($flags & P2_EDIT_CONF_USER_HIDDEN) {
            $ht = '';
        } else {
            $ht = "<hr><h4>{$title}</h4>" . "\n";
            if ($flags & P2_EDIT_CONF_USER_DISABLED) {
            $ht .= <<<EOP
<p>現在、この機能は無効になっています。<br>
有効にするには conf/{$admin_php}.inc.php で {$title} を on にしてください。</p>\n
EOP;
            }
        }
    }
    return $ht;
}

// }}}
// {{{ getConfBorderHtml()

/**
 * グループ終端のHTMLを得る（携帯では空）
 *
 * @param   string  $label  ラベル
 * @return  string
 */
function getConfBorderHtml($label)
{
    global $_conf;

    if ($_conf['ktai']) {
        $format = '<p>[%s]</p>';
    } else {
        $format = '<tr class="group"><td colspan="3" align="center">%s</td></tr>';
    }

    return sprintf($format, p2h($label));
}

// }}}
// {{{ getGroupEndHtml()

/**
 * グループ終端のHTMLを得る（携帯では空）
 *
 * @param   int     $flags  表示モード
 * @return  string
 */
function getGroupEndHtml($flags)
{
    global $_conf;

    // PC用
    if (!$_conf['ktai']) {
        $ht = '';
        if (!($flags & P2_EDIT_CONF_USER_HIDDEN)) {
            $ht .= <<<EOP
    <tr class="group">
        <td colspan="3" align="center">
            <input type="submit" name="submit_save" value="変更を保存する">
            <input type="reset"  name="reset_change" value="変更を取り消す" onclick="return window.confirm('変更を取り消してもよろしいですか？\\n（全てのタブの変更がリセットされます）');">
            <input type="submit" name="submit_default" value="デフォルトに戻す" onclick="return window.confirm('ユーザ設定をデフォルトに戻してもよろしいですか？\\n（やり直しはできません）');">
        </td>
    </tr>\n
EOP;
        }
        $ht .= <<<EOP
</table>
</div><!-- end of tab -->\n
EOP;
    // 携帯用
    } else {
        $ht = '';
    }
    return $ht;
}

// }}}
// {{{ getEditConfHtml()

/**
 * 編集フォームinput用HTMLを得る（関数内でPC、携帯用表示を振り分け）
 *
 * @param   stirng  $name   設定項目名
 * @param   string  $description_ht HTML形式の説明
 * @param   int     $flags  表示モード
 * @return  string
 */
function getEditConfHtml($name, $description_ht, $flags)
{
    global $_conf, $conf_user_def, $conf_user_sel, $conf_user_rad;

    // デフォルト値の規定がなければ、空白を返す
    if (!isset($conf_user_def[$name])) {
        return '';
    }

    $name_view = p2h($_conf[$name]);

    // 無効or非表示なら
    if ($flags & (P2_EDIT_CONF_USER_HIDDEN | P2_EDIT_CONF_USER_DISABLED)) {
        $form_ht = getEditConfHidHtml($name);
        // 携帯ならそのまま返す
        if ($_conf['ktai']) {
            return $form_ht;
        }
        if ($name_view === '') {
            $form_ht .= '<i>(empty)</i>';
        } else {
            $form_ht .= $name_view;
        }
        if (is_string($conf_user_def[$name])) {
            $def_views[$name] = p2h($conf_user_def[$name]);
        } else {
            $def_views[$name] = strval($conf_user_def[$name]);
        }
    // select 選択形式なら
    } elseif (isset($conf_user_sel[$name])) {
        $form_ht = getEditConfSelHtml($name);
        $key = $conf_user_def[$name];
        $def_views[$name] = p2h($conf_user_sel[$name][$key]);
    // radio 選択形式なら
    } elseif (isset($conf_user_rad[$name])) {
        $form_ht = getEditConfRadHtml($name);
        $key = $conf_user_def[$name];
        $def_views[$name] = p2h($conf_user_rad[$name][$key]);
    // input 入力式なら
    } else {
        if (!$_conf['ktai']) {
            $input_size_at = sprintf(' size="%d"', ($flags & P2_EDIT_CONF_USER_LONGTEXT) ? 40 : 20);
        } else {
            $input_size_at = '';
        }
        $input_type = ($flags & P2_EDIT_CONF_USER_PASSWORD) ? 'password' : 'text';
        $form_ht = <<<EOP
<input type="{$input_type}" name="conf_edit[{$name}]" value="{$name_view}"{$input_size_at}>
EOP;
        if (is_string($conf_user_def[$name])) {
            $def_views[$name] = p2h($conf_user_def[$name]);
        } else {
            $def_views[$name] = strval($conf_user_def[$name]);
        }
    }

    // iPhone用
    if ($_conf['iphone']) {
        return "<fieldset><legend>{$name}</legend>{$description_ht}<br>{$form_ht}</fieldset>\n";

    // 携帯用
    } elseif ($_conf['ktai']) {
        return "[{$name}]<br>{$description_ht}<br>{$form_ht}<br><br>\n";

    // PC用
    } else {
        return <<<EOP
    <tr title="デフォルト値: {$def_views[$name]}">
        <td>{$name}</td>
        <td>{$form_ht}</td>
        <td>{$description_ht}</td>
    </tr>\n
EOP;
    }
}

// }}}
// {{{ getEditConfHidHtml()

/**
 * 編集フォームhidden用HTMLを得る
 *
 * @param   stirng  $name   設定項目名
 * @return  string
 */
function getEditConfHidHtml($name)
{
    global $_conf, $conf_user_def;

    if (isset($_conf[$name]) && $_conf[$name] != $conf_user_def[$name]) {
        $value_ht = p2h($_conf[$name]);
    } else {
        $value_ht = p2h($conf_user_def[$name]);
    }

    $form_ht = "<input type=\"hidden\" name=\"conf_edit[{$name}]\" value=\"{$value_ht}\">";

    return $form_ht;
}

// }}}
// {{{ getEditConfSelHtml()

/**
 * 編集フォームselect用HTMLを得る
 *
 * @param   stirng  $name   設定項目名
 * @return  string
 */
function getEditConfSelHtml($name)
{
    global $_conf, $conf_user_def, $conf_user_sel;

    $form_ht = "<select name=\"conf_edit[{$name}]\">\n";

    foreach ($conf_user_sel[$name] as $key => $value) {
        /*
        if ($value == "") {
            continue;
        }
        */
        $selected = "";
        if ($_conf[$name] == $key) {
            $selected = " selected";
        }
        $key_ht = p2h($key);
        $value_ht = p2h($value);
        $form_ht .= "\t<option value=\"{$key_ht}\"{$selected}>{$value_ht}</option>\n";
    } // foreach

    $form_ht .= "</select>\n";

    return $form_ht;
}

// }}}
// {{{ getEditConfRadHtml()

/**
 * 編集フォームradio用HTMLを得る
 *
 * @param   stirng  $name   設定項目名
 * @return  string
 */
function getEditConfRadHtml($name)
{
    global $_conf, $conf_user_def, $conf_user_rad;

    $form_ht = '';

    foreach ($conf_user_rad[$name] as $key => $value) {
        /*
        if ($value == "") {
            continue;
        }
        */
        $checked = "";
        if ($_conf[$name] == $key) {
            $checked = " checked";
        }
        $key_ht = p2h($key);
        $value_ht = p2h($value);
        if ($_conf['iphone']) {
            $form_ht .= "<input type=\"radio\" name=\"conf_edit[{$name}]\" value=\"{$key_ht}\"{$checked}><span onclick=\"if(!this.previousSibling.checked)this.previousSibling.checked=true;\">{$value_ht}</span>\n";
        } else {
            $form_ht .= "<label><input type=\"radio\" name=\"conf_edit[{$name}]\" value=\"{$key_ht}\"{$checked}>{$value_ht}</label>\n";
        }
    } // foreach

    return $form_ht;
}

// }}}
// {{{ printEditConfGroupHtml()

/**
 * 編集フォームを表示する
 *
 * @param   stirng  $groupname  グループ名
 * @param   array   $conflist   設定項目名と説明の配列
 * @param   int     $flags      表示モード
 * @return  void
 */
function printEditConfGroupHtml($groupname, $conflist, $flags)
{
    echo getGroupSepaHtml($groupname, $flags);
    foreach ($conflist as $c) {
        if (!is_array($c)) {
            echo getConfBorderHtml($c);
        } elseif (isset($c[2]) && is_integer($c[2]) && $c[2] > 0) {
            echo getEditConfHtml($c[0], $c[1], $c[2] | $flags);
        } else {
            echo getEditConfHtml($c[0], $c[1], $flags);
        }
    }
    echo getGroupEndHtml($flags);
}

// }}}
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
