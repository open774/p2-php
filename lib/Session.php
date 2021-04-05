<?php
// {{{ GLOBALS

$GLOBALS['_SESS_VERSION'] = 1; // セッションのバージョン（全ての稼動途中セッションを強制破棄させたい時にUPしたりする）

// }}}
// {{{ Session

/**
 * Session Class
 *
 * IR, UA, アクセス時間のチェックを伴う、よりセキュアなセッション管理クラス
 * ほとんど自動で働くのであまり気にせず、通常通り $_SESSION の値を取り扱えばよい。
 * ただし、$_SESSION[$this->sess_array]（$_SESSION['_sess_array']） は予約語となっている。
 *
 * ■用例
 * $_session = new Session(); // ※この時点でPHP標準セッションがスタートする
 * if ($msg = $_session->checkSessionError()) { // よりセキュアなセッションチェック
 *     p2die($msg);
 * }
 *
 * $_SESSIONへのアクセスを終えた後は、session_write_close()しておくとよいだろう。
 *
 * ※重要※
 * php.ini で session.auto_start = 0 (PHPのデフォルトのまま) になっていること。
 * さもないとほとんどのセッション関連のパラメータがスクリプト内で変更できない。
 * .htaccessで変更が許可されているなら
 *
 * <IfModule mod_php4.c>
 *    php_flag session.auto_start Off
 * </IfModule>
 *
 * でもOK。
 *
 * @author aki
 */
class Session
{
    // {{{ static properties

    static public $_session_started = false;

    // }}}
    // {{{ properties

    public $sess_array = '_sess_array';

    // }}}
    // {{{ constructor

    /**
     * コンストラクタ
     *
     * ここでPHPの標準セッションがスタートする
     */
    public function __construct($session_name = null, $session_id = null)
    {
        $this->setCookieHttpOnly();

        // キャッシュ制御なし
        session_cache_limiter('none');

        // セッション名およびセッションIDを設定
        if ($session_name) {
            session_name($session_name);
        }
        if ($session_id) {
            session_id($session_id);
        }

        // セッションデータを初期化する
        session_start();
        self::$_session_started = true;

        /*
        Expires: Thu, 19 Nov 1981 08:52:00 GMT
        Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0
        Pragma: no-cache
        */
    }

    // }}}
    // {{{ _autoBegin()

    /**
     * よりセキュアなセッション管理を開始する
     * @return bool
     */
    private function _autoBegin()
    {
        // まだ強化セッションが始まっていなかったら
        if (!isset($_SESSION[$this->sess_array]['actime'])) {

            // セッション変数($this->sess_array)を初期セット
            $this->_initSess();

            // セッション変数の登録に失敗したら、エラー
            if (!isset($_SESSION[$this->sess_array]['actime'])) {
                trigger_error('Session::_autoBegin() セッション変数を登録できませんでした。', E_USER_WARNING);
                p2die('Session');
                return false;
            }
        }

        return true;
    }

    // }}}
    // {{{ _initSess()

    /**
     * セッション始めに変数をセットする
     *
     * @return void
     */
    private function _initSess()
    {
        // 初期化
        $_SESSION[$this->sess_array] = array();

        $_SESSION[$this->sess_array]['actime']     = time();
        $_SESSION[$this->sess_array]['ip']         = $_SERVER['REMOTE_ADDR'];
        $_SESSION[$this->sess_array]['ua']         = $_SERVER['HTTP_USER_AGENT'];
        // $_SESSION[$this->sess_array]['referer'] = $_SERVER['HTTP_REFERER'];
        $_SESSION[$this->sess_array]['version']    = $GLOBALS['_SESS_VERSION'];
    }

    // }}}
    // {{{ checkSessionError()

    /**
     * セッションの妥当性をチェックして、エラーがあればメッセージを得る。アクセス時間の更新もここで。
     *
     * @return false|string エラーがあれば、（unSession()して）エラーメッセージを返す。なければfalseを返す。
     */
    public function checkSessionError()
    {
        // 強化セッション
        $this->_autoBegin();

        $error_msg = '';

        if (!isset($_SESSION[$this->sess_array]['actime'])) {
            $error_msg = 'セッションが機能していません。';

        } else {

            if (!$this->_checkAcTime()) {
                $error_msg = 'セッションの時間切れです。再度ログインし直してください。';
            }

            if (!$this->_checkVersion()) {
                $error_msg = 'セッションのバージョンが正しくありません。'
                    .'（これはシステムのバージョンアップによって、一時的に起こることのある現象です）';
            }

            if (!$this->_checkIP()) {
                $error_msg = 'セッションのIPが正しくありません。';
            }

            if (!$this->_checkUA()) {
                $error_msg = 'セッションのUAが正しくありません。';
            }
        }

        // エラーがあれば、（unSession()して）エラーメッセージを返す。
        if ($error_msg) {
            self::unSession();
            return $error_msg;
        }

        // 問題なければ、アクセス時間を更新する
        $_SESSION[$this->sess_array]['actime'] = time();

        // クエリーにSIDを付加する場合は、毎回 session_regenerate_id() する、、と少し不便
        // 過去アクセス5つ分以前を無効にするとかもできそうだが、
        /*
        $sname = session_name();
        if (!$_COOKIE[$sname]) {
            $oldID = session_id();
            session_regenerate_id();
            unlink(session_save_path() . "/sess_$oldID");
        }
        */

        return false;
    }

