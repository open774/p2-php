<?php

// {{{ ImageCache2_DataObject_BlackList

class ImageCache2_DataObject_BlackList extends ImageCache2_DataObject_Common
{
    public $__table;                        // ƒe[ƒuƒ‹–¼
    public $id;                             // INTEGER not_null primary
    public $uri;                            // CHARACTER VARYING
    public $size;                           // INTEGER not_null
    public $md5;                            // CHARACTER not_null
    public $type;                           // SMALLINT not_null

    // {{{ constants

    const NOMORE = 0;
    const ABORN  = 1;
    const VIRUS  = 2;

    // }}}
    // {{{ constcurtor

    public function __construct()
    {
        parent::__construct();
        $this->__table = $this->_ini['General']['blacklist_table'];
    }

    // }}}
    // {{{ keys()

    public function keys()
    {
        return array('uri');
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
