<?php
require 'includes/config.php';
$pageTitle = 'About';
?>

<?php include 'includes/header/header.php'; ?>

<div class="container py-5">
    <div class="row align-items-center g-5">
        <div class="col-md-6">
            <span class="hero-badge" style="background: var(--bg-light); color: var(--text-dark); border-color: var(--border-light);">Our Story</span>
            <h1 style="font-weight:800; font-size:2.5rem; line-height:1.15; margin-top:0.75rem;">About Thread &amp; Press Hub</h1>
            <p class="lead" style="color:var(--text-medium); font-size:1rem; line-height:1.7; margin-top:1rem;">Thread &amp; Press Hub is an apparel shop based in Rizal that does two things: it sells ready-to-wear clothing for men, women and kids, and it prints your own designs on shirts, hoodies and polos.</p>
            <p style="color:var(--text-light);">Orders ship anywhere in the Philippines, or you can pick them up at the store for free. PWD and Senior Citizen customers get their 20% discount applied at checkout.</p>
        </div>
        <div class="col-md-6 text-center">
            <img src="images/hero/about-us.jpg" class="img-fluid" alt="About us" style="border-radius: var(--radius-lg);">
        </div>
    </div>

    <div class="py-5 mt-3">
        <div class="section-heading">
            <h2>What We Do</h2>
            <p>Ready-made, or made by you</p>
        </div>
        <div class="row g-4 text-center">
            <div class="col-md-4">
                <div class="card border-0 p-4" style="background:var(--bg-light); border-radius:var(--radius-lg);">
                    <i class="fas fa-shirt mb-3" style="font-size:2rem; color:var(--primary);"></i>
                    <h5 style="font-weight:700;">Ready-to-Wear</h5>
                    <p class="text-muted small mb-0">Dresses, tees, hoodies, pants and accessories, each listed with the colors and sizes in stock.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 p-4" style="background:var(--bg-light); border-radius:var(--radius-lg);">
                    <i class="fas fa-palette mb-3" style="font-size:2rem; color:var(--primary);"></i>
                    <h5 style="font-weight:700;">Custom Printing</h5>
                    <p class="text-muted small mb-0">Lay out your print in the Design Studio on a tee, hoodie, polo, couple set or company uniform, with the price shown as you design.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 p-4" style="background:var(--bg-light); border-radius:var(--radius-lg);">
                    <i class="fas fa-wand-magic-sparkles mb-3" style="font-size:2rem; color:var(--primary);"></i>
                    <h5 style="font-weight:700;">Try Before You Buy</h5>
                    <p class="text-muted small mb-0">AI Try-On shows a garment on your own photo, and the size finder suggests a size from your height and weight.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer/footer.php'; ?>