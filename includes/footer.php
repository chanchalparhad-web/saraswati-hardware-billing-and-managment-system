</div> <!-- End #content -->
</div> <!-- End #wrapper -->

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- App custom JS (Inline injection guarantees 100% functionality on Vercel without CDN 404s) -->
<?php 
$jsFile = __DIR__ . '/../assets/js/app.js';
if (file_exists($jsFile)): ?>
<script>
<?php echo file_get_contents($jsFile); ?>
</script>
<?php else: ?>
<script src="<?php echo base_url('assets/js/app.js'); ?>"></script>
<?php endif; ?>

</body>
</html>
