<?php
/**
 * rep2 - スレッド リード クラス
 */

// +Wiki
require_once P2_LIB_DIR . '/wiki/DatPluginCtl.php';

// {{{ ThreadRead

/**
 * スレッドリードクラス
 */
class ThreadRead extends Thread {
    // {{{ properties
    public $datlines; // datから読み込んだラインを格納する配列
    public $resrange; // array('start' => i, 'to' => i, 'nofirst' => bool)
    public $onbytes; // サーバから取得したdatサイズ
    public $diedat; // サーバからdat取得しようとしてできなかった時にtrueがセットされる
    public $onthefly; // ローカルにdat保存しないオンザフライ読み込みならtrue
    public $idp; // レス番号をキー、IDの前の文字列 ("ID:", " " 等) を値とする連想配列
    public $ids; // レス番号をキー、IDを値とする連想配列
    public $idcount; // IDをキー、出現回数を値とする連想配列
    public $getdat_error_msg_ht; // dat取得に失敗した時に表示されるメッセージ（HTML）
    public $old_host; // ホスト移転検出時、移転前のホストを保持する
    private $getdat_error_body; // dat取得に失敗した時に203で取得できたBODY
    public $datochi_residuums; // dat取得に失敗した時に203で取得できたdatlineの配列（レス番=>datline）

    // }}}
    // {{{ constructor

    /**
     * コンストラクタ
     */
    public function __construct() {
        parent::__construct ();
        $this->getdat_error_msg_ht = "";
    }

    // }}}
    // {{{ downloadDat()

    /**
     * DATをダウンロードする
     */
    public function downloadDat() {
        global $_conf;

        // まちBBS
        if (P2Util::isHostMachiBbs ($this->host)) {
            return DownloadDatMachiBbs::invoke ($this);
            // JBBS@したらば
        } elseif (P2Util::isHostJbbsShitaraba ($this->host)) {
            if (! function_exists ('shitarabaDownload')) {
                include P2_LIB_DIR . '/read_shitaraba.inc.php';
            }
            return shitarabaDownload ($this);

            // 2ch系
        } else {
            $this->getDatBytesFromLocalDat (); // $aThread->length をset
            $pinktest = "/\w+\.bbspink.com/";

            // 2ch bbspink●読み
            if (P2Util::isHost2chs ($this->host) && ! empty ($_GET['maru'])) {
                // ログインしてなければ or ログイン後、24時間以上経過していたら自動再ログイン
                if (! file_exists ($_conf['sid2ch_php']) || ! empty ($_REQUEST['relogin2ch']) || (filemtime ($_conf['sid2ch_php']) < time () - 60 * 60 * 24)) {
                    if (! function_exists ('login2ch')) {
                        include P2_LIB_DIR . '/login2ch.inc.php';
                    }
                    if (! login2ch ()) {
                        $this->getdat_error_msg_ht .= $this->get2chDatError ();
                        $this->diedat = true;
                        return false;
                    }
                }

                include $_conf['sid2ch_php'];
                return $this->_downloadDat2chMaru ($uaMona, $SID2ch);

            // 2chの過去ログ倉庫読み
            } elseif (! empty ($_GET['kakolog']) && ! empty ($_GET['kakoget'])) {
                if ($_GET['kakoget'] == 1) {
                    $ext = '.dat.gz';
                } elseif ($_GET['kakoget'] == 2) {
                    $ext = '.dat';
                }
                return $this->_downloadDat2chKako ($_GET['kakolog'], $ext);

            // 2ch はAPI経由で落とす
            } elseif (P2Util::isHost2chs ($this->host) && $_conf['2chapi_use'] && empty ($_GET['olddat'])) {

                // ログインしてなければ or ログイン後、設定した時間経過していたら自動再ログイン
                if (! file_exists ($_conf['sid2chapi_php']) || ! empty ($_REQUEST['relogin2chapi']) || (filemtime ($_conf['sid2chapi_php']) < time () - 60 * 60 * $_conf['2chapi_interval'])) {
                    if (! function_exists ('authenticate_2chapi')) {
                        include P2_LIB_DIR . '/auth2chapi.inc.php';
                    }
                    if (! authenticate_2chapi ()) {
                        $this->getdat_error_msg_ht .= $this->get2chDatError ();
                        $this->diedat = true;
                        return false;
                    }
                }

                include $_conf['sid2chapi_php'];
                return $this->_downloadDat2chAPI ($SID2chAPI, $this->length);
            } else {

                // 2ch 以外の外部板
                // DATを差分DLする
                return $this->_downloadDat2ch ($this->length);
            }
        }
    }

    // }}}
    // {{{ _downloadDat2chAPI()

