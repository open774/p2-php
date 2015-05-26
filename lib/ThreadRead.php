<?php
/**
 * rep2 - �X���b�h ���[�h �N���X
 */

// +Wiki
require_once P2_LIB_DIR . '/wiki/DatPluginCtl.php';

// {{{ ThreadRead

/**
 * �X���b�h���[�h�N���X
 */
class ThreadRead extends Thread {
    // {{{ properties
    public $datlines; // dat����ǂݍ��񂾃��C�����i�[����z��
    public $resrange; // array('start' => i, 'to' => i, 'nofirst' => bool)
    public $onbytes; // �T�[�o����擾����dat�T�C�Y
    public $diedat; // �T�[�o����dat�擾���悤�Ƃ��Ăł��Ȃ���������true���Z�b�g�����
    public $onthefly; // ���[�J����dat�ۑ����Ȃ��I���U�t���C�ǂݍ��݂Ȃ�true
    public $idp; // ���X�ԍ����L�[�AID�̑O�̕����� ("ID:", " " ��) ��l�Ƃ���A�z�z��
    public $ids; // ���X�ԍ����L�[�AID��l�Ƃ���A�z�z��
    public $idcount; // ID���L�[�A�o���񐔂�l�Ƃ���A�z�z��
    public $getdat_error_msg_ht; // dat�擾�Ɏ��s�������ɕ\������郁�b�Z�[�W�iHTML�j
    public $old_host; // �z�X�g�ړ]���o���A�ړ]�O�̃z�X�g��ێ�����
    private $getdat_error_body; // dat�擾�Ɏ��s��������203�Ŏ擾�ł���BODY
    public $datochi_residuums; // dat�擾�Ɏ��s��������203�Ŏ擾�ł���datline�̔z��i���X��=>datline�j

    // }}}
    // {{{ constructor

    /**
     * �R���X�g���N�^
     */
    public function __construct() {
        parent::__construct ();
        $this->getdat_error_msg_ht = "";
    }

    // }}}
    // {{{ downloadDat()

    /**
     * DAT���_�E�����[�h����
     */
    public function downloadDat() {
        global $_conf;

        // �܂�BBS
        if (P2Util::isHostMachiBbs ($this->host)) {
            return DownloadDatMachiBbs::invoke ($this);
            // JBBS@�������
        } elseif (P2Util::isHostJbbsShitaraba ($this->host)) {
            if (! function_exists ('shitarabaDownload')) {
                include P2_LIB_DIR . '/read_shitaraba.inc.php';
            }
            return shitarabaDownload ($this);

            // 2ch�n
        } else {
            $this->getDatBytesFromLocalDat (); // $aThread->length ��set
            $pinktest = "/\w+\.bbspink.com/";

            // 2ch bbspink���ǂ�
            if (P2Util::isHost2chs ($this->host) && ! empty ($_GET['maru'])) {
                // ���O�C�����ĂȂ���� or ���O�C����A24���Ԉȏ�o�߂��Ă����玩���ă��O�C��
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

            // 2ch�̉ߋ����O�q�ɓǂ�
            } elseif (! empty ($_GET['kakolog']) && ! empty ($_GET['kakoget'])) {
                if ($_GET['kakoget'] == 1) {
                    $ext = '.dat.gz';
                } elseif ($_GET['kakoget'] == 2) {
                    $ext = '.dat';
                }
                return $this->_downloadDat2chKako ($_GET['kakolog'], $ext);

            // 2ch ��API�o�R�ŗ��Ƃ�
            } elseif (P2Util::isHost2chs ($this->host) && $_conf['2chapi_use'] && empty ($_GET['olddat'])) {

                // ���O�C�����ĂȂ���� or ���O�C����A�ݒ肵�����Ԍo�߂��Ă����玩���ă��O�C��
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

                // 2ch �ȊO�̊O����
                // DAT������DL����
                return $this->_downloadDat2ch ($this->length);
            }
        }
    }

    // }}}
    // {{{ _downloadDat2chAPI()

