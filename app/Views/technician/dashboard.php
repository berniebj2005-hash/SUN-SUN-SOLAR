<?= view('partials/header', ['pageTitle' => 'Technician dashboard']) ?>
<section class="section wrap"><span class="eyebrow">TECHNICIAN PORTAL</span>
    <h1>Welcome, <?= esc($e['first_name']) ?></h1>
    <p>Your assigned jobs, site check-ins and service reports.</p>
    <h2>My assignments</h2>
    <?php foreach ($assignments as $a): ?>
        <article class="card" style="margin:14px 0">
            <h3>#<?= esc($a['booking_id']) ?> · <?= esc($a['service_name']) ?></h3>
            <p>Customer: <?= esc($a['first_name'] . ' ' . $a['last_name']) ?> · Service address:
                <?= esc($a['service_address']) ?></p>
            <p>Requested: <?= esc($a['requested_start']) ?> · Booking: <?= esc($a['booking_status']) ?> · Assignment:
                <?= esc($a['assignment_status']) ?></p>
            <form method="post" action="<?= site_url('technician/checkin') ?>" class="checkin-form">
                <?= csrf_field() ?><input type="hidden" name="assignment_id" value="<?= esc($a['assignment_id']) ?>"><input
                    type="hidden" name="latitude"><input type="hidden" name="longitude"><button type="button"
                    class="btn checkin-button">Check in at site</button><small class="geo-status"
                    aria-live="polite">Location permission required. Use HTTPS or localhost for browser location.</small>
            </form>
            <form action="<?= site_url('technician/report') ?>" method="post" class="form-stack"><?= csrf_field() ?><input
                    type="hidden" name="assignment_id" value="<?= esc($a['assignment_id']) ?>"><label>Issue type
                    (optional)<input name="issue_type" maxlength="100"></label><label>Service report<textarea
                        name="report_text" required maxlength="5000"></textarea></label><button type="submit">Submit
                    report</button></form>
        </article><?php endforeach; ?><?php if (!$assignments): ?>
        <p>No assigned jobs yet.</p><?php endif; ?>
    <h2>Announcements</h2><?php foreach ($announcements as $n): ?>
        <article class="card" style="margin:10px 0">
            <h3><?= esc($n['title']) ?></h3>
            <p><?= esc($n['body']) ?></p>
        </article><?php endforeach; ?><?php if (!$announcements): ?>
        <p>No announcements yet.</p><?php endif; ?>
</section>
<script>document.querySelectorAll('.checkin-form').forEach(form => { form.querySelector('.checkin-button').addEventListener('click', () => { const msg = form.querySelector('.geo-status'); if (!navigator.geolocation) { msg.textContent = 'Geolocation unavailable.'; return; } msg.textContent = 'Requesting location permission…'; navigator.geolocation.getCurrentPosition(pos => { form.querySelector('[name=latitude]').value = pos.coords.latitude; form.querySelector('[name=longitude]').value = pos.coords.longitude; form.submit(); }, () => { msg.textContent = 'Location access denied or unavailable. Check browser permission.'; }, { enableHighAccuracy: true, timeout: 15000 }); }); });</script>
<?= view('partials/footer') ?>