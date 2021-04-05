<?php
require_once P2_LIB_DIR . '/sort_threadlist.inc.php';

// {{{ ThreadList

/**
 * rep2 - ThreadList クラス
 */
class ThreadList
{
    // {{{ properties

    public $threads;   // クラスThreadのオブジェクトを格納する配列
    public $num;       // 格納されたThreadオブジェクトの数
    public $host;      // ex)pc.2ch.net
    public $bbs;       // ex)mac
    public $itaj;      // 板名 ex)新・mac板
    public $itaj_hd;   // HTML表示用に、板名を htmlspecialchars() したもの
    public $spmode;    // 普通板以外のスペシャルモード
    public $ptitle;    // ページタイトル

    // }}}
    // {{{ constructor

    /**
     * コンストラクタ
     */
    public function __construct()
    {
        $this->threads = array();
        $this->num = 0;
    }

    // }}}
    // {{{ setSpMode()

    /**
     * spmodeを設定する
     */
    public function setSpMode($name)
    {
        global $_conf;

        $halfwidth = ($_conf['ktai'] && !$_conf['iphone']);

        switch ($name) {
        case 'recent':
            $this->spmode = $name;
            $this->ptitle = $halfwidth ? '最近読んだｽﾚ' : '最近読んだスレ';
            break;
        case 'res_hist':
            $this->spmode = $name;
            $this->ptitle = '書き込み履歴';
            break;
        case 'fav':
            $this->spmode = $name;
            $this->ptitle = $halfwidth ? 'お気にｽﾚ' : 'お気にスレ';
            break;
        case 'taborn':
            $this->spmode = $name;
            $this->ptitle = $this->itaj . ($halfwidth ? ' (ｱﾎﾞﾝ中)' : ' (あぼーん中)');
            break;
        case 'soko':
            $this->spmode = $name;
            $this->ptitle = "{$this->itaj} (dat倉庫)";
            break;
        case 'palace':
            $this->spmode = $name;
            $this->ptitle = $halfwidth ? 'ｽﾚの殿堂' : 'スレの殿堂';
            break;
        case 'merge_favita':
            $this->spmode = $name;
            if ($_conf['expack.misc.multi_favs']) {
                $this->ptitle = str_replace(array('&gt;', '&lt;', '&quot;', '&#039;'),
                                            array('>', '<', '"', "'"),
                                            FavSetManager::getFavSetPageTitleHt('m_favita_set', 'お気に板')
                                            ) . ' (まとめ)';
            } else {
                $this->ptitle = 'お気に板 (まとめ)';
            }
            break;
        }
    }

    // }}}
    // {{{ setIta()

    /**
     * ■ 総合的に板情報（host, bbs, 板名）をセットする
     */
    public function setIta($host, $bbs, $itaj = "")
    {
        $this->host = $host;
        $this->bbs = $bbs;
        $this->setItaj($itaj);

        return true;
    }

    // }}}
    // {{{ setItaj()

    /**
     * ■板名をセットする
     */
    public function setItaj($itaj)
    {
        if ($itaj) {
            $this->itaj = $itaj;
        } else {
            $this->itaj = $this->bbs;
        }
        $this->itaj_hd = p2h($this->itaj);
        $this->ptitle = $this->itaj;

        return true;
    }

    // }}}
    // {{{ readList()

