<?php
/**
 * rep2 - ���[�U�ݒ� �f�t�H���g
 *
 * ���̃t�@�C���̓f�t�H���g�l�̐ݒ�Ȃ̂ŁA���ɕύX����K�v�͂���܂���
 */

// {{{ be.2ch.net�A�J�E���g

// be.2ch.net�̔F�؃p�X���[�h(�F�؃R�[�h�͎g���Ȃ��Ȃ�܂���)
$conf_user_def['be_2ch_password'] = ""; // ("")

// be.2ch.net�̓o�^���[���A�h���X
$conf_user_def['be_2ch_mail'] = ""; // ("")

// be.2ch.net��DMDM(�蓮�ݒ肷��ꍇ�̂ݓ���)
$conf_user_def['be_2ch_DMDM'] = ""; // ("")

// be.2ch.net��MDMD(�蓮�ݒ肷��ꍇ�̂ݓ���)
$conf_user_def['be_2ch_MDMD'] = ""; // ("")

// }}}
// {{{ PATH

// �E�������ɍŏ��ɕ\�������y�[�W�B�I�����C��URL���B
$conf_user_def['first_page'] = "first_cont.php"; // ("first_cont.php")

/*
    ���X�g�̓I�����C���ƃ��[�J���̗�������ǂݍ��߂�
    �I�����C���� $conf_user_def['brdfile_online'] �Őݒ�
    ���[�J���� ./board �f�B���N�g�����쐬���A���̒���brd�t�@�C����u���i�����j
*/

/*
    ���X�g���I�����C��URL($conf_user_def['brdfile_online'])���玩���œǂݍ��ށB
    �w���� menu.html �`���A2channel.brd �`���̂ǂ���ł��悢�B
    �K�v�Ȃ���΁A���w��("")�ɂ���B
*/
// ("http://azlucky.s25.xrea.com/2chboard/bbsmenu.html")    // 2ch + �O��BBS
// ("http://menu.2ch.net/bbsmenu.html")                     // 2ch��{

$conf_user_def['brdfile_online'] = "http://azlucky.s25.xrea.com/2chboard/bbsmenu.html";
$conf_user_rules['brdfile_online'] = array('emptyToDef', 'invalidUrlToDef');

// }}}
// {{{ subject

// �X���b�h�ꗗ�̎����X�V�Ԋu�B�i���w��B0�Ȃ玩���X�V���Ȃ��B�j
$conf_user_def['refresh_time'] = 0; // (0)

// �X���b�h�ꗗ�Ŗ��擾�X���ɑ΂��Č��X���ւ̃����N�i�E�j��\�� (����:1, ���Ȃ�:0, ���擾�X���ł�����:2)
$conf_user_def['sb_show_motothre'] = 1; // (1)
$conf_user_rad['sb_show_motothre'] = array('1' => '����', '0' => '���Ȃ�', '2' => '���擾�X���ł�');

// PC�{�����A�X���b�h�ꗗ�i�\���j�� ����ޭ�>>1 ��\�� (����:1, ���Ȃ�:0, �j���[�X�n�̂�:2)
$conf_user_def['sb_show_one'] = 0; // (0)
$conf_user_sel['sb_show_one'] = array('1' => '����', '0' => '���Ȃ�', '2' => '�j���[�X�n�̂�');

// �g�т̃X���b�h�ꗗ�i�\���j���珉�߂ẴX�����J�����̕\�����@ (����ޭ�>>1:1, 1����N���\��:2, �ŐVN���\��:3)
$conf_user_def['mobile.sb_show_first'] = 2; // (2)
$conf_user_sel['mobile.sb_show_first'] = array('1' => '����ޭ�>>1', '2' => '1����N���\��', '3' => '�ŐVN���\��');

// �X���b�h�ꗗ�ł��΂₳�i���X�Ԋu�j��\�� (����:1, ���Ȃ�:0)
$conf_user_def['sb_show_spd'] = 0; // (0)
$conf_user_rad['sb_show_spd'] = array('1' => '����', '0' => '���Ȃ�');

// �X���b�h�ꗗ�Ő����i1��������̃��X���j��\�� (����:1, ���Ȃ�:0)
$conf_user_def['sb_show_ikioi'] = 1; // (1)
$conf_user_rad['sb_show_ikioi'] = array('1' => '����', '0' => '���Ȃ�');

// �X���b�h�ꗗ�ł��C�ɃX���}�[�N����\�� (����:1, ���Ȃ�:0)
$conf_user_def['sb_show_fav'] = 0; // (0)
$conf_user_rad['sb_show_fav'] = array('1' => '����', '0' => '���Ȃ�');

