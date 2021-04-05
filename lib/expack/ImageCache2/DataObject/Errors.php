<?php

// {{{ ImageCache2_DataObject_Errors

class ImageCache2_DataObject_Errors extends ImageCache2_DataObject_Common
{
    public $__table;                        // テーブル名
    public $uri;                            // CHARACTER VARYING
    public $errcode;                        // CHARACTER VARYING not_null
    public $errmsg;                         // TEXT
    public $occured;                        // INTEGER not_null

    // {{{ constcurtor

    public function __construct()
    {
        parent::__construct();
        $this->__table = $this->_ini['General']['error_table'];
    }

    // }}}
    // {{{ keys()

    public function keys()
    {
        return array('uri');
    }

    // }}}
    // {{{ ic2_errlog_lotate()

    /**
     * ログの総数がerror_log_numを超えたら古いログから切り詰める
     */
    public function ic2_errlog_lotate()
    {
        $ini = ic2_loadconfig();
        $error_log_num = $ini['General']['error_log_num'];
        if ($error_log_num > 0) {
            $db = $this->PDO();
            $table = $this->__table;

            $getLogRows = function () use ($db, $table) {
                $stmt = $db->prepare('SELECT COUNT(*) FROM ' . $this->quoteIdentifier($table));
                $stmt->execute();
                return $stmt->fetch()[0];
            };

            $getRemovalLogs = function ($limit) use ($db, $table) {
                $stmt = $db->prepare('SELECT ' . $this->quoteIdentifier('occured') . ' FROM ' . $this->quoteIdentifier($table) . ' ORDER BY ' . $this->quoteIdentifier('occured') . ' LIMIT :num;');
                $stmt->bindValue(':num', $limit, PDO::PARAM_INT);
                $stmt->execute();
                return $stmt->fetchAll(PDO::FETCH_COLUMN);
            };

            $deleteLog = function ($occured) use ($db, $table) {
                $stmt = $db->prepare('DELETE FROM ' . $this->quoteIdentifier($table) . ' WHERE ' . $this->quoteIdentifier('occured') . ' = :occured;');
                $stmt->bindValue('occured', $occured, PDO::PARAM_INT);
                $stmt->execute();
                return $stmt->rowCount();
            };

            $num = $getLogRows() - $error_log_num;
            if ($num <= 0) {
                return;
            }

            foreach ($getRemovalLogs($num) as $occured) {
                $deleteLog($occured);
            }
        }
    }

    // }}}
    // {{{ ic2_errlog_clean()

    public function ic2_errlog_clean()
    {
        return $this->PDO()->query('DELETE FROM ' . $this->quoteIdentifier($this->__table));
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
