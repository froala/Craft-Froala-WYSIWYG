/**
 * Inserting a Link to any Entry from Craft's content
 *
 * @see quickInsert.js
 */
$.FroalaEditor.RegisterCommand('insertLinkEntry', {
    title: 'Insert Link',
    focus: true,
    refreshAfterCallback: true,
    callback: function (cmd, val) {
        var currentLocale = (Craft.getLocalStorage('BaseElementIndex.locale') || Craft.locale),
            _editor = this,
            _selectedText = (this.selection.text() || false);

        // save selection before modal is shown
        this.selection.save();

        var modal = Craft.createElementSelectorModal('Entry', {
            criteria: {locale: currentLocale},
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
});

/**
 * Insert an Image
 */
$.FroalaEditor.RegisterCommand('insertAssetImage', {
    title: 'Insert Image',
    focus: true,
    refreshAfterCallback: true,
    callback: function (cmd, val) {
        var currentLocale = Craft.getLocalStorage('BaseElementIndex.locale') || Craft.locale;
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
});

/**
 * Insert a file
 */
$.FroalaEditor.RegisterCommand('insertAssetFile', {
    title: 'Insert File',
    focus: true,
    refreshAfterCallback: true,
    callback: function (cmd, val) {
        var currentLocale = Craft.getLocalStorage('BaseElementIndex.locale') || Craft.locale;
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
});