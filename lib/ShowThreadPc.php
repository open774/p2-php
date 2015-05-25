<?php
/**
 * rep2 - スレッドを表示する クラス PC用
 */

require_once P2EX_LIB_DIR . '/ExpackLoader.php';

ExpackLoader::loadAAS();
ExpackLoader::loadActiveMona();
ExpackLoader::loadImageCache();

// {{{ ShowThreadPc

class ShowThreadPc extends ShowThread
{
	var $BBS_NONAME_NAME = ''; // +live (live.bbs_noname) 用

    // {{{ properties

    static private $_spm_objects = array();

    public $am_autodetect = false; // AA自動判定をするか否か
    public $am_side_of_id = false; // AAスイッチをIDの横に表示する
    public $am_on_spm = false; // AAスイッチをSPMに表示する

    public $asyncObjName;  // 非同期読み込み用JavaScriptオブジェクト名
    public $spmObjName; // スマートポップアップメニュー用JavaScriptオブジェクト名

    private $_ids_for_render;   // 出力予定のID(重複のみ)のリスト(8桁)
    private $_idcount_average;  // ID重複数の平均値
    private $_idcount_tops;     // ID重複数のトップ入賞までの重複数値

    // }}}
    // {{{ constructor

    /**
     * コンストラクタ
     */
    public function __construct($aThread, $matome = false)
    {
        parent::__construct($aThread, $matome);

        global $_conf;

        $this->_url_handlers = array(
            'plugin_linkThread',
            'plugin_link2chSubject',
        );

		// +live (live.bbs_noname) 用
		if ($_GET['live']) {
			if (empty($_conf['live.bbs_noname'])) {
				require_once P2_LIB_DIR . '/SettingTxt.php';
				$st = new SettingTxt($this->thread->host, $this->thread->bbs);
				$st->setSettingArray();
				if (!empty($st->setting_array['BBS_NONAME_NAME'])) {
					$this->BBS_NONAME_NAME = $st->setting_array['BBS_NONAME_NAME'];
				}
			}

		}

        // +Wiki
        if (isset($GLOBALS['linkPluginCtl'])) {
            $this->_url_handlers[] = 'plugin_linkPlugin';
        }
        if (isset($GLOBALS['replaceImageUrlCtl'])) {
            $this->_url_handlers[] = 'plugin_replaceImageUrl';
        } elseif ($_conf['preview_thumbnail']) {
            $this->_url_handlers[] = 'plugin_viewImage';
        }
        $this->_url_handlers[] = 'plugin_linkURL';

        // サムネイル表示制限数を設定
        if (!isset($GLOBALS['pre_thumb_unlimited']) || !isset($GLOBALS['pre_thumb_limit'])) {
            if (isset($_conf['pre_thumb_limit']) && $_conf['pre_thumb_limit'] > 0) {
                $GLOBALS['pre_thumb_limit'] = $_conf['pre_thumb_limit'];
                $GLOBALS['pre_thumb_unlimited'] = false;
            } else {
                $GLOBALS['pre_thumb_limit'] = null; // ヌル値だとisset()はfalseを返す
                $GLOBALS['pre_thumb_unlimited'] = true;
            }
        }
        $GLOBALS['pre_thumb_ignore_limit'] = false;

        // アクティブモナー初期化
        if (P2_ACTIVEMONA_AVAILABLE) {
            ExpackLoader::initActiveMona($this);
        }

        // ImageCache2初期化
        if (P2_IMAGECACHE_AVAILABLE == 2) {
            ExpackLoader::initImageCache($this);
        }

        // 非同期レスポップアップ・SPM初期化
        $js_id = sprintf('%u', crc32($this->thread->keydat));
        if ($this->_matome) {
            $this->asyncObjName = "t{$this->_matome}asp{$js_id}";
            $this->spmObjName = "t{$this->_matome}spm{$js_id}";
        } else {
            $this->asyncObjName = "asp{$js_id}";
            $this->spmObjName = "spm{$js_id}";
        }

        // 名無し初期化
        $this->setBbsNonameName();
    }

    // }}}
    // {{{ transRes()

    /**
     * DatレスをHTMLレスに変換する
     *
     * @param   string  $ares       datの1ライン
     * @param   int     $i          レス番号
     * @param   string  $pattern    ハイライト用正規表現
     * @return  string
     */
    public function transRes($ares, $i, $pattern = null)
    {
        global $_conf, $STYLE, $mae_msg, $highlight_msgs, $highlight_chain_nums;

        // +Wiki:置換ワード
        if (isset($GLOBALS['replaceWordCtl'])) {
            $replaceWordCtl = $GLOBALS['replaceWordCtl'];
            $name    = $replaceWordCtl->replace('name', $this->thread, $ares, $i);
            $mail    = $replaceWordCtl->replace('mail', $this->thread, $ares, $i);
            $date_id = $replaceWordCtl->replace('date', $this->thread, $ares, $i);
            $msg     = $replaceWordCtl->replace('msg',  $this->thread, $ares, $i);
        } else {
            list($name, $mail, $date_id, $msg) = $this->thread->explodeDatLine($ares);
        }

        if (($id = $this->thread->ids[$i]) !== null) {
            $idstr = 'ID:' . $id;
            $date_id = str_replace($this->thread->idp[$i] . $id, $idstr, $date_id);
        } else {
            $idstr = null;
        }

		// +live (live.bbs_noname) 用
		if (!empty($this->BBS_NONAME_NAME) and $this->BBS_NONAME_NAME == $name) {
			$name = '';
		}

        $tores = '';
        $rpop = '';
        if ($this->_matome) {
            $res_id = "t{$this->_matome}r{$i}";
            $msg_id = "t{$this->_matome}m{$i}";
        } else {
            $res_id = "r{$i}";
            $msg_id = "m{$i}";
        }
        $msg_class = 'message';

        // NGあぼーんチェック
        $ng_type = $this->_ngAbornCheck($i, strip_tags($name), $mail, $date_id, $id, $msg, false, $ng_info);
        if ($ng_type == self::ABORN) {
            return $this->_abornedRes($res_id);
        }
        if ($ng_type != self::NG_NONE) {
            $ngaborns_head_hits = self::$_ngaborns_head_hits;
            $ngaborns_body_hits = self::$_ngaborns_body_hits;
        }

		// +live ハイライトチェック
		if ($ng_type != self::HIGHLIGHT_NONE) {
			$highlight_head_hits = self::$_highlight_head_hits;
			$highlight_body_hits = self::$_highlight_body_hits;
		}

        // AA判定
        if ($this->am_autodetect && $this->activeMona->detectAA($msg)) {
            $msg_class .= ' ActiveMona';
        }

        //=============================================================
        // レスをポップアップ表示
        //=============================================================
        if ($_conf['quote_res_view']) {
            $quote_res_nums = $this->checkQuoteResNums($i, $name, $msg);

            foreach ($quote_res_nums as $rnv) {
                if (!isset($this->_quote_res_nums_done[$rnv])) {
                    $this->_quote_res_nums_done[$rnv] = true;
                    if (isset($this->thread->datlines[$rnv-1])) {
                        if ($this->_matome) {
                            $qres_id = "t{$this->_matome}qr{$rnv}";
                        } else {
                            $qres_id = "qr{$rnv}";
                        }
                        $ds = $this->qRes($this->thread->datlines[$rnv-1], $rnv);
                        $onPopUp_at = " onmouseover=\"showResPopUp('{$qres_id}',event)\" onmouseout=\"hideResPopUp('{$qres_id}')\"";
                        $rpop .= "<div id=\"{$qres_id}\" class=\"respopup\"{$onPopUp_at}>\n{$ds}</div>\n";
                    }
                }
            }
        }

        //=============================================================
        // まとめて出力
        //=============================================================

        $name = $this->transName($name); // 名前HTML変換
        $msg = $this->transMsg($msg, $i); // メッセージHTML変換


        // BEプロファイルリンク変換
        $date_id = $this->replaceBeId($date_id, $i);

        // BE＆絵文字アイコンリンク変換
        $msg = $this->replaceSsspIcon($msg);

        // HTMLポップアップ
        if ($_conf['iframe_popup']) {
            $date_id = preg_replace_callback("{<a href=\"(http://[-_.!~*()0-9A-Za-z;/?:@&=+\$,%#]+)\"({$_conf['ext_win_target_at']})>((\?#*)|(Lv\.\d+))</a>}", array($this, 'iframePopupCallback'), $date_id);
        }

        // NGメッセージ変換
        if ($ng_type != self::NG_NONE && count($ng_info)) {
            $ng_info = implode(', ', $ng_info);
            $msg = <<<EOMSG
<span class="ngword" onclick="show_ng_message('ngm{$ngaborns_body_hits}', this);">{$ng_info}</span>
<div id="ngm{$ngaborns_body_hits}" class="ngmsg ngmsg-by-msg">{$msg}</div>
EOMSG;
        }

        // NGネーム変換
        if ($ng_type & self::NG_NAME) {
            $name = <<<EONAME
<span class="ngword" onclick="show_ng_message('ngn{$ngaborns_head_hits}', this);">{$name}</span>
EONAME;
            $msg = <<<EOMSG
<div id="ngn{$ngaborns_head_hits}" class="ngmsg ngmsg-by-name">{$msg}</div>
EOMSG;

        // NGメール変換
        } elseif ($ng_type & self::NG_MAIL) {
            $mail = <<<EOMAIL
<span class="ngword" onclick="show_ng_message('ngn{$ngaborns_head_hits}', this);">{$mail}</span>
EOMAIL;
            $msg = <<<EOMSG
<div id="ngn{$ngaborns_head_hits}" class="ngmsg ngmsg-by-mail">{$msg}</div>
EOMSG;

        // NGID変換
        } elseif ($ng_type & self::NG_ID) {
            $date_id = <<<EOID
<span class="ngword" onclick="show_ng_message('ngn{$ngaborns_head_hits}', this);">{$date_id}</span>
EOID;
            $msg = <<<EOMSG
<div id="ngn{$ngaborns_head_hits}" class="ngmsg ngmsg-by-id">{$msg}</div>
EOMSG;

        }

		// +live ハイライトワード変換
		include P2_LIB_DIR . '/live/live_highlight_convert.php';

        /*
        //「ここから新着」画像を挿入
        if ($i == $this->thread->readnum +1) {
            $tores .= <<<EOP
                <div><img src="img/image.png" alt="新着レス" border="0" vspace="4"></div>
EOP;
        }
        */

        // SPM
        if ($_conf['expack.spm.enabled']) {
            $spmeh = " onmouseover=\"{$this->spmObjName}.show({$i},'{$msg_id}',event)\"";
            $spmeh .= " onmouseout=\"{$this->spmObjName}.hide(event)\"";
        } else {
            $spmeh = '';
        }

		// +live スレッド内容表示切替
		include P2_LIB_DIR . '/live/live_view_ctl.inc.php';

        /*if ($_conf['expack.am.enabled'] == 2) {
            $tores .= <<<EOJS
<script type="text/javascript">
//<![CDATA[
detectAA("{$msg_id}");
//]]>
</script>\n
EOJS;
        }*/

        // まとめてフィルタ色分け
        if ($pattern) {
            $tores = StrCtl::filterMarking($pattern, $tores);
        }

        return array('body' => $tores, 'q' => $rpop);
    }