// �\���̃X���b�h�ꗗ�ł̃f�t�H���g�̃\�[�g�w��
$conf_user_def['sb_sort_ita'] = 'ikioi'; // ('ikioi')
$conf_user_sel['sb_sort_ita'] = array(
    'midoku' => '�V��', 'res' => '���X', 'no' => 'No.', 'title' => '�^�C�g��', // 'spd' => '���΂₳',
    'ikioi' => '����', 'bd' => 'Birthday'); // , 'fav' => '���C�ɃX��'

// �V���\�[�g�ł́u�����Ȃ��v�́u�V�����[���v�ɑ΂���\�[�g�D�揇�� (���:0.1, ����:0, ����:-0.1)
$conf_user_def['sort_zero_adjust'] = '0.1'; // (0.1)
$conf_user_sel['sort_zero_adjust'] = array('0.1' => '���', '0' => '����', '-0.1' => '����');

// �����\�[�g���ɐV�����X�̂���X����D�� (����:1, ���Ȃ�:0)
$conf_user_def['cmp_dayres_midoku'] = 1; // (1)
$conf_user_rad['cmp_dayres_midoku'] = array('1' => '����', '0' => '���Ȃ�');

// �^�C�g���\�[�g���ɑS�p���p�E�啶���������𖳎� (����:1, ���Ȃ�:0)
$conf_user_def['cmp_title_norm'] = 0; // (0)
$conf_user_rad['cmp_title_norm'] = array('1' => '����', '0' => '���Ȃ�');

// �X���b�h�̃^�C�g�����璘�쌠�\�L���폜����
$conf_user_def['delete_copyright'] = 0; // (0)
$conf_user_rad['delete_copyright'] = array('1' => '����', '0' => '���Ȃ�');

//�폜���钘�쌠�\�L�̕�����(�J���}��؂�)
$conf_user_def['delete_copyright.list'] = "[�]�ڋ֎~],&copy;2ch.net";
$conf_user_rules['delete_copyright.list'] = array('emptyToDef');

// �g�щ{�����A��x�ɕ\������X���̐�
$conf_user_def['mobile.sb_disp_range'] = 30; // (30)
$conf_user_rules['mobile.sb_disp_range'] = array('emptyToDef', 'notIntExceptMinusToDef');

// �����X���͕\�������Ɋւ�炸�\�� (����:1, ���Ȃ�:0)
$conf_user_def['viewall_kitoku'] = 1; // (1)
$conf_user_rad['viewall_kitoku'] = array('1' => '����', '0' => '���Ȃ�');

// �g�щ{�����A�X���b�h�ꗗ�ŕ\������^�C�g���̒����̏�� (0�Ŗ�����)
$conf_user_def['mobile.sb_ttitle_max_len'] = 0; // (0)
$conf_user_rules['mobile.sb_ttitle_max_len'] = array('notIntExceptMinusToDef');

// �g�щ{�����A�X���b�h�^�C�g���������̏�����z�����Ƃ��A���̒����܂Ő؂�l�߂�
$conf_user_def['mobile.sb_ttitle_trim_len'] = 45; // (45)
$conf_user_rules['mobile.sb_ttitle_trim_len'] = array('emptyToDef', 'notIntExceptMinusToDef');

// �g�щ{�����A�X���b�h�^�C�g����؂�l�߂�ʒu (�擪, ����, ����)
$conf_user_def['mobile.sb_ttitle_trim_pos'] = 1; // (1)
$conf_user_rad['mobile.sb_ttitle_trim_pos'] = array('-1' => '�擪', '0' => '����', '1' => '����');

// }}}
// {{{ read

// �X�����e�\�����A���ǂ̉��R�O�̃��X�Ƀ|�C���^�����킹�邩
$conf_user_def['respointer'] = 1; // (1)
$conf_user_rules['respointer'] = array('notIntExceptMinusToDef');

// PC�{�����A�|�C���^�̉��R�O�̃��X����\�����邩
$conf_user_def['before_respointer'] = 25; // (25)
$conf_user_rules['before_respointer'] = array('notIntExceptMinusToDef');

// �V���܂Ƃߓǂ݂̎��A�|�C���^�̉��R�O�̃��X����\�����邩
$conf_user_def['before_respointer_new'] = 0; // (0)
$conf_user_rules['before_respointer_new'] = array('notIntExceptMinusToDef');

// �V���܂Ƃߓǂ݂ň�x�ɕ\�����郌�X��
$conf_user_def['rnum_all_range'] = 200; // (200)
$conf_user_rules['rnum_all_range'] = array('emptyToDef', 'notIntExceptMinusToDef');

// �摜URL�̐�ǂ݃T���l�C����\��(����:1, ���Ȃ�:0)
$conf_user_def['preview_thumbnail'] = 0; // (0)
$conf_user_rad['preview_thumbnail'] = array('1' => '����', '0' => '���Ȃ�');

