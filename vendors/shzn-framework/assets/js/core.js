"use strict";

function SHZNSemaphore() {

    let list = [];

    this.release = function (context = 'def') {
        list[context] = false;
    }

    this.lock = function (context = 'def') {
        list[context] = true;
    }

    this.is_locked = function (context = 'def') {
        return list[context] === true;

    }
}

let shzn_semaphore = new SHZNSemaphore();

function shzn_is_json(str) {
    try {
        return JSON.parse(str);
    } catch (e) {
        return false;
    }
}

function shzn_ajaxHandler(options) {

    let defaults = {
        action: 'shzn',
        mod: 'none',
        mod_action: 'none',
        mod_nonce: '',
        mod_args: '',
        mod_form: '',
        use_loading: false,
        callback: null
    };

    options = Object.assign(defaults, options);

    shzn_semaphore.lock(options.mod_action);

    if (options.use_loading)
        options.use_loading.addClass("shzn-loader");

    jQuery.ajax({
        url: ajaxurl,
        type: "GET",
        dataType: "json",
        global: false,
        cache: false,
        data: {
            action: options.action,
            mod: options.mod,
            mod_action: options.mod_action,
            mod_nonce: options.mod_nonce,
            mod_args: options.mod_args,
            mod_form: options.mod_form,
        },
        complete: function (jqXHR, status) {

            if (typeof options.callback === "function") {

                let res = shzn_is_json(jqXHR.responseText);

                if(!res)
                    res = jqXHR.responseText;

                if(typeof res.data !== 'undefined')
                    setTimeout(options.callback(res.data, res.success), 100);
                else
                    setTimeout(options.callback(res, res.success), 100);
            }

            if (options.use_loading)
                options.use_loading.removeClass("shzn-loader");

            shzn_semaphore.release(options.mod_action);
        }
    });
}

