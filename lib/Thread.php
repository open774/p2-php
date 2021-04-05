<?php

// {{{ Thread

/**
 * rep2 - スレッドクラス
 */
class Thread
{
    // {{{ properties

    public $ttitle;     // スレタイトル // idxline[0] // < は &lt; だったりする
    public $key;        // スレッドID // idxline[1]
    public $length;     // local Dat Bytes(int) // idxline[2]
    public $gotnum;     //（個人にとっての）既得レス数 // idxline[3]
    public $rescount;   // スレッドの総レス数（未取得分も含む）
    public $modified;   // datのLast-Modified // idxline[4]
    public $readnum;    // 既読レス数 // idxline[5] // MacMoeではレス表示位置だったと思う（last res）
    public $fav;        //お気に入り(bool的に) // idxline[6] favlist.idxも参照
    /*
    public $favs;       //お気に入りセット登録状態(boolの配列)
    */
    protected $_favs;   //お気に入りセット登録状態(boolの配列)
    /*
    public $name;       // ここでは利用せず idxline[7]（他所で利用）
    public $mail;       // ここでは利用せず idxline[8]（他所で利用）
    */
    public $newline;    // 次の新規取得レス番号 // idxline[9] 廃止予定。旧互換のため残してはいる。

    public $lastmodify; // lastmodify(http://info.5ch.net/index.php/Monazilla/develop/lastmodify.txt)
    public $extdat;     // VIPQ2 !extend(https://info.5ch.net/index.php/%E6%96%B0%E7%94%9FVIPQ2#.21extend:)

    // ※hostとはいうものの、2ch外の場合は、host以下のディレクトリまで含まれていたりする。
    public $host;       // ex)pc.2ch.net // idxline[10]
    public $bbs;        // ex)mac // idxline[11]
    public $itaj;       // 板名 ex)新・mac

    public $datochiok;  // 過去ログ扱いならtrue(1) // idxline[12]　// 使っている形跡が無いので再利用

    public $torder;     // スレッド新しい順番号
    public $unum;       // 未読（新着レス）数
    public $nunum;      // ソートのための調節なしの未読数

    public $keyidx;     // idxファイルパス
    public $keydat;     // ローカルdatファイルパス

    public $isonline;   // 板サーバにあればtrue。subject.txtやdat取得時に確認してセットされる。
    public $new;        // 新規スレならtrue

    /*
    public $ttitle_hc;  // < が &lt; であったりするので、デコードしたスレタイトル
    public $ttitle_hd;  // HTML表示用に、エンコードされたスレタイトル
    public $ttitle_ht;  // スレタイトル表示用HTMLコード。フィルタリング強調されていたりも。
    */
    protected $_ttitle_hc;  // < が &lt; であったりするので、デコードしたスレタイトル
    protected $_ttitle_hd;  // HTML表示用に、エンコードされたスレタイトル
    protected $_ttitle_ht;  // スレタイトル表示用HTMLコード。フィルタリング強調されていたりも。

    public $dayres;     // 一日当たりのレス数。勢い。

    public $dat_type;   // datの形式（2chの旧形式dat（,区切り）なら"2ch_old"）

    public $ls = '';    // 表示レス番号の指定

    public $similarity; // タイトルの類似性

    protected $_unknown_props;

    // }}}
    // {{{ constructor

    /**
     * コンストラクタ
     */
    public function __construct()
    {
        $this->_ttitle_hc = null;
        $this->_ttitle_hd = null;
        $this->_ttitle_ht = null;
        $this->nunum = 0;
    }

    // }}}
    // {{{ __get()

    /**
     * ゲッター
     *
     * 毎回必要でなく、生成コストのかかるプロパティ
     * (ttitle_hc, ttitle_hd, ttitle_ht, favs)
     * を必要になったときに設定・取得する
     *
     * _unknown_props は予備
     *
     * @param   string  $name
     * @return  mixed
     */
    public function __get($name)
    {
        switch ($name) {
        case 'ttitle_hc':
            return $this->getTtitleHc();
        case 'ttitle_hd':
            return $this->getTtitleHd();
        case 'ttitle_ht':
            return $this->getTtitleHt();
        case 'favs':
            return $this->getFavStatus();
        default:
            if (!is_array($this->_unknown_props)) {
                $this->_unknown_props = array();
            }
            if (array_key_exists($name, $this->_unknown_props)) {
                return $this->_unknown_props[$name];
            }
            return null;
        }
    }

    // }}}
    // {{{ __set()

