/* global jQuery */
/**
 * RealMe Sign-in widget
 *
 * @param  {[type]} document    Cache the top-level document
 * @param  {[type]} window      Cache a local window object.
 * @param  {[type]} jQuery      Cache a local jQuery object.
 * @return {Object} RM          A public API to the RealMe widget.
 */
// eslint-disable-next-line no-use-before-define
const RealMe = RealMe || (function (document, window, jQuery) {
    /**
     * Windows Phone with the mango update doesn't realise it has touchevents.
     * @type {Boolean}
     */
    const isIE9Mobile = navigator.userAgent.match(/(IEMobile\/9.0)/);
    const isIE6 = /\bMSIE 6/.test(navigator.userAgent) && !window.opera;

    if (isIE6) {
        return false;
    }

    /**
     * Internal namespace for RealMe
     * @type {Object}
     */
    const RM = {

        /**
         * Cache all our DOM elements
         * @return {void}
         */
        cacheElements() {
            this.$container = jQuery('.realme_widget');
            this.$trigger = jQuery('.whats_realme', this.$container);
            this.$modal = jQuery('.realme_popup', this.$container);
        },

        /**
         * Called when jQuery Document is ready.
         * Simple feature detection to determine if device is touch or not
         * @return {void}
         */
        init() {
            /**
             * Get all the elements when we init.
             */
            this.cacheElements();
            if ('ontouchstart' in document || isIE9Mobile !== null) {
                this.$container.addClass('touch');
                this.popup_window();
            } else {
                this.$container.addClass('no_touch');
                this.bind_no_touch();
            }
        },

        /**
         * use JS to prevent the href on the <a> from being followed in case user clicks instead of just hovers
         * @return {void}
         */
        bind_no_touch() {
            this.$trigger.on('click', (e) => {
                e.preventDefault();
            });
        },

        /**
         * [bind events for touch devices - add class to popup window to show / hide it
         * @param  {jQuery element} $elem
         * @return {void}
         */
        show_popup() {
            this.$modal.addClass('active');
        },

        /**
         * @param  {jQuery element} $elem
         * @return {void}
         */
        hide_popup() {
            this.$modal.removeClass('active');
        },

        /**
         * Popups up an information modal
         * @param  {jQuery element} $link
         * @param  {jQuery element} $modal
         * @return {void}
         */
        popup_window() {
            const me = this;

            this.$trigger.click(function (e) {
                if (this.$modal.hasClass('active')) {
                    me.hide_popup();
                } else {
                    me.show_popup();
                }
                e.stopPropagation();
            });

            this.$trigger.click(() => false);
        }
    };

    /**
     * Initialise RealMe widget
     * @return {[type]}
     */
    jQuery(document).ready(() => {
        RM.init();
    });

    return RM;
}(document, window, jQuery));
