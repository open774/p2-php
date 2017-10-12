<?php

// {{{ ImageCache2_DataObject_Errors

class ImageCache2_DataObject_Errors extends ImageCache2_DataObject_Common
{
    // {{{ constcurtor

    public function __construct()
    {
        parent::__construct();
        $this->__table = $this->_ini['General']['error_table'];
    }

    // }}}
    // {{{ table()

    public function table()
    {
        return array(
            'uri'     => DB_DATAOBJECT_STR,
            'errcode' => DB_DATAOBJECT_STR,
            'errmsg'  => DB_DATAOBJECT_STR,
            'occured' => DB_DATAOBJECT_INT,
        );
    }

    // }}}
    // {{{ keys()

    public function keys()
    {
        return array('uri');
    }

    // }}}
    // {{{ ic2_errlog_lotate()

    public function ic2_errlog_lotate()
    {
        $ini = ic2_loadconfig();
        $error_log_num = $ini['General']['error_log_num'];
        if ($error_log_num > 0) {
            $q_table = $this->_db->quoteIdentifier($this->__table);
            $sql1 = 'SELECT COUNT(*) FROM ' . $q_table;
            $sql2 = 'SELECT MIN(occured) FROM ' . $q_table;
            $sql3 = 'DELETE FROM ' . $q_table . ' WHERE occured = ';

            while (($r1 = $this->_db->getOne($sql1)) > $error_log_num) {
                if (PEAR::isError($r1)) {
                    return $r1;
                }
                $method = ($db_driver  == 'DB') ? 'getOne' : 'queryOne';
                $r2 = $this->_db->$method($sql2);
                if (PEAR::isError($r2)) {
                    return $r2;
                }
                $r3 = $this->_db->query($sql3 . $r2);
                if (PEAR::isError($r3)) {
                    return $r3;
                }
                if ($this->_db->affectedRows() == 0) {
                    break;
                }
            }

        }
        return DB_OK;
    }

    // }}}
    // {{{ ic2_errlog_clean()

    public function ic2_errlog_clean()
    {
        return $this->_db->query('DELETE FROM ' . $this->_db->quoteIdentifier($this->__table));
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