    /**
     * セッター
     *
     * ttitle_hc, ttitle_hd, ttitle_ht を任意の値に設定する
     *
     * _unknown_props は予備
     *
     * @param   string  $name
     * @param   mixed   $value
     * @return  void
     */
    public function __set($name, $value)
    {
        switch ($name) {
        case 'ttitle_hc':
            $this->_ttitle_hc = $value;
            break;
        case 'ttitle_hd':
            $this->_ttitle_hd = $value;
            break;
        case 'ttitle_ht':
            $this->_ttitle_ht = $value;
            break;
        default:
            if (!is_array($this->_unknown_props)) {
                $this->_unknown_props = array();
            }
            $this->_unknown_props[$name] = $value;
        }
    }

    // }}}
    // {{{ setTtitle()

    /**
     * ttitleをセットする
     */
    public function setTtitle($ttitle)
    {
        global $_conf;

        //著作権表記を除去
        if ($_conf['delete_copyright']==1) {
            foreach (explode (',',$_conf['delete_copyright.list']) as $value) {
                 $ttitle = str_replace($value,'',$ttitle);
            }
        }
        $this->ttitle = $ttitle;
    }

    // }}}
    // {{{ getTtitleHc()

    /**
     * HTMLの特殊文字をデコードしたスレタイトルを取得する
     */
    public function getTtitleHc()
    {
        if ($this->_ttitle_hc === null) {
            // < が &lt; であったりするので、デコードする
            //$this->_ttitle_hc = html_entity_decode($this->ttitle, ENT_COMPAT, 'Shift_JIS');

            // html_entity_decode() は結構重いので代替、、こっちだと半分くらいの処理時間
            $this->_ttitle_hc = str_replace(array('&lt;', '&gt;', '&amp;', '&quot;'),
                                            array('<'   , '>'   , '&'    , '"'     ), $this->ttitle);
        }
        return $this->_ttitle_hc;
    }

    // }}}
    // {{{ getTtitleHd()

    /**
     * HTML表示用に特殊文字をエンコードしたスレタイトルを取得する
     */
    public function getTtitleHd()
    {
        if ($this->_ttitle_hd === null) {
            // HTML表示用に htmlspecialchars() したもの
            $this->_ttitle_hd = p2h($this->ttitle, false);
            // <mark>[隔離病棟]</mark>などをデコード
            $this->_ttitle_hd = str_replace('&lt;/mark&gt;', '</mark>', str_replace('&lt;mark&gt;', '<mark>', $this->_ttitle_hd));
            // サロゲートペアの数値文字参照を変換
            $this->_ttitle_hd = P2Util::replaceNumericalSurrogatePair($this->_ttitle_hd);
        }
        return $this->_ttitle_hd;
    }

    // }}}
    // {{{ getTtitleHt()

    /**
     * HTML表示用に調整されたスレタイトルを取得する
     */
    public function getTtitleHt()
    {
        global $_conf;

        if ($this->_ttitle_ht === null) {
            // 一覧表示用に長さを切り詰めてから htmlspecialchars() したもの
            if ($_conf['ktai']) {
                $tt_max_len = $_conf['mobile.sb_ttitle_max_len'];
                $tt_trim_len = $_conf['mobile.sb_ttitle_trim_len'];
                $tt_trim_pos = $_conf['mobile.sb_ttitle_trim_pos'];

                $ttitle_hc = $this->getTtitleHc();
                $ttitle_len = strlen($ttitle_hc);

                if ($tt_max_len > 0 && $ttitle_len > $tt_max_len && $ttitle_len > $tt_trim_len) {
                    switch ($tt_trim_pos) {
                    case -1:
                        $a_ttitle = '... ';
                        $a_ttitle .= mb_strcut($ttitle_hc, $ttitle_len - $tt_trim_len);
                        break;
                    case 0:
                        $trim_len = floor($tt_trim_len / 2);
                        $a_ttitle = mb_strcut($ttitle_hc, 0, $trim_len);
                        $a_ttitle .= ' ... ';
                        $a_ttitle .= mb_strcut($ttitle_hc, $ttitle_len - $trim_len);
                        break;
                    case 1:
                    default:
                        $a_ttitle = mb_strcut($ttitle_hc, 0, $tt_trim_len);
                        $a_ttitle .= ' ...';
                    }
                    $this->_ttitle_ht = p2h($a_ttitle, false);
                } else {
                    $this->_ttitle_ht = $this->getTtitleHd();
                }
            } else {
                $this->_ttitle_ht = $this->getTtitleHd();
            }
        }

        return $this->_ttitle_ht;
    }

    // }}}
    // {{{ getThreadInfoFromExtIdxLine()

