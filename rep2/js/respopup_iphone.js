/**
 * rep2expack - iPhone�p���X�|�b�v�A�b�v
 *
 * iphone.js�̌�ɓǂݍ���
 * jQuery �K�{�ɂȂ�܂��� by 2ch774
 */

// {{{ globals

var _IRESPOPG = {
	'hash': {},
	'serial': 0,
	'callbacks': []
};

var ipoputil = {};

// }}}
// {{{ ipoputil.getZ()

/**
 * z-index�ɐݒ肷��l��Ԃ�
 *
 * css/ic2_iphone.css �� div#ic2-info �� z-index �� 999 ��
 * �Œ肳��Ă���̂Ń|�b�v�A�b�v���J��Ԃ��ƕs�������B
 * �|�b�v�A�b�v�I�u�W�F�N�g�� z-index ���W���Ǘ�����K�v����B
 *
 * @param {Element} obj
 * @return {String}
 */
ipoputil.getZ = function() {
	return (10 + _IRESPOPG.serial).toString();
};

// }}}
// {{{ ipoputil.getActivator()

/**
 * �I�u�W�F�N�g���őO�ʂɈړ�����֐���Ԃ�
 *
 * @param {Element} obj
 * @return void
 */
ipoputil.getActivator = function(obj) {
	return (function(){
		_IRESPOPG.serial++;
		obj.style.zIndex = ipoputil.getZ();
	});
};

// }}}
// {{{ ipoputil.getDeactivator()

/**
 * DOM�c���[����I�u�W�F�N�g����菜���֐���Ԃ�
 *
 * @param {Element} obj
 * @param {String} key
 * @return void
 */
ipoputil.getDeactivator = function($obj, key) {
	return (function(){
		delete _IRESPOPG.hash[key];
		//obj.parentNode.removeChild(obj);
		$obj.remove();
		delete $obj;
	});
};

// }}}
// {{{ ipoputil.callback()

/**
 * iPhone�p���X�|�b�v�A�b�v�̃R�[���o�b�N���\�b�h
 *
 * @param {XMLHttpRequest} req
 * @param {String} url
 * @param {String} popid
 * @param {Number} yOffset
 * @return void
 * @todo use asynchronous request
 */
ipoputil.callback = function(req, url, popid, yOffset) {
	var $container = $("<div/>");
	var $closer = $("<img/>");

	$container.attr("id",popid);
	$container.addClass("respop");
	$container.html(req.responseText);

	/*
	var rx = req.responseXML;
	while (rx.hasChildNodes()) {
		container.appendChild(document.importNode(rx.removeChild(rx.firstChild), true));
	}
	*/
	$container.css('top',yOffset.toString() + 'px');
	$container.css('z-index',ipoputil.getZ());
	//respop���̓�����������ɂ��Ă���
	$container.skOuterClick(ipoputil.getDeactivator($container, url),$("[id^=_respop]"),$('.close-button'),$('#ic2-info-body'),$('#ic2-info-closer'),$('#spm'),$('#spm-closer'));

	$closer.addClass('close-button');
	$closer.attr('src', 'img/iphone/close.png');
	$closer.click(ipoputil.getDeactivator($container, url));

	$container.append($closer);
	$(document.body).append($container);

	iutil.modifyExternalLink($container[0]);

	_IRESPOPG.hash[url] = $container[0];

	var lastres = document.evaluate('./div[@class="res" and position() = last()]',
									$container[0],
									null,
									XPathResult.ANY_UNORDERED_NODE_TYPE,
									null
									).singleNodeValue;

	if (lastres) {
		var back = document.createElement('div');
		back.className = 'respop-back';
		var anchor = document.createElement('a');
		anchor.setAttribute('href', '#' + popid);
		anchor.onclick = function(evt){
			iutil.stopEvent(evt || window.event);
			scrollTo(0, yOffset - 10);
			return false;
		};
		anchor.appendChild(document.createTextNode('��'));
		back.appendChild(anchor);
		lastres.appendChild(back);
	}

	var i;
	for (i = 0; i < _IRESPOPG.callbacks.length; i++) {
		_IRESPOPG.callbacks[i]($container[0]);
	}
};

// }}}
// {{{ ipoputil.popup()

/**
 * iPhone�p���X�|�b�v�A�b�v
 *
 * @param {String} url
 * @param {Event} evt
 * @return void
 */
ipoputil.popup = function(url, evt) {
	var yOffset = Math.max(10, iutil.getPageY(evt) - 20);

	if (_IRESPOPG.hash[url]) {
		_IRESPOPG.serial++;
		_IRESPOPG.hash[url].style.top = yOffset.toString() + 'px';
		_IRESPOPG.hash[url].style.zIndex = ipoputil.getZ();
		return false;
	}

	_IRESPOPG.serial++;
	var popnum = _IRESPOPG.serial;
	var popid = '_respop' + popnum;
	var req = new XMLHttpRequest();
	req.open('GET', url + '&ajax=true&respop_id=' + popnum, true);
	req.onreadystatechange = function() {
		if (this.readyState == 4) {
			if (this.status == 200) {
				ipoputil.callback(this, url, popid, yOffset);
			}
		}
	};
	req.send(null);
};

// }}}
// {{{ iResPopUp()

/**
 * iPhone�p���X�|�b�v�A�b�v
 *
 * @param {String} url
 * @param {Event} evt
 * @return false
 * @see iutil.popup
 */
var iResPopUp = function(url, evt) {
	evt = evt || window.event;
	iutil.stopEvent(evt);
	if (typeof url !== 'string' && typeof url.href === 'string') {
		url = url.href;
	}
	ipoputil.popup(url, evt);
	return false;
};

// }}}

/*
 * Local Variables:
 * mode: javascript
 * coding: cp932
 * tab-width: 4
 * c-basic-offset: 4
 * indent-tabs-mode: t
 * End:
 */
/* vim: set syn=javascript fenc=cp932 ai noet ts=4 sw=4 sts=4 fdm=marker: */
