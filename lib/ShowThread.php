<?php
/**
 * rep2- スレッドを表示する クラス
 */

$GLOBALS['last_hit_resnum'] = null;

// {{{ ShowThread

abstract class ShowThread
{
    // {{{ constants

    /**
     * リンクとして扱うパターン
     *
     * @var string
     */
    const LINK_REGEX = '{
(?P<link>(<[Aa][ ].+?>)(.*?)(</[Aa]>)) # リンク（PCREの特性上、必ずこのパターンを最初に試行する）
|
(?:
  (?P<quote> # 引用
    ((?:&gt;|＞){1,2}[ ]?) # 引用符
    (
      (?:[1-9]\\d{0,3}) # 1つ目の番号
      (?:
        (?:[ ]?(?:[,=]|、)[ ]?[1-9]\\d{0,3})+ # 連続
        |
        -(?:[1-9]\\d{0,3})? # 範囲
      )?
    )
    (?=\\D|$)
  ) # 引用ここまで
|                                  # PHP 5.3縛りにするなら、↓の\'のエスケープを外し、NOWDOCにする
  (?P<url>(ftp|h?t?tps?)://([0-9A-Za-z][\\w;/?:@=&$\\-_.+!*\'(),#%\\[\\]^~]+)) # URL
  ([^\\s<>]*) # URLの直後、タグorホワイトスペースが現れるまでの文字列
|
  (?P<id>ID:[ ]?([0-9A-Za-z/.+]{8,14})(?=[^0-9A-Za-z/.+]|$)) # ID（8,10桁 +PC/携帯識別フラグ）
)
}x';

    /**
     * リダイレクタの種類
     *
     * @var int
     */
    const REDIRECTOR_NONE = 0;
    const REDIRECTOR_IMENU = 1;
    const REDIRECTOR_PINKTOWER = 2;
    const REDIRECTOR_MACHIBBS = 3;

    /**
     * NGあぼーんの種類
     *
     * @var int
     */
    const ABORN = -1;
    const NG_NONE = 0;
    const NG_NAME = 1;
    const NG_MAIL = 2;
    const NG_ID = 4;
    const NG_MSG = 8;
    const NG_FREQ = 16;
    const NG_CHAIN = 32;
    const NG_AA = 64;

	// +live ハイライトワード用
	const HIGHLIGHT_NONE = 128;
	const HIGHLIGHT_NAME = 256;
	const HIGHLIGHT_MAIL = 512;
	const HIGHLIGHT_ID = 1024;
	const HIGHLIGHT_MSG = 2048;
	const HIGHLIGHT_CHAIN = 4096;

    // }}}
    // {{{ static properties

    /**
     * まとめ読みモード時のスレッド数
     *
     * @var int
     */
    static private $_matome_count = 0;

    /**
     * 本文以外がNGあぼーんにヒットした総数
     *
     * @var int
     */
    static protected $_ngaborns_head_hits = 0;

    /**
     * 本文がNGあぼーんにヒットした総数
     *
     * @var int
     */
    static protected $_ngaborns_body_hits = 0;

	static protected $_highlight_head_hits = 0; // 本文以外がハイライトにヒットした総数
	static protected $_highlight_body_hits = 0; // 本文がハイライトにヒットした総数

    /**
     * getAnchorRegex() のキャッシュ
     *
     * @var array
     */
    static private $_anchorRegexes = array();

    /**
     * _getAnchorRegexParts() のキャッシュ
     *
     * @var array
     */
    static private $_anchorRegexParts = null;

    /**
     * transLinkDo() のキャッシュ
     *
     * @var array
     */
    static private  $_link_cache = array();

    // }}}
    // {{{ properties

    /**
     * まとめ読みモード時のスレッド番号
     *
     * @var int
     */
    protected $_matome;

    /**
     * URLを処理する関数・メソッド名などを格納する配列
     * (組み込み)
     *
     * @var array
     */
    protected $_url_handlers;

    /**
     * URLを処理する関数・メソッド名などを格納する配列
     * (ユーザ定義、組み込みのものより優先)
     *
     * @var array
     */
    protected $_user_url_handlers;

    /**
     * 頻出IDをあぼーんする
     *
     * @var bool
     */
    protected $_ngaborn_frequent;

    /**
     * NG or あぼーんレスがあるかどうか
     *
     * @var bool
     */
    protected $_has_ngaborns;

    /**
     * あぼーんレス番号およびNGレス番号を格納する配列
     * array_intersect()を効率よく行うため、該当するレス番号は文字列にキャストして格納する
     *
     * @var array
     */
    protected $_aborn_nums;
    protected $_ng_nums;

	protected $_highlight_nums; // ハイライトレス番号を格納する配列
	protected $_highlight_msgs; // ハイライトメッセージを格納する配列

    /**
     * リダイレクタの種類
     *
     * @var int
     */
    protected $_redirector;

    /**
     * スレッドオブジェクト
     *
     * @var ThreadRead
     */
    public $thread;

    /**
     * アクティブモナー・オブジェクト
     *
     * @var ActiveMona
     */
    public $activeMona;

    /**
     * アクティブモナーが有効か否か
     *
     * @var bool
     */
    public $am_enabled = false;

    /**
     * 引用しているレス番号を登録した配列
     *
     * @var array
     */
    protected $_quote_res_nums;


    /**
     * 引用チェック済みレス番号の配列
     *
     * @var array
     */
    protected $_quote_res_nums_checked;

    /**
     * 引用変換済みレス番号の配列
     *
     * @var array
     */
    protected $_quote_res_nums_done;

    /**
     * レス番号チェックの再帰の深さ
     *
     * @var int
     */
    private $_quote_check_depth;

    /**
     * デフォルトの名前
     *
     * @var string
     */
    protected $_nanashiName = null;

    /**
     * 被アンカーを集計した配列(範囲アンカー含む) // [被参照レス番 : [参照レス番, ...], ...)
     *
     * @var array
     */
    protected $_quote_from = null;

    /**
     * アンカーを集計した配列(範囲アンカー除く) // [レス番 : [参照先レス番, ...], ...)
     *
     * @var array
     */
    protected $_quote_to = null;

    /**
     * お気に自動ランク
     *
     * @var bool
     */
    private $_auto_fav_rank = false;

    /**
     * リンクするサムネイルを生成するクラスのインスタンス
     *
     * @var ImageCache2_Thumbnailer
     */
    public $thumbnailer;

    /**
     * インライン表示するサムネイルを生成するクラスのインスタンス
     *
     * @var ImageCache2_Thumbnailer
     */
    public $inline_prvw;

    /**
     * インラインサムネイルのID属性接尾辞
     *
     * @var string
     */
    public $thumb_id_suffix;

    /**
     * 画像に付加するメモ
     *
     * @var string
     */
    public $img_memo;

    /**
     * 画像にメモを付けるためのクエリ文字列
     *
     * @var string
     */
    public $img_memo_query;

    /**
     * 画像解像度
     *
     * @var float
     */
    public $img_dpr = 1.0;

    /**
     * 画像解像度を指定するクエリ文字列
     *
     * @var string
     */
    public $img_dpr_query;

    // }}}
    // {{{ constructor

    /**
     * コンストラクタ
     */
    protected function __construct(ThreadRead $aThread, $matome = false)
    {
        global $_conf;

        // スレッドオブジェクトを登録
        $this->thread = $aThread;
        $this->str_to_link_regex = $this->_buildStrToLinkRegex();

        // まとめ読みモードか否か
        if ($matome) {
            $this->_matome = ++self::$_matome_count;
        } else {
            $this->_matome = false;
        }

        $this->_url_handlers = array();
        $this->_user_url_handlers = array();

        $this->_ngaborn_frequent = 0;
        if ($_conf['ngaborn_frequent']) {
            if ($_conf['ngaborn_frequent_dayres'] == 0) {
                $this->_ngaborn_frequent = $_conf['ngaborn_frequent'];
            } elseif ($this->thread->setDayRes() && $this->thread->dayres < $_conf['ngaborn_frequent_dayres']) {
                $this->_ngaborn_frequent = $_conf['ngaborn_frequent'];
            }
        }

        $this->_has_ngaborns = false;
        $this->_aborn_nums = array();
        $this->_ng_nums = array();

		$this->_highlight_nums = array();
		$this->_highlight_msgs = array();

        if (P2HostMgr::isHostBbsPink($this->thread->host)) {
            $this->_redirector = self::REDIRECTOR_PINKTOWER;
        } elseif (P2HostMgr::isHost2chs($this->thread->host)) {
            $this->_redirector = self::REDIRECTOR_IMENU;
        } elseif (P2HostMgr::isHostMachiBbs($this->thread->host)) {
            $this->_redirector = self::REDIRECTOR_MACHIBBS;
        } else {
            $this->_redirector = self::REDIRECTOR_NONE;
        }

        $this->_quote_res_nums = array();
        $this->_quote_res_nums_checked = array();
        $this->_quote_res_nums_done = array();
    }