    // }}}
    // {{{ quoteOne()

    /**
     * >>1 を表示する (引用ポップアップ用)
     */
    public function quoteOne()
    {
        global $_conf;

        if (!$_conf['quote_res_view']) {
            return false;
        }

        $rpop = '';
        $quote_res_nums = $this->checkQuoteResNums(0, '1', '');
        if (array_search(1, $quote_res_nums) === false) {
            $quote_res_nums[] = 1;
        }

        foreach ($quote_res_nums as $rnv) {
            if (!isset($this->_quote_res_nums_done[$rnv])) {
                $this->_quote_res_nums_done[$rnv] = true;
                if (isset($this->thread->datlines[$rnv-1])) {
                    if ($this->_matome) {
                        $qres_id = "t{$this->_matome}qr{$rnv}";
                    } else {
                        $qres_id = "qr{$rnv}";
                    }
                    $ds = $this->qRes($this->thread->datlines[$rnv-1], $rnv);
                    $onPopUp_at = " onmouseover=\"showResPopUp('{$qres_id}',event)\" onmouseout=\"hideResPopUp('{$qres_id}')\"";
                    $rpop .= "<div id=\"{$qres_id}\" class=\"respopup\"{$onPopUp_at}>\n{$ds}</div>\n";
                }
            }
        }

        $res1['q'] = $rpop;
        $res1['body'] = $this->transMsg('&gt;&gt;1', 1);

        return $res1;
    }

    // }}}
    // {{{ qRes()

    /**
     * レス引用HTML
     */
    public function qRes($ares, $i)
    {
        global $_conf;

        // +Wiki:置換ワード
        if (isset($GLOBALS['replaceWordCtl'])) {
            $replaceWordCtl = $GLOBALS['replaceWordCtl'];
            $name    = $replaceWordCtl->replace('name', $this->thread, $ares, $i);
            $mail    = $replaceWordCtl->replace('mail', $this->thread, $ares, $i);
            $date_id = $replaceWordCtl->replace('date', $this->thread, $ares, $i);
            $msg     = $replaceWordCtl->replace('msg',  $this->thread, $ares, $i);
        } else {
            list($name, $mail, $date_id, $msg) = $this->thread->explodeDatLine($ares);
        }

        if (($id = $this->thread->ids[$i]) !== null) {
            $idstr = 'ID:' . $id;
            $date_id = str_replace($this->thread->idp[$i] . $id, $idstr, $date_id);
        } else {
            $idstr = null;
        }

        $name = $this->transName($name); // 名前HTML変換
        $msg = $this->transMsg($msg, $i); // メッセージHTML変換

        $tores = '';

        if ($this->_matome) {
            $qmsg_id = "t{$this->_matome}qm{$i}";
        } else {
            $qmsg_id = "qm{$i}";
        }

        // >>1
        if ($i == 1) {
            $tores = "<h4 class=\"thread_title\">{$this->thread->ttitle_hd}</h4>";
        }

        // BEプロファイルリンク変換
        $date_id = $this->replaceBeId($date_id, $i);

        // BE＆絵文字アイコンリンク変換
        $msg = $this->replaceSsspIcon($msg);

        // HTMLポップアップ
        if ($_conf['iframe_popup']) {
            $date_id = preg_replace_callback("{<a href=\"(http://[-_.!~*()0-9A-Za-z;/?:@&=+\$,%#]+)\"({$_conf['ext_win_target_at']})>((\?#*)|(Lv\.\d+))</a>}", array($this, 'iframePopupCallback'), $date_id);
        }
        //

        // IDフィルタ
        if ($_conf['flex_idpopup'] == 1 && $id && $this->thread->idcount[$id] > 1) {
            $date_id = str_replace($idstr, $this->idFilter($idstr, $id), $date_id);
        }

        $msg_class = 'message';

        // AA 判定
        if ($this->am_autodetect && $this->activeMona->detectAA($msg)) {
            $msg_class .= ' ActiveMona';
        }

        // SPM
        if ($_conf['expack.spm.enabled']) {
            $spmeh = " onmouseover=\"{$this->spmObjName}.show({$i},'{$qmsg_id}',event)\"";
            $spmeh .= " onmouseout=\"{$this->spmObjName}.hide(event)\"";
        } else {
            $spmeh = '';
        }

        // $toresにまとめて出力
        $tores .= '<div class="res-header">';
        $tores .= "<span class=\"spmSW\"{$spmeh}>{$i}</span> : "; // 番号
        $tores .= preg_replace('{<b>[ ]*</b>}i', '', "<b class=\"name\">{$name}</b> : ");
        if ($mail) {
            // メール
			if (preg_match ("(^[\\s　]*sage[\\s　]*$)", $mail)) {
				$tores .= "<span class=\"sage\">$mail</span>"." ：";
			} else {
				$tores .= "<span class=\"mail\">$mail</span>"." ：";
			}
        }
        $tores .= $date_id; // 日付とID
        if ($this->am_side_of_id) {
            $tores .= ' ' . $this->activeMona->getMona($qmsg_id);
        }
        $tores .= "</div>\n";

        // 被レスリスト(縦形式)
        if ($_conf['backlink_list'] == 1 || $_conf['backlink_list'] > 2) {
            $tores .= $this->_quotebackListHtml($i, 1);
        }

        $tores .= "<div id=\"{$qmsg_id}\" class=\"{$msg_class}\">{$msg}</div>\n"; // 内容
        // 被レスリスト(横形式)
        if ($_conf['backlink_list'] == 2 || $_conf['backlink_list'] > 2) {
            $tores .= $this->_quotebackListHtml($i, 2);
        }

        // 被参照ブロック用データ
        if ($_conf['backlink_block'] > 0) {
            $tores .= $this->_getBacklinkComment($i);
        }

        return $tores;
    }

