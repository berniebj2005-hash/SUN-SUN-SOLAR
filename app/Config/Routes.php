<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Site::index');
$routes->get('products', 'Site::products');
$routes->get('services', 'Site::services');
$routes->get('quote', 'Site::quote');
$routes->post('quote', 'Site::submitQuote');
$routes->get('register', 'Auth::register');
$routes->post('register', 'Auth::registerProcess');
$routes->get('login', 'Auth::login');
$routes->post('login', 'Auth::loginProcess');
$routes->post('logout', 'Auth::logout');
$routes->get('dashboard', 'Customer::dashboard');
$routes->get('bookings/new', 'Customer::newBooking');
$routes->post('bookings', 'Customer::createBooking');

$routes->get('staff', 'Staff::dashboard');
$routes->get('staff/employees/new', 'Staff::newEmployee');
$routes->post('staff/employees', 'Staff::createEmployee');
$routes->post('staff/bookings/status', 'Staff::changeBooking');
$routes->post('staff/bookings/assign', 'Staff::assignTechnician');
$routes->get('technician', 'Technician::index');
$routes->post('technician/checkin', 'Technician::checkin');
$routes->post('technician/report', 'Technician::report');
$routes->get('payments', 'Billing::index');
$routes->post('payments', 'Billing::record');
$routes->get('support', 'Support::index');
$routes->post('support', 'Support::create');
$routes->get('support/(:num)', 'Support::show/$1');
$routes->post('support/(:num)/reply', 'Support::reply/$1');
$routes->post('support/(:num)/status', 'Support::status/$1');