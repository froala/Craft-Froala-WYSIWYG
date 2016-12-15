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
});

$.FroalaEditor.RegisterCommand('linkEdit', {
    callback: function (cmd, val) {
        var currentLocale = (Craft.getLocalStorage('BaseElementIndex.locale') || Craft.locale),
            _editor = this;

        var $current_link = this.link.get();

        var modal = Craft.createElementSelectorModal('Entry', {
            criteria: { locale: currentLocale },
            onSelect: $.proxy(function (elements) {
                if (elements.length) {

                    var element = elements[0];
                    var url = element.url + '#entry:' + element.id;

                    $current_link = $($current_link);
                    $current_link.attr('href', url);

                    return true;
                }
            }, this),
            closeOtherModals: false
        });
    }
});