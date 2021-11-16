"use strict";

(function ($) {

    let $window = $(window),
        $document = $('document'),
        $body = $("body");

    $document.ready(function () {

        $(".wpfs-collapse-handler").on("click", function () {
            let $this = $(this);
            $this.children('.wpfs-collapse-icon').toggleClass('wpfs-collapse-icon-close');
            $this.next().toggle(300);
        });
    });

    $window.on('beforeunload', function (e) {
        if ($body.hasClass('wpfs-doingAction')) {
            (e || window.event).returnValue = WPFS.strings.text_close_warning;
            return WPFS.strings.text_close_warning;
        }
    });

})(jQuery);