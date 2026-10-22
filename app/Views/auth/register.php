<?= view('partials/header', ['pageTitle' => 'Register']) ?>
<section class="section wrap narrow"><span class="eyebrow">CUSTOMER REGISTRATION</span>
    <h1>Join Sun Son Solar</h1>
    <p class="lead">Customer accounts can be created here. Employee accounts are created by HR or administrators.</p>
    <form class="formcard" action="<?= site_url('register') ?>" method="post"><?= csrf_field() ?>
        <div class="two"><label>First name<input name="first_name" maxlength="100" required
                    value="<?= esc(old('first_name')) ?>"></label><label>Last name<input name="last_name"
                    maxlength="100" required value="<?= esc(old('last_name')) ?>"></label></div><label>Middle name
            (optional)<input name="middle_name" maxlength="100" value="<?= esc(old('middle_name')) ?>"></label>
        <div class="two"><label>Birthdate (optional)<input type="date" name="birthdate"
                    value="<?= esc(old('birthdate')) ?>"></label><label>Gender (optional)<input name="gender"
                    maxlength="30" value="<?= esc(old('gender')) ?>"></label></div><label>Email address<input
                type="email" name="email" maxlength="255" required value="<?= esc(old('email')) ?>"></label><label>Phone
            number<input type="tel" name="phone_number" maxlength="30" required
                value="<?= esc(old('phone_number')) ?>"></label><label>Address<textarea name="address" rows="3"
                required><?= esc(old('address')) ?></textarea></label><label>Username<input name="username"
                minlength="3" maxlength="50" autocomplete="username" required
                value="<?= esc(old('username')) ?>"></label>
        <div class="two"><label>Password<input type="password" name="password" minlength="8" maxlength="72"
                    autocomplete="new-password" required></label><label>Confirm password<input type="password"
                    name="password_confirm" minlength="8" maxlength="72" autocomplete="new-password" required></label>
        </div><button class="btn" type="submit">Create account</button>
        <p>Already registered? <a href="<?= site_url('login') ?>">Log in</a></p>
    </form>
</section><?= view('partials/footer') ?>