    // }}}
    // {{{ _getBacklinkComment()

    protected function _getBacklinkComment($i)
    {
        $backlinks = $this->_quotebackListHtml($i, 3);
        if (strlen($backlinks)) {
            return '<!-- backlinks:' . $backlinks . ' -->';
        }
        return '';
    }

    // }}}
    // {{{ transName()

    /**
     * 名前をHTML用に変換する
     *
     * @param   string  $name   名前
     * @return  string
     */
    public function transName($name)
    {
        global $_conf;

        // トリップやホスト付きなら分解する
        if (($pos = strpos($name, '◆')) !== false) {
            $trip = substr($name, $pos);
            $name = substr($name, 0, $pos);
        } else {
            $trip = null;
        }

        // 数字を引用レスポップアップリンク化
        if ($_conf['quote_res_view']) {
            if (strlen($name) && $name != $this->_nanashiName) {
                $name = preg_replace_callback(
                    self::getAnchorRegex('/(?:^|%prefix%)(%nums%)/'),
                    array($this, '_quoteNameCallback'), $name
                );
            }
        }

        if ($trip) {
            $name .= $trip;
        } elseif ($name) {
            // 文字化け回避
            $name = $name . ' ';
            //if (in_array(0xF0 & ord(substr($name, -1)), array(0x80, 0x90, 0xE0))) {
            //    $name .= ' ';
            //}
        }

        return $name;
    }

    // }}}
    // {{{ transMsg()

    /**
     * datのレスメッセージをHTML表示用メッセージに変換する
     *
     * @param   string  $msg    メッセージ
     * @param   int     $mynum  レス番号
     * @return  string
     */
    public function transMsg($msg, $mynum)
    {
        global $_conf;
        global $pre_thumb_ignore_limit;

        // 2ch旧形式のdat
        if ($this->thread->dat_type == '2ch_old') {
            $msg = str_replace('＠｀', ',', $msg);
            $msg = preg_replace('/&amp(?=[^;])/', '&', $msg);
        }

        // &補正
        $msg = preg_replace('/&(?!#?\\w+;)/', '&amp;', $msg);

        // Safariから投稿されたリンク中チルダの文字化け補正
        //$msg = preg_replace('{(h?t?tp://[\w\.\-]+/)～([\w\.\-%]+/?)}', '$1~$2', $msg);

        // >>1のリンクをいったん外す
        // <a href="../test/read.cgi/accuse/1001506967/1" target="_blank">&gt;&gt;1</a>
        $msg = preg_replace('{<[Aa] .+?>(&gt;&gt;\\d[\\d\\-]*)</[Aa]>}', '$1', $msg);

        // 本来は2chのDAT時点でなされていないとエスケープの整合性が取れない気がする。（URLリンクのマッチで副作用が出てしまう）
        //$msg = str_replace(array('"', "'"), array('&quot;', '&#039;'), $msg);

        // 2006/05/06 ノートンの誤反応対策 body onload=window()
        $msg = str_replace('onload=window()', '<i>onload=window</i>()', $msg);

        // 新着レスの画像は表示制限を無視する設定なら
        if ($mynum > $this->thread->readnum && $_conf['expack.ic2.newres_ignore_limit']) {
            $pre_thumb_ignore_limit = true;
        }

        // 文末の改行と連続する改行を除去
        if ($_conf['strip_linebreaks']) {
            $msg = $this->stripLineBreaks($msg /*, ' <br><span class="stripped">***</span><br> '*/);
        }

        // 引用やURLなどをリンク
        $msg = $this->transLink($msg);

        // Wikipedia記法への自動リンク
        if ($_conf['_linkToWikipeida']) {
            $msg = $this->_wikipediaFilter($msg);
        }

        return $msg;
    }

    // }}}
    // {{{ _abornedRes()

    /**
     * あぼーんレスのHTMLを取得する
     *
     * @param  string $res_id
     * @return string
     */
    protected function _abornedRes($res_id)
    {
        global $_conf;

        if ($_conf['ngaborn_purge_aborn']) {
            return '';
        }

        return <<<EOP
<div id="{$res_id}" class="res aborned">
<div class="res-header">&nbsp;</div>
<div class="message">&nbsp;</div>
</div>\n
EOP;
    }

    // }}}
    // {{{ idFilter()

    /**
     * IDフィルタリングポップアップ変換
     *
     * @param   string  $idstr  ID:xxxxxxxxxx
     * @param   string  $id        xxxxxxxxxx
     * @return  string
     */
    public function idFilter($idstr, $id)
    {
        global $_conf;

        // IDは8桁または10桁(+携帯/PC識別子)と仮定して
        /*
        if (strlen($id) % 2 == 1) {
            $id = substr($id, 0, -1);
        }
        */
        $num_ht = '';
        if (isset($this->thread->idcount[$id]) && $this->thread->idcount[$id] > 0) {
            $num = (string) $this->thread->idcount[$id];
            if ($_conf['iframe_popup'] == 3) {
                $num_ht = ' <img src="img/ida.png" width="2" height="12" alt="">';
                $num_ht .= preg_replace('/\\d/', '<img src="img/id\\0.png" height="12" alt="">', $num);
                $num_ht .= '<img src="img/idz.png" width="2" height="12" alt=""> ';
            } else {
                $num_ht = '('.$num.')';
            }
        } else {
            return $idstr;
        }

        if ($_conf['coloredid.enable'] > 0 && preg_match("|^ID: ?[0-9A-Za-z/.+]+|",$idstr)) {
            if ($this->_ids_for_render === null) {
                $this->_ids_for_render = array();
            }
            $this->_ids_for_render[substr($id, 0, 8)] = $this->thread->idcount[$id];
            if ($_conf['coloredid.click'] > 0) {
                $num_ht = '<a href="javascript:void(0);" class="' . self::cssClassedId($id) . '" onClick="idCol.click(\'' . substr($id, 0, 8) . '\', event); return false;" onDblClick="this.onclick(event); return false;">' . $num_ht . '</a>';
            }
            $idstr = $this->_coloredIdStr(
                $idstr, $id, $_conf['coloredid.click'] > 0 ? true : false);
        }

        $filter_url = $_conf['read_php'] . '?' . http_build_query(array(
            'host' => $this->thread->host,
            'bbs'  => $this->thread->bbs,
            'key'  => $this->thread->key,
            'ls'   => 'all',
            'offline' => '1',
            'idpopup' => '1',
            'rf' => array(
                'field'   => ResFilter::FIELD_ID,
                'method'  => ResFilter::METHOD_JUST,
                'match'   => ResFilter::MATCH_ON,
                'include' => ResFilter::INCLUDE_NONE,
                'word'    => $id,
            ),
        ), '', '&amp;') . $_conf['k_at_a'];

        if ($_conf['iframe_popup']) {
            return self::iframePopup($filter_url, $idstr, $_conf['bbs_win_target_at']) . $num_ht;
        }
        return "<a href=\"{$filter_url}\"{$_conf['bbs_win_target_at']}>{$idstr}</a>{$num_ht}";
    }