// �摜URL�̐�ǂ݃T���l�C������x�ɕ\�����鐧�����i0�Ŗ������j
$conf_user_def['pre_thumb_limit'] = 7; // (7)
$conf_user_rules['pre_thumb_limit'] = array('notIntExceptMinusToDef');

// �摜�T���l�C���̏c�̑傫�����w��i�s�N�Z���j
$conf_user_def['pre_thumb_height'] = "32"; // ("32")
//$conf_user_rules['pre_thumb_height'] = array('emptyToDef', 'notIntExceptMinusToDef');

// �摜�T���l�C���̉��̑傫�����w��i�s�N�Z���j
$conf_user_def['pre_thumb_width'] = "32"; // ("32")
//$conf_user_rules['pre_thumb_width'] = array('emptyToDef', 'notIntExceptMinusToDef');

// HTML�|�b�v�A�b�v�i����:1, ���Ȃ�:0, p�ł���:2, �摜�ł���:3�j
$conf_user_def['iframe_popup'] = 2; // (2)
$conf_user_sel['iframe_popup'] = array('1' => '����', '0' => '���Ȃ�', '2' => 'p�ł���', '3' => '�摜�ł���');

// HTML�|�b�v�A�b�v������ꍇ�̃C�x���g�i�N���b�N:1, �}�E�X�I�[�o�[:0�j
$conf_user_def['iframe_popup_event'] = 1; // (1)
$conf_user_rad['iframe_popup_event'] = array('1' => '�N���b�N', '0' => '�}�E�X�I�[�o�[');

// HTML�|�b�v�A�b�v�̕\���x�����ԁi�b�j
$conf_user_def['iframe_popup_delay'] = 0.2; // (0.2)
//$conf_user_rules['iframe_popup_delay'] = array('FloatExceptMinus');

// HTML�|�b�v�A�b�v�̎��
$conf_user_def['iframe_popup_type'] = 1;
$conf_user_rad['iframe_popup_type'] = array('0' => '�ʏ�', '1' => '��');

// ID:xxxxxxxx��ID�t�B���^�����O�̃����N�ɕϊ��i����:1, ���Ȃ�:0�j
$conf_user_def['flex_idpopup'] = 1; // (1)
$conf_user_rad['flex_idpopup'] = array('1' => '����', '0' => '���Ȃ�');

// �O���T�C�g���փW�����v���鎞�ɊJ���E�B���h�E�̃^�[�Q�b�g���i����:"", �V��:"_blank"�j
$conf_user_def['ext_win_target'] = "_blank"; // ("_blank")

// p2�Ή�BBS�T�C�g���ŃW�����v���鎞�ɊJ���E�B���h�E�̃^�[�Q�b�g���i����:"", �V��:"_blank"�j
$conf_user_def['bbs_win_target'] = ""; // ("")

// �X���b�h�����ɏ������݃t�H�[����\���i����:1, ���Ȃ�:0�j
$conf_user_def['bottom_res_form'] = 1; // (1)
$conf_user_rad['bottom_res_form'] = array('1' => '����', '0' => '���Ȃ�');

// ���p���X��\���i����:1, ���Ȃ�:0�j
$conf_user_def['quote_res_view'] = 1; // (1)
$conf_user_rad['quote_res_view'] = array('1' => '����', '0' => '���Ȃ�');

// NG���X�����p���X�\�����邩�i����:1, ���Ȃ�:0�j
$conf_user_def['quote_res_view_ng'] = 0; // (0)
$conf_user_rad['quote_res_view_ng'] = array('1' => '����', '0' => '���Ȃ�');

// ���ځ[�񃌃X�����p���X�\�����邩�i����:1, ���Ȃ�:0�j
$conf_user_def['quote_res_view_aborn'] = 0; // (0)
$conf_user_rad['quote_res_view_aborn'] = array('1' => '����', '0' => '���Ȃ�');

// PC�{�����A�����̉��s�ƘA��������s�������i����:1, ���Ȃ�:0�j
$conf_user_def['strip_linebreaks'] = 0; // (0)
$conf_user_rad['strip_linebreaks'] = array('1' => '����', '0' => '���Ȃ�');

// [[�P��]]��Wikipedia�ւ̃����N�ɂ���i����:1, ���Ȃ�:0�j
$conf_user_def['link_wikipedia'] = 0; // (0)
$conf_user_rad['link_wikipedia'] = array('1' => '����', '0' => '���Ȃ�');

// �t�Q�ƃ��X�g�̕\��
$conf_user_def['backlink_list'] = 1;
$conf_user_rad['backlink_list'] = array('1' => '�c���[�ۂ��\��', '2' => '���\��', '3' => '����', '0' => '���Ȃ�');

// �t�Q�ƃ��X�g�Ŗ����A���J�[��L���ɂ��邩
$conf_user_def['backlink_list_future_anchor'] = 1;
$conf_user_rad['backlink_list_future_anchor'] = array('1' => '�L��', '0' => '����');