    /**
     * 2chAPIで DAT を差分ダウンロードする
     *
     * @return mix 取得できたか、更新がなかった場合はtrueを返す
     */
    protected function _downloadDat2chAPI($sid, $from_bytes) {
        global $_conf;
        global $debug;

        $AppKey = $_conf['2chapi_appkey'];
        $AppName = $_conf['2chapi_appname'];
        $HMKey = $_conf['2chapi_hmkey'];
        $ReadUA = sprintf ($_conf['2chapi_ua.read'], $AppName);

        if (! ($this->host && $this->bbs && $this->key)) {
            return false;
        }

        // >>1プレビューの時は差分取得しなくて良いので常にtrue(新着無し)を返す
        if (is_readable ($this->keydat) && ! empty ($_GET['one'])) {
            return true;
        }

        if ($sid == '') {
            return false;
        }

        $from_bytes = intval ($from_bytes);

        if ($from_bytes == 0) {
            $zero_read = true;
        } else {
            $zero_read = false;
            $from_bytes = $from_bytes - 1;
        }

        $serverName = explode ('.', $this->host);
        // $url = "http://{$this->host}/{$this->bbs}/dat/{$this->key}.dat";
        // $url="http://news2.2ch.net/test/read.cgi?bbs=newsplus&key=1038486598";

        if($_conf['2chapi_ssl.read']) {
            $url = 'https://api.2ch.net/v1/';
        } else {
            $url = 'http://api.2ch.net/v1/';
        }

        $url .= $serverName[0] . '/' . $this->bbs . '/' . $this->key;
        $message = '/v1/' . $serverName[0] . '/' . $this->bbs . '/' . $this->key . $sid . $AppKey;
        $HB = hash_hmac ("sha256", $message, $HMKey);

        $purl = parse_url ($url); // URL分解

        try {
            $req = P2Util::getHTTPRequest2 ($url, HTTP_Request2::METHOD_POST);

            // ヘッダ
            $req->setHeader ('User-Agent', $ReadUA);

            if (! empty ($_GET['one'])) {
                // >>1プレビューの時はサーバーに最初の部分だけ請求
                $req->setHeader ('Range', "bytes=0-8192");
            } elseif (! $zero_read) {
                $req->setHeader ('Range', sprintf ('bytes=%d-', $from_bytes) );
            }

            if ($this->modified) {
                $req->setHeader ('If-Modified-Since', $this->modified);
            }

            // Basic認証用のヘッダ
            if (isset ($purl['user']) && isset ($purl['pass'])) {
                $req->setAuth ($purl['user'], $purl['pass'], HTTP_Request2::AUTH_BASIC);
            }

            // POSTする内容
            $req->addPostParameter (array (
                    'sid' => $sid,
                    'hobo' => $HB,
                    'appkey' => $AppKey
            ));

            // POSTデータの送信
            $response = $req->send ();

            $code = $response->getStatus ();

            if ($code == '200' || $code == '206') { // Partial Content
                $body = $response->getBody ();

                if (! empty ($_GET['one'])) {
                    $cut = mb_strrpos ($body, "\n");
                    $body = mb_substr ($body, 0, $cut === false ? mb_strlen ($body) : $cut + 1);
                    $this->onbytes = strlen ($body);
                } elseif ($zero_read) {
                    $this->onbytes = intval ($response->getHeader ('Content-Length'));
                } else {
                    if (preg_match ('@^bytes ([^/]+)/([0-9]+)@i', $response->getHeader ('Content-Range'), $matches)) {
                        $this->onbytes = intval ($matches[2]);
                    }
                }

                $this->modified = $response->getHeader ('Last-Modified');

                // 1行目を切り出す
                $posLF = mb_strpos ($body, "\n");
                $firstmsg = mb_substr ($body, 0, $posLF === false ? mb_strlen ($body) : $posLF);

                // ngで始まってたらapiのエラーの可能性
                if (preg_match ("/^ng \((.*)\)$/", $firstmsg)) {
                    if (strstr ($firstmsg, "not valid")) {
                        // sidが無効になった可能性。もう一回認証するため最初からやり直し。
                        if (empty ($_REQUEST['relogin2chapi'])) {
                            $_REQUEST['relogin2chapi'] = true;
                            return $this->downloadDat ();
                        }
                    }
                    $this->getdat_error_msg_ht .= "<p>rep2 error: API経由でのスレッド取得に失敗しました。" . $firstmsg . "</p>";
                    $this->getdat_error_msg_ht .= " [<a href=\"{$_conf['read_php']}?host={$this->host}&amp;bbs={$this->bbs}&amp;key={$this->key}&amp;ls={$this->ls}&amp;relogin2chapi=true\">APIで再取得を試みる</a>]";
                    $this->getdat_error_msg_ht .= " [<a href=\"{$_conf['read_php']}?host={$this->host}&amp;bbs={$this->bbs}&amp;key={$this->key}&amp;ls={$this->ls}&amp;olddat=true\">旧datで再取得を試みる</a>]";
                    $this->diedat = true;
                    return false;
                } elseif (mb_strpos ($firstmsg, "２ちゃんねる ★<><>2015/03/13(金) 00:00:00.00 ID:????????<> 3月13日より２") === 0) {
                    return $this->_downloadDat2chNotFound ('404');
                }
                unset ($firstmsg);

                // 末尾の改行であぼーんチェック
                if (! $zero_read) {
                    if (substr ($body, 0, 1) != "\n") {
                        // echo "あぼーん検出";
                        $this->onbytes = 0;
                        $this->modified = null;
                        return $this->_downloadDat2chAPI ($sid, 0); // あぼーん検出。全部取り直し。
                    }
                    $body = substr ($body, 1);
                }

                // 2行目を切り出す
                $lines = explode("\n", $body);
                $secondmsg = $lines[1];

                // 2行目が過去ログであることを示しているようであれば過去ログリンクを表示
                if (mb_strpos ($secondmsg, "２ちゃんねる ★<><>2015/05/31(日) 00:00:00.00 ID:????????<> このスレッドは過去ログです。") === 0) {
                    return $this->_downloadDat2chNotFound ('302');
                }
                unset ($secondmsg);
                unset ($lines);

                $file_append = ($zero_read) ? 0 : FILE_APPEND;

                if (FileCtl::file_write_contents ($this->keydat, $body, $file_append) === false) {
                    p2die ('cannot write file.');
                }

                // $GLOBALS['debug'] && $GLOBALS['profiler']->enterSection("dat_size_check");
                // 取得後サイズチェック
                if ($zero_read == false && $this->onbytes) {
                    $this->getDatBytesFromLocalDat (); // $aThread->length をset
                    if ($this->onbytes != $this->length) {
                        $this->onbytes = 0;
                        $this->modified = null;
                        P2Util::pushInfoHtml ("<p>rep2 info: {$this->onbytes}/{$this->length} ファイルサイズが変なので、datを再取得</p>");
                        // $GLOBALS['debug'] && $GLOBALS['profiler']->leaveSection("dat_size_check");
                        return $this->_downloadDat2chAPI ($sid, 0); // datサイズは不正。全部取り直し。
                    }
                }

                $this->isonline = true;
                return true;
            } elseif ($code == '302') { // Found
                                        // ホストの移転を追跡
                $new_host = BbsMap::getCurrentHost ($this->host, $this->bbs);
                if ($new_host != $this->host) {
                    $this->old_host = $this->host;
                    $this->host = $new_host;
                    return $this->_downloadDat2chAPI ($sid, $from_bytes);
                } else {
                    return $this->_downloadDat2chNotFound ($code);
                }
            } elseif ($code == '304') {
                $this->isonline = true;
                return '304 Not Modified';
            } elseif ($code == '416') { // Requested Range Not Satisfiable
                                        // echo "あぼーん検出";
                $this->onbytes = 0;
                $this->modified = null;
                return $this->_downloadDat2chAPI ($sid, 0); // あぼーん検出。全部取り直し。
            } else {
                return $this->_downloadDat2chNotFound ($code);
            }

        } catch (Exception $e) {
            $this->getdat_error_msg_ht .= "<p>サーバ接続エラー: " . $e->getMessage ();
            $this->getdat_error_msg_ht .= "<br>rep2 error: 板サーバへの接続に失敗しました。</p>";
            $this->diedat = true;
            return false;
        }
    }

    // }}}
    // {{{ _downloadDat2ch()

