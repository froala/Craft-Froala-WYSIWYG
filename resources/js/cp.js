$.FroalaEditor.DefineIcon('linkEntry', { NAME: 'search' });
$.FroalaEditor.RegisterCommand('linkEntry', {
    title: 'Choose Link',
    focus: true,
    refreshAfterCallback: true,
    callback: function (cmd, val) {
        var currentLocale = (Craft.getLocalStorage('BaseElementIndex.locale') || Craft.locale),
            _editor = this,
            _selectedText = (this.selection.text() || false);

        var modal = Craft.createElementSelectorModal('Entry', {
            criteria: { locale: currentLocale },
            onSelect: $.proxy(function (elements) {
                if (elements.length) {

                    var element = elements[0];
                    var url = element.url + '#entry:' + element.id;
                    var urlLabel = _selectedText || element.label;

                    if (_selectedText !== false) {
                        _editor.html.insert('<a href="' + url + '">' + urlLabel + '</a>');
                    } else {
                        _editor.link.insert(url, urlLabel);
                    }

                    return true;
                }
            }, this),
            closeOtherModals: false
        });
    }
});