    // }}}

    /**
     * @param   void
     * @return  void
     */
    protected function setBbsNonameName()
    {
        $st = new SettingTxt($this->thread->host, $this->thread->bbs);
        $st->setSettingArray();
        if (array_key_exists('BBS_NONAME_NAME', $st->setting_array)) {
            $BBS_NONAME_NAME = $st->setting_array['BBS_NONAME_NAME'];
            if (strlen($BBS_NONAME_NAME)) {
                $this->_nanashiName = $BBS_NONAME_NAME;
            }
        }
    }

    // {{{ getDatToHtml()

    /**
     * DatをHTML変換したものを取得する
     *
     * @param   bool $is_fragment   trueなら<div class="thread"></div>で囲まない
     * @return  bool|string
     */
    public function getDatToHtml($is_fragment = false)
    {
        global $_conf, $filter_hits, $last_hit_resnum;

        $aThread = $this->thread;

        // 表示レス範囲が指定されていなければ
        if (!$aThread->resrange) {
            $error = '<p><b>p2 error: {$this->resrange} is false at getDatToHtml()</b></p>';
            return $error;
        }

        $start = $aThread->resrange['start'];
        $to = $aThread->resrange['to'];
        $nofirst = $aThread->resrange['nofirst'];

        $is_ktai = $_conf['ktai'];
        $resFilter = ResFilter::getFilter();
        if ($resFilter && $resFilter->hasWord()) {
            $do_filtering = true;
            $nofirst = true;
        } else {
            $do_filtering = false;
        }

        $datlines = $aThread->datlines;
        $count = count($datlines);

        $buf['body'] = $is_fragment ? '' : "<div class=\"thread\">\n";
        $buf['q'] = '';

        // まず 1 を表示
        if (!$nofirst) {
            $res = $this->transRes($datlines[0], 1);
            if (is_array($res)) {
                $buf['body'] .= $res['body'];
                $buf['q'] .= $res['q'] ? $res['q'] : '';
            } else {
                $buf['body'] .= $res;
            }
        }

        // 連鎖のため、範囲外のNGあぼーんチェック
        if ($_conf['ngaborn_chain_all'] && empty($_GET['nong'])) {
            $pre = min($count, $start);
            for ($i = ($nofirst) ? 0 : 1; $i < $pre; $i++) {
                $n = $i + 1;
                list($name, $mail, $date_id, $msg) = $aThread->explodeDatLine($datlines[$i]);
                if (($id = $aThread->ids[$n]) !== null) {
                    $date_id = str_replace($aThread->idp[$n] . $id, "ID:$id", $date_id);
                }
                $this->_ngAbornCheck($n, strip_tags($name), $mail, $date_id, $id, $msg);
            }
        }

        // フィルタリング
        if ($do_filtering) {
            $datlines = $resFilter->apply($this);
            $filter_hits = $resFilter->hits;
            $last_hit_resnum = $resFilter->last_hit_resnum;
        }

        // 指定範囲を表示
        $i = 0;
        $n = 0;
        $rn = 0;

        if ($do_filtering) {
            if (!empty($resFilter->range)) {
                $start = $resFilter->range['start'];
                $to = $resFilter->range['to'];
            }
            $pattern = $resFilter->getPattern();
        } else {
            $pattern = null;
        }

        foreach ($datlines as $i => $ares) {
            if ($ares === null) {
                continue;
            }
            $n++;
            if ($i === 0 && !$nofirst) {
                continue;
            }
            if ($n < $start) {
                continue;
            }
            if ($n > $to) {
                break;
            }
            $rn = $i + 1;
            $res = $this->transRes($ares, $rn, $pattern);
            if (is_array($res)) {
                $buf['body'] .= $res['body'];
                $buf['q'] .= $res['q'] ? $res['q'] : '';
            } else {
                $buf['body'] .= $res;
            }
        }

        if ($this->thread->readnum < $rn) {
            $this->thread->readnum = $rn;
        }

        if ($do_filtering && !$is_ktai) {
            $buf['body'] .= "<script type=\"text/javascript\">filterCount({$filter_hits});</script>\n";
        }

        if (!$is_fragment) {
            $buf['body'] .= "</div>\n";
        }

        return $buf['body'] . $buf['q'];

    }

    /**
     * 指定の書込みへのレスをHTMLに変換したものを取得する
     *
     * @param   bool $is_fragment   trueなら<div class="thread"></div>で囲まない
     * @param   bool $show_rootres  trueなら指定の書込みも結果に含める
     * @return  bool|string
     */
    public function getDatToHtml_resFrom($is_fragment = false, $show_rootres = false)
    {
        global $_conf;

        $aThread = $this->thread;

        // 表示レスが指定されていなければ
        $target = $aThread->resrange['start'];
        if (!$aThread->resrange || $target != $aThread->resrange['to']) {
            $error = '<p><b>p2 error: {$this->resrange} is false at datToHtml()</b></p>';
            return $error;
        }

        $datlines = $aThread->datlines;
        $count = count($datlines);

        $buf['body'] = $is_fragment ? '' : "<div class=\"thread\">\n";
        $buf['q'] = '';

        // 連鎖のため、範囲外のNGあぼーんチェック
        if ($_conf['ngaborn_chain_all'] && empty($_GET['nong'])) {
            $pre = min($count, $start);
            for ($i = ($nofirst) ? 0 : 1; $i < $pre; $i++) {
                $n = $i + 1;
                list($name, $mail, $date_id, $msg) = $aThread->explodeDatLine($datlines[$i]);
                if (($id = $aThread->ids[$n]) !== null) {
                    $date_id = str_replace($aThread->idp[$n] . $id, "ID:$id", $date_id);
                }
                $this->_ngAbornCheck($n, strip_tags($name), $mail, $date_id, $id, $msg);
            }
        }

        // レス展開
        $datlines = array_fill(0, count($aThread->datlines), null);
        if ($show_rootres) {
            $datlines[$target - 1] = $aThread->datlines[$target - 1];
        }
        list($name, $mail, $date_id, $msg) =
            $aThread->explodeDatLine($aThread->datlines[$target - 1]);
        foreach ($this->checkQuoteResNums($target, $name, $msg, false, true, false) as $rn) {
            $ri = $rn - 1;
            if ($datlines[$ri] === null) {
                $datlines[$ri] = $aThread->datlines[$ri];
            }
        }

        // 表示
        $i = 0;
        $n = 0;
        $rn = 0;
        foreach ($datlines as $i => $ares) {
            if ($ares === null) {
                continue;
            }
            $n++;
            $rn = $i + 1;
            $res = $this->transRes($ares, $rn);
            if (is_array($res)) {
                $buf['body'] .= $res['body'];
                $buf['q'] .= $res['q'] ? $res['q'] : '';
            } else {
                $buf['body'] .= $res;
            }
        }

        if (!$is_fragment) {
            $buf['body'] .= "</div>\n";
        }

        return $buf['body'] . $buf['q'];
    }

    // }}}
    // {{{ transRes()

    /**
     * DatレスをHTMLレスに変換する
     *
     * @param   string  $ares   datの1ライン
     * @param   int     $i      レス番号
     * @return  string
     */
    abstract public function transRes($ares, $i);

    // }}}
    // {{{ transName()

    /**
     * 名前をHTML用に変換する
     *
     * @param   string  $name   名前
     * @return  string
     */
    abstract public function transName($name);

    // }}}
    // {{{ transMsg()

    /**
     * datのレスメッセージをHTML表示用メッセージに変換する
     *
     * @param   string  $msg    メッセージ
     * @param   int     $mynum  レス番号
     * @return  string
     */
    abstract public function transMsg($msg, $mynum);

    // }}}
    // {{{ replaceBeId()

