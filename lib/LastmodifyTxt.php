<?php

// {{{ LastmodifyTxt

/**
 * LastmodifyTxt�N���X
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
     * �R���X�g���N�^
     */
    public function __construct($host, $bbs)
    {
        global $_conf;
        $this->host = $host;
        $this->bbs =  $bbs;
        $this->storage = 'file';

        $this->lastmodify_file = P2Util::datDirOfHostBbs($host, $bbs) . 'lastmodify.txt';
        // �ڑ��悪 2ch.net / 5ch / pink �ȊO�̏ꍇ�_�E�����[�h���Ȃ�
        if (!P2HostMgr::isHost2chs($this->host)) {
        	return ;
        }
        $this->lastmodify_url = 'https://' . $host . '/' . $bbs . '/lastmodify.txt';

        // lastmodify.txt�� �_�E�����[�h���Z�b�g����
        $this->dlAndSetLastmodify();
    }

    // }}}
    // {{{ dlAndSetLastmodify()

    /**
     * lastmodify.txt���_�E�����[�h���Z�b�g����
     *
     * @return boolean �Z�b�g�ł���� true�A�ł��Ȃ���� false
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
     * lastmodify.txt���_�E�����[�h����
     *
     * @return string lastmodify.txt �̒��g
     */
    public function downloadLastmodify()
    {
        global $_conf;

        if ($this->storage === 'file') {
            FileCtl::mkdirFor($this->lastmodify_file); // �f�B���N�g����������΍��

            if (file_exists($this->lastmodify_file)) {
                if (!empty($_REQUEST['norefresh']) || (empty($_REQUEST['refresh']) && isset($_REQUEST['word']))) {
                    return;    // �X�V���Ȃ��ꍇ�́A���̏�Ŕ����Ă��܂�
                } elseif (!empty($GLOBALS['expack.subject.multi-threaded-download.done'])) {
                    return;    // ����_�E�����[�h�ς̏ꍇ��������
                } elseif (empty($_POST['newthread']) and $this->isLastmodifyTxtFresh()) {
                    return;    // �V�K�X�����Ď��łȂ��A�X�V���V�����ꍇ��������
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
                // �z�X�g�̈ړ]��ǐ�
                $new_host = P2HostMgr::getCurrentHost($this->host, $this->bbs);
                if ($new_host != $this->host) {
                    $aNewLastmodifyTxt = new LastmodifyTxt($new_host, $this->bbs);
                    $body = $aNewLastmodifyTxt->downloadLastmodify();
                    return $body;
                }
            } elseif ($code == 200 || $code == 206) {
                //var_dump($response->getHeader());
                $body = $response->getBody();
                // ������� or be.2ch.net �Ȃ�EUC��SJIS�ɕϊ�
                if (P2HostMgr::isHostJbbsShitaraba($this->host) || P2HostMgr::isHostBe2chs($this->host)) {
                    $body = mb_convert_encoding($body, 'CP932', 'CP51932');
                }
                if (FileCtl::file_write_contents($this->lastmodify_file, $body) === false) {
                    p2die('cannot write file');
                }
            } elseif ($code == 304) {
                // touch���邱�ƂōX�V�C���^�[�o���������̂ŁA���΂炭�ă`�F�b�N����Ȃ��Ȃ�
                // �i�ύX���Ȃ��̂ɏC�����Ԃ��X�V����̂́A�����C���i�܂Ȃ����A�����ł͓��ɖ��Ȃ����낤�j
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
            $info_msg_ht .= "rep2 info: <a href=\"{$url_t}\"{$_conf['ext_win_target_at']}>{$this->lastmodify_url}</a> �ɐڑ��ł��܂���ł����B</p>";
            P2Util::pushInfoHtml($info_msg_ht);
            $body = '';
        }

        return $body;
    }

    // }}}
    // {{{ isLastmodifyTxtFresh()

    /**
     * lastmodify.txt ���V�N�Ȃ� true ��Ԃ�
     *
     * @return boolean �V�N�Ȃ� true�B�����łȂ���� false�B
     */
    public function isLastmodifyTxtFresh()
    {
        global $_conf;

        // �L���b�V��������ꍇ
        if (file_exists($this->lastmodify_file)) {
            // �L���b�V���̍X�V���w�莞�Ԉȓ��Ȃ�
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
     * lastmodify.txt ��ǂݍ���
     *
     * ��������΁A$this->lastmodify_lines ���Z�b�g�����
     *
     * @param string $cont ����� eashm �p�ɓn���Ă���B
     * @return boolean ���s����
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
     * extdat ��ǂݍ���
     *
     * ��������΁A$this->lastmodify_lines ���Z�b�g�����
     *
     * @param string $cont ����� eashm �p�ɓn���Ă���B
     * @return boolean ���s����
     */
    public function getThreadExtend($key)
    {
        // �ڑ��悪 2ch / 5ch / pink �ȊO�̏ꍇ�� '' ��Ԃ�
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
