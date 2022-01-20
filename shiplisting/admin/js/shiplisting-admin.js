(function ($) {
    'use strict';

    /**
     * All of the code for your admin-facing JavaScript source
     * should reside in this file.
     *
     * Note: It has been assumed you will write jQuery code here, so the
     * $ function reference has been prepared for usage within the scope
     * of this function.
     *
     * This enables you to define handlers, for when the DOM is ready:
     *
     * $(function() {
     *
     * });
     *
     * When the window is loaded:
     *
     * $( window ).load(function() {
     *
     * });
     *
     * ...and/or other possibilities.
     *
     * Ideally, it is not considered best practise to attach more than a
     * single DOM-ready or window-load handler for a particular page.
     * Although scripts in the WordPress core, Plugins and Themes may be
     * practising this, we should strive to set a better example in our own work.
     */

    $(function () {
        function callback_admin_toggle() {
            var link = $(this);
            var content_section_id = $(this).attr('content-section');
            var content = $('#' + content_section_id);

            if (content.length > 0) {
                if (!content.hasClass('active')) {
                    link.text('Hide section');
                    content.slideDown(300, function () {
                        $(this).addClass('active');
                    });
                } else {
                    link.text('Show section');
                    content.slideUp(300, function () {
                        $(this).removeClass('active');
                    });
                }
            }
        }

        var shiplisting_admin = {
            callbacks: {
                'admin_toggle': {
                    0: $('.shiplisting-admin-toggle a'),
                    1: 'click',
                    2: callback_admin_toggle
                },
            },

            init: function () {
                // check page
                if (!this.check_page()) {
                    return;
                }

                //init sections
                this.init_sections();
            },

            init_sections: function () {
                // init callbacks
                $.each(this.callbacks, function (key, callback) {
                    if (callback[0].length > 0 && callback[1].length > 0 && callback[2] !== undefined) {
                        $(callback[0]).on(callback[1], callback[2]);
                    }
                });
            },

            check_page: function () {
                if (window.location.href.indexOf('admin.php') > 0) {
                    return true;
                }
            }
        };

        shiplisting_admin.init();
    });
})(jQuery);
