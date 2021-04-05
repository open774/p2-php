<?php

// {{{ Login

/**
 * rep2 - ���O�C���F�؂������N���X
 *
 * @create  2005/6/14
 * @author aki
 */
class Login
{
    // {{{ properties

    public $user;   // ���[�U���i�����I�Ȃ��́j
    public $user_u; // ���[�U���i���[�U�ƒ��ڐG��镔���j
    public $pass_x; // �Í������ꂽ�p�X���[�h

    // }}}
    // {{{ constructor

    /**
     * �R���X�g���N�^
     */
    public function __construct()
    {
        $login_user = $this->setdownLoginUser();

        // ���[�U�����w�肳��Ă��Ȃ����
        if ($login_user === null) {

            // ���O�C�����s
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
     * ���[�U�����Z�b�g����
     */
    public function setUser($user)
    {
        $this->user_u = $user;
        $this->user = $user;
    }

    // }}}
    // {{{ setdownLoginUser()

    /**
     * ���O�C�����[�U���̎w��𓾂�
     */
    public function setdownLoginUser()
    {
        global $_conf;
        $login_user = null;

        // ���[�U������̗D�揇�ʂɉ�����

        // ���O�C���t�H�[������̎w��
        if (!empty($GLOBALS['brazil'])) {
            $add_mail = '.,@-';
        } else {
            $add_mail = '';
        }
        if (isset($_REQUEST['form_login_id']) && preg_match("/^[0-9A-Za-z_{$add_mail}]+\$/", $_REQUEST['form_login_id'])) {
            $login_user = $this->setdownLoginUserWithRequest();

        // GET�����ł̎w��
        } elseif (isset($_REQUEST['user']) && preg_match("/^[0-9A-Za-z_{$add_mail}]+\$/", $_REQUEST['user'])) {
            $login_user = $_REQUEST['user'];

        // Cookie�Ŏw��
        } elseif (isset($_COOKIE['cid']) && ($user = $this->getUserFromCid($_COOKIE['cid'])) !== false) {
            if (preg_match("/^[0-9A-Za-z_{$add_mail}]+\$/", $user)) {
                $login_user = $user;
            }

        // Session�Ŏw��
        } elseif (isset($_SESSION['login_user']) && preg_match("/^[0-9A-Za-z_{$add_mail}]+\$/", $_SESSION['login_user'])) {
            $login_user = $_SESSION['login_user'];

        // �O���F�؂Ŏw��
        } elseif ($_conf['external_authentication'] && isset($_SERVER['REMOTE_USER']) && (preg_match("/^[0-9A-Za-z_{$add_mail}]+\$/", $_SERVER['REMOTE_USER']))) {
            $login_user = $_SERVER['REMOTE_USER'];
        }

        return $login_user;
    }

    // }}}
    // {{{ setdownLoginUserWithRequest()

    /**
     * REQUEST���烍�O�C�����[�U���̎w��𓾂�
     */
    public function setdownLoginUserWithRequest()
    {
        return $_REQUEST['form_login_id'];
    }

    // }}}
    // {{{ authorize()

    /**
     * �F�؂��s��
     */
    public function authorize()
    {
        global $_conf, $_p2session;

        // {{{ �F�؃`�F�b�N

        $auth_result = $this->_authCheck();
        if (!$auth_result) {
            // ���O�C�����s
            if (!function_exists('printLoginFirst')) {
                include P2_LIB_DIR . '/login_first.inc.php';
            }
            printLoginFirst($this);
            exit;
        }

        // }}}

        // �����O�C��OK�Ȃ�

        // {{{ ���O�A�E�g�̎w�肪�����

        if (!empty($_REQUEST['logout'])) {

            // �Z�b�V�������N���A�i�A�N�e�B�u�A��A�N�e�B�u���킸�j
            Session::unSession();

            // �⏕�F�؂��N���A
            $this->clearCookieAuth();

            $url = rtrim(dirname(P2Util::getMyUrl()), '/') . '/'; // . $user_u_q;

            header('Location: '.$url);
            exit;
        }

        // }}}
        // {{{ �Z�b�V���������p����Ă���Ȃ�A�Z�b�V�����ϐ��̍X�V

        if (isset($_p2session)) {

            // ���[�U���ƃp�XX���X�V
            $_SESSION['login_user']   = $this->user_u;
            $_SESSION['login_pass_x'] = $this->pass_x;
            if (!array_key_exists('login_microtime', $_SESSION)) {
                $_SESSION['login_microtime'] = microtime();
            }

            // devicePixelRatio�w�肪����Εێ�
            if (!empty($_REQUEST['device_pixel_ratio'])) {
                $device_pixel_ratio = floatval($_REQUEST['device_pixel_ratio']);
                if ($device_pixel_ratio === 1.5 || $device_pixel_ratio === 2.0) {
                    $_SESSION['device_pixel_ratio'] = $device_pixel_ratio;
                }
            }
        }

        // }}}
        // {{{ �v��������΁A�⏕�F�؂�o�^

        $this->registerCookie();

        // }}}

        // �Z�b�V������F�؈ȊO�Ɏg��Ȃ��ꍇ�͕���
        if (P2_SESSION_CLOSE_AFTER_AUTHENTICATION) {
            session_write_close();
        }

        // _authCheck() ���������Ԃ����Ƃ��́AURL�ƌ��Ȃ��ă��_�C���N�g
        if (is_string($auth_result)) {
            header('Location: ' . $auth_result);
            exit;
        }

        return true;
    }

    // }}}
    // {{{ checkAuthUserFile()

    /**
     * �F�؃��[�U�ݒ�̃t�@�C���𒲂ׂāA�����ȃf�[�^�Ȃ�̂ĂĂ��܂�
     */
    public function checkAuthUserFile()
    {
        global $_conf;

        if (@include($_conf['auth_user_file'])) {
            // ���[�U��񂪂Ȃ�������A�t�@�C�����̂ĂĔ�����
            if (empty($rec_login_user_u) || empty($rec_login_pass_x)) {
                unlink($_conf['auth_user_file']);
            }
        }

        return true;
    }

    // }}}
    // {{{ _authCheck()

    /**
     * �F�؂̃`�F�b�N���s��
     *
     * @return bool
     */
    private function _authCheck()
    {
        global $_conf;
        global $_login_failed_flag;
        global $_p2session;

        $this->checkAuthUserFile();

        // �F�؃��[�U�ݒ�i�t�@�C���j��ǂݍ��݂ł�����
        if (file_exists($_conf['auth_user_file'])) {
            include $_conf['auth_user_file'];

            // ���[�U�����������A�F�؎��s�Ŕ�����
            if ($this->user_u != $rec_login_user_u) {
                P2Util::pushInfoHtml('<p>p2 error: ���O�C���G���[</p>');

                // ���O�C�����s���O���L�^����
                if (!empty($_conf['login_log_rec'])) {
                    $recnum = isset($_conf['login_log_rec_num']) ? intval($_conf['login_log_rec_num']) : 100;
                    P2Util::recAccessLog($_conf['login_failed_log_file'], $recnum);
                }

                return false;
            }

            // �p�X���[�h�ݒ肪����΁A�Z�b�g����
            if (isset($rec_login_pass_x) && strlen($rec_login_pass_x) > 0) {
                $this->pass_x = $rec_login_pass_x;
            }
        }

        // �F�ؐݒ� or �p�X���[�h�L�^���Ȃ������ꍇ�͂����܂�
        if (!$this->pass_x) {

            // �V�K�o�^�łȂ���΃G���[�\��
            if (empty($_POST['submit_new'])) {
                P2Util::pushInfoHtml('<p>p2 error: ���O�C���G���[</p>');
            }

            return false;
        }

        // {{{ �N�b�L�[�F�؃p�X�X���[

        if (isset($_COOKIE['cid'])) {

            if ($this->checkUserPwWithCid($_COOKIE['cid'])) {
                return true;

            // Cookie�F�؂��ʂ�Ȃ����
            } else {
                // �Â��N�b�L�[���N���A���Ă���
                $this->clearCookieAuth();
            }
        }

        // }}}
        // {{{ ���łɃZ�b�V�������o�^����Ă�����A�Z�b�V�����ŔF��

        if (isset($_SESSION['login_user']) && isset($_SESSION['login_pass_x'])) {

            // �Z�b�V���������p����Ă���Ȃ�A�Z�b�V�����̑Ó����`�F�b�N
            if (isset($_p2session)) {
                if ($msg = $_p2session->checkSessionError()) {
                    P2Util::pushInfoHtml('<p>p2 error: ' . p2h($msg) . '</p>');
                    //Session::unSession();
                    // ���O�C�����s
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

        // {{{ �t�H�[�����烍�O�C��������

        if (!empty($_POST['submit_member'])) {

            // �t�H�[�����O�C�������Ȃ�
            if ($_POST['form_login_id'] == $this->user_u and sha1($_POST['form_login_pass']) == $this->pass_x) {

                // �Â��N�b�L�[���N���A���Ă���
                $this->clearCookieAuth();

                // ���O�C�����O���L�^����
                $this->logLoginSuccess();

                // ���_�C���N�g
                return $_SERVER['REQUEST_URI'];
                //return true;

            // �t�H�[�����O�C�����s�Ȃ�
            } else {
                P2Util::pushInfoHtml('<p>p2 info: ���O�C���ł��܂���ł����B<br>���[�U�����p�X���[�h���Ⴂ�܂��B</p>');
                $_login_failed_flag = true;

                // ���O�C�����s���O���L�^����
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
     * ���O�C�����O���L�^����
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
     * ���O�C�����s���O���L�^����
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
     * �[��ID��F�؃t�@�C���o�^����
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
            P2Util::pushInfoHtml('<p>Error: �f�[�^��ۑ��ł��܂���ł����B�F�ؓo�^���s�B</p>');
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
     * �[��ID�̔F�؃t�@�C���o�^���O��
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
     * �V�K���[�U���쐬����
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
            p2die("{$_conf['auth_user_file']} ��ۑ��ł��܂���ł����B�F��{$p_str['user']}�o�^���s�B");
        }

        return true;
    }

    // }}}
    // {{{ registerCookie()

    /**
     * cookie�F�؂�o�^/��������
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
                // �N�b�L�[���N���A
                $this->clearCookieAuth();
            }
        }

        return $r;
    }

    // }}}
    // {{{ clearCookieAuth()

    /**
     * Cookie�F�؂��N���A����
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
     * CID��cookie�ɃZ�b�g����
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
     * ID��PASS�Ǝ��Ԃ�����߂ĈÍ�������Cookie���iCID�j�𐶐��擾����
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
     * Cookie�iCID�j���烆�[�U���𓾂�
     *
     * @return array|false ��������Δz��A���s�Ȃ� false ��Ԃ�
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

        // �L������ ����
        if (time() > $time + (60*60*24 * $_conf['cid_expire_day'])) {
            return false; // �����؂�
        }

        return $cid_info;
    }

    // }}}
    // {{{ getUserFromCid()

    /**
     * Cookie���iCID�j����user�𓾂�
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
     * Cookie���iCID�j��user, pass���ƍ�����
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

        // PW���ƍ�
        if ($pw_enc == md5($this->user_u . ':' . $time . ':' . $this->pass_x)) {
            return true;
        } else {
            return false;
        }
    }

    // }}}
    // {{{ getMd5CryptPassForCid()

    /**
     * MD5Crypt::encrypt, MD5Crypt::decrypt �̂��߂� password(salt) �𓾂�
     * �i�N�b�L�[��cid�̐����ɗ��p���Ă���j
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
