<?php
/*
    rep2-ip2host - ユーザ設定 デフォルト
    
    このファイルはデフォルト値の設定なので、特に変更する必要はありません
*/

// {{{ キャッシュ方法

// キャッシュ方法(sessionStorage:0, localStorage:1, サーバー側ファイル:2)
$conf_user_def['ip2host.cache.type'] = 0; // (0)
$conf_user_sel['ip2host.cache.type'] = array(
    '0' => 'sessionStorage(ブラウザ側)',
    '1' => 'localStorage(ブラウザ側)',
    '2' => 'ファイル(サーバー側)',
);

// }}}

// {{{ 書き換えのタイミング

// 書き換えのタイミング(スレ表示時に一括書き換え:0, 画面スクロールで書き換え:1)
$conf_user_def['ip2host.replace.type'] = 0; // (0)
$conf_user_sel['ip2host.replace.type'] = array(
    '0' => 'スレ表示時に一括書き換え',
    '1' => '画面スクロールで書き換え',
);

// }}}

// {{{ ip2hostの設定

// ip2hostを使用するか
$conf_user_def['ip2host.enabled'] = 0; // (0)
$conf_user_rad['ip2host.enabled'] = array('1' => 'する', '0' => 'しない');

// キャッシュの上限数
$conf_user_def['ip2host.cache.size'] = 500; // (500)
$conf_user_rules['ip2host.cache.size'] = array('emptyToDef', 'notIntExceptMinusToDef');

// 逆引き後のあぼーん処理をするか
$conf_user_def['ip2host.aborn.enabled'] = 1; // (1)
$conf_user_rad['ip2host.aborn.enabled'] = array('1' => 'する', '0' => 'しない');

// }}}