    /**
     * BEプロファイルリンク変換
     */
    public function replaceBeId($date_id, $i)
    {
        global $_conf;

        $beid_replace = "<a href=\"http://be.2ch.net/test/p.php?i=\$1&u=d:http://{$this->thread->host}/test/read.cgi/{$this->thread->bbs}/{$this->thread->key}/{$i}\"{$_conf['ext_win_target_at']}>Lv.\$2</a>";

        //<BE:23457986:1>
        $be_match = '|<BE:(\d+):(\d+)>|i';
        if (preg_match($be_match, $date_id)) {
            $date_id = preg_replace($be_match, $beid_replace, $date_id);

        } else {

            $beid_replace = "<a href=\"http://be.2ch.net/test/p.php?i=\$1&u=d:http://{$this->thread->host}/test/read.cgi/{$this->thread->bbs}/{$this->thread->key}/{$i}\"{$_conf['ext_win_target_at']}>?\$2</a>";
            $date_id = preg_replace('|BE: ?(\d+)-(#*)|i', $beid_replace, $date_id);
        }

        return $date_id;
    }

    // }}}
    // {{{ replaceSsspIcon()

    /**
     * BE＆絵文字アイコンリンク変換
     */
    public function replaceSsspIcon($msg)
    {
        //sssp://img.2ch.net/ico/miyabi.gif
        //sssp://img.2ch.net/emoji/2ch-cry.gif
        return preg_replace("{sssp(://[-_.!~*()0-9A-Za-z;/:@&=+\$,%]+?\.(?:gif|png|jpe|jpe?g|ico|bmp|tif|mng|j2k)(?:[?#][-_.!~*()0-9A-Za-z;/?:@&=+\$,%#]*|))}", "<a href=\"http$1\"><img src=\"http$1\" /></a>", $msg);
    }

    // }}}
    // {{{ _ngAbornCheck()

    /**
     * NGあぼーんチェック
     *
     * @param   int     $i          レス番号
     * @param   string  $name       名前欄
     * @param   string  $mail       メール欄
     * @param   string  $date_id    日付・ID欄
     * @param   string  $id         ID
     * @param   string  $msg        レス本文
     * @param   bool    $nong       NGチェックをするかどうか
     * @param   array  &$info       NGの理由が格納される変数の参照
     * @return  int NGタイプ。ShowThread::NG_XXX のビット和か ShowThread::ABORN
     */
    protected function _ngAbornCheck($i, $name, $mail, $date_id, $id, $msg, $nong = false, &$info = null)
    {
        global $_conf, $ngaborns_hits, $highlight_msgs, $highlight_chain_nums;

        $info = array();
        $type = self::NG_NONE;

        // >>1 をあぼーんの対象外にする
        if ($_conf['ngaborn_exclude_one'] && $i == 1) {
            return $type;
        }

        // {{{ 頻出IDチェック

        if ($this->_ngaborn_frequent && $id && $this->thread->idcount[$id] >= $_conf['ngaborn_frequent_num']) {
            if (!$_conf['ngaborn_frequent_one'] && $id == $this->thread->ids[1]) {
                // >>1 はそのまま表示
            } elseif ($this->_ngaborn_frequent == 1) {
                $ngaborns_hits['aborn_freq']++;
                return $this->_markNgAborn($i, self::ABORN, false);
            } elseif (!$nong) {
                $ngaborns_hits['ng_freq']++;
                $type |= $this->_markNgAborn($i, self::NG_FREQ, false);
                $info[] = sprintf('頻出ID:%s(%d)', $id, $this->thread->idcount[$id]);
            }
        }

        // }}}
        // {{{ 連鎖チェック

        if ($_conf['ngaborn_chain'] && $this->_has_ngaborns &&
            preg_match_all('/(?:&gt;|＞)([1-9][0-9\\-,]*)/', $msg, $matches)
        ) {
            $references = array_unique(preg_split('/[-,]+/',
                                                  trim(implode(',', $matches[1]), '-,'),
                                                  -1,
                                                  PREG_SPLIT_NO_EMPTY));
            $intersections = array_intersect($references, $this->_aborn_nums);
            $info_suffix = '';

            if ($intersections) {
                if ($_conf['ngaborn_chain'] == 1) {
                    $ngaborns_hits['aborn_chain']++;
                    return $this->_markNgAborn($i, self::ABORN, true);
                }
                if ($nong) {
                    $intersections = null;
                } else {
                    $info_suffix = '(' . (($_conf['ktai']) ? 'ｱﾎﾞﾝ' : 'あぼーん') . ')';
                }
            } elseif (!$nong) {
                $intersections = array_intersect($references, $this->_ng_nums);
            }

            if ($intersections) {
                $ngaborns_hits['ng_chain']++;
                $type |= $this->_markNgAborn($i, self::NG_CHAIN, true);
                $info[] = sprintf('連鎖NG:&gt;&gt;%d%s', current($intersections), $info_suffix);
            }
        }

        // }}}
        // {{{ あぼーんチェック

        if ($_conf['ngaborn_auto']) {//自動あぼーん
            // 自動あぼーんID
            if ($this->ngAbornCheck('aborn_auto', $date_id) !== false) {
                $ngaborns_hits['aborn_auto']++;
                return $this->_markNgAborn($i, self::ABORN, false);
            }
        }

        // あぼーんレス
        if ($this->abornResCheck($i) !== false) {
            if ($_conf['ngaborn_auto']) {
                //自動あぼーんならIDを追加
                NgAbornCtl::ngAbornAdd('aborn_auto', $id);
            }
            $ngaborns_hits['aborn_res']++;
            return $this->_markNgAborn($i, self::ABORN, false);
        }

        // あぼーんネーム
        if ($this->ngAbornCheck('aborn_name', $name) !== false) {
            if ($_conf['ngaborn_auto']) {
                //自動あぼーんならIDを追加
                NgAbornCtl::ngAbornAdd('aborn_auto', $id);
            }
            $ngaborns_hits['aborn_name']++;
            return $this->_markNgAborn($i, self::ABORN, false);
        }

        // あぼーんメール
        if ($this->ngAbornCheck('aborn_mail', $mail) !== false) {
            if ($_conf['ngaborn_auto']) {
                //自動あぼーんならIDを追加
                NgAbornCtl::ngAbornAdd('aborn_auto', $id);
            }
            $ngaborns_hits['aborn_mail']++;
            return $this->_markNgAborn($i, self::ABORN, false);
        }

        // あぼーんID
        if ($this->ngAbornCheck('aborn_id', $date_id) !== false) {
            $ngaborns_hits['aborn_id']++;
            return $this->_markNgAborn($i, self::ABORN, false);
        }

        // あぼーんメッセージ
        if ($this->ngAbornCheck('aborn_msg', $msg) !== false) {
            if ($_conf['ngaborn_auto']) {
                //自動あぼーんならIDを追加
                NgAbornCtl::ngAbornAdd('aborn_auto', $id);
            }
            $ngaborns_hits['aborn_msg']++;
            return $this->_markNgAborn($i, self::ABORN, true);
        }

        // }}}

        if ($nong) {
            return $type;
        }

        // {{{ NGチェック

        if ($_conf['ngaborn_auto']) {//自動あぼーん
            // 自動NGIDを先にNGする
            if ($this->ngAbornCheck('ng_auto', $date_id) !== false) {
                $ngaborns_hits['ng_auto']++;
                $type |= $this->_markNgAborn($i, self::NG_ID, false);
                $autong_done = true; //自動NGIDの対象になったレスは意味が無いのでNGIDに追加しない
            }
        }

        // NGネームチェック
        if ($this->ngAbornCheck('ng_name', $name) !== false) {
            if ($_conf['ngaborn_auto'] && !$autong_done) {
                //自動あぼーんならIDを追加
                $add_done = NgAbornCtl::ngAbornAdd('ng_auto', $id);
            }
            $ngaborns_hits['ng_name']++;
            $type |= $this->_markNgAborn($i, self::NG_NAME, false);
        }

        // NGメールチェック
        if ($this->ngAbornCheck('ng_mail', $mail) !== false) {
            if ($_conf['ngaborn_auto'] && !$autong_done && !$add_done) {
                //自動あぼーんならIDを追加
                $add_done = NgAbornCtl::ngAbornAdd('ng_auto', $id);
            }
            $ngaborns_hits['ng_mail']++;
            $type |= $this->_markNgAborn($i, self::NG_MAIL, false);
        }

        // NGIDチェック
        if ($this->ngAbornCheck('ng_id', $date_id) !== false) {
            $ngaborns_hits['ng_id']++;
            $type |= $this->_markNgAborn($i, self::NG_ID, false);
        }

        // NGメッセージチェック
        $a_ng_msg = $this->ngAbornCheck('ng_msg', $msg);
        if ($a_ng_msg !== false) {
            if ($_conf['ngaborn_auto'] && !$autong_done && !$add_done) {
                //自動あぼーんならIDを追加
                $add_done = NgAbornCtl::ngAbornAdd('ng_auto', $id);
            }
            $ngaborns_hits['ng_msg']++;
            $type |= $this->_markNgAborn($i, self::NG_MSG, true);
            $info[] = sprintf('NG%s:%s',
                              ($_conf['ktai']) ? 'ﾜｰﾄﾞ' : 'ワード',
                              p2h($a_ng_msg));
        }

		// +live ハイライトチェック
		include P2_LIB_DIR . '/live/live_highlight_check.php';

        // }}}

        return $type;
    }