    // }}}
    // {{{ _linkToWikipeida()

    /**
     * @see ShowThread
     */
    protected function _linkToWikipeida($word)
    {
        global $_conf;

        $link = 'http://ja.wikipedia.org/wiki/' . rawurlencode($word);
        if ($_conf['through_ime']) {
            $link = P2Util::throughIme($link);
        }

        return "<a href=\"{$link}\"{$_conf['ext_win_target_at']}>{$word}</a>";
    }

    // }}}
    // {{{ quoteRes()

    /**
     * 引用変換（単独）
     *
     * @param   string  $full           >>1-100
     * @param   string  $qsign          >>
     * @param   string  $appointed_num    1-100
     * @param   bool    $anchor_jump
     * @return  string
     */
    public function quoteRes($full, $qsign, $appointed_num, $anchor_jump = false)
    {
        global $_conf;

        $appointed_num = mb_convert_kana($appointed_num, 'n');   // 全角数字を半角数字に変換
        if (preg_match('/\\D/', $appointed_num)) {
            $appointed_num = preg_replace('/\\D+/', '-', $appointed_num);
            return $this->quoteResRange($full, $qsign, $appointed_num);
        }
        if (preg_match('/^0/', $appointed_num)) {
            return $full;
        }

        $qnum = intval($appointed_num);
        if ($qnum < 1 || $qnum > sizeof($this->thread->datlines)) {
            return $full;
        }

        // あぼーんレスへのアンカー
        if ($_conf['quote_res_view_aborn'] == 0 &&
                in_array($qnum, $this->_aborn_nums)) {
            return '<span class="abornanchor" title="あぼーん">' . "{$full}</span>";
        }

        if ($anchor_jump && $qnum >= $this->thread->resrange['start'] && $qnum <= $this->thread->resrange['to']) {
            $read_url = '#' . ($this->_matome ? "t{$this->_matome}" : '') . "r{$qnum}";
        } else {
            $read_url = "{$_conf['read_php']}?host={$this->thread->host}&amp;bbs={$this->thread->bbs}&amp;key={$this->thread->key}&amp;offline=1&amp;ls={$appointed_num}";
        }
        $attributes = $_conf['bbs_win_target_at'];
        if ($_conf['quote_res_view'] && ($_conf['quote_res_view_ng'] != 0 ||
                !in_array($qnum, $this->_ng_nums))) {
            if ($this->_matome) {
                $qres_id = "t{$this->_matome}qr{$qnum}";
            } else {
                $qres_id = "qr{$qnum}";
            }
            $attributes .= " onmouseover=\"showResPopUp('{$qres_id}',event)\"";
            $attributes .= " onmouseout=\"hideResPopUp('{$qres_id}')\"";
        }
        return "<a href=\"{$read_url}\"{$attributes}"
            . (in_array($qnum, $this->_aborn_nums) ? ' class="abornanchor"' :
                (in_array($qnum, $this->_ng_nums) ? ' class="nganchor"' : ''))
            . ">{$full}</a>";
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
    public function quoteResRange($full, $qsign, $appointed_num)
    {
        global $_conf;

        if ($appointed_num == '-') {
            return $full;
        }

        $read_url = "{$_conf['read_php']}?host={$this->thread->host}&amp;bbs={$this->thread->bbs}&amp;key={$this->thread->key}&amp;offline=1&amp;ls={$appointed_num}n";

        if ($_conf['iframe_popup']) {
            $pop_url = $read_url . "&amp;renzokupop=true";
            return self::iframePopup(array($read_url, $pop_url), $full, $_conf['bbs_win_target_at'], 1);
        }

        // 普通にリンク
        return "<a href=\"{$read_url}\"{$_conf['bbs_win_target_at']}>{$full}</a>";

        // 1つ目を引用レスポップアップ
        /*
        $qnums = explode('-', $appointed_num);
        $qlink = $this->quoteRes($qsign . $qnum[0], $qsign, $qnum[0]) . '-';
        if (isset($qnums[1])) {
            $qlink .= $qnums[1];
        }
        return $qlink;
        */
    }

    // }}}
    // {{{ iframePopup()

    /**
     * HTMLポップアップ変換
     *
     * @param   string|array    $url
     * @param   string|array    $str
     * @param   string          $attr
     * @param   int|null        $mode
     * @param   bool            $marker
     * @return  string
     */
    static public function iframePopup($url, $str, $attr = '', $mode = null, $marker = false)
    {
        global $_conf;

        // リンク用URLとポップアップ用URL
        if (is_array($url)) {
            $link_url = $url[0];
            $pop_url = $url[1];
        } else {
            $link_url = $url;
            $pop_url = $url;
        }

        // リンク文字列とポップアップの印
        if (is_array($str)) {
            $link_str = $str[0];
            $pop_str = $str[1];
        } else {
            $link_str = $str;
            $pop_str = null;
        }

        // リンクの属性
        if (is_array($attr)) {
            $_attr = $attr;
            $attr = '';
            foreach ($_attr as $key => $value) {
                $attr .= ' ' . $key . '="' . p2h($value) . '"';
            }
        } elseif ($attr !== '' && substr($attr, 0, 1) != ' ') {
            $attr = ' ' . $attr;
        }

        // リンクの属性にHTMLポップアップ用のイベントハンドラを加える
        $pop_attr = $attr;
        if ($_conf['iframe_popup_event'] == 1) {
            $pop_attr .= " onclick=\"stophide=true; showHtmlPopUp('{$pop_url}',event,0" . ($marker ? ' ,this' : '') . "); return false;\"";
        } else {
            $pop_attr .= " onmouseover=\"showHtmlPopUp('{$pop_url}',event,{$_conf['iframe_popup_delay']}" . ($marker ? ' ,this' : '') . ")\"";
        }
        $pop_attr .= " onmouseout=\"offHtmlPopUp()\"";

        // 最終調整
        if (is_null($mode)) {
            $mode = $_conf['iframe_popup'];
        }
        if ($mode == 2 && !is_null($pop_str)) {
            $mode = 3;
        } elseif ($mode == 3 && is_null($pop_str)) {
            global $skin, $STYLE;

            $custom_pop_img = "skin/{$skin}/pop.png";
            if (file_exists($custom_pop_img)) {
                $pop_img = p2h($custom_pop_img);
                $x = $STYLE['iframe_popup_mark_width'];
                $y = $STYLE['iframe_popup_mark_height'];
            } else {
                $pop_img = 'img/pop.png';
                $y = $x = 12;
            }
            $pop_str = "<img src=\"{$pop_img}\" width=\"{$x}\" height=\"{$y}\" hspace=\"2\" vspace=\"0\" border=\"0\" align=\"top\" alt=\"\">";
        }

        // リンク作成
        switch ($mode) {
        // マーク無し
        case 1:
            return "<a href=\"{$link_url}\"{$pop_attr}>{$link_str}</a>";
        // (p)マーク
        case 2:
            return "(<a href=\"{$link_url}\"{$pop_attr}>p</a>)<a href=\"{$link_url}\"{$attr}>{$link_str}</a>";
        // [p]画像、サムネイルなど
        case 3:
            return "<a href=\"{$link_url}\"{$pop_attr}>{$pop_str}</a><a href=\"{$link_url}\"{$attr}>{$link_str}</a>";
        // ポップアップしない
        default:
            return "<a href=\"{$link_url}\"{$attr}>{$link_str}</a>";
        }
    }

    // }}}
    // {{{ iframePopupCallback()

    /**
     * HTMLポップアップ変換（コールバック用インターフェース）
     *
     * @param   array   $s  正規表現にマッチした要素の配列
     * @return  string
     */
    public function iframePopupCallback($s)
    {
        return self::iframePopup(p2h($s[1], false), p2h($s[3], false), $s[2]);
    }

    // }}}
    // {{{ _coloredIdStr()

    /**
     * Merged from http://jiyuwiki.com/index.php?cmd=read&page=rep2%A4%C7%A3%C9%A3%C4%A4%CE%C7%D8%B7%CA%BF%A7%CA%D1%B9%B9&alias%5B%5D=pukiwiki%B4%D8%CF%A2
     *
     * @return  string
     */
    protected function _coloredIdStr($idstr, $id, $classed = false)
    {
        global $_conf;

        if (!(isset($this->thread->idcount[$id])
                && $this->thread->idcount[$id] > 1)) {
            return $idstr;
        }
        if ($classed) {
            return $this->_coloredIdStrClassed($idstr, $id);
        }

        switch ($_conf['coloredid.rate.type']) {
        case 1:
            $rate = $_conf['coloredid.rate.times'];
            break;
        case 2:
            $rate = $this->getIdCountRank(10);
            break;
        case 3:
            $rate = $this->getIdCountAverage();
            break;
        default:
            return $idstr;
        }

        if ($rate > 1 && $this->thread->idcount[$id] >= $rate) {
            switch ($_conf['coloredid.coloring.type']) {
            case 0:
                return $this->_coloredIdStr0($idstr, $id);
                break;
            case 1:
                return $this->_coloredIdStr1($idstr, $id);
                break;
            default:
                return $idstr;
            }
        }

        return $idstr;
    }

    // }}}
    // {{{ _coloredIdStrClassed()

    private function _coloredIdStrClassed($idstr, $id)
    {
        $ret = array();
        $arr = explode(':', $idstr);
        foreach ($arr as $i => $str) {
            if ($i == 0 || $i == 1) {
                $ret[] = '<span class="' . self::cssClassedId($id)
                    . ($i == 0 ? '-l' : '-b') . '">' . $str . '</span>';
            } else {
                $ret[] = $str;
            }
        }
        return implode(':', $ret);
    }

    // }}}
    // {{{ _coloredIdStr0()

    /**
     * IDカラー オリジナル着色用
     */
    private function _coloredIdStr0($idstr, $id)
    {
        if (!function_exists('coloredIdStyle0')) {
            require P2_LIB_DIR . '/color/coloredIdStyle0.inc.php';
        }

        if (isset($this->idstyles[$id])) {
            $colored = $this->idstyles[$id];
        } else {
            $colored = coloredIdStyle0($id, $this->thread->idcount[$id]);
            $this->idstyles[$id] = $colored;
        }
        $ret = array();
        foreach ($arr = explode(':', $idstr) as $i => $str) {
            if ($colored[$i]) {
                $ret[] = "<span style=\"{$colored[$i]}\">{$str}</span>";
            } else {
                $ret[] = $str;
            }
        }
        return implode(':', $ret);
    }

    // }}}
    // {{{ _coloredIdStr1()

    /**
     * IDカラー thermon版用
     */
    private function _coloredIdStr1($idstr, $id)
    {
        if (!function_exists('coloredIdStyle')) {
            require P2_LIB_DIR . '/color/coloredIdStyle.inc.php';
        }

        $colored = coloredIdStyle($idstr, $id, $this->thread->idcount[$id]);
        $idstr2 = preg_split('/:/',$idstr,2); // コロンでID文字列を分割
        $ret = array_shift($idstr2).':';
        if ($colored[1]) {
            $idstr2[1] = substr($idstr2[0], 4);
            $idstr2[0] = substr($idstr2[0], 0, 4);
        }
        foreach ($idstr2 as $i => $str) {
            if ($colored[$i]) {
                $ret .= "<span style=\"{$colored[$i]}\">{$str}</span>";
            } else {
                $ret .= $str;
            }
        }
        return $ret;
    }

    // }}}
    // {{{ cssClassedId()

    /**
     * IDカラーに使用するCSSクラス名をID文字列から算出して返す.
     */
    static public function cssClassedId($id)
    {
        return 'idcss-' . bin2hex(
            base64_decode(str_replace('.', '+', substr($id, 0, 8))));
    }

    // }}}
    // {{{ ユーティリティメソッド
    // {{{ imageHtmlPopup()

    /**
     * 画像をHTMLポップアップ&ポップアップウインドウサイズに合わせる
     */
    public function imageHtmlPopup($img_url, $img_tag, $link_str)
    {
        global $_conf;

        if ($_conf['expack.ic2.enabled'] && $_conf['expack.ic2.fitimage']) {
            $popup_url = 'ic2_fitimage.php?url=' . rawurlencode(str_replace('&amp;', '&', $img_url));
        } else {
            $popup_url = $img_url;
        }

        $pops = ($_conf['iframe_popup'] == 1) ? $img_tag . $link_str : array($link_str, $img_tag);
        return self::iframePopup(array($img_url, $popup_url), $pops, $_conf['ext_win_target_at'], null, true);
    }

    // }}}
    // {{{ respopToAsync()

    /**
     * レスポップアップを非同期モードに加工する
     */
    public function respopToAsync($str)
    {
        $respop_regex = '/(onmouseover)=\"(showResPopUp\(\'(q(\d+)of\d+)\',event\).*?)\"/';
        $respop_replace = '$1="loadResPopUp(' . $this->asyncObjName . ', $4);$2"';
        return preg_replace($respop_regex, $respop_replace, $str);
    }

    // }}}
    // {{{ getASyncObjJs()

    /**
     * 非同期読み込みで利用するJavaScriptオブジェクトを生成する
     */
    public function getASyncObjJs()
    {
        global $_conf;
        static $done = array();

        if (isset($done[$this->asyncObjName])) {
            return;
        }
        $done[$this->asyncObjName] = true;

        $code = <<<EOJS
<script type="text/javascript">
//<![CDATA[
var {$this->asyncObjName} = {
    host:"{$this->thread->host}", bbs:"{$this->thread->bbs}", key:"{$this->thread->key}",
    readPhp:"{$_conf['read_php']}", readTarget:"{$_conf['bbs_win_target']}"
};
//]]>
</script>\n
EOJS;
        return $code;
    }

    // }}}
    // {{{ getSpmObjJs()

    /**
     * スマートポップアップメニューを生成するJavaScriptコードを生成する
     */
    public function getSpmObjJs($retry = false)
    {
        global $_conf, $STYLE;

        if (isset(self::$_spm_objects[$this->spmObjName])) {
            return $retry ? self::$_spm_objects[$this->spmObjName] : '';
        }

        $ttitle_en = UrlSafeBase64::encode($this->thread->ttitle);

        if ($_conf['expack.spm.filter_target'] == '' || $_conf['expack.spm.filter_target'] == 'read') {
            $_conf['expack.spm.filter_target'] = '_self';
        }

        $motothre_url = $this->thread->getMotoThread();
        $motothre_url = substr($motothre_url, 0, strlen($this->thread->ls) * -1);

        $_spmOptions = array(
            'null',
            ((!$_conf['disable_res'] && $_conf['expack.spm.kokores']) ? (($_conf['expack.spm.kokores_orig']) ? '2' : '1') : '0'),
            (($_conf['expack.spm.ngaborn']) ? (($_conf['expack.spm.ngaborn_confirm']) ? '2' : '1') : '0'),
            (($_conf['expack.spm.filter']) ? '1' : '0'),
            (($this->am_on_spm) ? '1' : '0'),
            (($_conf['expack.aas.enabled']) ? '1' : '0'),
        );
        $spmOptions = implode(',', $_spmOptions);

        // エスケープ
        $_spm_title = StrCtl::toJavaScript($this->thread->ttitle_hc);
        $_spm_url = addslashes($motothre_url);
        $_spm_host = addslashes($this->thread->host);
        $_spm_bbs = addslashes($this->thread->bbs);
        $_spm_key = addslashes($this->thread->key);
        $_spm_ls = addslashes($this->thread->ls);

        $code = <<<EOJS
<script type="text/javascript">
//<![CDATA[\n
EOJS;

        if (!count(self::$_spm_objects)) {
            $code .= sprintf("spmFlexTarget = '%s';\n", StrCtl::toJavaScript($_conf['expack.spm.filter_target']));
            if ($_conf['expack.aas.enabled']) {
                $code .= sprintf("var aas_popup_width = %d;\n", $_conf['expack.aas.default.width'] + 10);
                $code .= sprintf("var aas_popup_height = %d;\n", $_conf['expack.aas.default.height'] + 10);
            }
        }

        $code .= <<<EOJS
var {$this->spmObjName} = {
    'objName':'{$this->spmObjName}',
    'rc':'{$this->thread->rescount}',
    'title':'{$_spm_title}',
    'ttitle_en':'{$ttitle_en}',
    'url':'{$_spm_url}',
    'host':'{$_spm_host}',
    'bbs':'{$_spm_bbs}',
    'key':'{$_spm_key}',
    'ls':'{$_spm_ls}',
    'spmOption':[{$spmOptions}]
};
SPM.init({$this->spmObjName});
//]]>
</script>\n
EOJS;

        self::$_spm_objects[$this->spmObjName] = $code;

        return $code;
    }

    // }}}
    // }}}
    // {{{ transLinkDo()から呼び出されるURL書き換えメソッド
    /**
     * これらのメソッドは引数が処理対象パターンに合致しないとfalseを返し、
     * transLinkDo()はfalseが返ってくると$_url_handlersに登録されている次の関数/メソッドに処理させようとする。
     */
    // {{{ plugin_linkURL()

    /**
     * URLリンク
     *
     * @param   string $url
     * @param   array $purl
     * @param   string $str
     * @return  string|false
     */
    public function plugin_linkURL($url, $purl, $str)
    {
        global $_conf;

        if (isset($purl['scheme'])) {
            // ime
            if ($_conf['through_ime']) {
                $link_url = P2Util::throughIme($purl[0]);
            } else {
                $link_url = $url;
            }

            $is_http = ($purl['scheme'] == 'http' || $purl['scheme'] == 'https');

            // HTMLポップアップ
            if ($_conf['iframe_popup'] && $is_http) {
                // *pm 指定の場合のみ、特別に手動転送指定を追加する
                if (substr($_conf['through_ime'], -2) == 'pm') {
                    $pop_url = P2Util::throughIme($purl[0], -1);
                } else {
                    $pop_url = $link_url;
                }
                $link = self::iframePopup(array($link_url, $pop_url), $str, $_conf['ext_win_target_at']);
            } else {
                $link = "<a href=\"{$link_url}\"{$_conf['ext_win_target_at']}>{$str}</a>";
            }

            // ブラクラチェッカ
            if ($_conf['brocra_checker_use'] && $_conf['brocra_checker_url'] && $is_http) {
                if (strlen($_conf['brocra_checker_query'])) {
                    $brocra_checker_url = $_conf['brocra_checker_url'] . '?' . $_conf['brocra_checker_query'] . '=' . rawurlencode($purl[0]);
                } else {
                    $brocra_checker_url = rtrim($_conf['brocra_checker_url'], '/') . '/' . $url;
                }
                $brocra_checker_url_orig = $brocra_checker_url;
                // ブラクラチェッカ・ime
                if ($_conf['through_ime']) {
                    $brocra_checker_url = P2Util::throughIme($brocra_checker_url);
                }
                $check_mark = 'チェック';
                $check_mark_prefix = '[';
                $check_mark_suffix = ']';
                // ブラクラチェッカ・HTMLポップアップ
                if ($_conf['iframe_popup']) {
                    // *pm 指定の場合のみ、特別に手動転送指定を追加する
                    if (substr($_conf['through_ime'], -2) == 'pm') {
                        $brocra_checker_url = P2Util::throughIme($brocra_checker_url_orig, -1);
                    } else {
                        $brocra_pop_url = $brocra_checker_url;
                    }
                    if ($_conf['iframe_popup'] == 3) {
                        $check_mark = '<img src="img/check.png" width="33" height="12" alt="">';
                        $check_mark_prefix = '';
                        $check_mark_suffix = '';
                    }
                    $brocra_checker_link = self::iframePopup(array($brocra_checker_url, $brocra_pop_url), $check_mark, $_conf['ext_win_target_at']);
                } else {
                    $brocra_checker_link = "<a href=\"{$brocra_checker_url}\"{$_conf['ext_win_target_at']}>{$check_mark}</a>";
                }
                $link .= $check_mark_prefix . $brocra_checker_link . $check_mark_suffix;
            }

            return $link;
        }
        return false;
    }

    // }}}
    // {{{ plugin_link2chSubject()

    /**
     * 2ch bbspink    板リンク
     *
     * @param   string $url
     * @param   array $purl
     * @param   string $str
     * @return  string|false
     */
    public function plugin_link2chSubject($url, $purl, $str)
    {
        global $_conf;

        if (preg_match('{^https?://(.+)/(.+)/$}', $purl[0], $m)) {
            //rep2に登録されている板ならばリンクする
            if (BbsMap::isRegisteredBbs($m[1],$m[2])) {
                $subject_url = "{$_conf['subject_php']}?host={$m[1]}&amp;bbs={$m[2]}";
                return "<a href=\"{$url}\" target=\"subject\">{$str}</a> [<a href=\"{$subject_url}{$_conf['k_at_a']}\" target=\"subject\">板をp2で開く</a>]";
            }
        }
        return false;
    }

    // }}}
    // {{{ plugin_linkThread()

    /**
     * スレッドリンク
     *
     * @param   string $url
     * @param   array $purl
     * @param   string $str
     * @return  string|false
     */
    public function plugin_linkThread($url, $purl, $str)
    {
        global $_conf;

        list($nama_url, $host, $bbs, $key, $ls) = P2Util::detectThread($purl[0]);
        if ($host && $bbs && $key) {
            $read_url = "{$_conf['read_php']}?host={$host}&amp;bbs={$bbs}&amp;key={$key}&amp;ls={$ls}{$_conf['k_at_a']}";
            if ($_conf['iframe_popup']) {
                if ($ls && preg_match('/^[0-9\\-n]+$/', $ls)) {
                    $pop_url = $read_url;
                } else {
                    $pop_url = $read_url . '&amp;one=true';
                }
                return self::iframePopup(array($read_url, $pop_url), $str, $_conf['bbs_win_target_at']);
            }
            return "<a href=\"{$read_url}{$_conf['bbs_win_target_at']}\">{$str}</a>";
        }

        return false;
    }

    // }}}
    // {{{ plugin_viewImage()

    /**
     * 画像ポップアップ変換
     *
     * @param   string $url
     * @param   array $purl
     * @param   string $str
     * @return  string|false
     */
    public function plugin_viewImage($url, $purl, $str)
    {
        global $_conf;
        global $pre_thumb_unlimited, $pre_thumb_limit;

        if (P2Util::isUrlWikipediaJa($url)) {
            return false;
        }

        // 表示制限
        if (!$pre_thumb_unlimited && empty($pre_thumb_limit)) {
            return false;
        }

        if (preg_match('{^https?://.+?\\.(jpe?g|gif|png)$}i', $purl[0]) && empty($purl['query'])) {
            $pre_thumb_limit--; // 表示制限カウンタを下げる
            $img_tag = "<img class=\"thumbnail\" src=\"{$url}\" height=\"{$_conf['pre_thumb_height']}\" width=\"{$_conf['pre_thumb_width']}\" hspace=\"4\" vspace=\"4\" align=\"middle\">";

            if ($_conf['iframe_popup']) {
                $view_img = $this->imageHtmlPopup($url, $img_tag, $str);
            } else {
                $view_img = "<a href=\"{$url}\"{$_conf['ext_win_target_at']}>{$img_tag}{$str}</a>";
            }

            // ブラクラチェッカ （プレビューとは相容れないのでコメントアウト）
            /*if ($_conf['brocra_checker_use']) {
                $link_url_en = rawurlencode($url);
                if ($_conf['iframe_popup'] == 3) {
                    $check_mark = '<img src="img/check.png" width="33" height="12" alt="">';
                    $check_mark_prefix = '';
                    $check_mark_suffix = '';
                } else {
                    $check_mark = 'チェック';
                    $check_mark_prefix = '[';
                    $check_mark_suffix = ']';
                }
                $view_img .= $check_mark_prefix . "<a href=\"{$_conf['brocra_checker_url']}?{$_conf['brocra_checker_query']}={$link_url_en}\"{$_conf['ext_win_target_at']}>{$check_mark}</a>" . $check_mark_suffix;
            }*/

            return $view_img;
        }

        return false;
    }

    // }}}
    // {{{ plugin_replaceImageUrl()

    /**
     * 置換画像URL+ImageCache2
     */
    public function plugin_replaceImageUrl($url, $purl, $str)
    {
        static $serial = 0;

        global $_conf;
        global $pre_thumb_unlimited, $pre_thumb_ignore_limit, $pre_thumb_limit;

        // +Wiki
        global $replaceImageUrlCtl;

        $url = $purl[0];
        $replaced = $replaceImageUrlCtl->replaceImageUrl($url);
        if (!$replaced[0]) {
            return false;
        }

        foreach ($replaced as $v) {
            $url_en = rawurlencode($v['url']);
            $url_ht = p2h($v['url']);
            $ref_en = $v['referer'] ? '&amp;ref=' . rawurlencode($v['referer']) : '';

            // 準備
            $serial++;
            $thumb_id = 'thumbs' . $serial . $this->thumb_id_suffix;
            $tmp_thumb = './img/ic_load.png';

            $icdb = new ImageCache2_DataObject_Images();

            // r=0:リンク;r=1:リダイレクト;r=2:PHPで表示
            // t=0:オリジナル;t=1:PC用サムネイル;t=2:携帯用サムネイル;t=3:中間イメージ
            // +Wiki
            $img_url = 'ic2.php?r=1&amp;uri=' . $url_en . $ref_en;
            $thumb_url = 'ic2.php?r=1&amp;t=1&amp;uri=' . $url_en . $ref_en;
            // お気にスレ自動画像ランク
            $rank = null;
            if ($_conf['expack.ic2.fav_auto_rank']) {
                $rank = $this->getAutoFavRank();
                if ($rank !== null) $thumb_url .= '&rank=' . $rank;
            }

            // DBに画像情報が登録されていたとき
            if ($icdb->get($v['url'])) {

                // ウィルスに感染していたファイルのとき
                if ($icdb->mime == 'clamscan/infected') {
                    $result .= "<img class=\"thumbnail\" src=\"./img/x04.png\" width=\"32\" height=\"32\" hspace=\"4\" vspace=\"4\" align=\"middle\">";
                    continue;
                }
                // あぼーん画像のとき
                if ($icdb->rank < 0) {
                    $result .= "<img class=\"thumbnail\" src=\"./img/x01.png\" width=\"32\" height=\"32\" hspace=\"4\" vspace=\"4\" align=\"middle\">";
                    continue;
                }

                // オリジナルがキャッシュされているときは画像を直接読み込む
                if (file_exists($this->thumbnailer->srcPath($icdb->size, $icdb->md5, $icdb->mime))) {
                    $img_url = $this->thumbnailer->srcUrl($icdb->size, $icdb->md5, $icdb->mime);
                    $cached = true;
                } else {
                    $cached = false;
                }

                // サムネイルが作成されていているときは画像を直接読み込む
                if (file_exists($this->thumbnailer->thumbPath($icdb->size, $icdb->md5, $icdb->mime))) {
                    $thumb_url = $this->thumbnailer->thumbUrl($icdb->size, $icdb->md5, $icdb->mime);
                    // 自動スレタイメモ機能がONでスレタイが記録されていないときはDBを更新
                    if (!is_null($this->img_memo) && strpos($icdb->memo, $this->img_memo) === false){
                        $update = new ImageCache2_DataObject_Images();
                        if (!is_null($icdb->memo) && strlen($icdb->memo) > 0) {
                            $update->memo = $this->img_memo . ' ' . $icdb->memo;
                        } else {
                            $update->memo = $this->img_memo;
                        }
                        $update->whereAddQuoted('uri', '=', $v['url']);
                    }

                    // expack.ic2.fav_auto_rank_override の設定とランク条件がOKなら
                    // お気にスレ自動画像ランクを上書き更新
                    if ($rank !== null &&
                            self::isAutoFavRankOverride($icdb->rank, $rank)) {
                        if ($update === null) {
                            $update = new ImageCache2_DataObject_Images();
                            $update->whereAddQuoted('uri', '=', $v['url']);
                        }
                        $update->rank = $rank;
                    }

                    if ($update !== null) {
                        $update->update();
                    }
                }

                // サムネイルの画像サイズ
                $thumb_size = $this->thumbnailer->calc($icdb->width, $icdb->height);
                $thumb_size = preg_replace('/(\d+)x(\d+)/', 'width="$1" height="$2"', $thumb_size);
                $tmp_thumb = './img/ic_load1.png';

                $orig_img_url   = $img_url;
                $orig_thumb_url = $thumb_url;

            // 画像がキャッシュされていないとき
            // 自動スレタイメモ機能がONならクエリにUTF-8エンコードしたスレタイを含める
            } else {
                // 画像がブラックリストorエラーログにあるか確認
                if (false !== ($errcode = $icdb->ic2_isError($v['url']))) {
                    $result .= "<img class=\"thumbnail\" src=\"./img/{$errcode}.png\" width=\"32\" height=\"32\" hspace=\"4\" vspace=\"4\" align=\"middle\">";
                    continue;
                }

                $cached = false;

                $orig_img_url   = $img_url;
                $orig_thumb_url = $thumb_url;
                $img_url .= $this->img_memo_query;
                $thumb_url .= $this->img_memo_query;
                $thumb_size = '';
                $tmp_thumb = './img/ic_load2.png';
            }

            // キャッシュされておらず、表示数制限が有効のとき
            if (!$cached && !$pre_thumb_unlimited && !$pre_thumb_ignore_limit) {
                // 表示制限を超えていたら、表示しない
                // 表示制限を超えていなければ、表示制限カウンタを下げる
                if ($pre_thumb_limit <= 0) {
                    $show_thumb = false;
                } else {
                    $show_thumb = true;
                    $pre_thumb_limit--;
                }
            } else {
                $show_thumb = true;
            }

            // 表示モード
            if ($show_thumb) {
                $img_tag = "<img class=\"thumbnail\" src=\"{$thumb_url}\" {$thumb_size} hspace=\"4\" vspace=\"4\" align=\"middle\">";
                if ($_conf['iframe_popup']) {
                    $view_img = $this->imageHtmlPopup($img_url, $img_tag, '');
                } else {
                    $view_img = "<a href=\"{$img_url}\"{$_conf['ext_win_target_at']}>{$img_tag}</a>";
                }
            } else {
                $img_tag = "<img id=\"{$thumb_id}\" class=\"thumbnail\" src=\"{$tmp_thumb}\" width=\"32\" height=\"32\" hspace=\"4\" vspace=\"4\" align=\"middle\">";
                $view_img = "<a href=\"{$img_url}\" onclick=\"return loadThumb('{$thumb_url}','{$thumb_id}')\"{$_conf['ext_win_target_at']}>{$img_tag}</a><a href=\"{$img_url}\"{$_conf['ext_win_target_at']}></a>";
            }

            $view_img .= '<img class="ic2-info-opener" src="img/s2a.png" width="16" height="16" onclick="ic2info.show('
                    //. "'{$url_ht}', '{$orig_img_url}', '{$_conf['ext_win_target']}', '{$orig_thumb_url}', event)\">";
                      . "'{$url_ht}', event)\">";

            $result .= $view_img;
        }
        // ソースへのリンクをime付きで表示
        $ime_url = P2Util::throughIme($url);
        $result .= "<a class=\"img_through_ime\" href=\"{$ime_url}\"{$_conf['ext_win_target_at']}>{$str}</a>";
        return $result;
    }

    /**
     * +Wiki:リンクプラグイン
     */
    public function plugin_linkPlugin($url, $purl, $str)
    {
        return $GLOBALS['linkPluginCtl']->replaceLinkToHTML($url, $str);
    }

    // }}}
    // {{{ getQuotebacksJson()

    public function getQuotebacksJson()
    {
        $ret = array();
        foreach ($this->getQuoteFrom() as $resnum => $quote_from) {
            if (!$quote_from) {
                continue;
            }
            if ($resnum != 1 && ($resnum < $this->thread->resrange['start'] || $resnum > $this->thread->resrange['to'])) {
                continue;
            }
            $tmp = array();
            foreach ($quote_from as $quote) {
                if ($quote != 1 && ($quote < $this->thread->resrange['start'] || $quote > $this->thread->resrange['to'])) {
                    continue;
                }
                $tmp[] = $quote;
            }
            if ($tmp) $ret[] = "{$resnum}:[" . join(',', $tmp) . "]";
        }
        return '{' . join(',', $ret) . '}';
    }

    // }}}
    // {{{ getResColorJs()

    public function getResColorJs()
    {
        global $_conf, $STYLE;

        $fontstyle_bold = empty($STYLE['fontstyle_bold']) ? 'normal' : $STYLE['fontstyle_bold'];
        $fontweight_bold = empty($STYLE['fontweight_bold']) ? 'normal' : $STYLE['fontweight_bold'];
        $fontfamily_bold = $STYLE['fontfamily_bold'];
        $backlinks = $this->getQuotebacksJson();
        $colors = array();
        $backlink_colors = join(',',
            array_map(create_function('$x', 'return "\'{$x}\'";'),
                explode(',', $_conf['backlink_coloring_track_colors']))
        );
        $prefix = $this->_matome ? "t{$this->_matome}" : '';
        return <<<EOJS
<script type="text/javascript">
if (typeof rescolObjs == 'undefined') rescolObjs = [];
rescolObjs.push((function() {
    var obj = new BacklinkColor('{$prefix}');
    obj.colors = [{$backlink_colors}];
    obj.highlightStyle = {fontStyle :'{$fontstyle_bold}', fontWeight : '{$fontweight_bold}', fontFamily : '{$fontfamily_bold}'};
    obj.backlinks = {$backlinks};
    return obj;
})());
</script>
EOJS;
    }

    // }}}
    // {{{ getIdsForRenderJson()

    public function getIdsForRenderJson()
    {
        $ret = array();
        if ($this->_ids_for_render) {
            foreach ($this->_ids_for_render as $id => $count) {
                $ret[] = "'{$id}':{$count}";
            }
        }
        return '{' . join(',', $ret) . '}';
    }

    // }}}
    // {{{ getIdColorJs()

    public function getIdColorJs()
    {
        global $_conf, $STYLE;

        if ($_conf['coloredid.enable'] < 1 || $_conf['coloredid.click'] < 1) {
            return '';
        }
        if (count($this->thread->idcount) < 1) {
            return '';
        }

        $idslist = $this->getIdsForRenderJson();

        $rate = $_conf['coloredid.rate.times'];
        $tops = $this->getIdCountRank(10);
        $average = $this->getIdCountAverage();
        $color_init = '';
        if ($_conf['coloredid.rate.type'] > 0) {
            switch($_conf['coloredid.rate.type']) {
            case 2:
                $init_rate = $tops;
                break;
            case 3:
                $init_rate = $average;
                break;
            case 1:
                $init_rate = $rate;
            default:
            }
            if ($init_rate > 1)
                $color_init .= 'idCol.initColor(' . $init_rate . ', idslist);';
        }
        $color_init .= "idCol.rate = {$rate};";
        if (!$this->_matome) {
            $color_init .= "idCol.tops = {$tops};";
            $color_init .= "idCol.average = {$average};";
        }
        $hissiCount = $_conf['coloredid.rate.hissi.times'];
        $mark_colors = join(',',
            array_map(create_function('$x', 'return "\'{$x}\'";'),
                explode(',', $_conf['coloredid.marking.colors']))
        );
        $fontstyle_bold = empty($STYLE['fontstyle_bold']) ? 'normal' : $STYLE['fontstyle_bold'];
        $fontweight_bold = empty($STYLE['fontweight_bold']) ? 'normal' : $STYLE['fontweight_bold'];
        $fontfamily_bold = $STYLE['fontfamily_bold'];
        $uline = $STYLE['a_underline_none'] != 1
            ? 'idCol.colorStyle["textDecoration"] = "underline"' : '';
        return <<<EOJS
<script>
(function() {
var idslist = {$idslist};
if (typeof idCol == 'undefined') {
    idCol = new IDColorChanger(idslist, {$hissiCount});
    idCol.colors = [{$mark_colors}];
{$uline};
    idCol.highlightStyle = {fontStyle :'{$fontstyle_bold}', fontWeight : '{$fontweight_bold}', fontFamily : '{$fontfamily_bold}', fontSize : '104%'};
} else idCol.addIdlist(idslist);
{$color_init}
idCol.setupSPM('{$this->spmObjName}');
})();
</script>
EOJS;
    }

    // }}}
    // {{{ getIdCountAverage()

    public function getIdCountAverage()
    {
        if ($this->_idcount_average !== null) {
            return $this->_idcount_average;
        }

        $sum = 0;
        $param = 0;

        foreach ($this->thread->idcount as $count) {
            if ($count > 1) {
                $sum += $count;
                $param++;
            }
        }

        $result = ($param < 1) ? 0 : intval(ceil($sum / $param));
        $this->_idcount_average = $result;

        return $result;
    }

    // }}}
    // {{{ getIdCountRank()

    public function getIdCountRank($rank)
    {
        if ($this->_idcount_tops !== null) {
            return $this->_idcount_tops;
        }

        $ranking = array();

        foreach ($this->thread->idcount as $count) {
            if ($count > 1) {
                $ranking[] = $count;
            }
        }

        if (count($ranking) == 0) {
            return 0;
        }

        rsort($ranking);
        $rcount = count($ranking);

        $result = ($rcount >= $rank) ? $ranking[$rank - 1] : $ranking[$rcount  - 1];
        $this->_idcount_tops = $result;

        return $result;
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
