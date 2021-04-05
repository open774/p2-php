<?php
/**
 * rep2 - �g�їp�ŃX���b�h��\������ �N���X
 */

require_once P2EX_LIB_DIR . '/ExpackLoader.php';

ExpackLoader::loadAAS();
ExpackLoader::loadActiveMona();
ExpackLoader::loadImageCache();

// {{{ ShowThreadK

class ShowThreadK extends ShowThread
{
    // {{{ properties

    static private $_spm_objects = array();

    public $am_autong = false; // ����AA�������邩�ۂ�

    public $aas_rotate = '90����]'; // AAS ��]�����N������

    public $respopup_at = '';  // ���X�|�b�v�A�b�v�E�C�x���g�n���h��
    public $target_at = '';    // ���p�A�ȗ��AID�ANG���̃����N�^�[�Q�b�g
    public $check_st = '�m';   // �ȗ��ANG���̃����N������

    public $spmObjName; // �X�}�[�g�|�b�v�A�b�v���j���[�pJavaScript�I�u�W�F�N�g��

    private $_dateIdPattern;    // ���t���������̌����p�^�[��
    private $_dateIdReplace;    // ���t���������̒u��������

    //private $_lineBreaksReplace; // �A��������s�̒u��������

    private $_kushiYakiName = null; // BBQ�ɏĂ���Ă���Ƃ��̖��O�ړ���

    // }}}
    // {{{ constructor

