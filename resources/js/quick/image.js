$.extend($.FroalaEditor.QUICK_INSERT_BUTTONS, {
    insertAssetImage: {
        icon: 'insertAssetImage',
        title: 'Insert Image',
        callback: function () {
            this.quickInsert.hide();

            var currentLocale = (Craft.getLocalStorage('BaseElementIndex.locale') || Craft.locale);
            var _editor = this;

            // save selection before modal is shown
            this.selection.save();

            var modal = Craft.createElementSelectorModal('Asset', {
                criteria: {locale: currentLocale, kind: ['image']},
                multiSelect: true,
                canSelectImageTransforms: (typeof _froalaEditorTransforms != 'undefined'),
                transforms: (typeof _froalaEditorTransforms != 'undefined') ? _froalaEditorTransforms : false,
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
    }
});