<?php

return [
    'email' => env('CONTACT_EMAIL', 'lrnhub24@gmail.com'),
    'phone' => env('CONTACT_PHONE', '+917020275309'),
    'phone_link' => env('CONTACT_PHONE_LINK', '+917020275309'),
    'address_lines' => array_values(array_filter([
        env('CONTACT_ADDRESS_LINE_1', '26 Nandas House Vishwakarma Nagar'),
        env('CONTACT_ADDRESS_LINE_2', 'Nagpur, 440027'),
    ])),
    'response_time' => env('CONTACT_RESPONSE_TIME', 'Response within 24 hours'),
    'working_hours' => env('CONTACT_WORKING_HOURS', 'Mon-Fri, 9am-5pm'),
];