    // }}}
    // {{{ _markNgAborn()

    /**
     * NGあぼーんにヒットしたレス番号を記録する
     *
     * @param   int $num        レス番号
     * @param   int $type       NGあぼーんの種類
     * @param   bool $isBody    本文にヒットしたかどうか
     * @return  int $typeと同じ値
     */
    protected function _markNgAborn($num, $type, $isBody)
    {
        if ($type) {
            if ($isBody) {
                self::$_ngaborns_body_hits++;
            } else {
                self::$_ngaborns_head_hits++;
            }

            // array_intersect()を効率よく行うため、レス番号を文字列型にキャストする
            $str = (string)$num;
            if ($type == self::ABORN) {
                $this->_aborn_nums[$num] = $str;
            } else {
                $this->_ng_nums[$num] = $str;
            }

            $this->_has_ngaborns = true;
        }

        return $type;
    }

    // }}}
	// {{{ _markHighlight()

	// +live ハイライトにヒットしたレス番号を記録する
	protected function _markHighlight($num, $type, $isBody)
	{
		if ($type) {
			if ($isBody) {
				self::$_highlight_body_hits++;
			} else {
				self::$_highlight_head_hits++;
			}

			$str = (string)$num;
			$this->_highlight_nums[$num] = $str;

			$this->_has_ngaborns = true;
		}

		return $type;
	}

	// }}}
    // {{{ ngAbornCheck()

    /**
     * NGあぼーんチェック
     */
    public function ngAbornCheck($code, $resfield, $ic = false)
    {
        global $ngaborns;

        //$GLOBALS['debug'] && $GLOBALS['profiler']->enterSection('ngAbornCheck()');

        if (isset($ngaborns[$code]['data']) && is_array($ngaborns[$code]['data'])) {
            // +Wiki:BEあぼーん
            if ($code == 'aborn_be' || $code == 'ng_be') {
                // プロフィールIDを抜き出す
                if (preg_match('/BE:(\\d+)/', $resfield, $matches)) {
                    $beId = P2Util::calcBeId((int)$matches[1]);
                    if ($beId === 0) {
                        return false;
                    }
                    $resfield = (string)$beId;
                } else {
                    return false;
                }
            }

            $bbs = $this->thread->bbs;
            $title = $this->thread->ttitle_hc;

            foreach ($ngaborns[$code]['data'] as $k => $v) {
                // 板チェック
                if (isset($v['bbs']) && in_array($bbs, $v['bbs']) == false) {
                    continue;
                }

                // タイトルチェック
                if (isset($v['title']) && stripos($title, $v['title']) === false) {
                    continue;
                }

                // ワードチェック
                // 正規表現
                if ($v['regex']) {
                    $re_method = $v['regex'];
                    /*if ($re_method($v['word'], $resfield, $matches)) {
                        $this->ngAbornUpdate($code, $k);
                        //$GLOBALS['debug'] && $GLOBALS['profiler']->leaveSection('ngAbornCheck()');
                        return p2h($matches[0]);
                    }*/
                     if ($re_method($v['word'], $resfield)) {
                        $this->ngAbornUpdate($code, $k);
                        //$GLOBALS['debug'] && $GLOBALS['profiler']->leaveSection('ngAbornCheck()');
                        return $v['cond'];
                    }
                // +Wiki:BEあぼーん(完全一致)
                } elseif ($code == 'aborn_be' || $code == 'ng_be') {
                    if ($resfield == $v['word']) {
                        $this->ngAbornUpdate($code, $k);
                        //$GLOBALS['debug'] && $GLOBALS['profiler']->leaveSection('ngAbornCheck()');
                        return $v['cond'];
                    }
               // 大文字小文字を無視
                } elseif ($ic || $v['ignorecase']) {
                    if (stripos($resfield, $v['word']) !== false) {
                        $this->ngAbornUpdate($code, $k);
                        //$GLOBALS['debug'] && $GLOBALS['profiler']->leaveSection('ngAbornCheck()');
                        return $v['cond'];
                    }
                // 単純に文字列が含まれるかどうかをチェック
                } else {
                    if (strpos($resfield, $v['word']) !== false) {
                        $this->ngAbornUpdate($code, $k);
                        //$GLOBALS['debug'] && $GLOBALS['profiler']->leaveSection('ngAbornCheck()');
                        return $v['cond'];
                    }
                }
            }
        }

        //$GLOBALS['debug'] && $GLOBALS['profiler']->leaveSection('ngAbornCheck()');
        return false;
    }

    // }}}
    // {{{ abornResCheck()

    /**
     * 特定レスの透明あぼーんチェック
     */
    public function abornResCheck($resnum)
    {
        global $ngaborns;

        $target = $this->thread->host . '/' . $this->thread->bbs . '/' . $this->thread->key . '/' . $resnum;

        if (isset($ngaborns['aborn_res']['data']) && is_array($ngaborns['aborn_res']['data'])) {
            foreach ($ngaborns['aborn_res']['data'] as $k => $v) {
                if ($ngaborns['aborn_res']['data'][$k]['word'] == $target) {
                    $this->ngAbornUpdate('aborn_res', $k);
                    return true;
                }
            }
        }
        return false;
    }

    // }}}
    // {{{ ngAbornUpdate()

    /**
     * NG/あぼ～ん日時と回数を更新
     */
    public function ngAbornUpdate($code, $k)
    {
        global $ngaborns;

        if (isset($ngaborns[$code]['data'][$k])) {
            $ngaborns[$code]['data'][$k]['lasttime'] = date('Y/m/d G:i'); // HIT時間を更新
            if (empty($ngaborns[$code]['data'][$k]['hits'])) {
                $ngaborns[$code]['data'][$k]['hits'] = 1; // 初HIT
            } else {
                $ngaborns[$code]['data'][$k]['hits']++; // HIT回数を更新
            }
        }
    }

    // }}}
    // {{{ addURLHandler()

    /**
     * ユーザ定義URLハンドラ（メッセージ中のURLを書き換える関数）を追加する
     *
     * ハンドラは最初に追加されたものから順番に試行される
     * URLはハンドラの返り値（文字列）で置換される
     * falseを帰した場合は次のハンドラに処理が委ねられる
     *
     * ユーザ定義URLハンドラの引数は
     *  1. string $url  URL
     *  2. array  $purl URLをparse_url()したもの
     *  3. string $str  パターンにマッチした文字列、URLと同じことが多い
     *  4. object $aShowThread 呼び出し元のオブジェクト
     * である
     * 常にfalseを返し、内部で処理するだけの関数を登録してもよい
     *
     * @param   callback $function  コールバックメソッド
     * @return  void
     * @access  public
     * @todo    ユーザ定義URLハンドラのオートロード機能を実装
     */
    public function addURLHandler($function)
    {
        $this->_user_url_handlers[] = $function;
    }

    // }}}
    // {{{ stripLineBreaks()

    /**
     * 文末の改行と連続する改行を取り除く
     *
     * @param string $msg
     * @param string $replacement
     * @return string
     */
    public function stripLineBreaks($msg, $replacement = ' <br><br> ')
    {
        if (P2_MBREGEX_AVAILABLE) {
            $msg = mb_ereg_replace('(?:[\\s　]*<br>)+[\\s　]*$', '', $msg);
			$msg = mb_ereg_replace('(?:^[\\s　]*<br>)+[\\s　]*', '', $msg);
            $msg = mb_ereg_replace('(?:[\\s　]*<br>){3,}', $replacement, $msg);
        } else {
            mb_convert_variables('UTF-8', 'CP932', $msg, $replacement);
            $msg = preg_replace('/(?:[\\s\\x{3000}]*<br>)+[\\s\\x{3000}]*$/u', '', $msg);
            $msg = preg_replace('/(?:[\\s\\x{3000}]*<br>){3,}/u', $replacement, $msg);
            $msg = mb_convert_encoding($msg, 'CP932', 'UTF-8');
        }

        return $msg;
    }

