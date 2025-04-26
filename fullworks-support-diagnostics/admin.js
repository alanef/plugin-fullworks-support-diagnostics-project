jQuery(document).ready(function($) {
    // Tab navigation
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();

        // Get the target tab
        var target = $(this).attr('href').substring(1);

        // Update active tab
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');

        // Show/hide tab content
        $('.tab-content').hide();
        $('#' + target).show();
    });

    // Make sure the psdData object exists
    if (typeof psdData === 'undefined') {
        console.error('psdData is not defined. Admin script may be loading before localization.');
        return;
    }

    // Generate diagnostic data
    $('#fwpsd-generate-data').on('click', function() {
        console.log('Generate diagnostic data button clicked');
        var $button = $(this);
        var $resultArea = $('#wpsa-diagnostic-result');

        $button.prop('disabled', true).text('Generating...');

        $.ajax({
            url: wpsaData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpsa_generate_diagnostic_data',
                nonce: wpsaData.nonce
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
                $button.prop('disabled', false).text('Generate Diagnostic Data');
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
        $button.text('Copied!');
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
        if (!confirm('Are you sure you want to regenerate the access keys? Any existing links using the current keys will stop working.')) {
            return;
        }

        var $button = $(this);
        $button.prop('disabled', true).text('Regenerating...');

        $.ajax({
            url: wpsaData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpsa_regenerate_keys',
                nonce: wpsaData.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Update the displayed keys
                    wpsaData.accessKey = response.data.access_key;
                    wpsaData.restEndpointKey = response.data.rest_endpoint_key;

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
                $button.prop('disabled', false).text('Regenerate Keys');
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