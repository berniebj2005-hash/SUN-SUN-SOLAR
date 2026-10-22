<?= view('partials/header', ['pageTitle' => 'Request service']) ?>
<section class="section wrap narrow"><span class="eyebrow">CUSTOMER PORTAL</span>
    <h1>Request a service</h1>
    <p class="lead">Choose a date and time. Requests remain pending until approved by the team.</p>
    <form class="formcard" action="<?= site_url('bookings') ?>" method="post"><?= csrf_field() ?><label>Service<select
                name="service_id" required>
                <option value="">Choose service</option><?php foreach ($services as $s): ?>
                    <option value="<?= (int) $s['service_id'] ?>" <?= (string) old('service_id') === (string) $s['service_id'] ? 'selected' : '' ?>><?= esc($s['service_name']) ?></option><?php endforeach; ?>
            </select></label><label>Preferred date and time<input type="datetime-local" name="requested_start" required
                value="<?= esc(old('requested_start')) ?>"></label><label>Service address<textarea
                name="service_address" rows="3"
                required><?= esc(old('service_address') ?: ($profile['address'] ?? '')) ?></textarea></label><label>Notes
            (optional)<textarea name="notes" rows="4"><?= esc(old('notes')) ?></textarea></label><button class="btn"
            type="submit">Send booking request</button></form>
</section><?= view('partials/footer') ?>