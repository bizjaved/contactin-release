/**
 * Contact Edit Modal - Rich UX with inline validation
 * 
 * Features:
 * - Drawer-style modal that slides from right
 * - Real-time field validation
 * - Live phone number preview/formatting
 * - Dirty state tracking
 * - Unsaved changes warning
 * - AJAX save with loading state
 */

(function($) {
    'use strict';

    const ContactEditModal = {
        modal: null,
        form: null,
        originalData: {},
        isDirty: false,
        isLoading: false,
        currentContactId: null,
        emailCheckTimeout: null,

        /**
         * Initialize the module
         */
        init() {
            this.modal = $('#cin-contact-edit-modal');
            this.form = $('#cin-edit-contact-form');
            this.bindEvents();
        },

        /**
         * Bind all event handlers
         */
        bindEvents() {
            // Open modal from edit buttons
            $(document).on('click', '.cin-edit-contact-btn', (e) => {
                e.preventDefault();
                const contactId = $(e.currentTarget).data('contact-id');
                const nonce = $(e.currentTarget).data('nonce');
                this.open(contactId, nonce);
            });

            // Close modal
            this.modal.on('click', '.cin-modal-close, .cin-cancel-edit-btn', (e) => {
                e.preventDefault();
                this.handleClose();
            });

            // Close on overlay click
            this.modal.on('click', '.cin-modal-overlay', (e) => {
                if ($(e.target).hasClass('cin-modal-overlay')) {
                    this.handleClose();
                }
            });

            // Form field changes - track dirty state and validate
            this.form.on('input change', 'input[type="text"], input[type="email"], input[type="tel"]', (e) => {
                const $field = $(e.currentTarget);
                this.validateField($field);
                
                // Special handling for email field - debounce AJAX check
                if ($field.attr('name') === 'email') {
                    this.checkEmailAvailability($field);
                }
                
                this.updateDirtyState();
            });

            // Save button
            this.modal.on('click', '.cin-save-contact-btn', (e) => {
                e.preventDefault();
                this.save();
            });

            // Prevent accidental close with unsaved changes
            $(window).on('beforeunload', (e) => {
                if (this.isDirty && this.modal.is(':visible')) {
                    e.preventDefault();
                    e.returnValue = cinContactEdit.strings.unsaved_changes;
                }
            });

            // Real-time phone formatting
            this.form.on('blur', '.cin-phone-input', (e) => {
                this.formatPhoneNumber($(e.currentTarget));
            });
        },

        /**
         * Open modal with contact data
         */
        open(contactId, nonce) {
            this.currentContactId = contactId;
            this.isDirty = false;
            this.originalData = {};

            // Reset form to clean state
            this.resetForm();

            // Set nonce
            this.form.find('[name="nonce"]').val(nonce);
            this.form.find('#cin-edit-contact-id').val(contactId);

            // Show modal
            this.modal.css('display', 'flex').addClass('open');
            this.modal.find('.cin-modal-drawer-container').attr('aria-hidden', 'false');

            // Focus first field
            setTimeout(() => {
                this.form.find('#cin-edit-salutation').focus();
            }, 300);

            // Populate form with contact data
            this.loadContactData(contactId);
        },

        /**
         * Load contact data via AJAX
         */
        loadContactData(contactId) {
            const subtitleEl = this.modal.find('.cin-edit-contact-subtitle');
            
            // Show loading state
            if (subtitleEl.length) {
                subtitleEl.text('Loading...');
            }

            // Try to get data from page or fetch via AJAX
            this.fetchContactData(contactId);
        },

        /**
         * Fetch contact data from server
         */
        fetchContactData(contactId) {
            $.ajax({
                url: cinContactEdit.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'ci_get_contact_data',
                    contact_id: contactId,
                    nonce: this.form.find('[name="nonce"]').val(),
                },
                success: (response) => {
                    if (response.success && response.data) {
                        this.populateForm(response.data);
                    } else {
                        console.warn('Failed to load contact data');
                        this.resetForm();
                    }
                },
                error: () => {
                    console.error('AJAX error loading contact data');
                    this.resetForm();
                }
            });
        },

        /**
         * Populate form with contact data
         */
        populateForm(data) {
            this.form.find('[name="salutation"]').val(data.salutation || '');
            this.form.find('[name="name"]').val(data.name || '');
            this.form.find('[name="email"]').val(data.email || '');
            this.form.find('[name="primary_phone"]').val(data.primary_phone || '');
            this.form.find('[name="mobile_phone"]').val(data.mobile_phone || '');
            this.form.find('[name="home_phone"]').val(data.home_phone || '');
            this.form.find('[name="other_phone"]').val(data.other_phone || '');

            // Store original values for dirty state
            this.captureOriginalValues();

            const subtitleEl = this.modal.find('.cin-edit-contact-subtitle');
            if (subtitleEl.length && data.name) {
                subtitleEl.text(data.name);
            }

            this.clearAllErrors();
            this.updateDirtyState();
        },

        /**
         * Reset form without data
         */
        resetForm() {
            this.form[0].reset();
            this.originalData = {};
            this.captureOriginalValues();
            this.clearAllErrors();
            this.updateDirtyState();
        },

        /**
         * Capture original field values for dirty state
         */
        captureOriginalValues() {
            this.form.find('input[type="text"], input[type="email"], input[type="tel"]').each((i, el) => {
                const $el = $(el);
                this.originalData[$el.attr('name')] = $el.val() ?? '';
            });
        },

        /**
         * Check if form has unsaved changes
         */
        updateDirtyState() {
            this.isDirty = false;

            this.form.find('input[type="text"], input[type="email"], input[type="tel"]').each((i, el) => {
                const $el = $(el);
                const name = $el.attr('name');
                const currentValue = $el.val();
                const originalValue = this.originalData[name] ?? '';

                if (currentValue !== originalValue) {
                    this.isDirty = true;
                    return false; // break
                }
            });

            // Show/hide dirty state indicator
            this.modal.find('.cin-form-dirty-state').toggle(this.isDirty);

            // Enable/disable save button
            this.modal.find('.cin-save-contact-btn').prop('disabled', !this.isDirty);
        },

        /**
         * Validate individual field
         */
        validateField($field) {
            const name = $field.attr('name');
            const value = $field.val().trim();
            const $error = $field.closest('.cin-form-group').find('.cin-form-error');

            let error = '';

            // Required fields
            if ($field.prop('required') && !value) {
                error = cinContactEdit.strings.name_required;
            }

            // Email validation
            if (name === 'email' && value) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(value)) {
                    error = cinContactEdit.strings.invalid_email;
                }
            }

            // Phone validation (basic)
            if (name.includes('phone') && value) {
                if (value.replace(/\D/g, '').length < 10) {
                    error = cinContactEdit.strings.invalid_phone;
                }
            }

            // Show/hide error
            if (error) {
                $error.text(error).addClass('show');
                $field.addClass('error');
                return false;
            } else {
                $error.removeClass('show').text('');
                $field.removeClass('error');
                return true;
            }
        },

        /**
         * Check email availability via AJAX (debounced)
         */
        checkEmailAvailability($field) {
            // Clear existing timeout
            if (this.emailCheckTimeout) {
                clearTimeout(this.emailCheckTimeout);
            }

            const email = $field.val().trim();
            const $error = $field.closest('.cin-form-group').find('.cin-form-error');

            // Don't check if empty or invalid format
            if (!email) {
                $error.removeClass('show').text('');
                return;
            }

            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                return; // Format error already shown by validateField
            }

            // Debounce the AJAX call
            this.emailCheckTimeout = setTimeout(() => {
                $.post(cinContactEdit.ajax_url, {
                    action: cinContactEdit.check_email_action,
                    nonce: this.form.find('[name="nonce"]').val(),
                    email: email,
                    contact_id: this.currentContactId,
                }, (response) => {
                    // Only show error if email is not available
                    if (!response.success) {
                        $error.text(response.data.message || cinContactEdit.strings.email_in_use).addClass('show');
                        $field.addClass('error');
                    } else {
                        $error.removeClass('show').text('');
                        $field.removeClass('error');
                    }
                }).fail(() => {
                    // Silently fail on AJAX error - don't block form submission
                    console.warn('Email availability check failed');
                });
            }, 500); // 500ms debounce
        },

        /**
         * Validate entire form
         */
        validateForm() {
            let isValid = true;
            this.clearAllErrors();

            this.form.find('input[type="text"], input[type="email"], input[type="tel"]').each((i, el) => {
                if (!this.validateField($(el))) {
                    isValid = false;
                }
            });

            return isValid;
        },

        /**
         * Clear all error messages
         */
        clearAllErrors() {
            this.form.find('.cin-form-error').removeClass('show').text('');
            this.form.find('input').removeClass('error');
        },

        /**
         * Format phone number and show preview
         */
        formatPhoneNumber($field) {
            const value = $field.val().trim();
            if (!value) return;

            // Basic formatting (US format for demo)
            const digits = value.replace(/\D/g, '');
            let formatted = value;

            if (digits.length >= 10) {
                // Show formatted preview
                const preview = `+1 (${digits.slice(-10, -7)}) ${digits.slice(-7, -4)}-${digits.slice(-4)}`;
                const $preview = this.form.find(`.cin-phone-preview[data-for="${$field.data('phone-type')}-phone"]`);
                $preview.text(preview).addClass('active');
            }

            this.validateField($field);
        },

        /**
         * Save contact via AJAX
         */
        save() {
            if (!this.validateForm()) {
                alert(cinContactEdit.strings.validation_error);
                return;
            }

            this.isLoading = true;
            this.showLoadingState();

            const formData = {
                action: cinContactEdit.action,
                nonce: this.form.find('[name="nonce"]').val(),
                contact_id: this.currentContactId,
                name: this.form.find('[name="name"]').val().trim(),
                email: this.form.find('[name="email"]').val().trim(),
                salutation: this.form.find('[name="salutation"]').val().trim(),
                primary_phone: this.form.find('[name="primary_phone"]').val().trim(),
                mobile_phone: this.form.find('[name="mobile_phone"]').val().trim(),
                home_phone: this.form.find('[name="home_phone"]').val().trim(),
                other_phone: this.form.find('[name="other_phone"]').val().trim(),
            };

            $.post(cinContactEdit.ajax_url, formData, (response) => {
                this.isLoading = false;
                this.hideLoadingState();

                if (response.success) {
                    // Show success message
                    this.showSuccessNotification(cinContactEdit.strings.save_success);

                    // Reset dirty state and close promptly
                    this.isDirty = false;
                    this.originalData = {};
                    this.close();

                    // Reload page to reflect changes
                    setTimeout(() => {
                        location.reload();
                    }, 150);
                } else {
                    alert(response.data?.message || cinContactEdit.strings.save_error);
                }
            }).fail((jqXHR, textStatus, errorThrown) => {
                this.isLoading = false;
                this.hideLoadingState();
                console.error('Contact save failed:', textStatus, errorThrown);
                alert(cinContactEdit.strings.save_error);
            });
        },

        /**
         * Show loading state
         */
        showLoadingState() {
            const $loading = this.modal.find('.cin-modal-loading-overlay');
            $loading.fadeIn(200);
            this.modal.find('.cin-save-contact-btn').prop('disabled', true);
        },

        /**
         * Hide loading state
         */
        hideLoadingState() {
            const $loading = this.modal.find('.cin-modal-loading-overlay');
            $loading.fadeOut(200);
            this.modal.find('.cin-save-contact-btn').prop('disabled', false);
        },

        /**
         * Show success notification
         */
        showSuccessNotification(message) {
            // Could integrate with WordPress notices API
            console.log('Success:', message);
        },

        /**
         * Handle modal close with unsaved changes warning
         */
        handleClose() {
            if (this.isDirty) {
                if (!confirm(cinContactEdit.strings.unsaved_changes)) {
                    return;
                }
            }
            this.close();
        },

        /**
         * Close modal
         */
        close() {
            const $container = this.modal.find('.cin-modal-drawer-container');
            $container.addClass('closing');

            setTimeout(() => {
                this.modal.css('display', 'none').removeClass('open');
                $container.removeClass('closing');
                this.isDirty = false;
                this.currentContactId = null;
                this.form[0].reset();
            }, 180);
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        if (typeof cinContactEdit !== 'undefined') {
            ContactEditModal.init();
        }
    });

})(jQuery);
