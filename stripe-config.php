<?php
require_once __DIR__ . '/stripe-php-master/init.php';

\Stripe\Stripe::setApiKey('sk_test_51S7aZAR19uamX4sZHRFn88T4NKrE0WwnMx3cpD0rXWSDlOQt2XhOhpTLhrO6g9WRfuAKGGcxVSKjAj4Qb0g07aTJ00yXdQ8j0K');
define('STRIPE_WEBHOOK_SECRET', 'whsec_U0tXQNbS5sdWanBoOPWJdb6JmtkdEufZ');