    // }}}
    // {{{ transLink()

    /**
     * リンク対象文字列を変換する
     *
     * @param   string $str
     * @return  string
     */
    public function transLink($str)
    {
        return preg_replace_callback($this->str_to_link_regex, array($this, 'transLinkDo'), $str);
    }

    // }}}
    // {{{ transLinkDo()

    /**
     * リンク対象文字列の種類を判定して対応した関数/メソッドに渡す
     *
     * @param   array   $s
     * @return  string
     */
    public function transLinkDo(array $s)
    {
        if ($s['url']) {
            // sha1で囲んだ方が多少速くなるが低確率で衝突する
            $key = sha1(serialize($s));
            // キャッシュしてない場合
            if (!isset(self::$_link_cache[$key])) {
                // 結果を計算
                self::$_link_cache[$key] = self::_transLinkDo($s);
            }
            return self::$_link_cache[$key];
        } else {
            return self::_transLinkDo($s);
        }
    }
    // {{{ _transLinkDo()

    /**
     * transLinkDoの子関数
     *
     * @param   array   $s
     * @return  string
     */
    private function _transLinkDo(array $s)
    {
        global $_conf;

        $orig = $s[0];
        $following = '';

        // PHP 5.2.7 未満の preg_replace_callback() では名前付き捕獲式集合が使えないので
        /*
        if (!array_key_exists('link', $s)) {
            $s['link']  = $s[1];
            $s['quote'] = $s[5];
            $s['url']   = $s[8];
            $s['id']    = $s[11];
        }
        */

        // マッチしたサブパターンに応じて分岐
        // リンク
        if ($s['link']) {
            if (preg_match('{ href=(["\'])?(.+?)(?(1)\\1)(?=[ >])}i', $s[2], $m)) {
                $url = $m[2];
                $str = $s[3];
            } else {
                return $s[3];
            }

            // 引用
        } elseif ($s['quote']) {
            return  preg_replace_callback(
                self::getAnchorRegex('/(%prefix%)?(%a_range%)/'),
                array($this, '_quoteResCallback'), $s['quote']);

            // http or ftp のURL
        } elseif ($s['url']) {
            if ($_conf['ktai'] && $s[9] == 'ftp') {
                return $orig;
            }
            $url = preg_replace('/^t?(tps?)$/', 'ht$1', $s[9]) . '://' . $s[10];
            $str = $s['url'];
            $following = $s[11];
            if (strlen($following) > 0) {
                // ウィキペディア日本語版のURLで、SJISの2バイト文字の上位バイト
                // (0x81-0x9F,0xE0-0xEF)が続くとき
                if (P2HostMgr::isUrlWikipediaJa($url)) {
                    $leading = ord($following);
                    if ((($leading ^ 0x90) < 32 && $leading != 0x80) || ($leading ^ 0xE0) < 16) {
                        $url .= rawurlencode(mb_convert_encoding($following, 'UTF-8', 'CP932'));
                        $str .= $following;
                        $following = '';
                    }
                } elseif (strpos($following, 'tp://') !== false) {
                    // 全角スペース+URL等の場合があるので再チェック
                    $following = $this->transLink($following);
                }
            }

            // ID
        } elseif ($s['id'] && $_conf['flex_idpopup']) { // && $_conf['flex_idlink_k']
            return $this->idFilter($s['id'], $s[12]);

            // その他（予備）
        } else {
            return strip_tags($orig);
        }

        // リダイレクタを外す
        switch ($this->_redirector) {
            case self::REDIRECTOR_IMENU:
                $url = preg_replace('{^([a-z]+://)ime\\.nu/}', '$1', $url);
                break;
            case self::REDIRECTOR_PINKTOWER:
                $url = preg_replace('{^([a-z]+://)pinktower\\.com/}', '$1', $url);
                break;
            case self::REDIRECTOR_MACHIBBS:
                $url = preg_replace('{^[a-z]+://machi(?:bbs\\.com|\\.to)/bbs/link\\.cgi\\?URL=}', '', $url);
                break;
        }

        // エスケープされていない特殊文字をエスケープ
        $url = p2h($url, false);
        $str = p2h($str, false);
        // 実態参照・数値参照を完全にデコードしようとすると負荷が大きいし、
        // "&"以外の特殊文字はほとんどの場合URLエンコードされているはずなので
        // 中途半端に凝った処理はせず、"&amp;"→"&"のみ再変換する。
        $raw_url = str_replace('&amp;', '&', $url);

        // URLをパース・ホストを検証
        $purl = @parse_url($raw_url);
        if (!$purl || !array_key_exists('host', $purl) ||
            strpos($purl['host'], '.') === false ||
            $purl['host'] == '127.0.0.1' ||
            P2HostMgr::isHostExample($purl['host']))
        {
            return $orig;
        }
        // URLのマッチングで"&amp;"を考慮しなくて済むように、生のURLを登録しておく
        $purl[0] = $raw_url;

        // URLを処理
        foreach ($this->_user_url_handlers as $handler) {
            if (false !== ($link = call_user_func($handler, $url, $purl, $str, $this))) {
                return $link . $following;
            }
        }
        foreach ($this->_url_handlers as $handler) {
            if (false !== ($link = $this->$handler($url, $purl, $str))) {
                return $link . $following;
            }
        }

        return $orig;
    }

    // }}}
    // {{{ idFilter()

    /**
     * IDフィルタリング変換
     *
     * @param   string  $idstr  ID:xxxxxxxxxx
     * @param   string  $id        xxxxxxxxxx
     * @return  string
     */
    abstract public function idFilter($idstr, $id);

    // }}}
    // {{{ _idFilterCallback()

    /**
     * IDフィルタリング変換
     *
     * @param   array   $s  正規表現にマッチした要素の配列
     * @return  string
     */
    protected function _idFilterCallback(array $s)
    {
        return $this->idFilter($s[0], $s[1]);
    }

    // }}}
    // {{{ _quoteNameCallback()

    /**
     * @param   array $s
     * @return  string  HTML
     */
    protected function _quoteNameCallback($s)
    {
        return preg_replace_callback(
            self::getAnchorRegex('/(%prefix%)?(%a_num%)/'),
            array($this, '_quoteResCallback'), $s[0]
        );
    }

    // }}}
    // {{{ quoteRes()

    /**
     * 引用変換（単独）
     *
     * @param   string  $full           >>1
     * @param   string  $qsign          >>
     * @param   string  $appointed_num    1
     * @return  string
     */
    abstract public function quoteRes($full, $qsign, $appointed_num);

    // }}}
    // {{{ _quoteResCallback()

    /**
     * 引用変換（単独）
     *
     * @param   array   $s  正規表現にマッチした要素の配列
     * @return  string
     */
    protected function _quoteResCallback(array $s)
    {
        return $this->quoteRes($s[0], $s[1], $s[2]);
    }

    // }}}
    // {{{ quoteResRange()

    /**
     * 引用変換（範囲）
     *
     * @param   string  $full           >>1-100
     * @param   string  $qsign          >>
     * @param   string  $appointed_num    1-100
     * @return  string
     */
    abstract public function quoteResRange($full, $qsign, $appointed_num);

    // }}}
    // {{{ _quoteResRangeCallback()

    /**
     * 引用変換（範囲）
     *
     * @param   array   $s  正規表現にマッチした要素の配列
     * @return  string
     */
    protected function _quoteResRangeCallback(array $s)
    {
        return $this->quoteResRange($s[0], $s[1], $s[2]);
    }

    // }}}
    // {{{ checkQuoteResNums()

    /**
     * HTMLメッセージ中の引用レスの番号を再帰チェックする
     */
    public function checkQuoteResNums($res_num, $name, $msg,
                                      $with_quotes = true,
                                      $with_backlinks = null,
                                      $cascade = true)
    {
        global $_conf;

        $this->_quote_check_depth = 0;

        if ($with_backlinks === null) {
            $with_backlinks = ($_conf['backlink_list'] > 0 || $_conf['backlink_block'] > 0) ? true : false;
        }

        if ($with_backlinks) {
            return $this->checkQuoteResNumsFromSummary(
                $res_num == 0 ? 1 : $res_num, $with_quotes, $with_backlinks);
        }

        return $this->_checkQuoteResNums($res_num, $name, $msg);
    }

