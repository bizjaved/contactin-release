/**
 * Secure Contact Us Hub - File Upload Handler
 * 
 * Handles pre-submission file uploads via AJAX with progress tracking.
 * Files are uploaded before form submission and referenced by file_id.
 * 
 * @package ContactIn
 * @since   1.7.0
 */

(function() {
    'use strict';

    // Configuration
    const CONFIG = {
        ajaxUrl: window.cinFormConfig?.ajaxUrl || '/',
        restUrl: window.cinFormConfig?.restUrl || '/wp-json/',
        nonce: window.cinFormConfig?.nonce || '',
        maxFileSize: window.cinFormConfig?.maxFileSize || 5,
    };

    /**
     * Initialize file upload handlers
     */
    function initFileUpload() {
        // Debug: print allowed file types from config
        if (window.cinFormConfig && window.cinFormConfig.allowedFileTypes !== undefined) {
        } else {
        }
        const fileInput = document.getElementById('file-input');
        const progressContainer = document.getElementById('file-upload-progress');
        const progressBar = document.querySelector('.file-progress-bar');
        const progressText = document.querySelector('.file-progress-text');
        const submitButton = document.querySelector('button[type="submit"]');
        const form = document.getElementById('contactin-form');

        if (!fileInput) {
            return;
        }

        // Store currently uploaded file info
        let uploadedFile = {
            id: null,
            ext: null,
            name: null,
        };

        /**
         * Handle file selection
         */
        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;

            // Debug: print file info and allowed types
            const allowedTypes = window.cinFormConfig?.allowedFileTypes || '';
            const allowedExts = allowedTypes.split(',').map(e => e.trim().toLowerCase()).filter(Boolean);
            const fileName = file.name || '';
            const fileExt = fileName.split('.').pop().toLowerCase();

            // Check file type before upload
            if (fileExt && allowedExts.length > 0 && !allowedExts.includes(fileExt)) {
                console.warn('[ContactIN Upload] File type not allowed:', fileExt);
                if (progressText) {
                    progressText.textContent = `✗ File type .${fileExt} not allowed. Allowed: ${allowedExts.join(', ')}`;
                    progressText.className = 'file-progress-text file-progress-error';
                }
                if (progressBar) {
                    progressBar.className = 'file-progress-bar file-progress-error';
                    progressBar.style.width = '0%';
                }
                if (progressContainer) {
                    progressContainer.classList.remove('cin-hidden');
                }
                fileInput.value = '';
                if (submitButton) submitButton.disabled = false;
                return;
            }

            // Reset progress bar and show progress container
            if (progressBar) {
                progressBar.style.width = '0%';
                progressBar.className = 'file-progress-bar';
            }
            if (progressText) {
                progressText.textContent = '0%';
                progressText.className = 'file-progress-text';
            }
            if (progressContainer) {
                progressContainer.classList.remove('cin-hidden');
                progressContainer.style.display = 'block';
                progressContainer.classList.add('active');
            }
            
            if (submitButton) {
                submitButton.disabled = true;
            }

            // Upload file
            uploadFile(file, uploadedFile, progressBar, progressText, submitButton, progressContainer);
        });

        /**
         * Prevent form submission if file upload is in progress
         */
        if (form) {
            form.addEventListener('submit', function(e) {
                // Check if file is selected but not yet uploaded
                if (fileInput && fileInput.files.length > 0 && !uploadedFile.id) {
                    e.preventDefault();
                    
                    if (submitButton) {
                        submitButton.disabled = true;
                    }
                    
                    // Show inline error message instead of alert
                    if (progressText) {
                        progressText.textContent = '⏳ Please wait for file upload to complete...';
                        progressText.className = 'file-progress-text file-progress-warning';
                    }
                    if (progressContainer) {
                        progressContainer.classList.remove('cin-hidden');
                        progressContainer.style.display = 'block';
                    }
                    return false;
                }
                
                // If file was uploaded, verify hidden inputs exist
                if (uploadedFile.id) {
                    const fileIdInput = document.querySelector('input[name="file_id"]');
                    if (!fileIdInput) {
                        // Add them now as fallback
                        const formElement = document.getElementById('contactin-form');
                        if (formElement) {
                            const input1 = document.createElement('input');
                            input1.type = 'hidden';
                            input1.name = 'file_id';
                            input1.value = uploadedFile.id;
                            formElement.appendChild(input1);
                            
                            const input2 = document.createElement('input');
                            input2.type = 'hidden';
                            input2.name = 'file_ext';
                            input2.value = uploadedFile.ext;
                            formElement.appendChild(input2);
                            
                            const input3 = document.createElement('input');
                            input3.type = 'hidden';
                            input3.name = 'file_original_name';
                            input3.value = uploadedFile.name;
                            formElement.appendChild(input3);
                            
                        }
                    }
                }
            });
        }

        /**
         * File upload is handled above - hidden inputs are added immediately
         * after successful upload, ensuring they're present when form is submitted
         */
    }

    /**
     * Upload file to server via AJAX (not REST API)
     * This works regardless of REST API setting
     * 
     * @param {File} file The file to upload
     * @param {Object} uploadedFile Object to store upload result
     * @param {HTMLElement} progressBar The progress bar element
     * @param {HTMLElement} progressText The progress text element
     * @param {HTMLElement} submitButton The form submit button
     * @param {HTMLElement} progressContainer The progress container
     */
    function uploadFile(file, uploadedFile, progressBar, progressText, submitButton, progressContainer) {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('action', 'contactin_upload_attachment');
        formData.append('nonce', CONFIG.nonce);

        // Build AJAX endpoint URL (doesn't require REST API to be enabled)
        const ajaxUrl = CONFIG.ajaxUrl;

        const xhr = new XMLHttpRequest();
        let uploadTimeout;

        // Set 5-minute timeout for upload
        xhr.timeout = 5 * 60 * 1000;

        // Track upload progress
        if (xhr.upload) {
            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    const percentComplete = Math.round((e.loaded / e.total) * 100);
                    
                    if (progressBar) {
                        progressBar.style.width = percentComplete + '%';
                    }
                    if (progressText) {
                        progressText.textContent = percentComplete + '%';
                    }
                }
            }, false);
        }

        // Handle upload completion
        xhr.addEventListener('load', function() {
            clearTimeout(uploadTimeout);
            
            if (xhr.status === 200 || xhr.status === 201) {
                try {
                    let response = JSON.parse(xhr.responseText);
                    
                    // WordPress AJAX sends data wrapped in object
                    // Check both formats
                    let fileData = null;
                    if (response.success && response.data) {
                        fileData = response.data;
                    } else if (response.data && response.data.file_id) {
                        fileData = response.data;
                    } else if (response.file_id) {
                        fileData = response;
                    }
                    
                    if (fileData && fileData.file_id) {
                        // Store uploaded file info
                        uploadedFile.id = fileData.file_id;
                        uploadedFile.ext = file.name.split('.').pop();
                        uploadedFile.name = fileData.filename || file.name;

                        // Add hidden inputs immediately after successful upload
                        const formElement = document.getElementById('contactin-form');
                        
                        if (formElement && !document.querySelector('input[name="file_id"]')) {
                            const fileIdInput = document.createElement('input');
                            fileIdInput.type = 'hidden';
                            fileIdInput.name = 'file_id';
                            fileIdInput.value = uploadedFile.id;
                            formElement.appendChild(fileIdInput);

                            const fileExtInput = document.createElement('input');
                            fileExtInput.type = 'hidden';
                            fileExtInput.name = 'file_ext';
                            fileExtInput.value = uploadedFile.ext;
                            formElement.appendChild(fileExtInput);
                            
                            const fileNameInput = document.createElement('input');
                            fileNameInput.type = 'hidden';
                            fileNameInput.name = 'file_original_name';
                            fileNameInput.value = uploadedFile.name;
                            formElement.appendChild(fileNameInput);
                            
                        }

                        // Show success message
                        if (progressText) {
                            progressText.textContent = '✓ Upload complete!';
                            progressText.className = 'file-progress-text file-progress-success';
                        }
                        if (progressBar) {
                            progressBar.style.width = '100%';
                            progressBar.className = 'file-progress-bar file-progress-success';
                        }

                        // Enable submit button after successful upload
                        if (submitButton) {
                            submitButton.disabled = false;
                        }
                    } else {
                        console.warn('[ContactIN Upload] No file_id found in response');
                        showUploadError('✗ Upload completed but file ID not received', progressText, progressBar, submitButton);
                    }
                } catch (e) {
                    console.error('[ContactIN Upload] Error parsing response:', e);
                    showUploadError('✗ Unexpected error processing upload', progressText, progressBar, submitButton);
                }
            } else {
                // Handle error response
                try {
                    const response = JSON.parse(xhr.responseText);
                    
                    let errorMessage = '✗ Upload failed: ' + xhr.status;
                    if (response.data && response.data.message) {
                        errorMessage = '✗ ' + response.data.message;
                    } else if (response.message) {
                        errorMessage = '✗ ' + response.message;
                    }
                    
                    showUploadError(errorMessage, progressText, progressBar, submitButton);
                } catch (e) {
                    showUploadError('✗ Upload failed: ' + xhr.status, progressText, progressBar, submitButton);
                }
            }
        });

        xhr.addEventListener('error', function() {
            clearTimeout(uploadTimeout);
            showUploadError('✗ Network error during upload', progressText, progressBar, submitButton);
        });

        xhr.addEventListener('abort', function() {
            clearTimeout(uploadTimeout);
            showUploadError('✗ Upload cancelled', progressText, progressBar, submitButton);
        });

        xhr.addEventListener('timeout', function() {
            clearTimeout(uploadTimeout);
            showUploadError('✗ Upload timeout - file took too long', progressText, progressBar, submitButton);
            xhr.abort();
        });

        // Start upload
        xhr.open('POST', ajaxUrl, true);
        xhr.send(formData);
    }

    /**
     * Display upload error message
     */
    function showUploadError(message, progressText, progressBar, submitButton) {
        if (progressText) {
            progressText.textContent = message;
            progressText.className = 'file-progress-text file-progress-error';
        }
        if (progressBar) {
            progressBar.style.width = '0%';
            progressBar.className = 'file-progress-bar file-progress-error';
        }
        if (submitButton) {
            submitButton.disabled = false;
        }
    }

    /**
     * Initialize when DOM is ready
     */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            initFileUpload();
        });
    } else {
        initFileUpload();
    }
})();
