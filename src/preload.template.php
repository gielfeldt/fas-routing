
<?php foreach ($files as $file) : ?>
opcache_compile_file(<?php var_export(realpath($file)); ?>);
<?php endforeach; ?>