    // }}}
    // {{{ _checkQuoteResNums()

    /**
     * HTMLメッセージ中の引用レスの番号を再帰チェックする
     */
    protected function _checkQuoteResNums($res_num, $name, $msg)
    {
        // 再帰リミッタ
        if ($this->_quote_check_depth > 30) {
            return array();
        } else {
            $this->_quote_check_depth++;
        }

        if (array_key_exists($res_num, $this->_quote_res_nums)) {
            return $this->_quote_res_nums[$res_num];
        }

        $aThread = $this->thread;

        $quote_res_nums = array();

        $name = preg_replace('/(◆.*)/', '', $name, 1);

        // 名前
        if ($matches = $this->getQuoteResNumsName($name)) {
            foreach ($matches as $a_quote_res_num) {
                if ($a_quote_res_num) {
                    $quote_res_nums[] = $a_quote_res_num;
                    $a_quote_res_idx = $a_quote_res_num - 1;

                    // 自分自身の番号と同一でなければ、
                    if ($a_quote_res_num != $res_num) {
                        // チェックしていない番号を再帰チェック
                        if (!isset($this->_quote_res_nums_checked[$a_quote_res_num])) {
                            $this->_quote_res_nums_checked[$a_quote_res_num] = true;
                            if (isset($aThread->datlines[$a_quote_res_idx])) {
                                $datalinear = $aThread->explodeDatLine($aThread->datlines[$a_quote_res_idx]);
                                $quote_name = $datalinear[0];
                                $quote_msg = $aThread->datlines[$a_quote_res_idx];
                                $quote_res_nums = array_merge($quote_res_nums,
                                                              $this->_checkQuoteResNums($a_quote_res_num,
                                                                                        $quote_name,
                                                                                        $quote_msg));
                            }
                         }
                     }
                }
                // $name=preg_replace("/([0-9]+)/", "", $name, 1);
            }
        }

        // >>1のリンクをいったん外す
        // <a href="../test/read.cgi/accuse/1001506967/1" target="_blank">&gt;&gt;1</a>
        $msg = preg_replace('{<[Aa] .+?>(&gt;&gt;[1-9][\\d\\-]*)</[Aa]>}', '$1', $msg);

        //echo $msg;
        if (preg_match_all(self::getAnchorRegex('/%full%/'), $msg, $out, PREG_PATTERN_ORDER)) {
            foreach ($out[2] as $numberq) {
                if ($matches=preg_split(self::getAnchorRegex('/%delimiter%/'), $numberq)) {
                    foreach ($matches as $a_quote_res_num) {
                        if (preg_match(self::getAnchorRegex('/%range_delimiter%/'),$a_quote_res_num)) { continue;}
                        $a_quote_res_num = (int) (mb_convert_kana($a_quote_res_num, 'n'));
                        $a_quote_res_idx = $a_quote_res_num - 1;

                        //echo $a_quote_res_num;

                        if (!$a_quote_res_num) {break;}
                        $quote_res_nums[] = $a_quote_res_num;

                        // 自分自身の番号と同一でなければ、
                        if ($a_quote_res_num != $res_num) {
                            // チェックしていない番号を再帰チェック
                            if (!isset($this->_quote_res_nums_checked[$a_quote_res_num])) {
                                $this->_quote_res_nums_checked[$a_quote_res_num] = true;
                                if (isset($aThread->datlines[$a_quote_res_idx])) {
                                    $datalinear = $aThread->explodeDatLine($aThread->datlines[$a_quote_res_idx]);
                                    $quote_name = $datalinear[0];
                                    $quote_msg = $aThread->datlines[$a_quote_res_idx];
                                    $quote_res_nums = array_merge($quote_res_nums,
                                                                  $this->_checkQuoteResNums($a_quote_res_num,
                                                                                            $quote_name,
                                                                                            $quote_msg));
                                }
                             }
                         }

                     }

                }

            }

        }

        if (count($quote_res_nums)) {
            sort($quote_res_nums, SORT_NUMERIC);
            $this->_quote_res_nums[$res_num] = array_unique($quote_res_nums);
            $quote_res_nums = $this->_quote_res_nums[$res_num];
        }

        return $quote_res_nums;
    }

    // }}}
    // {{{ checkQuoteResNumsFromSummary()

    /**
     * 引用レス集計結果からポップアップ用に用意すべき番号を再帰チェックする
     */
    public function checkQuoteResNumsFromSummary($res_num, $with_quotes, $with_backlinks)
    {
        // 再帰リミッタ
        if ($this->_quote_check_depth > 3000) {
            return array();
        } else {
            $this->_quote_check_depth++;
        }

        $ret = array();

        // 参照レス
        if ($with_quotes) {
            $ret = array_merge($ret,
                $this->_checkQuoteResNumsFromSummary(
                    $res_num, $this->getQuoteTo(), $with_quotes, $with_backlinks));
        }
        // 被参照レス
        if ($with_backlinks) {
            $ret = array_merge($ret,
                $this->_checkQuoteResNumsFromSummary(
                    $res_num, $this->getQuoteFrom(), $with_quotes, $with_backlinks));
        }
        return $ret;
    }

    // }}}
    // {{{ _checkQuoteResNumsFromSummary()

    protected function _checkQuoteResNumsFromSummary($res_num, $quotes, $with_quotes, $with_backlinks)
    {
        $ret = array();
        if (array_key_exists($res_num, $quotes)) {
            foreach ($quotes[$res_num] as $quote_num) {
                $ret[] = $quote_num;
                if ($quote_num != $res_num) {
                    if (!isset($this->_quote_res_nums_checked[$quote_num])) {
                        $this->_quote_res_nums_checked[$quote_num] = true;
                        $ret = array_merge($ret,
                            $this->checkQuoteResNumsFromSummary($quote_num, $with_quotes, $with_backlinks));
                    }
                }
            }
        }
        return $ret;
    }

    // }}}
    // {{{ getQuoteResNumsName()

    public function getQuoteResNumsName($name)
    {
        if (strlen(trim($name)) == 0 || $name == $this->_nanashiName) {
            return false;
        }

        // HTMLタグを除去
        $name = strip_tags(trim($name));

        // デフォルトの名無し名を除去
        if (isset($this->_nanashiName)) {
            $name = str_replace($this->_nanashiName, "", $name);
        }

        /*
         * ワッチョイを除去
         * (ﾜｯﾁｮｲW a897-sa51)
         * (ｽｯｯﾌﾟ Ad38-g1G2 [11.11.11.11 [上級国民]])
         */
        $name = preg_replace('/\(.+ .{4}-.{4}( \[.+])?\)/', '', $name, 1);

        // トリップを除去
        $name = preg_replace('/◆.*/', '', $name, 1);

        if (strlen($name) == 0) {
            return false;
        }

        /*
        //if (preg_match('/[0-9]+/', $name, $m)) {
             return (int)$m[0];
        }
         */

        if (preg_match_all(self::getAnchorRegex('/(?:^|%prefix%)(%nums%)/'), $name, $matches)) {
            foreach ($matches[1] as $a_quote_res_num) {
                $quote_res_nums[] = (int)mb_convert_kana($a_quote_res_num, 'n');
            }
            return array_unique($quote_res_nums);
        }

        return false;
    }

    // }}}
    // {{{ _wikipediaFilter()

    /**
     * [[語句]]があった時にWikipediaへ自動リンク
     *
     * @param   string  $msg            メッセージ
     * @return  string
     *
     * original code:
     *  http://akid.s17.xrea.com/p2puki/index.phtml?%A5%E6%A1%BC%A5%B6%A1%BC%A5%AB%A5%B9%A5%BF%A5%DE%A5%A4%A5%BA%28rep2%20Ver%201.7.0%A1%C1%29#led2c85d
     */
    protected function _wikipediaFilter($msg)
    {
        if (strpos($msg, '[[') === false) {
            return $msg;
        }

        $msg = preg_replace_callback('/\\[\\[([^\\[\\]\\n<>]+)\\]\\]+/u',
                                     array($this, '_linkToWikipeidaCallback'),
                                     mb_convert_encoding($msg, 'UTF-8', 'CP932'));

        return mb_convert_encoding($msg, 'CP932', 'UTF-8');
    }

