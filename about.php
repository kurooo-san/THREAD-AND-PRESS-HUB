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
            <p class="lead" style="color:var(--text-medium); font-size:1rem; line-height:1.7; margin-top:1rem;">Thread &amp; Press Hub was founded with a simple mission: to provide high-quality, stylish apparel for everyone. Our team of designers and fabric experts work hard to deliver premium products at affordable prices.</p>
            <p style="color:var(--text-light);">We believe in sustainability, comfort, and customer satisfaction. Every piece you purchase supports small-scale artisans and responsible manufacturing practices.</p>
        </div>
        <div class="col-md-6 text-center">
            <img src="images/hero/about-us.jpg" class="img-fluid" alt="About us" style="border-radius: var(--radius-lg);">
        </div>
    </div>

    <div class="py-5 mt-3">
        <div class="section-heading">
            <h2>Our Values</h2>
            <p>What drives us every day</p>
        </div>
        <div class="row g-4 text-center">
            <div class="col-md-4">
                <div class="card border-0 p-4" style="background:var(--bg-light); border-radius:var(--radius-lg);">
                    <i class="fas fa-leaf mb-3" style="font-size:2rem; color:var(--primary);"></i>
                    <h5 style="font-weight:700;">Eco-Friendly</h5>
                    <p class="text-muted small mb-0">We use sustainable materials and minimize waste in every step of production.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 p-4" style="background:var(--bg-light); border-radius:var(--radius-lg);">
                    <i class="fas fa-users mb-3" style="font-size:2rem; color:var(--primary);"></i>
                    <h5 style="font-weight:700;">Community</h5>
                    <p class="text-muted small mb-0">Supporting local artisans and fair labor practices across the Philippines.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 p-4" style="background:var(--bg-light); border-radius:var(--radius-lg);">
                    <i class="fas fa-gem mb-3" style="font-size:2rem; color:var(--primary);"></i>
                    <h5 style="font-weight:700;">Quality</h5>
                    <p class="text-muted small mb-0">Premium fabrics, durable construction, and attention to every detail.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer/footer.php'; ?>