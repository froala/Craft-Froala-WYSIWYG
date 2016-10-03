$.FroalaEditor.DefineIcon('linkEntry', { NAME: 'search' });
$.FroalaEditor.RegisterCommand('linkEntry', {
    title: 'Choose Link',
    focus: true,
    refreshAfterCallback: true,
    callback: function (cmd, val) {
        var currentLocale = Craft.getLocalStorage('BaseElementIndex.locale') || Craft.locale;
        var editor = this;

        var modal = Craft.createElementSelectorModal('Entry', {
            criteria: { locale: currentLocale },
            onSelect: $.proxy(function (elements) {
                if (elements.length) {

                    var element = elements[0];
                    var url = element.url + '#entry:' + element.id;
                    var urlLabel = element.label;

                    // when a text already is selected
                    var selectedText = editor.selection.get();
                    if (selectedText.length > 0) {
                        urlLabel = selectedText;
                    }

                    // insert link
                    editor.link.insert(url, urlLabel);

                    return true;
                }
            }, this),
            closeOtherModals: false
        });
    }
});