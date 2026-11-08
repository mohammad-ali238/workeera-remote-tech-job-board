/**
 * CV manager — upload, list, and delete candidate CV files via AJAX.
 */
(function ($) {
    'use strict';

    $(document).ready(function() {
        var $wrap = $('.workeera-cvmanager-files-wrap');

        // Upload button triggers file input
        $wrap.on('click', '.workeera-cvmanager-upload-btn', function() {
            $wrap.find('.workeera-cvmanager-file-input').trigger('click');
        });

        // File input change -> upload
        $wrap.on('change', '.workeera-cvmanager-file-input', function() {
            var file = this.files[0];
            if (file) {
                $.workeera_cv_upload_file(file, $wrap);
            }
        });

        // Add more button
        $wrap.on('click', '.workeera-cvmanager-addmore-btn', function() {
            var currentCount = $wrap.find('.workeera-cvmanager-file-item').length;
            var maxFiles = parseInt(workeera_cv_manager.max_files);
            if (currentCount >= maxFiles) {
                $.workeera_submit_msg_alert({
                    msg: workeera_cv_manager.max_files_reached_msg,
                    class: 'workeera-alert-warning'
                });
                return;
            }
            $wrap.find('.workeera-cvmanager-file-input').trigger('click');
        });

        // Delete button
        $wrap.on('click', '.workeera-cvmanager-delete-btn', function() {
            var $btn = $(this);
            var fileId = $btn.data('file-id');

            $.workeera_confirm_box({
                title: workeera_cv_manager.delete_confirm_title,
                message: workeera_cv_manager.delete_confirm_msg,
                buttons: {
                    yes: {
                        title: workeera_cv_manager.delete_btn_label,
                        class: 'workeera-delitm-btn',
                        action: function() {
                            $.workeera_cv_delete_file(fileId, $btn, $wrap);
                        }
                    },
                    no: {
                        title: workeera_cv_manager.cancel_btn_label,
                        class: 'workeera-cancel-act',
                        action: function() {
                            $.workeera_confirm_box.hide();
                        }
                    }
                }
            });
        });
    });

    /**
     * Validate and upload a CV file via AJAX.
     *
     * @param {File}   file  Selected file from the input element.
     * @param {jQuery} $wrap CV manager wrapper element.
     */
    $.workeera_cv_upload_file = function(file, $wrap) {
        var maxSize = workeera_cv_manager.max_file_size_bytes;
        var allowedMimes = Object.values(workeera_cv_manager.allowed_mimes);

        if (file.size > maxSize) {
            $.workeera_submit_msg_alert({msg: workeera_cv_manager.upload_error_filesize, class: 'workeera-alert-danger'});
            return;
        }
        if (!allowedMimes.includes(file.type)) {
            $.workeera_submit_msg_alert({msg: workeera_cv_manager.upload_error_filetype, class: 'workeera-alert-danger'});
            return;
        }

        var $loader = $wrap.find('.workeera-cvmanager-loader');
        $loader.removeClass('workeera-hide');

        var formData = new FormData();
        formData.append('cv_file', file);
        formData.append('action', 'workeera_cv_upload');
        formData.append('workeera_nonce', workeera_cv_manager.nonce);

        $.ajax({
            url: workeera_cv_manager.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.error == '0') {
                    // Append HTML returned from PHP
                    $wrap.find('.workeera-cvmanager-files-list').append(response.item_html);
                    $.workeera_cv_update_ui($wrap, response.file_count);
                    $.workeera_submit_msg_alert({msg: workeera_cv_manager.upload_success, class: 'workeera-alert-success'});
                } else {
                    $.workeera_submit_msg_alert({msg: response.msg, class: 'workeera-alert-danger'});
                }
                $loader.addClass('workeera-hide');
                $wrap.find('.workeera-cvmanager-file-input').val('');
            },
            error: function() {
                $.workeera_submit_msg_alert({msg: workeera_cv_manager.upload_error_generic, class: 'workeera-alert-danger'});
                $loader.addClass('workeera-hide');
                $wrap.find('.workeera-cvmanager-file-input').val('');
            }
        });
    };

    /**
     * Delete a stored CV file via AJAX and remove the matching list item.
     *
     * @param {number} fileId Attachment ID of the file to delete.
     * @param {jQuery} $btn   Delete button that triggered the action.
     * @param {jQuery} $wrap  CV manager wrapper element.
     */
    $.workeera_cv_delete_file = function(fileId, $btn, $wrap) {
        var $loader = $wrap.find('.workeera-cvmanager-loader');
        $loader.removeClass('workeera-hide');

        $.ajax({
            url: workeera_cv_manager.ajax_url,
            type: 'POST',
            data: {
                action: 'workeera_cv_delete',
                file_id: fileId,
                workeera_nonce: workeera_cv_manager.nonce
            },
            dataType: 'json',
            success: function(response) {
                if (response.error == '0') {
                    $wrap.find('.workeera-cvmanager-file-item[data-file-id="' + fileId + '"]').remove();
                    $.workeera_cv_update_ui($wrap, response.file_count);
                    $.workeera_submit_msg_alert({msg: workeera_cv_manager.delete_success, class: 'workeera-alert-success'});
                    $.workeera_confirm_box.hide();
                } else {
                    $.workeera_submit_msg_alert({msg: response.msg, class: 'workeera-alert-danger'});
                }
                $loader.addClass('workeera-hide');
            },
            error: function() {
                $.workeera_submit_msg_alert({msg: workeera_cv_manager.upload_error_generic, class: 'workeera-alert-danger'});
                $loader.addClass('workeera-hide');
            }
        });
    };

    /**
     * Update the Add More button and upload box visibility based on file count.
     *
     * @param {jQuery} $wrap     CV manager wrapper element.
     * @param {number} fileCount Current number of uploaded CV files.
     */
    $.workeera_cv_update_ui = function($wrap, fileCount) {
        var maxFiles = parseInt(workeera_cv_manager.max_files);
        var $uploadBox = $wrap.find('.workeera-cvmanager-upload-box');
        var $addMoreWrap = $wrap.find('.workeera-cvmanager-addmore-wrap');

        // Show/hide upload box: visible only when zero files
        if (fileCount === 0) {
            $uploadBox.removeClass('workeera-hide');
        } else {
            $uploadBox.addClass('workeera-hide');
        }

        // Update add more area
        if (fileCount === 0) {
            $addMoreWrap.empty();
        } else if (fileCount < maxFiles) {
            $addMoreWrap.html('<button type="button" class="workeera-btn workeera-cvmanager-addmore-btn"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px"><line x1="12" y1="5" x2="12" y2="19" /><line x1="5" y1="12" x2="19" y2="12" /></svg>' + workeera_cv_manager.add_more_text + '</button>');
        } else {
            $addMoreWrap.html('<p class="workeera-cvmanager-max-msg">' + workeera_cv_manager.max_files_reached_msg + '</p>');
        }
    };

})(jQuery);