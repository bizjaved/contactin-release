(function($) {
    'use strict';

    const ContactDeletion = {
        init() {
            if (typeof cinContactDeletion === 'undefined') {
                console.warn('ContactDeletion: cinContactDeletion localization not found');
                return;
            }
            $(document).on('click', '.cin-delete-contact-btn', this.handleDeleteClick.bind(this));
        },

        handleDeleteClick(e) {
            e.preventDefault();
            const contactId = $(e.currentTarget).data('contact-id');
            if (!contactId) return;
            this.getMessageCount(contactId);
        },

        getMessageCount(contactId) {
            const self = this;

            if (typeof cinContactDeletion === 'undefined') {
                this.showError('Contact deletion configuration not loaded. Please refresh the page.');
                return;
            }

            $.ajax({
                url: cinContactDeletion.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: cinContactDeletion.action_count,
                    contact_id: contactId,
                    nonce: this.getNonce(),
                },
                success(response) {
                    if (response.success) {
                        self.showConfirmationDialog(contactId, response.data.message_count);
                    } else {
                        self.showError(response.data?.message || cinContactDeletion.strings.error);
                    }
                },
                error() {
                    self.showError(cinContactDeletion.strings.error);
                },
            });
        },

        showConfirmationDialog(contactId, messageCount) {
            if (messageCount === 0) {
                this.showSimpleConfirmation(contactId);
            } else {
                this.showMessageConfirmation(contactId, messageCount);
            }
        },

        showSimpleConfirmation(contactId) {
            const confirmDelete = confirm(cinContactDeletion.strings.confirm_delete);
            if (confirmDelete) {
                this.deleteContact(contactId, false, null);
            }
        },

        showMessageConfirmation(contactId, messageCount) {
            const modal = this.buildModal(contactId, messageCount);
            $('body').append(modal);

            $(document).on('click', '.cin-contact-deletion-modal .cin-btn', (e) => {
                const $btn = $(e.currentTarget);

                if ($btn.hasClass('cin-btn-delete-all')) {
                    this.deleteContact(contactId, true, $btn);
                } else if ($btn.hasClass('cin-btn-cancel')) {
                    this.closeDeleteModal();
                }
            });
        },

        buildModal(contactId, messageCount) {
            const messageText = cinContactDeletion.strings.has_messages.replace('%d', messageCount);
            const html = `
                <div class="cin-contact-deletion-modal-overlay">
                    <div class="cin-contact-deletion-modal">
                        <div class="cin-modal-header">
                            <h2>${cinContactDeletion.strings.confirm_delete}</h2>
                        </div>
                        <div class="cin-modal-body">
                            <p>${messageText}</p>
                            <div class="cin-modal-actions">
                                <button type="button" class="cin-btn cin-btn-secondary cin-btn-cancel">
                                    ${cinContactDeletion.strings.cancel}
                                </button>
                                <button type="button" class="cin-btn cin-btn-danger cin-btn-delete-all">
                                    ${cinContactDeletion.strings.delete_contact_messages}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            return html;
        },

        closeDeleteModal() {
            $('.cin-contact-deletion-modal-overlay').fadeOut(200, function() {
                $(this).remove();
            });
            $(document).off('click', '.cin-contact-deletion-modal .cin-btn');
        },

        deleteContact(contactId, deleteMessages, $btn) {
            const self = this;
            const originalText = $btn ? $btn.text() : '';

            if ($btn) {
                $btn.prop('disabled', true).text(cinContactDeletion.strings.deleting);
            }

            $.ajax({
                url: cinContactDeletion.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: cinContactDeletion.action_delete,
                    contact_id: contactId,
                    delete_messages: deleteMessages ? 1 : 0,
                    nonce: this.getNonce(),
                },
                success(response) {
                    if (response.success) {
                        self.closeDeleteModal();
                        self.showSuccess(response.data.message);

                        const urlParams = new URLSearchParams(window.location.search);
                        const isDetailPage = urlParams.has('contact_id');

                        if (isDetailPage) {
                            setTimeout(() => {
                                const backUrl = new URL(window.location);
                                backUrl.searchParams.delete('contact_id');
                                window.location.href = backUrl.toString();
                            }, 1500);
                        } else {
                            const $row = $(`tr#contactin-row-${contactId}`);
                            if ($row.length) {
                                $row.fadeOut(300, function() {
                                    $(this).remove();
                                    const $countSpan = $('span.displaying-num');
                                    if ($countSpan.length) {
                                        const currentCount = parseInt($countSpan.text()) || 0;
                                        if (currentCount > 0) {
                                            $countSpan.text(currentCount - 1 + ' items');
                                        }
                                    }
                                });
                            }
                        }
                    } else {
                        if ($btn) {
                            $btn.prop('disabled', false).text(originalText);
                        }
                        self.showError(response.data?.message || cinContactDeletion.strings.error);
                    }
                },
                error() {
                    if ($btn) {
                        $btn.prop('disabled', false).text(originalText);
                    }
                    self.showError(cinContactDeletion.strings.error);
                },
            });
        },

        getNonce() {
            return $('input[name="ci_contact_deletion_nonce"]').val() || '';
        },

        showError(message) {
            this.showNotice(message, 'error');
        },

        showSuccess(message) {
            this.showNotice(message, 'success');
        },

        showNotice(message, type) {
            const noticeClass = type === 'error' ? 'notice-error' : 'notice-success';
            const notice = `
                <div class="notice ${noticeClass} is-dismissible">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            `;

            $('div.wrap').prepend(notice);

            $(document).on('click', '.notice-dismiss', function() {
                $(this).closest('.notice').fadeOut(200, function() {
                    $(this).remove();
                });
            });
        },
    };

    $(document).ready(function() {
        if (typeof cinContactDeletion !== 'undefined') {
            ContactDeletion.init();
        } else {
            setTimeout(function() {
                if (typeof cinContactDeletion !== 'undefined') {
                    ContactDeletion.init();
                }
            }, 100);
        }
    });

    window.ContactDeletion = ContactDeletion;

})(jQuery);
