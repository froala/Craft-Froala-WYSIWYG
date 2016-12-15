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
            criteria: { locale: currentLocale, kind: ['excel', 'pdf', 'powerpoint', 'text', 'word'] },
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