    /**
     * ■ readList メソッド
     */
    public function readList()
    {
        global $_conf;

        //$GLOBALS['debug'] && $GLOBALS['profiler']->enterSection('readList()');

        $lines = array();

        switch ($this->spmode) {

        // ローカルの履歴ファイル 読み込み
        case 'recent':
            $lines = FileCtl::file_read_lines($_conf['recent_idx']);
            break;

        // ローカルの書き込み履歴ファイル 読み込み
        case 'res_hist':
            $lines = FileCtl::file_read_lines($_conf['res_hist_idx']);
            break;

        //ローカルのお気にファイル 読み込み
        case 'fav':
            $lines = FileCtl::file_read_lines($_conf['favlist_idx']);
            break;

        // お気に板をまとめて読み込み
        case 'merge_favita':
            $favitas = array();

            if (file_exists($_conf['favita_brd'])) {
                foreach (file($_conf['favita_brd']) as $l) {
                    if (preg_match("/^\t?(.+?)\t(.+?)\t.+?\$/", rtrim($l), $m)) {
                        $favitas[] = array('host' => $m[1], 'bbs' => $m[2]);
                    }
                }
            }

            if (empty($_REQUEST['norefresh']) && !(empty($_REQUEST['refresh']) && isset($_REQUEST['word']))) {
                if ($_conf['expack.use_curl_multi'] == 1) {
                    P2CurlMulti::fetchSubjectTxt($favitas);
                    $GLOBALS['expack.subject.multi-threaded-download.done'] = true;
                } elseif ($_conf['expack.use_pecl_http'] == 1) {
                    P2HttpExt::activate();
                    P2HttpRequestPool::fetchSubjectTxt($favitas);
                    $GLOBALS['expack.subject.multi-threaded-download.done'] = true;
                } elseif ($_conf['expack.use_pecl_http'] == 2) {
                    if (P2CommandRunner::fetchSubjectTxt('merge_favita', $_conf)) {
                        $GLOBALS['expack.subject.multi-threaded-download.done'] = true;
                    }
                }
            }

            $i = 0;

            foreach ($favitas as $ita) {
                $aSubjectTxt = new SubjectTxt($ita['host'], $ita['bbs']);
                $k = (float)sprintf('0.%d', ++$i);

                if (is_array($aSubjectTxt->subject_lines)) {
                    $j = 0;

                    foreach ($aSubjectTxt->subject_lines as $l) {
                        if (preg_match('/^([0-9]+)\\.(?:dat|cgi)(?:,|<>)(.+) ?(?:\\(|（)([0-9]+)(?:\\)|）)/', $l, $m)) {
                            $lines[] = array(
                                'key' => $m[1],
                                'ttitle' => rtrim($m[2]),
                                'rescount' => (int)$m[3],
                                'host' => $ita['host'],
                                'bbs' => $ita['bbs'],
                                'torder' => ++$j + $k,
                            );
                        }
                    }
                }
            }
            break;

        // p2_threads_aborn.idx 読み込み
        case 'taborn':
            $taborn_file = $this->getIdxDir() . 'p2_threads_aborn.idx';
            $lines = FileCtl::file_read_lines($taborn_file);
            break;

        // spmodeがdat倉庫の場合
        case 'soko':
            $dat_host_bbs_dir = $this->getDatDir(false);
            $idx_host_bbs_dir = $this->getIdxDir(false);

            $lines = array();

            //$GLOBALS['debug'] && $GLOBALS['profiler']->enterSection('dat');
            // datログディレクトリを走査して孤立datにidx付加
            if ($cdir = dir($dat_host_bbs_dir)) { // or die ("ログディレクトリがないよ！");
                // ディレクトリ走査
                while ($entry = $cdir->read()) {
                    if (preg_match('/([0-9]+)\\.dat$/', $entry, $matches)) {
                        $theidx = $idx_host_bbs_dir . DIRECTORY_SEPARATOR . $matches[1] . '.idx';
                        if (!file_exists($theidx)) {
                            $thedat = $dat_host_bbs_dir . DIRECTORY_SEPARATOR . $entry;
                            if ($datlines = FileCtl::file_read_lines($thedat, FILE_IGNORE_NEW_LINES)) {
                                $firstdatline = $datlines[0];
                                if (strpos($firstdatline, '<>') !== false) {
                                    $datline_sepa = '<>';
                                } else {
                                    $datline_sepa = ',';
                                }
                                $d = explode($datline_sepa, $firstdatline);
                                $atitle = $d[4];
                                $gotnum = sizeof($datlines);
                                $readnum = $gotnum;
                                $anewline = $readnum + 1;
                                $data = array($atitle, $matches[1], '', $gotnum, '',
                                            $readnum, '', '', '', $anewline,
                                            '', '', '');
                                P2Util::recKeyIdx($theidx, $data);
                            }
                        }
                        // array_push($lines, $idl[0]);
                    }
                }
                $cdir->close();
            }
            //$GLOBALS['debug'] && $GLOBALS['profiler']->leaveSection('dat');

            //$GLOBALS['debug'] && $GLOBALS['profiler']->enterSection('idx');
            // {{{ idxログディレクトリを走査してidx情報を抽出してリスト化
            if ($cdir = dir($idx_host_bbs_dir)) { // or die ("ログディレクトリがないよ！");
                // ディレクトリ走査
                while ($entry = $cdir->read()) {
                    if (preg_match('/([0-9]+)\\.idx$/', $entry)) {
                        $thedix = $idx_host_bbs_dir . DIRECTORY_SEPARATOR . $entry;
                        $idl = FileCtl::file_read_lines($thedix);
                        if (is_array($idl)) {
                            array_push($lines, $idl[0]);
                        }
                    }
                }
                $cdir->close();
            }
            // }}}
            //$GLOBALS['debug'] && $GLOBALS['profiler']->leaveSection('idx');
            break;

        // スレの殿堂の場合  // p2_palace.idx 読み込み
        case 'palace':
            if ($lines = FileCtl::file_read_lines($_conf['palace_idx'])) {
                //P2Util::pushInfoHtml('<p>殿堂はがらんどうです</p>');
                //return false;
            }
            break;

        // オンライン上の subject.txt を読み込む（spmodeでない場合）
        default:
            if (!$this->spmode) {
                $aSubjectTxt = new SubjectTxt($this->host, $this->bbs);
                if (P2HostMgr::isHost2chs($this->host)) {
                    // subject.txt を lastmodify.txt 形式に変換つつマージ
                    //   本来｢subject.txt を lastmodify.txt 形式に変換｣するのは SubjectTxt クラスの中で行ったほうが
                    //   将来的にいいかもしれないがとりあえずここでやる
                    foreach($aSubjectTxt->subject_lines as $l){
                        if (!preg_match('/^(([0-9]+)\\.(?:dat|cgi))(?:,|<>)(.+) ?(?:\\(|（)([0-9]+)(?:\\)|）)/', $l, $matches)) {
                            continue;
                        }

                        $threads[$matches[2]]['key']    = $matches[1];      // スレッドID
                        $threads[$matches[2]]['title']  = $matches[3];      // title
                        // サロゲートペアの数値文字参照を変換
                        $threads[$matches[2]]['title']  = P2Util::replaceNumericalSurrogatePair($threads[$matches[2]]['title']);
                        $threads[$matches[2]]['rc']     = $matches[4];      // rescount

                        $threads[$matches[2]]['param']  = implode('<>',array_fill(0, 5, '')); // lastmodify.txt に含まれないスレを表示できるように
                    }

                    $aLastmodify = new LastmodifyTxt($this->host, $this->bbs);
                    foreach($aLastmodify->lastmodify_lines as $l){
                        if (!preg_match('/^(([0-9]+)\.(?:dat|cgi))<>(.+?)<>(\d+)<>(\d+)<>(\d+)<>(\d+)<>(.+?)<>(.+?)<>/', $l, $matches)) {
                            continue;
                        }

                        $key = $matches[2];
                        array_splice($matches, 0, 5); // subject.txt に含まれているデータは捨てる
                        $threads[$key]['param'] = implode('<>', $matches);  // extend
                    }

                    unset($aSubjectTxt->subject_lines);
                    foreach($threads as $l){
                        $aSubjectTxt->subject_lines[] = implode('<>', $l) . "<>\n";
                    }
                }
                $lines = $aSubjectTxt->subject_lines;
            }
        }

        //$GLOBALS['debug'] && $GLOBALS['profiler']->leaveSection('readList()');

        return is_array($lines) ? $lines : array();
    }

