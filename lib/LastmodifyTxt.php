<?php

// {{{ LastmodifyTxt

/**
 * LastmodifyTxtクラス
 */
class LastmodifyTxt
{
    // {{{ properties

    public $host;
    public $bbs;
    public $lastmodify_url;
    public $lastmodify_file;
    public $lastmodify_lines;
    public $storage;

    // }}}
    // {{{ constructor

    /**
     * コンストラクタ
     */
    public function __construct($host, $bbs)
    {
        global $_conf;
        $this->host = $host;
        $this->bbs =  $bbs;
        $this->storage = 'file';

        $this->lastmodify_file = P2Util::datDirOfHostBbs($host, $bbs) . 'lastmodify.txt';
        // 接続先が 2ch.net / 5ch / pink 以外の場合ダウンロードしない
        if (!P2HostMgr::isHost2chs($this->host)) {
        	return ;
        }
        $this->lastmodify_url = 'https://' . $host . '/' . $bbs . '/lastmodify.txt';

        // lastmodify.txtを ダウンロード＆セットする
        $this->dlAndSetLastmodify();
    }

    // }}}
    // {{{ dlAndSetLastmodify()

    /**
     * lastmodify.txtをダウンロード＆セットする
     *
     * @return boolean セットできれば true、できなければ false
     */
    public function dlAndSetLastmodify()
    {
        $cont = $this->downloadLastmodify();
        if ($this->setLastmodifyLines($cont)) {
            return true;
        } else {
            return false;
        }
    }

    // }}}
    // {{{ downloadLastmodify()

    /**
     * lastmodify.txtをダウンロードする
     *
     * @return string lastmodify.txt の中身
     */
    public function downloadLastmodify()
    {
        global $_conf;

        if ($this->storage === 'file') {
            FileCtl::mkdirFor($this->lastmodify_file); // 板ディレクトリが無ければ作る

            if (file_exists($this->lastmodify_file)) {
                if (!empty($_REQUEST['norefresh']) || (empty($_REQUEST['refresh']) && isset($_REQUEST['word']))) {
                    return;    // 更新しない場合は、その場で抜けてしまう
                } elseif (!empty($GLOBALS['expack.subject.multi-threaded-download.done'])) {
                    return;    // 並列ダウンロード済の場合も抜ける
                } elseif (empty($_POST['newthread']) and $this->isLastmodifyTxtFresh()) {
                    return;    // 新規スレ立て時でなく、更新が新しい場合も抜ける
                }
                $modified = http_date(filemtime($this->lastmodify_file));
            } else {
                $modified = false;
            }
        }

        // DL
        try {
            $req = P2Commun::createHTTPRequest($this->lastmodify_url, HTTP_Request2::METHOD_GET);
            $modified && $req->setHeader("If-Modified-Since", $modified);

            $response = P2Commun::getHTTPResponse($req);

            $code = $response->getStatus();
            $body = '';
            if ($code == 302) {
                // ホストの移転を追跡
                $new_host = P2HostMgr::getCurrentHost($this->host, $this->bbs);
                if ($new_host != $this->host) {
                    $aNewLastmodifyTxt = new LastmodifyTxt($new_host, $this->bbs);
                    $body = $aNewLastmodifyTxt->downloadLastmodify();
                    return $body;
                }
            } elseif ($code == 200 || $code == 206) {
                //var_dump($response->getHeader());
                $body = $response->getBody();
                // したらば or be.2ch.net ならEUCをSJISに変換
                if (P2HostMgr::isHostJbbsShitaraba($this->host) || P2HostMgr::isHostBe2chs($this->host)) {
                    $body = mb_convert_encoding($body, 'CP932', 'CP51932');
                }
                if (FileCtl::file_write_contents($this->lastmodify_file, $body) === false) {
                    p2die('cannot write file');
                }
            } elseif ($code == 304) {
                // touchすることで更新インターバルが効くので、しばらく再チェックされなくなる
                // （変更がないのに修正時間を更新するのは、少し気が進まないが、ここでは特に問題ないだろう）
                if ($this->storage === 'file') {
                    touch($this->lastmodify_file);
                }
            } else {
                $error_msg = $code;
            }
        } catch (Exception $e) {
            $error_msg = $e->getMessage();
        }

        if (isset($error_msg) && strlen($error_msg) > 0) {
            $url_t = P2Util::throughIme($this->lastmodify_url);
            $info_msg_ht = "<p class=\"info-msg\">Error: {$error_msg}<br>";
            $info_msg_ht .= "rep2 info: <a href=\"{$url_t}\"{$_conf['ext_win_target_at']}>{$this->lastmodify_url}</a> に接続できませんでした。</p>";
            P2Util::pushInfoHtml($info_msg_ht);
            $body = '';
        }

        return $body;
    }

    // }}}
    // {{{ isLastmodifyTxtFresh()

    /**
     * lastmodify.txt が新鮮なら true を返す
     *
     * @return boolean 新鮮なら true。そうでなければ false。
     */
    public function isLastmodifyTxtFresh()
    {
        global $_conf;

        // キャッシュがある場合
        if (file_exists($this->lastmodify_file)) {
            // キャッシュの更新が指定時間以内なら
            // clearstatcache();
            if (filemtime($this->lastmodify_file) > time() - $_conf['sb_dl_interval']) {
                return true;
            }
        }

        return false;
    }

    // }}}
    // {{{ setLastmodifyLines()

    /**
     * lastmodify.txt を読み込む
     *
     * 成功すれば、$this->lastmodify_lines がセットされる
     *
     * @param string $cont これは eashm 用に渡している。
     * @return boolean 実行成否
     */
    public function setLastmodifyLines($cont = '')
    {
        $this->lastmodify_lines = FileCtl::file_read_lines($this->lastmodify_file);

        if ($this->lastmodify_lines) {
            return true;
        } else {
            return false;
        }
    }

    // }}}
    // {{{ getThreadExtend()

    /**
     * extdat を読み込む
     *
     * 成功すれば、$this->lastmodify_lines がセットされる
     *
     * @param string $cont これは eashm 用に渡している。
     * @return boolean 実行成否
     */
    public function getThreadExtend($key)
    {
        // 接続先が 2ch / 5ch / pink 以外の場合は '' を返す
        if (!file_exists($this->lastmodify_file)) {
            return '';
        }

        foreach($this->lastmodify_lines as $l){
            if (preg_match("/^($key\.(?:dat|cgi))<>(.+?)<>(\d+)<>(\d+)<>(\d+)<>(\d+)<>(.+?)<>(.+?)<>/", $l, $matches)) { break; }
        }
        return $matches[8];
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
