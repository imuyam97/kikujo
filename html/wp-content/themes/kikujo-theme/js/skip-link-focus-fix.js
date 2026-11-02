/**
 * Skip link focus fix for older browsers
 *
 * Helps with accessibility by ensuring the skip link
 * works correctly in all browsers.
 *
 * @package Kikujo_Theme
 */

(function() {
    'use strict';

    var isWebkit = navigator.userAgent.toLowerCase().indexOf('webkit') > -1;
    var isOpera = navigator.userAgent.toLowerCase().indexOf('opera') > -1;
    var isIE = navigator.userAgent.toLowerCase().indexOf('msie') > -1;

    if ((isWebkit || isOpera || isIE) && document.getElementById && window.addEventListener) {
        window.addEventListener('hashchange', function() {
            var id = location.hash.substring(1);
            var element;

            if (!(/^[A-z0-9_-]+$/).test(id)) {
                return;
            }

            element = document.getElementById(id);

            if (element) {
                if (!(/^(?:a|select|input|button|textarea)$/i).test(element.tagName)) {
                    element.tabIndex = -1;
                }

                element.focus();
            }
        }, false);
    }
})();
