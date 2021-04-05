<?php

// {{{ ip2host.inc.php

/**
* ip2host
*/

//  ip->hostしてあぼーん
echo <<<IP2HOST
<script>
    let web_storage = 0;

IP2HOST;
echo "    let scroll_replace = ".$_conf['ip2host.replace.type'].";\n";
//if ($_conf['ip2host.replace.type'] == 0) {
//    echo "    let scroll_replace = 0;";
//} else {
//    echo "    let scroll_replace = 1;";
//}
echo <<<IP2HOST

    if (('sessionStorage' in window) && (window.sessionStorage !== null)) {
        web_storage = 1;
    }\n
IP2HOST;
if ($_conf['ip2host.cache.type'] == 2) {
    //  WebStorageを使用しない
    echo "    web_storage = 0;\n";
}
echo <<<IP2HOST
    
    //$(window).on('load',function() {
    $(function() {
        ip2host(scroll_replace, web_storage);
    });

    if (scroll_replace) {
        $(window).on('scroll click', function () {
            ip2host(scroll_replace, web_storage);
        });
    }

    function ip2host(scroll_replace, web_storage = 0) {
        let st = null;

        if (web_storage) {\n
IP2HOST;
if ($_conf['ip2host.cache.type'] == 0) {
    //  sessionStorage
    echo "            st = sessionStorage;\n";
} else {
    //  localStorage
    echo "            st = localStorage;\n";
}
echo <<<IP2HOST
        }

        $('div.res-header').each(function() {
            let res_header = $(this);

            let header_top = res_header.offset().top;
            let scroll_top = $(window).scrollTop();
            let wh = $(window).height();
            if (!scroll_replace || (scroll_replace && header_top > scroll_top && header_top < scroll_top + wh)) {
                let res_header_tmp = res_header.clone();
                res_header_tmp.find('b').text('');
                let ip_addr = res_header_tmp.html().match(/.+? \[(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})[\]| ]/);
                if (ip_addr == null) {
                    return true;
                };

                replace_html(ip_addr[1], st, res_header);
            }
        });
    }

    function replace_html(ip_addr, st, res_header) {
        let param = {};
        let ip2host_url = 'ip2host.php';
        let host = null;

        //  名前欄のあぼーん処理を適用 する:1 しない:0\n
IP2HOST;
echo "        let aborn = ".$_conf['ip2host.aborn.enabled'].";\n";
echo <<<IP2HOST
        
        //  キャッシュするIPの上限数\n
IP2HOST;
echo "        let ip_cache_size = ".$_conf['ip2host.cache.size'].";\n";
echo <<<IP2HOST
        
        if (st) {
            if (st.length > ip_cache_size) {
                st.clear();
            } else {
                host = st.getItem(ip_addr);
            }
        } else {
            param['cache_size'] = ip_cache_size;
		}
        if (host === null) {
            param['action'] = 'GetHost';
        } else {
            param['action'] = 'AbornHost';
        }
        param['ip'] = ip_addr;
        param['aborn'] = aborn;
        param['host'] = host;\n
IP2HOST;
        echo "            param['bbs'] = '".$bbs."';\n";
        echo "            param['title'] = '".$ttitle_en."';\n";
echo <<<IP2HOST
        
        $.ajax({
            type: "GET",
            url: ip2host_url,
            data: param,
            dataType : "text",
            timeout: 30000
        }).done(function(data){
            let ret_host = $.trim(data);

            if (host === null) {
                if (ret_host.indexOf(',') != -1) {
                    let ret = ret_host.split(',');

                    host = ret[0];
                    ret_host = ret[1];
                }
                else
                {
                    host = ret_host;
                }

                if (st) {
                    st.setItem(ip_addr, host);
                }
            }

            if (ret_host == ip_addr) {
                console.log('unresolved IP address: '+ret_host);
            } else if (ret_host == 'aborn'){
                res_header.hide();
                res_header.parent().find('.message').hide();
                res_header.parent().hide();
            } else {
                let b_text = res_header.find('b').text();
                res_header.find('b').text('');
                res_header.html(res_header.html().replace(/ \[(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})([\]| ])/, ' ['+ret_host+'$2'));
                res_header.find('b').text(b_text);
            }
        }).fail(function(XMLHttpRequest, textStatus, errorThrown){
            //alert(errorThrown);
        });
	}
</script>\n
IP2HOST;

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
