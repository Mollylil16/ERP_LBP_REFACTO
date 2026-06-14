<?php

use App\Helpers\View;

$restrictedTables = $restrictedTables ?? [];
?>
<?php if ($restrictedTables !== []): ?>
    <aside class="rh-restricted-data" role="status">
        <strong>Certaines données sont masquées selon vos habilitations.</strong>
        <span><?= View::e(implode(', ', array_values($restrictedTables))) ?></span>
    </aside>
<?php endif; ?>
