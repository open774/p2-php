<?php
/*
    rep2expack - ユーザ設定 デフォルト

    このファイルはデフォルト値の設定なので、特に変更する必要はありません
*/

// {{{ 携帯のカラーリング設定

// 背景
$conf_user_def['mobile.background_color'] = ""; // ("")
$conf_user_rules['mobile.background_color'] = array('notHtmlColorToDef');

// 基本文字色
$conf_user_def['mobile.text_color'] = ""; // ("")
$conf_user_rules['mobile.text_color'] = array('notHtmlColorToDef');

// リンク
$conf_user_def['mobile.link_color'] = ""; // ("")
$conf_user_rules['mobile.link_color'] = array('notHtmlColorToDef');

// 訪問済みリンク
$conf_user_def['mobile.vlink_color'] = ""; // ("")
$conf_user_rules['mobile.vlink_color'] = array('notHtmlColorToDef');

// 新着スレッドマーク
$conf_user_def['mobile.newthre_color'] = "#ff0000"; // ("#ff0000")
$conf_user_rules['mobile.newthre_color'] = array('notHtmlColorToDef');

// スレッドタイトル
$conf_user_def['mobile.ttitle_color'] = "#1144aa"; // ("#1144aa")
$conf_user_rules['mobile.ttitle_color'] = array('notHtmlColorToDef');

// 新着レス番号
$conf_user_def['mobile.newres_color'] = "#ff6600"; // ("#ff6600")
$conf_user_rules['mobile.newres_color'] = array('notHtmlColorToDef');

// NGワード
$conf_user_def['mobile.ngword_color'] = "#bbbbbb"; // ("#bbbbbb")
$conf_user_rules['mobile.ngword_color'] = array('notHtmlColorToDef');

// オンザフライレス番号
$conf_user_def['mobile.onthefly_color'] = "#00aa00"; // ("#00aa00")
$conf_user_rules['mobile.onthefly_color'] = array('notHtmlColorToDef');

// フィルタリングでマッチしたキーワード
$conf_user_def['mobile.match_color'] = ""; // ("")
$conf_user_rules['mobile.match_color'] = array('notHtmlColorToDef');

// アクセスキーの番号を表示（しない:0, する:1, 絵文字:2）
$conf_user_def['mobile.display_accesskey'] = 1; // (1)
$conf_user_rad['mobile.display_accesskey'] = array('2' => '絵文字', '1' => '表示', '0' => '非表示');

// }}}
// {{{ tGrep

// 一発検索（off:0, on:1）
$conf_user_def['expack.tgrep.quicksearch'] = 1; // (1)
$conf_user_rad['expack.tgrep.quicksearch'] = array('1' => '表示', '0' => '非表示');

// 検索履歴を記録する数（off:0）
$conf_user_def['expack.tgrep.recent_num'] = 10; // (10)
$conf_user_rules['expack.tgrep.recent_num'] = array('notIntExceptMinusToDef');

// サーチボックスに検索履歴を記録する数、Safari専用（off:0）
$conf_user_def['expack.tgrep.recent2_num'] = 10; // (10)
$conf_user_rules['expack.tgrep.recent2_num'] = array('notIntExceptMinusToDef');

// 検索に使用する検索エンジン
$conf_user_def['expack.tgrep.engine'] = '4';
$conf_user_sel['expack.tgrep.engine'] = array(
    '0' => sprintf('tGrep (%s)', parse_url($_conf['expack.tgrep_url'], PHP_URL_HOST)), // tGrep(互換)サービス
    '1' => 'dig (dig.5ch.net)', // もうずっとサービス停止中
    '2' => 're.Find2ch (refind2ch.org)', // https://refind2ch.org/about
    '3' => '5chスレタイ検索 (ff5ch.syoboi.jp)', // http://z.syoboi.jp/web/ff2ch/
    '4' => '5ちゃんねるスレタイ検索 (find.5ch.net)', // http://find.5ch.net/
);

// }}}
// {{{ スマートポップアップメニュー

// ここにレス（off:0, on:1）
// conf_admin_ex.inc.php で $_conf['disable_res'] が 1 になっていると使えない
$conf_user_def['expack.spm.kokores'] = 1; // (1)
$conf_user_rad['expack.spm.kokores'] = array('1' => '表示', '0' => '非表示');

