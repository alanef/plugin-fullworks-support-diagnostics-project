// Import translation functions from WordPress
const { __, _x, sprintf } = wp.i18n;

jQuery(document).ready(function($) {
    // Get the active tab from URL params, URL hash, or localStorage
    function getActiveTab() {
        // First check URL param (used when redirecting after form submission)
        var urlParams = new URLSearchParams(window.location.search);
        var tabParam = urlParams.get('tab');
        if (tabParam && $('#' + tabParam).length) {
            return tabParam;
        }
        
        // Check URL hash next
        var hashTab = window.location.hash.substring(1);
        if (hashTab && $('#' + hashTab).length) {
            return hashTab;
        }
        
        // Then check localStorage
        var storedTab = localStorage.getItem('fwpsd_active_tab');
        if (storedTab && $('#' + storedTab).length) {
            return storedTab;
        }
        
        // Default to diagnostics tab
        return 'diagnostics';
    }
    
    // Set the active tab
    function setActiveTab(tabId) {
        // Update tab classes
        $('.nav-tab').removeClass('nav-tab-active');
        $('a[href="#' + tabId + '"]').addClass('nav-tab-active');
        
        // Show/hide content
        $('.tab-content').hide();
        $('#' + tabId).show();
        
        // Store in localStorage
        localStorage.setItem('fwpsd_active_tab', tabId);
        
        // Update URL hash (without page reload)
        if (history.pushState) {
            history.pushState(null, null, '#' + tabId);
        } else {
            window.location.hash = '#' + tabId;
        }
    }
    
    // Initialize active tab
    setActiveTab(getActiveTab());
    
    // Tab navigation
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        
        // Get the target tab
        var target = $(this).attr('href').substring(1);
        
        // Update URL with the tab parameter (without reloading)
        var url = new URL(window.location.href);
        url.searchParams.set('tab', target);
        history.replaceState(null, '', url);
        
        setActiveTab(target);
    });
    
    // Add confirmation for debug constant management
    $('#fwpsd-manage-debug-constants').on('change', function() {
        if (this.checked) {
            var confirmed = confirm(
                // Use WordPress i18n functions for translatable strings
                sprintf(
                    '%s\n\n• %s\n• %s\n• %s\n\n%s',
                    __('WARNING: This will modify your wp-config.php file.', 'fullworks-support-diagnostics'),
                    __('A backup will be created before changes', 'fullworks-support-diagnostics'),
                    __('Debug constants you select will be added to wp-config.php', 'fullworks-support-diagnostics'),
                    __('This can change your site\'s behavior and error logging', 'fullworks-support-diagnostics'),
                    __('Are you sure you want to enable debug constants management?', 'fullworks-support-diagnostics')
                )
            );
            
            if (!confirmed) {
                this.checked = false;
                return false;
            }
        }
    });

    // Make sure the psdData object exists
    if (typeof psdData === 'undefined') {
        console.error('psdData is not defined. Admin script may be loading before localization.');
        return;
    }

    // Generate diagnostic data
    $('#wpsa-generate-data').on('click', function() {
        console.log('Generate diagnostic data button clicked');
        var $button = $(this);
        var $resultArea = $('#wpsa-diagnostic-result');

        $button.prop('disabled', true).text(__('Generating...', 'fullworks-support-diagnostics'));

        $.ajax({
            url: psdData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pp_' + psdData.pluginSlug + '_generate_diagnostic_data', // Plugin-specific action (PAG-71)
                nonce: psdData.nonce
            },
            success: function(response) {
                console.log('AJAX response received:', response);
                if (response.success) {
                    // Display the data
                    $('#wpsa-diagnostic-data').val(JSON.stringify(response.data.data, null, 2));

                    // Set the direct access link
                    if (response.data.direct_access_url) {
                        console.log('Direct access URL:', response.data.direct_access_url);
                        $('#wpsa-access-link').val(response.data.direct_access_url);
                    } else {
                        console.error('Direct access URL not found in response:', response);
                    }

                    // Show the result area
                    $resultArea.show();
                } else {
                    alert('Error: ' + (response.data ? response.data.message : 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error);
                alert('Error: Could not communicate with the server. ' + error);
            },
            complete: function() {
                $button.prop('disabled', false).text(__('Generate Diagnostic Data', 'fullworks-support-diagnostics'));
            }
        });
    });

    // Copy to clipboard
    $('#wpsa-copy-data').on('click', function() {
        var textArea = document.getElementById('wpsa-diagnostic-data');
        textArea.select();
        document.execCommand('copy');

        // Show temporary success message
        var $button = $(this);
        var originalText = $button.text();
        $button.text(__('Copied!', 'fullworks-support-diagnostics'));
        setTimeout(function() {
            $button.text(originalText);
        }, 2000);
    });

    // Download as JSON
    $('#wpsa-download-data').on('click', function() {
        var data = $('#wpsa-diagnostic-data').val();
        var filename = 'wp-support-diagnostic-' + new Date().toISOString().slice(0,19).replace(/:/g, '-') + '.json';

        var blob = new Blob([data], {type: 'application/json'});
        var url = URL.createObjectURL(blob);

        var a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    });

    // Regenerate keys
    $('#wpsa-regenerate-keys').on('click', function() {
        if (!confirm(__('Are you sure you want to regenerate the access keys? Any existing links using the current keys will stop working.', 'fullworks-support-diagnostics'))) {
            return;
        }

        var $button = $(this);
        $button.prop('disabled', true).text(__('Regenerating...', 'fullworks-support-diagnostics'));

        $.ajax({
            url: psdData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pp_' + psdData.pluginSlug + '_regenerate_keys', // Plugin-specific action (PAG-71)
                nonce: psdData.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Update the displayed keys
                    psdData.accessKey = response.data.access_key;
                    psdData.restEndpointKey = response.data.rest_endpoint_key;

                    // Reload the page to show updated keys
                    window.location.href = window.location.href + '&keys_regenerated=1';
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            error: function() {
                alert('Error: Could not communicate with the server.');
            },
            complete: function() {
                $button.prop('disabled', false).text(__('Regenerate Keys', 'fullworks-support-diagnostics'));
            }
        });
    });

    // Copy access link
    $('#wpsa-access-link').on('click', function() {
        $(this).select();
        document.execCommand('copy');

        // Show temporary message
        var $this = $(this);
        var originalBg = $this.css('background-color');
        $this.css('background-color', '#e7f7e3');
        setTimeout(function() {
            $this.css('background-color', originalBg);
        }, 1000);
    });
});