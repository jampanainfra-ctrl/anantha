<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../includes/routes.php';
$tests=[
    ['','', '/anantha/'],['index.php','', '/anantha/'],['gallery.php','', '/anantha/galary/'],['gallery/','', '/anantha/galary/'],['galary','', '/anantha/galary/'],
    ['gallery.php?category=Rooms#gallery','', '/anantha/galary/?category=Rooms#gallery'],['availability.php','', '/anantha/availability/'],
    ['admin/login.php','', '/anantha/ceoadmin/'],['admin/index.php','', '/anantha/ceoadmin/'],['login.php','admin', '/anantha/ceoadmin/'],
    ['dashboard.php','admin', '/anantha/ceoadmin/dashboard/'],['gallery.php','admin', '/anantha/ceoadmin/gallery/'],['admin','admin','/anantha/ceoadmin/'],
    ['booking-form.php?id=7#payments','admin', '/anantha/ceoadmin/booking-form/?id=7#payments'],
    ['../availability.php','admin', '/anantha/availability/'],['../api/availability.php?date=2026-11-01','admin', '/anantha/api/availability/?date=2026-11-01'],
    ['api/index.php','', '/anantha/api/'],['api/reviews.php','', '/anantha/api/reviews/'],['../assets/forms.css','admin', '/anantha/assets/forms.css'],
    ['assets/logo.jpeg','', '/anantha/assets/logo.jpeg'],['uploads/gallery/picture.jpg','admin', '/anantha/uploads/gallery/picture.jpg'],
    ['#payments','admin', '#payments'],['?q=test','admin', '?q=test'],['https://example.com/file.php','', 'https://example.com/file.php'],
    ['tel:+917995011199','', 'tel:+917995011199'],['mailto:hello@example.com','', 'mailto:hello@example.com'],
    ['/anantha/ceoadmin/bookings/?q=test','admin', '/anantha/ceoadmin/bookings/?q=test'],['/ceoadmin/','', '/anantha/ceoadmin/'],
];
putenv('ANANTHA_BASE_PATH=/anantha');$checks=0;
foreach($tests as[$input,$context,$expected]){if(app_url($input,$context)!==$expected)throw new RuntimeException('URL mismatch: '.$input.' -> '.app_url($input,$context));$checks++;}
putenv('ANANTHA_BASE_PATH=');
foreach([['gallery.php','/galary/'],['admin/login.php','/ceoadmin/'],['admin/bookings.php','/ceoadmin/bookings/'],['assets/logo.jpeg','/assets/logo.jpeg']]as[$input,$expected]){if(app_url($input)!==$expected)throw new RuntimeException('Root deployment mismatch');$checks++;}
putenv('ANANTHA_BASE_PATH=/events/anantha/');
if(app_url('gallery.php')!=='/events/anantha/galary/')throw new RuntimeException('Nested deployment mismatch');$checks++;
putenv('ANANTHA_BASE_PATH');echo "PASS: $checks clean URL checks.\n";
