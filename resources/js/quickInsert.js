/**
 * Quick inserting a Link to any Entry from Craft's content
 *
 * @see buttons.js
 */
$.extend($.FroalaEditor.QUICK_INSERT_BUTTONS, {
    insertLinkEntry: {
        icon: 'insertLinkEntry',
        title: 'Insert Link',
        callback: function() {
            this.quickInsert.hide();

            var currentLocale = (Craft.getLocalStorage('BaseElementIndex.locale') || Craft.locale),
                _editor = this,
                _selectedText = (this.selection.text() || false);

            // save selection before modal is shown
            this.selection.save();

            var modal = Craft.createElementSelectorModal('Entry', {
                criteria: { locale: currentLocale },
                onSelect: $.proxy(function (elements) {
                    if (elements.length) {

                        var element = elements[0];
                        var url = element.url + '#entry:' + element.id;
                        var urlLabel = _selectedText || element.label;

                        _editor.link.insert(url, urlLabel);

                        return true;
                    }
                }, this),
                closeOtherModals: false
            });
        }
    },
    insertAssetImage: {
        icon: 'insertAssetImage',
        title: 'Insert Image',
        callback: function() {
            this.quickInsert.hide();

            var currentLocale = (Craft.getLocalStorage('BaseElementIndex.locale') || Craft.locale);
            var _editor = this;

            // save selection before modal is shown
            this.selection.save();

            var modal = Craft.createElementSelectorModal('Asset', {
                criteria: { locale: currentLocale, kind: 'image' },
                multiSelect: true,
                sources: [
                    _editor.$oel[0].dataset.sourceImages
                ],
                onSelect: $.proxy(function (assets, transform) {
                    if (assets.length) {
                        for (var i = 0; i < assets.length; i++) {
                            var asset = assets[i],
                                url = asset.url + '#asset:' + asset.id;

                            if (transform) {
                                url += ':' + transform;
                            }

                            _editor.image.insert(url);
                        }

                        return true;
                    }
                }, this),
                closeOtherModals: false
            });
        }
    },
    insertAssetFile: {
        icon: 'insertAssetFile',
        title: 'Insert File',
        callback: function() {
            this.quickInsert.hide();

            var currentLocale = (Craft.getLocalStorage('BaseElementIndex.locale') || Craft.locale);
            var _editor = this;

            // save selection before modal is shown
            this.selection.save();

            var modal = Craft.createElementSelectorModal('Asset', {
                criteria: { locale: currentLocale },
                multiSelect: true,
                sources: [
                    _editor.$oel[0].dataset.sourceFiles
                ],
                onSelect: $.proxy(function (assets, transform) {
                    if (assets.length) {
                        for (var i = 0; i < assets.length; i++) {
                            var asset = assets[i];

                            var url = asset.url + '#asset:' + asset.id;
                            if (transform) {
                                url += ':' + transform;
                            }

                            _editor.file.insert(url, asset.label);
                        }

                        return true;
                    }
                }, this),
                closeOtherModals: false
            });
        }
    }
});