    // }}}
    // {{{ addThread()

    /**
     * ■ addThread メソッド
     */
    public function addThread(Thread $aThread)
    {
        //$GLOBALS['debug'] && $GLOBALS['profiler']->enterSection('addThread()');

        $this->threads[] = $aThread;
        $this->num++;

        //$GLOBALS['debug'] && $GLOBALS['profiler']->leaveSection('addThread()');

        return $this->num;
    }

    // }}}
    // {{{ sort()

    /**
     * スレッドを並び替える
     *
     * @param string $mode
     * @param bool $reverse
     * @return void
     */
    public function sort($mode, $reverse = false)
    {
        global $_conf;

        if (!$this->threads) {
            return;
        }

        //$GLOBALS['debug'] && $GLOBALS['profiler']->enterSection('sort');

        $do_benchmark = false;
        $use_multisort = true;
        $cmp = null;

        switch ($mode) {
        case 'midoku':
            if ($this->spmode == 'soko') {
                $cmp = 'cmp_key';
            } else {
                $cmp = 'cmp_midoku';
            }
            break;
        case 'ikioi':
        case 'spd':
            if ($_conf['cmp_dayres_midoku']) {
                $cmp = 'cmp_dayres_midoku';
            } else {
                $cmp = 'cmp_dayres';
            }
            break;
        case 'no':
            if ($this->spmode == 'soko') {
                $cmp = 'cmp_key';
            } else {
                $cmp = 'cmp_no';
            }
            break;
        case 'bd':
            $cmp = 'cmp_key';
            break;
        case 'fav':
        case 'ita':
        case 'res':
        case 'title':
            $cmp = 'cmp_' . $mode;
            break;
        case 'similarity':
            if (!empty($GLOBALS['wakati_words'])) {
                $cmp = 'cmp_similarity';
            } else {
                $cmp = 'cmp_title';
            }
            break;
        default:
            $info_msg_ht = sprintf('<p class="info-msg">ソート指定が変です。(%s)</p>', p2h($mode));
            P2Util::pushInfoHtml($info_msg_ht);
        }

        if ($cmp) {
            if ($do_benchmark) {
                $before = microtime(true);
            }
            if ($use_multisort) {
                $cmp = 'p2_multi_' . $cmp;
                $cmp($this, $reverse);
            } else {
                $cmp = 'p2_' . $cmp;
                usort($this->threads, $cmp);
            }
        }

        if (!($cmp && $use_multisort) && $reverse) {
            $this->threads = array_reverse($this->threads);
        }

        if ($cmp && $do_benchmark) {
            $after = microtime(true);
            $count = count($this->threads);
            P2Util::pushInfoHtml(sprintf(
                '<p class="info-msg" style="font-family:monospace">%s(%d thread%s)%s = %0.6f sec.</p>',
                $cmp,
                number_format($count),
                ($count > 1) ? 's' : '',
                $reverse ? '+reverse' : '',
                $after - $before));
        }

        //$GLOBALS['debug'] && $GLOBALS['profiler']->leaveSection('sort');
    }

    // }}}
    // {{{ getDatDir()

    /**
     * datの保存ディレクトリを返す
     *
     * @param bool $dir_sep
     * @return string
     * @see P2Util::datDirOfHost(), Thread::getDatDir()
     */
    public function getDatDir($dir_sep = true)
    {
        return P2Util::datDirOfHostBbs($this->host, $this->bbs, $dir_sep);
    }

    // }}}
    // {{{ getIdxDir()

    /**
     * idxの保存ディレクトリを返す
     *
     * @param bool $dir_sep
     * @return string
     * @see P2Util::idxDirOfHost(), Thread::getIdxDir()
     */
    public function getIdxDir($dir_sep = true)
    {
        return P2Util::idxDirOfHostBbs($this->host, $this->bbs, $dir_sep);
    }

    // }}}
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