    /**
     * �R���X�g���N�^
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
        if (isset($GLOBALS['replaceImageUrlCtl'])) {
            $this->_url_handlers[] = 'plugin_replaceImageUrl';
        } elseif ($_conf['mobile.use_picto']) {
            $this->_url_handlers[] = 'plugin_viewImage';
        }
        if ($_conf['mobile.link_youtube']) {
            $this->_url_handlers[] = 'plugin_linkYouTube';
        }
        $this->_url_handlers[] = 'plugin_linkURL';

        if (!$_conf['mobile.bbs_noname_name']) {
            $this->setBbsNonameName();
        }

        if (P2HostMgr::isHost2chs($aThread->host)) {
            $this->_kushiYakiName = ' </b>[�\{}@{}@{}-]<b> ';
        }

        if ($_conf['mobile.date_zerosuppress']) {
            $this->_dateIdPattern = '~^(?:' . date('Y|y') . ')/(?:0(\\d)|(\\d\\d))?(?:(/)0)?~';
            $this->_dateIdReplace = '$1$2$3';
        } else {
            $this->_dateIdPattern = '~^(?:' . date('Y|y') . ')/~';
            $this->_dateIdReplace = '';
        }

        // �A��������s�̒u���������ݒ�
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

        // �T���l�C���\����������ݒ�
        if (!isset($GLOBALS['pre_thumb_unlimited']) || !isset($GLOBALS['expack.ic2.pre_thumb_limit_k'])) {
            if (isset($_conf['expack.ic2.pre_thumb_limit_k']) && $_conf['expack.ic2.pre_thumb_limit_k'] > 0) {
                $GLOBALS['pre_thumb_limit_k'] = $_conf['expack.ic2.pre_thumb_limit_k'];
                $GLOBALS['pre_thumb_unlimited'] = false;
            } else {
                $GLOBALS['pre_thumb_limit_k'] = null;   // �k���l����isset()��false��Ԃ�
                $GLOBALS['pre_thumb_unlimited'] = true;
            }
        }
        $GLOBALS['pre_thumb_ignore_limit'] = false;

        // �A�N�e�B�u���i�[������
        if (P2_ACTIVEMONA_AVAILABLE) {
            ExpackLoader::initActiveMona($this);
        }

        // ImageCache2������
        if (P2_IMAGECACHE_AVAILABLE == 2) {
            ExpackLoader::initImageCache($this);
        }

        // AAS ������
        if (P2_AAS_AVAILABLE) {
            ExpackLoader::initAAS($this);
        }

        // SPM������
        //if ($this->_matome) {
        //    $this->spmObjName = sprintf('t%dspm%u', $this->_matome, crc32($this->thread->keydat));
        //} else {
            $this->spmObjName = sprintf('spm%u', crc32($this->thread->keydat));
        //}
    }

    // }}}
    // {{{ transRes()

    /**
     * Dat���X��HTML���X�ɕϊ�����
     *
     * @param   string  $ares       dat��1���C��
     * @param   int     $i          ���X�ԍ�
     * @param   string  $pattern    �n�C���C�g�p���K�\��
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

        // +Wiki:�u�����[�h
        if (isset($GLOBALS['replaceWordCtl'])) {
            $replaceWordCtl = $GLOBALS['replaceWordCtl'];
            $name    = $replaceWordCtl->replace('name', $this->thread, $ares, $i);
            $mail    = $replaceWordCtl->replace('mail', $this->thread, $ares, $i);
            $date_id = $replaceWordCtl->replace('date', $this->thread, $ares, $i);
            $msg     = $replaceWordCtl->replace('msg',  $this->thread, $ares, $i);
        }

        $tores = '';
        if ($this->_matome) {
            $res_id = "t{$this->_matome}r{$i}";
        } else {
            $res_id = "r{$i}";
        }

        // NG���ځ[��`�F�b�N
        $nong = !empty($_GET['nong']);
        $ng_type = $this->_ngAbornCheck($i, strip_tags($name), $mail, $date_id, $id, $msg, $nong, $ng_info);
        if ($ng_type == self::ABORN) {
            return $this->_abornedRes($res_id);
        }
        if (!$nong && $this->am_autong && $this->activeMona->detectAA($msg)) {
            $is_ng = array_key_exists($i, $this->_ng_nums);
            $ng_type |= $this->_markNgAborn($i, self::NG_AA, true);
            $ng_info[] = 'AA��';
            // AA��A��NG�Ώۂ���O���ꍇ
            if (!$is_ng && $_conf['expack.am.autong_k'] == 2) {
                unset($this->_ng_nums[$i]);
            }
        }
        if ($ng_type != self::NG_NONE) {
            $ngaborns_head_hits = self::$_ngaborns_head_hits;
            $ngaborns_body_hits = self::$_ngaborns_body_hits;
        }

        // {{{ ���O�Ɠ��t�EID�𒲐�

        // ���Ă��}�[�N��Z�k
        if ($this->_kushiYakiName !== null && strpos($name, $this->_kushiYakiName) === 0) {
            $name = substr($name, strlen($this->_kushiYakiName));
            // �f�t�H���g�̖��O�͏ȗ�
            if ($name === $this->_nanashiName) {
                $name = '[��]';
            } else {
                $name = '[��]' . $name;
            }
        // �f�t�H���g�̖��O�Ɠ����Ȃ�ȗ�
        } elseif ($name === $this->_nanashiName) {
            $name = '';
        }

        // ���݂̔N���͏ȗ��J�b�g����B�����̐擪0���J�b�g�B
        $date_id = preg_replace($this->_dateIdPattern, $this->_dateIdReplace, $date_id);

        // �j���Ǝ��Ԃ̊Ԃ��l�߂�
        $date_id = str_replace(') ', ')', $date_id);

        // �b���J�b�g
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
        // �܂Ƃ߂ďo��
        //=============================================================

        if ($name) {
            $name = $this->transName($name); // ���OHTML�ϊ�
        }
        $msg = $this->transMsg($msg, $i); // ���b�Z�[�WHTML�ϊ�

        // BE�v���t�@�C�������N�ϊ�
        $date_id = $this->replaceBeId($date_id, $i);

        // NG���b�Z�[�W�ϊ�
        if ($ng_type != self::NG_NONE && count($ng_info)) {
            $ng_info = implode(', ', $ng_info);

            $msg = <<<EOMSG
<s><font color="{$STYLE['mobile_read_ngword_color']}">{$ng_info}</font></s> <a class="button" href="{$_conf['read_php']}?host={$this->thread->host}&amp;bbs={$this->thread->bbs}&amp;key={$this->thread->key}&amp;ls={$i}&amp;k_continue=1&amp;nong=1{$_conf['k_at_a']}"{$this->respopup_at}{$this->target_at}>{$this->check_st}</a>
EOMSG;

            // AAS
            if (($ng_type & self::NG_AA) && P2_AAS_AVAILABLE) {
                $aas_url = "aas.php?host={$this->thread->host}&amp;bbs={$this->thread->bbs}&amp;key={$this->thread->key}&amp;resnum={$i}";
                if (P2_AAS_AVAILABLE == 2) {
                    $aas_txt = "<img src=\"{$aas_url}{$_conf['k_at_a']}&amp;inline=1\">";
                } else {
                    $aas_txt = "AAS";
                }

                $msg .= " <a class=\"aas\" href=\"{$aas_url}{$_conf['k_at_a']}\"{$this->target_at}>{$aas_txt}</a>";
                $msg .= " <a class=\"button\" href=\"{$aas_url}{$_conf['k_at_a']}&amp;rotate=1\"{$this->target_at}>{$this->aas_rotate}</a>";

            }
        }

        // NG�l�[���ϊ�
        if ($ng_type & self::NG_NAME) {
            $name = <<<EONAME
<s><font color="{$STYLE['mobile_read_ngword_color']}">{$name}</font></s>
EONAME;
            $msg = <<<EOMSG
<a class="button" href="{$_conf['read_php']}?host={$this->thread->host}&amp;bbs={$this->thread->bbs}&amp;key={$this->thread->key}&amp;ls={$i}&amp;k_continue=1&amp;nong=1{$_conf['k_at_a']}"{$this->respopup_at}{$this->target_at}>{$this->check_st}</a>
EOMSG;

        // NG���[���ϊ�
        } elseif ($ng_type & self::NG_MAIL) {
            $mail = <<<EOMAIL
<s class="ngword" onmouseover="document.getElementById('ngn{$ngaborns_head_hits}').style.display = 'block';">{$mail}</s>
EOMAIL;
            $msg = <<<EOMSG
<div id="ngn{$ngaborns_head_hits}" style="display:none;">{$msg}</div>
EOMSG;

        // NGID�ϊ�
        } elseif ($ng_type & self::NG_ID) {
            $date_id = <<<EOID
<s><font color="{$STYLE['mobile_read_ngword_color']}">{$date_id}</font></s>
EOID;
            $msg = <<<EOMSG
<a class="button" href="{$_conf['read_php']}?host={$this->thread->host}&amp;bbs={$this->thread->bbs}&amp;key={$this->thread->key}&amp;ls={$i}&amp;k_continue=1&amp;nong=1{$_conf['k_at_a']}"{$this->respopup_at}{$this->target_at}>{$this->check_st}</a>
EOMSG;
        }

        /*
        //�u��������V���v�摜��}��
        if ($i == $this->thread->readnum +1) {
            $tores .= <<<EOP
                <div><img src="img/image.png" alt="�V�����X" border="0" vspace="4"></div>
EOP;
        }
        */

