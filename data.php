<?php

/**
 * data.php
 * MY Buddy – Hyperlocal Malaysia AI Buddy
 *
 * Stores local Malaysian recommendation data as a PHP array.
 * No database needed — just include this file wherever you need the data.
 *
 * Each item has:
 *   id          – unique number
 *   name        – recommendation name
 *   type        – Food | Place | Activity | Product | Service
 *   description – short description
 *   area        – Malaysian state or city
 *   price_min   – lowest expected price in RM
 *   price_max   – highest expected price in RM
 *   category    – Local | Non-local
 *   mood_tags   – keywords that match user mood or intent
 *   rating      – score out of 5
 *   feedback    – one-line user-style review
 */

$recommendations = [

    // ── FOOD ────────────────────────────────────────────────────────────────

    [
        "id"          => 1,
        "name"        => "Nasi Lemak Ayam Goreng",
        "type"        => "Food",
        "description" => "Classic Malaysian meal with fragrant rice, sambal, egg, peanuts, cucumber, and crispy fried chicken.",
        "area"        => "Kuala Lumpur",
        "price_min"   => 8,
        "price_max"   => 15,
        "category"    => "Local",
        "mood_tags"   => ["hungry", "comfort", "local", "makan"],
        "rating"      => 4.8,
        "feedback"    => "Sedap, filling, and easy to find around Malaysia."
    ],
    [
        "id"          => 2,
        "name"        => "Roti Canai Breakfast",
        "type"        => "Food",
        "description" => "Affordable Malaysian breakfast served with dhal or curry.",
        "area"        => "Selangor",
        "price_min"   => 2,
        "price_max"   => 7,
        "category"    => "Local",
        "mood_tags"   => ["breakfast", "cheap", "lepak", "mamak"],
        "rating"      => 4.7,
        "feedback"    => "Best for a quick morning meal or casual lepak."
    ],
    [
        "id"          => 3,
        "name"        => "Kopitiam Coffee and Kaya Toast",
        "type"        => "Food",
        "description" => "Traditional kopitiam-style coffee with kaya toast and half-boiled eggs.",
        "area"        => "Penang",
        "price_min"   => 5,
        "price_max"   => 12,
        "category"    => "Local",
        "mood_tags"   => ["chill", "breakfast", "coffee", "heritage"],
        "rating"      => 4.6,
        "feedback"    => "Good for slow mornings and traditional local vibes."
    ],

    // ── PLACES ──────────────────────────────────────────────────────────────

    [
        "id"          => 4,
        "name"        => "KLCC Park Evening Walk",
        "type"        => "Place",
        "description" => "A relaxing outdoor spot for walking, photos, and city views.",
        "area"        => "Kuala Lumpur",
        "price_min"   => 0,
        "price_max"   => 10,
        "category"    => "Local",
        "mood_tags"   => ["relax", "walk", "weekend", "nature"],
        "rating"      => 4.5,
        "feedback"    => "Nice place to relax without spending much."
    ],
    [
        "id"          => 6,
        "name"        => "Bubble Tea Cafe Hangout",
        "type"        => "Place",
        "description" => "Modern cafe drink option for casual hangout or study session.",
        "area"        => "Johor Bahru",
        "price_min"   => 8,
        "price_max"   => 20,
        "category"    => "Non-local",
        "mood_tags"   => ["sweet", "cafe", "study", "hangout"],
        "rating"      => 4.2,
        "feedback"    => "Good for a modern cafe mood, but not the cheapest option."
    ],

    // ── ACTIVITIES ──────────────────────────────────────────────────────────

    [
        "id"          => 5,
        "name"        => "Pasar Malam Food Hunt",
        "type"        => "Activity",
        "description" => "Explore local street food, snacks, drinks, and affordable products at a night market.",
        "area"        => "Selangor",
        "price_min"   => 10,
        "price_max"   => 30,
        "category"    => "Local",
        "mood_tags"   => ["food", "night", "weekend", "jalan-jalan"],
        "rating"      => 4.8,
        "feedback"    => "Great for students, friends, and weekend makan plans."
    ],

    // ── PRODUCTS ────────────────────────────────────────────────────────────

    [
        "id"          => 7,
        "name"        => "Batik Tote Bag",
        "type"        => "Product",
        "description" => "A practical Malaysian-style tote bag suitable for daily use or gifts.",
        "area"        => "Malaysia",
        "price_min"   => 15,
        "price_max"   => 45,
        "category"    => "Local",
        "mood_tags"   => ["gift", "fashion", "local", "budget"],
        "rating"      => 4.4,
        "feedback"    => "Useful and supports local design."
    ],

    // ── SERVICES ────────────────────────────────────────────────────────────

    [
        "id"          => 8,
        "name"        => "Online Food Delivery Deal",
        "type"        => "Service",
        "description" => "Convenient food delivery option for users who prefer staying at home.",
        "area"        => "Malaysia",
        "price_min"   => 15,
        "price_max"   => 40,
        "category"    => "Non-local",
        "mood_tags"   => ["lazy", "home", "delivery", "hungry"],
        "rating"      => 4.3,
        "feedback"    => "Convenient when you do not want to go out."
    ],

];
