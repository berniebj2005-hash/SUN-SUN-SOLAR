<?= view('partials/header', ['pageTitle' => 'Staff dashboard']) ?>
<section class="section wrap"><span class="eyebrow">STAFF PORTAL</span>
    <h1>Operations dashboard</h1>
    <p>Signed in as <?= esc(session('role')) ?>.</p>
    <div class="actions"><?php if (in_array(session('role'), ['admin', 'hr'], true)): ?><a class="btn"
                href="<?= site_url('staff/employees/new') ?>">Create employee account</a><?php endif; ?><a
            class="btn outline" href="<?= site_url('payments') ?>">Payments</a><a class="btn outline"
            href="<?= site_url('support') ?>">Support tickets</a></div>
    <h2>Recent bookings</h2>
    <div class="tablewrap">
        <table>
            <thead>
                <tr>
                    <th>Booking</th>
                    <th>Customer</th>
                    <th>Service</th>
                    <th>Requested</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $b): ?>
                    <tr>
                        <td>#<?= esc($b['booking_id']) ?></td>
                        <td><?= esc($b['first_name'] . ' ' . $b['last_name']) ?></td>
                        <td><?= esc($b['service_name']) ?></td>
                        <td><?= esc($b['requested_start']) ?></td>
                        <td><?= esc($b['booking_status']) ?></td>
                        <td>
                            <?php if (in_array(session('role'), ['admin', 'dispatcher'], true)): ?>
                                <form action="<?= site_url('staff/bookings/status') ?>" method="post"><?= csrf_field() ?><input
                                        type="hidden" name="booking_id" value="<?= esc($b['booking_id']) ?>"><select
                                        name="status"><?php foreach (['pending', 'approved', 'declined', 'cancelled', 'in_progress', 'completed'] as $status): ?>
                                            <option value="<?= esc($status) ?>" <?= $b['booking_status'] === $status ? 'selected' : '' ?>>
                                                <?= esc($status) ?></option><?php endforeach; ?>
                                    </select><button type="submit">Update</button></form>
                                <form action="<?= site_url('staff/bookings/assign') ?>" method="post"><?= csrf_field() ?><input
                                        type="hidden" name="booking_id" value="<?= esc($b['booking_id']) ?>"><select
                                        name="employee_id" required>
                                        <option value="">Technician...</option><?php foreach ($technicians as $t): ?>
                                            <option value="<?= esc($t['employee_id']) ?>">
                                                <?= esc($t['first_name'] . ' ' . $t['last_name']) ?></option><?php endforeach; ?>
                                    </select><button type="submit">Assign</button></form>
                            <?php else: ?>View only<?php endif; ?>
                        </td>
                    </tr><?php endforeach; ?><?php if (!$bookings): ?>
                    <tr>
                        <td colspan="6">No bookings yet.</td>
                    </tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <h2>Employees</h2>
    <div class="tablewrap">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Department</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody><?php foreach ($employees as $e): ?>
                    <tr>
                        <td><?= esc($e['first_name'] . ' ' . $e['last_name']) ?></td>
                        <td><?= esc($e['username']) ?></td>
                        <td><?= esc($e['role_name']) ?></td>
                        <td><?= esc($e['department_name']) ?></td>
                        <td><?= esc($e['account_status']) ?></td>
                    </tr><?php endforeach; ?><?php if (!$employees): ?>
                    <tr>
                        <td colspan="5">No employees yet.</td>
                    </tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section><?= view('partials/footer') ?>