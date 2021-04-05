<?php

// {{{ Login

/**
 * rep2 - ログイン認証を扱うクラス
 *
 * @create  2005/6/14
 * @author aki
 */
class Login
{
    // {{{ properties

    public $user;   // ユーザ名（内部的なもの）
    public $user_u; // ユーザ名（ユーザと直接触れる部分）
    public $pass_x; // 暗号化されたパスワード

    // }}}
    // {{{ constructor

    /**
     * コンストラクタ
     */
    public function __construct()
    {
        $login_user = $this->setdownLoginUser();

        // ユーザ名が指定されていなければ
        if ($login_user === null) {

            // ログイン失敗
            require_once P2_LIB_DIR . '/login_first.inc.php';
            printLoginFirst($this);
            exit;
        }

        $this->setUser($login_user);
        $this->pass_x = null;
    }

    // }}}
    // {{{ setUser()

    /**
     * ユーザ名をセットする
     */
    public function setUser($user)
    {
        $this->user_u = $user;
        $this->user = $user;
    }

    // }}}
    // {{{ setdownLoginUser()

    /**
     * ログインユーザ名の指定を得る
     */
    public function setdownLoginUser()
    {
        global $_conf;
        $login_user = null;

        // ユーザ名決定の優先順位に沿って

        // ログインフォームからの指定
        if (!empty($GLOBALS['brazil'])) {
            $add_mail = '.,@-';
        } else {
            $add_mail = '';
        }
        if (isset($_REQUEST['form_login_id']) && preg_match("/^[0-9A-Za-z_{$add_mail}]+\$/", $_REQUEST['form_login_id'])) {
            $login_user = $this->setdownLoginUserWithRequest();

        // GET引数での指定
        } elseif (isset($_REQUEST['user']) && preg_match("/^[0-9A-Za-z_{$add_mail}]+\$/", $_REQUEST['user'])) {
            $login_user = $_REQUEST['user'];

        // Cookieで指定
        } elseif (isset($_COOKIE['cid']) && ($user = $this->getUserFromCid($_COOKIE['cid'])) !== false) {
            if (preg_match("/^[0-9A-Za-z_{$add_mail}]+\$/", $user)) {
                $login_user = $user;
            }

        // Sessionで指定
        } elseif (isset($_SESSION['login_user']) && preg_match("/^[0-9A-Za-z_{$add_mail}]+\$/", $_SESSION['login_user'])) {
            $login_user = $_SESSION['login_user'];

        // 外部認証で指定
        } elseif ($_conf['external_authentication'] && isset($_SERVER['REMOTE_USER']) && (preg_match("/^[0-9A-Za-z_{$add_mail}]+\$/", $_SERVER['REMOTE_USER']))) {
            $login_user = $_SERVER['REMOTE_USER'];
        }

        return $login_user;
    }

    // }}}
    // {{{ setdownLoginUserWithRequest()

    /**
     * REQUESTからログインユーザ名の指定を得る
     */
    public function setdownLoginUserWithRequest()
    {
        return $_REQUEST['form_login_id'];
    }

    // }}}
    // {{{ authorize()

