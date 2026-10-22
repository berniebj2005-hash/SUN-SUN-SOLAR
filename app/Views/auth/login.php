<?= view('partials/header', ['pageTitle' => 'Login']) ?>
<section class="section wrap narrow"><span class="eyebrow">YOUR ACCOUNT</span>
    <h1>Welcome back</h1>
    <p class="lead">Sign in to manage your solar service requests.</p>
    <form class="formcard" action="<?= site_url('login') ?>" method="post"><?= csrf_field() ?><label>Username<input
                type="text" name="username" autocomplete="username" required></label><label>Password<input
                type="password" name="password" autocomplete="current-password" required></label><button class="btn"
            type="submit">Log in</button>
        <p>New customer? <a href="<?= site_url('register') ?>">Create an account</a></p>
    </form>
</section><?= view('partials/footer') ?>