// �t�Q�ƃ��X�g�ł��̒l���L���͈̓��X��ΏۊO�ɂ���(0�Ő����Ȃ�)
$conf_user_def['backlink_list_range_anchor_limit'] = 0;
$conf_user_rules['backlink_list_range_anchor_limit'] = array('notIntExceptMinusToDef');

// �t�Q�ƃu���b�N��W�J�ł���悤�ɂ��邩
$conf_user_def['backlink_block'] = 1;
$conf_user_rad['backlink_block'] = array('1' => '����', '0' => '���Ȃ�');

// �t�Q�ƃu���b�N�œW�J����Ă��郌�X�̖{�̂ɑ������邩
$conf_user_def['backlink_block_readmark'] = 1;
$conf_user_rad['backlink_block_readmark'] = array('1' => '����', '0' => '���Ȃ�');

// �g�щ{�����A�����̉��s�ƘA��������s�������i����:1, ���Ȃ�:0�j
$conf_user_def['mobile.strip_linebreaks'] = 0; // (0)
$conf_user_rad['mobile.strip_linebreaks'] = array('1' => '����', '0' => '���Ȃ�');

// �g�щ{�����A��x�ɕ\�����郌�X�̐�
$conf_user_def['mobile.rnum_range'] = 15; // (15)
$conf_user_rules['mobile.rnum_range'] = array('emptyToDef', 'notIntExceptMinusToDef');

// �g�щ{�����A��̃��X�̍ő�\���T�C�Y
$conf_user_def['mobile.res_size'] = 600; // (600)
$conf_user_rules['mobile.res_size'] = array('emptyToDef', 'notIntExceptMinusToDef');

// �g�щ{�����A���X���ȗ������Ƃ��̕\���T�C�Y
$conf_user_def['mobile.ryaku_size'] = 120; // (120)
$conf_user_rules['mobile.ryaku_size'] = array('notIntExceptMinusToDef');

// �g�щ{�����AAA�炵�����X���ȗ�����T�C�Y�i0�Ȃ疳���j
$conf_user_def['mobile.aa_ryaku_size'] = 30; // (30)
$conf_user_rules['mobile.aa_ryaku_size'] = array('notIntExceptMinusToDef');

// �g�щ{�����A�|�C���^�̉��R�O�̃��X����\�����邩
$conf_user_def['mobile.before_respointer'] = 0; // (0)
$conf_user_rules['mobile.before_respointer'] = array('notIntExceptMinusToDef');

// �g�щ{�����A�O�������N�ɒʋ΃u���E�U(��)�𗘗p(����:1, ���Ȃ�:0)
$conf_user_def['mobile.use_tsukin'] = 1; // (1)
$conf_user_rad['mobile.use_tsukin'] = array('1' => '����', '0' => '���Ȃ�');

// �g�щ{�����A�摜�����N��pic.to(��)�𗘗p(����:1, ���Ȃ�:0)
$conf_user_def['mobile.use_picto'] = 1; // (1)
$conf_user_rad['mobile.use_picto'] = array('1' => '����', '0' => '���Ȃ�');

// �g�щ{�����AYouTube�̃����N���T���l�C���\���i����:1, ���Ȃ�:0, �T���l�C���\�������Ń����N���Ȃ�:2�j
$conf_user_def['mobile.link_youtube'] = 0; // (0)
$conf_user_rad['mobile.link_youtube'] = array('1' => '����', '0' => '���Ȃ�', '2' => '��Ȳٕ\���������ݸ���Ȃ�');

// �g�щ{�����A�f�t�H���g�̖���������\���i����:1, ���Ȃ�:0�j
$conf_user_def['mobile.bbs_noname_name'] = 0; // (0)
$conf_user_rad['mobile.bbs_noname_name'] = array('1' => '����', '0' => '���Ȃ�');

// �g�щ{�����A�d�����Ȃ�ID�͖����݂̂̏ȗ��\���i����:1, ���Ȃ�:0�j
$conf_user_def['mobile.clip_unique_id'] = 1; // (1)
$conf_user_rad['mobile.clip_unique_id'] = array('1' => '����', '0' => '���Ȃ�');

// �g�щ{�����A���t��0���ȗ��\���i����:1, ���Ȃ�:0�j
$conf_user_def['mobile.date_zerosuppress'] = 1; // (1)
$conf_user_rad['mobile.date_zerosuppress'] = array('1' => '����', '0' => '���Ȃ�');

// �g�щ{�����A�����̕b���ȗ��\���i����:1, ���Ȃ�:0�j
$conf_user_def['mobile.clip_time_sec'] = 1; // (1)
$conf_user_rad['mobile.clip_time_sec'] = array('1' => '����', '0' => '���Ȃ�');