    /**
     * 標準方法で 2ch互換 DAT を差分ダウンロードする
     *
     * @return mix 取得できたか、更新がなかった場合はtrueを返す
     */
    protected function _downloadDat2ch($from_bytes) {
        global $_conf;
        global $debug;

        if (! ($this->host && $this->bbs && $this->key)) {
            return false;
        }

        // >>1プレビューの時は差分取得しなくて良いので常にtrue(新着無し)を返す
        if (is_readable ($this->keydat) && ! empty ($_GET['one'])) {
            return true;
        }

        $from_bytes = intval ($from_bytes);

        if ($from_bytes == 0) {
            $zero_read = true;
        } else {
            $zero_read = false;
            $from_bytes = $from_bytes - 1;
        }

        $url = "http://{$this->host}/{$this->bbs}/dat/{$this->key}.dat";
        // $url="http://news2.2ch.net/test/read.cgi?bbs=newsplus&key=1038486598";

        $purl = parse_url ($url); // URL分解
                                    // $request .= "Accept-Charset: Shift_JIS\r\n";
                                    // $request .= "Accept-Encoding: gzip, deflate\r\n";

        try {
            $req = P2Util::getHTTPRequest2 ($url, HTTP_Request2::METHOD_GET);
            // ヘッダ
            $req->setHeader ('Referer', "http://{$purl['host']}/{$this->bbs}/");

            if (! empty ($_GET['one'])) {
                // >>1プレビューの時はサーバーに最初の部分だけ請求
                $req->setHeader ('Range', "bytes=0-8192");
            } elseif (! $zero_read) {
                $req->setHeader ('Range', sprintf ('bytes=%d-', $from_bytes) );
            }

            if ($this->modified) {
                $req->setHeader ('If-Modified-Since', $this->modified);
            }

            // Basic認証用のヘッダ
            if (isset ($purl['user']) && isset ($purl['pass'])) {
                $req->setAuth ($purl['user'], $purl['pass'], HTTP_Request2::AUTH_BASIC);
            }

            // Requestの送信
            $response = $req->send ();

            $code = $response->getStatus ();

            if ($code == '200' || $code == '206') { // Partial Content
                $body = $response->getBody ();

                if (! empty ($_GET['one'])) {
                    $cut = mb_strrpos ($body, "\n");
                    $body = mb_substr ($body, 0, $cut === false ? mb_strlen ($body) : $cut + 1);
                    $this->onbytes = strlen ($body);
                } elseif ($zero_read) {
                    $this->onbytes = intval ($response->getHeader ('Content-Length'));
                } else {
                    if (preg_match ('@^bytes ([^/]+)/([0-9]+)@i', $response->getHeader ('Content-Range'), $matches)) {
                        $this->onbytes = intval ($matches[2]);
                    }
                }

                $this->modified = $response->getHeader ('Last-Modified');

                // ホストが2chの時にDATを利用できない旨のメッセージが出たらエラーとする（DAT破損対策）
                if (P2Util::isHost2chs ($this->host)) {
                    // 1行目を切り出す
                    $posLF = mb_strpos ($body, "\n");
                    $firstmsg = mb_substr ($body, 0, $posLF === false ? mb_strlen ($body) : $posLF);

                    if (mb_strpos ($firstmsg, "２ちゃんねる ★<><>2015/03/13(金) 00:00:00.00 ID:????????<> 3月13日より２") === 0) {
                        $this->getdat_error_msg_ht .= "<p>rep2 error: 板サーバから接続を拒否されました<br>rep2 info: 2ちゃんねるのDAT提供は終了しました</p>";
                        $this->diedat = true;
                        return false;
                    }
                    unset ($firstmsg);
                }

                // 末尾の改行であぼーんチェック
                if (! $zero_read) {
                    if (substr ($body, 0, 1) != "\n") {
                        // echo "あぼーん検出";
                        $this->onbytes = 0;
                        $this->modified = null;
                        return $this->_downloadDat2ch (0); // あぼーん検出。全部取り直し。
                    }
                    $body = substr ($body, 1);
                }

                $file_append = ($zero_read) ? 0 : FILE_APPEND;

                if (FileCtl::file_write_contents ($this->keydat, $body, $file_append) === false) {
                    p2die ('cannot write file.');
                }

                // $GLOBALS['debug'] && $GLOBALS['profiler']->enterSection("dat_size_check");
                // 取得後サイズチェック
                if ($zero_read == false && $this->onbytes) {
                    $this->getDatBytesFromLocalDat (); // $aThread->length をset
                    if ($this->onbytes != $this->length) {
                        $this->onbytes = 0;
                        $this->modified = null;
                        P2Util::pushInfoHtml ("<p>rep2 info: {$this->onbytes}/{$this->length} ファイルサイズが変なので、datを再取得</p>");
                        // $GLOBALS['debug'] && $GLOBALS['profiler']->leaveSection("dat_size_check");
                        return $this->_downloadDat2ch (0); // datサイズは不正。全部取り直し。
                    }
                }

                $this->isonline = true;
                return true;
            } elseif ($code == '302') { // Found
                                        // ホストの移転を追跡
                $new_host = BbsMap::getCurrentHost ($this->host, $this->bbs);
                if ($new_host != $this->host) {
                    $this->old_host = $this->host;
                    $this->host = $new_host;
                    return $this->_downloadDat2ch ($from_bytes);
                } else {
                    return $this->_downloadDat2chNotFound ($code);
                }
            } elseif ($code == '304') {
                $this->isonline = true;
                return '304 Not Modified';
            } elseif ($code == '416') { // Requested Range Not Satisfiable
                                        // echo "あぼーん検出";
                $this->onbytes = 0;
                $this->modified = null;
                return $this->_downloadDat2ch (0); // あぼーん検出。全部取り直し。
            } else {
                return $this->_downloadDat2chNotFound ($code);
            }
        } catch (Exception $e) {
            $this->getdat_error_msg_ht .= "<p>サーバ接続エラー: " . $e->getMessage ();
            $this->getdat_error_msg_ht .= "<br>rep2 error: 板サーバへの接続に失敗しました。</p>";
            $this->diedat = true;
            return false;
        }
    }

    // }}}
    // {{{ _downloadDat2chNotFound()

    /**
     * 2ch DATをダウンロードできなかったときに呼び出される
     */
    protected function _downloadDat2chNotFound($code = null) {
        // 2ch, bbspink ならread.cgiで確認
        $this->getdat_error_msg_ht .= $this->get2chDatError ($code);
        $this->diedat = true;
        return false;
    }

    // }}}
    // {{{ _downloadDat2chMaru()

