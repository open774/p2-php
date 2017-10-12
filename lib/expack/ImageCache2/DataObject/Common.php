<?php

// {{{ ImageCache2_DataObject_Common

/**
 * @abstract
 */
class ImageCache2_DataObject_Common extends DB_DataObject
{
    // {{{ properties

    protected $_db;
    protected $_ini;

    // }}}
    // {{{ constcurtor

    /**
     * コンストラクタ
     */
    public function __construct()
    {
        static $set_to_utf8 = false;

        // 設定の読み込み
        $ini = ic2_loadconfig();
        $this->_ini = $ini;
        if (!$ini['General']['dsn']) {
            p2die('DSNが設定されていません。');
        }

        // データベースへ接続
        $this->_database_dsn = $ini['General']['dsn'];
        $this->_db = $this->getDatabaseConnection();
        if (PEAR::isError($this->_db)) {
            p2die($this->_db->getMessage());
        }

        // MDB2の拡張モジュール読込み
        if ($this->_ini['General']['db_driver'] == 'MDB2') {
            $this->_db->loadModule('Extended');
        }

        // クライアントの文字セットに UTF-8 を指定
        if (!$set_to_utf8) {
            if (preg_match('/^(\w+)(?:\((\w+)\))?:/', $this->_database_dsn, $m)) {
                $driver = strtolower($m[1]);
            } else {
                $driver = 'unknown';
            }

            switch ($driver) {
            case 'mysql':
            case 'mysqli':
                if ($driver == 'mysql' && function_exists('mysql_set_charset')) {
                    mysql_set_charset('utf8', $this->_db->connection);
                } elseif ($driver == 'mysqli' && function_exists('mysqli_set_charset')) {
                    mysqli_set_charset($this->_db->connection, 'utf8');
                } else {
                    $this->_db->query("SET NAMES utf8");
                }
                break;
            case 'pgsql':
                if (function_exists('pg_set_client_encoding')) {
                    pg_set_client_encoding($this->_db->connection, 'UNICODE');
                } else {
                    $this->_db->query("SET CLIENT_ENCODING TO 'UNICODE'");
                }
                break;
            }

            $set_to_utf8 = true;
        }
    }

    // }}}
    // {{{ whereAddQuoted()

    /**
     * 適切にクォートされたWHERE句をつくる
     */
    public function whereAddQuoted($key, $cmp, $value, $logic = 'AND')
    {
        $types = $this->table();
        $col = $this->_db->quoteIdentifier($key);
        if ($types[$key] != DB_DATAOBJECT_INT) {
        	$db_quote = ($this->_ini['General']['db_driver'] == 'MDB2') ? 'quote' : 'quoteSmart';
            $value = $this->_db->$db_quote($value);
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
            $k = $this->_db->quoteIdentifier($k);
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