// �g�щ{�����AID������"O"�ɉ�����ǉ��i����:1, ���Ȃ�:0�j
$conf_user_def['mobile.underline_id'] = 0; // (0)
$conf_user_rad['mobile.underline_id'] = array('1' => '����', '0' => '���Ȃ�');

// �g�щ{�����A�u�ʁv�̃R�s�[�p�e�L�X�g�{�b�N�X�𕪊����镶����
$conf_user_def['mobile.copy_divide_len'] = 0; // (0)
$conf_user_rules['mobile.copy_divide_len'] = array('notIntExceptMinusToDef');

// �g�щ{�����A[[�P��]]��Wikipedia�ւ̃����N�ɂ���i����:1, ���Ȃ�:0�j
$conf_user_def['mobile.link_wikipedia'] = 0; // (0)
$conf_user_rad['mobile.link_wikipedia'] = array('1' => '����', '0' => '���Ȃ�');

// �g�щ{�����A�t�Q�ƃ��X�g�̕\��
$conf_user_def['mobile.backlink_list'] = 0;
$conf_user_rad['mobile.backlink_list'] = array('1' => '����', '0' => '���Ȃ�');

// �g�щ{�����A�t�Q�ƃ��X�g���ȗ��\�����鐔�i���̐���葽�����X�͏ȗ��\���B0:�ȗ����Ȃ��j
$conf_user_def['mobile.backlink_list.suppress'] = 1;
$conf_user_rules['mobile.backlink_list.suppress'] = array('notIntExceptMinusToDef');

// �g�щ{�����A�t�Q�ƃ��X�g�Ƀ��X�܂Ƃ߃y�[�W�ւ̃����N��\�����邩
$conf_user_def['mobile.backlink_list.openres_navi'] = 1;
$conf_user_rad['mobile.backlink_list.openres_navi'] = array('1' => '����', '2' => '�t�Q�ƃ��X�g���ȗ�����������', '0' => '���Ȃ�');

// �{�����_�u���N���b�N���ă��X�ǐՃJ���[�����O
$conf_user_def['backlink_coloring_track'] = 1;
$conf_user_rad['backlink_coloring_track'] = array('1' => '����', '0' => '���Ȃ�');
// �{�����_�u���N���b�N���ă��X�ǐՃJ���[�����O�̐F���X�g(�J���}��؂�)
$conf_user_def['backlink_coloring_track_colors'] = '#479e01,#0033ff,#0099cc,#9900ff,#ff5599,#ff9900,#993333,#ff6600,#0066cf,#ff3300';

// ID�ɐF��t����
$conf_user_def['coloredid.enable'] = 1;
$conf_user_rad['coloredid.enable'] = array('1' => '����', '0' => '���Ȃ�');
// ��ʕ\������ID�ɒ��F���Ă�������
$conf_user_def['coloredid.rate.type'] = 3;
$conf_user_rad['coloredid.rate.type'] = array('0' => '���Ȃ�', '1' => '�o����', '2' => '�X�����g�b�v10', '3' => '�X�������ψȏ�');
// �������o�����̏ꍇ�̐�(n�ȏ�)
$conf_user_def['coloredid.rate.times'] = 2;
$conf_user_rules['coloredid.rate.times'] = array('notIntExceptMinusToDef');
// �K������(ID�u�����N)�̏o����(0�Ŗ����BIE/Safari��blink��Ή�)
$conf_user_def['coloredid.rate.hissi.times'] = 25;
$conf_user_rules['coloredid.rate.hissi.times'] = array('notIntExceptMinusToDef');
// ID�o�������N���b�N����ƒ��F���g�O��(�u���Ȃ��v�ɂ����Javascript�ł͂Ȃ�PHP�Œ��F)
$conf_user_def['coloredid.click'] = 1;
$conf_user_rad['coloredid.click'] = array('1' => '����', '0' => '���Ȃ�');
// ID�o�������_�u���N���b�N���ă}�[�L���O����F���X�g(�J���}��؂�)
$conf_user_def['coloredid.marking.colors'] = '#f00,#0f0,#00f,#f90,#f0f,#ff0,#90f,#0ff,#9f0';
// �J���[�����O�̃^�C�v
$conf_user_def['coloredid.coloring.type'] = 0;
$conf_user_rad['coloredid.coloring.type'] = array('0' => '�I���W�i��', '1' => 'thermon��');

// }}}
// {{{ NG/���ځ[��

// >>1 �ȊO�̕p�oID�����ځ[�񂷂�(����:1, ���Ȃ�:0 NG�ɂ���:2)
$conf_user_def['ngaborn_frequent'] = 0; // (0)
$conf_user_rad['ngaborn_frequent'] = array('1' => '����', '0' => '���Ȃ�', '2' => 'NG�ɂ���');