    /**
     * 2ch●用 DATをダウンロードする
     *
     * @param string $uaMona
     * @param string $SID2ch
     * @param bool $shirokuma
     *            trueならofflaw2で取得
     * @return bool
     * @see lib/login2ch.inc.php
     */
    protected function _downloadDat2chMaru($uaMona, $SID2ch, $shirokuma = false) {
        global $_conf;
        global $debug;

        if (! ($this->host && $this->bbs && $this->key)) {
            return false;
        }

        $AppKey = $_conf['2chapi_appkey'];
        $AppName = $_conf['2chapi_appname'];
        $HMKey = $_conf['2chapi_hmkey'];
        $ReadUA = sprintf ($_conf['2chapi_ua.read'], $AppName);

        if ($SID2ch == '') {
            return false;
        }

        $from_bytes = intval ($from_bytes);

        if ($from_bytes == 0) {
            $zero_read = true;
        } else {
            $zero_read = false;
            $from_bytes = $from_bytes - 1;
        }

        $serverName = explode ('.', $this->host);
        // $url = "http://{$this->host}/{$this->bbs}/dat/{$this->key}.dat";
        // $url="http://news2.2ch.net/test/read.cgi?bbs=newsplus&key=1038486598";

        if($_conf['2chapi_ssl.read']) {
            $url = 'https://api.2ch.net/v1/';
        } else {
            $url = 'http://api.2ch.net/v1/';
        }

        $url .= $serverName[0] . '/' . $this->bbs . '/' . $this->key;
        $message = '/v1/' . $serverName[0] . '/' . $this->bbs . '/' . $this->key . $SID2ch . $AppKey;
        $HB = hash_hmac ("sha256", $message, $HMKey);

        $purl = parse_url ($url); // URL分解

        try {
            $req = P2Util::getHTTPRequest2 ($url, HTTP_Request2::METHOD_POST);

            // ヘッダ
            $req->setHeader ('User-Agent', $ReadUA);

            if (! $zero_read) {
                $req->setHeader ('Range', sprintf ('bytes=%d-', $from_bytes) );
            }

            if ($this->modified) {
                $req->setHeader ('If-Modified-Since', $this->modified);
            }

            // Basic認証用のヘッダ
            if (isset ($purl['user']) && isset ($purl['pass'])) {
                $req->setAuth ($purl['user'], $purl['pass'], HTTP_Request2::AUTH_BASIC);
            }

            // POSTする内容
            $req->addPostParameter (array (
                    'sid' => $SID2ch,
                    'hobo' => $HB,
                    'appkey' => $AppKey
            ));

            // POSTデータの送信
            $response = $req->send ();

            $code = $response->getStatus ();

            if ($code == '200' || $code == '206') { // Partial Content
                $body = $response->getBody ();

                $this->onbytes = intval ($response->getHeader ('Content-Length'));

                $this->modified = $response->getHeader ('Last-Modified');

                // 1行目を切り出す
                $posLF = mb_strpos ($body, "\n");
                $firstmsg = mb_substr ($body, 0, $posLF === false ? mb_strlen ($body) : $posLF);

                // ngで始まってたらapiのエラーの可能性
                if (preg_match ("/^ng \((.*)\)$/", $firstmsg)) {
                    $this->getdat_error_msg_ht .= "<p>rep2 error: API経由での浪人 ID のスレッド取得に失敗しました。" . $firstmsg . "</p>";
                    $this->getdat_error_msg_ht .= " [<a href=\"{$_conf['read_php']}?host={$this->host}&amp;bbs={$this->bbs}&amp;key={$this->key}&amp;ls={$this->ls}&amp;relogin2chapi=true\">APIで再取得を試みる</a>]";
                    $this->getdat_error_msg_ht .= $this->_generateMarutoriLink (true);
                    $this->getdat_error_msg_ht .= " [<a href=\"{$_conf['read_php']}?host={$this->host}&amp;bbs={$this->bbs}&amp;key={$this->key}&amp;ls={$this->ls}&amp;olddat=true\">旧datで再取得を試みる</a>]";
                    $this->diedat = true;
                    return false;
                }
                unset ($firstmsg);

                // 末尾の改行であぼーんチェック
                if (! $zero_read) {
                    if (substr ($body, 0, 1) != "\n") {
                        // echo "あぼーん検出";
                        $this->onbytes = 0;
                        $this->modified = null;
                        return $this->_downloadDat2chMaru($uaMona, $SID2ch, $shirokuma); // datサイズは不正。全部取り直し。
                    }
                    $body = substr ($body, 1);
                }

                $file_append = ($zero_read) ? 0 : FILE_APPEND;

                if (FileCtl::file_write_contents ($this->keydat, $body, $file_append) === false) {
                    p2die ('cannot write file.');
                }

                // $GLOBALS['debug'] && $GLOBALS['profiler']->enterSection("dat_size_check");
                // 取得後サイズチェック
                if ($zero_read == false && $this->onbytes) {
                    $this->getDatBytesFromLocalDat (); // $aThread->length をset
                    if ($this->onbytes != $this->length) {
                        $this->onbytes = 0;
                        $this->modified = null;
                        P2Util::pushInfoHtml ("<p>rep2 info: {$this->onbytes}/{$this->length} ファイルサイズが変なので、datを再取得</p>");
                        // $GLOBALS['debug'] && $GLOBALS['profiler']->leaveSection("dat_size_check");
                        return $this->_downloadDat2chMaru($uaMona, $SID2ch, $shirokuma); // datサイズは不正。全部取り直し。
                    }
                }

                return true;
            } elseif ($code == '304') {
                return '304 Not Modified';
            } else {
                return $this->_downloadDat2chMaruNotFound ($code);
            }
        } catch (Exception $e) {
            $this->getdat_error_msg_ht .= "<p>サーバ接続エラー: " . $e->getMessage ();
            $this->getdat_error_msg_ht .= "<br>rep2 error: 板サーバへの接続に失敗しました。</p>";
            $this->diedat = true;
            return false;
        }
    }

    // }}}
    // {{{ _downloadDat2chMaruNotFound()

    /**
     * ●IDでの取得ができなかったときに呼び出される
     */
    protected function _downloadDat2chMaruNotFound() {
        global $_conf;

        // 再チャレンジがまだなら、再チャレンジする。SIDが変更されてしまっている場合がある時のための自動チャレンジ。
        if (empty ($_REQUEST['relogin2ch']) && empty ($_REQUEST['shirokuma'])) {
            $_REQUEST['relogin2ch'] = true;
            return $this->downloadDat ();
        } else {
            $remarutori_ht = $this->_generateMarutoriLink (true);
            $this->getdat_error_msg_ht .= "<p>rep2 info: ●IDでのスレッド取得に失敗しました。{$remarutori_ht}{$moritori_ht}</p>";
            $this->diedat = true;
            return false;
        }
    }

    // }}}
    // {{{ _downloadDat2chKako()

    /**
     * 2chの過去ログ倉庫からdat.gzをダウンロード＆解凍する
     */
    protected function _downloadDat2chKako($uri, $ext) {
        global $_conf;

        $url = $uri . $ext;

        $purl = parse_url ($url); // URL分解

        try {
            $req = P2Util::getHTTPRequest2 ($url, HTTP_Request2::METHOD_GET);

            // Requestの送信
            $response = $req->send ();

            $code = $response->getStatus ();

            if ($code == '200' || $code == '206') { // Partial Content
                $body = $response->getBody ();

                $this->onbytes = intval ($response->getHeader ('Content-Length'));

                $this->modified = $response->getHeader ('Last-Modified');

                if (FileCtl::file_write_contents ($this->keydat, $body, 0) === false) {
                    p2die ('cannot write file. downloadDat2chMaru()');
                }

                return true;
            } elseif ($code == '304') {
                return '304 Not Modified';
            } else {
                return $this->_downloadDat2chKakoNotFound ($uri, $ext);
            }
        } catch (Exception $e) {
            $this->getdat_error_msg_ht .= "<p>サーバ接続エラー: " . $e->getMessage ();
            $this->getdat_error_msg_ht .= "<br>rep2 error: 板サーバへの接続に失敗しました。</p>";
            $this->diedat = true;
            return false;
        }
    }

    // }}}
    // {{{ _downloadDat2chKakoNotFound()

    /**
     * 過去ログを取得できなかったときに呼び出される
     */
    protected function _downloadDat2chKakoNotFound($uri, $ext) {
        global $_conf;

        if ($ext == ".dat.gz") {
            // .dat.gzがなかったら.datでもう一度
            return $this->_downloadDat2chKako ($uri, ".dat");
        }
        if (! empty ($_GET['kakolog'])) {
            $kako_html_url = p2h ($_GET['kakolog'] . '.html');
            $kakolog_ht = "<p><a href=\"{$kako_html_url}\"{$_conf['bbs_win_target_at']}>{$kako_html_url}</a></p>";
        }
        $this->getdat_error_msg_ht = "<p>rep2 info: 2ちゃんねる過去ログ倉庫からのスレッド取り込みに失敗しました。</p>";
        $this->getdat_error_msg_ht .= $kakolog_ht;
        $this->diedat = true;
        return false;
    }

    // }}}
    // {{{ get2chDatError()

