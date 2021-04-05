<?php
/**
 * ImageCache2 - �������猏�����擾����
 */

function getIC2ImageCount($key, $threshold = null) {
    require_once P2EX_LIB_DIR . '/ImageCache2/bootstrap.php';
    // �ݒ�t�@�C���ǂݍ���
    $ini = ic2_loadconfig();

    $icdb = new ImageCache2_DataObject_Images();
    // 臒l�Ńt�B���^�����O
    if ($threshold === null) $threshold = $ini['Viewer']['threshold'];
    if (!($threshold == -1)) {
        $icdb->whereAddQuoted('rank', '>=', $threshold);
    }

    $db_class = $icdb->db_class;
    $keys = explode(' ', $icdb->uniform($key, 'CP932'));
    foreach ($keys as $k) {
        $operator = 'LIKE';
        $wildcard = '%';
        $not = false;
        if ($k[0] == '-' && strlen($k) > 1) {
            $not = true;
            $k = substr($k, 1);
        }
        if (strpos($k, '%') !== false || strpos($k, '_') !== false) {
            // SQLite2��LIKE���Z�q�̉E�ӂŃo�b�N�X���b�V���ɂ��G�X�P�[�v��
            // ESCAPE�ŃG�X�P�[�v�������w�肷�邱�Ƃ��ł��Ȃ��̂�GLOB���Z�q���g��
            if ($db_class == 'sqlite') {
                if (strpos($k, '*') !== false || strpos($k, '?') !== false) {
                    throw new InvalidArgumentException('�u%�܂���_�v�Ɓu*�܂���?�v�����݂���L�[���[�h�͎g���܂���B');
                } else {
                    $operator = 'GLOB';
                    $wildcard = '*';
                }
            } else {
                $k = preg_replace('/[%_]/', '\\\\$0', $k);
            }
        }
        $expr = $wildcard . $k . $wildcard;
        if ($not) {
            $operator = 'NOT ' . $operator;
        }
        $icdb->whereAddQuoted('memo', $operator, $expr);
    }

    try {
        $all = $icdb->count('*');
    } catch (PDOException $e) {
        p2die($e->getMessage());
    }
    return $all;
}

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