// >>1 ���p�oID���ځ[��̑ΏۊO�ɂ���(����:1, ���Ȃ�:0)
$conf_user_def['ngaborn_frequent_one'] = 0; // (0)
$conf_user_rad['ngaborn_frequent_one'] = array('1' => '����', '0' => '���Ȃ�');

// �p�oID���ځ[��̂������l�i�o���񐔂�����ȏ��ID�����ځ[��j
$conf_user_def['ngaborn_frequent_num'] = 30; // (30)
$conf_user_rules['ngaborn_frequent_num'] = array('emptyToDef', 'notIntExceptMinusToDef');

// �����̑����X���ł͕p�oID���ځ[�񂵂Ȃ��i�����X��/�X�����Ă���̓����A0�Ȃ疳���j
$conf_user_def['ngaborn_frequent_dayres'] = 0; // (0)
$conf_user_rules['ngaborn_frequent_dayres'] = array('notIntExceptMinusToDef');

// �A��NG���ځ[��(����:1, ���Ȃ�:0, ���ځ[�񃌃X�ւ̃��X��NG�ɂ���:2)
$conf_user_def['ngaborn_chain'] = 0; // (0)
$conf_user_rad['ngaborn_chain'] = array('1' => '����', '0' => '���Ȃ�', '2' => '���ׂ�NG�ɂ���');

// NG���ځ[��̑ΏۂɂȂ������X��ID�������I��NG���ځ[�񂷂�(����:1, ���Ȃ�:0 NG�ɂ���:2)
$conf_user_def['ngaborn_auto'] = 0; // (0)
$conf_user_rad['ngaborn_auto'] = array('1' => '����', '0' => '���Ȃ�');

// �\���͈͊O�̃��X���A��NG���ځ[��̑Ώۂɂ���(����:1, ���Ȃ�:0)
// �������y�����邽�߁A�f�t�H���g�ł͂��Ȃ�
$conf_user_def['ngaborn_chain_all'] = 0; // (0)
$conf_user_rad['ngaborn_chain_all'] = array('1' => '����', '0' => '���Ȃ�');

// ���̊��ԁANG���ځ[���HIT���Ȃ���΁A�o�^���[�h�������I�ɊO���i�����j
$conf_user_def['ngaborn_daylimit'] = 180; // (180)
$conf_user_rules['ngaborn_daylimit'] = array('emptyToDef', 'notIntExceptMinusToDef');

// ���ځ[�񃌃X�͕s��div�u���b�N���`�悵�Ȃ�
$conf_user_def['ngaborn_purge_aborn'] = 0;  // (0)
$conf_user_rad['ngaborn_purge_aborn'] = array('1' => '�͂�', '0' => '������');

// }}}
// {{{ 2ch API

// 2ch API ���g�p����
$conf_user_def['2chapi_use'] = 0; // (0)
$conf_user_rad['2chapi_use'] = array('1' => '����', '0' => '���Ȃ�');

// 2ch API �F�؂���Ԋu(�P��:����)
$conf_user_def['2chapi_interval'] = 1; // (1)
$conf_user_rules['2chapi_interval'] = array('emptyToDef', 'notIntExceptMinusToDef');

// APPKey
$conf_user_def['2chapi_appkey'] = ""; // ("")

// HMKey
$conf_user_def['2chapi_hmkey'] = ""; // ("")

// AppName
$conf_user_def['2chapi_appname'] = ""; // ("")

// API�F�؂Ŏg�p����User-Agent
$conf_user_def['2chapi_ua.auth'] = "Monazilla/1.3"; // ("Monazilla/1.3")
$conf_user_sel['2chapi_ua.auth'] = array(
    'DOLIB/1.00'       => '1 DOLIB/1.00',
    'Monazilla/1.3'     => '2 Monazilla/1.3',
    'Monazilla/1.00 (%s)'    => '3 Monazilla/1.00 (AppName)',
    'Mozilla/3.0 (compatible; %s)'   => '4 Mozilla/3.0 (compatible; AppName)',
);

//DAT�擾�Ŏg�p����User-Agent
$conf_user_def['2chapi_ua.read'] = "Mozilla/3.0 (compatible; %s)"; // ("Monazilla/1.3")
$conf_user_sel['2chapi_ua.read'] = array(
    'Monazilla/1.00 (%s)'    => '1 Monazilla/1.00 (AppName)',
    'Mozilla/3.0 (compatible; %s)'   => '2 Mozilla/3.0 (compatible; AppName)',
);

// API�F�؂�SSL���g�p����
$conf_user_def['2chapi_ssl.auth'] = 1;  // (1)
$conf_user_rad['2chapi_ssl.auth'] = array('1' => '����', '0' => '���Ȃ�');

// DAT�擾��SSL���g�p����
$conf_user_def['2chapi_ssl.read'] = 1;  // (1)
$conf_user_rad['2chapi_ssl.read'] = array('1' => '����', '0' => '���Ȃ�');