    /**
     * 2chのdatを取得できなかった原因を返す
     *
     * @return string エラーメッセージ（原因がわからない場合は空で返す）
     */
    public function get2chDatError($code = null) {
        global $_conf;

        // ホスト移転検出で変更したホストを元に戻す
        if (! empty ($this->old_host)) {
            $this->host = $this->old_host;
            $this->old_host = null;
        }

        $reason = null;
        if (P2Util::isHost2chs ($this->host) || P2Util::isHostVip2ch ($this->host)) {
            if ($code == '302') {
                $body203 = $this->_get2ch203Body();
                if ($body203 !== false && preg_match('/過去ログ ★/', $body203)) {
                    $this->getdat_error_body = $body203;
                    if (preg_match('/このスレッドは過去ログ倉庫に格.{1,2}されています/', $body203)) {
                        $reason = 'datochi';
                        $this->setDatochiResiduums();
                    } elseif (preg_match('{http://[^/]+/[^/]+/kako/\\d+(/\\d+)?/(\\d+)\\.html}', $body203, $matches)) {
                        $reason = 'kakohtml';
                    }
                }
            }
        }

        $read_url = "http://{$this->host}/test/read.cgi/{$this->bbs}/{$this->key}/";

        // {{{ read.cgi からHTMLを取得

        $read_response_html = '';
        if (! $reason) {
            try {
                $req = P2Util::getHTTPRequest2 ($read_url.'1', HTTP_Request2::METHOD_GET);
                // ヘッダ
                $req->setHeader ('User-Agent', P2Util::getP2UA(false,P2Util::isHost2chs($this->host))); // ここは、"Monazilla/" をつけるとNG

                // Requestの送信
                $response = $req->send ();

                $res_code = $response->getStatus ();

                $test403 = "/403\.dat/";
                if ($res_code == '200' || $res_code == '206') { // Partial Content
                    $read_response_html = $response->getBody ();
                } elseif ($res_code == '302' || preg_match ($test403, $response->getBody (), $test403)) {
                    $read_response_html = $response->getBody ();
                } else {
                    $url_t = P2Util::throughIme ($read_url);
                    $info_msg_ht = "<p class=\"info-msg\">Error: {$code}<br>";
                    $info_msg_ht .= "rep2 info: <a href=\"{$url_t}\"{$_conf['ext_win_target_at']}>{$read_url}</a> のHTMLを取得出来ませんでした。</p>";
                    P2Util::pushInfoHtml ($info_msg_ht);
                }
            } catch (Exception $e) {
                $this->getdat_error_msg_ht .= "<p>サーバ接続エラー: " . $e->getMessage ();
                $this->getdat_error_msg_ht .= "<br>rep2 error: 板サーバへの接続に失敗しました。</p>";
                $this->diedat = true;
            }
            unset ($req, $response);
        }

        // }}}
        // {{{ 取得したHTML（$read_response_html）を解析して、原因を見つける

        $dat_response_status = '';
        $dat_response_msg = '';

        $vip2ch_kakosoko_match = "/格.{1,2}されています。もう書き込みできません。。/";
        $kakosoko_match = "/このスレッドは過去ログ倉庫に格.{1,2}されています/";
        $kakosoko_match2 = "/http:\/\/turing1000\.nttec\.com\/?(403|404|500)\.dat/";

        $naidesu_match = "/<title>そんな板orスレッドないです。<\/title>/";

        // 0ちゃんねるスクリプトに反応するように
        $soukoni_match = "/<title>隊長！過去ログ倉庫に<\/title>/";

        $error3939_match = "{<title>２ちゃんねる error 3939</title>}"; // 過去ログ倉庫でhtml化の時（他にもあるかも、よく知らない）

        // <a href="http://qb5.2ch.net/sec2chd/kako/1091/10916/1091634596.html">
        // <a href="../../../../mac/kako/1004/10046/1004680972.html">
        // $kakohtml_match = "{<a href=\"\.\./\.\./\.\./\.\./([^/]+/kako/\d+(/\d+)?/(\d+)).html\">}";
        $kakohtml_match = "{/([^/]+/kako/\d+(/\d+)?/(\d+)).html\">}";
        $waithtml_match = "/html化されるのを待っているようです。/";
        $vip2ch_kakodat_match = "{/([^/]+/kako/\d+(/\d+)?/(\d+)).dat\">}"; // vip2ch.com用

        // <title>がこのスレッドは過去ログ倉庫に
        if ($reason === 'datochi' || preg_match ($kakosoko_match, $read_response_html, $matches) || preg_match ($kakosoko_match2, $read_response_html, $matches)) {
            $dat_response_status = "このスレッドは過去ログ倉庫に格納されています。";
            $marutori_ht = $this->_generateMarutoriLink ();
            $plugin_ht = $this->_generateWikiDatLink ($read_url);
            $dat_response_msg = "<p>2ch info - このスレッドは過去ログ倉庫に格納されています。{$marutori_ht}{$moritori_ht}{$plugin_ht}</p>";

        // <title>がそんな板orスレッドないです。or error 3939
        } elseif ($reason === 'kakohtml' or
            preg_match ($naidesu_match, $read_response_html, $matches) ||
            preg_match ($error3939_match, $read_response_html, $matches) ||
            preg_match ($vip2ch_kakosoko_match, $read_response_html, $matches) ||
            preg_match ($soukoni_match, $read_response_html, $matches)) {

            if ($reason === 'kakohtml' or preg_match ($kakohtml_match, $read_response_html, $matches)) {
                if ($reason === 'kakohtml') {
                    preg_match ('{/([^/]+/kako/\d+(/\d+)?/(\d+)).html}', $this->getdat_error_body, $matches);
                }
                $dat_response_status = "隊長! 過去ログ倉庫で、html化されたスレッドを発見しました。";
                $kakolog_uri = "http://{$this->host}/{$matches[1]}";
                $kakolog_url_en = rawurlencode ($kakolog_uri);
                $read_kako_url = "{$_conf['read_php']}?host={$this->host}&amp;bbs={$this->bbs}&amp;key={$this->key}&amp;ls={$this->ls}&amp;kakolog={$kakolog_url_en}&amp;kakoget=1";
                $dat_response_msg = "<p>2ch info - 隊長! 過去ログ倉庫で、<a href=\"{$kakolog_uri}.html\"{$_conf['bbs_win_target_at']}>スレッド {$matches[3]}.html</a> を発見しました。 [<a href=\"{$read_kako_url}\">rep2に取り込んで読む</a>]</p>";
            } elseif (preg_match ($waithtml_match, $read_response_html, $matches)) {
                $dat_response_status = "隊長! スレッドはhtml化されるのを待っているようです。";
                $marutori_ht = $this->_generateMarutoriLink ();
                $dat_response_msg = "<p>2ch info - 隊長! スレッドはhtml化されるのを待っているようです。{$marutori_ht}{$moritori_ht}</p>";
            } elseif (preg_match ($vip2ch_kakodat_match, $read_response_html, $matches)) {
                $dat_response_status = "隊長! 過去ログ倉庫で、datを発見しました。";
                $kakolog_uri = "http://{$this->host}/{$matches[1]}";
                $kakolog_url_en = rawurlencode ($kakolog_uri);
                $read_kako_url = "{$_conf['read_php']}?host={$this->host}&amp;bbs={$this->bbs}&amp;key={$this->key}&amp;ls={$this->ls}&amp;kakolog={$kakolog_url_en}&amp;kakoget=1";
                $dat_response_msg = "<p>2ch info - 隊長! 過去ログ倉庫で、<a href=\"{$kakolog_uri}.html\"{$_conf['bbs_win_target_at']}>スレッド {$this->key}.html</a> を発見しました。 [<a href=\"{$read_kako_url}\">rep2に取り込んで読む</a>]</p>";
            } else {
                if (! empty ($_GET['kakolog'])) {
                    $dat_response_status = 'そんな板orスレッドないです。';
                    $kako_html_url = p2h ($_GET['kakolog'] . '.html');
                    $kakolog_query = rawurlencode ($_GET['kakolog']);
                    $read_kako_url = "{$_conf['read_php']}?host={$this->host}&amp;bbs={$this->bbs}&amp;key={$this->key}&amp;ls={$this->ls}&amp;kakolog={$kakolog_query}&amp;kakoget=1";
                    $dat_response_msg = '<p>2ch info - そんな板orスレッドないです。</p>';
                    $dat_response_msg .= "<p><a href=\"{$kako_html_url}\"{$_conf['bbs_win_target_at']}>{$kako_html_url}</a> [<a href=\"{$read_kako_url}\">rep2にログを取り込んで読む</a>]</p>";
                } else {
                    $dat_response_status = 'そんな板orスレッドないです。';
                    $dat_response_msg = '<p>2ch info - そんな板orスレッドないです。</p>';
                }
            }

        // 原因が分からない場合でも、とりあえず過去ログ取り込みのリンクを維持している。と思う。あまり覚えていない 2005/2/27 aki
        } elseif (! empty ($_GET['kakolog'])) {
            $dat_response_status = '';
            $kako_html_url = p2h ($_GET['kakolog'] . '.html');
            $kakolog_query = rawurlencode ($_GET['kakolog']);
            $read_kako_url = "{$_conf['read_php']}?host={$this->host}&amp;bbs={$this->bbs}&amp;key={$this->key}&amp;ls={$this->ls}&amp;kakolog={$kakolog_query}&amp;kakoget=1";
            $dat_response_msg = "<p><a href=\"{$kako_html_url}\"{$_conf['bbs_win_target_at']}>{$kako_html_url}</a> [<a href=\"{$read_kako_url}\">rep2にログを取り込んで読む</a>]</p>";
        }

        // }}}

        return $dat_response_msg;
    }

