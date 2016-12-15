$.extend($.FroalaEditor.QUICK_INSERT_BUTTONS, {
    insertLinkEntry: {
        icon: 'insertLinkEntry',
        title: 'Insert Link',
        callback: function () {
            this.quickInsert.hide();

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
    }
});