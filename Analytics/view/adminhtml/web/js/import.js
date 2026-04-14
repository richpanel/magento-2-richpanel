/**
 * Import orders widget
 *
 * @author Shubhanshu Chouhan <shubhanshu@chouhan.com>
 */
define([
    'jquery',
    'mage/translate',
    'jquery/ui'
    ], function($, $t, $ui) {
        "use strict";
        $.widget('richpanel.import', {

            /**
             * Default options
             */
            options : {
                storeId: null,
                totalChunks: 0,
                percentage: 100,
                submitUrl: '',
                loaderImage: '', // TODO: Probably add loader image while importing
                messageSelector: '',
                duration: '-12 Months'
            },

            /**
             * Construtor method for widget
             *
             * @return {this}
             */
            _create: function() {
                this._bindSubmit();
                return this;
            },

            /**
             * Prevent default form submition
             *
             * @return {void}
             */
            _bindSubmit: function() {
                var self = this;
                let duration = jQuery("#richpanel_analytics_general_rp_duration").val();
                let initialChunk = 0;
                if (duration == 'resume') {
                    let localValue = localStorage.getItem("rpmagentosyncchunk");
                    if (isNaN(localValue) == false) {
                        initialChunk = Number.parseInt(localValue);
                    }
                }
                self.element.on('click', function(e) {
                    // Disable the button during the import
                    $(this).addClass('disabled').attr('disabled', 'disabled').text('Importing orders');

                    if(self.options.totalChunks > 0){
                        self.options.percentage = (100 / self.options.totalChunks);
                        self.chunkSync(initialChunk);
                    } else {
                        self.finishedImportingMessage();
                    }
                });
            },

            /**
             * Post chunk id to proceed orders to Richpanel
             *
             * @param  {integer} chunkId
             * @return {void}
             */
            chunkSync: function(chunkId) {
                var self = this;
                var progress = Math.round(chunkId * self.options.percentage);
                self.updateImportingMessage($t(`Please wait... ${progress}% (${chunkId*50}/${self.options.totalChunks*50}) done`), true);

                var data = {
                    'storeId': self.options.storeId,
                    'chunkId': chunkId,
                    'totalChunks': self.options.totalChunks,
                    'form_key': window.FORM_KEY,
                    'duration': self.options.duration
                };
                $.post(self.options.submitUrl, data, function(response) {
                    if (response.success) {
                        var newChunkId = chunkId + 1;
                        if(newChunkId < self.options.totalChunks) {
                            localStorage.setItem("rpmagentosyncchunk", chunkId);
                            setTimeout(function() {
                                self.chunkSync(newChunkId);
                            }, 100);
                        } else {
                            self.finishedImportingMessage();
                            localStorage.setItem("rpmagentosyncchunk", 0);
                        }
                    } else {
                        self.updateImportingMessage("<span style='color: red;'>" + response.message + "</span>");
                    }
                });
            },

            /**
             * Update progress message
             *
             * @param  {string} message
             * @return {void}
             */
            updateImportingMessage: function(message) {
                $(this.options.messageSelector).html(message);
            },

            /**
             * Finished progress message
             *
             * @return {void}
             */
            finishedImportingMessage: function() {
                this.updateImportingMessage("<span style='color: green;'>" + $t('Done! Please expect up to 30 minutes for your historical data to appear in Richpanel.') + "</span>");
                this.element.removeClass('disabled').addClass('success').text($t('Orders imported'));
            }

        });

        return $.richpanel.import;
    }
);