// �f�o�b�O�p�̏����o�͂���
$conf_user_def['2chapi_debug_print'] = 0; // (0)
$conf_user_rad['2chapi_debug_print'] = array('1' => '����', '0' => '���Ȃ�');

// }}}
// {{{ ETC

// ���X�������ݎ��̃f�t�H���g�̖��O
$conf_user_def['my_FROM'] = ""; // ("")

// ���X�������ݎ��̃f�t�H���g��mail
$conf_user_def['my_mail'] = "sage"; // ("sage")

// PC�{�����A�\�[�X�R�[�h�̃R�s�y�ɓK�����␳������`�F�b�N�{�b�N�X��\���i����:1, ���Ȃ�:0, pc�I�̂�:2�j
$conf_user_def['editor_srcfix'] = 0; // (0)
$conf_user_rad['editor_srcfix'] = array('1' => '����', '0' => '���Ȃ�', '2' => 'pc�I�̂�');

// �V�����X���b�h���擾�������ɕ\�����郌�X��(�S�ĕ\������ꍇ:"all")
$conf_user_def['get_new_res'] = 200; // (200)

// �ŋߓǂ񂾃X���̋L�^��
$conf_user_def['rct_rec_num'] = 50; // (50)
$conf_user_rules['rct_rec_num'] = array('notIntExceptMinusToDef');

// �������ݗ����̋L�^��
$conf_user_def['res_hist_rec_num'] = 20; // (20)
$conf_user_rules['res_hist_rec_num'] = array('notIntExceptMinusToDef');

// �������ݓ��e���O���L�^(����:1, ���Ȃ�:0)
$conf_user_def['res_write_rec'] = 1; // (1)
$conf_user_rad['res_write_rec'] = array('1' => '����', '0' => '���Ȃ�');

// �|�b�v�A�b�v���珑�����ݐ���������X�����ēǂݍ��݂���(����:1, ���Ȃ�:0)
$conf_user_def['res_popup_reload'] = 1; // (1)
$conf_user_rad['res_popup_reload'] = array('1' => '����', '0' => '���Ȃ�');

// �O��URL�W�����v����ۂɒʂ��Q�[�g
// �u���ځv�ł�Cookie���g���Ȃ��[���ł� gate.php ��ʂ�
$conf_user_def['through_ime'] = "exm"; // ("exm")
$conf_user_sel['through_ime'] = array(
    ''       => '����',
    'p2'     => 'p2 ime (�����]��)',
    'p2m'    => 'p2 ime (�蓮�]��)',
    'p2pm'   => 'p2 ime (p�̂ݎ蓮�]��)',
    'ex'     => 'gate.php (�����]��1�b)',
    'exq'    => 'gate.php (�����]��0�b)',
    'exm'    => 'gate.php (�蓮�]��)',
    'expm'   => 'gate.php (p�̂ݎ蓮�]��)',
    'google' => 'Google',
);

// HTTPS�ŃA�N�Z�X���Ă���Ƃ��͊O��URL�Q�[�g��ʂ��Ȃ��iHTTPS�ł͒�:1, ��ɒʂ�:0�j
$conf_user_def['through_ime_http_only'] = 0; // (0)
$conf_user_rad['through_ime_http_only'] = array('1' => 'HTTPS�ł͒�', '0' => '��ɒʂ�');

// �Q�[�g�Ŏ����]�����Ȃ��g���q�i�J���}��؂�ŁA�g���q�̑O�̃s���I�h�͕s�v�j
$conf_user_def['ime_manual_ext'] = "exe,zip"; // ("exe,zip")

/*
// ���C�ɃX�����L�ɎQ���i����:1, ���Ȃ�:0�j
$conf_user_def['join_favrank'] = 0; // (0)
$conf_user_rad['join_favrank'] = array('1' => '����', '0' => '���Ȃ�');
 */

// ���C�ɔ̃X���ꗗ���܂Ƃ߂ĕ\�� (����:1, ���Ȃ�:0, �����X���̂�:2)
$conf_user_def['merge_favita'] = 0; // (0)
$conf_user_rad['merge_favita'] = array('1' => '����', '0' => '���Ȃ�', '2' => '�����X���̂�');

// �h���b�O���h���b�v�ł��C�ɔ���בւ���i����:1, ���Ȃ�:0�j
$conf_user_def['favita_order_dnd'] = 1; // (1)
$conf_user_rad['favita_order_dnd'] = array('1' => '����', '0' => '���Ȃ�');

// ���j���[�ɐV������\���i����:1, ���Ȃ�:0, ���C�ɔ̂�:2�j
$conf_user_def['enable_menu_new'] = 1; // (1)
$conf_user_rad['enable_menu_new'] = array('1' => '����', '0' => '���Ȃ�', '2' => '���C�ɔ̂�');