// ここにレスで開くフォームに元レスの内容を表示する（off:0, on:1）
$conf_user_def['expack.spm.kokores_orig'] = 1; // (1)
$conf_user_rad['expack.spm.kokores_orig'] = array('1' => 'する', '0' => 'しない');

// あぼーんワード・NGワード登録（off:0, on:1）
$conf_user_def['expack.spm.ngaborn'] = 1; // (1)
$conf_user_rad['expack.spm.ngaborn'] = array('1' => '表示', '0' => '非表示');

// あぼーんワード・NGワード登録時に確認する（off:0, on:1）
$conf_user_def['expack.spm.ngaborn_confirm'] = 1; // (1)
$conf_user_rad['expack.spm.ngaborn_confirm'] = array('1' => 'する', '0' => 'しない');

// フィルタリング（off:0, on:1）
$conf_user_def['expack.spm.filter'] = 1; // (1)
$conf_user_rad['expack.spm.filter'] = array('1' => '表示', '0' => '非表示');

// フィルタリング結果を開くフレームまたはウインドウ
$conf_user_def['expack.spm.filter_target'] = "_popup"; // ("_popup")
$conf_user_sel['expack.spm.filter_target'] = array(
    '_popup'    => 'HTMLポップアップ',
    '_blank'    => '新規ウインドウ',
    '_self'     => '同じフレーム',
    //'_parent' => '親フレーム',
    //'_top'    => '同じウインドウ',
);

// }}}
// {{{ アクティブモナー

// フォント
$conf_user_def['expack.am.fontfamily'] = "Mona,モナー"; // ("Mona,モナー")

// 文字の大きさ
$conf_user_def['expack.am.fontsize'] = "16px"; // ("16px")

// スイッチを表示する位置
$conf_user_def['expack.am.display'] = 0; // (0)
$conf_user_sel['expack.am.display'] = array('0' => 'IDの横', '1' => 'SPM', '2' => '両方');

// 自動判定 (PC)
$conf_user_def['expack.am.autodetect'] = 0; // (0)
$conf_user_rad['expack.am.autodetect'] = array('1' => 'する', '0' => 'しない');

// 自動判定 & NG ワード化、AAS が有効なら AAS のリンクも作成 (携帯)
$conf_user_def['expack.am.autong_k'] = 0; // (0)
$conf_user_rad['expack.am.autong_k'] = array('1' => 'する', '0' => 'しない', '2' => 'する (連鎖NGはしない)');

// 自動判定する行数の下限
$conf_user_def['expack.am.lines_limit'] = 5; // (5)
$conf_user_rules['expack.am.lines_limit'] = array('notIntExceptMinusToDef');

// }}}
// {{{ 入力支援

// 定型文
//$conf_user_def['expack.editor.constant'] = 0; // (0)
//$conf_user_rad['expack.editor.constant'] = array('1' => '使う', '0' => '使わない');

// リアルタイム・プレビュー
$conf_user_def['expack.editor.dpreview'] = 0; // (0)
$conf_user_sel['expack.editor.dpreview'] = array('1' => '投稿フォームの上に表示', '2' => '投稿フォームの下に表示', '0' => '非表示');

// リアルタイム・プレビューでAA補正用のチェックボックスを表示する
$conf_user_def['expack.editor.dpreview_chkaa'] = 0; // (0)
$conf_user_rad['expack.editor.dpreview_chkaa'] = array('1' => 'する', '0' => 'しない');

// 本文が空でないかチェック
$conf_user_def['expack.editor.check_message'] = 1; // (1)
$conf_user_rad['expack.editor.check_message'] = array('1' => 'する', '0' => 'しない');

// sage チェック
$conf_user_def['expack.editor.check_sage'] = 1; // (1)
$conf_user_rad['expack.editor.check_sage'] = array('1' => 'する', '0' => 'しない');

