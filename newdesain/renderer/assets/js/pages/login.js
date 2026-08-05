/**
 * Login Page JS
 * Logic for handling login form submission via AJAX
 */

$(document).ready(function () {
    // Test if background image loads - Preload image
    var img = new Image();
    img.src = 'http://localhost/jembatantimbangan/assets/img/arroyan.png';

    $('#loginForm').on('submit', function (e) {
        e.preventDefault();

        const btnText = $('#btnText');
        const btnSpinner = $('#btnSpinner');
        const btn = $('#btnLogin');
        const originalText = btnText.text();

        // Disable button and show loading
        btn.prop('disabled', true);
        btnText.text('Loading...');
        btnSpinner.show();

        $.ajax({
            url: 'login.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    showAlert('success', response.message);
                    setTimeout(function () {
                        window.location.href = response.data.redirect;
                    }, 1500);
                } else {
                    showAlert('danger', response.message);
                    btn.prop('disabled', false);
                    btnText.text(originalText);
                    btnSpinner.hide();
                }
            },
            error: function () {
                showAlert('danger', 'Terjadi kesalahan sistem!');
                btn.prop('disabled', false);
                btnText.text(originalText);
                btnSpinner.hide();
            }
        });
    });

    function showAlert(type, message) {
        const alert = $('#alert-message');
        alert.removeClass('alert-success alert-danger')
            .addClass('alert-' + type)
            .html(message)
            .fadeIn();

        if (type === 'success') {
            setTimeout(() => {
                alert.fadeOut();
            }, 1500);
        }
    }

    // Add input animation effects
    $('.form-control').on('focus', function () {
        $(this).parent().find('.input-group-text').css({
            'background-color': 'rgba(102, 126, 234, 0.1)',
            'border-color': '#667eea'
        });
    });

    $('.form-control').on('blur', function () {
        $(this).parent().find('.input-group-text').css({
            'background-color': 'rgba(255, 255, 255, 0.08)',
            'border-color': 'rgba(255, 255, 255, 0.1)'
        });
    });

    // Auto-hide alert on click
    $(document).on('click', '.alert', function () {
        $(this).fadeOut();
    });
});
