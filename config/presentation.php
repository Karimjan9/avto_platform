<?php

return [
    'enabled' => (bool) env('MARKETPLACE_PRESENTATION_ENABLED', false),
    'notice' => 'Namoyish ma’lumotlari: e’lonlar, do‘konlar, belgilar va reytinglar test uchun ko‘rsatilgan. Haqiqiy savdo taklifi emas.',
    'owners' => [
        'showcase' => ['email' => 'showcase@demo.buxoroavto.invalid', 'name' => 'DEMO · BuxoroAvto namoyish'],
        'avtoqism' => ['email' => 'avtoqism@demo.buxoroavto.invalid', 'name' => 'DEMO · AvtoQism Buxoro'],
        'buxoroparts' => ['email' => 'buxoroparts@demo.buxoroavto.invalid', 'name' => 'DEMO · Buxoro Parts'],
    ],
    'images' => [
        'onix' => 'images/marketplace/demo-onix.webp',
        'tracker' => 'images/marketplace/demo-tracker.webp',
        'headlight' => 'images/marketplace/demo-headlight.webp',
        'yamaha' => 'images/marketplace/demo-yamaha.webp',
    ],
    'cards' => [
        'onix' => ['title' => 'Chevrolet Onix', 'owner' => 'showcase', 'badge' => 'Tekshirilgan', 'badge_type' => 'verified_business', 'image' => 'images/marketplace/demo-onix.webp'],
        'tracker' => ['title' => 'Chevrolet Tracker', 'owner' => 'showcase', 'badge' => 'Reklama', 'badge_type' => 'sponsored', 'image' => 'images/marketplace/demo-tracker.webp'],
        'headlight' => ['title' => 'Chevrolet Onix fara (o‘ng)', 'owner' => 'avtoqism', 'badge' => 'Tekshirilgan', 'badge_type' => 'verified_business', 'image' => 'images/marketplace/demo-headlight.webp'],
        'yamaha' => ['title' => 'Yamaha FZ-S 150', 'owner' => 'showcase', 'badge' => 'Tekshirilgan', 'badge_type' => 'verified_business', 'image' => 'images/marketplace/demo-yamaha.webp'],
    ],
    'businesses' => [
        'demo-avtoqism-buxoro' => ['name' => 'AvtoQism Buxoro', 'owner' => 'avtoqism', 'rating' => 4.8, 'reviews_count' => 320, 'badge' => 'Tekshirilgan do‘kon', 'label' => 'Demo do‘kon'],
        'demo-buxoro-parts' => ['name' => 'Buxoro Parts', 'owner' => 'buxoroparts', 'rating' => 4.6, 'reviews_count' => 280, 'badge' => 'Tekshirilgan do‘kon', 'label' => 'Demo do‘kon'],
    ],
    'featured_product' => [
        'identity_key' => hash('sha256', 'buxoroavto.demo.presentation.onix.right-headlight.v1'),
        'title' => 'Chevrolet Onix fara (o‘ng)',
        'part_number' => 'DEMO-ONIX-HL-R',
    ],
];
