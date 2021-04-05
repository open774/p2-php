<?php
/**
 * rep2 スレッドサブジェクト表示関数
 * for subject.php
 */

// {{{ sb_print()

/**
 * sb_print - スレッド一覧を表示する (<tr>～</tr>)
 */
function sb_print($aThreadList)
{
    global $_conf, $sb_view, $p2_setting, $STYLE;

    //$GLOBALS['debug'] && $GLOBALS['profiler']->enterSection('sb_print()');

    if (!$aThreadList->threads) {
        echo '<tbody><tr><td>　該当サブジェクトはなかったぽ</td></tr></tbody>';
        //$GLOBALS['debug'] && $GLOBALS['profiler']->leaveSection('sb_print()');
        return;
    }

    // 変数 ================================================

    // >>1 表示 (spmodeは除く)
    $only_one_bool = false;
    if (!$aThreadList->spmode && ($_conf['sb_show_one'] == 1 || ($_conf['sb_show_one'] == 2 &&
        (strpos($aThreadList->bbs, 'news') !== false || $aThreadList->bbs == 'bizplus')
    ))) {
        $only_one_bool = true;
    }

    // チェックボックス
    if ($aThreadList->spmode == 'taborn' || $aThreadList->spmode == 'soko') {
        $checkbox_bool = true;
    } else {
        $checkbox_bool = false;
    }

    // 板名
    if ($aThreadList->spmode && $aThreadList->spmode != 'taborn' && $aThreadList->spmode != 'soko') {
        $ita_name_bool = true;
    } else {
        $ita_name_bool = false;
    }

    // 実況版のみ発動する機能がONの時は板が実況板かどうか判断
    $livebbs_bool = false;
    if($_conf['live.livelink_subject']==1||$_conf['live.livebbs_forcelive']==1) {
        foreach (explode (',',$_conf['live.livebbs_list']) as $value) {
             if(strpos($aThreadList->bbs, $value) !== false) {
                 $livebbs_bool = true;
                 break;
             }
        }
    }

    $norefresh_q = '&amp;norefresh=true';

    $td = array('edit' => '', 'offrec' => '', 'unum' => '', 'rescount' => '',
                'one' => '', 'checkbox' => '', 'ita' => '', 'spd' => '',
                'ikioi' => '', 'birth' => '', 'fav' => '');

    // td欄 cssクラス
    $class_t  = ' class="t"';   // 基本
    $class_te = ' class="te"';  // 並び替え
    $class_tu = ' class="tu"';  // 新着レス数
    $class_tn = ' class="tn"';  // レス数
    $class_tc = ' class="tc"';  // チェックボックス
    $class_to = ' class="to"';  // オーダー番号
    $class_tl = ' class="tl"';  // タイトル
    $class_ts = ' class="ts"';  // すばやさ
    $class_ti = ' class="ti"';  // 勢い

    // ソート ==================================================

    // 現在のソート形式をclass指定でCSSカラーリング
    $class_sort_midoku  = '';   // 新着
    $class_sort_res     = '';   // レス
    $class_sort_no      = '';   // No.
    $class_sort_title   = '';   // タイトル
    $class_sort_ita     = '';   // 板
    $class_sort_spd     = '';   // すばやさ
    $class_sort_ikioi   = '';   // 勢い
    $class_sort_bd      = '';   // Birthday
    $class_sort_fav     = '';   // お気に入り
    if (empty($_REQUEST['rsort'])) {
        ${'class_sort_' . $GLOBALS['now_sort']} = ' class="now_sort"';
    } else {
        ${'class_sort_' . $GLOBALS['now_sort']} = ' class="now_sort rsort"';
    }

    // 逆順ソート用クエリ
    $rsortq_midoku  = '';   // 新着
    $rsortq_res     = '';   // レス
    $rsortq_no      = '';   // No.
    $rsortq_title   = '';   // タイトル
    $rsortq_ita     = '';   // 板
    $rsortq_spd     = '';   // すばやさ
    $rsortq_ikioi   = '';   // 勢い
    $rsortq_bd      = '';   // Birthday
    $rsortq_fav     = '';   // お気に入り
    if (empty($_REQUEST['rsort'])) {
        ${'rsortq_' . $GLOBALS['now_sort']} = '&amp;rsort=1';
    }

    $sortq_spmode = '';
    $sortq_host = '';
    $sortq_ita = '';
    // spmode時
    if ($aThreadList->spmode) {
        $sortq_spmode = "&amp;spmode={$aThreadList->spmode}";
    }
    // spmodeでない、または、spmodeがあぼーん or dat倉庫なら
    if (!$aThreadList->spmode || $aThreadList->spmode == 'taborn' || $aThreadList->spmode == 'soko') {
        $sortq_host = "&amp;host={$aThreadList->host}";
        $sortq_ita = "&amp;bbs={$aThreadList->bbs}";
    }

    $sortq_common = $sortq_spmode . $sortq_host . $sortq_ita . $_conf['k_at_a'];

    if (!empty($_REQUEST['find_cont']) && strlen($GLOBALS['word_fm']) > 0) {
        $word_q = '&amp;word=' . rawurlencode($GLOBALS['word']) . '&amp;method=' . rawurlencode($GLOBALS['sb_filter']['method']);
    } else {
        $word_q = '';
    }

    $fav_index = $_conf['expack.misc.multi_favs'] ? intval($_SESSION['m_favlist_set']) : 0;

    //=====================================================
    // テーブルヘッダ
    //=====================================================
    echo "<thead>\n<tr class=\"tableheader\">\n";

    // 並替
    if ($sb_view == 'edit') {
        echo "<th{$class_te}>&nbsp;</th>\n";
    }
    // 履歴の解除
    if ($aThreadList->spmode == 'recent') {
        echo "<th{$class_t}>&nbsp;</th>\n";
    }
    // 新着
    if ($sb_view != 'edit') {
        echo <<<EOP
<th{$class_tu} id="sb_th_midoku"><a{$class_sort_midoku} href="{$_conf['subject_php']}?sort=midoku{$sortq_common}{$rsortq_midoku}{$norefresh_q}" target="_self">新着</a></th>\n
EOP;
    }
    // レス数
    if ($sb_view != 'edit') {
        echo <<<EOP
<th{$class_tn} id="sb_th_res"><a{$class_sort_res} href="{$_conf['subject_php']}?sort=res{$sortq_common}{$rsortq_res}{$norefresh_q}" target="_self">レス</a></th>\n
EOP;
    }
    // >>1
    if ($only_one_bool) {
        echo "<th{$class_t}>&nbsp;</th>\n";
    }
    // チェックボックス
    if ($checkbox_bool) {
        echo <<<EOP
<th{$class_tc}><input id="allbox" name="allbox" type="checkbox" title="すべての項目を選択、または選択解除"></th>\n
EOP;
    }
    // No.
    $title = empty($aThreadList->spmode) ? ' title="2ch標準の並び順番号"' : '';
    echo <<<EOP
<th{$class_to} id="sb_th_no"><a{$class_sort_no} href="{$_conf['subject_php']}?sort=no{$sortq_common}{$rsortq_no}{$norefresh_q}" target="_self"{$title}>No.</a></th>\n
EOP;
    // タイトル
    echo <<<EOP
<th{$class_tl} id="sb_th_title"><a{$class_sort_title} href="{$_conf['subject_php']}?sort=title{$sortq_common}{$rsortq_title}{$norefresh_q}" target="_self">タイトル</a></th>\n
EOP;
    // 板
    if ($ita_name_bool) {
        echo <<<EOP
<th{$class_t} id="sb_th_ita"><a{$class_sort_ita} href="{$_conf['subject_php']}?sort=ita{$sortq_common}{$rsortq_ita}{$norefresh_q}" target="_self">板</a></th>\n
EOP;
    }
    // すばやさ
    if ($_conf['sb_show_spd']) {
        echo <<<EOP
<th{$class_ts} id="sb_th_spd"><a{$class_sort_spd} href="{$_conf['subject_php']}?sort=spd{$sortq_common}{$rsortq_spd}{$norefresh_q}" target="_self">すばやさ</a></th>\n
EOP;
    }
    // 勢い
    if ($_conf['sb_show_ikioi']) {
        echo <<<EOP
<th{$class_ti} id="sb_th_ikioi"><a{$class_sort_ikioi} href="{$_conf['subject_php']}?sort=ikioi{$sortq_common}{$rsortq_ikioi}{$norefresh_q}" target="_self">勢い</a></th>\n
EOP;
    }
    // Birth
    echo <<<EOP
<th{$class_t} id="sb_th_bd"><a{$class_sort_bd} href="{$_conf['subject_php']}?sort=bd{$sortq_common}{$rsortq_bd}{$norefresh_q}" target="_self">since</a></th>\n
EOP;
    // お気に入り
    if ($_conf['sb_show_fav'] && $aThreadList->spmode != 'taborn') {
        echo <<<EOP
<th{$class_t} id="sb_th_fav"><a{$class_sort_fav} href="{$_conf['subject_php']}?sort=fav{$sortq_common}{$rsortq_fav}{$norefresh_q}" target="_self" title="お気にスレ">☆</a></th>\n
EOP;
    }

    echo "</tr>\n</thead>\n";

    //=====================================================
    //テーブルボディ
    //=====================================================

    //spmodeがあればクエリー追加
    if ($aThreadList->spmode) {
        $spmode_q = "&amp;spmode={$aThreadList->spmode}";
    } else {
        $spmode_q = '';
    }

    $i = 0;
    foreach ($aThreadList->threads as $aThread) {
        if ($i % 100 == 0) {
            if ($i > 0) {
                echo '</tbody>';
            }
            printf('<tbody class="tgroup%d">', $i / 100 + 1);
        }
        $i++;
        $midoku_ari = false;
        $anum_ht = ''; // #r1

        $host_bbs_key_q = "host={$aThread->host}&amp;bbs={$aThread->bbs}&amp;key={$aThread->key}{$_conf['k_at_a']}";

        if ($aThreadList->spmode != 'taborn') {
            if (!$aThread->torder) { $aThread->torder = $i; }
        }

        // tr欄 cssクラス
        if ($i % 2) {
            $row_class = 'r1 r_odd';
        } else {
            $row_class = 'r2 r_even';
        }

        //新着レス数 =============================================
        $unum_ht_c = '&nbsp;';
        // 既得済み
        if ($aThread->isKitoku()) {
            $row_class .= ' r_read'; // readは過去分詞

            // $ttitle_en_q は節減省略
            $delelog_js = "return rep2.subject.deleLog('{$host_bbs_key_q}',this);";
            $title_at = ' title="クリックするとログ削除"';

            $anum_ht = sprintf('#r%d', min($aThread->rescount, $aThread->rescount - $aThread->nunum + 1 - $_conf['respointer']));

            // subject.txtにない時
            if (!$aThread->isonline) {
                $row_class .= ' r_offline';
                // JavaScriptでの確認ダイアログあり
                $unum_ht_c = <<<EOP
<a class="un_n" href="{$_conf['subject_php']}?{$host_bbs_key_q}{$spmode_q}&amp;dele=true" target="_self">-</a>
EOP;
                $row_class = ' nosubject';

            // 新着あり
            } elseif ($aThread->unum > 0) {
                $row_class .= ' r_new';
                $midoku_ari = true;
                $unum_ht_c = <<<EOP
<a id="un{$i}" class="un_a" href="{$_conf['subject_php']}?{$host_bbs_key_q}{$spmode_q}&amp;dele=true" target="_self">{$aThread->unum}</a>
EOP;

            // subject.txtにはあるが、新着なし
            } else {
                $unum_ht_c = <<<EOP
<a class="un" href="{$_conf['subject_php']}?{$host_bbs_key_q}{$spmode_q}&amp;dele=true" target="_self">{$aThread->unum}</a>
EOP;
            }
        }

        $td['unum'] = "<td{$class_tu}>{$unum_ht_c}</td>\n";

        // 総レス数 =============================================
        $td['rescount'] = "<td{$class_tn}>{$aThread->rescount}</td>\n";

        // 板名 ============================================
        if ($ita_name_bool) {
            $ita_name_ht = p2h($aThread->itaj ? $aThread->itaj : $aThread->bbs);
            $td['ita'] = <<<EOP
<td{$class_t}><a href="{$_conf['subject_php']}?host={$aThread->host}&amp;bbs={$aThread->bbs}{$_conf['k_at_a']}" target="_self">{$ita_name_ht}</a></td>\n
EOP;
        }


        // お気に入り ========================================
        if ($_conf['sb_show_fav']) {
            if ($aThreadList->spmode != 'taborn') {
                if (empty($aThread->favs[$fav_index])) {
                    $favmark = '+';
                    $favdo = '1';
                } else {
                    $favmark = '★';
                    $favdo = '0';
                }

                // $ttitle_en_q も付けた方がいいが、節約のため省略する
                $td['fav'] = <<<EOP
<td{$class_t}><a class="fav" href="info.php?{$host_bbs_key_q}&amp;setfav={$favdo}" target="info">{$favmark}</a></td>\n
EOP;
            }
        }

        // torder(info) =================================================
        // お気にスレ
        if ($aThread->fav) {
            $torder_st = "<b>{$aThread->torder}</b>";
        } else {
            $torder_st = $aThread->torder;
        }
        $torder_ht = <<<EOP
<a id="to{$i}" class="info" href="info.php?{$host_bbs_key_q}">{$torder_st}</a>
EOP;

        // title =================================================
        $rescount_q = '&amp;rescount=' . $aThread->rescount;

        // dat倉庫 or 殿堂なら
        if ($aThreadList->spmode == 'soko' || $aThreadList->spmode == 'palace') {
            $rescount_q = '';
            $offline_q = '&amp;offline=true';
            $anum_ht = '';
        // subject.txt にない場合
        } elseif (!$aThread->isonline) {
            $offline_q = '&amp;offline=true';
        } else {
            $offline_q = '';
        }

        // タイトル未取得なら
        $ttitle_ht = $aThread->ttitle_ht;
        if (strlen($ttitle_ht) == 0) {
            $ttitle_ht = "http://{$aThread->host}/test/read.cgi/{$aThread->bbs}/{$aThread->key}/";
        }

        if ($aThread->similarity) {
            $ttitle_ht .= sprintf(' <var>(%0.1f)</var>', $aThread->similarity * 100);
        }

        // 元スレ
        $moto_thre_ht = '';
        if ($_conf['sb_show_motothre']) {
            if ($_conf['sb_show_motothre'] == 2 || !$aThread->isKitoku()) {
                $moto_thre_ht = '<a class="thre_title moto_thre" href="'
                              . p2h($aThread->getMotoThread(false, ''))
                              . '">・</a>';
            }
        }

        // 新規スレ
        if ($aThread->new) {
            $row_class .= ' r_brand_new';
            $title_class = 'thre_title_new';
        } else {
            $title_class = 'thre_title';
        }
        if ($midoku_ari) {
            $title_class .= ' midoku_ari';
        }

        // スレリンク
        if ($word_q) {
            $rescount_q = '';
            $offline_q = '&amp;offline=true';
            $anum_ht = '';
        }
        $thre_url = "{$_conf['read_php']}?{$host_bbs_key_q}{$rescount_q}{$offline_q}{$word_q}{$anum_ht}{$_conf['k_at_a']}";

        // +live リンク表示切替
        if($_conf['live.livelink_subject']==2||$livebbs_bool)
        {
            // $ttitle_en_q は節減省略
            $live_url = "live_frame.php?{$host_bbs_key_q}&amp;live=1{$rescount_q}";
        }

        // オンリー>>1
        if ($only_one_bool) {
            $td['one'] = <<<EOP
<td{$class_t}><a href="{$_conf['read_php']}?{$host_bbs_key_q}&amp;one=true">&gt;&gt;1</a></td>\n
EOP;
        }

        // チェックボックス
        if ($checkbox_bool) {
            $checked_ht = '';
            if ($aThreadList->spmode == 'taborn') {
                if (!$aThread->isonline) { // or ($aThread->rescount >= 1000)
                    $checked_ht = ' checked';
                }
            }
            $td['checkbox'] = <<<EOP
<td{$class_tc}><input name="checkedkeys[]" type="checkbox" value="{$aThread->key}"{$checked_ht}></td>\n
EOP;
        }

        // 並替
        if ($sb_view == 'edit') {
            $td['unum'] = '';
            $td['rescount'] = '';
            $sb_view_q = '&amp;sb_view=edit';
            if ($aThreadList->spmode == 'fav') {
                $setkey = 'setfav';
            } elseif ($aThreadList->spmode == 'palace') {
                $setkey = 'setpal';
            }
            $narabikae_a = "{$_conf['subject_php']}?{$host_bbs_key_q}{$spmode_q}{$sb_view_q}";

            $td['edit'] = <<<EOP
<td{$class_te}>
    <a class="te" href="{$narabikae_a}&amp;{$setkey}=top" target="_self">▲</a>
    <a class="te" href="{$narabikae_a}&amp;{$setkey}=up" target="_self">↑</a>
    <a class="te" href="{$narabikae_a}&amp;{$setkey}=down" target="_self">↓</a>
    <a class="te" href="{$narabikae_a}&amp;{$setkey}=bottom" target="_self">▼</a>
</td>\n
EOP;
        }

        // 最近読んだスレの解除
        if ($aThreadList->spmode == 'recent') {
            $td['offrec'] = <<<EOP
<td{$class_tc}><a href="info.php?{$host_bbs_key_q}&amp;offrec=true">×</a></td>\n
EOP;
        }

        // すばやさ（＝ 時間/レス ＝ レス間隔）
        if ($_conf['sb_show_spd']) {
            if ($spd_st = $aThread->getTimePerRes()) {
                $td['spd'] = "<td{$class_ts}>{$spd_st}</td>\n";
            }
        }

        // 勢い
        if ($_conf['sb_show_ikioi']) {
            if ($aThread->dayres > 0) {
                // 0.0 とならないように小数点第2位で切り上げ
                $dayres = ceil($aThread->dayres * 10) / 10;
                $dayres_st = sprintf("%01.1f", $dayres);
            } else {
                $dayres_st = '-';
            }
            $td['ikioi'] = "<td{$class_ti}>{$dayres_st}</td>\n";
        }

        // Birth
        $birth_format = isset($_conf['birth_format']) ? $_conf['birth_format'] : 'y/m/d';
        $birth = date($birth_format, intval($aThread->key)); // (y/m/d H:i)
        $td['birth'] = "<td{$class_t}>{$birth}</td>\n";
        unset($birth_format);

        // +live 実況ボタンの処理
        // +live 実況中ic2のサムネイル作成をonoff
        $live_ic2_off = '';
        if ($_conf['expack.ic2.enabled']
        && (!$_conf['live.ic2_onoff'])) {
            $live_ic2_off = "onclick=\"javascript:parent.menu.ic2_menu_switch(0);\"";
        }
        // +live スレのリンク先を実況に書き換える
        $thre_addtag = '';
        if($_conf['live.livebbs_forcelive']==1&&$livebbs_bool) {
            $thre_addtag = $live_ic2_off."target=\"_blank\"";
            $thre_url = $live_url;
        } elseif (isset($live_url)) {
            $livelink_body = <<<EOP
<a href="{$live_url}" title="別窓で実況" target="_blank" {$live_ic2_off}><img src ="./img/live.png" alt="+live"></a>&nbsp;
EOP;
        }

        //====================================================================================
        // スレッド一覧 table ボディ HTMLプリント <tr></tr>
        //====================================================================================

        // ボディ
        echo <<<EOR
<tr class="{$row_class}">
{$td['edit']}{$td['offrec']}{$td['unum']}{$td['rescount']}{$td['one']}{$td['checkbox']}<td{$class_to}>{$torder_ht}</td>
<td{$class_tl}><div class="el">{$moto_thre_ht}
{$livelink_body}
<a id="tt{$i}" href="{$thre_url}" {$thre_addtag} class="{$title_class}">{$ttitle_ht}</a></div></td>
{$td['ita']}{$td['spd']}{$td['ikioi']}{$td['birth']}{$td['fav']}</tr>\n
EOR;

    }

    echo "</tbody>\n";

    //$GLOBALS['debug'] && $GLOBALS['profiler']->leaveSection('sb_print()');
    return true;
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
