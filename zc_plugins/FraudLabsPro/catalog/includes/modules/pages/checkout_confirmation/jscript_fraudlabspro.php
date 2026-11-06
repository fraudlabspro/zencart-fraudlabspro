<?php
if ((defined('FRAUDLABSPRO_AGENTJS') && FRAUDLABSPRO_AGENTJS === 'true') && (defined('FRAUDLABSPRO_ENABLED') && FRAUDLABSPRO_ENABLED === 'true')) {
?>

<script>
    (function() {
        function s() {
            var e = document.createElement('script');
            e.type = 'text/javascript';
            e.async = true;
            e.src = 'https://cdn.fraudlabspro.com/s.js';
            var s = document.getElementsByTagName('script')[0];
            s.parentNode.insertBefore(e, s);
        }

        (window.attachEvent) ? window.attachEvent('onload', s) : window.addEventListener('load', s, false);
    })();
</script>
<?php } ?>
