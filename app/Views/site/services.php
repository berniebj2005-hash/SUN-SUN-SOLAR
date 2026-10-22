<?= view('partials/header', ['pageTitle' => 'Services']) ?>
<section class="section wrap"><span class="eyebrow">WHAT WE OFFER</span>
    <h1>Our solar services</h1>
    <p class="lead">From consultation and design to repair and monitoring.</p>
    <div class="grid three"><?php foreach ($services as $service): ?>
            <article class="card">
                <div class="cardicon">⚡</div>
                <h3><?= esc($service['service_name']) ?></h3>
                <p><?= esc($service['description'] ?: 'Contact the Sun Son Solar team to learn more about this service.') ?>
                </p>
                <p><strong><?= $service['base_price'] !== null ? 'From ₱' . number_format((float) $service['base_price'], 2) : 'Price on request' ?></strong>
                </p><a href="<?= site_url('quote') . '?service_id=' . (int) $service['service_id'] ?>">Request a quote →</a>
            </article><?php endforeach; ?>
    </div>
</section><?= view('partials/footer') ?>