// 下書き保存
$conf_user_def['expack.editor.savedraft'] = 1; // (1)
$conf_user_rad['expack.editor.savedraft'] = array('1' => 'する', '0' => 'しない');
// 下書きを自動保存するインターバル(秒)
$conf_user_def['expack.editor.savedraft.interval'] = 20; // (20)
$conf_user_rules['expack.editor.savedraft.interval'] = array('notIntExceptMinusToDef');
// 下書き保存(携帯)
$conf_user_def['expack.editor.mobile.savedraft'] = 1; // (1)
$conf_user_rad['expack.editor.mobile.savedraft'] = array('1' => 'する', '0' => 'しない');
// 下書きを自動保存するインターバル(秒)(iphone)
$conf_user_def['expack.editor.mobile.savedraft.interval'] = 0; // (0)
$conf_user_rules['expack.editor.mobile.savedraft.interval'] = array('notIntExceptMinusToDef');

// }}}
// {{{ RSSリーダ

// RSSが更新されたかどうか確認する間隔（分指定）
$conf_user_def['expack.rss.check_interval'] = 30; // (30)
$conf_user_rules['expack.rss.check_interval'] = array('notIntExceptMinusToDef');

// RSSの外部リンクを開くフレームまたはウインドウ
$conf_user_def['expack.rss.target_frame'] = "read"; // ("read")

// 概要を開くフレームまたはウインドウ
$conf_user_def['expack.rss.desc_target_frame'] = "read"; // ("read")

// }}}
// {{{ ImageCache2

// 画像キャッシュ一覧のデフォルト表示モード
$conf_user_def['expack.ic2.viewer_default_mode'] = 0; // (0)
$conf_user_sel['expack.ic2.viewer_default_mode'] = array('3' => 'サムネイルだけ', '0' => '一覧', '1' => '一括変更', '2' => '個別管理');

// キャッシュに失敗したときの確認用にime経由でソースへのリンクを作成
$conf_user_def['expack.ic2.through_ime'] = 0; // (0)
$conf_user_rad['expack.ic2.through_ime'] = array('1' => 'する', '0' => 'しない');

// ポップアップ画像の大きさをウインドウの大きさに合わせる
$conf_user_def['expack.ic2.fitimage'] = 0; // (0)
$conf_user_sel['expack.ic2.fitimage'] = array('1' => 'する', '0' => 'しない', '2' => '幅が大きいときだけする', '3' => '高さが大きいときだけする', '4' => '手動でする');

// 携帯でインライン・サムネイルが有効のときの表示する制限数（0で無制限）
$conf_user_def['expack.ic2.pre_thumb_limit_k'] = 5; // (5)
$conf_user_rules['expack.ic2.pre_thumb_limit_k'] = array('notIntExceptMinusToDef');

// 新着レスの画像は pre_thumb_limit を無視して全て表示する
$conf_user_def['expack.ic2.newres_ignore_limit'] = 0; // (0)
$conf_user_rad['expack.ic2.newres_ignore_limit'] = array('1' => 'する', '0' => 'しない');

// 携帯で新着レスの画像は pre_thumb_limit_k を無視して全て表示する
$conf_user_def['expack.ic2.newres_ignore_limit_k'] = 0; // (0)
$conf_user_rad['expack.ic2.newres_ignore_limit_k'] = array('1' => 'する', '0' => 'しない');

// スレ表示時に画像キャッシュ一覧へのスレタイ検索リンクを表示する
$conf_user_def['expack.ic2.thread_imagelink'] = 1; // (1)
$conf_user_rad['expack.ic2.thread_imagelink'] = array('1' => 'する', '0' => 'しない');

// スレ表示時にスレタイで検索した時の画像数を表示する
$conf_user_def['expack.ic2.thread_imagecount'] = 1; // (1)
$conf_user_rad['expack.ic2.thread_imagecount'] = array('1' => 'する', '0' => 'しない');

// お気にスレに登録されているスレの画像に自動ランクを設定する
$conf_user_def['expack.ic2.fav_auto_rank'] = 0; // (0)
$conf_user_rad['expack.ic2.fav_auto_rank'] = array('1' => 'する', '0' => 'しない');

// お気にスレの画像を自動ランク設定する場合の設定値(カンマ区切り)[お気に0のランク値,お気に1のランク値, , ,]
$conf_user_def['expack.ic2.fav_auto_rank_setting'] = '1,1,2,3,4,5';