    // }}}
    // {{{ _linkToWikipeidaCallback()

    /**
     * Wikipediaの語句をリンクに変換して返す.
     *
     * @param   array  $matches
     * @return  string
     */
    protected function _linkToWikipeidaCallback($matches)
    {
        return '[[' . $this->_linkToWikipeida($matches[1]) . ']]';
    }

    // }}}
    // {{{ _linkToWikipeida()

    /**
     * Wikipediaの語句をリンクに変換して返す.
     *
     * @param   string  $word   語句
     * @return  string
     */
    abstract protected function _linkToWikipeida($word);

    // }}}
    // {{{ _makeQuotes()

    /**
     * レスデータを集計して$this->_quote_toと$this->_quote_fromに保存.
     */
    protected function _makeQuotes()
    {
        global $_conf;

        $this->_quote_to = array();
        $this->_quote_from = array();

        if (!$this->thread->datlines) {
            return;
        }

        foreach ($this->thread->datlines as $num => $line) {
            list($name, $mail, $date_id, $msg) = $this->thread->explodeDatLine($line);

           // NGあぼーんチェック
            if (($id = $this->thread->ids[$num + 1]) !== null) {
                $date_id = str_replace($this->thread->idp[$num] . $id, 'ID:' . $id, $date_id);
            }
            $ng_type = $this->_ngAbornCheck($num + 1, strip_tags($name), $mail, $date_id, $id, $msg);
            if ($ng_type == self::ABORN) {
                continue;
            }

            // 名前
            if ($nmatches = $this->getQuoteResNumsName($name)) {
                foreach ($nmatches as $a_quote_res_num) {
                    if ($a_quote_res_num) {
                        if (!array_key_exists($a_quote_res_num, $this->_quote_from) || $this->_quote_from[$a_quote_res_num] === null) {
                            $this->_quote_from[$a_quote_res_num] = array();
                        }
                        if (!in_array($num + 1, $this->_quote_from[$a_quote_res_num])) {
                            $this->_quote_from[$a_quote_res_num][] = $num + 1;
                        }

                        if (!array_key_exists($num + 1, $this->_quote_to) || $this->_quote_to[$num + 1] === null) {
                            $this->_quote_to[$num + 1] = array();
                        }
                        if (!in_array($a_quote_res_num, $this->_quote_to[$num + 1])) {
                            $this->_quote_to[$num + 1][] = $a_quote_res_num;
                        }
                    }
                }
            }


            // >>1のリンクをいったん外す
            // <a href="../test/read.cgi/accuse/1001506967/1" target="_blank">&gt;&gt;1</a>
            $msg = preg_replace('{<[Aa] .+?>(&gt;&gt;[1-9][\\d\\-]*)</[Aa]>}', '$1', $msg);
            if (!preg_match_all(self::getAnchorRegex('/%full%/'), $msg, $out, PREG_PATTERN_ORDER)) {
                continue;
            }
            foreach ($out[2] as $numberq) {
                if (!preg_match_all(self::getAnchorRegex('/(?:%prefix%)?(%a_range%)/'), $numberq, $anchors, PREG_PATTERN_ORDER)) continue;
                foreach ($anchors[1] as $anchor) {
                    if (preg_match(self::getAnchorRegex('/(%a_num%)%range_delimiter%(?:%prefix%)?(%a_num%)/'), $anchor, $matches)) {
                        $from = intval(mb_convert_kana($matches[1], 'n'));
                        $to = intval(mb_convert_kana($matches[2], 'n'));
                        if ($from < 1 || $to < 1 || $from > $to
                            || ($to - $from + 1) > sizeof($this->thread->datlines)) {
                            continue;
                        }
                        if ($_conf['backlink_list_range_anchor_limit'] != 0) {
                            if ($to - $from >= $_conf['backlink_list_range_anchor_limit']) {
                                continue;
                            }
                        }
                        for ($i = $from; $i <= $to; $i++) {
                            if ($i > sizeof($this->thread->datlines)) {
                                break;
                            }
                            if ($_conf['backlink_list_future_anchor'] == 0) {
                                // レス番号以降のアンカーは無視する
                                if ($i >= $num + 1) {
                                    continue;
                                }
                            }
                            if (!array_key_exists($i, $this->_quote_from) || $this->_quote_from[$i] === null) {
                                $this->_quote_from[$i] = array();
                            }
                            if (!in_array($num + 1, $this->_quote_from[$i])) {
                                $this->_quote_from[$i][] = $num + 1;
                            }
                        }
                    } elseif (preg_match(self::getAnchorRegex('/(%a_num%)/'), $anchor, $matches)) {
                        $quote_num = intval(mb_convert_kana($matches[1], 'n'));
                        if (!array_key_exists($num + 1, $this->_quote_to) || $this->_quote_to[$num + 1] === null) {
                            $this->_quote_to[$num + 1] = array();
                        }
                        if (!in_array($quote_num, $this->_quote_to[$num + 1])) {
                            $this->_quote_to[$num + 1][] = $quote_num;
                        }

                        if ($_conf['backlink_list_future_anchor'] == 0) {
                            // レス番号以降のアンカーは無視する
                            if ($quote_num >= $num + 1) {
                                continue;
                            }
                        }
                        if (!array_key_exists($quote_num, $this->_quote_from) || $this->_quote_from[$quote_num] === null) {
                            $this->_quote_from[$quote_num] = array();
                        }
                        if (!in_array($num + 1, $this->_quote_from[$quote_num])) {
                            $this->_quote_from[$quote_num][] = $num + 1;
                        }
                    }
                }
            }
        }
    }

    // }}}
    // {{{ getQuoteFrom()

    /**
     * 被レスリストを返す.
     *
     * @return  array
     */
    public function getQuoteFrom()
    {
        if ($this->_quote_from === null) {
            $this->_makeQuotes();  // 被レスデータ集計
        }
        return $this->_quote_from;
    }

    // }}}
    // {{{ getQuoteTo()

    /**
     * レスリストを返す.
     *
     * @return  array
     */
    public function getQuoteTo()
    {
        if ($this->_quote_to === null) {
            $this->_makeQuotes();  // レスデータ集計
        }
        return $this->_quote_to;
    }

    // }}}
    // {{{ _quotebackListHtml()

    /**
     * 被レスリストをHTMLで整形して返す.
     *
     * @param   int     $resnum レス番号
     * @param   int     $type   1:縦形式 2:横形式 3:展開用ブロック用文字列
     * @param   bool    $popup  横形式でのポップアップ処理(true:ポップアップする、false:挿入する)
     * @return  string
     */
    protected function _quotebackListHtml($resnum, $type, $popup=true)
    {
        $quote_from = $this->getQuoteFrom();
        if (!array_key_exists($resnum, $quote_from)) return '';

        $anchors = $quote_from[$resnum];
        sort($anchors);

        if ($type == 1) {
            return $this->_quotebackVerticalListHtml($anchors, $resnum);
        } elseif ($type == 2) {
            return $this->_quotebackHorizontalListHtml($anchors, $resnum);
        } elseif ($type == 3) {
            return $this->_quotebackResData($anchors, $resnum);
        }
    }

    // }}}
    // {{{ _quotebackVerticalListHtml()

    protected function _quotebackVerticalListHtml($anchors, $resnum)
    {
        $ret = '<div class="v_reslist"><ul>';
        $anchor_cnt = 1;
        foreach ($anchors as $anchor) {
            if ($anchor_cnt > 1) {
                $ret .= '<li>│</li>';
            }
            if ($anchor_cnt < count($anchors)) {
                $ret .= '<li>├';
            } else {
                $ret .= '<li>└';
            }
            $ret .= ($anchor == $resnum)
                 ? $anchor
                 : $this->quoteRes($anchor, '', $anchor, true);
            $anchor_cnt++;
        }
        $ret .= '</ul></div>';
        return $ret;
    }

    // }}}
    // {{{ _quotebackHorizontalListHtml()

    protected function _quotebackHorizontalListHtml($anchors, $resnum)
    {
        $ret = '<div class="reslist">';
        $count = 0;

        foreach ($anchors as $idx => $anchor) {
            if ($anchor == $resnum) {
                continue;
            }
            $anchor_link= $this->quoteRes('>>' . $anchor, '>>', $anchor);
            $qres_id = ($this->_matome ? "t{$this->_matome}" : '') ."qr{$anchor}";
            $ret .= '<div class="reslist_inner">';
            $ret .= sprintf('<div>【参照レス：%s】</div>',$anchor_link);
            $ret .= '</div>';
            $count++;
        }
        $ret .= '</div>';

        return $ret;
    }

