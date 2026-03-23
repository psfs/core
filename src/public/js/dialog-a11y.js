(function (window, $) {
    'use strict';

    if (!window.bootbox || !$ || !$.fn || !$.fn.modal || typeof window.bootbox.dialog !== 'function') {
        return;
    }

    var originalDialog = window.bootbox.dialog;
    var dialogId = 0;

    function nextId(prefix) {
        dialogId += 1;
        return prefix + dialogId;
    }

    function ensureAriaAttributes(dialog) {
        var $dialog = $(dialog);
        var $title = $dialog.find('.modal-title').first();
        var $body = $dialog.find('.bootbox-body, .bootbox-prompt-message').first();

        $dialog.attr({
            'aria-modal': 'true',
            'aria-hidden': 'false',
            'role': 'dialog'
        });

        if ($title.length) {
            if (!$title.attr('id')) {
                $title.attr('id', nextId('bootbox-title-'));
            }
            $dialog.attr('aria-labelledby', $title.attr('id'));
        } else {
            $dialog.removeAttr('aria-labelledby');
        }

        if ($body.length) {
            if (!$body.attr('id')) {
                $body.attr('id', nextId('bootbox-body-'));
            }
            $dialog.attr('aria-describedby', $body.attr('id'));
        } else {
            $dialog.removeAttr('aria-describedby');
        }
    }

    window.bootbox.dialog = function (options) {
        var dialogOptions = options;

        if ($.isPlainObject(dialogOptions)) {
            dialogOptions = $.extend({}, dialogOptions);

            if (dialogOptions.onEscape == null) {
                dialogOptions.onEscape = true;
            }

            var userOnShown = dialogOptions.onShown;
            var userOnHidden = dialogOptions.onHidden;
            var userOnHide = dialogOptions.onHide;

            dialogOptions.onShown = function () {
                ensureAriaAttributes(this);

                if ($.isFunction(userOnShown)) {
                    return userOnShown.apply(this, arguments);
                }
            };
            dialogOptions.onHidden = function () {
                $(this).attr('aria-hidden', 'true');

                if ($.isFunction(userOnHidden)) {
                    return userOnHidden.apply(this, arguments);
                }
            };
            dialogOptions.onHide = function () {
                $(this).attr('aria-hidden', 'true');

                if ($.isFunction(userOnHide)) {
                    return userOnHide.apply(this, arguments);
                }
            };
        }

        return originalDialog.call(window.bootbox, dialogOptions);
    };
}(window, window.jQuery));