    /**
     * 認証を行う
     */
    public function authorize()
    {
        global $_conf, $_p2session;

        // {{{ 認証チェック

        $auth_result = $this->_authCheck();
        if (!$auth_result) {
            // ログイン失敗
            if (!function_exists('printLoginFirst')) {
                include P2_LIB_DIR . '/login_first.inc.php';
            }
            printLoginFirst($this);
            exit;
        }

        // }}}

        // ■ログインOKなら

        // {{{ ログアウトの指定があれば

        if (!empty($_REQUEST['logout'])) {

            // セッションをクリア（アクティブ、非アクティブを問わず）
            Session::unSession();

            // 補助認証をクリア
            $this->clearCookieAuth();

            $url = rtrim(dirname(P2Util::getMyUrl()), '/') . '/'; // . $user_u_q;

            header('Location: '.$url);
            exit;
        }

        // }}}
        // {{{ セッションが利用されているなら、セッション変数の更新

        if (isset($_p2session)) {

            // ユーザ名とパスXを更新
            $_SESSION['login_user']   = $this->user_u;
            $_SESSION['login_pass_x'] = $this->pass_x;
            if (!array_key_exists('login_microtime', $_SESSION)) {
                $_SESSION['login_microtime'] = microtime();
            }

            // devicePixelRatio指定があれば保持
            if (!empty($_REQUEST['device_pixel_ratio'])) {
                $device_pixel_ratio = floatval($_REQUEST['device_pixel_ratio']);
                if ($device_pixel_ratio === 1.5 || $device_pixel_ratio === 2.0) {
                    $_SESSION['device_pixel_ratio'] = $device_pixel_ratio;
                }
            }
        }

        // }}}
        // {{{ 要求があれば、補助認証を登録

        $this->registerCookie();

        // }}}

        // セッションを認証以外に使わない場合は閉じる
        if (P2_SESSION_CLOSE_AFTER_AUTHENTICATION) {
            session_write_close();
        }

        // _authCheck() が文字列を返したときは、URLと見なしてリダイレクト
        if (is_string($auth_result)) {
            header('Location: ' . $auth_result);
            exit;
        }

        return true;
    }

    // }}}
    // {{{ checkAuthUserFile()

    /**
     * 認証ユーザ設定のファイルを調べて、無効なデータなら捨ててしまう
     */
    public function checkAuthUserFile()
    {
        global $_conf;

        if (@include($_conf['auth_user_file'])) {
            // ユーザ情報がなかったら、ファイルを捨てて抜ける
            if (empty($rec_login_user_u) || empty($rec_login_pass_x)) {
                unlink($_conf['auth_user_file']);
            }
        }

        return true;
    }

    // }}}
    // {{{ _authCheck()

    /**
     * 認証のチェックを行う
     *
     * @return bool
     */
    private function _authCheck()
    {
        global $_conf;
        global $_login_failed_flag;
        global $_p2session;

        $this->checkAuthUserFile();

        // 認証ユーザ設定（ファイル）を読み込みできたら
        if (file_exists($_conf['auth_user_file'])) {
            include $_conf['auth_user_file'];

            // ユーザ名が違ったら、認証失敗で抜ける
            if ($this->user_u != $rec_login_user_u) {
                P2Util::pushInfoHtml('<p>p2 error: ログインエラー</p>');

                // ログイン失敗ログを記録する
                if (!empty($_conf['login_log_rec'])) {
                    $recnum = isset($_conf['login_log_rec_num']) ? intval($_conf['login_log_rec_num']) : 100;
                    P2Util::recAccessLog($_conf['login_failed_log_file'], $recnum);
                }

                return false;
            }

            // パスワード設定があれば、セットする
            if (isset($rec_login_pass_x) && strlen($rec_login_pass_x) > 0) {
                $this->pass_x = $rec_login_pass_x;
            }
        }

        // 認証設定 or パスワード記録がなかった場合はここまで
        if (!$this->pass_x) {

            // 新規登録でなければエラー表示
            if (empty($_POST['submit_new'])) {
                P2Util::pushInfoHtml('<p>p2 error: ログインエラー</p>');
            }

            return false;
        }

        // {{{ クッキー認証パススルー

        if (isset($_COOKIE['cid'])) {

            if ($this->checkUserPwWithCid($_COOKIE['cid'])) {
                return true;

            // Cookie認証が通らなければ
            } else {
                // 古いクッキーをクリアしておく
                $this->clearCookieAuth();
            }
        }

        // }}}
        // {{{ すでにセッションが登録されていたら、セッションで認証

        if (isset($_SESSION['login_user']) && isset($_SESSION['login_pass_x'])) {

            // セッションが利用されているなら、セッションの妥当性チェック
            if (isset($_p2session)) {
                if ($msg = $_p2session->checkSessionError()) {
                    P2Util::pushInfoHtml('<p>p2 error: ' . p2h($msg) . '</p>');
                    //Session::unSession();
                    // ログイン失敗
                    return false;
                }
            }

            if ($this->user_u == $_SESSION['login_user']) {
                if ($_SESSION['login_pass_x'] != $this->pass_x) {
                    Session::unSession();
                    return false;

                } else {
                    return true;
                }
            }
        }

        // }}}

        $mobile = (new Net_UserAgent_Mobile)->singleton();

        // {{{ フォームからログインした時

        if (!empty($_POST['submit_member'])) {

            // フォームログイン成功なら
            if ($_POST['form_login_id'] == $this->user_u and sha1($_POST['form_login_pass']) == $this->pass_x) {

                // 古いクッキーをクリアしておく
                $this->clearCookieAuth();

                // ログインログを記録する
                $this->logLoginSuccess();

                // リダイレクト
                return $_SERVER['REQUEST_URI'];
                //return true;

            // フォームログイン失敗なら
            } else {
                P2Util::pushInfoHtml('<p>p2 info: ログインできませんでした。<br>ユーザ名かパスワードが違います。</p>');
                $_login_failed_flag = true;

                // ログイン失敗ログを記録する
                $this->logLoginFailed();

                return false;
            }
        }

        // }}}

        return false;
    }

