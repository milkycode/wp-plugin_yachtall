/**
 * Yachtino Shiplisting WordPress Plugin.
 * @author      Christian Hinz <christian@milkycode.com>
 * @category    Milkycode
 * @package     shiplisting
 * @copyright   Copyright (c) 2022 milkycode GmbH (https://www.milkycode.com)
 */

/** @namespace wp */
window.wp = window.wp || {};

(function ($) {
    /**
     * Create the WordPress Shiplisting namespace.
     *
     * @namespace wp.sl
     */
    wp.sl = {};
    wp.sl.route = {};
    wp.sl.utils = {};

    wp.sl.route.constructor = function (route) {
        this._id = route.id;
        this._path = route.path;
        this._title = route.title;
        this._language = route.language;
        this._callback = route.callback;
    };

    wp.sl.constructor = function () {
        wp.sl.initialize();
    };

    wp.sl.initialize = function () {
        // initialize class
        this.init_route();
    };

    wp.sl.init_route = function () {
        this._current_uri = window.location.href;
        wp.sl.send_api_request({
            'action': 'shiplisting_api_init_route',
            'callback': wp.sl.init_route_callback
        }, {
            'uri': this._current_uri
        });
    };

    wp.sl.init_route_callback = function (data) {
        this._current_route = new wp.sl.route.constructor(data);
    };

    wp.sl.send_api_request = function (options, post_data) {
        options = options || {};
        post_data = post_data || null;

        options = wp.sl.utils._defaults(options, {
            type: 'POST',
            url: shiplisting_public.ajax_url,
            action: null,
            callback: null
        });

        if (options.callback === undefined) {
            return;
        }

        $.ajax({
            type: options.type,
            url: options.url,
            data: {
                action: options.action,
                post_data
            },
            success: function (data) {
                //options.callback(JSON.parse(data));
            },
            error: function (XMLHttpRequest, statusText) {
                options.callback(statusText);
            }
        });
    };

    wp.sl.utils._defaults = function (sources, defaults) {
        if (sources.length === 0 || defaults.length === 0) {
            return sources;
        }

        $.each(defaults, function (key, val) {
            var source_value = sources[key];
            if (source_value === undefined) {
                sources[key] = val;
            }
        });

        return sources;
    };
}(jQuery));