    /**
     * fav, recent用の拡張idxリストからラインデータを取得する
     */
    public function getThreadInfoFromExtIdxLine($l)
    {
        $la = explode('<>', rtrim($l));
        $this->host = $la[10];
        $this->bbs = $la[11];
        $this->key = $la[1];

        if (!$this->ttitle) {
            if ($la[0]) {
                $this->setTtitle(rtrim($la[0]));
            }
        }

        //$this->fav = (int)$la[6];
    }

    // }}}
    // {{{ setThreadPathInfo()

    /**
     * Set Path info
     */
    public function setThreadPathInfo($host, $bbs, $key)
    {
        //$GLOBALS['debug'] && $GLOBALS['profiler']->enterSection('setThreadPathInfo()');

        $this->host = $host;
        $this->bbs = $bbs;
        $this->key = $key;

        $this->keydat = $this->getDatDir() . $key . '.dat';
        $this->keyidx = $this->getIdxDir() . $key . '.idx';

        //$GLOBALS['debug'] && $GLOBALS['profiler']->leaveSection('setThreadPathInfo()');

        return true;
    }

    // }}}
    // {{{ isKitoku()

    /**
     * スレッドが既得済みならtrueを返す
     */
    public function isKitoku()
    {
        // if (file_exists($this->keyidx)) {
        if ($this->gotnum || $this->readnum || $this->newline > 1) {
            return true;
        }
        return false;
    }

    // }}}
    // {{{ getThreadInfoFromIdx()

    /**
     * 既得スレッドデータをkey.idxから取得する
     */
    public function getThreadInfoFromIdx()
    {
        //$GLOBALS['debug'] && $GLOBALS['profiler']->enterSection('getThreadInfoFromIdx');

        if (!$lines = FileCtl::file_read_lines($this->keyidx)) {
            //$GLOBALS['debug'] && $GLOBALS['profiler']->leaveSection('getThreadInfoFromIdx');
            return false;
        }

        $key_line = rtrim($lines[0]);
        $lar = explode('<>', $key_line);
        if (!$this->ttitle) {
            if ($lar[0]) {
                $this->setTtitle(rtrim($lar[0]));
            }
        }

        if ($lar[5]) {
            $this->readnum = intval($lar[5]);

        // 旧互換措置（$lar[9] newlineの廃止）
        } elseif ($lar[9]) {
            $this->readnum = $lar[9] -1;
        }

        if ($lar[3]) {
            $this->gotnum = intval($lar[3]);

            if ($this->rescount) {
                $this->unum = $this->rescount - $this->readnum;
                // machi bbs はsubjectの更新にディレイがあるようなので調整しておく
                if ($this->unum < 0) {
                    $this->unum = 0;
                }
                $this->nunum = $this->unum;
            }
        } else {
            $this->gotnum = 0;
        }

        $this->fav = (int)$lar[6]; // あえてboolでなく

        // 過去ログ取り込み済フラグ
        if (isset($lar[12])) {
            $this->datochiok = $lar[12];
        }

        /*
        // 現在key.idxのこのカラムは使用していない。datサイズは直接ファイルの大きさを読み取って調べる
        if ($lar[2]) {
            $this->length = $lar[2];
        }
        */
        if ($lar[4]) { $this->modified = $lar[4]; }

        //$GLOBALS['debug'] && $GLOBALS['profiler']->leaveSection('getThreadInfoFromIdx');

        return $key_line;
    }

    // }}}
    // {{{ getDatBytesFromLocalDat()

    /**
     * ローカルDATのファイルサイズを取得する
     */
    public function getDatBytesFromLocalDat()
    {
        clearstatcache();
        if (file_exists($this->keydat)) {
            $this->length = filesize($this->keydat);
        } else {
            $this->length = 0;
        }
        return $this->length;
    }

    // }}}
    // {{{ getThreadInfoFromSubjectTxtLine()

