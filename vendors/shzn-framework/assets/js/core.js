/**
 * @author    sh1zen
 * @copyright Copyright (C)  2021
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

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

function maybe_parse_json(str, check = false) {
    try {
        return JSON.parse(str);
    } catch (e) {
        return check ? false : str;
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

                let res = maybe_parse_json(jqXHR.responseText);

                if (!res)
                    res = jqXHR.responseText;

                if (typeof res.data !== 'undefined')
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

function shzn_popup(args) {

    let defargs = {
        header: '',
        body: '',
        footer: '',
        element: body
    }

    args = {...defargs, ...args}

    let $context = jQuery(args.element);

    $context.append(response)
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

        if (!($tabs instanceof jQuery)) {
            console.log("Error initializing shzn_tabHandler");
            return;
        }

        if ($tabs.length === 0)
            return;

        let $tab_list = $tabs.find(".shzn-ar-tablist");

        if ($tab_list.length === 0)
            return;

        let form_action = 'options.php';

        /**
         * Initialize aria attr
         */
        $tab_list.each(function () {

            let $this_tab_list = $(this),
                $this_tab_list_items = $this_tab_list.children(".shzn-ar-tab"),
                $this_tab_list_links = $this_tab_list.find(".shzn-ar-tab_link");

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
        $(".shzn-ar-tabcontent").attr({
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

            let $tab_content = $("#" + hash + ".shzn-ar-tabcontent");

            if ($tab_content.length !== 0) {

                if ($("#lbl_" + hash + ".shzn-ar-tab_link:not([aria-disabled='true'])").length) {

                    // display not disabled
                    $tab_content.removeAttr("aria-hidden");

                    // selection menu
                    $("#lbl_" + hash + ".shzn-ar-tab_link").attr({
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
                $tab_selected = $this.find('.shzn-ar-tab_link[aria-selected="true"]'),
                $first_link = $this.find('.shzn-ar-tab_link:not([aria-disabled="true"]):first'),
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
        $body.on("click", ".shzn-ar-tab_link[aria-disabled='true']", function (e) {
            e.preventDefault();
        });

        $body.on("click", ".shzn-ar-tab_link:not([aria-disabled='true'])", function (event) {

            let $this = $(this),
                $hash_to_update = $this.attr("aria-controls"),
                $tab_content_linked = $("#" + $this.attr("aria-controls")),
                $parent = $this.closest(".shzn-ar-tabs"),

                $all_tab_links = $parent.find(".shzn-ar-tab_link"),
                $all_tab_contents = $parent.find(".shzn-ar-tabcontent"),

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
        $body.on("keydown", ".shzn-ar-tablist", function (event) {

            let $parent = $(this).closest('.shzn-ar-tabs');

            // some event should be activated only if the focus is on tabs (not on tabpanel)
            if (!$(document.activeElement).is($parent.find('.shzn-ar-tab_link'))) {
                return;
            }

            // catch keyboard event only if focus is on tab
            if (!event.ctrlKey) {

                let $activated = $parent.find('.shzn-ar-tab_link[aria-selected="true"]').parent();

                // strike left in the tab
                if (event.keyCode === 37) {

                    let $last_link = $parent.find('.shzn-ar-tab:last-child .shzn-ar-tab_link'),
                        $prev = $activated;

                    // search valid previous
                    do {
                        // if we are on first => activate last
                        if ($prev.is(".shzn-ar-tab:first-child")) {
                            $prev = $last_link.parent();
                        }
                        // else previous
                        else {
                            $prev = $prev.prev();
                        }
                    }
                    while ($prev.children('.shzn-ar-tab_link').attr('aria-disabled') === 'true' && $prev !== $activated);

                    $prev.children(".shzn-ar-tab_link").click().focus();

                    event.preventDefault();
                }
                // strike  right in the tab
                else if (event.keyCode === 39) {

                    let $first_link = $parent.find('.shzn-ar-tab:first-child .shzn-ar-tab_link'),
                        $next = $activated;

                    // search valid next
                    do {
                        // if we are on last => activate first
                        if ($next.is(".shzn-ar-tab:last-child")) {
                            $next = $first_link.parent();
                        }
                        // else previous
                        else {
                            $next = $next.next();
                        }
                    }
                    while ($next.children('.shzn-ar-tab_link').attr('aria-disabled') === 'true' && $next !== $activated);

                    $next.children(".shzn-ar-tab_link").click().focus();

                    event.preventDefault();

                }
            }

        });
    };

    function handleDependent(parent, visible = true, deep = true) {

        $('.shzn *[data-parent*="' + parent + '"]').each(function () {
            let $this = $(this),
                cntx = $this,
                visibleAction = $this.data('parent').charAt(0) === "!" ? !visible : visible;

            if (!$this.hasClass('shzn-separator')) {
                cntx = $this.closest('tr');
            }

            if ($this.is('input')) {
                $this.prop("readonly", !visibleAction);
            }

            if (deep && this.id) {
                handleDependent(this.id, visible, deep)
            }

            visibleAction ? cntx.removeClass('shzn-disabled-blur') : cntx.addClass('shzn-disabled-blur');
        });
    }

    function createCircleChart(percent, color, size, stroke) {
        return `<svg class="shzn-progressbarCircle__chart" viewbox="0 0 36 36" width="${size}" height="${size}" xmlns="http://www.w3.org/2000/svg">
        <path class="shzn-progressbarCircle__bg" stroke="#eeeeee" stroke-width="${stroke * 0.5}" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
        <path class="shzn-progressbarCircle__stroke" stroke="${color}" stroke-width="${stroke}" stroke-dasharray="${percent},100" stroke-linecap="round" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
        <text class="shzn-progressbarCircle__info" x="50%" y="50%" alignment-baseline="central" text-anchor="middle" font-size="8">${percent}%</text></svg>`;
    }

    $document.ready(function () {

        let media_uploader;
        $(".shzn-uploader__init").on('click', function (e) {
            e.preventDefault();
            let btn_uploader = $(this);
            if (media_uploader) {
                media_uploader.open();
                return;
            }
            media_uploader = wp.media({
                title: 'Upload media',
                library: {type: btn_uploader.data('type') || 'image'},
                multiple: false
            }).on('select', function (e) {
                // This will return the selected media from the Media Uploader, the result is an object
                let uploaded_media = media_uploader.state().get('selection').first();
                // Convert uploaded_media to a JSON object to make accessing it easier
                let media_url = uploaded_media.toJSON().url;
                // Assign the url value to the input field
                btn_uploader.parent().find('input').val(media_url);
            }).open();
        });

        $('.shzn-progressbarCircle').each((i, chart) => {
            let $chart = $(chart);
            let percent = $chart.data("percent") || 0,
                color = $chart.data("color") || "var(--main-dark-color)",
                size = $chart.data("size") || 100,
                stroke = $chart.data("stroke") || 1;

            $chart.html(createCircleChart(percent, color, size, stroke));
        })

        $(".shzn-collapse-handler").on("click", function () {
            let $this = $(this);
            $this.children('.shzn-collapse-icon').toggleClass('shzn-collapse-icon-close');
            $this.next().toggle(300);
        });

        $(".shzn-dropdown__opener").on("click", function (event) {

            event.preventDefault();

            let $dropDown = $(this).closest(".shzn-dropdown");

            $dropDown.find(".shzn-multiselect__wrapper").slideToggle();
            $dropDown.toggleClass("is-open");

            $dropDown.off("click", "li");
            $dropDown.on("click", "li", function (e) {
                e.stopPropagation();
                let $selectedLI = $(this);

                $dropDown.find("input").val($selectedLI.data('value'));

                $dropDown.find(".shzn-multiselect__wrapper").slideToggle();
                $dropDown.toggleClass("is-open");
            });
        });

        shzn_tabHandler($('.shzn-ar-tabs'));

        $(".shzn-apple-switch").each(function () {

            if (!$(this).prop('checked')) {
                handleDependent(this.id, false)
            }

            $(this).on('click', function () {

                let $this = $(this);

                if ($this.prop("checked")) {

                    handleDependent(this.id)

                    let parent = $this.data('parent');

                    if (typeof parent !== 'undefined' && parent !== '') {

                        let $parent = $('#' + parent);

                        if (!$parent.prop("checked"))
                            $parent.prop("checked", true);
                    }
                } else {

                    handleDependent(this.id, false)
                }
            });
        });
    });


    $window.on('beforeunload', function (e) {
        if ($body.hasClass('shzn-doingAction')) {
            return SHZN.locale.text_close_warning || 'Are you sure you want to leave?';
        }
    });

})(jQuery);