    // }}}
    // {{{ previewOne()

    /**
     * >>1のみをプレビューする
     */
    public function previewOne() {
        global $_conf;

        if (! ($this->host && $this->bbs && $this->key)) {
            return false;
        }

        // 通常と同じようにDATの取得を試みる。$_GET['one']がセットされていれば2ch互換は>>1だけ落とす
        $this->downloadDat ();

        // ローカルdatから取得
        if (is_readable ($this->keydat)) {
            $fd = fopen ($this->keydat, 'rb');
            $first_line = fgets ($fd, 32800);
            fclose ($fd);

            // be.2ch.net ならEUC→SJIS変換
            if (P2Util::isHostBe2chNet ($this->host)) {
                $first_line = mb_convert_encoding ($first_line, 'CP932', 'CP51932');
            }

            $first_datline = rtrim ($first_line);
            if (strpos ($first_datline, '<>') !== false) {
                $datline_sepa = "<>";
            } else {
                $datline_sepa = ',';
                $this->dat_type = '2ch_old';
            }
            $d = explode ($datline_sepa, $first_datline);
            $this->setTtitle ($d[4]);
        }

        if (! $this->readnum) {
            $this->readnum = 1;
        }

        if ($_conf['ktai']) {
            if ($_conf['iphone']) {
                $aShowThread = new ShowThreadI($this);
            } else {
                $aShowThread = new ShowThreadK($this);
            }
            $aShowThread->am_autong = false;
        } else {
            $aShowThread = new ShowThreadPc ($this);
        }

        $body = '';
        $body .= "<div class=\"thread\">\n";
        $res = $aShowThread->transRes ($first_line, 1); // 1を表示
        $body .= is_array ($res) ? $res['body'] . $res['q'] : $res;
        $body .= "</div>\n";

        return $body;
    }

    // }}}
    // {{{ previewOneNotFound()

    /**
     * >>1をプレビューでスレッドデータが見つからなかったときに呼び出される
     */
    public function previewOneNotFound($code = null) {
        global $_conf;

        $this->diedat = true;
        // 2ch, bbspink, vip2ch ならread.cgiで確認
        if (P2Util::isHost2chs ($this->host) || P2Util::isHostVip2ch ($this->host)) {
            $this->getdat_error_msg_ht = $this->get2chDatError ($code);
            if (count ($this->datochi_residuums)) {
                if ($_conf['ktai']) {
                    $aShowThread = new ShowThreadK ($this);
                    $aShowThread->am_autong = false;
                } else {
                    $aShowThread = new ShowThreadPc ($this);
                }
                $this->onthefly = true;
                $body = "<div><span class=\"onthefly\">on the fly</span></div>\n";
                $body .= "<div class=\"thread\">\n";
                $res = $aShowThread->transRes ($this->datochi_residuums[1], 1);
                $body .= is_array ($res) ? $res['body'] . $res['q'] : $res;
                $body .= "</div>\n";
                return $body;
            }
        }
        return false;
    }

    // }}}
    // {{{ lsToPoint()

    /**
     * $lsを分解してstartとtoとnofirstを求める
     */
    public function lsToPoint() {
        global $_conf;

        $start = 1;
        $to = false;
        $nofirst = false;

        // nを含んでいる場合は、>>1を表示しない（$nofirst）
        if (strpos ($this->ls, 'n') !== false) {
            $nofirst = true;
            $this->ls = str_replace ('n', '', $this->ls);
        }

        // 範囲指定で分割
        $n = explode ('-', $this->ls);
        // 範囲指定がなければ
        if (sizeof ($n) == 1) {
            // l指定があれば
            if (substr ($n[0], 0, 1) === 'l') {
                $ln = intval (substr ($n[0], 1));
                if ($_conf['ktai']) {
                    if ($ln > $_conf['mobile.rnum_range']) {
                        $ln = $_conf['mobile.rnum_range'];
                    }
                }
                $start = $this->rescount - $ln + 1;
                if ($start < 1) {
                    $start = 1;
                }
                $to = $this->rescount;
            // all指定なら
            } elseif ($this->ls === 'all') {
                $start = 1;
                $to = $this->rescount;
            } else {
                // レス番指定
                if (intval ($this->ls) > 0) {
                    $this->ls = intval ($this->ls);
                    $start = $this->ls;
                    $to = $this->ls;
                    $nofirst = true;
                    // 指定がない or 不正な場合は、allと同じ表示にする
                } else {
                    $start = 1;
                    $to = $this->rescount;
                }
            }
            // 範囲指定があれば
        } else {
            if (! $start = intval ($n[0])) {
                $start = 1;
            }
            if (! $to = intval ($n[1])) {
                $to = $this->rescount;
            }
        }

        // 新着まとめ読みの表示数制限
        if (isset ($GLOBALS['rnum_all_range']) and $GLOBALS['rnum_all_range'] > 0) {

            /*
             * ■携帯の新着まとめ読みが、ちょっきしで終わった時に、の「続きor更新」判定問題
             *
             * リミット < スレの表示範囲
             * 次リミットは　0
             * スレの表示範囲を終える前にリミット数消化
             * →続き
             *
             * リミット > スレの表示範囲
             * 次リミットは +
             * リミット数が残っている間に、スレの表示範囲を終えた
             * →更新
             *
             * リミット = スレの表示範囲
             * 次リミットは 0
             * スレの表示範囲丁度でリミットを消化した
             * →続き? 更新?
             * 続きの場合も更新の場合もある。逐次処理のため、
             * 他のスレの残り新着数があるかどうかが不明で判定できない。
             */

            // リミットがスレの表示範囲より小さい場合は、スレの表示範囲をリミットに合わせる
            $limit_to = $start + $GLOBALS['rnum_all_range'] - 1;

            if ($limit_to < $to) {
                $to = $limit_to;

                // スレの表示範囲丁度でリミットを消化した場合
            } elseif ($limit_to == $to) {
                $GLOBALS['limit_to_eq_to'] = true;
            }

            // 次のリミットは、今回のスレの表示範囲分を減らした数
            $GLOBALS['rnum_all_range'] = $GLOBALS['rnum_all_range'] - ($to - $start) - 1;

            // print_r("$start, $to, {$GLOBALS['rnum_all_range']}");
        } else {
            // 携帯用
            if ($_conf['ktai']) {
                // 表示数制限
                /*
                 * if ($start + $_conf['mobile.rnum_range'] -1 <= $to) {
                 * $to = $start + $_conf['mobile.rnum_range'] -1;
                 * }
                 */
                // 次X件では、前一つを含み、実質+1となるので、1つおまけする
                if ($start + $_conf['mobile.rnum_range'] <= $to) {
                    $to = $start + $_conf['mobile.rnum_range'];
                }
                if (ResFilter::getWord () !== null) {
                    $start = 1;
                    $to = $this->rescount;
                    $nofirst = false;
                }
            }
        }

        $this->resrange = compact ('start', 'to', 'nofirst');
        return $this->resrange;
    }

