<?= view('partials/header', ['pageTitle' => 'Support']) ?>
<section class="section wrap">
    <h1>Support tickets</h1><?php if (session('role') === 'customer'): ?>
        <form method="post" action="<?= site_url('support') ?>" class="card form-stack"><?= csrf_field() ?>
            <h2>New support ticket</h2><label>Subject<input name="subject" maxlength="200"
                    required></label><label>Issue<textarea name="description" maxlength="5000"
                    required></textarea></label><button type="submit">Submit ticket</button>
        </form><?php endif; ?>
    <h2>Tickets</h2>
    <div class="tablewrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Subject</th>
                    <th>Customer</th>
                    <th>Status</th>
                    <th>Opened</th>
                </tr>
            </thead>
            <tbody><?php foreach ($tickets as $t): ?>
                    <tr>
                        <td><a href="<?= site_url('support/' . $t['ticket_id']) ?>">#<?= esc($t['ticket_id']) ?></a></td>
                        <td><?= esc($t['subject']) ?></td>
                        <td><?= esc($t['first_name'] . ' ' . $t['last_name']) ?></td>
                        <td><?= esc($t['ticket_status']) ?></td>
                        <td><?= esc($t['created_at']) ?></td>
                    </tr><?php endforeach; ?><?php if (!$tickets): ?>
                    <tr>
                        <td colspan="5">No tickets.</td>
                    </tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section><?= view('partials/footer') ?>