        // �ԍ��i�I���U�t���C���j
        if ($this->thread->onthefly) {
            $GLOBALS['newres_to_show_flag'] = true;
            $tores .= "<div id=\"{$res_id}\" name=\"{$res_id}\">[<font color=\"{$STYLE['mobile_read_onthefly_color']}'\">{$i}</font>]";
            // �ԍ��i�V�����X���j
        } elseif ($i > $this->thread->readnum) {
            $GLOBALS['newres_to_show_flag'] = true;
            $tores .= "<div id=\"{$res_id}\" name=\"{$res_id}\">[<font color=\"{$STYLE['mobile_read_newres_color']}\">{$i}</font>]";
            // �ԍ�
        } else {
            $tores .= "<div id=\"{$res_id}\" name=\"{$res_id}\">[{$i}]";
        }

        // ���O
        if ($name) {
            $tores .= "{$name}: ";
         }

         // ���[��
         if ($mail) {
             $tores .= "{$mail}: ";
         }
         // ���t��ID
         $tores .= "{$date_id}<br>\n";
         // ���e
         $tores .= "{$msg}</div>\n";
         // �탌�X���X�g
         if ($_conf['mobile.backlink_list'] == 1) {
             $linkstr = $this->_quotebackListHtml($i, 2);
             if (strlen($linkstr)) {
                 $tores .= '<br>' . $linkstr;
             }
         }
         $tores .= "<hr>\n";

