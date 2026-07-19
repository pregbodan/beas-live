        </div><!-- end .page-body -->
    </main><!-- end .main-content -->
</div><!-- end .layout -->

<script src="<?= APP_URL ?>/assets/js/main.js"></script>
<?php if (isset($pageScripts)): ?>
    <?php foreach ($pageScripts as $script): ?>
        <script src="<?= $script ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>
<?php if (isset($inlineScript)): ?>
<script><?= $inlineScript ?></script>
<?php endif; ?>
</body>
</html>