// お気にスレの画像を自動ランク設定する場合に、キャッシュ済み画像に自動ランクを上書きするか
$conf_user_def['expack.ic2.fav_auto_rank_override'] = 0; // (0)
$conf_user_rad['expack.ic2.fav_auto_rank_override'] = array('1' => 'する', '2' => 'ランク0だったらする', '3' => 'ランクが上がるならする', '0' => 'しない');

// サーバに接続する際にタイムアウトするまでの時間（秒）[0 => http_conn_timeout に依存]
$conf_user_def['expack.ic2.getter_conn_timeout'] = 5;
$conf_user_rules['expack.ic2.getter_conn_timeout'] = array('notIntExceptMinusToDef');

// ダウンロードがタイムアウトするまでの時間（秒）[0 => http_read_timeout に依存]
$conf_user_def['expack.ic2.getter_read_timeout'] = 60;
$conf_user_rules['expack.ic2.getter_read_timeout'] = array('notIntExceptMinusToDef');

// }}}
// {{{ AAS

// 携帯で AA と自動判定されたときインライン AAS 表示する（0:しない; 1:する;）
$conf_user_def['expack.aas.inline_enabled'] = 0; // (0)
$conf_user_rad['expack.aas.inline_enabled'] = array('1' => 'する', '0' => 'しない');

// PC用の画像形式（0:PNG; 1:JPEG; 2:GIF;）
$conf_user_def['expack.aas.default.type'] = 0; // (0)
$conf_user_sel['expack.aas.default.type'] = array('0' => 'PNG', '1' => 'JPEG', '2' => 'GIF');

// JPEGの品質（0-100）
$conf_user_def['expack.aas.default.quality'] = 80; // (80)
$conf_user_rules['expack.aas.default.quality'] = array('emptyToDef', 'notIntExceptMinusToDef');

// PC用の画像の横幅 (ピクセル)
$conf_user_def['expack.aas.default.width'] = 640; // (640)
$conf_user_rules['expack.aas.default.width'] = array('emptyToDef', 'notIntExceptMinusToDef');

// PC用の画像の高さ (ピクセル)
$conf_user_def['expack.aas.default.height'] = 480; // (480)
$conf_user_rules['expack.aas.default.height'] = array('emptyToDef', 'notIntExceptMinusToDef');

// PC用の画像のマージン (ピクセル)
$conf_user_def['expack.aas.default.margin'] = 5; // (5)
$conf_user_rules['expack.aas.default.margin'] = array('notIntExceptMinusToDef');

// 文字サイズ (ポイント)
$conf_user_def['expack.aas.default.fontsize'] = 16; // (16)
$conf_user_rules['expack.aas.default.fontsize'] = array('emptyToDef', 'notIntExceptMinusToDef');

// 文字が画像からはみ出る場合、リサイズして納める (0:リサイズ; 1:非表示)
$conf_user_def['expack.aas.default.overflow'] = 0; // (0)
$conf_user_rad['expack.aas.default.overflow'] = array('1' => '非表示', '0' => 'リサイズ');

// 太字にする (0:しない; 1:する)
$conf_user_def['expack.aas.default.bold'] = 0; // (0)
$conf_user_rad['expack.aas.default.bold'] = array('1' => 'する', '0' => 'しない');

// 文字色 (6桁または3桁の16進数)
$conf_user_def['expack.aas.default.fgcolor'] = '000000'; // ('000000')

// 背景色 (6桁または3桁の16進数)
$conf_user_def['expack.aas.default.bgcolor'] = 'ffffff'; // ('ffffff')

// 携帯用の画像形式（0:PNG; 1:JPEG; 2:GIF;）
$conf_user_def['expack.aas.mobile.type'] = 2; // (2)
$conf_user_sel['expack.aas.mobile.type'] = array('0' => 'PNG', '1' => 'JPEG', '2' => 'GIF');

// JPEGの品質（0-100）
$conf_user_def['expack.aas.mobile.quality'] = 80; // (80)
$conf_user_rules['expack.aas.mobile.quality'] = array('emptyToDef', 'notIntExceptMinusToDef');

