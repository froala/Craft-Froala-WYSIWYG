(function (factory) {
    if (typeof define === 'function' && define.amd) {
        // AMD. Register as an anonymous module.
        define(['jquery'], factory);
    } else if (typeof module === 'object' && module.exports) {
        // Node/CommonJS
        module.exports = function (root, jQuery) {
            if (jQuery === undefined) {
                // require('jQuery') returns a factory that requires window to
                // build a jQuery instance, we normalize how we use modules
                // that require this pattern but the window provided is a noop
                // if it's defined (how jquery works)
                if (typeof window !== 'undefined') {
                    jQuery = require('jquery');
                }
                else {
                    jQuery = require('jquery')(root);
                }
            }
            return factory(jQuery);
        };
    } else {
        // Browser globals
        factory(window.jQuery);
    }
}(function ($) {

    $.extend($.FE.DEFAULTS, {
        // general
        craftAssetElementType: false,
        craftAssetElementRefHandle: false,
        // links
        craftLinkCriteria: false,
        craftLinkSources: [],
        craftLinkStorageKey: false,
        craftLinkElementType: false,
        craftLinkElementRefHandle: false,
        // images
        craftImageCriteria: false,
        craftImageSources: [],
        craftImageStorageKey: false,
        craftImageTransforms: [],
        // files
        craftFileCriteria: false,
        craftFileSources: [],
        craftFileStorageKey: false
    });

    $.FE.PLUGINS.craft = function (editor) {

        function showEntrySelectModal() {
            var disabledElementIds = [],
                $popup = editor.popups.get('link.insert'),
                selectedText = (editor.selection.text() || false);

            // save selection before modal is shown
            var $currentImage = editor.image.get();
            if (!$currentImage) {
                editor.selection.save();
            }

            // check the src url containing '#{refhandle}:{id}[:{transform}]'
            var urlValue = $popup.find('input[name="href"]').val();
            if (urlValue && urlValue.indexOf('#') !== -1) {

                var hashValue = urlValue.substr(urlValue.indexOf('#'));
                hashValue = decodeURIComponent(hashValue);

                if (hashValue.indexOf(':') !== -1) {
                    disabledElementIds.push(hashValue.split(':')[1]);
                }
            }

            _elementModal(
                editor.opts.craftLinkElementType,
                editor.opts.craftLinkStorageKey,
                editor.opts.craftLinkSources,
                editor.opts.craftLinkCriteria,
                {
                    disabledElementIds: disabledElementIds,
                    transforms: editor.opts.craftImageTransforms
                },
                function(elements) {
                    if ($currentImage) {
                        editor.image.edit($currentImage);

                        // re-focus the popup
                        editor.popups.show('link.insert');
                    } else {
                        editor.selection.restore();

                        // re-focus the popup if not visible
                        if (!editor.popups.isVisible('link.insert')) {
                            editor.popups.show('link.insert');
                        }
                    }

                    // add-in element link details
                    if (elements.length) {
                        var element = elements[0],
                            url = element.url + '#' + editor.opts.craftLinkElementRefHandle + ':' + element.id,
                            title = selectedText.length > 0 ? selectedText : element.label;

                        $popup.find('input[name="href"]').val(url);

                        var currentText = $popup.find('input[name="text"]').val();
                        if (currentText.length === 0) {
                            $popup.find('input[name="text"]').val(title);
                        }
                    }
                }
            );
        }

        function showImageInsertModal() {
            // save selection before modal is shown
            editor.selection.save();

            _elementModal(
                editor.opts.craftAssetElementType,
                editor.opts.craftImageStorageKey,
                editor.opts.craftImageSources,
                editor.opts.craftImageCriteria,
                {
                    transforms: editor.opts.craftImageTransforms
                },
                function(assets, transform) {
                    if (assets.length) {
                        for (var i = 0; i < assets.length; i++) {
                            var asset = assets[i],
                                url = asset.url + '#' + editor.opts.craftAssetElementRefHandle + ':' + asset.id;

                            if (transform) {
                                url += ':' + transform;
                            }

                            editor.image.insert(url, false);
                        }

                        return true;
                    }
                }
            );
        }

        function showImageReplaceModal() {
            var disabledElementIds = [],
                $currentImage = editor.image.get();

            // check the src url containing '#{refhandle}:{id}[:{transform}]'
            if ($currentImage.attr('src').indexOf('#') !== -1) {

                var hashValue = $currentImage.attr('src').substr($currentImage.attr('src').indexOf('#'));
                hashValue = decodeURIComponent(hashValue);

                if (hashValue.indexOf(':') !== -1) {
                    disabledElementIds.push(hashValue.split(':')[1]);
                }
            }

            _elementModal(
                editor.opts.craftAssetElementType,
                editor.opts.craftImageStorageKey,
                editor.opts.craftImageSources,
                editor.opts.craftImageCriteria,
                {
                    disabledElementIds: disabledElementIds,
                    transforms: editor.opts.craftImageTransforms
                },
                function(assets, transform) {
                    if (assets.length) {
                        for (var i = 0; i < assets.length; i++) {
                            var asset = assets[i],
                                url = asset.url + '#' + editor.opts.craftAssetElementRefHandle + ':' + asset.id;

                            if (transform) {
                                url += ':' + transform;
                            }

                            editor.image.insert(url, false, [], $currentImage);
                        }

                        return true;
                    }
                }
            );
        }

        function showFileInsertModal(viaPopup) {
            var viaPopup = viaPopup || false,
                disabledElementIds = [],
                selectedText = (editor.selection.text() || false);

            if (viaPopup) {
                var $popup = editor.popups.get('link.insert');

                // check the src url containing '#asset:{id}[:{transform}]'
                var urlValue = $popup.find('input[name="href"]').val();
                if (urlValue && urlValue.indexOf('#') !== -1) {

                    var hashValue = urlValue.substr(urlValue.indexOf('#'));
                    hashValue = decodeURIComponent(hashValue);

                    if (hashValue.indexOf(':') !== -1) {
                        disabledElementIds.push(hashValue.split(':')[1]);
                    }
                }
            }

            // save selection before modal is shown
            editor.selection.save();

            _elementModal(
                editor.opts.craftAssetElementType,
                editor.opts.craftFileStorageKey,
                editor.opts.craftFileSources,
                editor.opts.craftFileCriteria,
                {
                    disabledElementIds: disabledElementIds
                },
                function(elements) {

                    // re-focus the popup
                    if (viaPopup && !editor.popups.isVisible('link.insert')) {
                        editor.popups.show('link.insert');
                    }

                    if (elements.length) {
                        var element = elements[0],
                            url = element.url + '#' + editor.opts.craftAssetElementRefHandle + ':' + element.id,
                            title = selectedText.length > 0 ? selectedText : element.label;

                        if (viaPopup) {
                            // no title replace at update
                            $popup.find('input[name="href"]').val(url);
                        } else {
                            editor.link.insert(url, title);
                        }

                        return true;
                    }
                }
            );
        }

        function _elementModal(type, storageKey, sources, criteria, addOpts, callback) {
            // Don't blur editor when opening elementModal
            editor.events.disableBlur();

            var modalOpts = {
                storageKey: (storageKey || 'Froala.Craft.Modal.' + type),
                sources: sources,
                criteria: criteria,
                onSelect: $.proxy(callback, editor),
                closeOtherModals: false
            };

            if (typeof addOpts !== 'undefined') {
                modalOpts = $.extend(modalOpts, addOpts);
            }

            var modal = Craft.createElementSelectorModal(type, modalOpts);
        }

        return {
            showEntrySelectModal: showEntrySelectModal,
            showImageInsertModal: showImageInsertModal,
            showImageReplaceModal: showImageReplaceModal,
            showFileInsertModal: showFileInsertModal
        }
    };

    /*
        LINK REPLACEMENTS & ADDITIONS
     */

    $.FE.DefineIcon('craftLinkEntry', { NAME: 'newspaper-o' });
    $.FE.RegisterCommand('craftLinkEntry', {
        title: 'Link to Craft Entry',
        focus: true,
        refreshOnCallback: true,
        callback: function () {
            this.craft.showEntrySelectModal();
        }
    });

    $.FE.DefineIcon('craftLinkAsset', { NAME: 'file-o' });
    $.FE.RegisterCommand('craftLinkAsset', {
        title: 'Link to Craft Asset',
        focus: true,
        refreshOnCallback: true,
        callback: function () {
            this.craft.showFileInsertModal(true);
        }
    });

    $.extend($.FE.DEFAULTS, {
        linkInsertButtons: ['craftLinkEntry','craftLinkAsset']
    });

    /*
        IMAGE REPLACEMENTS & ADDITIONS
     */

    $.FE.RegisterCommand('insertImage', $.extend($.FE.COMMANDS['insertImage'], {
        callback: function (cmd, val) {
            this.craft.showImageInsertModal();
        }
    }));

    $.FE.RegisterCommand('imageReplace', $.extend($.FE.COMMANDS['imageReplace'], {
        callback: function(cmd, val) {
            this.craft.showImageReplaceModal();
        }
    }));

    /*
        FILE REPLACEMENTS & ADDITIONS
     */

    $.FE.RegisterCommand('insertFile', $.extend($.FE.COMMANDS['insertFile'], {
        callback: function (cmd, val) {
            this.craft.showFileInsertModal();
        }
    }));

    /*
        SHORTCUT REPLACEMENT FOR CRAFT'S SAVE ACTION
     */

    $.FroalaEditor.RegisterShortcut($.FE.KEYCODE.S, null, null, null, false, false);

}));