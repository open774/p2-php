<?php
/*
    rep2-ip2host - ���[�U�ݒ� �f�t�H���g
    
    ���̃t�@�C���̓f�t�H���g�l�̐ݒ�Ȃ̂ŁA���ɕύX����K�v�͂���܂���
*/

// {{{ �L���b�V�����@

// �L���b�V�����@(sessionStorage:0, localStorage:1, �T�[�o�[���t�@�C��:2)
$conf_user_def['ip2host.cache.type'] = 0; // (0)
$conf_user_sel['ip2host.cache.type'] = array(
    '0' => 'sessionStorage(�u���E�U��)',
    '1' => 'localStorage(�u���E�U��)',
    '2' => '�t�@�C��(�T�[�o�[��)',
);

// }}}

// {{{ ���������̃^�C�~���O

// ���������̃^�C�~���O(�X���\�����Ɉꊇ��������:0, ��ʃX�N���[���ŏ�������:1)
$conf_user_def['ip2host.replace.type'] = 0; // (0)
$conf_user_sel['ip2host.replace.type'] = array(
    '0' => '�X���\�����Ɉꊇ��������',
    '1' => '��ʃX�N���[���ŏ�������',
);

// }}}

// {{{ ip2host�̐ݒ�

// ip2host���g�p���邩
$conf_user_def['ip2host.enabled'] = 0; // (0)
$conf_user_rad['ip2host.enabled'] = array('1' => '����', '0' => '���Ȃ�');

// �L���b�V���̏����
$conf_user_def['ip2host.cache.size'] = 500; // (500)
$conf_user_rules['ip2host.cache.size'] = array('emptyToDef', 'notIntExceptMinusToDef');

// �t������̂��ځ[�񏈗������邩
$conf_user_def['ip2host.aborn.enabled'] = 1; // (1)
$conf_user_rad['ip2host.aborn.enabled'] = array('1' => '����', '0' => '���Ȃ�');

// }}}
