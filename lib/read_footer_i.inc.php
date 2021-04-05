<?php
/**
 * rep2 - スレッド表示 - フッタ部分 - iPhone用 for read.php
 */

require_once P2_LIB_DIR . '/read_jump_k.inc.php';

// {{{ 総数

if ($do_filtering) {
    $last_resnum = $resFilter->last_hit_resnum;
    $rescount_st = "{$resFilter->hits}hit / {$aThread->rescount}レス";
} else {
    $last_resnum = $aThread->resrange['to'];
    $rescount_st = "{$aThread->rescount}レス";
}

// }}}
// {{{ ツールバーを表示

echo '<div class="ntoolbar" id="footer"><div class="ntoolbar" id="pager">';
echo '<table><tbody><tr>';

// {{{ ページャ

// 前のページ
echo '<td>';
if ($read_navi_previous_url) {
    echo toolbar_i_standard_button('img/gp3-prev.png', null, $read_navi_previous_url);
} else {
    echo toolbar_i_disabled_button('img/gp3-prev.png', null);
}
echo '</td>';

// ページ番号を直接指定
echo '<td colspan="2">';
echo get_read_jump($aThread, '', true);
echo '</td>';

// 次のページ
echo '<td>';
if ($read_navi_next_url) {
    echo toolbar_i_standard_button('img/gp4-next.png', null, $read_navi_next_url);
} else {
    echo toolbar_i_disabled_button('img/gp4-next.png', null);
}
echo '</td>';

// 上へ
echo '<td>';
echo toolbar_i_standard_button('img/gp1-up.png', null, '#header');
echo '</td>';

// }}}

echo '</tr></tbody></table></div>';

// {{{書き込みフォーム
if ($_conf['bottom_res_form']) {
    $bbs = $aThread->bbs;
    $key = $aThread->key;
    $host = $aThread->host;
    $rescount = $aThread->rescount;
    $ttitle_en = UrlSafeBase64::encode($aThread->ttitle);

    $submit_value = '書き込む';

    $key_idx = $aThread->keyidx;

    // フォームのオプション読み込み
    require_once P2_LIB_DIR . '/post_form_options.inc.php';

    $htm['resform_ttitle'] = <<<EOP
<p><b class="thre_title">{$aThread->ttitle_hd}</b></p>
EOP;

    require_once P2_LIB_DIR . '/post_form.inc.php';

    echo <<<EOP
<div id="kakiko" class="extra">
{$htm['dpreview']}
{$htm['post_form']}
{$htm['dpreview2']}
</div>\n
EOP;
}
// }}}

// {{{ その他ボタン類

echo '<table><tbody><tr>';

// 新着
echo '<td>';
if (!$aThread->diedat) {
    $escaped_url = "{$_conf['read_php']}?{$host_bbs_key_q}&amp;ls={$aThread->rescount}-n&amp;nt={$newtime}{$_conf['k_at_a']}";
    echo toolbar_i_standard_button('img/glyphish/icons2/01-refresh.png', '新着', $escaped_url);
} else {
    echo toolbar_i_disabled_button('img/glyphish/icons2/01-refresh.png', '新着');
}
echo '</td>';

// スレ情報
echo '<td>';
$escaped_url = "info.php?{$host_bbs_key_q}{$ttitle_en_q}{$_conf['k_at_a']}";
echo toolbar_i_opentab_button('img/gp5-info.png', '情報', $escaped_url);
echo '</td>';

// トップに戻る
echo '<td>';
echo toolbar_i_standard_button('img/glyphish/icons2/53-house.png', 'TOP', "index.php{$_conf['k_at_q']}");
echo '</td>';

// アクション
echo '<td>';
echo toolbar_i_action_thread_button('img/glyphish/icons2/12-eye.png', 'アクション', $aThread);
echo '</td>';

// 書き込む
echo '<td>';
if (!$aThread->diedat) {
    if (empty($_conf['disable_res'])) {
        if ($_conf['bottom_res_form']) {
            echo toolbar_i_showhide_button('img/glyphish/icons2/08-chat.png', '書込', 'kakiko');
        } else {
            $escaped_url = "post_form.php?{$host_bbs_key_q}&amp;rescount={$aThread->rescount}{$ttitle_en_q}{$_conf['k_at_a']}";
            echo toolbar_i_standard_button('img/glyphish/icons2/08-chat.png', '書込', $escaped_url);
        }
    } else {
        echo toolbar_i_opentab_button('img/glyphish/icons2/08-chat.png', '元スレ', $motothre_url);
    }
} else {
    echo toolbar_i_disabled_button('img/glyphish/icons2/08-chat.png', '書込');
}
echo '</td>';

// }}}

echo '</tr></tbody></table>';
echo '</div>';

// }}}

// ImageCache2
if ($_conf['expack.ic2.enabled']) {
    if (!function_exists('ic2_loadconfig')) {
        include P2EX_LIB_DIR . '/ImageCache2/bootstrap.php';
    }
    $ic2conf = ic2_loadconfig();
    if ($ic2conf['Thumb1']['width'] > 80) {
        include P2EX_LIB_DIR . '/ImageCache2/templates/info-v.tpl.html';
    } else {
        include P2EX_LIB_DIR . '/ImageCache2/templates/info-h.tpl.html';
    }
}

// SPM
if ($_conf['expack.spm.enabled']) {
    echo ShowThreadI::getSpmElementHtml();
}

// 最終レス番号を更新
echo <<<EOS
<script type="text/javascript">
//<![CDATA[
(function(n){
    var ktool_value = document.getElementById('ktool_value');
    if (ktool_value) {
        ktool_value.value = n;
    }
})({$last_resnum});
//]]>
</script>
EOS;

// フィルタヒット数を更新
if ($do_filtering) {
    echo <<<EOS
<script type="text/javascript">
//<![CDATA[
(function(n){
    var searching = document.getElementById('searching');
    if (searching) {
        searching.innerHTML = n;
    }
})({$resFilter->hits});
//]]>
</script>
EOS;
}

// ip2host
if ($_conf['ip2host.enabled']) {
    include P2EX_LIB_DIR . '/ip2host.inc.php';
}

echo '</body></html>';

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