    // }}}
    // {{{ readDat()

    /**
     * Datを読み込む
     * $this->datlines を set する
     */
    public function readDat() {
        global $_conf;

        if (file_exists ($this->keydat)) {
            if ($this->datlines = FileCtl::file_read_lines ($this->keydat)) {

                // be.2ch.net ならEUC→SJIS変換
                // 念のためSJISとUTF-8も文字コード判定の候補に入れておく
                // ・・・が、文字化けしたタイトルのスレッドで誤判定があったので、指定しておく
                if (P2Util::isHostBe2chNet ($this->host)) {
                    // mb_convert_variables('CP932', 'CP51932,CP932,UTF-8', $this->datlines);
                    mb_convert_variables ('CP932', 'CP51932', $this->datlines);
                }

                if (strpos ($this->datlines[0], '<>') === false) {
                    $this->dat_type = '2ch_old';
                }
            }
        } else {
            return false;
        }

        $this->rescount = sizeof ($this->datlines);

        if ($_conf['flex_idpopup'] || $_conf['ngaborn_chain'] || $_conf['ngaborn_frequent'] || ($_conf['ktai'] && ($_conf['mobile.clip_unique_id'] || $_conf['mobile.underline_id']))) {
            $this->_setIdCount ();
        }

        return true;
    }

    // }}}
    // {{{ setIdCount()

    /**
     * 一つのスレ内でのID出現数をセットする
     */
    protected function _setIdCount() {
        if (! $this->datlines) {
            return;
        }

        $i = 0;
        $idp = array_fill (1, $this->rescount, null);
        $ids = array_fill (1, $this->rescount, null);

        foreach ($this->datlines as $l) {
            $lar = explode ('<>', $l);
            $i ++;
            if (preg_match ('<(ID: ?)([0-9A-Za-z/.+]+)(?=[^0-9A-Za-z/.+]|$)>', $lar[2], $m)) {
                $idp[$i] = $m[1];
                $ids[$i] = $m[2];
            }
        }

        $this->idp = $idp;
        $this->ids = $ids;
        $this->idcount = array_count_values (array_filter ($ids, 'is_string'));
    }

    // }}}
    // {{{ explodeDatLine()

    /**
     * datlineをexplodeする
     */
    public function explodeDatLine($aline) {
        $aline = rtrim ($aline);

        if ($this->dat_type === '2ch_old') {
            $parts = explode (',', $aline);
        } else {
            $parts = explode ('<>', $aline);
        }

        // iframe を削除。2chが正常化して必要なくなったらこのコードは外したい。2005/05/19
        $parts[3] = preg_replace ('{<(iframe|script)( .*?)?>.*?</\\1>}i', '', $parts[3]);

        return $parts;
    }

    // }}}
    // {{{ scanOriginalHosts()

    /**
     * datを走査してスレ立て時のホスト候補を検出する
     *
     * @param
     *            void
     * @return array
     */
    public function scanOriginalHosts() {
        if (P2Util::isHost2chs ($this->host) && file_exists ($this->keydat) && ($dat = file_get_contents ($this->keydat))) {
            $bbs_re = preg_quote ($this->bbs, '@');
            $pattern = "@/(\\w+\\.(?:2ch\\.net|bbspink\\.com))(?:/test/read\\.cgi)?/{$bbs_re}\\b@";
            if (preg_match_all ($pattern, $dat, $matches, PREG_PATTERN_ORDER)) {
                $hosts = array_unique ($matches[1]);
                $arKey = array_search ($this->host, $hosts);
                if ($arKey !== false && array_key_exists ($arKey, $hosts)) {
                    unset ($hosts[$arKey]);
                }

                return $hosts;
            }
        }

        return array ();
    }

    // }}}
    // {{{ getDefaultGetDatErrorMessageHTML()

    /**
     * デフォルトのdat取得失敗エラーメッセージHTMLを取得する
     *
     * @param
     *            void
     * @return string
     */
    public function getDefaultGetDatErrorMessageHTML() {
        global $_conf;

        $diedat_msg = '<p><b>rep2 info: 板サーバから最新のスレッド情報を取得できませんでした。</b>';
        if ($hosts = $this->scanOriginalHosts ()) {
            $common_q = '&amp;bbs=' . rawurldecode ($this->bbs) . '&amp;key=' . rawurldecode ($this->key) . '&amp;ls=' . rawurldecode ($this->ls);
            $diedat_msg .= '<br>datから他のホスト候補を検出しました。';
            foreach ($hosts as $host) {
                $diedat_msg .= " [<a href=\"{$_conf['read_php']}?host={$host}{$common_q}{$_conf['k_at_a']}\">{$host}</a>]";
            }
        }
        $diedat_msg .= '</p>';

        return $diedat_msg;
    }

    // }}}
    // {{{ _pushInfoMessage()

    /**
     * 情報メッセージをプッシュする
     *
     * @param string $summary
     * @param string $description
     * @return void
     */
    static protected function _pushInfoMessage($summary, $description) {
        $message = '<p class="info-msg">' . $summary . '<br>rep2 info: ' . $description . '</p>';
        P2Util::pushInfoHtml ($message);
    }

    // }}}
    // {{{ _pushInfoConnectFailed()

    /**
     * 接続に失敗した旨のメッセージをプッシュする
     *
     * @param string $url
     * @param int $errno
     * @param string $errstr
     * @return void
     */
    static protected function _pushInfoConnectFailed($url, $errno, $errstr) {
        $summary = sprintf ('HTTP接続エラー (%d) %s', $errno, $errstr);
        $description = self::_urlToAnchor ($url) . ' に接続できませんでした。';
        self::_pushInfoMessage ($summary, $description);
    }

    // }}}
    // {{{ _pushInfoReadTimedOut()

    /**
     * 読み込みがタイムアウトした旨のメッセージをプッシュする
     *
     * @param string $url
     * @return void
     */
    static protected function _pushInfoReadTimedOut($url) {
        $summary = 'HTTP接続タイムアウト';
        $description = self::_urlToAnchor ($url) . ' を読み込み完了できませんでした。';
        self::_pushInfoMessage ($summary, $description);
    }

    // }}}
    // {{{ _pushInfoHttpError()

    /**
     * HTTPエラーのメッセージをプッシュする
     *
     * @param string $url
     * @param int $errno
     * @param string $errstr
     * @return void
     */
    static protected function _pushInfoHttpError($url, $errno, $errstr) {
        $summary = sprintf ('HTTP %d %s', $errno, $errstr);
        $description = self::_urlToAnchor ($url) . ' を読み込めませんでした。';
        self::_pushInfoMessage ($summary, $description);
    }

