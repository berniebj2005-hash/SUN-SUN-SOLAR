<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#093d32"><meta name="description" content="Sun Son Solar solar products and services, from consultation and installation to maintenance.">
<title><?= esc($pageTitle ?? 'Sun Son Solar') ?> | Sun Son Solar</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="<?= base_url('assets/css/site.css') ?>">
<script defer src="<?= base_url('assets/js/site.js') ?>"></script></head>
<body><a class="skip-link" href="#main-content">Skip to content</a>
<header class="nav" id="site-header"><div class="wrap navrow">
<a class="brand" href="<?= site_url('/') ?>" aria-label="Sun Son Solar homepage"><span class="brand-symbol" aria-hidden="true">☀</span><span>SUN SON <b>SOLAR</b><small>ENERGY FOR TOMORROW</small></span></a>
<button class="menu-toggle" type="button" aria-controls="main-navigation" aria-expanded="false" aria-label="Open navigation"><span></span><span></span><span></span></button>
<nav id="main-navigation" class="main-nav" aria-label="Main navigation">
<a href="<?= site_url('/') ?>">Home</a><a href="<?= site_url('products') ?>">Products</a><a href="<?= site_url('services') ?>">Services</a><a href="<?= site_url('quote') ?>">Get a quote</a>
<?php if (session('logged_in')): ?>
<a href="<?= site_url(session('role')==='technician'?'technician':(session('role')==='customer'?'dashboard':'staff')) ?>">Dashboard</a>
<?php if(session('role')!=='technician'): ?><a href="<?= site_url('payments') ?>">Payments</a><a href="<?= site_url('support') ?>">Support</a><?php endif; ?>
<form class="inline" action="<?= site_url('logout') ?>" method="post"><?= csrf_field() ?><button class="nav-logout" type="submit">Log out</button></form>
<?php else: ?><a href="<?= site_url('login') ?>">Log in</a><a class="btn nav-cta" href="<?= site_url('register') ?>">Sign up <span aria-hidden="true">↗</span></a><?php endif; ?>
</nav></div></header>
<main id="main-content">
<?php if (session()->getFlashdata('success')): ?><div class="wrap message success" role="status"><?= esc(session()->getFlashdata('success')) ?></div><?php endif; ?>
<?php if (session()->getFlashdata('error')): ?><div class="wrap message error" role="alert"><?= esc(session()->getFlashdata('error')) ?></div><?php endif; ?>
<?php if (session()->getFlashdata('errors')): ?><div class="wrap message error" role="alert"><ul><?php foreach(session()->getFlashdata('errors') as $error): ?><li><?= esc($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