// 携帯用の画像の横幅 (ピクセル)
$conf_user_def['expack.aas.mobile.width'] = 230; // (230)
$conf_user_rules['expack.aas.mobile.width'] = array('emptyToDef', 'notIntExceptMinusToDef');

// 携帯用の画像の高さ (ピクセル)
$conf_user_def['expack.aas.mobile.height'] = 450; // (450)
$conf_user_rules['expack.aas.mobile.height'] = array('emptyToDef', 'notIntExceptMinusToDef');

// 携帯用の画像のマージン (ピクセル)
$conf_user_def['expack.aas.mobile.margin'] = 2; // (2)
$conf_user_rules['expack.aas.mobile.margin'] = array('notIntExceptMinusToDef');

// 文字サイズ (ポイント)
$conf_user_def['expack.aas.mobile.fontsize'] = 16; // (16)
$conf_user_rules['expack.aas.mobile.fontsize'] = array('emptyToDef', 'notIntExceptMinusToDef');

// 文字が画像からはみ出る場合、リサイズして納める (0:リサイズ; 1:非表示)
$conf_user_def['expack.aas.mobile.overflow'] = 0; // (0)
$conf_user_rad['expack.aas.mobile.overflow'] = array('1' => '非表示', '0' => 'リサイズ');

// 太字にする (0:しない; 1:する)
$conf_user_def['expack.aas.mobile.bold'] = 0; // (0)
$conf_user_rad['expack.aas.mobile.bold'] = array('1' => 'する', '0' => 'しない');

// 文字色 (6桁または3桁の16進数)
$conf_user_def['expack.aas.mobile.fgcolor'] = '000000'; // ('000000')

// 背景色 (6桁または3桁の16進数)
$conf_user_def['expack.aas.mobile.bgcolor'] = 'ffffff'; // ('ffffff')

// インライン表示の画像形式（0:PNG; 1:JPEG; 2:GIF;）
$conf_user_def['expack.aas.inline.type'] = 2; // (2)
$conf_user_sel['expack.aas.inline.type'] = array('0' => 'PNG', '1' => 'JPEG', '2' => 'GIF');

// JPEGの品質（0-100）
$conf_user_def['expack.aas.inline.quality'] = 80; // (80)
$conf_user_rules['expack.aas.inline.quality'] = array('emptyToDef', 'notIntExceptMinusToDef');

// インライン表示の横幅 (ピクセル)
$conf_user_def['expack.aas.inline.width'] = 64; // (64)
$conf_user_rules['expack.aas.inline.width'] = array('emptyToDef', 'notIntExceptMinusToDef');

// インライン表示の高さ (ピクセル)
$conf_user_def['expack.aas.inline.height'] = 64; // (64)
$conf_user_rules['expack.aas.inline.height'] = array('emptyToDef', 'notIntExceptMinusToDef');

// インライン表示のマージン (ピクセル)
$conf_user_def['expack.aas.inline.margin'] = 0; // (0)
$conf_user_rules['expack.aas.inline.margin'] = array('notIntExceptMinusToDef');

// 文字サイズ (ポイント)
$conf_user_def['expack.aas.inline.fontsize'] = 6; // (6)
$conf_user_rules['expack.aas.inline.fontsize'] = array('emptyToDef', 'notIntExceptMinusToDef');

// 文字が画像からはみ出る場合、リサイズして納める (0:リサイズ; 1:非表示)
$conf_user_def['expack.aas.inline.overflow'] = 1; // (1)
$conf_user_rad['expack.aas.inline.overflow'] = array('1' => '非表示', '0' => 'リサイズ');

// 太字にする (0:しない; 1:する)
$conf_user_def['expack.aas.inline.bold'] = 0; // (0)
$conf_user_rad['expack.aas.inline.bold'] = array('1' => 'する', '0' => 'しない');

// 文字色 (6桁または3桁の16進数)
$conf_user_def['expack.aas.inline.fgcolor'] = '000000'; // ('000000')

// 背景色 (6桁または3桁の16進数)
$conf_user_def['expack.aas.inline.bgcolor'] = 'ffffff'; // ('ffffff')

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