    // }}}
    // {{{ _checkAcTime()

    /**
     * セッションのアクセス時間をチェックする
     *
     * @return bool
     */
    private function _checkAcTime($minutes = 30)
    {
        // 最終アクセス時間から、一定時間以上が経過していればExpire
        if ($_SESSION[$this->sess_array]['actime'] + $minutes * 60 < time()) {
            return false;
        } else {
            return true;
        }
    }

    // }}}
    // {{{ _checkVersion()

    /**
     * セッションのバージョンをチェックする
     *
     * @return bool
     */
    private function _checkVersion()
    {
        if ($_SESSION[$this->sess_array]['version'] == $GLOBALS['_SESS_VERSION']) {
            return true;
        } else {
            return false;
        }
    }

    // }}}
    // {{{ _checkIP()

    /**
     * IPアドレス妥当性チェックする
     *
     * @return bool
     */
    private function _checkIP()
    {
        $check_level = 1; // 0～4 docomoを考慮すると、1まで

        $ses_ips = explode('.', $_SESSION[$this->sess_array]['ip']);
        $now_ips = explode('.', $_SERVER['REMOTE_ADDR']);

        for ($i = 0; $i++; $i < $check_level) {
            if ($ses_ips[$i] != $now_ips[$i]) {
                return false;
            }
        }
        return true;
    }

    // }}}
    // {{{ _checkUA()

    /**
     * UAでセッションの妥当性をチェックする
     *
     * @return bool
     */
    private function _checkUA()
    {
        // {{{ docomoはUTN時にUA後部が変わるので機種名で検証する

        $mobile = (new Net_UserAgent_Mobile)->singleton();
        if ($mobile->isDoCoMo()) {
            $mobile_b = Net_UserAgent_Mobile::factory($_SESSION[$this->sess_array]['ua']);
            if ($mobile_b->getModel() == $mobile->getModel()) {
                return true;
            }
        }

        // }}}

        // $offset = 12;
        if (empty($offset)) {
            $offset = strlen($_SERVER['HTTP_USER_AGENT']);
        }
        if (substr($_SERVER['HTTP_USER_AGENT'], 0, $offset) == substr($_SESSION[$this->sess_array]['ua'], 0, $offset)) {
            return true;
        } else {
            return false;
        }
    }

    // }}}
    // {{{ unSession()

    /**
     * $_SESSIONでセッションを破棄する
     *
     * セッションがない、もしくは正しくない場合などに
     * http://jp.php.net/manual/ja/function.session-destroy.php
     *
     * @return void
     */
    static public function unSession()
    {
        global $_conf;

        // セッションの初期化
        // session_name("something")を使用している場合は特にこれを忘れないように!
        if (!self::$_session_started) {
            session_start();
        }

        // セッション変数を全て解除する
        $_SESSION = array();

        // セッションを切断するにはセッションクッキーも削除する。
        $session_name = session_name();
        if (isset($_COOKIE[$session_name])) {
           //setcookie($session_name, '', time() - 42000);
           P2Util::unsetCookie($session_name);
           unset($_COOKIE[$session_name]);
        }

        // 最終的に、セッションを破壊する
        if (isset($_conf['session_dir'])) {
            $session_file = $_conf['session_dir'] . '/sess_' . session_id();

        } else {
            $session_file = session_save_path() . '/sess_' . session_id();
        }

        session_destroy();
        if (file_exists($session_file)) {
            unlink($session_file);
        }
    }

    // }}}
    // {{{ setCookieHttpOnly()

    /**
     * セッションのsetcookieにHttpOnlyを指定する
     * http://msdn2.microsoft.com/ja-jp/library/system.web.httpcookie.httponly(VS.80).aspx
     *
     * @param   void
     * @return  void
     */
    private function setCookieHttpOnly()
    {
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null;

        // Mac IEは、動作不良を起こすらしいっぽいので対象から外す。（そもそも対応もしていない）
        // Mozilla/4.0 (compatible; MSIE 5.16; Mac_PowerPC)
        if (preg_match('/MSIE \d\\.\d+; Mac/', $ua)) {
            return;
        }

        ini_set('session.cookie_httponly', true);
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