    /**
     * 2chAPI�� DAT �������_�E�����[�h����
     *
     * @return mix �擾�ł������A�X�V���Ȃ������ꍇ��true��Ԃ�
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

        // >>1�v���r���[�̎��͍����擾���Ȃ��ėǂ��̂ŏ��true(�V������)��Ԃ�
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

        $purl = parse_url ($url); // URL����

        try {
            $req = P2Util::getHTTPRequest2 ($url, HTTP_Request2::METHOD_POST);

            // �w�b�_
            $req->setHeader ('User-Agent', $ReadUA);

            if (! empty ($_GET['one'])) {
                // >>1�v���r���[�̎��̓T�[�o�[�ɍŏ��̕�����������
                $req->setHeader ('Range', "bytes=0-8192");
            } elseif (! $zero_read) {
                $req->setHeader ('Range', sprintf ('bytes=%d-', $from_bytes) );
            }

            if ($this->modified) {
                $req->setHeader ('If-Modified-Since', $this->modified);
            }

            // Basic�F�ؗp�̃w�b�_
            if (isset ($purl['user']) && isset ($purl['pass'])) {
                $req->setAuth ($purl['user'], $purl['pass'], HTTP_Request2::AUTH_BASIC);
            }

            // POST������e
            $req->addPostParameter (array (
                    'sid' => $sid,
                    'hobo' => $HB,
                    'appkey' => $AppKey
            ));

            // POST�f�[�^�̑��M
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

                // 1�s�ڂ�؂�o��
                $posLF = mb_strpos ($body, "\n");
                $firstmsg = mb_substr ($body, 0, $posLF === false ? mb_strlen ($body) : $posLF);

                // ng�Ŏn�܂��Ă���api�̃G���[�̉\��
                if (preg_match ("/^ng \((.*)\)$/", $firstmsg)) {
                    if (strstr ($firstmsg, "not valid")) {
                        // sid�������ɂȂ����\���B�������F�؂��邽�ߍŏ������蒼���B
                        if (empty ($_REQUEST['relogin2chapi'])) {
                            $_REQUEST['relogin2chapi'] = true;
                            return $this->downloadDat ();
                        }
                    }
                    $this->getdat_error_msg_ht .= "<p>rep2 error: API�o�R�ł̃X���b�h�擾�Ɏ��s���܂����B" . $firstmsg . "</p>";
                    $this->getdat_error_msg_ht .= " [<a href=\"{$_conf['read_php']}?host={$this->host}&amp;bbs={$this->bbs}&amp;key={$this->key}&amp;ls={$this->ls}&amp;relogin2chapi=true\">API�ōĎ擾�����݂�</a>]";
                    $this->getdat_error_msg_ht .= " [<a href=\"{$_conf['read_php']}?host={$this->host}&amp;bbs={$this->bbs}&amp;key={$this->key}&amp;ls={$this->ls}&amp;olddat=true\">��dat�ōĎ擾�����݂�</a>]";
                    $this->diedat = true;
                    return false;
                } elseif (mb_strpos ($firstmsg, "�Q�����˂� ��<><>2015/03/13(��) 00:00:00.00 ID:????????<> 3��13�����Q") === 0) {
                    return $this->_downloadDat2chNotFound ('404');
                }
                unset ($firstmsg);

                // �����̉��s�ł��ځ[��`�F�b�N
                if (! $zero_read) {
                    if (substr ($body, 0, 1) != "\n") {
                        // echo "���ځ[�񌟏o";
                        $this->onbytes = 0;
                        $this->modified = null;
                        return $this->_downloadDat2chAPI ($sid, 0); // ���ځ[�񌟏o�B�S����蒼���B
                    }
                    $body = substr ($body, 1);
                }

                $file_append = ($zero_read) ? 0 : FILE_APPEND;

                if (FileCtl::file_write_contents ($this->keydat, $body, $file_append) === false) {
                    p2die ('cannot write file.');
                }

                // $GLOBALS['debug'] && $GLOBALS['profiler']->enterSection("dat_size_check");
                // �擾��T�C�Y�`�F�b�N
                if ($zero_read == false && $this->onbytes) {
                    $this->getDatBytesFromLocalDat (); // $aThread->length ��set
                    if ($this->onbytes != $this->length) {
                        $this->onbytes = 0;
                        $this->modified = null;
                        P2Util::pushInfoHtml ("<p>rep2 info: {$this->onbytes}/{$this->length} �t�@�C���T�C�Y���ςȂ̂ŁAdat���Ď擾</p>");
                        // $GLOBALS['debug'] && $GLOBALS['profiler']->leaveSection("dat_size_check");
                        return $this->_downloadDat2chAPI ($sid, 0); // dat�T�C�Y�͕s���B�S����蒼���B
                    }
                }

                $this->isonline = true;
                return true;
            } elseif ($code == '302') { // Found
                                        // �z�X�g�̈ړ]��ǐ�
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
                                        // echo "���ځ[�񌟏o";
                $this->onbytes = 0;
                $this->modified = null;
                return $this->_downloadDat2chAPI ($sid, 0); // ���ځ[�񌟏o�B�S����蒼���B
            } else {
                return $this->_downloadDat2chNotFound ($code);
            }

        } catch (Exception $e) {
            $this->getdat_error_msg_ht .= "<p>�T�[�o�ڑ��G���[: " . $e->getMessage ();
            $this->getdat_error_msg_ht .= "<br>rep2 error: �T�[�o�ւ̐ڑ��Ɏ��s���܂����B</p>";
            $this->diedat = true;
            return false;
        }
    }

    // }}}
    // {{{ _downloadDat2ch()

    /**
     * �W�����@�� 2ch�݊� DAT �������_�E�����[�h����
     *
     * @return mix �擾�ł������A�X�V���Ȃ������ꍇ��true��Ԃ�
     */
    protected function _downloadDat2ch($from_bytes) {
        global $_conf;
        global $debug;

        if (! ($this->host && $this->bbs && $this->key)) {
            return false;
        }

        // >>1�v���r���[�̎��͍����擾���Ȃ��ėǂ��̂ŏ��true(�V������)��Ԃ�
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

        $purl = parse_url ($url); // URL����
                                    // $request .= "Accept-Charset: Shift_JIS\r\n";
                                    // $request .= "Accept-Encoding: gzip, deflate\r\n";

        try {
            $req = P2Util::getHTTPRequest2 ($url, HTTP_Request2::METHOD_GET);
            // �w�b�_
            $req->setHeader ('Referer', "http://{$purl['host']}/{$this->bbs}/");

            if (! empty ($_GET['one'])) {
                // >>1�v���r���[�̎��̓T�[�o�[�ɍŏ��̕�����������
                $req->setHeader ('Range', "bytes=0-8192");
            } elseif (! $zero_read) {
                $req->setHeader ('Range', sprintf ('bytes=%d-', $from_bytes) );
            }

            if ($this->modified) {
                $req->setHeader ('If-Modified-Since', $this->modified);
            }

            // Basic�F�ؗp�̃w�b�_
            if (isset ($purl['user']) && isset ($purl['pass'])) {
                $req->setAuth ($purl['user'], $purl['pass'], HTTP_Request2::AUTH_BASIC);
            }

            // Request�̑��M
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

                // �z�X�g��2ch�̎���DAT�𗘗p�ł��Ȃ��|�̃��b�Z�[�W���o����G���[�Ƃ���iDAT�j���΍�j
                if (P2Util::isHost2chs ($this->host)) {
                    // 1�s�ڂ�؂�o��
                    $posLF = mb_strpos ($body, "\n");
                    $firstmsg = mb_substr ($body, 0, $posLF === false ? mb_strlen ($body) : $posLF);

                    if (mb_strpos ($firstmsg, "�Q�����˂� ��<><>2015/03/13(��) 00:00:00.00 ID:????????<> 3��13�����Q") === 0) {
                        $this->getdat_error_msg_ht .= "<p>rep2 error: �T�[�o����ڑ������ۂ���܂���<br>rep2 info: 2�����˂��DAT�񋟂͏I�����܂���</p>";
                        $this->diedat = true;
                        return false;
                    }
                    unset ($firstmsg);
                }

                // �����̉��s�ł��ځ[��`�F�b�N
                if (! $zero_read) {
                    if (substr ($body, 0, 1) != "\n") {
                        // echo "���ځ[�񌟏o";
                        $this->onbytes = 0;
                        $this->modified = null;
                        return $this->_downloadDat2ch (0); // ���ځ[�񌟏o�B�S����蒼���B
                    }
                    $body = substr ($body, 1);
                }

                $file_append = ($zero_read) ? 0 : FILE_APPEND;

                if (FileCtl::file_write_contents ($this->keydat, $body, $file_append) === false) {
                    p2die ('cannot write file.');
                }

                // $GLOBALS['debug'] && $GLOBALS['profiler']->enterSection("dat_size_check");
                // �擾��T�C�Y�`�F�b�N
                if ($zero_read == false && $this->onbytes) {
                    $this->getDatBytesFromLocalDat (); // $aThread->length ��set
                    if ($this->onbytes != $this->length) {
                        $this->onbytes = 0;
                        $this->modified = null;
                        P2Util::pushInfoHtml ("<p>rep2 info: {$this->onbytes}/{$this->length} �t�@�C���T�C�Y���ςȂ̂ŁAdat���Ď擾</p>");
                        // $GLOBALS['debug'] && $GLOBALS['profiler']->leaveSection("dat_size_check");
                        return $this->_downloadDat2ch (0); // dat�T�C�Y�͕s���B�S����蒼���B
                    }
                }

                $this->isonline = true;
                return true;
            } elseif ($code == '302') { // Found
                                        // �z�X�g�̈ړ]��ǐ�
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
                                        // echo "���ځ[�񌟏o";
                $this->onbytes = 0;
                $this->modified = null;
                return $this->_downloadDat2ch (0); // ���ځ[�񌟏o�B�S����蒼���B
            } else {
                return $this->_downloadDat2chNotFound ($code);
            }
        } catch (Exception $e) {
            $this->getdat_error_msg_ht .= "<p>�T�[�o�ڑ��G���[: " . $e->getMessage ();
            $this->getdat_error_msg_ht .= "<br>rep2 error: �T�[�o�ւ̐ڑ��Ɏ��s���܂����B</p>";
            $this->diedat = true;
            return false;
        }
    }

    // }}}
    // {{{ _downloadDat2chNotFound()

    /**
     * 2ch DAT���_�E�����[�h�ł��Ȃ������Ƃ��ɌĂяo�����
     */
    protected function _downloadDat2chNotFound($code = null) {
        // 2ch, bbspink �Ȃ�read.cgi�Ŋm�F
        $this->getdat_error_msg_ht .= $this->get2chDatError ($code);
        $this->diedat = true;
        return false;
    }

    // }}}
    // {{{ _downloadDat2chMaru()

    /**
     * 2ch���p DAT���_�E�����[�h����
     *
     * @param string $uaMona
     * @param string $SID2ch
     * @param bool $shirokuma
     *            true�Ȃ�offlaw2�Ŏ擾
     * @return bool
     * @see lib/login2ch.inc.php
     */
    protected function _downloadDat2chMaru($uaMona, $SID2ch, $shirokuma = false) {
        global $_conf;

        if (! ($this->host && $this->bbs && $this->key && $this->keydat)) {
            return false;
        }

        // �Q�l�Ή�
        $rokkasystem = explode (".", $this->host, 2);
        $url = "http://rokka.$rokkasystem[1]/$rokkasystem[0]/{$this->bbs}/{$this->key}/?raw=0.0&sid=";
        $url .= rawurlencode ($SID2ch);
        $purl = parse_url ($url); // URL����

        try {
            $req = P2Util::getHTTPRequest2 ($url, HTTP_Request2::METHOD_GET);
            // �w�b�_
            $req->setHeader ('User-Agent', "{$uaMona} ({$_conf['p2ua']})");

            // Request�̑��M
            $response = $req->send ();

            $code = $response->getStatus ();

            if ($code == '200' || $code == '206') { // Partial Content
                $body = $response->getBody ();

                $this->onbytes = intval ($response->getHeader ('Content-Length'));

                $this->modified = $response->getHeader ('Last-Modified');

                if (FileCtl::file_write_contents ($this->keydat, $body, 0) === false) {
                    p2die ('cannot write file. downloadDat2chMaru()');
                }

                // �N���[�j���O =====
                if ($marudatlines = FileCtl::file_read_lines ($this->keydat)) {
                    if (! $shirokuma) {
                        $firstline = array_shift ($marudatlines);
                        // �`�����N�Ƃ�
                        if (strpos ($firstline, 'Success') === false) { // �Q�l(rokka)�Ή�
                            $secondline = array_shift ($marudatlines);
                        }
                    }
                    $cont = '';
                    foreach ($marudatlines as $aline) {
                        $cont .= $aline;
                    }
                    if (FileCtl::file_write_contents ($this->keydat, $cont) === false) {
                        p2die ('cannot write file. downloadDat2chMaru()');
                    }
                }

                return true;
            } elseif ($code == '304') {
                return '304 Not Modified';
            } else {
                return $this->_downloadDat2chMaruNotFound ($code);
            }
        } catch (Exception $e) {
            $this->getdat_error_msg_ht .= "<p>�T�[�o�ڑ��G���[: " . $e->getMessage ();
            $this->getdat_error_msg_ht .= "<br>rep2 error: �T�[�o�ւ̐ڑ��Ɏ��s���܂����B</p>";
            $this->diedat = true;
            return false;
        }
    }

    // }}}
    // {{{ _downloadDat2chMaruNotFound()

    /**
     * ��ID�ł̎擾���ł��Ȃ������Ƃ��ɌĂяo�����
     */
    protected function _downloadDat2chMaruNotFound() {
        global $_conf;

        // �ă`�������W���܂��Ȃ�A�ă`�������W����BSID���ύX����Ă��܂��Ă���ꍇ�����鎞�̂��߂̎����`�������W�B
        if (empty ($_REQUEST['relogin2ch']) && empty ($_REQUEST['shirokuma'])) {
            $_REQUEST['relogin2ch'] = true;
            return $this->downloadDat ();
        } else {
            $remarutori_ht = $this->_generateMarutoriLink (true);
            $this->getdat_error_msg_ht .= "<p>rep2 info: ��ID�ł̃X���b�h�擾�Ɏ��s���܂����B{$remarutori_ht}{$moritori_ht}</p>";
            $this->diedat = true;
            return false;
        }
    }

    // }}}
    // {{{ _downloadDat2chKako()

    /**
     * 2ch�̉ߋ����O�q�ɂ���dat.gz���_�E�����[�h���𓀂���
     */
    protected function _downloadDat2chKako($uri, $ext) {
        global $_conf;

        $url = $uri . $ext;

        $purl = parse_url ($url); // URL����

        try {
            $req = P2Util::getHTTPRequest2 ($url, HTTP_Request2::METHOD_GET);

            // Request�̑��M
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
            $this->getdat_error_msg_ht .= "<p>�T�[�o�ڑ��G���[: " . $e->getMessage ();
            $this->getdat_error_msg_ht .= "<br>rep2 error: �T�[�o�ւ̐ڑ��Ɏ��s���܂����B</p>";
            $this->diedat = true;
            return false;
        }
    }

    // }}}
    // {{{ _downloadDat2chKakoNotFound()

    /**
     * �ߋ����O���擾�ł��Ȃ������Ƃ��ɌĂяo�����
     */
    protected function _downloadDat2chKakoNotFound($uri, $ext) {
        global $_conf;

        if ($ext == ".dat.gz") {
            // .dat.gz���Ȃ�������.dat�ł�����x
            return $this->_downloadDat2chKako ($uri, ".dat");
        }
        if (! empty ($_GET['kakolog'])) {
            $kako_html_url = p2h ($_GET['kakolog'] . '.html');
            $kakolog_ht = "<p><a href=\"{$kako_html_url}\"{$_conf['bbs_win_target_at']}>{$kako_html_url}</a></p>";
        }
        $this->getdat_error_msg_ht = "<p>rep2 info: 2�����˂�ߋ����O�q�ɂ���̃X���b�h��荞�݂Ɏ��s���܂����B</p>";
        $this->getdat_error_msg_ht .= $kakolog_ht;
        $this->diedat = true;
        return false;
    }

    // }}}
    // {{{ get2chDatError()

    /**
     * 2ch��dat���擾�ł��Ȃ�����������Ԃ�
     *
     * @return string �G���[���b�Z�[�W�i�������킩��Ȃ��ꍇ�͋�ŕԂ��j
     */
    public function get2chDatError($code = null) {
        global $_conf;

        // �z�X�g�ړ]���o�ŕύX�����z�X�g�����ɖ߂�
        if (! empty ($this->old_host)) {
            $this->host = $this->old_host;
            $this->old_host = null;
        }

        $reason = null;
        if (P2Util::isHost2chs ($this->host) || P2Util::isHostVip2ch ($this->host)) {
            if ($code == '302') {
                $body203 = $this->_get2ch203Body();
                if ($body203 !== false && preg_match('/�ߋ����O ��/', $body203)) {
                    $this->getdat_error_body = $body203;
                    if (preg_match('/���̃X���b�h�͉ߋ����O�q�ɂɊi.{1,2}����Ă��܂�/', $body203)) {
                        $reason = 'datochi';
                        $this->setDatochiResiduums();
                    } elseif (preg_match('{http://[^/]+/[^/]+/kako/\\d+(/\\d+)?/(\\d+)\\.html}', $body203, $matches)) {
                        $reason = 'kakohtml';
                    }
                }
            }
        }

        $read_url = "http://{$this->host}/test/read.cgi/{$this->bbs}/{$this->key}/";

        // {{{ read.cgi ����HTML���擾

        $read_response_html = '';
        if (! $reason) {
            try {
                $req = P2Util::getHTTPRequest2 ($read_url.'1', HTTP_Request2::METHOD_GET);
                // �w�b�_
                $req->setHeader ('User-Agent', P2Util::getP2UA(false,P2Util::isHost2chs($this->host))); // �����́A"Monazilla/" �������NG

                // Request�̑��M
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
                    $info_msg_ht .= "rep2 info: <a href=\"{$url_t}\"{$_conf['ext_win_target_at']}>{$read_url}</a> ��HTML���擾�o���܂���ł����B</p>";
                    P2Util::pushInfoHtml ($info_msg_ht);
                }
            } catch (Exception $e) {
                $this->getdat_error_msg_ht .= "<p>�T�[�o�ڑ��G���[: " . $e->getMessage ();
                $this->getdat_error_msg_ht .= "<br>rep2 error: �T�[�o�ւ̐ڑ��Ɏ��s���܂����B</p>";
                $this->diedat = true;
            }
            unset ($req, $response);
        }

        // }}}
        // {{{ �擾����HTML�i$read_response_html�j����͂��āA������������

        $dat_response_status = '';
        $dat_response_msg = '';

        $vip2ch_kakosoko_match = "/�i.{1,2}����Ă��܂��B�����������݂ł��܂���B�B/";
        $kakosoko_match = "/���̃X���b�h�͉ߋ����O�q�ɂɊi.{1,2}����Ă��܂�/";
        $kakosoko_match2 = "/http:\/\/turing1000\.nttec\.com\/?(403|404|500)\.dat/";

        $naidesu_match = "/<title>����Ȕ�or�X���b�h�Ȃ��ł��B<\/title>/";

        // 0�����˂�X�N���v�g�ɔ�������悤��
        $soukoni_match = "/<title>�����I�ߋ����O�q�ɂ�<\/title>/";

        $error3939_match = "{<title>�Q�����˂� error 3939</title>}"; // �ߋ����O�q�ɂ�html���̎��i���ɂ����邩���A�悭�m��Ȃ��j

        // <a href="http://qb5.2ch.net/sec2chd/kako/1091/10916/1091634596.html">
        // <a href="../../../../mac/kako/1004/10046/1004680972.html">
        // $kakohtml_match = "{<a href=\"\.\./\.\./\.\./\.\./([^/]+/kako/\d+(/\d+)?/(\d+)).html\">}";
        $kakohtml_match = "{/([^/]+/kako/\d+(/\d+)?/(\d+)).html\">}";
        $waithtml_match = "/html�������̂�҂��Ă���悤�ł��B/";
        $vip2ch_kakodat_match = "{/([^/]+/kako/\d+(/\d+)?/(\d+)).dat\">}"; // vip2ch.com�p

        // <title>�����̃X���b�h�͉ߋ����O�q�ɂ�
        if ($reason === 'datochi' || preg_match ($kakosoko_match, $read_response_html, $matches) || preg_match ($kakosoko_match2, $read_response_html, $matches)) {
            $dat_response_status = "���̃X���b�h�͉ߋ����O�q�ɂɊi�[����Ă��܂��B";
            $marutori_ht = $this->_generateMarutoriLink ();
            $plugin_ht = $this->_generateWikiDatLink ($read_url);
            $dat_response_msg = "<p>2ch info - ���̃X���b�h�͉ߋ����O�q�ɂɊi�[����Ă��܂��B{$marutori_ht}{$moritori_ht}{$plugin_ht}</p>";

        // <title>������Ȕ�or�X���b�h�Ȃ��ł��Bor error 3939
        } elseif ($reason === 'kakohtml' or
            preg_match ($naidesu_match, $read_response_html, $matches) ||
            preg_match ($error3939_match, $read_response_html, $matches) ||
            preg_match ($vip2ch_kakosoko_match, $read_response_html, $matches) ||
            preg_match ($soukoni_match, $read_response_html, $matches)) {

            if ($reason === 'kakohtml' or preg_match ($kakohtml_match, $read_response_html, $matches)) {
                if ($reason === 'kakohtml') {
                    preg_match ('{/([^/]+/kako/\d+(/\d+)?/(\d+)).html}', $this->getdat_error_body, $matches);
                }
                $dat_response_status = "����! �ߋ����O�q�ɂŁAhtml�����ꂽ�X���b�h�𔭌����܂����B";
                $kakolog_uri = "http://{$this->host}/{$matches[1]}";
                $kakolog_url_en = rawurlencode ($kakolog_uri);
                $read_kako_url = "{$_conf['read_php']}?host={$this->host}&amp;bbs={$this->bbs}&amp;key={$this->key}&amp;ls={$this->ls}&amp;kakolog={$kakolog_url_en}&amp;kakoget=1";
                $dat_response_msg = "<p>2ch info - ����! �ߋ����O�q�ɂŁA<a href=\"{$kakolog_uri}.html\"{$_conf['bbs_win_target_at']}>�X���b�h {$matches[3]}.html</a> �𔭌����܂����B [<a href=\"{$read_kako_url}\">rep2�Ɏ�荞��œǂ�</a>]</p>";
            } elseif (preg_match ($waithtml_match, $read_response_html, $matches)) {
                $dat_response_status = "����! �X���b�h��html�������̂�҂��Ă���悤�ł��B";
                $marutori_ht = $this->_generateMarutoriLink ();
                $dat_response_msg = "<p>2ch info - ����! �X���b�h��html�������̂�҂��Ă���悤�ł��B{$marutori_ht}{$moritori_ht}</p>";
            } elseif (preg_match ($vip2ch_kakodat_match, $read_response_html, $matches)) {
                $dat_response_status = "����! �ߋ����O�q�ɂŁAdat�𔭌����܂����B";
                $kakolog_uri = "http://{$this->host}/{$matches[1]}";
                $kakolog_url_en = rawurlencode ($kakolog_uri);
                $read_kako_url = "{$_conf['read_php']}?host={$this->host}&amp;bbs={$this->bbs}&amp;key={$this->key}&amp;ls={$this->ls}&amp;kakolog={$kakolog_url_en}&amp;kakoget=1";
                $dat_response_msg = "<p>2ch info - ����! �ߋ����O�q�ɂŁA<a href=\"{$kakolog_uri}.html\"{$_conf['bbs_win_target_at']}>�X���b�h {$this->key}.html</a> �𔭌����܂����B [<a href=\"{$read_kako_url}\">rep2�Ɏ�荞��œǂ�</a>]</p>";
            } else {
                if (! empty ($_GET['kakolog'])) {
                    $dat_response_status = '����Ȕ�or�X���b�h�Ȃ��ł��B';
                    $kako_html_url = p2h ($_GET['kakolog'] . '.html');
                    $kakolog_query = rawurlencode ($_GET['kakolog']);
                    $read_kako_url = "{$_conf['read_php']}?host={$this->host}&amp;bbs={$this->bbs}&amp;key={$this->key}&amp;ls={$this->ls}&amp;kakolog={$kakolog_query}&amp;kakoget=1";
                    $dat_response_msg = '<p>2ch info - ����Ȕ�or�X���b�h�Ȃ��ł��B</p>';
                    $dat_response_msg .= "<p><a href=\"{$kako_html_url}\"{$_conf['bbs_win_target_at']}>{$kako_html_url}</a> [<a href=\"{$read_kako_url}\">rep2�Ƀ��O����荞��œǂ�</a>]</p>";
                } else {
                    $dat_response_status = '����Ȕ�or�X���b�h�Ȃ��ł��B';
                    $dat_response_msg = '<p>2ch info - ����Ȕ�or�X���b�h�Ȃ��ł��B</p>';
                }
            }

        // ������������Ȃ��ꍇ�ł��A�Ƃ肠�����ߋ����O��荞�݂̃����N���ێ����Ă���B�Ǝv���B���܂�o���Ă��Ȃ� 2005/2/27 aki
        } elseif (! empty ($_GET['kakolog'])) {
            $dat_response_status = '';
            $kako_html_url = p2h ($_GET['kakolog'] . '.html');
            $kakolog_query = rawurlencode ($_GET['kakolog']);
            $read_kako_url = "{$_conf['read_php']}?host={$this->host}&amp;bbs={$this->bbs}&amp;key={$this->key}&amp;ls={$this->ls}&amp;kakolog={$kakolog_query}&amp;kakoget=1";
            $dat_response_msg = "<p><a href=\"{$kako_html_url}\"{$_conf['bbs_win_target_at']}>{$kako_html_url}</a> [<a href=\"{$read_kako_url}\">rep2�Ƀ��O����荞��œǂ�</a>]</p>";
        }

        // }}}

        return $dat_response_msg;
    }

    // }}}
    // {{{ previewOne()

    /**
     * >>1�݂̂��v���r���[����
     */
    public function previewOne() {
        global $_conf;

        if (! ($this->host && $this->bbs && $this->key)) {
            return false;
        }

        // �ʏ�Ɠ����悤��DAT�̎擾�����݂�B$_GET['one']���Z�b�g����Ă����2ch�݊���>>1�������Ƃ�
        $this->downloadDat ();

        // ���[�J��dat����擾
        if (is_readable ($this->keydat)) {
            $fd = fopen ($this->keydat, 'rb');
            $first_line = fgets ($fd, 32800);
            fclose ($fd);

            // be.2ch.net �Ȃ�EUC��SJIS�ϊ�
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
        $res = $aShowThread->transRes ($first_line, 1); // 1��\��
        $body .= is_array ($res) ? $res['body'] . $res['q'] : $res;
        $body .= "</div>\n";

        return $body;
    }

    // }}}
    // {{{ previewOneNotFound()

    /**
     * >>1���v���r���[�ŃX���b�h�f�[�^��������Ȃ������Ƃ��ɌĂяo�����
     */
    public function previewOneNotFound($code = null) {
        global $_conf;

        $this->diedat = true;
        // 2ch, bbspink, vip2ch �Ȃ�read.cgi�Ŋm�F
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
     * $ls�𕪉�����start��to��nofirst�����߂�
     */
    public function lsToPoint() {
        global $_conf;

        $start = 1;
        $to = false;
        $nofirst = false;

        // n���܂�ł���ꍇ�́A>>1��\�����Ȃ��i$nofirst�j
        if (strpos ($this->ls, 'n') !== false) {
            $nofirst = true;
            $this->ls = str_replace ('n', '', $this->ls);
        }

        // �͈͎w��ŕ���
        $n = explode ('-', $this->ls);
        // �͈͎w�肪�Ȃ����
        if (sizeof ($n) == 1) {
            // l�w�肪�����
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
            // all�w��Ȃ�
            } elseif ($this->ls === 'all') {
                $start = 1;
                $to = $this->rescount;
            } else {
                // ���X�Ԏw��
                if (intval ($this->ls) > 0) {
                    $this->ls = intval ($this->ls);
                    $start = $this->ls;
                    $to = $this->ls;
                    $nofirst = true;
                    // �w�肪�Ȃ� or �s���ȏꍇ�́Aall�Ɠ����\���ɂ���
                } else {
                    $start = 1;
                    $to = $this->rescount;
                }
            }
            // �͈͎w�肪�����
        } else {
            if (! $start = intval ($n[0])) {
                $start = 1;
            }
            if (! $to = intval ($n[1])) {
                $to = $this->rescount;
            }
        }

        // �V���܂Ƃߓǂ݂̕\��������
        if (isset ($GLOBALS['rnum_all_range']) and $GLOBALS['rnum_all_range'] > 0) {

            /*
             * ���g�т̐V���܂Ƃߓǂ݂��A����������ŏI��������ɁA�́u����or�X�V�v������
             *
             * ���~�b�g < �X���̕\���͈�
             * �����~�b�g�́@0
             * �X���̕\���͈͂��I����O�Ƀ��~�b�g������
             * ������
             *
             * ���~�b�g > �X���̕\���͈�
             * �����~�b�g�� +
             * ���~�b�g�����c���Ă���ԂɁA�X���̕\���͈͂��I����
             * ���X�V
             *
             * ���~�b�g = �X���̕\���͈�
             * �����~�b�g�� 0
             * �X���̕\���͈͒��x�Ń��~�b�g����������
             * ������? �X�V?
             * �����̏ꍇ���X�V�̏ꍇ������B���������̂��߁A
             * ���̃X���̎c��V���������邩�ǂ������s���Ŕ���ł��Ȃ��B
             */

            // ���~�b�g���X���̕\���͈͂�菬�����ꍇ�́A�X���̕\���͈͂����~�b�g�ɍ��킹��
            $limit_to = $start + $GLOBALS['rnum_all_range'] - 1;

            if ($limit_to < $to) {
                $to = $limit_to;

                // �X���̕\���͈͒��x�Ń��~�b�g�����������ꍇ
            } elseif ($limit_to == $to) {
                $GLOBALS['limit_to_eq_to'] = true;
            }

            // ���̃��~�b�g�́A����̃X���̕\���͈͕������炵����
            $GLOBALS['rnum_all_range'] = $GLOBALS['rnum_all_range'] - ($to - $start) - 1;

            // print_r("$start, $to, {$GLOBALS['rnum_all_range']}");
        } else {
            // �g�їp
            if ($_conf['ktai']) {
                // �\��������
                /*
                 * if ($start + $_conf['mobile.rnum_range'] -1 <= $to) {
                 * $to = $start + $_conf['mobile.rnum_range'] -1;
                 * }
                 */
                // ��X���ł́A�O����܂݁A����+1�ƂȂ�̂ŁA1���܂�����
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
     * Dat��ǂݍ���
     * $this->datlines �� set ����
     */
    public function readDat() {
        global $_conf;

        if (file_exists ($this->keydat)) {
            if ($this->datlines = FileCtl::file_read_lines ($this->keydat)) {

                // be.2ch.net �Ȃ�EUC��SJIS�ϊ�
                // �O�̂���SJIS��UTF-8�������R�[�h����̌��ɓ���Ă���
                // �E�E�E���A�������������^�C�g���̃X���b�h�Ō딻�肪�������̂ŁA�w�肵�Ă���
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
     * ��̃X�����ł�ID�o�������Z�b�g����
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
     * datline��explode����
     */
    public function explodeDatLine($aline) {
        $aline = rtrim ($aline);

        if ($this->dat_type === '2ch_old') {
            $parts = explode (',', $aline);
        } else {
            $parts = explode ('<>', $aline);
        }

        // iframe ���폜�B2ch�����퉻���ĕK�v�Ȃ��Ȃ����炱�̃R�[�h�͊O�������B2005/05/19
        $parts[3] = preg_replace ('{<(iframe|script)( .*?)?>.*?</\\1>}i', '', $parts[3]);

        return $parts;
    }

    // }}}
    // {{{ scanOriginalHosts()

    /**
     * dat�𑖍����ăX�����Ď��̃z�X�g�������o����
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
     * �f�t�H���g��dat�擾���s�G���[���b�Z�[�WHTML���擾����
     *
     * @param
     *            void
     * @return string
     */
    public function getDefaultGetDatErrorMessageHTML() {
        global $_conf;

        $diedat_msg = '<p><b>rep2 info: �T�[�o����ŐV�̃X���b�h�����擾�ł��܂���ł����B</b>';
        if ($hosts = $this->scanOriginalHosts ()) {
            $common_q = '&amp;bbs=' . rawurldecode ($this->bbs) . '&amp;key=' . rawurldecode ($this->key) . '&amp;ls=' . rawurldecode ($this->ls);
            $diedat_msg .= '<br>dat���瑼�̃z�X�g�������o���܂����B';
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
     * ��񃁃b�Z�[�W���v�b�V������
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
     * �ڑ��Ɏ��s�����|�̃��b�Z�[�W���v�b�V������
     *
     * @param string $url
     * @param int $errno
     * @param string $errstr
     * @return void
     */
    static protected function _pushInfoConnectFailed($url, $errno, $errstr) {
        $summary = sprintf ('HTTP�ڑ��G���[ (%d) %s', $errno, $errstr);
        $description = self::_urlToAnchor ($url) . ' �ɐڑ��ł��܂���ł����B';
        self::_pushInfoMessage ($summary, $description);
    }

    // }}}
    // {{{ _pushInfoReadTimedOut()

    /**
     * �ǂݍ��݂��^�C���A�E�g�����|�̃��b�Z�[�W���v�b�V������
     *
     * @param string $url
     * @return void
     */
    static protected function _pushInfoReadTimedOut($url) {
        $summary = 'HTTP�ڑ��^�C���A�E�g';
        $description = self::_urlToAnchor ($url) . ' ��ǂݍ��݊����ł��܂���ł����B';
        self::_pushInfoMessage ($summary, $description);
    }

    // }}}
    // {{{ _pushInfoHttpError()

    /**
     * HTTP�G���[�̃��b�Z�[�W���v�b�V������
     *
     * @param string $url
     * @param int $errno
     * @param string $errstr
     * @return void
     */
    static protected function _pushInfoHttpError($url, $errno, $errstr) {
        $summary = sprintf ('HTTP %d %s', $errno, $errstr);
        $description = self::_urlToAnchor ($url) . ' ��ǂݍ��߂܂���ł����B';
        self::_pushInfoMessage ($summary, $description);
    }

    // }}}
    // {{{ _urlToAnchor()

    /**
     * _pushInfo�n���\�b�h�p��URL���A���J�[�ɕϊ�����
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
     * 2ch��DAT��UA��Monazilla�ɂ��Ȃ��ŃA�N�Z�X���āAbody�𓾂ĕԂ�.
     *
     * @return �擾����body�i����Ɏ擾�ł��Ȃ������ꍇ��false)
     */
    private function _get2ch203Body() {
        // 2007/06/11 302�̎��ɁAUA��Monazilla�ɂ��Ȃ���DAT�A�N�Z�X�����݂��203���A���Ă��āA
        // body����'�ߋ����O ��'�Ƃ���΁A���������Ƃ݂Ȃ����Ƃɂ���B
        // �d�l�̊m�؂����Ă��Ȃ��̂ŁA���̂悤�Ȕ��f�ł悢�̂��͂����肵�Ȃ��B
        // 203 Non-Authoritative Information
        // �ߋ����O ��
        /*
         * ��������W���B�B�B<><>2007/06/10(��) 13:29:51.68 0<> http://mlb.yahoo.co.jp/headlines/?a=2279 <br> ����큄������������������������������������������� <>������탁�W���[���i���� ����c�_14001��
         * 1001, 131428 (�����X��, �T�C�Y)<><>1181480550000000 (�ŏI�X�V)<><div style="color:navy;font-size:smaller;">|<br />| ����<br />|</div><>
         * �P�O�O�P<><>Over 1000 Thread<> ���̃X���b�h�͂P�O�O�O�𒴂��܂����B <br> ���������Ȃ��̂ŁA�V�����X���b�h�𗧂ĂĂ��������ł��B�B�B <>
         * �ߋ����O ��<><>[�ߋ����O]<><div style="color:red;text-align:center;">�� ���̃X���b�h�͉ߋ����O�q�ɂɊi�[����Ă��܂�</div><hr /><br />IE�����ʂ̃u���E�U�Ō���ꍇ http://tubo.80.kg/tubo_and_maru.html<br />��p�̃u���E�U�Ō���ꍇ http://www.monazilla.org/<br /><br />�Q�����˂� Viewer ���g���ƁA�����ɓǂ߂܂��B http://2ch.tora3.net/<br /><div style="color:navy;">���� Viewer(�ʏ́�) �̔���ŁA�Q�����˂�͐ݔ��𑝋����Ă��܂��B<br />�������ꂽ��A�V�����T�[�o�𓊓��ł���Ƃ������ł��B</div><br />�悭�킩��Ȃ��ꍇ�̓\�t�g�E�F�A��Go http://pc11.2ch.net/software/<br /><br />�����^�| ( http://find.2ch.net/faq/faq2.php#c1 ) �������Ă���΁A50�����^�|�ŕ\���ł��܂��B<br />�@�@�@�@�����炩�� �� http://find.2ch.net/index.php?STR=dat:http://ex23.2ch.net/test/read.cgi/morningcoffee/1181449791/<br /><br /><hr /><>
         */
        try {
            $url = "http://{$this->host}/{$this->bbs}/dat/{$this->key}.dat";
            $req = P2Util::getHTTPRequest2 ($url,HTTP_Request2::METHOD_GET);

            $res = $req->send ();

            // ���X�|���X�R�[�h������
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
     * DAT�擾�G���[����>>1�ƍŏI���X��DAT�̌`����$this->datochi_residuums��
     * �ۑ�����i���X�� => datline �̔z��j
     * $this->getdat_error_body�̓��e����\�z.
     *
     * @return boolean ����ɏI�������ꍇ��true
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
     * +Wiki��DAT�擾�v���O�C����dat���擾���邽�߂̃����N�𐶐�����B
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
                        $code = '��' . $code;
                    } else {
                        $code = '�~' . $code;
                    }
                    $plugin_ht .= "    <option value=\"{$replace}\">{$code}:{$v['title']}</option>\n";
                }
            }
            if ($plugin_ht) {
                $plugin_ht = '<select size=1 name="kakolog">' . $plugin_ht . '</select>';
            } else {
                $plugin_ht = '<input type="text" name="kakolog" size="64">';
            }
            $plugin_ht .= '����<input type="submit" name="kakoget" value="�擾">';
        } else {
            $plugin_ht = '<input type="submit" name="plugin" value="DAT��T��">';
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
     * ����offlaw��dat���擾���邽�߂̃����N�𐶐�����B
     *
     * @param bool $retry
     * @return string HTML
     */
    protected function _generateMarutoriLink($retry = false) {
        global $_conf;

        if ($retry) {
            $retry_q = "&amp;relogin2ch=true";
            $atext = "��ID�ōĎ擾����";
        } else {
            $atext = "��ID��rep2�Ɏ�荞��";
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