    // }}}
    // {{{ logLoginSuccess()

    /**
     * ログインログを記録する
     */
    public function logLoginSuccess()
    {
        global $_conf;

        if (!empty($_conf['login_log_rec'])) {
            $recnum = isset($_conf['login_log_rec_num']) ? intval($_conf['login_log_rec_num']) : 100;
            P2Util::recAccessLog($_conf['login_log_file'], $recnum);
        }

        return true;
    }

    // }}}
    // {{{ logLoginFailed()

    /**
     * ログイン失敗ログを記録する
     */
    public function logLoginFailed()
    {
        global $_conf;

        if (!empty($_conf['login_log_rec'])) {
            $recnum = isset($_conf['login_log_rec_num']) ? intval($_conf['login_log_rec_num']) : 100;
            P2Util::recAccessLog($_conf['login_failed_log_file'], $recnum, 'txt');
        }

        return true;
    }

    // }}}
    // {{{ _registerAuth()

    /**
     * 端末IDを認証ファイル登録する
     */
    private function _registerAuth($key, $sub_id, $auth_file)
    {
        global $_conf;

        $cont = <<<EOP
<?php
\${$key}='{$sub_id}';\n
EOP;
        $fp = fopen($auth_file, 'wb');
        if (!$fp) {
            P2Util::pushInfoHtml('<p>Error: データを保存できませんでした。認証登録失敗。</p>');
            return false;
        }
        flock($fp, LOCK_EX);
        fwrite($fp, $cont);
        flock($fp, LOCK_UN);
        fclose($fp);
        return true;
    }

    // }}}
    // {{{ _registerAuthOff()

    /**
     * 端末IDの認証ファイル登録を外す
     */
    private function _registerAuthOff($auth_file)
    {
        if (file_exists($auth_file)) {
            unlink($auth_file);
        }
    }

    // }}}
    // {{{ makeUser()

    /**
     * 新規ユーザを作成する
     */
    public function makeUser($user_u, $pass)
    {
        global $_conf;

        $login_user = strval($user_u);
        $hashed_login_pass = sha1($pass);
        $login_user_repr = var_export($login_user, true);
        $login_pass_repr = var_export($hashed_login_pass, true);
        $auth_user_cont = <<<EOP
<?php
\$rec_login_user_u = {$login_user_repr};
\$rec_login_pass_x = {$login_pass_repr};\n
EOP;
        if (FileCtl::file_write_contents($_conf['auth_user_file'], $auth_user_cont) === false) {
            p2die("{$_conf['auth_user_file']} を保存できませんでした。認証{$p_str['user']}登録失敗。");
        }

        return true;
    }

    // }}}
    // {{{ registerCookie()

    /**
     * cookie認証を登録/解除する
     *
     * @param void
     * @return boolean
     */
    public function registerCookie()
    {
        $r = true;

        if (!empty($_REQUEST['ctl_keep_login'])) {
            if (!empty($_REQUEST['keep_login'])) {
                $r = $this->setCookieCid($this->user_u, $this->pass_x);
            } else {
                // クッキーをクリア
                $this->clearCookieAuth();
            }
        }

        return $r;
    }