        // �܂Ƃ߂ăt�B���^�F����
        if ($pattern) {
            if (is_string($_conf['k_filter_marker'])) {
                $tores = StrCtl::filterMarking($pattern, $tores, $_conf['k_filter_marker']);
            } else {
                $tores = StrCtl::filterMarking($pattern, $tores);
            }
        }

        // �S�p�p���X�y�[�X�J�i�𔼊p��
        if (!empty($_conf['mobile.save_packet'])) {
            $tores = mb_convert_kana($tores, 'rnsk'); // CP932 ���� ask �� �� �� < �ɕϊ����Ă��܂��悤��
        }

        return array('body' => $tores, 'q' => '');
    }

    // }}}
    // {{{ transName()

    /**
     * ���O��HTML�p�ɕϊ�����
     *
     * @param   string  $name   ���O
     * @return  string
     */
    public function transName($name)
    {
        $name = strip_tags($name);

        // �g���b�v��z�X�g�t���Ȃ番������
        if (($pos = strpos($name, '��')) !== false) {
            $trip = substr($name, $pos);
            $name = substr($name, 0, $pos);
        } else {
            $trip = null;
        }

        // ���������p���X�|�b�v�A�b�v�����N��
        if (strlen($name) && $name != $this->_nanashiName) {
            $name = preg_replace_callback(
                self::getAnchorRegex('/(?:^|%prefix%)%nums%/'),
                array($this, '_quoteNameCallback'), $name
            );
        }

        if ($trip) {
            $name .= $trip;
        } elseif ($name) {
            // �����������
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
     * dat�̃��X���b�Z�[�W��HTML�\���p���b�Z�[�W�ɕϊ�����
     *
     * @param   string  $msg    ���b�Z�[�W
     * @param   int     $mynum  ���X�ԍ�
     * @return  string
     */
    public function transMsg($msg, $mynum)
    {
        global $_conf;
        global $pre_thumb_ignore_limit;

        $ryaku = false;

        // 2ch���`����dat
        if ($this->thread->dat_type == '2ch_old') {
            $msg = str_replace('���M', ',', $msg);
            $msg = preg_replace('/&amp(?=[^;])/', '&', $msg);
        }

		// �T���Q�[�g�y�A�̐��l�����Q�Ƃ�ϊ�
        $msg = P2Util::replaceNumericalSurrogatePair($msg);
		
        // &�␳
        $msg = preg_replace('/&(?!#?\\w+;)/', '&amp;', $msg);

        // >>1�̃����N����������O��
        // <a href="../test/read.cgi/accuse/1001506967/1" target="_blank">&gt;&gt;1</a>
        $msg = preg_replace('{<[Aa] .+?>(&gt;&gt;\\d[\\d\\-]*)</[Aa]>}', '$1', $msg);

        // �傫������
        if (empty($_GET['k_continue']) && strlen($msg) > $_conf['mobile.res_size']) {
            // <br>�ȊO�̃^�O���������A������؂�l�߂�
            $msg = strip_tags($msg, '<br>');
            $msg = mb_strcut($msg, 0, $_conf['mobile.ryaku_size']);
            $msg = preg_replace('/ *<[^>]*$/', '', $msg);

            // >>1, >1, ��1, ����1�����p���X�|�b�v�A�b�v�����N��
            $msg = preg_replace_callback(
                self::getAnchorRegex('/%full%/'),
                array($this, '_quoteResCallback'), $msg
            );

            $msg .= "<a href=\"{$_conf['read_php']}?host={$this->thread->host}&amp;bbs={$this->thread->bbs}&amp;key={$this->thread->key}&amp;ls={$mynum}&amp;k_continue=1&amp;offline=1{$_conf['k_at_a']}\"{$this->respopup_at}{$this->target_at}>��</a>";
            return $msg;
        }

        // �V�����X�̉摜�͕\�������𖳎�����ݒ�Ȃ�
        if ($mynum > $this->thread->readnum && $_conf['expack.ic2.newres_ignore_limit_k']) {
            $pre_thumb_ignore_limit = true;
        }

        // �����̉��s�ƘA��������s������
        if ($_conf['mobile.strip_linebreaks']) {
            $msg = $this->stripLineBreaks($msg /*, $this->_lineBreaksReplace*/);
        }

        // ���p��URL�Ȃǂ������N
        $msg = $this->transLink($msg);

        // Wikipedia�L�@�ւ̎��������N
        if ($_conf['mobile._linkToWikipeida']) {
            $msg = $this->_wikipediaFilter($msg);
        }

        return $msg;
    }

    // }}}
    // {{{ _abornedRes()

    /**
     * ���ځ[�񃌃X��HTML���擾����
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
    // {{{ idFilter()

    /**
     * ID�t�B���^�����O�����N�ϊ�
     *
     * @param   string  $idstr  ID:xxxxxxxxxx
     * @param   string  $id        xxxxxxxxxx
     * @return  string
     */
    public function idFilter($idstr, $id)
    {
        global $_conf;

        //$idflag = '';   // �g��/PC���ʎq
        // ID��8���܂���10��(+�g��/PC���ʎq)�Ɖ��肵��
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

        return  "<a href=\"{$link}\">{$word}</a>";
    }

    // }}}
    // {{{ quoteRes()

    /**
     * ���p�ϊ��i�P�Ɓj
     *
     * @param   string  $full           >>1-100
     * @param   string  $qsign          >>
     * @param   string  $appointed_num    1-100
     * @return string
     */
    public function quoteRes($full, $qsign, $appointed_num)
    {
        global $_conf, $STYLE;

        if ($appointed_num == '-') {
            return $full;
        }

        $appointed_num = mb_convert_kana($appointed_num, 'n');   // �S�p�����𔼊p�����ɕϊ�
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
     * ���p�ϊ��i�͈́j
     *
     * @param   string  $full           >>1-100
     * @param   string  $qsign          >>
     * @param   string  $appointed_num    1-100
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
        // read.php�ŕ\���͈͂𔻒肷��̂ŏ璷�ł͂���
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
     * �g�їp�O��URL�ϊ�
     *
     * @param   string  $full
     * @param   string  $url
     * @param   string  $str
     * @return  string
     */
    public function ktaiExtUrl($full, $url, $str)
    {
        global $_conf;

        // �ʋ΃u���E�U
        $tsukin_link = '';
        if ($_conf['mobile.use_tsukin']) {
            $tsukin_url = 'http://www.sjk.co.jp/c/w.exe?y=' . rawurlencode($url);
            if ($_conf['through_ime']) {
                $tsukin_url = P2Util::throughIme($tsukin_url);
            }
            $tsukin_link = '<a href="' . $tsukin_url . '">��</a>';
        }

        // jig�u���E�UWEB http://bwXXXX.jig.jp/fweb/?_jig_=
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
     * �g�їp�O��URL�ϊ�
     *
     * @param   array   $s  ���K�\���Ƀ}�b�`�����v�f�̔z��
     * @return  string
     */
    public function ktaiExtUrlCallback(array $s)
    {
        return $this->ktaiExtUrl($s[0], $s[1], $s[2]);
    }

    // }}}
    // {{{ transLinkDo()����Ăяo�����URL�����������\�b�h
    /**
     * �����̃��\�b�h�͈����������Ώۃp�^�[���ɍ��v���Ȃ���false��Ԃ��A
     * transLinkDo()��false���Ԃ��Ă����$_url_handlers�ɓo�^����Ă��鎟�̊֐�/���\�b�h�ɏ��������悤�Ƃ���B
     */
    // {{{ plugin_linkURL()

    /**
     * URL�����N
     */
    public function plugin_linkURL($url, $purl, $str)
    {
        global $_conf;

        if (isset($purl['scheme'])) {
            // �g�їp�O��URL�ϊ�
            if ($_conf['mobile.use_tsukin']) {
                return $this->ktaiExtUrl('', $purl[0], $str);
            }
            // ime
            if ($_conf['through_ime']) {
                $link_url = P2Util::throughIme($purl[0]);
            } else {
                $link_url = $url;
            }
            return "<a href=\"{$link_url}\">{$str}</a>";
        }
        return false;
    }

    // }}}
    // {{{ plugin_link2chSubject()

    /**
     * 2ch bbspink �����N
     */
    public function plugin_link2chSubject($url, $purl, $str)
    {
        global $_conf;

        if (preg_match('{^https?://(.+)/(.+)/$}', $purl[0], $m)) {
            //rep2�ɓo�^����Ă���Ȃ�΃����N����
            if (P2HostMgr::isRegisteredBbs($m[1],$m[2])) {
                $subject_url = "{$_conf['subject_php']}?host={$m[1]}&amp;bbs={$m[2]}";
                return "<a href=\"{$url}\">{$str}</a> [<a href=\"{$subject_url}{$_conf['k_at_a']}\">��p2�ŊJ��</a>]";
            }
        }
        return false;
    }

    // }}}
    // {{{ plugin_linkThread()

    /**
     * �X���b�h�����N
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
    // {{{ plugin_linkYouTube()

    /**
     * YouTube�����N�ϊ��v���O�C��
     *
     * Zend_Gdata_Youtube���g���΃T���l�C�����̑��̏����ȒP�Ɏ擾�ł��邪...
     *
     * @param   string $url
     * @param   array $purl
     * @param   string $str
     * @return  string|false
     */
    public function plugin_linkYouTube($url, $purl, $str)
    {
        global $_conf;

        // http://www.youtube.com/watch?v=Mn8tiFnAUAI
        if (preg_match('{^http://(www|jp)\\.youtube\\.com/watch\\?v=([0-9A-Za-z_\\-]+)}', $purl[0], $m)) {
            $subd = $m[1];
            $id = $m[2];

            if ($_conf['mobile.link_youtube'] == 2) {
                $link = $str;
            } else {
                $link = $this->plugin_linkURL($url, $purl, $str);
                if ($link === false) {
                    // plugin_linkURL()�������Ƌ@�\���Ă�����肱���ɂ͗��Ȃ�
                    if ($_conf['through_ime']) {
                        $link_url = P2Util::throughIme($purl[0]);
                    } else {
                        $link_url = $url;
                    }
                    $link = "<a href=\"{$link_url}\">{$str}</a>";
                }
            }

            return <<<EOP
{$link}<br><img src="http://img.youtube.com/vi/{$id}/default.jpg" alt="YouTube {$id}">
EOP;
        }
        return false;
    }

    // }}}
    // {{{ plugin_viewImage()

    /**
     * �摜�����N�ϊ�
     */
    public function plugin_viewImage($url, $purl, $str)
    {
        global $_conf;

        if (P2HostMgr::isUrlWikipediaJa($url)) {
            return false;
        }

        if (preg_match('{^https?://.+?\\.(jpe?g|gif|png)$}i', $url) && empty($purl['query'])) {
            $picto_url = 'http://pic.to/'.$purl['host'].$purl['path'];
            $picto_tag = '<a href="'.$picto_url.'">(��)</a> ';
            if ($_conf['through_ime']) {
                $link_url  = P2Util::throughIme($purl[0]);
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
            // �C�����C���v���r���[�̗L������
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

            // r=0:�����N;r=1:���_�C���N�g;r=2:PHP�ŕ\��
            // t=0:�I���W�i��;t=1:PC�p�T���l�C��;t=2:�g�їp�T���l�C��;t=3:���ԃC���[�W
            $img_url = 'ic2.php?r=0&amp;t=2&amp;uri=' . $url_en . $ref_en;
            $img_url2 = 'ic2.php?r=0&amp;t=2&amp;id=';
            $src_url = 'ic2.php?r=1&amp;t=0&amp;uri=' . $url_en . $ref_en;
            $src_url2 = 'ic2.php?r=1&amp;t=0&amp;id=';
            $src_exists = false;

            // ���C�ɃX�������摜�����N
            $rank = null;
            if ($_conf['expack.ic2.fav_auto_rank']) {
                $rank = $this->getAutoFavRank();
            }

            // DB�ɉ摜��񂪓o�^����Ă����Ƃ�
            if ($icdb->get($v['url'])) {
                $img_id = $icdb->id;

                // �E�B���X�Ɋ������Ă����t�@�C���̂Ƃ�
                if ($icdb->mime == 'clamscan/infected') {
                    return '[IC2:�E�B���X�x��]';
                }
                // ���ځ[��摜�̂Ƃ�
                if ($icdb->rank < 0) {
                    return '[IC2:���ځ[��摜]';
                }

                // �I���W�i���̗L�����m�F
                if (file_exists($this->thumbnailer->srcPath($icdb->size, $icdb->md5, $icdb->mime))) {
                    $src_exists = true;
                    $img_url = $img_url2 . $icdb->id;
                    $src_url = $this->thumbnailer->srcUrl($icdb->size, $icdb->md5, $icdb->mime);
                } else {
                    $img_url = $this->thumbnailer->thumbUrl($icdb->size, $icdb->md5, $icdb->mime);
                    $src_url = $src_url2 . $icdb->id;
                }

                // �C�����C���v���r���[���L���̂Ƃ�
                $prv_url = null;
                if ($this->thumbnailer->ini['General']['inline'] == 1) {
                    // PC��read_new_k.php�ɃA�N�Z�X�����Ƃ���
                    if (!isset($this->inline_prvw) || !is_object($this->inline_prvw)) {
                        $this->inline_prvw = $this->thumbnailer;
                    }
                    $prv_url = $this->inline_prvw->thumbUrl($icdb->size, $icdb->md5, $icdb->mime);

                    // �T���l�C���\���������ȓ��̂Ƃ�
                    if ($inline_preview_flag) {
                        // �v���r���[�摜������Ă��邩�ǂ�����img�v�f�̑���������
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
                        $img_str = '[p2:�����摜(�ݸ:' . $icdb->rank . ')]';
                    }
                }

                // �����X���^�C�����@�\��ON�ŃX���^�C���L�^����Ă��Ȃ��Ƃ���DB���X�V
                if (!is_null($this->img_memo) && strpos($icdb->memo, $this->img_memo) === false){
                    $update = new ImageCache2_DataObject_Images();
                    if (!is_null($icdb->memo) && strlen($icdb->memo) > 0) {
                        $update->memo = $this->img_memo . ' ' . $icdb->memo;
                    } else {
                        $update->memo = $this->img_memo;
                    }
                    $update->whereAddQuoted('uri', '=', $v['url']);
                }

                // expack.ic2.fav_auto_rank_override �̐ݒ�ƃ����N������OK�Ȃ�
                // ���C�ɃX�������摜�����N���㏑���X�V
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

            // �摜���L���b�V������Ă��Ȃ��Ƃ�
            // �����X���^�C�����@�\��ON�Ȃ�N�G����UTF-8�G���R�[�h�����X���^�C���܂߂�
            } else {
                // �摜���u���b�N���X�gor�G���[���O�ɂ��邩�m�F
                if (false !== ($errcode = $icdb->ic2_isError($v['url']))) {
                    return "<s>[IC2:�װ({$errcode})]</s>";
                }

                // �C�����C���v���r���[���L���ŁA�T���l�C���\���������ȓ��Ȃ�
                if ($this->thumbnailer->ini['General']['inline'] == 1 && $inline_preview_flag) {
                    $rank_str = ($rank !== null) ? '&rank=' . $rank : '';
                    $img_str = "<img src=\"ic2.php?r=2&amp;t=1&amp;uri={$url_en}{$this->img_memo_query}{$rank_str}{$ref_en}\" width=\"{$prvw_size[0]}\" height=\"{$prvw_size[1]}\">";
                    $inline_preview_done = true;
                } else {
                    $img_url .= $this->img_memo_query;
                }
            }

            // �\�����������f�N�������g
            if ($inline_preview_flag && $inline_preview_done) {
                $pre_thumb_limit_k--;
            }

            if (!empty($_SERVER['REQUEST_URI'])) {
                $backto = '&amp;from=' . rawurlencode($_SERVER['REQUEST_URI']);
            } else {
                $backto = '';
            }

            if (is_null($img_str)) {
                $result .= sprintf('<a href="%s%s">[IC2:%s:%s]</a>',
                                   $img_url,
                                   $backto,
                                   p2h($purl['host']),
                                   p2h(basename($purl['path']))
                                   );
            }

            $result .= "<a href=\"{$img_url}{$backto}\">{$img_str}</a>";
        }

        $linkUrlResult = $this->plugin_linkURL($url, $purl, $str);
        if ($linkUrlResult !== false) {
            $result .= $linkUrlResult;
        }

        return $result;
    }

    // }}}
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
        $plus_str = count($plus) > 0 ? '+' .  ($plus_cnt > 0 ? $plus_cnt : '') : '';

        $url = $_conf['read_php'] . '?' . http_build_query(array(
            'host' => $this->thread->host,
            'bbs'  => $this->thread->bbs,
            'key'  => $this->thread->key,
            'ls'   => $resnum,
            'offline' => '1',
            'showbl' => '1',
        ), '', '&amp;') . $_conf['k_at_a'];

        $suppress = false;
        $n = 0;
        $reslist = array();
        foreach($anchors as $anchor) {
            if ($anchor == $resnum) continue;
            $n++;
            if ($_conf['mobile.backlink_list.suppress'] > 0
                && $n > $_conf['mobile.backlink_list.suppress']) {
                $suppress = true;
                break;
            }
            $reslist[] = $this->quoteRes('>>'.$anchor, '>>', $anchor);
        }

        $res_navi = '';
        if ($_conf['mobile.backlink_list.openres_navi'] == 1
            || ($_conf['mobile.backlink_list.openres_navi'] == 2
                && $suppress === true)) {
            if (count($anchors) > 1 || $plus_str) {
                $res_navi = "(<a href=\"{$url}\"{$this->target_at}>"
                    . (count($anchors) > 1 ? count($anchors) : '')
                    . $plus_str . '</a>)';
            }
        }

        $res_count = count($reslist);
        if ($res_count === 1 && $suppress === true && $_conf['mobile.backlink_list.suppress'] == 1) {
            $ret .= sprintf('<div>�y�Q��ڽ %s�z</div>', $res_navi);
        } elseif ($res_count === 1 && $suppress === false) {
            $ret .= sprintf('<div>�y�Q��ڽ %s%s�z</div>', $reslist[0], $res_navi);
        } else {
            for ($n = 0; $n < $res_count; $n++) {
                $ret .= '<div>�y�Q��ڽ ' . $reslist[$n] . '�z</div>';
            }
            $ret .= '<div>' . ($suppress ? '��' : '') . $res_navi . '</div>';
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