    // }}}
    // {{{ _urlToAnchor()

    /**
     * _pushInfo系メソッド用にURLをアンカーに変換する
     *
     * @param string $url
     * @return string
     */
    static protected function _urlToAnchor($url) {
        global $_conf;

        return sprintf ('<a href="%s"%s>%s</a>', P2Util::throughIme ($url), $_conf['ext_win_target_at'], p2h ($url));
    }

    // }}}
    // {{{ _get2ch203Body()

    /**
     * 2chのDATにUAをMonazillaにしないでアクセスして、bodyを得て返す.
     *
     * @return 取得したbody（正常に取得できなかった場合はfalse)
     */
    private function _get2ch203Body() {
        // 2007/06/11 302の時に、UAをMonazillaにしないでDATアクセスを試みると203が帰ってきて、
        // body中に'過去ログ ★'とあれば、●落ち中とみなすことにする。
        // 仕様の確証が取れていないので、このような判断でよいのかはっきりしない。
        // 203 Non-Authoritative Information
        // 過去ログ ★
        /*
         * 名無し募集中。。。<><>2007/06/10(日) 13:29:51.68 0<> http://mlb.yahoo.co.jp/headlines/?a=2279 <br> くわわ＞＞＞＞＞＞＞＞＞＞＞＞＞＞＞＞＞＞＞＞＞井川 <>★くわわメジャー昇格おめ 売上議論14001★
         * 1001, 131428 (総レス数, サイズ)<><>1181480550000000 (最終更新)<><div style="color:navy;font-size:smaller;">|<br />| 中略<br />|</div><>
         * １００１<><>Over 1000 Thread<> このスレッドは１０００を超えました。 <br> もう書けないので、新しいスレッドを立ててくださいです。。。 <>
         * 過去ログ ★<><>[過去ログ]<><div style="color:red;text-align:center;">■ このスレッドは過去ログ倉庫に格納されています</div><hr /><br />IE等普通のブラウザで見る場合 http://tubo.80.kg/tubo_and_maru.html<br />専用のブラウザで見る場合 http://www.monazilla.org/<br /><br />２ちゃんねる Viewer を使うと、すぐに読めます。 http://2ch.tora3.net/<br /><div style="color:navy;">この Viewer(通称●) の売上で、２ちゃんねるは設備を増強しています。<br />●が売れたら、新しいサーバを投入できるという事です。</div><br />よくわからない場合はソフトウェア板へGo http://pc11.2ch.net/software/<br /><br />モリタポ ( http://find.2ch.net/faq/faq2.php#c1 ) を持っていれば、50モリタポで表示できます。<br />　　　　こちらから → http://find.2ch.net/index.php?STR=dat:http://ex23.2ch.net/test/read.cgi/morningcoffee/1181449791/<br /><br /><hr /><>
         */
        try {
            $url = "http://{$this->host}/{$this->bbs}/dat/{$this->key}.dat";
            $req = P2Util::getHTTPRequest2 ($url,HTTP_Request2::METHOD_GET);

            $res = $req->send ();

            // レスポンスコードを検証
            if ('203' == $res->getStatus ()) {
                return $res->getBody ();
            }

            return false;
        } catch (Exception $exp) {
            return false;
        }
    }

    // }}}
    // {{{ setDatochiResiduums()

    /**
     * DAT取得エラー時の>>1と最終レスをDATの形式で$this->datochi_residuumsに
     * 保存する（レス番 => datline の配列）
     * $this->getdat_error_bodyの内容から構築.
     *
     * @return boolean 正常に終了した場合はtrue
     */
    private function setDatochiResiduums() {
        $this->datochi_residuums = array ();
        if (! $this->getdat_error_body || strlen ($this->getdat_error_body) === 0) {
            return false;
        }

        $lines = explode ("\n", $this->getdat_error_body);
        if (count ($lines) < 3) {
            return false;
        }
        $first_line = $lines[0];
        $first_datline = rtrim ($first_line);
        if (strpos ($first_datline, '<>') !== false) {
            $datline_sepa = '<>';
        } else {
            $datline_sepa = ',';
            $this->dat_type = '2ch_old';
        }
        $d = explode ($datline_sepa, $first_datline);
        $this->setTtitle ($d[4]);

        $this->datochi_residuums[1] = $first_line;

        $second_line = $lines[1];
        if (strpos ($second_line, '<>') === false) {
            return false;
        }
        $d = explode ('<>', $second_line);
        if (count ($d) < 1) {
            return false;
        }
        list ($lastn, $size) = explode (',', $d[0]);
        $lastn = intval (trim ($lastn));
        if (! $lastn) {
            return false;
        }

        $this->datochi_residuums[$lastn] = $lines[2];
        return true;
    }

    // }}}
    // {{{ _generateWikiDatLink()

    /**
     * +WikiのDAT取得プラグインでdatを取得するためのリンクを生成する。
     *
     * @param
     *            void
     * @return string
     */
    protected function _generateWikiDatLink($read_url) {
        global $_conf;

        $plugin_ht = '';

        // +Wiki
        if ($_GET['plugin']) {
            $datPlugin = new DatPluginCtl ();
            $datPlugin->load ();
            foreach ($datPlugin->getData () as $v) {
                if (preg_match ('{' . $v['match'] . '}', $read_url)) {
                    $replace = @preg_replace ('{' . $v['match'] . '}', $v['replace'], $read_url);
                    $code = P2UtilWiki::getResponseCode ($replace);
                    if ($code == 200) {
                        $code = '○' . $code;
                    } else {
                        $code = '×' . $code;
                    }
                    $plugin_ht .= "    <option value=\"{$replace}\">{$code}:{$v['title']}</option>\n";
                }
            }
            if ($plugin_ht) {
                $plugin_ht = '<select size=1 name="kakolog">' . $plugin_ht . '</select>';
            } else {
                $plugin_ht = '<input type="text" name="kakolog" size="64">';
            }
            $plugin_ht .= 'から<input type="submit" name="kakoget" value="取得">';
        } else {
            $plugin_ht = '<input type="submit" name="plugin" value="DATを探す">';
        }
        $plugin_ht = <<<EOP
<form method="get" action="{$_conf['read_php']}">
    <input type="hidden" name="host" value="{$this->host}">
    <input type="hidden" name="bbs" value="{$this->bbs}">
    <input type="hidden" name="key" value="{$this->key}">
    <input type="hidden" name="ls" value="{$this->ls}">
    <input type="hidden" name="kakoget" value="2">
    {$_conf['k_input_ht']}
{$plugin_ht}
</form>
EOP;

        return $plugin_ht;
    }

    // }}}
    // {{{ _generateMarutoriHtml()
    /**
     * ●とofflawでdatを取得するためのリンクを生成する。
     *
     * @param bool $retry
     * @return string HTML
     */
    protected function _generateMarutoriLink($retry = false) {
        global $_conf;

        if ($retry) {
            $retry_q = "&amp;relogin2ch=true";
            $atext = "●IDで再取得する";
        } else {
            $atext = "●IDでrep2に取り込む";
        }
        $marutori_ht = " [<a href=\"{$_conf['read_php']}?host={$this->host}&amp;bbs={$this->bbs}&amp;key={$this->key}&amp;ls={$this->ls}&amp;maru=true{$retry_q}{$_conf['k_at_a']}\">{$atext}</a>]";
        return $marutori_ht;
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
