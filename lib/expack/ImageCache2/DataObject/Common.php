<?php

// {{{ ImageCache2_DataObject_Common

/**
 * @abstract
 */
class ImageCache2_DataObject_Common extends PDO_DataObject
{
    // {{{ properties
    protected $_ini;
    public $db_class;
    // }}}
    // {{{ constcurtor

    /**
     * コンストラクタ
     */
    public function __construct()
    {
        parent::__construct();
        // 設定の読み込み
        $this->_ini = ic2_loadconfig();
        $this->db_class = explode(':', $this->_ini['General']['dsn'])[0];
    }

    // }}}
    // {{{ whereAddQuoted()

    /**
     * 適切にクォートされたWHERE句をつくる
     */
    public function whereAddQuoted($key, $cmp, $value, $logic = 'AND')
    {
        $types = $this->tableColumns();
        $col = $this->quoteIdentifier($key);
        if ($types[$key] != PDO_DataObject::INT) {
            $value = $this->PDO()->quote($value);
        }
        $cond = sprintf('%s %s %s', $col, $cmp, $value);
        return $this->whereAdd($cond, $logic);
    }

    // }}}
    // {{{ orderByArray()

    /**
     * 配列からORDER BY句をつくる
     */
    public function orderByArray(array $sort)
    {
        $order = array();
        foreach ($sort as $k => $d) {
            if (!is_string($k)) {
                if ($d && is_string($d)) {
                    $k = $d;
                    $d = 'ASC';
                } else {
                    continue;
                }
            }
            $k = $this->quoteIdentifier($k);
            if (!$d || strtoupper($d) == 'DESC') {
                $order[] = $k . ' DESC';
            } else {
                $order[] = $k . ' ASC';
            }
        }
        if (!count($order)) {
            return false;
        }
        return $this->orderBy(implode(', ', $order));
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