    /**
     * subject.txt の一行からスレ情報を取得する
     */
    public function getThreadInfoFromSubjectTxtLine($l)
    {
        //$GLOBALS['debug'] && $GLOBALS['profiler']->enterSection('getThreadInfoFromSubjectTxtLine()');

        if (preg_match('/^([0-9]+)\\.(?:dat|cgi)(?:,|<>)(.+) ?(?:\\(|（)([0-9]+)(?:\\)|）)$/', $l, $matches)) {
            $this->isonline = true;
            $this->key = $matches[1];
            $this->setTtitle(rtrim($matches[2]));
            $this->rescount = (int)$matches[3];
            if ($this->readnum) {
                $this->unum = $this->rescount - $this->readnum;
                // machi bbs はsageでsubjectの更新が行われないそうなので調整しておく
                if ($this->unum < 0) {
                    $this->unum = 0;
                }
                $this->nunum = $this->unum;
            }

            //$GLOBALS['debug'] && $GLOBALS['profiler']->leaveSection('getThreadInfoFromSubjectTxtLine()');
            return true;
        }

        if (preg_match('/^([0-9]+)\.(?:dat|cgi)<>(.+?)<>(\d+)<>(\d+)<>(\d+)<>(\d+)<>(.+?)<>(.+?)<>/', $l, $matches)) {
            $this->isonline = true;
            $this->key = $matches[1];
            $this->setTtitle(rtrim($matches[2]));
            $this->rescount = (int)$matches[3];
            if ($this->readnum) {
                $this->unum = $this->rescount - $this->readnum;
                // machi bbs はsageでsubjectの更新が行われないそうなので調整しておく
                if ($this->unum < 0) {
                    $this->unum = 0;
                }
                $this->nunum = $this->unum;
            }
            $this->lastmodify = (int)$matches[6];
            $this->extdat = $matches[8];

            //$GLOBALS['debug'] && $GLOBALS['profiler']->leaveSection('getThreadInfoFromSubjectTxtLine()');
            return true;
        }

        //$GLOBALS['debug'] && $GLOBALS['profiler']->leaveSection('getThreadInfoFromSubjectTxtLine()');
        return false;
    }

    // }}}
    // {{{ setTitleFromLocal()

    /**
     * スレタイトル取得メソッド
     */
    public function setTitleFromLocal()
    {
        if (!isset($this->ttitle)) {

            if ($this->datlines) {
                $firstdatline = rtrim($this->datlines[0]);
                $d = $this->explodeDatLine($firstdatline);
                $this->setTtitle($d[4]);

            // ローカルdatの1行目から取得
            } elseif (is_readable($this->keydat)) {
                $fd = fopen($this->keydat, "rb");
                $l = fgets($fd, 32800);
                fclose($fd);
                $firstdatline = rtrim($l);
                if (strpos($firstdatline, '<>') !== false) {
                    $datline_sepa = "<>";
                } else {
                    $datline_sepa = ",";
                    $this->dat_type = "2ch_old";
                }
                $d = explode($datline_sepa, $firstdatline);
                $this->setTtitle($d[4]);

                // be.2ch.net ならEUC→SJIS変換
                if (P2HostMgr::isHostBe2chs($this->host)) {
                    $ttitle = mb_convert_encoding($this->ttitle, 'CP932', 'CP51932');
                    $this->setTtitle($ttitle);
                }
            }

        }

        return $this->ttitle;
    }

    // }}}
    // {{{ getMotoThread()

    /**
     * 元スレURLを返す
     *
     * @param   bool    $force_pc   trueなら携帯モードでもPC用の元スレURLを返す
     * @param   string  $ls         レス表示番号or範囲。nullならlsプロパティを使う
     *                              掲示板によっては無視される場合もある
     * @return  string  元スレURL
     */
    public function getMotoThread($force_pc = false, $ls = null)
    {
        global $_conf;

        if ($force_pc) {
            $mobile = false;
        } elseif ($_conf['iphone']) {
            $mobile = false;
        } elseif ($_conf['ktai']) {
            $mobile = true;
        } else {
            $mobile = false;
        }

        if ($ls === null) {
            $ls = $this->ls;
        }

        // 2ch系
        if (P2HostMgr::isHost2chs($this->host)) {
            // PC
            if (!$mobile) {
                $motothre_url = "http://{$this->host}/test/read.cgi/{$this->bbs}/{$this->key}/{$ls}";
            // 携帯
            } else {
                if (P2HostMgr::isHostBbsPink($this->host)) {
                    //$motothre_url = "http://{$this->host}/test/r.i/{$this->bbs}/{$this->key}/{$ls}";
                    $motothre_url = "http://speedo.ula.cc/test/r.so/{$this->host}/{$this->bbs}/{$this->key}/{$ls}"; 
                } else {
                    $mail = rawurlencode($_conf['my_mail']);
                    // c.2chはl指定に非対応なので、代わりにn
                    $ls = (substr($ls, 0, 1) == 'l') ? 'n' : $ls;
                    $motothre_url = "http://c.2ch.net/test/--3!mail={$mail}/{$this->bbs}/{$this->key}/{$ls}";
                }
            }

        // まちBBS
        } elseif (P2HostMgr::isHostMachiBbs($this->host)) {
            if ($mobile) {
                $motothre_url = "http://{$this->host}/bbs/read.pl?IMODE=TRUE&BBS={$this->bbs}&KEY={$this->key}";
            } else {
                $motothre_url = "http://{$this->host}/bbs/read.cgi/{$this->bbs}/{$this->key}/{$ls}";
            }

        // まちびねっと
        } elseif (P2HostMgr::isHostMachiBbsNet($this->host)) {
            $motothre_url = "http://{$this->host}/test/read.cgi?bbs={$this->bbs}&key={$this->key}";
            if ($mobile) { $motothre_url .= '&imode=true'; }

        // JBBSしたらば
        } elseif (P2HostMgr::isHostJbbsShitaraba($this->host)) {
            list($host, $category) = explode('/', P2HostMgr::adjustHostJbbs($this->host), 2);
            $bbs_cgi = ($mobile) ? 'i.cgi' : 'read.cgi';
            $motothre_url = "http://{$host}/bbs/{$bbs_cgi}/{$category}/{$this->bbs}/{$this->key}/{$ls}";

        // その他
        } else {
            $motothre_url = "http://{$this->host}/test/read.cgi/{$this->bbs}/{$this->key}/{$ls}";
        }

        return $motothre_url;
    }