    // }}}
    // {{{ clearCookieAuth()

    /**
     * Cookie認証をクリアする
     */
    public function clearCookieAuth()
    {
        setcookie('cid', '', time() - 3600);
        $_COOKIE = array();

        return true;
    }

    // }}}
    // {{{ setCookieCid()

    /**
     * CIDをcookieにセットする
     *
     * @param string $user_u
     * @param string $pass_x
     * @return boolean
     */
    protected function setCookieCid($user_u, $pass_x)
    {
        global $_conf;

        $time = time() + 60*60*24 * $_conf['cid_expire_day'];

        if ($cid = $this->makeCid($user_u, $pass_x)) {
            return P2Util::setCookie('cid', $cid, $time);
        }
        return false;
    }

    // }}}
    // {{{ makeCid()

    /**
     * IDとPASSと時間をくるめて暗号化したCookie情報（CID）を生成取得する
     *
     * @return mixed
     */
    public function makeCid($user_u, $pass_x)
    {
        if (is_null($user_u) || is_null($pass_x)) {
            return false;
        }

        $user_time  = $user_u . ':' . time() . ':';
        $md5_utpx = md5($user_time . $pass_x);
        $cid_src  = $user_time . $md5_utpx;
        if (isset($_SESSION['device_pixel_ratio'])) {
            $cid_src .= ':' . $_SESSION['device_pixel_ratio'];
        }
        return MD5Crypt::encrypt($cid_src, self::getMd5CryptPassForCid());
    }

    // }}}
    // {{{ getCidInfo()

    /**
     * Cookie（CID）からユーザ情報を得る
     *
     * @return array|false 成功すれば配列、失敗なら false を返す
     */
    public function getCidInfo($cid)
    {
        global $_conf;

        $dec = MD5Crypt::decrypt($cid, self::getMd5CryptPassForCid());

        $cid_info = explode(':', $dec);
        switch (count($cid_info)) {
            case 3:
                break;
            case 4:
                $device_pixel_ratio = floatval(array_pop($cid_info));
                if (isset($GLOBALS['_p2session'])
                    && ($device_pixel_ratio === 1.5 || $device_pixel_ratio === 2.0)
                ) {
                    $_SESSION['device_pixel_ratio'] = $device_pixel_ratio;
                }
                break;
            default:
                return false;
        }

        list($user, $time, $md5_utpx) = $cid_info;
        if (!strlen($user) || !$time || !$md5_utpx) {
            return false;
        }

        // 有効期限 日数
        if (time() > $time + (60*60*24 * $_conf['cid_expire_day'])) {
            return false; // 期限切れ
        }

        return $cid_info;
    }

    // }}}
    // {{{ getUserFromCid()

    /**
     * Cookie情報（CID）からuserを得る
     *
     * @return mixed
     */
    public function getUserFromCid($cid)
    {
        if (!$ar = $this->getCidInfo($cid)) {
            return false;
        }

        return $user = $ar[0];
    }

    // }}}
    // {{{ checkUserPwWithCid()

    /**
     * Cookie情報（CID）とuser, passを照合する
     *
     * @return boolean
     */
    public function checkUserPwWithCid($cid)
    {
        global $_conf;

        if (is_null($this->user_u) || is_null($this->pass_x) || is_null($cid)) {
            return false;
        }

        if (!$ar = $this->getCidInfo($cid)) {
            return false;
        }

        $time = $ar[1];
        $pw_enc = $ar[2];

        // PWを照合
        if ($pw_enc == md5($this->user_u . ':' . $time . ':' . $this->pass_x)) {
            return true;
        } else {
            return false;
        }
    }

    // }}}
    // {{{ getMd5CryptPassForCid()

    /**
     * MD5Crypt::encrypt, MD5Crypt::decrypt のための password(salt) を得る
     * （クッキーのcidの生成に利用している）
     *
     * @param   void
     * @access  private
     * @return  string
     */
    static private function getMd5CryptPassForCid()
    {
        static $pass = null;

        if ($pass !== null) {
            return $pass;
        }

        $seed = $_SERVER['SERVER_SOFTWARE'];
        $pass = md5($seed, true);

        return $pass;
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
