<?= view('partials/header', ['pageTitle' => 'Products']) ?>
<section class="section wrap"><span class="eyebrow">CATALOG</span>
    <h1>Solar products</h1>
    <p class="lead">Browse available solar equipment. Prices are displayed where provided.</p>
    <div class="grid three"><?php if (!$products): ?>
            <div class="empty">No products listed yet. Product categories are already set up in the database.</div>
        <?php endif; ?><?php foreach ($products as $p): ?>
            <article class="card">
                <div class="product-visual">☀</div><small class="tag"><?= esc($p['category_name']) ?></small>
                <h3><?= esc($p['product_name']) ?></h3>
                <p><?= esc($p['description'] ?: 'Contact us for specifications.') ?></p>
                <strong><?= $p['display_price'] !== null ? '₱' . number_format((float) $p['display_price'], 2) : 'Request pricing' ?></strong>
            </article><?php endforeach; ?>
    </div>
</section><?= view('partials/footer') ?>