jQuery(document).ready(function () {
    var test = new wp.sl.constructor();

    var _boatImages = [];
    jQuery('.shiplisting-images-slider li').each(function (key, val) {
        _boatImages.push([key + 1, jQuery(val).find('img').attr('src')]);

        var imgSrc = jQuery(val).find('img').attr('src');

        if (imgSrc === undefined)
            return;

        imgSrc = imgSrc.replace('huge_', 'list_');

        var video = '';
        var videoId = '';
        if (jQuery(this).hasClass('video')) {
            video = ' video';
            videoId = ' video-id="' + jQuery(this).attr('video-id') + '"';
        }

        var currentPicture = ' class="shiplisting-modal-slider-item' + video + '"';
        if (key === 0)
            currentPicture = ' class="shiplisting-modal-slider-item' + video + ' current"';

        jQuery('.shiplisting-modal-slider ul').append('<li' + currentPicture + '' + videoId + '><img src="' + imgSrc + '" /></li>');
    });
    jQuery('.shiplisting-modal-current-img img').attr('src', jQuery('.shiplisting-images-current img').attr('src'));

    if (jQuery('.shiplisting-discounts-content-wrapper') !== undefined) {
        if (jQuery('.shiplisting-discounts-content-wrapper').text().length < 1) {
            jQuery('.shiplisting-discounts-wrapper').remove();
        }
    }

    jQuery('div.shiplisting-charter-possibilities div.row').each(function (key, val) {
        if (jQuery(this).find('.right').text().length < 1) {
            jQuery(this).remove();
        }
    });

    var identicalBoats = jQuery('span.shiplisting-identical-boats-value');
    if (identicalBoats !== undefined) {
        if (identicalBoats.text().length < 1) {
            identicalBoats.parent().parent().hide();
        }
    }

    function isItemInArray(array, item) {
        for (var i = 0; i < array.length; i++) {
            if (array[i][1] == item) {
                return array[i][0];
            }
        }
        return false;
    }

    if (jQuery('div.shiplisting-sale-contact-form') !== undefined) {
        if (!jQuery('div.shiplisting-sale-contact-form').hasClass('enabled')) {
            jQuery('div.shiplisting-sale-contact-form').remove();
        }
    }

    jQuery('.shiplisting-images-slider-item').on('click', function (e) {
        e.preventDefault();
        //if (!jQuery(this).hasClass('current')) {
        jQuery('.shiplisting-images-slider-item.current').removeClass('current');

        var imgSrc = jQuery(this).find('img').attr('src');
        if (imgSrc !== undefined) {
            imgSrc = imgSrc.replace('list_', 'huge_');
            jQuery('.shiplisting-images-current img').attr('src', imgSrc);
            jQuery(this).addClass('current');
        }

        if (jQuery(this).hasClass('video')) {
            jQuery('.shiplisting-modal-current-img img').css('display', 'none');
            var videoUrl = 'https://www.youtube-nocookie.com/embed/{video_id}?rel=0&showinfo=0';
            videoUrl = videoUrl.replace('{video_id}', jQuery(this).attr('video-id'));
            if (jQuery('.shiplisting-modal-current-img').find('iframe') !== undefined) {
                if (jQuery('.shiplisting-modal-current-img').find('iframe').length > 0) {
                    jQuery('.shiplisting-modal-current-img').find('iframe').remove();
                }
                jQuery('.shiplisting-modal-current-img').append('<iframe type="text/html" width="100%" height="100%" src="' + videoUrl + '" frameborder="0" allowfullscreen></iframe> ');
            } else {
                jQuery('.shiplisting-modal-current-img').find('iframe').attr('src', videoUrl);
            }
        } else if (jQuery(this).hasClass('pdf')) {
            jQuery(this).addClass('current');

            if (jQuery(this).attr('pdf-url') !== undefined)
                window.open(jQuery(this).attr('pdf-url'));
        } else {
            jQuery('.shiplisting-modal-current-img img').css('display', 'initial');
            jQuery('.shiplisting-modal-current-img iframe').css('display', 'none');

            jQuery('.shiplisting-modal-current-img img').attr('src', imgSrc);

            jQuery('.shiplisting-modal-slider-item.current').removeClass('current');
            jQuery('.shiplisting-modal-slider-item img[src="' + jQuery(this).find('img').attr('src') + '"]').parent().addClass('current');
        }

        var posMaxHtml = jQuery('.shiplisting-images-position').html();
        posMaxHtml = posMaxHtml.substr(posMaxHtml.indexOf('/') + 2, 2);
        jQuery('.shiplisting-images-position').html(isItemInArray(_boatImages, jQuery(this).find('img').attr('src')) + ' / ' + posMaxHtml);
        //}
    });

    jQuery('.shiplisting-images-current').on('click', function (e) {
        e.preventDefault();
        var current_modal = jQuery('.shiplisting-modal-window');

        jQuery('.shiplisting-images-slider-item.current').trigger('click');
        var html = current_modal.html();
        current_modal.remove();
        jQuery('<div class="shiplisting-modal-window rounded-corners-5">' + html + '</div>').appendTo(document.body);

        jQuery('.shiplisting-modal-slider-item').on('click', function (e) {
            e.preventDefault();
            if (!jQuery(this).hasClass('current')) {
                jQuery('.shiplisting-modal-slider-item.current').removeClass('current');

                if (jQuery(this).hasClass('video')) {
                    jQuery('.shiplisting-modal-current-img img').css('display', 'none');
                    var videoUrl = 'https://www.youtube-nocookie.com/embed/{video_id}?rel=0&showinfo=0';
                    videoUrl = videoUrl.replace('{video_id}', jQuery(this).attr('video-id'));
                    if (jQuery('.shiplisting-modal-current-img').find('iframe') !== undefined) {
                        jQuery('.shiplisting-modal-current-img').append('<iframe type="text/html" width="100%" height="100%" src="' + videoUrl + '" frameborder="0" allowfullscreen></iframe> ');
                    } else {
                        jQuery('.shiplisting-modal-current-img').find('iframe').attr('src', videoUrl);
                    }
                } else {
                    jQuery('.shiplisting-modal-current-img img').css('display', 'initial');
                    jQuery('.shiplisting-modal-current-img iframe').css('display', 'none');

                    var imgSrc = jQuery(this).find('img').attr('src');
                    imgSrc = imgSrc.replace('list_', 'huge_');
                    jQuery('.shiplisting-modal-current-img img').attr('src', imgSrc);
                    jQuery(this).addClass('current');
                }
            }
        });

        jQuery('.shiplisting-modal-close').on('click', function (e) {
            e.preventDefault();

            if (jQuery('.shiplisting-modal-current-img').find('iframe').length > 0) {
                jQuery('.shiplisting-modal-current-img').find('iframe').remove();
            }
            jQuery('.shiplisting-modal-window').hide();
        });

        jQuery('.shiplisting-modal-window').show();
    });

    jQuery('.shiplisting-modal-window:visible').on('swipeleft', function () {
        changeImage(true);
    });

    jQuery('.shiplisting-modal-window:visible').on('swiperight', function () {
        changeImage(false);
    });

    jQuery(document).keyup(function (e) {
        if (jQuery('.shiplisting-modal-window:visible') !== undefined) {
            if (e.keyCode === 27) {
                if (jQuery('.shiplisting-modal-current-img').find('iframe').length > 0) {
                    jQuery('.shiplisting-modal-current-img').find('iframe').remove();
                }
                jQuery('.shiplisting-modal-window').hide();
            }
            if (e.keyCode === 37) {
                changeImage(false);
            }
            if (e.keyCode === 39) {
                changeImage(true);
            }
        }
    });

    function changeImage(next = true) {
        var currentImg = jQuery('.shiplisting-modal-slider-item.current');
        var newImg = null;

        if (next) {
            newImg = currentImg.next();
            if (newImg.length === 0) {
                newImg = jQuery('.shiplisting-modal-slider-item:first-child');
            }
        } else {
            newImg = currentImg.prev();
            if (newImg.length === 0) {
                newImg = jQuery('.shiplisting-modal-slider-item:last-child');
            }
        }

        currentImg.removeClass('current');
        var imgSrc = newImg.find('img').attr('src');
        imgSrc = imgSrc.replace('list_', 'huge_');
        jQuery('.shiplisting-modal-current-img img').attr('src', imgSrc);
        newImg.addClass('current');

        if (newImg.hasClass('video')) {
            jQuery('.shiplisting-modal-current-img img').css('display', 'none');
            var videoUrl = 'https://www.youtube-nocookie.com/embed/{video_id}?rel=0&showinfo=0';
            videoUrl = videoUrl.replace('{video_id}', newImg.attr('video-id'));
            if (jQuery('.shiplisting-modal-current-img').find('iframe').length > 0) {
                jQuery('.shiplisting-modal-current-img').find('iframe').remove();
                jQuery('.shiplisting-modal-current-img').append('<iframe type="text/html" width="100%" height="100%" src="' + videoUrl + '" frameborder="0" allowfullscreen></iframe> ');
            } else {
                jQuery('.shiplisting-modal-current-img').find('iframe').attr('src', videoUrl);
            }
        } else {
            jQuery('.shiplisting-modal-current-img img').css('display', 'initial');
            jQuery('.shiplisting-modal-current-img iframe').css('display', 'none');

            jQuery('.shiplisting-modal-current-img img').attr('src', imgSrc);

            jQuery('.shiplisting-modal-slider-item.current').removeClass('current');
            jQuery('.shiplisting-modal-slider-item img[src="' + newImg.find('img').attr('src') + '"]').parent().addClass('current');
        }
    }

    jQuery('.shiplisting-boatdata-details-value').each(function (key, val) {
        if (jQuery(val).html().length < 1 || jQuery(val).text().trim() === '/' || jQuery(val).text().trim().length === 0) {
            if (jQuery(val).parent().hasClass('mb-25')) {
                jQuery(this).parent().removeClass('mb-25');
                jQuery(this).parent().next(':visible').addClass('mb-25');
            }
            jQuery(val).parent().css('display', 'none');
        }
    });

    jQuery('.shiplisting-boat-equipment.ajax-toggler .shiplisting-equipment-item-wrapper h3').on('click', function (e) {
        e.preventDefault();
        jQuery(this).parent().find('.shiplisting-equipment-item-text').slideToggle();
    });

    (function () {
        // Your base, I'm in it!
        var originalAddClassMethod = jQuery.fn.addClass;
        var originalRemoveClassMethod = jQuery.fn.removeClass;

        jQuery.fn.addClass = function () {
            // Execute the original method.
            var result = originalAddClassMethod.apply(this, arguments);

            // trigger a custom event
            jQuery(this).trigger('cssClassAdded');

            // return the original result
            return result;
        };

        jQuery.fn.removeClass = function () {
            // Execute the original method.
            var result = originalRemoveClassMethod.apply(this, arguments);

            // trigger a custom event
            jQuery(this).trigger('cssClassRemoved');

            // return the original result
            return result;
        };
    })();

    jQuery('div.shiplisting-boat-equipment-wrapper div.shiplisting-boat-equipment').bind('cssClassAdded', function () {
        if (jQuery(this).hasClass('toggled')) {
            jQuery('div.shiplisting-boat-equipment-wrapper div.shiplisting-boat-equipment h2 span').text('[ ' + jQuery('.shiplisting-boat-equipment h2 span').attr('i18n-close') + ' ]');
            jQuery('div.shiplisting-equipment-item-wrapper div.shiplisting-equipment-item-text:not(:visible)').each(function () {
                jQuery(this).slideDown('normal');
            });
        }
    });

    jQuery('div.shiplisting-boat-equipment-wrapper div.shiplisting-boat-equipment').bind('cssClassRemoved', function (args) {
        if (!jQuery(this).hasClass('toggled')) {
            jQuery('div.shiplisting-boat-equipment-wrapper div.shiplisting-boat-equipment h2 span').text('[ ' + jQuery('.shiplisting-boat-equipment h2 span').attr('i18n-open') + ' ]');
            jQuery('div.shiplisting-equipment-item-wrapper div.shiplisting-equipment-item-text:visible').each(function () {
                jQuery(this).slideUp('normal');
            });
        }
    });

    jQuery('.shiplisting-boat-equipment h2 span').on('click', function (e) {
        e.preventDefault();
        var content = jQuery('div.shiplisting-boat-equipment-wrapper div.shiplisting-boat-equipment');

        if (content.hasClass('toggled')) {
            content.removeClass('toggled');
        } else {
            content.addClass('toggled');
        }
    });

    jQuery('#shiplisting-sale-contact-submit').on('click', function (event) {
        event.preventDefault();

        var cname = (jQuery('#shiplisting-sale-contact-name').val()) ? jQuery('#shiplisting-sale-contact-name').val() : "";
        var cemail = jQuery('#shiplisting-sale-contact-email').val();
        var cphone = (jQuery('#shiplisting-sale-contact-telephone').val()) ? jQuery('#shiplisting-sale-contact-telephone').val() : "";
        var caddress = (jQuery('#shiplisting-sale-contact-caddress').val()) ? jQuery('#shiplisting-sale-contact-caddress').val() : "";
        var cpostcode = (jQuery('#shiplisting-sale-contact-postcode').val()) ? jQuery('#shiplisting-sale-contact-postcode').val() : "";
        var ccity = (jQuery('#shiplisting-sale-contact-city').val()) ? jQuery('#shiplisting-sale-contact-city').val() : "";
        var ccountry = jQuery('#shiplisting-sale-contact-countries option:selected').val();
        var chtime = (jQuery('#shiplisting-sale-contact-chtime') !== undefined) ? jQuery('#shiplisting-sale-contact-chtime').val() : "";
        var ctext = jQuery('#shiplisting-sale-contact-message').val();

        var boat_id = jQuery('#shiplisting-boat_id').val();

        function checkForm() {
            if (cname && cemail && ccountry && ctext) {
                if (jQuery('#shiplisting-sale-contact-chtime').length > 0) {
                    if (chtime) {
                        return true;
                    }
                }
                return true;
            } else {
                return false;
            }
        }

        var data = {
            'cname': cname,
            'cemail': cemail,
            'cphone': cphone,
            'caddress': caddress,
            'cpostcode': cpostcode,
            'ccity': ccity,
            'ccountry': ccountry,
            'chTime': chtime,
            'ctext': ctext,

            'boatId': boat_id,
            'view': window.location.href,
            'ip': jQuery('#shiplisting-hidden-value').val(),
            'domain': window.location.host,
            'kind': 'request',
            'formSent': 1
        };

        var i18n_error = jQuery('div.shiplisting-sale-contact-form.enabled div.shiplisting-sale-alert').attr('i18n-required');
        if (!checkForm()) {
            alert(i18n_error);
            return;
        }

        var post = jQuery.post(window.location, data, function (returnData) {
            jQuery('.shiplisting-sale-alert').slideUp(400, function () {
                jQuery(this).removeClass('info');

                returnData = JSON.parse(returnData);
                if (returnData) {
                    if (returnData['ok'] === 1) {
                        jQuery(this).addClass('success');
                        jQuery(this).text(jQuery(this).attr('i18n-success'));
                        jQuery('div.shiplisting-sale-contact-form.enabled div.shiplisting-sale-form-row').slideUp(400, function () {
                            jQuery('div.shiplisting-sale-contact-form.enabled div.shiplisting-sale-form-row').remove();
                        });
                    } else if (returnData['errors']['errMsg'].length > 0) {
                        jQuery(this).addClass('error');
                        var errorMsgs = '';
                        jQuery.each(returnData['errors']['errMsg'], function (key, val) {
                            errorMsgs += val + '<br>';
                        });
                        jQuery(this).html(errorMsgs);
                    }

                    jQuery(this).slideDown(400);
                }
            });
        });
    });

    function getUrlVars() {
        var vars = {};
        var parts = window.location.href.replace(/[?&]+([^=&]+)=([^&]*)/gi, function (m, key, value) {
            vars[key] = value;
        });
        return vars;
    }

    function post_filter_options() {
        var postArray = [];
        var postUrl = "";
        jQuery.each(jQuery('#shiplisting-boats-filtering input[type=text], #shiplisting-boats-filtering select'), function (key, val) {
            if (jQuery(val).val() !== '') {
                if (jQuery(val).val() !== '-1' && jQuery(val).val() !== '-1') {
                    if (jQuery(val).val() !== '-1') {
                        postArray[jQuery(val)[0].attributes['id'].value] = jQuery(val).val();
                        postUrl += '&' + jQuery(val)[0].attributes['id'].value + '=' + jQuery(val).val();
                    }
                }
            }
        });
        var currentPage = '?pg=1';
        var tmpuri = window.location.href.split('?')[0];
        if (tmpuri.slice(-1) !== '/') {
            tmpuri += '/';
        }
        window.location.href = tmpuri + currentPage + postUrl;
    }

    jQuery('form#shiplisting-boats-filtering').on('change', function () {
        post_filter_options();
    });
    jQuery('.quick-search #shiplisting-filter-column-button').on('click', function (event) {
        event.preventDefault();
        post_filter_options();
    });
    jQuery('form#shiplisting-boats-filtering').on('submit', function (event) {
        event.preventDefault();
        post_filter_options();
    });
    jQuery('form#shiplisting-boats-filtering a.shiplisting-filter-reset-link').on('click', function (e) {
        jQuery('form#shiplisting-boats-filtering select').each(function () {
            jQuery(this).find('option:eq(0)').prop('selected', true);
        });
        jQuery('form#shiplisting-boats-filtering').trigger('change');
    });

    function select_contains_value(select, value, urlkey) {
        if (jQuery(select).html().indexOf('value="' + value + '"') && (getUrlVars()[urlkey] !== undefined)) {
            jQuery(select).val(getUrlVars()[urlkey]);
        }
        return false;
    }

    function update_filter_options_with_uri() {
        jQuery.each(jQuery('form#shiplisting-boats-filtering div.shiplisting-filter-column'), function (key, value) {
            var url_var = null;

            // get select id
            var select_id = (jQuery(this).find('div.shiplisting-filter-column-content select')[0] !== undefined) ? jQuery(this).find('div.shiplisting-filter-column-content select')[0].id : null;
            if (jQuery(this).find('div.shiplisting-filter-column-content').hasClass('double')) {
                url_var = [
                    select_id.substring(0, select_id.length - 1) + 'f',
                    select_id.substring(0, select_id.length - 1) + 't'
                ];
            } else {
                url_var = select_id;
            }

            // not select, its fulltext
            if (url_var === null) {
                url_var = (jQuery(this).find('div.shiplisting-filter-column-content input[type=text]')[0] !== undefined) ? jQuery(this).find('div.shiplisting-filter-column-content input[type=text]')[0].id : null;
            }

            // we're ready
            if (url_var) {
                var tmp_url_var = url_var;
                // check if we got an array of presidents
                if (Array.isArray(tmp_url_var)) {
                    // check for from to
                    jQuery.each(tmp_url_var, function (key, value) {
                        if (getUrlVars()[value] !== undefined) {
                            // we're present with uri boyka
                            select_contains_value('#' + value, getUrlVars()[value], value);
                        }
                    });
                }

                // check if we're mr president
                var is_present = (getUrlVars()[tmp_url_var] !== undefined) ? getUrlVars()[tmp_url_var] : false;
                if (is_present !== false) {
                    // we're present with uri boyka
                    select_contains_value('#' + tmp_url_var, getUrlVars()[tmp_url_var], tmp_url_var);
                }
            }
        });
    }

    update_filter_options_with_uri();

    if (jQuery('div.shiplisting-boats.adv-filtering div.shiplisting-boats-filtering div.shiplisting-boats-filter').length > 0) {
        if (window.matchMedia("(max-width: 767px)").matches === false) {
            jQuery(window).bind('scroll', function () {
                var currentScrollTop = jQuery(window).scrollTop();
                var filter = jQuery('div.shiplisting-boats.adv-filtering div.shiplisting-boats-filtering div.shiplisting-boats-filter');
                var filterPosTop = jQuery('div.shiplisting-boats.adv-filtering div.shiplisting-boats-filtering').offset().top;
                var navigationFixedTop = jQuery('div.navigation-top.site-navigation-fixed').height();
                var topPadding = 15;

                if (filter === undefined) {
                    return;
                }

                if (filterPosTop.length !== 0 || currentScrollTop.length !== 0) {
                    if (navigationFixedTop !== undefined) {
                        filterPosTop = (filterPosTop - navigationFixedTop);
                    }

                    if (currentScrollTop >= (filterPosTop - topPadding)) {
                        filter.css({'position': 'fixed', 'width': filter.css('width')});
                        if (navigationFixedTop !== undefined) {
                            filter.css({
                                'top': (navigationFixedTop + 45) + 'px'
                            });
                        } else {
                            filter.css({
                                'top': '115px'
                            });
                        }

                    } else {
                        filter.css({'position': '', 'width': filter.css('width'), 'top': ''});
                    }
                }
            });
        }
    }

    String.prototype.trunc =
        function (n, useWordBoundary) {
            if (this.length <= n) {
                return this;
            }
            var subString = this.substr(0, n - 1);
            return (useWordBoundary
                ? subString.substr(0, subString.lastIndexOf(' '))
                : subString) + "&hellip;";
        };

    jQuery.each(jQuery('div.shiplisting-boats-object > div.shiplisting-object-details > div.shiplisting-object-detail span.shiplisting-object-value'), function (key, object) {
        if (jQuery(this).text().length === 0) {
            jQuery(this).parent().remove();
        } else {
            jQuery(this).html(jQuery(this).html().trunc(100, true));
        }
    });
});