    // }}}
    // {{{ _quotebackResData()

    protected function _quotebackResData($anchors, $resnum)
    {
        $ret = array();
        foreach ($anchors as $idx => $anchor) {
            if ($anchor == $resnum) {
                continue;
            }
            $ret[] = ($this->_matome ? "t{$this->_matome}" : '') ."qr{$anchor}";
        }

        return join('/', $ret);
    }

    // }}}
    // {{{ getDatochiResiduums()

    /**
     * DAT落ちの際に取得できた>>1と最後のレスをHTMLで返す.
     *
     * @return  string|false
     */
    public function getDatochiResiduums()
    {
        $ret = '';
        $elines = $this->thread->datochi_residuums;
        if (!count($elines)) {
            return $ret;
        }

        $this->thread->onthefly = true;
        $ret = "<div><span class=\"onthefly\">on the fly</span></div>\n";
        $ret .= "<div class=\"thread\">\n";

        foreach($elines as $num => $line) {
            $res = $this->transRes($line, $num);
            if (is_array($res)) {
                $ret .= $res['body'] . $res['q'];
            } else {
                $ret .= $res;
            }
        }

        $ret .= "</div>\n";

        return $ret;
    }

    // }}}
    // {{{ getAutoFavRanks()

    /**
     * 自動ランク設定を返す.
     *
     * @return  array
     */
    public function getAutoFavRank()
    {
        global $_conf;

        if ($this->_auto_fav_rank !== false) {
            return $this->_auto_fav_rank;
        }

        $ranks = explode(',', strtr($_conf['expack.ic2.fav_auto_rank_setting'], ' ', ''));
        $ret = null;
        if ($_conf['expack.misc.multi_favs']) {
            $idx = 0;
            if (!is_array($this->thread->favs)) {
                return null;
            }
            foreach ($this->thread->favs as $fav) {
                if ($fav) {
                    $rank = $ranks[$idx];
                    if (is_numeric($rank)) {
                        $rank = intval($rank);
                        if ($ret === null) {
                            $ret = $rank;
                        } else {
                            $ret = max($ret, $rank);
                        }
                    }
                }
                $idx++;
            }
        } else {
            if ($this->thread->fav && is_numeric($ranks[0])) {
                $ret = intval($ranks[0]);
            }
        }
        $this->_auto_fav_rank = $ret;

        return $ret;
    }

    // }}}
    // {{{ isAutoFavRankOverride()

    /**
     * 自動ランク設定でランクを上書きすべきか返す.
     *
     * @param   int $now    現在のランク
     * @param   int $new    自動ランク
     * @return  bool
     */
    static public function isAutoFavRankOverride($now, $new)
    {
        global $_conf;

        switch ($_conf['expack.ic2.fav_auto_rank_override']) {
        case 0:
            return false;
            break;
        case 1:
            return $now != $new;
            break;
        case 2:
            return $now == 0 && $now != $new;
            break;
        case 3:
            return $now < $new;
            break;
        default:
            return false;
        }
        return false;
    }

    // }}}
    // {{{ getAnchorRegex()

    /**
     * アンカーの正規表現を返す
     *
     * @param   string  $pattern  ex)'/%full%/'
     * @param   boolean $unicode
     * @return  string
     */
    static public function getAnchorRegex($pattern, $unicode = false)
    {
        if (!array_key_exists($pattern, self::$_anchorRegexes)) {
            self::$_anchorRegexes[$pattern] = strtr($pattern, self::_getAnchorRegexParts());
            // 大差はないが compileMobile2chUriCallBack() のように preg_relplace_callback()してもいいかも。
        }
        if ($unicode) {
            return StrSjis::toUnicodePattern(self::$_anchorRegexes[$pattern]);
        }
        return self::$_anchorRegexes[$pattern];
    }

    // }}}
    // {{{ _getAnchorRegexParts()

    /**
     * アンカーの構成要素（正規表現パーツの配列）を返す
     *
     * @param   void
     * @return  string
     */
    static private function _getAnchorRegexParts()
    {
        if (!is_null(self::$_anchorRegexParts)) {
            return self::$_anchorRegexParts;
        }

        $anchor = array();

        // アンカーの構成要素（正規表現パーツの配列）

        // 空白文字
        $anchor_space = '(?:[ ]|　)';
        //$anchor[' '] = '';

        // アンカー引用子 >>
        $anchor['prefix'] = "(?:(?:&gt;|＞|&lt;|＜|〉){1,2}|(?:\)){2}|》|≫){$anchor_space}*\.?";

        // 数字
        $anchor['a_digit'] = '(?:\\d|０|１|２|３|４|５|６|７|８|９)';
        /*
        $anchor[0] = '(?:0|０)';
        $anchor[1] = '(?:1|１)';
        $anchor[2] = '(?:2|２)';
        $anchor[3] = '(?:3|３)';
        $anchor[4] = '(?:4|４)';
        $anchor[5] = '(?:5|５)';
        $anchor[6] = '(?:6|６)';
        $anchor[7] = '(?:7|７)';
        $anchor[8] = '(?:8|８)';
        $anchor[9] = '(?:9|９)';
        */

        // 範囲指定子
        // -|‐|ー = HYPHEN-MINUS | HYPHEN | KATAKANA-HIRAGANA PROLONGED SOUND MARK
        $anchor['range_delimiter'] = '(?:-|\\x81\\x5d|\\x81\\x5b)'; // [\\-\\x{2010}\\x{30fc}]

        // 列挙指定子
        $anchor['delimiter'] = "{$anchor_space}?(?:[\.,=+]|、|・|＝|，){$anchor_space}?";

        // あぼーん用アンカー引用子
        //$anchor['prefix_abon'] = "&gt;{1,2}{$anchor_space}?";

        // レス番号
        $anchor['a_num'] = sprintf('%s{1,4}', $anchor['a_digit']);

        // レス範囲
        /*
        $anchor['a_range'] = sprintf('%s(?:%s%s)?',
            $anchor['a_num'], $anchor['range_delimiter'], $anchor['a_num']
        );
        */
        $anchor['a_range'] = sprintf('%s(?:%s(?:%s)?%s)?',
            $anchor['a_num'], $anchor['range_delimiter'], $anchor['prefix'], $anchor['a_num']
        );

        // レス範囲の列挙
        $anchor['ranges'] = sprintf('%s(?:%s%s)*(?!%s)',
            $anchor['a_range'], $anchor['delimiter'], $anchor['a_range'], $anchor['a_digit']
        );

        // レス番号の列挙
        $anchor['nums'] = sprintf('%s(?:%s%s)*(?!%s)',
            $anchor['a_num'], $anchor['delimiter'], $anchor['a_num'], $anchor['a_digit']
        );

        // アンカー全体
        $anchor['full'] = sprintf('(%s)(%s)', $anchor['prefix'], $anchor['ranges']);

        // getAnchorRegex() の strtr() 置換用にkeyを '%key%' に変換する
        foreach ($anchor as $k => $v) {
            $anchor['%' . $k . '%'] = $v;
            unset($anchor[$k]);
        }

        self::$_anchorRegexParts = $anchor;

        return self::$_anchorRegexParts;
    }

    // }}}
    // {{{_buildStrToLinkRegex()

    /**
     * リンクとして扱うパターンを返す
     *
     * @param   void
     * @return  string
     */
    static protected function _buildStrToLinkRegex()
    {
        return '{'
            . '(?P<link>(<[Aa] .+?>)(.*?)(</[Aa]>))' // リンク（PCREの特性上、必ずこのパターンを最初に試行する）
            . '|'
            . '(?:'
            .   '(?P<quote>' // 引用
            .       self::getAnchorRegex('%full%')
            .   ')'
            . '|'
            .   '(?P<url>'
            .       '(ftp|h?ttps?|tps?)://([0-9A-Za-z][\\w!#%&+*,\\-./:;=?@\\[\\]^~]+)' // URL
            .   ')'
            . '|'
            .   '(?P<id>ID: ?([0-9A-Za-z/.+]{8,14})(?=[^0-9A-Za-z/.+]|$))' // ID（8,10桁 +PC/携帯識別フラグ）
            . ')'
            . '}';
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