// ���j���[�����̎����X�V�Ԋu�i���w��B0�Ȃ玩���X�V���Ȃ��B�j
$conf_user_def['menu_refresh_time'] = 0; // (0)
$conf_user_rules['menu_refresh_time'] = array('notIntExceptMinusToDef');

// �J�e�S���ꗗ�������Ԃɂ���(����:1, ���Ȃ�:0)
$conf_user_def['menu_hide_brds'] = 0; // (0)
$conf_user_rad['menu_hide_brds'] = array('1' => '����', '0' => '���Ȃ�');

// �u���N���`�F�b�J (����:1, ���Ȃ�:0)
$conf_user_def['brocra_checker_use'] = 0; // (0)
$conf_user_rad['brocra_checker_use'] = array('1' => '����', '0' => '���Ȃ�');

// �u���N���`�F�b�JURL
$conf_user_def['brocra_checker_url'] = ""; // ("")
$conf_user_rules['brocra_checker_url'] = array('emptyToDef', 'invalidUrlToDef');

// �u���N���`�F�b�J�̃N�G���[
$conf_user_def['brocra_checker_query'] = ""; // ("")

// �g�щ{�����A�p�P�b�g�ʂ����炷���߁A�S�p�p���E�J�i�E�X�y�[�X�𔼊p�ɕϊ� (����:1, ���Ȃ�:0)
$conf_user_def['mobile.save_packet'] = 1; // (1)
$conf_user_rad['mobile.save_packet'] = array('1' => '����', '0' => '���Ȃ�');

// �v���L�V�𗘗p(����:1, ���Ȃ�:0)
$conf_user_def['proxy_use'] = 0; // (0)
$conf_user_rad['proxy_use'] = array('1' => '����', '0' => '���Ȃ�');

// �v���L�V�z�X�g ex)"127.0.0.1", "www.p2proxy.com"
$conf_user_def['proxy_host'] = ""; // ("")

// �v���L�V�|�[�g ex)"8080"
$conf_user_def['proxy_port'] = ""; // ("")

// �v���L�V���[�U�[�� (�g�p����ꍇ�̂�)
$conf_user_def['proxy_user'] = ""; // ("")

// �v���L�V�p�X���[�h (�g�p����ꍇ�̂�)
$conf_user_def['proxy_password'] = ""; // ("")

// �t���[���� ���j���[ �̕\����
$conf_user_def['frame_menu_width'] = "158"; // ("158")

// �t���[���E�� �X���ꗗ �̕\����
$conf_user_def['frame_subject_width'] = "40%"; // ("40%")

// �t���[���E�� �X���{�� �̕\����
$conf_user_def['frame_read_width'] = "60%"; // ("60%")

// 3�y�C����ʂ̃t���[���̕��ו�
$conf_user_def['pane_mode'] = 0;  // (0)
$conf_user_rad['pane_mode'] = array('0' => '�W���i�Ɍ`�j', '1' => '�����i��`�j');

// SSL�ʐM(�����O�C����)�Ɏg�p����֐� ���Y������g�����C���X�g�[������K�v������
$conf_user_def['ssl_function'] = "curl";  // (socket)
$conf_user_sel['ssl_function'] = array('socket' => 'OpenSSL', 'curl' => 'cURL');

// SSL�ʐM�̐ڑ�������؂��邽�߂Ɏg�p����ؖ������i�[���ꂽ�f�B���N�g�� �����؂ł��Ȃ����̂ݎw��
$conf_user_def['ssl_capath'] = ""; // ()

// 2ch.net�́����O�C����SSL���g�p����
$conf_user_def['2ch_ssl.maru'] = 1;  // (1)
$conf_user_rad['2ch_ssl.maru'] = array('1' => '����', '0' => '���Ȃ�');

// 2ch.net��subjec.txt��SETTING.TXT�̎擾��SSL���g�p����
$conf_user_def['2ch_ssl.subject'] = 0;  // (0)
$conf_user_rad['2ch_ssl.subject'] = array('1' => '����', '0' => '���Ȃ�');

// 2ch.net�̏������݂�SSL���g�p����
$conf_user_def['2ch_ssl.post'] = 0;  // (0)
$conf_user_rad['2ch_ssl.post'] = array('1' => '����', '0' => '���Ȃ�');

// }}}
// {{{ �g���p�b�N��iPhone

include P2_CONFIG_DIR . '/conf_user_def_ex.inc.php';
include P2_CONFIG_DIR . '/conf_user_def_i.inc.php';

// }}}
// {{{ ��+Wiki

include P2_CONFIG_DIR . '/conf_user_def_wiki.inc.php';

// }}}
// {{{ +live

include P2_CONFIG_DIR . '/conf_user_def_live.inc.php';

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
