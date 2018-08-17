(function ($) {

    /** global: Craft */
    /** global: Garnish */
    /**
     * Froala Editor input class
     */
    Craft.FroalaEditorConfig = Garnish.Base.extend({
        init: function (settings) {
            this.settings = settings;
            this.pluginSettings = this.settings.pluginSettings;
            this.fieldSettings = this.settings.fieldSettings;

            this.config = settings.editorConfig;
            this.config.key = this.pluginSettings.licenseKey;
            this.config.theme = 'craftcms';

            this.setContentContainer();
            this.assembleParagraphStyles();
            this.assembleEnabledPlugins();
            this.assembleToolbarButtons();
            this.clearCorePluginsEnabled();

            this.afterInit();
        },
        setContentContainer: function () {

            this.config.scrollableContainer = '#' + this.settings.id + '-field';
        },
        assembleParagraphStyles: function () {
            if (typeof this.config.paragraphStyles !== 'undefined') {
                return;
            }

            var list = {};

            var pluginRows = this.pluginSettings.customCssClasses;
            $.each(pluginRows, function (index, value) {
                list[value.className] = value.displayName;
            });

            this.config.paragraphStyles = list;
        },
        assembleEnabledPlugins: function () {
            if (typeof this.config.pluginsEnabled !== 'undefined') {
                return;
            }

            var list = [],
                enabledPlugins = this.pluginSettings.enabledPlugins;

            if (enabledPlugins !== '*' && $.isArray(enabledPlugins)) {
                for (var i = 0; i < enabledPlugins.length; i++) {
                    var camelCase = function (str) {
                        return str
                            // Replaces any - or _ characters with a space
                            .replace(/[-_]+/g, ' ')
                            // Removes any non alphanumeric characters
                            .replace(/[^\w\s]/g, '')
                            // Uppercases the first character in each group immediately following a space
                            // (delimited by spaces)
                            .replace(/ (.)/g, function ($1) {
                                return $1.toUpperCase();
                            })
                            // Removes spaces
                            .replace(/ /g, '');
                    };

                    list.push(camelCase(enabledPlugins[i]));
                }

                // always add code-view when allowed
                if (this.settings.allowCodeView) {
                    list.push('codeView');
                }

                // always add our own craft plugin extension
                list.push('craft');

                this.config.pluginsEnabled = list;
            }
        },
        assembleToolbarButtons: function () {
            if (typeof this.config.toolbarButtons === 'undefined') {
                this.config.toolbarButtons = this.getToolbarButtons('lg');
            }
            if (typeof this.config.toolbarButtonsMD === 'undefined') {
                this.config.toolbarButtonsMD = this.getToolbarButtons('md');
            }
            if (typeof this.config.toolbarButtonsSM === 'undefined') {
                this.config.toolbarButtonsSM = this.getToolbarButtons('sm');
            }
            if (typeof this.config.toolbarButtonsXS === 'undefined') {
                this.config.toolbarButtonsXS = this.getToolbarButtons('xs');
            }

            // disable quick insert for now
            this.config.quickInsertButtons = false;
            this.config.quickInsertTags = [''];
        },
        getToolbarButtons: function (size) {

            var buttons = [
                'fullscreen',
                'bold',
                'italic',
                'underline',
                'strikeThrough',
                'subscript',
                'superscript',
                '|',
                'undo',
                'redo',
                '|',
                'fontFamily',
                'fontSize',
                'color',
                'inlineStyle',
                'paragraphStyle',
                'paragraphFormat',
                '|',
                'align',
                'formatOL',
                'formatUL',
                'outdent',
                'indent',
                'quote',
                'insertHR',
                '-',
                'insertLink',
                'insertImage',
                'insertVideo',
                'embedly',
                'insertFile',
                'insertTable',
                '|',
                'selectAll',
                'clearFormatting',
                '|',
                'print',
                'spellChecker'
            ];

            switch (size) {
                case 'md':
                    buttons = [
                        'fullscreen',
                        'bold',
                        'italic',
                        'underline',
                        'strikeThrough',
                        '|',
                        'undo',
                        'redo',
                        '|',
                        'fontFamily',
                        'fontSize',
                        'color',
                        'paragraphStyle',
                        'paragraphFormat',
                        '|',
                        'align',
                        'formatOL',
                        'formatUL',
                        'outdent',
                        'indent',
                        'quote',
                        'insertHR',
                        '|',
                        'insertLink',
                        'insertImage',
                        'insertVideo',
                        'embedly',
                        'insertFile',
                        'insertTable',
                        '|',
                        'clearFormatting',
                        'spellChecker'
                    ];
                    break;
                case 'sm':
                    buttons = [
                        'fullscreen',
                        'bold',
                        'italic',
                        'underline',
                        'strikeThrough',
                        'undo',
                        'redo',
                        'fontFamily',
                        'fontSize',
                        'insertLink',
                        'insertImage',
                        'insertVideo',
                        'insertTable'
                    ];
                    break;
                case 'xs':
                    buttons = [
                        'bold',
                        'italic',
                        'undo',
                        'redo',
                        'insertLink',
                        'insertImage',
                        'insertVideo'
                    ];
                    break;
            }

            if (this.settings.allowCodeView) {
                buttons.push('html');
            }

            // Against enabled plugins
            // See https://www.froala.com/wysiwyg-editor/docs/options#toolbarButtons
            if (this.config.pluginsEnabled !== '*' && $.isArray(this.config.pluginsEnabled)) {
                for (var i = 0; i < buttons.length; i++) {
                    if (buttons[i] === '|' || buttons[i] === '-') {
                        continue;
                    }

                    var checkAgainst = buttons[i];
                    switch (checkAgainst) {
                        case 'color':
                            checkAgainst = 'colors';
                            break;
                        case 'formatOL':
                        case 'formatUL':
                            checkAgainst = 'lists';
                            break;
                        case 'insertFile':
                        case 'insertImage':
                        case 'insertLink':
                        case 'insertTable':
                        case 'insertVideo':
                            checkAgainst = checkAgainst.replace('insert', '');
                            checkAgainst = checkAgainst.toLowerCase();
                            break;
                        case 'html':
                            checkAgainst = 'codeView';
                            break;
                    }

                    if (this.config.pluginsEnabled.indexOf(checkAgainst) === -1) {
                        delete buttons[i];
                    }
                }
            }

            return buttons;
        },
        clearCorePluginsEnabled: function() {
            // create a new list of enabled plugins without the core ones.
            if (this.config.pluginsEnabled !== '*' && $.isArray(this.config.pluginsEnabled)) {
                var newList = [];
                for (var i = 0; i < this.config.pluginsEnabled.length; i++) {
                    var currentItem = this.config.pluginsEnabled[i];
                    if (!(currentItem in this.settings.corePlugins)) {
                        newList.push(currentItem);
                    }
                }

                this.config.pluginsEnabled = newList;
            }
        },

        afterInit: $.noop
    });

})(jQuery);