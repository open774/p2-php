<?php
/**
 * rep2 - 携帯用でスレッドを表示する クラス
 */

require_once P2EX_LIB_DIR . '/ExpackLoader.php';

ExpackLoader::loadAAS();
ExpackLoader::loadActiveMona();
ExpackLoader::loadImageCache();

// {{{ ShowThreadI

class ShowThreadI extends ShowThread
{
    // {{{ properties

    static private $_spm_objects = array();

    public $am_autong = false; // 自動AA略をするか否か

    public $aas_rotate = '90°回転'; // AAS 回転リンク文字列

    public $respopup_at = ' onclick="return iResPopUp(this, event);"';  // レスポップアップ・イベントハンドラ
    public $target_at = ' target="_blank"';    // 引用、省略、ID、NG等のリンクターゲット
    public $check_st = 'check';   // 省略、NG等のリンク文字列

    public $spmObjName; // スマートポップアップメニュー用JavaScriptオブジェクト名

    private $_dateIdPattern;    // 日付書き換えの検索パターン
    private $_dateIdReplace;    // 日付書き換えの置換文字列

    //private $_lineBreaksReplace; // 連続する改行の置換文字列

    private $_kushiYakiName = null; // BBQに焼かれているときの名前接頭辞

    // }}}
    // {{{ constructor

