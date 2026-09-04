<?php
require 'includes/config.php';
$pageTitle = 'Pages';
?>

<?php include 'includes/header/header.php'; ?>

<div class="container my-5">
    <div class="mb-5">
        <h1 class="display-5" style="font-weight: 800; margin-bottom: 1rem;">Information Pages</h1>
        <p class="lead text-muted">Everything you need to know about Thread and Press Hub</p>
    </div>

    <div class="row g-4">
        <!-- About Us -->
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card border-0 shadow-sm h-100 info-card" style="border-top: 4px solid var(--accent-green);">
                <div class="card-body">
                    <div style="font-size: 2rem; margin-bottom: 1rem;">
                        <i class="fas fa-store" style="color: var(--accent-green);"></i>
                    </div>
                    <h5 class="card-title" style="font-weight: 700;">About Us</h5>
                    <p class="card-text text-muted">Learn about our mission, values, and what makes Thread and Press Hub the go-to fashion destination for quality apparel and exceptional customer service.</p>
                    <a href="#about" class="btn btn-sm btn-outline-primary">Read More</a>
                </div>
            </div>
        </div>

        <!-- Shipping Info -->
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card border-0 shadow-sm h-100 info-card" style="border-top: 4px solid var(--accent-green);">
                <div class="card-body">
                    <div style="font-size: 2rem; margin-bottom: 1rem;">
                        <i class="fas fa-truck" style="color: var(--accent-green);"></i>
                    </div>
                    <h5 class="card-title" style="font-weight: 700;">Shipping Information</h5>
                    <p class="card-text text-muted">Find out about our shipping rates, delivery times, and tracking options. We deliver nationwide with fast and reliable service.</p>
                    <a href="#shipping" class="btn btn-sm btn-outline-primary">Read More</a>
                </div>
            </div>
        </div>

        <!-- Returns & Exchanges -->
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card border-0 shadow-sm h-100 info-card" style="border-top: 4px solid var(--accent-green);">
                <div class="card-body">
                    <div style="font-size: 2rem; margin-bottom: 1rem;">
                        <i class="fas fa-redo" style="color: var(--accent-green);"></i>
                    </div>
                    <h5 class="card-title" style="font-weight: 700;">Returns & Exchanges</h5>
                    <p class="card-text text-muted">Our hassle-free 30-day return policy ensures you shop with confidence. Learn how to initiate a return or exchange with ease.</p>
                    <a href="#returns" class="btn btn-sm btn-outline-primary">Read More</a>
                </div>
            </div>
        </div>

        <!-- Size Guide -->
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card border-0 shadow-sm h-100 info-card" style="border-top: 4px solid var(--accent-green);">
                <div class="card-body">
                    <div style="font-size: 2rem; margin-bottom: 1rem;">
                        <i class="fas fa-ruler" style="color: var(--accent-green);"></i>
                    </div>
                    <h5 class="card-title" style="font-weight: 700;">Size Guide</h5>
                    <p class="card-text text-muted">Find your perfect fit with our comprehensive size charts for all categories. Measurements in both US and metric units available.</p>
                    <a href="#sizes" class="btn btn-sm btn-outline-primary">Read More</a>
                </div>
            </div>
        </div>

        <!-- Privacy Policy -->
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card border-0 shadow-sm h-100 info-card" style="border-top: 4px solid var(--accent-green);">
                <div class="card-body">
                    <div style="font-size: 2rem; margin-bottom: 1rem;">
                        <i class="fas fa-lock" style="color: var(--accent-green);"></i>
                    </div>
                    <h5 class="card-title" style="font-weight: 700;">Privacy Policy</h5>
                    <p class="card-text text-muted">We care about your privacy. Read our comprehensive privacy policy to understand how we collect, use, and protect your personal information.</p>
                    <a href="#privacy" class="btn btn-sm btn-outline-primary">Read More</a>
                </div>
            </div>
        </div>

        <!-- Terms of Service -->
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card border-0 shadow-sm h-100 info-card" style="border-top: 4px solid var(--accent-green);">
                <div class="card-body">
                    <div style="font-size: 2rem; margin-bottom: 1rem;">
                        <i class="fas fa-file-contract" style="color: var(--accent-green);"></i>
                    </div>
                    <h5 class="card-title" style="font-weight: 700;">Terms of Service</h5>
                    <p class="card-text text-muted">Understand the terms and conditions that govern your use of Thread and Press Hub. Your rights and responsibilities as a customer.</p>
                    <a href="#terms" class="btn btn-sm btn-outline-primary">Read More</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Content Sections -->
    <hr class="my-5">

    <div id="about" class="mb-5">
        <h3 style="font-weight: 700; margin-bottom: 1.5rem;">About Thread and Press Hub</h3>
        <p>Thread and Press Hub is your ultimate destination for premium quality apparel and fashion-forward styles. Founded with a passion for bringing together style, comfort, and excellence, we've built a community of customers who trust us for their everyday wear and special occasion outfits.</p>
        <p>Our mission is simple: to provide the highest quality garments at fair prices, backed by exceptional customer service and a commitment to sustainability. We carefully curate our collection from trusted suppliers and emerging designers to ensure every piece meets our strict quality standards.</p>
        <h5 style="font-weight: 700; margin-top: 2rem; margin-bottom: 1rem;">Why Choose Us?</h5>
        <ul>
            <li><strong>Premium Quality:</strong> All items are made from high-quality fabrics and materials</li>
            <li><strong>Diverse Selection:</strong> From classic wardrobe staples to trendy pieces for every style</li>
            <li><strong>Customer-Focused:</strong> 24/7 support and hassle-free returns</li>
            <li><strong>Fair Pricing:</strong> Great value for money with regular promotions</li>
            <li><strong>Fast Shipping:</strong> Metro Manila delivery in 1-2 days, provincial in 3-5 days</li>
        </ul>
    </div>

    <div id="shipping" class="mb-5">
        <h3 style="font-weight: 700; margin-bottom: 1.5rem;">Shipping Information</h3>
        <p>We partner with reliable logistics companies to ensure your orders arrive safely and on time.</p>
        <table class="table">
            <thead>
                <tr>
                    <th>Delivery Area</th>
                    <th>Shipping Time</th>
                    <th>Cost</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Metro Manila</td>
                    <td>1-2 business days</td>
                    <td>₱50 (Free over ₱1,500)</td>
                </tr>
                <tr>
                    <td>Provincial (Nearby)</td>
                    <td>2-3 business days</td>
                    <td>₱100 (Free over ₱1,500)</td>
                </tr>
                <tr>
                    <td>Provincial (Remote)</td>
                    <td>3-5 business days</td>
                    <td>₱150 (Free over ₱2,000)</td>
                </tr>
            </tbody>
        </table>
        <p class="text-muted small"><strong>Note:</strong> All shipping times are estimates. Delays may occur due to weather, holidays, or logistics issues.</p>
    </div>

    <div id="returns" class="mb-5">
        <h3 style="font-weight: 700; margin-bottom: 1.5rem;">Returns & Exchanges</h3>
        <p>We want you to be completely satisfied with your purchase. If you're not happy with your order, we make returns and exchanges easy.</p>
        <h5 style="font-weight: 700; margin-top: 1.5rem; margin-bottom: 1rem;">30-Day Return Policy</h5>
        <ul>
            <li>Items must be unworn, unwashed, and in original condition</li>
            <li>Include original packaging and tags</li>
            <li>Return shipping is free for orders over ₱1,500</li>
            <li>Refunds are processed within 7-10 days after receipt</li>
            <li>Clearance items are final sale and cannot be returned</li>
        </ul>
        <p style="margin-top: 1.5rem;">To initiate a return or exchange, <a href="contact.php">contact our customer service team</a>.</p>
    </div>

    <div id="sizes" class="mb-5">
        <h3 style="font-weight: 700; margin-bottom: 1.5rem;">Size Guide</h3>
        <p>Find your perfect fit using our comprehensive size charts below.</p>
        <h5 style="font-weight: 700; margin-top: 1.5rem; margin-bottom: 1rem;">Women's Apparel</h5>
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>Size</th>
                    <th>Bust (inches)</th>
                    <th>Waist (inches)</th>
                    <th>Hip (inches)</th>
                </tr>
            </thead>
            <tbody>
                <tr><td>XS</td><td>32-34</td><td>24-26</td><td>34-36</td></tr>
                <tr><td>S</td><td>34-36</td><td>26-28</td><td>36-38</td></tr>
                <tr><td>M</td><td>36-38</td><td>28-30</td><td>38-40</td></tr>
                <tr><td>L</td><td>38-40</td><td>30-32</td><td>40-42</td></tr>
                <tr><td>XL</td><td>40-42</td><td>32-34</td><td>42-44</td></tr>
            </tbody>
        </table>
    </div>

    <div id="privacy" class="mb-5">
        <h3 style="font-weight: 700; margin-bottom: 1.5rem;">Privacy Policy</h3>
        <p>Thread and Press Hub is committed to protecting your privacy. We collect personal information only when necessary to process your orders and improve our services.</p>
        <p><strong>Information We Collect:</strong> Your name, email, phone number, shipping address, and payment information are collected only when you place an order.</p>
        <p><strong>How We Use Information:</strong> We use your information to process orders, send shipment updates, and respond to your inquiries. We do not sell or share your information with third parties without your consent.</p>
    </div>

    <div id="terms" class="mb-5">
        <h3 style="font-weight: 700; margin-bottom: 1.5rem;">Terms of Service</h3>
        <p>By using Thread and Press Hub, you agree to these terms and conditions. Please read them carefully.</p>
        <ul>
            <li>You must be at least 18 years old to make purchases</li>
            <li>All prices are subject to change without notice</li>
            <li>We reserve the right to cancel orders that violate our policies</li>
            <li>Product images are for reference only and may not reflect exact colors</li>
            <li>By providing feedback, you grant us the right to use it for promotional purposes</li>
        </ul>
    </div>
</div>

<style>
    .info-card {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .info-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 12px 24px rgba(0, 0, 0, 0.12) !important;
    }
</style>

<?php include 'includes/footer/footer.php'; ?>