(function ($) {

    let $window = $(window),
        $document = $('document'),
        $body = $("body");

    $.fn.shznNotice = function (response, status, locals) {

        let $this = $(this);

        if (status) {

            if (response.length > 0) {
                $this.append(response)
            } else
                $this.append("<p class='success'>" + locals.success + "</p>");

        } else {

            if (response.length > 0)
                $this.append("<p class='error'>" + response + "</p>")
            else
                $this.append("<p class='error'>" + locals.error + "</p>");
        }
    }

    let shzn_tabHandler = function ($tabs) {

        // Store current URL hash.
        let hash = window.location.hash.substring(1);

        if (!$tabs instanceof $) {
            console.log("Error initializing flex_tabHandler");
            return;
        }

        if ($tabs.length === 0)
            return;

        let $tab_list = $tabs.find(".ar-tablist");

        if ($tab_list.length === 0)
            return;

        let form_action = 'options.php';

        /**
         * Initialize aria attr
         */
        $tab_list.each(function () {

            let $this_tab_list = $(this),
                $this_tab_list_items = $this_tab_list.children(".ar-tab"),
                $this_tab_list_links = $this_tab_list.find(".ar-tab_link");

            // roles init
            $this_tab_list.attr("role", "tablist"); // ul
            $this_tab_list_items.attr("role", "presentation"); // li
            $this_tab_list_links.attr("role", "tab"); // a

            // controls/tabindex attributes
            $this_tab_list_links.each(function () {

                let $this = $(this),
                    $href = $this.attr("href");

                if (typeof $href !== "undefined" && $href !== "" && $href !== "#") {
                    $this.attr({
                        "aria-controls": $href.replace("#", ""),
                        "tabindex": -1,
                        "aria-selected": "false"
                    });
                }

                $this.removeAttr("href");
            });
        });

        /**
         * handle tab content
         */
        $(".ar-tabcontent").attr({
            "role": "tabpanel", // contents
            "aria-hidden": "true", // all hidden
            //"tabindex": -1
        }).each(function () {
            let $this = $(this), $this_id = $this.attr("id");
            // label by link
            $this.attr("aria-labelledby", "lbl_" + $this_id);
        });


        // search if hash is ON not disabled tab
        if (hash !== "") {

            let $tab_content = $("#" + hash + ".ar-tabcontent");

            if ($tab_content.length !== 0) {

                if ($("#lbl_" + hash + ".ar-tab_link:not([aria-disabled='true'])").length) {

                    // display not disabled
                    $tab_content.removeAttr("aria-hidden");

                    // selection menu
                    $("#lbl_" + hash + ".ar-tab_link").attr({
                        "aria-selected": "true",
                        "tabindex": 0
                    });

                    $tab_content.find('#shzn-uoptions').attr('action', form_action + '#' + hash);

                }
            }
        }

        // if no selected => select first not disabled
        $tabs.each(function () {
            let $this = $(this),
                $tab_selected = $this.find('.ar-tab_link[aria-selected="true"]'),
                $first_link = $this.find('.ar-tab_link:not([aria-disabled="true"]):first'),
                $first_content = $('#' + $first_link.attr('aria-controls'));

            if ($tab_selected.length === 0) {
                $first_link.attr({
                    "aria-selected": "true",
                    "tabindex": 0
                });
                $first_content.removeAttr("aria-hidden");
            }
        });

        /* Events ---------------------------------------------------------------------------------------------------------- */
        /* click on a tab link disabled */
        $body.on("click", ".ar-tab_link[aria-disabled='true']", function (e) {
            e.preventDefault();
        });

        $body.on("click", ".ar-tab_link:not([aria-disabled='true'])", function (event) {

            let $this = $(this),
                $hash_to_update = $this.attr("aria-controls"),
                $tab_content_linked = $("#" + $this.attr("aria-controls")),
                $parent = $this.closest(".ar-tabs"),

                $all_tab_links = $parent.find(".ar-tab_link"),
                $all_tab_contents = $parent.find(".ar-tabcontent"),

                $form = $tab_content_linked.find('#shzn-uoptions');

            // aria selected false on all links
            $all_tab_links.attr({
                "tabindex": -1,
                "aria-selected": "false"
            });

            // add aria selected on $this
            $this.attr({
                "aria-selected": "true",
                "tabindex": 0
            });

            // add aria-hidden on all tabs contents
            $all_tab_contents.attr("aria-hidden", "true");

            if (typeof $form !== 'undefined') {
                $form.attr('action', form_action + '#' + $hash_to_update);
            }

            // remove aria-hidden on tab linked
            $tab_content_linked.removeAttr("aria-hidden");

            setTimeout(function () {
                history.pushState(null, null, location.pathname + location.search + '#' + $hash_to_update)
            }, 300);

            event.preventDefault();
        });

        /* Key down in tabs */
        $body.on("keydown", ".ar-tablist", function (event) {

            let $parent = $(this).closest('.ar-tabs');

            // some event should be activated only if the focus is on tabs (not on tabpanel)
            if (!$(document.activeElement).is($parent.find('.ar-tab_link'))) {
                return;
            }

            // catch keyboard event only if focus is on tab
            if (!event.ctrlKey) {

                let $activated = $parent.find('.ar-tab_link[aria-selected="true"]').parent();

                // strike left in the tab
                if (event.keyCode === 37) {

                    let $last_link = $parent.find('.ar-tab:last-child .ar-tab_link'),
                        $prev = $activated;

                    // search valid previous
                    do {
                        // if we are on first => activate last
                        if ($prev.is(".ar-tab:first-child")) {
                            $prev = $last_link.parent();
                        }
                        // else previous
                        else {
                            $prev = $prev.prev();
                        }
                    }
                    while ($prev.children('.ar-tab_link').attr('aria-disabled') === 'true' && $prev !== $activated);

                    $prev.children(".ar-tab_link").click().focus();

                    event.preventDefault();
                }
                // strike  right in the tab
                else if (event.keyCode === 39) {

                    let $first_link = $parent.find('.ar-tab:first-child .ar-tab_link'),
                        $next = $activated;

                    // search valid next
                    do {
                        // if we are on last => activate first
                        if ($next.is(".ar-tab:last-child")) {
                            $next = $first_link.parent();
                        }
                        // else previous
                        else {
                            $next = $next.next();
                        }
                    }
                    while ($next.children('.ar-tab_link').attr('aria-disabled') === 'true' && $next !== $activated);

                    $next.children(".ar-tab_link").click().focus();

                    event.preventDefault();

                }
            }

        });
    };

    $document.ready(function () {

        $(".shzn-collapse-handler").on("click", function () {
            let $this = $(this);
            $this.children('.shzn-collapse-icon').toggleClass('shzn-collapse-icon-close');
            $this.next().toggle(300);
        });

        shzn_tabHandler($('.ar-tabs'));

        $(".shzn-apple-switch").each(function () {

            if (!$(this).prop('checked')) {
                $('input[data-parent="' + this.id + '"]').each(function () {
                    let $this = $(this);

                    $this.closest('tr').addClass('shzn-disabled-blur');
                    $this.prop("readonly", true);
                });
            }

            $(this).on('click', function () {

                let $this = $(this);

                if ($this.prop("checked")) {

                    $('input[data-parent="' + this.id + '"]').each(function () {
                        $(this).closest('tr').removeClass('shzn-disabled-blur');
                        $(this).prop("readonly", false);
                    });

                    let parent = $this.data('parent');

                    if (typeof parent !== 'undefined' && parent !== '') {

                        let $parent = $('#' + parent);

                        if (!$parent.prop("checked"))
                            $parent.prop("checked", true);
                    }
                } else {
                    $('input[data-parent="' + this.id + '"]').each(function () {
                        let $this = $(this);

                        $this.closest('tr').addClass('shzn-disabled-blur');
                        $this.prop("readonly", true);
                    });
                }
            });
        });
    });

    $window.on('beforeunload', function (e) {
        if ($body.hasClass('shzn-doingAction')) {
            (e || window.event).returnValue = SHZN.strings.text_close_warning;
            return SHZN.strings.text_close_warning;
        }
    });

})(jQuery);