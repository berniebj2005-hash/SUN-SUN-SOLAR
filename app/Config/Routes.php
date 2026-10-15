<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');
$routes->get('/register', 'Home::register');
$routes->post('/register', 'UserController::save');
$routes->get('/dashboard', 'Home::dashboard');
$routes->get('/login', 'Home::index');
$routes->post('/login', 'UserController::login');