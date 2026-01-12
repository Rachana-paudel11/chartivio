/**
 * Admin List Scripts for Chartivio
 */
function cvioCopyList(btn, text) {
    navigator.clipboard.writeText(text).then(function () {
        const original = btn.innerHTML;
        btn.innerHTML = '<span>Copied!</span>';
        btn.style.background = '#dcfce7';
        btn.style.borderColor = '#86efac';
        setTimeout(() => {
            btn.innerHTML = original;
            btn.style.background = '';
            btn.style.borderColor = '';
        }, 2000);
    });
}

jQuery(document).ready(function ($) {
    // Add "How to Use" link above the title
    if ($('body').hasClass('post-type-chartivio') && $('body').hasClass('edit-php')) {
        $('.wp-heading-inline').before('<div style="margin-bottom: 10px;"><a href="' + cvio_admin_vars.how_to_use_url + '" style="text-decoration: none; font-weight: 600; font-size: 14px; color: #2271b1; display: inline-flex; align-items: center;"><span class="dashicons dashicons-editor-help" style="font-size: 20px; width: 20px; height: 20px; margin-right: 5px;"></span>How to Use Guide</a></div>');
    }
});