    // }}}
    // {{{ setDayRes()

    /**
     * 勢い（レス/日）をセットする
     */
    public function setDayRes($nowtime = false)
    {
        //$GLOBALS['debug'] && $GLOBALS['profiler']->enterSection('setDayRes()');

        if (!isset($this->key) || !isset($this->rescount)) {
            //$GLOBALS['debug'] && $GLOBALS['profiler']->leaveSection('setDayRes()');
            return false;
        }

        if (!$nowtime) {
            $nowtime = time();
        }
        if ($pastsc = $nowtime - $this->key) {
            $this->dayres = $this->rescount / $pastsc * 60 * 60 * 24;
            //$GLOBALS['debug'] && $GLOBALS['profiler']->leaveSection('setDayRes()');
            return true;
        }

        //$GLOBALS['debug'] && $GLOBALS['profiler']->leaveSection('setDayRes()');
        return false;
    }

    // }}}
    // {{{ getTimePerRes()

    /**
     * レス間隔（時間/レス）を取得する
     */
    public function getTimePerRes()
    {
        $noresult_st = "-";

        if (!isset($this->dayres)) {
            if (!$this->setDayRes(time())) {
                return $noresult_st;
            }
        }

        if ($this->dayres <= 0) {
            return $noresult_st;

        } elseif ($this->dayres < 1/365) {
            $spd = 1/365 / $this->dayres;
            $spd_suffix = "年";
        } elseif ($this->dayres < 1/30.5) {
            $spd = 1/30.5 / $this->dayres;
            $spd_suffix = "ヶ月";
        } elseif ($this->dayres < 1) {
            $spd = 1 / $this->dayres;
            $spd_suffix = "日";
        } elseif ($this->dayres < 24) {
            $spd = 24 / $this->dayres;
            $spd_suffix = "時間";
        } elseif ($this->dayres < 24*60) {
            $spd = 24*60 / $this->dayres;
            $spd_suffix = "分";
        } elseif ($this->dayres < 24*60*60) {
            $spd = 24*60*60 / $this->dayres;
            $spd_suffix = "秒";
        } else {
            $spd = 1;
            $spd_suffix = "秒以下";
        }
        if ($spd > 0) {
            $spd_st = sprintf("%01.1f", @round($spd, 2)) . $spd_suffix;
        } else {
            $spd_st = "-";
        }
        return $spd_st;
    }

    // }}}
    // {{{ getFavStatus()

    /**
     * お気に入り登録状態を取得する
     */
    public function getFavStatus()
    {
        global $_conf;

        if (!is_array($this->_favs)) {
            if (!$_conf['expack.misc.multi_favs'] || $_conf['expack.misc.favset_num'] < 0) {
                $this->_favs = array($this->fav);
            } else {
                $this->_favs = array_fill(0, $_conf['expack.misc.favset_num'] + 1, false);
                $group = P2HostMgr::getHostGroupName($this->host);
                foreach ($_conf['favlists'] as $num => $favlist) {
                    foreach ($favlist as $fav) {
                        if ($this->key == $fav['key'] && $this->bbs == $fav['bbs'] && $group == $fav['group']) {
                            $this->_favs[$num] = true;
                            break;
                        }
                    }
                }
            }
        }

        return $this->_favs;
    }

    // }}}
    // {{{ getDatDir()

    /**
     * datの保存ディレクトリを返す
     *
     * @param bool $dir_sep
     * @return string
     * @see P2Util::datDirOfHost(), ThreadList::getDatDir()
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
     * @see P2Util::idxDirOfHost(), ThreadList::getIdxDir()
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
