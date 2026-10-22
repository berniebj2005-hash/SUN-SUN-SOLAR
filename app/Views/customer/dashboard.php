<?= view('partials/header', ['pageTitle' => 'My dashboard']) ?>
<section class="section wrap"><span class="eyebrow">CUSTOMER PORTAL</span>
    <h1>Hello, <?= esc($profile['first_name'] ?? session('username')) ?>!</h1>
    <p class="lead">View your booking requests and service notifications.</p>
    <div class="actions"><a class="btn" href="<?= site_url('bookings/new') ?>">Request service →</a><a
            class="btn outline" href="<?= site_url('quote') ?>">Get a quote</a></div>
    <div class="actions"><a class="btn outline" href="<?= site_url('payments') ?>">Payments & receipts</a><a
            class="btn outline" href="<?= site_url('support') ?>">Support tickets</a></div>
    <h2 class="section-title">My service bookings</h2>
    <div class="tablewrap">
        <table>
            <thead>
                <tr>
                    <th>Service</th>
                    <th>Requested time</th>
                    <th>Address</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody><?php if (!$bookings): ?>
                    <tr>
                        <td colspan="4">No bookings yet. Request your first service above.</td>
                    </tr><?php endif; ?><?php foreach ($bookings as $b): ?>
                    <tr>
                        <td><?= esc($b['service_name']) ?></td>
                        <td><?= esc($b['requested_start']) ?></td>
                        <td><?= esc($b['service_address']) ?></td>
                        <td><span class="tag"><?= esc(ucwords(str_replace('_', ' ', $b['booking_status']))) ?></span></td>
                    </tr><?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <h2 class="section-title">Notifications</h2>
    <div class="grid two-col"><?php if (!$notifications): ?>
            <p class="empty">No notifications yet.</p><?php endif; ?><?php foreach ($notifications as $n): ?>
            <article class="card">
                <h3><?= esc($n['title']) ?></h3>
                <p><?= esc($n['message']) ?></p><small><?= esc($n['created_at']) ?></small>
            </article><?php endforeach; ?>
    </div>
</section><?= view('partials/footer') ?>