    /**
     * コンストラクタ
     */
    public function __construct(ThreadRead $aThread, $matome = false)
    {
        parent::__construct($aThread, $matome);

        global $_conf, $STYLE;

        $this->_url_handlers = array(
            'plugin_linkThread',
            'plugin_link2chSubject',
        );
        // +Wiki
        if (isset($GLOBALS['linkPluginCtl'])) {
            $this->_url_handlers[] = 'plugin_linkPlugin';
        }
        if (isset($GLOBALS['replaceImageUrlCtl'])) {
            $this->_url_handlers[] = 'plugin_replaceImageUrl';
        } elseif ($_conf['mobile.use_picto']) {
            $this->_url_handlers[] = 'plugin_viewImage';
        }
        $this->_url_handlers[] = 'plugin_linkURL';

        if (!$_conf['mobile.bbs_noname_name']) {
            $this->setBbsNonameName();
        }

        if (P2HostMgr::isHost2chs($aThread->host)) {
            $this->_kushiYakiName = ' </b>[―{}@{}@{}-]<b> ';
        }

        if ($_conf['mobile.date_zerosuppress']) {
            $this->_dateIdPattern = '~^(?:' . date('Y|y') . ')/(?:0(\\d)|(\\d\\d))?(?:(/)0)?~';
            $this->_dateIdReplace = '$1$2$3';
        } else {
            $this->_dateIdPattern = '~^(?:' . date('Y|y') . ')/~';
            $this->_dateIdReplace = '';
        }

        // 連続する改行の置換文字列を設定
        /*
        if ($_conf['mobile.strip_linebreaks']) {
            $ngword_color = $GLOBALS['STYLE']['mobile_read_ngword_color'];
            if (strpos($ngword_color, '\\') === false && strpos($ngword_color, '$') === false) {
                $this->_lineBreaksReplace = " <br><s><font color=\"{$ngword_color}\">***</font></s><br> ";
            } else {
                $this->_lineBreaksReplace = ' <br><s>***</s><br> ';
            }
        } else {
            $this->_lineBreaksReplace = null;
        }
        */

        // サムネイル表示制限数を設定
        if (!isset($GLOBALS['pre_thumb_unlimited']) || !isset($GLOBALS['expack.ic2.pre_thumb_limit_k'])) {
            if (isset($_conf['expack.ic2.pre_thumb_limit_k']) && $_conf['expack.ic2.pre_thumb_limit_k'] > 0) {
                $GLOBALS['pre_thumb_limit_k'] = $_conf['expack.ic2.pre_thumb_limit_k'];
                $GLOBALS['pre_thumb_unlimited'] = false;
            } else {
                $GLOBALS['pre_thumb_limit_k'] = null;   // ヌル値だとisset()はfalseを返す
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

        // AAS 初期化
        if (P2_AAS_AVAILABLE) {
            ExpackLoader::initAAS($this);
        }

        // SPM初期化
        //if ($this->_matome) {
        //    $this->spmObjName = sprintf('t%dspm%u', $this->_matome, crc32($this->thread->keydat));
        //} else {
        $this->spmObjName = sprintf('spm%u', crc32($this->thread->keydat));
        //}
    }

    // }}}
    // {{{ transRes()

    /**
     * DatレスをHTMLレスに変換する
     *
     * @param   string $ares datの1ライン
     * @param   int $i レス番号
     * @param   string $pattern ハイライト用正規表現
     * @return  string
     */
    public function transRes($ares, $i, $pattern = null)
    {
        global $_conf, $STYLE, $mae_msg;

        list($name, $mail, $date_id, $msg) = $this->thread->explodeDatLine($ares);
        if (($id = $this->thread->ids[$i]) !== null) {
            $idstr = 'ID:' . $id;
            $date_id = str_replace($this->thread->idp[$i] . $id, $idstr, $date_id);
        } else {
            $idstr = null;
        }

        // +Wiki:置換ワード
        if (isset($GLOBALS['replaceWordCtl'])) {
            $replaceWordCtl = $GLOBALS['replaceWordCtl'];
            $name = $replaceWordCtl->replace('name', $this->thread, $ares, $i);
            $mail = $replaceWordCtl->replace('mail', $this->thread, $ares, $i);
            $date_id = $replaceWordCtl->replace('date', $this->thread, $ares, $i);
            $msg = $replaceWordCtl->replace('msg', $this->thread, $ares, $i);
        }

        $tores = '';
        if ($this->_matome) {
            $res_id = "t{$this->_matome}r{$i}";
        } else {
            $res_id = "r{$i}";
        }

        // NGあぼーんチェック
        $nong = !empty($_GET['nong']);
        $ng_type = $this->_ngAbornCheck($i, strip_tags($name), $mail, $date_id, $id, $msg, $nong, $ng_info);
        if ($ng_type == self::ABORN) {
            return $this->_abornedRes($res_id);
        }
        if (!$nong && $this->am_autong && $this->activeMona->detectAA($msg)) {
            $is_ng = array_key_exists($i, $this->_ng_nums);
            $ng_type |= $this->_markNgAborn($i, self::NG_AA, true);
            $ng_info[] = 'AA略';
            // AAを連鎖NG対象から外す場合
            if (!$is_ng && $_conf['expack.am.autong_k'] == 2) {
                unset($this->_ng_nums[$i]);
            }
        }
        if ($ng_type != self::NG_NONE) {
            $ngaborns_head_hits = self::$_ngaborns_head_hits;
            $ngaborns_body_hits = self::$_ngaborns_body_hits;
        }

        // {{{ 名前と日付・IDを調整

        // 串焼きマークを短縮
        if ($this->_kushiYakiName !== null && strpos($name, $this->_kushiYakiName) === 0) {
            $name = substr($name, strlen($this->_kushiYakiName));
            // デフォルトの名前は省略
            if ($name === $this->_nanashiName) {
                $name = '[串]';
            } else {
                $name = '[串]' . $name;
            }
            // デフォルトの名前と同じなら省略
        } elseif ($name === $this->_nanashiName) {
            $name = '';
        }

        // 現在の年号は省略カットする。月日の先頭0もカット。
        $date_id = preg_replace($this->_dateIdPattern, $this->_dateIdReplace, $date_id);

        // 曜日と時間の間を詰める
        $date_id = str_replace(') ', ')', $date_id);

        // 秒もカット
        if ($_conf['mobile.clip_time_sec']) {
            $date_id = preg_replace('/(\\d\\d:\\d\\d):\\d\\d(?:\\.\\d\\d)?/', '$1', $date_id);
        }

        // ID
        if ($id !== null) {
            $id_suffix = substr($id, -1);

            if ($_conf['mobile.underline_id'] && $id_suffix == 'O' && strlen($id) % 2) {
                $do_underline_id_suffix = true;
            } else {
                $do_underline_id_suffix = false;
            }

            if ($this->thread->idcount[$id] > 1) {
                if ($_conf['flex_idpopup'] == 1) {
                    $date_id = str_replace($idstr, $this->idFilter($idstr, $id), $date_id);
                }
                if ($do_underline_id_suffix) {
                    $date_id = str_replace($idstr, substr($idstr, 0, -1) . '<u>' . $id_suffix . '</u>', $date_id);
                }
            } else {
                if ($_conf['mobile.clip_unique_id']) {
                    if ($do_underline_id_suffix) {
                        $date_id = str_replace($idstr, 'ID:*<u>' . $id_suffix . '</u>', $date_id);
                    } else {
                        $date_id = str_replace($idstr, 'ID:*' . $id_suffix, $date_id);
                    }
                } else {
                    if ($do_underline_id_suffix) {
                        $date_id = str_replace($idstr, substr($idstr, 0, -1) . '<u>' . $id_suffix . '</u>', $date_id);
                    }
                }
            }
        } else {
            if ($_conf['mobile.clip_unique_id']) {
                $date_id = str_replace('ID:???', 'ID:?', $date_id);
            }
        }

        // }}}

        //=============================================================
        // まとめて出力
        //=============================================================

        if ($name) {
            $name = $this->transName($name); // 名前HTML変換
        }
        $msg = $this->transMsg($msg, $i); // メッセージHTML変換

        // BEプロファイルリンク変換
        $date_id = $this->replaceBeId($date_id, $i);

        // NGメッセージ変換
        if ($ng_type != self::NG_NONE && count($ng_info)) {
            $ng_info = implode(', ', $ng_info);
            if ($ng_type == self::NG_AA) {
                $msg = <<<EOMSG
<a class="button" href="{$_conf['read_php']}?host={$this->thread->host}&amp;bbs={$this->thread->bbs}&amp;key={$this->thread->key}&amp;ls={$i}&amp;k_continue=1&amp;nong=1{$_conf['k_at_a']}"{$this->respopup_at}{$this->target_at}>{$ng_info}</a>
EOMSG;
            } else {
                $msg = <<<EOMSG
<s><font color="{$STYLE['mobile_read_ngword_color']}">{$ng_info}</font></s> <a class="button" href="{$_conf['read_php']}?host={$this->thread->host}&amp;bbs={$this->thread->bbs}&amp;key={$this->thread->key}&amp;ls={$i}&amp;k_continue=1&amp;nong=1{$_conf['k_at_a']}"{$this->respopup_at}{$this->target_at}>{$this->check_st}</a>
EOMSG;
            }

            // AAS
            if (($ng_type & self::NG_AA) && P2_AAS_AVAILABLE) {
                $aas_url = "aas.php?host={$this->thread->host}&amp;bbs={$this->thread->bbs}&amp;key={$this->thread->key}&amp;resnum={$i}";
                if (P2_AAS_AVAILABLE == 2) {
                    $aas_txt = "<img src=\"{$aas_url}{$_conf['k_at_a']}&amp;inline=1\">";
                } else {
                    $aas_txt = "AAS";
                }
                $msg .= " <a class=\"aas limelight\" href=\"{$aas_url}&amp;b=pc\" title=\"&gt;&gt;{$i}\"{$this->target_at}>{$aas_txt}</a>";
            }
        }

        // NGネーム変換
        if ($ng_type & self::NG_NAME) {
            $name = <<<EONAME
<s><font color="{$STYLE['mobile_read_ngword_color']}">{$name}</font></s>
EONAME;
            $msg = <<<EOMSG
<a class="button" href="{$_conf['read_php']}?host={$this->thread->host}&amp;bbs={$this->thread->bbs}&amp;key={$this->thread->key}&amp;ls={$i}&amp;k_continue=1&amp;nong=1{$_conf['k_at_a']}"{$this->respopup_at}{$this->target_at}>{$this->check_st}</a>
EOMSG;

            // NGメール変換
        } elseif ($ng_type & self::NG_MAIL) {
            $mail = <<<EOMAIL
<s class="ngword" onmouseover="document.getElementById('ngn{$ngaborns_head_hits}').style.display = 'block';">{$mail}</s>
EOMAIL;
            $msg = <<<EOMSG
<div id="ngn{$ngaborns_head_hits}" style="display:none;">{$msg}</div>
EOMSG;

            // NGID変換
        } elseif ($ng_type & self::NG_ID) {
            $date_id = <<<EOID
<s><font color="{$STYLE['mobile_read_ngword_color']}">{$date_id}</font></s>
EOID;
            $msg = <<<EOMSG
<a class="button" href="{$_conf['read_php']}?host={$this->thread->host}&amp;bbs={$this->thread->bbs}&amp;key={$this->thread->key}&amp;ls={$i}&amp;k_continue=1&amp;nong=1{$_conf['k_at_a']}"{$this->respopup_at}{$this->target_at}>{$this->check_st}</a>
EOMSG;
        }

        /*
        //「ここから新着」画像を挿入
        if ($i == $this->thread->readnum +1) {
            $tores .= <<<EOP
                <div><img src="img/image.png" alt="新着レス" border="0" vspace="4"></div>
EOP;
        }
        */

        $tores .= "<div id=\"{$res_id}\" class=\"res\"><div class=\"res-header\">";

        $no_class = 'no';
        $no_onclick = '';

        // オンザフライ時
        if ($this->thread->onthefly) {
            $GLOBALS['newres_to_show_flag'] = true;
            $no_class .= ' onthefly';
            // 新着レス時
        } elseif ($i > $this->thread->readnum) {
            $GLOBALS['newres_to_show_flag'] = true;
            $no_class .= ' newres';
        }

        // SPM
        if ($_conf['expack.spm.enabled']) {
            $no_onclick = " onclick=\"{$this->spmObjName}.show({$i},'{$res_id}',event)\"";
        }

        // 番号
        $tores .= "<span class=\"{$no_class}\"{$no_onclick}>{$i}</span>";
        // 名前
        $tores .= " <span class=\"name\">{$name}</span>";
        // メール
        $tores .= " <span class=\"mail\">{$mail}</span>";
        // 日付とID
        $tores .= " <span class=\"date-id\">{$date_id}</span></div>\n";
        // 内容
        $tores .= "<div class=\"message\">{$msg}</div>";
        // 被レスリスト
        if ($_conf['mobile.backlink_list'] == 1) {
            $linkstr = $this->_quotebackListHtml($i, 2);
            if (strlen($linkstr)) {
                $tores .= $linkstr;
            }
        }
        $tores .= "</div>\n"; // 内容を閉じる

        // まとめてフィルタ色分け
        if ($pattern) {
            if (is_string($_conf['k_filter_marker'])) {
                $tores = StrCtl::filterMarking($pattern, $tores, $_conf['k_filter_marker']);
            } else {
                $tores = StrCtl::filterMarking($pattern, $tores);
            }
        }

        // 全角英数スペースカナを半角に
        if (!empty($_conf['mobile.save_packet'])) {
            $tores = mb_convert_kana($tores, 'rnsk'); // CP932 だと ask で ＜ を < に変換してしまうようだ
        }

        return array('body' => $tores, 'q' => '');
    }

    // }}}
    // {{{ transName()

    /**
     * 名前をHTML用に変換する
     *
     * @param   string $name 名前
     * @return  string
     */
    public function transName($name)
    {
        $name = strtr($name, array('<b>' => '', '</b>' => '')); 

        // トリップやホスト付きなら分解する
        if (($pos = strpos($name, '◆')) !== false) {
            $trip = substr($name, $pos);
            $name = substr($name, 0, $pos);
        } else {
            $trip = null;
        }

        // 数字を引用レスポップアップリンク化
        if (strlen($name) && $name != $this->_nanashiName) {
            $name = preg_replace_callback(
                self::getAnchorRegex('/(?:^|%prefix%)%nums%/'),
                array($this, '_quoteNameCallback'), $name
            );
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
     * @param   string $msg メッセージ
     * @param   int $mynum レス番号
     * @return  string
     */
    public function transMsg($msg, $mynum)
    {
        global $_conf;
        global $pre_thumb_ignore_limit;

        $ryaku = false;

        // 2ch旧形式のdat
        if ($this->thread->dat_type == '2ch_old') {
            $msg = str_replace('＠｀', ',', $msg);
            $msg = preg_replace('/&amp(?=[^;])/', '&', $msg);
        }

		// サロゲートペアの数値文字参照を変換
        $msg = P2Util::replaceNumericalSurrogatePair($msg);
		
        // &補正
        $msg = preg_replace('/&(?!#?\\w+;)/', '&amp;', $msg);

        // >>1のリンクをいったん外す
        // <a href="../test/read.cgi/accuse/1001506967/1" target="_blank">&gt;&gt;1</a>
        $msg = preg_replace('{<[Aa] .+?>(&gt;&gt;\\d[\\d\\-]*)</[Aa]>}', '$1', $msg);

        // 大きさ制限
        if (empty($_GET['k_continue']) && strlen($msg) > $_conf['mobile.res_size']) {
            // <br>以外のタグを除去し、長さを切り詰める
            $msg = strip_tags($msg, '<br>');
            $msg = mb_strcut($msg, 0, $_conf['mobile.ryaku_size']);
            $msg = preg_replace('/ *<[^>]*$/', '', $msg);

            // >>1, >1, ＞1, ＞＞1を引用レスポップアップリンク化
            $msg = preg_replace_callback(
                self::getAnchorRegex('/%full%/'),
                array($this, '_quoteResCallback'), $msg
            );

            $msg .= "<a href=\"{$_conf['read_php']}?host={$this->thread->host}&amp;bbs={$this->thread->bbs}&amp;key={$this->thread->key}&amp;ls={$mynum}&amp;k_continue=1&amp;offline=1{$_conf['k_at_a']}\"{$this->respopup_at}{$this->target_at}>略</a>";
            return $msg;
        }

        // 新着レスの画像は表示制限を無視する設定なら
        if ($mynum > $this->thread->readnum && $_conf['expack.ic2.newres_ignore_limit_k']) {
            $pre_thumb_ignore_limit = true;
        }

        // 文末の改行と連続する改行を除去
        if ($_conf['mobile.strip_linebreaks']) {
            $msg = $this->stripLineBreaks($msg /*, $this->_lineBreaksReplace*/);
        }

        // 引用やURLなどをリンク
        $msg = $this->transLink($msg);

        // Wikipedia記法への自動リンク
        if ($_conf['mobile._linkToWikipeida']) {
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
<div id="{$res_id}" name="{$res_id}" class="res aborned">&nbsp;</div>\n
EOP;
    }

    // }}}
    // {{{ getSpmObjJs()

    /**
     * スマートポップアップメニューに必要なスレッド情報を格納したJavaScriptコードを取得
     */
    public function getSpmObjJs($retry = false)
    {
        global $_conf;

        if (isset(self::$_spm_objects[$this->spmObjName])) {
            return $retry ? self::$_spm_objects[$this->spmObjName] : '';
        }

        $ttitle_en = UrlSafeBase64::encode($this->thread->ttitle);

        $motothre_url = $this->thread->getMotoThread();
        $motothre_url = substr($motothre_url, 0, strlen($this->thread->ls) * -1);

        // エスケープ
        $_spm_title = StrCtl::toJavaScript($this->thread->ttitle_hc);
        $_spm_url = addslashes($motothre_url);
        $_spm_host = addslashes($this->thread->host);
        $_spm_bbs = addslashes($this->thread->bbs);
        $_spm_key = addslashes($this->thread->key);
        $_spm_ls = addslashes($this->thread->ls);
        $_spm_b = ($_conf['view_forced_by_query']) ? "&b={$_conf['b']}" : '';

        $code = <<<EOJS
<script type="text/javascript">
//<![CDATA[
var {$this->spmObjName} = {
    'objName':'{$this->spmObjName}',
    'query':'&host={$_spm_host}&bbs={$_spm_bbs}&key={$_spm_key}&rescount={$this->thread->rescount}&ttitle_en={$ttitle_en}{$_spm_b}',
    'rc':'{$this->thread->rescount}',
    'title':'{$_spm_title}',
    'ttitle_en':'{$ttitle_en}',
    'url':'{$_spm_url}',
    'host':'{$_spm_host}',
    'bbs':'{$_spm_bbs}',
    'key':'{$_spm_key}',
    'ls':'{$_spm_ls}',
    'client':['{$_conf['b']}','{$_conf['client_type']}']
};
{$this->spmObjName}.show = (function(no,id,evt){SPM.show({$this->spmObjName},no,id,evt);});
{$this->spmObjName}.hide = SPM.hide; // (function(evt){SPM.hide(evt);});
//]]>
</script>\n
EOJS;

        self::$_spm_objects[$this->spmObjName] = $code;

        return $code;
    }

    // }}}
    // {{{ getSpmElementHtml()

    /**
     * スマートポップアップメニュー用のHTMLを生成する
     */
    static public function getSpmElementHtml()
    {
        global $_conf;

        $spm = <<<EOP
<div id="spm" class="col-xs-8">
<div id="spm-header" class="spm-section">&gt;&gt;<span id="spm-num">???</span></div>
<div id="spm-menu" class="spm-section">
    <span id="spm-reply-noquote" onclick="SPM.replyTo(false)">これにレス</span>
    <span id="spm-reply-quote" onclick="SPM.replyTo(true)">引用してレス</span>
EOP;
        if ($_conf['expack.aas.enabled']) {
            $spm .= <<<EOP
    <span id="spm-aas" onclick="SPM.open('aas')">AAS</span>
    <span id="spm-aas-rotate" onclick="SPM.open('aas_rotate')">AAS(回転)</span>
EOP;
        }
        $spm .= <<<EOP
    <span id="spm-rref" onclick="SPM.open('rref')">逆参照</span>
</div>
<div id="spm-action" >
<div class="input-group"> 
<select id="spm-select-target" class=" form-control" style="width:40%;">
    <option value="name">名前</option>
    <option value="mail">メール</option>
    <option value="id" selected>ID</option>
    <option value="msg">本文</option>
    <option value="be">BE</option>
</select><select id="spm-select-action"class=" form-control" style="width:60%;">
    <option value="aborn" selected>をあぼーん</option>
    <option value="ng">を NG</option>
<!-- <option value="search">検索</option> -->
</select><span class="input-group-btn"><button class="btn" type="button" onclick="SPM.doAction()">OK</button></span>
</div></div>
<img id="spm-closer" src="img/iphone/close.png" width="24" height="26" onclick="SPM.hide(event)">
</div>
EOP;
        return $spm;
    }

    // }}}
    // {{{ idFilter()

    /**
     * IDフィルタリングリンク変換
     *
     * @param   string $idstr ID:xxxxxxxxxx
     * @param   string $id xxxxxxxxxx
     * @return  string
     */
    public function idFilter($idstr, $id)
    {
        global $_conf;

        //$idflag = '';   // 携帯/PC識別子
        // IDは8桁または10桁(+携帯/PC識別子)と仮定して
        /*
        if (strlen($id) % 2 == 1) {
            $id = substr($id, 0, -1);
            $idflag = substr($id, -1);
        } elseif (isset($s[2])) {
            $idflag = $s[2];
        }
        */

        $filter_url = $_conf['read_php'] . '?' . http_build_query(array(
                'host' => $this->thread->host,
                'bbs' => $this->thread->bbs,
                'key' => $this->thread->key,
                'ls' => 'all',
                'offline' => '1',
                'idpopup' => '1',
                'rf' => array(
                    'field' => ResFilter::FIELD_ID,
                    'method' => ResFilter::METHOD_JUST,
                    'match' => ResFilter::MATCH_ON,
                    'include' => ResFilter::INCLUDE_NONE,
                    'word' => $id,
                ),
            ), '', '&amp;') . $_conf['k_at_a'];

        if (isset($this->thread->idcount[$id]) && $this->thread->idcount[$id] > 0) {
            $num_ht = "(<a href=\"{$filter_url}\"{$this->target_at}>{$this->thread->idcount[$id]}</a>)";
        } else {
            return $idstr;
        }

        return "{$idstr}{$num_ht}";
    }

    // }}}
    // {{{ _linkToWikipeida()

    /**
     * @see ShowThread
     */
    protected function _linkToWikipeida($word)
    {
        global $_conf;

        $link = 'http://ja.wapedia.org/' . rawurlencode($word);
        if ($_conf['through_ime']) {
            $link = P2Util::throughIme($link);
        }

        return "<a href=\"{$link}\">{$word}</a>";
    }

    // }}}
    // {{{ quoteRes()

    /**
     * 引用変換（単独）
     *
     * @param   string $full >>1-100
     * @param   string $qsign >>
     * @param   string $appointed_num 1-100
     * @return string
     */
    public function quoteRes($full, $qsign, $appointed_num)
    {
        global $_conf, $STYLE;

        if ($appointed_num == '-') {
            return $full;
        }

        $appointed_num = mb_convert_kana($appointed_num, 'n');   // 全角数字を半角数字に変換
        if (preg_match('/\\D/', $appointed_num)) {
            $appointed_num = preg_replace('/\\D+/', '-', $appointed_num);
            return $this->quoteResRange($full, $qsign, $appointed_num);
        }
        if (preg_match('/^0/', $appointed_num)) {
            return $full;
        }
        $qnum = intval($appointed_num);
        if ($qnum < 1 || $qnum > $this->thread->rescount) {
            return $full;
        }

        $read_url = "{$_conf['read_php']}?host={$this->thread->host}&amp;bbs={$this->thread->bbs}&amp;key={$this->thread->key}&amp;offline=1&amp;ls={$appointed_num}";
        return "<a href=\"{$read_url}{$_conf['k_at_a']}\"{$this->respopup_at}{$this->target_at}>"
            . (in_array($qnum, $this->_aborn_nums) ? "<s><font color=\"{$STYLE['mobile_read_ngword_color']}\">{$full}</font></s>" :
                (in_array($qnum, $this->_ng_nums) ? "<s>{$full}</s>" : "{$full}")) . "</a>";
    }

    // }}}
    // {{{ quoteResRange()

    /**
     * 引用変換（範囲）
     *
     * @param   string $full >>1-100
     * @param   string $qsign >>
     * @param   string $appointed_num 1-100
     * @return string
     */
    public function quoteResRange($full, $qsign, $appointed_num)
    {
        global $_conf;

        if ($appointed_num == '-') {
            return $full;
        }

        list($from, $to) = explode('-', $appointed_num);
        if (!$from) {
            $from = 1;
        } elseif ($from < 1 || $from > $this->thread->rescount) {
            return $full;
        }
        // read.phpで表示範囲を判定するので冗長ではある
        if (!$to) {
            $to = min($from + $_conf['mobile.rnum_range'] - 1, $this->thread->rescount);
        } else {
            $to = min($to, $from + $_conf['mobile.rnum_range'] - 1, $this->thread->rescount);
        }

        $read_url = "{$_conf['read_php']}?host={$this->thread->host}&amp;bbs={$this->thread->bbs}&amp;key={$this->thread->key}&amp;offline=1&amp;ls={$from}-{$to}";

        return "<a href=\"{$read_url}{$_conf['k_at_a']}\"{$this->target_at}>{$full}</a>";
    }

    // }}}
    // {{{ ktaiExtUrl()

    /**
     * 携帯用外部URL変換
     *
     * @param   string $full
     * @param   string $url
     * @param   string $str
     * @return  string
     */
    public function ktaiExtUrl($full, $url, $str)
    {
        global $_conf;

        // 通勤ブラウザ
        $tsukin_link = '';
        if ($_conf['mobile.use_tsukin']) {
            $tsukin_url = 'http://www.sjk.co.jp/c/w.exe?y=' . rawurlencode($url);
            if ($_conf['through_ime']) {
                $tsukin_url = P2Util::throughIme($tsukin_url);
            }
            $tsukin_link = '<a href="' . $tsukin_url . '">通</a>';
        }

        // jigブラウザWEB http://bwXXXX.jig.jp/fweb/?_jig_=
        $jig_link = '';
        /*
        $jig_url = 'http://bwXXXX.jig.jp/fweb/?_jig_=' . rawurlencode($url);
        if ($_conf['through_ime']) {
            $jig_url = P2Util::throughIme($jig_url);
        }
        $jig_link = '<a href="'.$jig_url.'">j</a>';
        */

        if ($tsukin_link || $jig_link) {
            $ext_pre = '(' . $tsukin_link . (($tsukin_link && $jig_link) ? '|' : '') . $jig_link . ')';
        } else {
            $ext_pre = '';
        }

        if ($_conf['through_ime']) {
            $url = P2Util::throughIme($url);
        }
        return $ext_pre . '<a href="' . $url . '">' . $str . '</a>';
    }

    // }}}
    // {{{ ktaiExtUrlCallback()

    /**
     * 携帯用外部URL変換
     *
     * @param   array $s 正規表現にマッチした要素の配列
     * @return  string
     */
    public function ktaiExtUrlCallback(array $s)
    {
        return $this->ktaiExtUrl($s[0], $s[1], $s[2]);
    }

    // }}}
    // {{{ transLinkDo()から呼び出されるURL書き換えメソッド
    /**
     * これらのメソッドは引数が処理対象パターンに合致しないとfalseを返し、
     * transLinkDo()はfalseが返ってくると$_url_handlersに登録されている次の関数/メソッドに処理させようとする。
     */
    // {{{ plugin_linkURL()

    /**
     * URLリンク
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
            return "<a href=\"{$link_url}\" target=\"_blank\">{$str}</a>";
        }
        return false;
    }

    // }}}
    // {{{ plugin_link2chSubject()

    /**
     * 2ch bbspink 板リンク
     */
    public function plugin_link2chSubject($url, $purl, $str)
    {
        global $_conf;

        if (preg_match('{^https?://(.+)/(.+)/$}', $purl[0], $m)) {
            //rep2に登録されている板ならばリンクする
            if (P2HostMgr::isRegisteredBbs($m[1], $m[2])) {
                $subject_url = "{$_conf['subject_php']}?host={$m[1]}&amp;bbs={$m[2]}";
                return "<a href=\"{$url}\">{$str}</a> [<a href=\"{$subject_url}{$_conf['k_at_a']}\">板をp2で開く</a>]";
            }
        }
        return false;
    }

    // }}}
    // {{{ plugin_linkThread()

    /**
     * スレッドリンク
     */
    public function plugin_linkThread($url, $purl, $str)
    {
        global $_conf;

        list($nama_url, $host, $bbs, $key, $ls) = P2Util::detectThread($purl[0]);
        if ($host && $bbs && $key) {
            $read_url = "{$_conf['read_php']}?host={$host}&amp;bbs={$bbs}&amp;key={$key}&amp;ls={$ls}";
            return "<a href=\"{$read_url}{$_conf['k_at_a']}\">{$str}</a>";
        }

        return false;
    }

    // }}}
    // {{{ plugin_viewImage()

    /**
     * 画像リンク変換
     */
    public function plugin_viewImage($url, $purl, $str)
    {
        global $_conf;

        if (P2HostMgr::isUrlWikipediaJa($url)) {
            return false;
        }

        if (preg_match('{^https?://.+?\\.(jpe?g|gif|png)$}i', $url) && empty($purl['query'])) {
            $picto_url = 'http://pic.to/' . $purl['host'] . $purl['path'];
            $picto_tag = '<a href="' . $picto_url . '">(ﾋﾟ)</a> ';
            if ($_conf['through_ime']) {
                $link_url = P2Util::throughIme($purl[0]);
                $picto_url = P2Util::throughIme($picto_url);
            } else {
                $link_url = $url;
            }
            return "{$picto_tag}<a href=\"{$link_url}\">{$str}</a>";
        }

        return false;
    }

    // }}}
    // {{{ plugin_replaceImageUrl()

    public function plugin_replaceImageUrl($url, $purl, $str)
    {
        global $_conf;
        global $pre_thumb_unlimited, $pre_thumb_ignore_limit, $pre_thumb_limit_k;

        if (P2HostMgr::isUrlWikipediaJa($url)) {
            return false;
        }

        // if (preg_match('{^https?://.+?\\.(jpe?g|gif|png)$}i', $url) && empty($purl['query'])) {
        // +Wiki
        global $replaceImageUrlCtl;

        $url = $purl[0];
        $replaced = $replaceImageUrlCtl->replaceImageUrl($url);
        if (!$replaced[0]) {
            return false;
        }

        foreach ($replaced as $v) {
            // インラインプレビューの有効判定
            if ($pre_thumb_unlimited || $pre_thumb_ignore_limit || $pre_thumb_limit_k > 0) {
                $inline_preview_flag = true;
                $inline_preview_done = false;
            } else {
                $inline_preview_flag = false;
                $inline_preview_done = false;
            }

            // +Wiki
            // $url_en = rawurlencode($url);
            $url_ht = $url;
            $url_en = rawurlencode($v['url']);
            $ref_en = $v['referer'] ? '&amp;ref=' . rawurlencode($v['referer']) : '';
            $img_str = null;
            $img_id = null;

            $icdb = new ImageCache2_DataObject_Images();

            // r=0:リンク;r=1:リダイレクト;r=2:PHPで表示
            // t=0:オリジナル;t=1:PC用サムネイル;t=2:携帯用サムネイル;t=3:中間イメージ
            $img_url = 'ic2.php?r=0&amp;t=2&amp;uri=' . $url_en . $ref_en;
            $img_url2 = 'ic2.php?r=0&amp;t=2&amp;id=';
            $src_url = 'ic2.php?r=1&amp;t=0&amp;uri=' . $url_en . $ref_en;
            $src_url2 = 'ic2.php?r=1&amp;t=0&amp;id=';
            $src_exists = false;

            // お気にスレ自動画像ランク
            $rank = null;
            if ($_conf['expack.ic2.fav_auto_rank']) {
                $rank = $this->getAutoFavRank();
            }

            // DBに画像情報が登録されていたとき
            if ($icdb->get($v['url'])) {
                $img_id = $icdb->id;

                // ウィルスに感染していたファイルのとき
                if ($icdb->mime == 'clamscan/infected') {
                    return '[IC2:ウィルス警告]';
                }
                // あぼーん画像のとき
                if ($icdb->rank < 0) {
                    return '[IC2:あぼーん画像]';
                }

                // オリジナルの有無を確認
                if (file_exists($this->thumbnailer->srcPath($icdb->size, $icdb->md5, $icdb->mime))) {
                    $src_exists = true;
                    $img_url = $img_url2 . $icdb->id;
                    $src_url = $this->thumbnailer->srcUrl($icdb->size, $icdb->md5, $icdb->mime);
                } else {
                    $img_url = $this->thumbnailer->thumbUrl($icdb->size, $icdb->md5, $icdb->mime);
                    $src_url = $src_url2 . $icdb->id;
                }

                // インラインプレビューが有効のとき
                $prv_url = null;
                if ($this->thumbnailer->ini['General']['inline'] == 1) {
                    // PCでread_new_k.phpにアクセスしたとき等
                    if (!isset($this->inline_prvw) || !is_object($this->inline_prvw)) {
                        $this->inline_prvw = $this->thumbnailer;
                    }
                    $prv_url = $this->inline_prvw->thumbUrl($icdb->size, $icdb->md5, $icdb->mime);

                    // サムネイル表示制限数以内のとき
                    if ($inline_preview_flag) {
                        // プレビュー画像が作られているかどうかでimg要素の属性を決定
                        if (file_exists($this->inline_prvw->thumbPath($icdb->size, $icdb->md5, $icdb->mime))) {
                            $prvw_size = explode('x', $this->inline_prvw->calc($icdb->width, $icdb->height));
                            $img_str = "<img src=\"{$prv_url}\" width=\"{$prvw_size[0]}\" height=\"{$prvw_size[1]}\">";
                        } else {
                            $r_type = ($this->thumbnailer->ini['General']['redirect'] == 1) ? 1 : 2;
                            if ($src_exists) {
                                $prv_url = "ic2.php?r={$r_type}&amp;t=1&amp;id={$icdb->id}";
                            } else {
                                $prv_url = "ic2.php?r={$r_type}&amp;t=1&amp;uri={$url_en}";
                            }
                            $prv_url .= $this->img_dpr_query;
                            if ($this->img_dpr === 1.5 || $this->img_dpr === 2.0) {
                                $prv_onload = sprintf(' onload="autoAdjustImgSize(this, %f);"', $this->img_dpr);
                            } else {
                                $prv_onload = '';
                            }
                            $img_str = "<img src=\"{$prv_url}\"{$prv_onload} width=\"{$prvw_size[0]}\" height=\"{$prvw_size[1]}\">";
                        }
                        $inline_preview_done = true;
                    } else {
                        $img_str = '[p2:既得画像(ﾗﾝｸ:' . $icdb->rank . ')]';
                    }
                }

                // 自動スレタイメモ機能がONでスレタイが記録されていないときはDBを更新
                if (!is_null($this->img_memo) && strpos($icdb->memo, $this->img_memo) === false) {
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
                    self::isAutoFavRankOverride($icdb->rank, $rank)
                ) {
                    if ($update === null) {
                        $update = new ImageCache2_DataObject_Images();
                        $update->whereAddQuoted('uri', '=', $v['url']);
                    }
                    $update->rank = $rank;

                }
                if ($update !== null) {
                    $update->update();
                }

                // 画像がキャッシュされていないとき
                // 自動スレタイメモ機能がONならクエリにUTF-8エンコードしたスレタイを含める
            } else {
                // 画像がブラックリストorエラーログにあるか確認
                if (false !== ($errcode = $icdb->ic2_isError($v['url']))) {
                    return "<s>[IC2:ｴﾗｰ({$errcode})]</s>";
                }

                // インラインプレビューが有効で、サムネイル表示制限数以内なら
                if ($this->thumbnailer->ini['General']['inline'] == 1 && $inline_preview_flag) {
                    $rank_str = ($rank !== null) ? '&rank=' . $rank : '';
                    $img_str = "<img src=\"ic2.php?r=2&amp;t=1&amp;uri={$url_en}{$this->img_memo_query}{$rank_str}{$ref_en}\" width=\"{$prvw_size[0]}\" height=\"{$prvw_size[1]}\">";
                    $inline_preview_done = true;
                } else {
                    $img_url .= $this->img_memo_query;
                }
            }

            // 表示数制限をデクリメント
            if ($inline_preview_flag && $inline_preview_done) {
                $pre_thumb_limit_k--;
            }

            if (!empty($_SERVER['REQUEST_URI'])) {
                $backto = '&amp;from=' . rawurlencode($_SERVER['REQUEST_URI']);
            } else {
                $backto = '';
            }

            $result = "";

            if (is_null($img_str)) {
                $result .= sprintf('<a href="%s%s">[IC2:%s:%s]</a>',
                    $img_url,
                    $backto,
                    p2h($purl['host']),
                    p2h(basename($purl['path']))
                );
            }

            $img_title = p2h($purl['host'])
                . '&#10;'
                . p2h(basename($purl['path']));
            $result .= "<a class=\"limelight\" href=\"{$src_url}\" title=\"{$img_title}\" target=\"_blank\">{$img_str}</a>"
                //. ' <img class="ic2-show-info" src="img/s2a.png" width="16" height="16" onclick="ic2info.show('
                . ' <input type="button" class="ic2-show-info" value="i" onclick="ic2info.show('
                . (($img_id) ? $img_id : "'{$v['url']}'") . ', event)">';
        }

        $linkUrlResult = $this->plugin_linkURL($url, $purl, $str);
        if ($linkUrlResult !== false) {
            $result .= $linkUrlResult;
        }

        return $result;
    }

    // }}}
    // {{{ plugin_linkPlugin()

    /**
     * +Wiki:リンクプラグイン
     */
    public function plugin_linkPlugin($url, $purl, $str)
    {
        return $GLOBALS['linkPluginCtl']->replaceLinkToHTML($url, $str);
    }
    // }}}
    // {{{ _quotebackHorizontalListHtml()

    protected function _quotebackHorizontalListHtml($anchors, $resnum)
    {
        global $_conf;

        if ($_GET['showbl']) {
            return '';
        }
        $anchors = array_diff($anchors, array($resnum));
        if (!$anchors) {
            return '';
        }
        $ret = '';

        $plus = array();
        foreach ($anchors as $num) {
            $plus = array_merge($plus, $this->_getQuotebackCount($num));
        }
        $plus = array_unique($plus);
        $plus_cnt = count(array_diff($plus, $anchors));
        $plus_str = count($plus) > 0 ? '+' . ($plus_cnt > 0 ? $plus_cnt : '') : '';

        $url = $_conf['read_php'] . '?' . http_build_query(array(
                'host' => $this->thread->host,
                'bbs' => $this->thread->bbs,
                'key' => $this->thread->key,
                'ls' => $resnum,
                'offline' => '1',
                'showbl' => '1',
            ), '', '&amp;') . $_conf['k_at_a'];

        $suppress = false;
        $n = 0;
        $reslist = array();
        foreach ($anchors as $anchor) {
            if ($anchor == $resnum) continue;
            $n++;
            if ($_conf['mobile.backlink_list.suppress'] > 0
                && $n > $_conf['mobile.backlink_list.suppress']
            ) {
                $suppress = true;
                break;
            }
            $reslist[] = $this->quoteRes('>>' . $anchor, '>>', $anchor);
        }

        $res_navi = '';
        if ($_conf['mobile.backlink_list.openres_navi'] == 1
            || ($_conf['mobile.backlink_list.openres_navi'] == 2
                && $suppress === true)
        ) {
            if (count($anchors) > 1 || $plus_str) {
                $res_navi = "(<a href=\"{$url}\"{$this->target_at}>"
                    . (count($anchors) > 1 ? count($anchors) : '')
                    . $plus_str . '</a>)';
            }
        }

        $res_count = count($reslist);
        if ($res_count === 1 && $suppress === true && $_conf['mobile.backlink_list.suppress'] == 1) {
            $ret .= sprintf('<div>【参照ﾚｽ %s】</div>', $res_navi);
        } elseif ($res_count === 1 && $suppress === false) {
            $ret .= sprintf('<div>【参照ﾚｽ %s%s】</div>', $reslist[0], $res_navi);
        } else {
            for ($n = 0; $n < $res_count; $n++) {
                $ret .= '<div>【参照ﾚｽ ' . $reslist[$n] . '】</div>';
            }
            $ret .= '<div>' . ($suppress ? '略' : '') . $res_navi . '</div>';
        }

        return '<div class="reslist">' . $ret . '</div>';
    }

    // }}}
    // {{{ _getQuotebackCount()

    protected function _getQuotebackCount($num, $checked = null)
    {
        $ret = array();
        if ($checked === null) {
            $checked = array();
        }
        $checked[] = $num;
        $quotes = $this->getQuoteFrom();
        if ($quotes[$num]) {
            $ret = $quotes[$num];
            foreach ($quotes[$num] as $quote_num) {
                if ($quote_num != $num && !in_array($quote_num, $checked)) {
                    $ret = array_merge($ret, $this->_getQuotebackCount($quote_num, array_merge($ret, $checked)));
                }
            }
        }
        return $ret;
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
