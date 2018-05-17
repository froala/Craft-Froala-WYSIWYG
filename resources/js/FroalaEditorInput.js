(function($) {

    /** global: Craft */
    /** global: Garnish */
    /**
     * Froala Editor input class
     */
    Craft.FroalaEditorInput = Craft.FroalaEditorConfig.extend({
        afterInit: function() {
            this.id = this.settings.id;

            // Initialize Froala
            this.$textarea = $('#' + this.id);

            this.initEditor();
        },
        initEditor: function() {
            Craft.FroalaEditorInput.currentInstance = this;

            this.$textarea.froalaEditor(this.config);

            delete Craft.FroalaEditorInput.currentInstance;
        }
    });

})(jQuery);