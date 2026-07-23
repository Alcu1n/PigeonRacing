<script data-navigate-once>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/admin/service-worker.js', { scope: '/admin/' });
        });
    }
</script>
