<?php
require 'includes/config.php';
require 'includes/apparel-config.php';
redirectToLogin();

$pageTitle = 'Design Your Apparel';
$apparelConfig = getApparelConfig();

// Get user discount type
$user_discount = 'regular';
$user_stmt = $conn->prepare("SELECT user_type FROM users WHERE id = ?");
$user_stmt->bind_param("i", $_SESSION['user_id']);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
if ($user_result->num_rows > 0) {
    $user_data = $user_result->fetch_assoc();
    $user_discount = $user_data['user_type'];
}
$user_stmt->close();

// Run migration if table doesn't exist
$tableCheck = $conn->query("SHOW TABLES LIKE 'custom_designs'");
if ($tableCheck->num_rows === 0) {
    $migrationSQL = file_get_contents(__DIR__ . '/database/migrate_custom_designs.sql');
    if ($migrationSQL) {
        $conn->multi_query($migrationSQL);
        while ($conn->next_result()) {;}
    }
}
?>

<?php include 'includes/header/header.php'; ?>

<link rel="stylesheet" href="css/design-modern.css">

<style>
/* Design Tool Styles */
.design-tool-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 1.5rem;
}

.design-tool-header {
    text-align: center;
    margin-bottom: 2rem;
}

.design-tool-header h1 {
    font-size: 2.2rem;
    font-weight: 800;
    color: var(--text-dark, #1a1a1a);
}

.design-tool-header p {
    color: var(--text-light, #666);
    font-size: 1rem;
    max-width: 600px;
    margin: 0.5rem auto 0;
}

.design-workspace {
    display: grid;
    grid-template-columns: 280px 1fr 300px;
    gap: 1.5rem;
    min-height: 650px;
}

/* Left Toolbar */
.design-toolbar {
    background: #fff;
    border-radius: 16px;
    padding: 1.25rem;
    box-shadow: 0 2px 16px rgba(0,0,0,0.06);
    border: 1px solid var(--border-light, #e5e5e5);
    overflow-y: auto;
    max-height: 700px;
}

.tool-section {
    margin-bottom: 1.25rem;
    padding-bottom: 1.25rem;
    border-bottom: 1px solid var(--border-light, #eee);
}

.tool-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.tool-section h6 {
    font-weight: 700;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-light, #888);
    margin-bottom: 0.75rem;
}

.tool-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    width: 100%;
    padding: 0.6rem 0.75rem;
    border: 1px solid var(--border-light, #e0e0e0);
    background: #fff;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.85rem;
    color: var(--text-dark, #333);
    margin-bottom: 0.4rem;
}

.tool-btn:hover {
    background: #f8f9fa;
    border-color: #ccc;
}

.tool-btn.active {
    background: var(--accent-green, #2d6a4f);
    color: #fff;
    border-color: var(--accent-green, #2d6a4f);
}

.tool-btn i {
    width: 18px;
    text-align: center;
}

/* Apparel Type Selector */
.apparel-types {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 0.5rem;
}

.apparel-type-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.3rem;
    padding: 0.6rem 0.4rem;
    border: 2px solid var(--border-light, #e0e0e0);
    background: #fff;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.72rem;
    font-weight: 600;
}

.apparel-type-btn:hover {
    border-color: var(--accent-green, #2d6a4f);
}

.apparel-type-btn.active {
    border-color: var(--accent-green, #2d6a4f);
    background: rgba(45,106,79,0.06);
    color: var(--accent-green, #2d6a4f);
}

.apparel-type-btn i {
    font-size: 1.4rem;
}

/* Color Picker */
.color-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.color-swatch-btn {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    border: 2px solid #ddd;
    cursor: pointer;
    transition: all 0.15s;
    padding: 0;
}

.color-swatch-btn:hover,
.color-swatch-btn.active {
    transform: scale(1.15);
    border-color: #333;
    box-shadow: 0 0 0 2px rgba(0,0,0,0.15);
}

/* Brush Size */
.brush-size-slider {
    width: 100%;
    accent-color: var(--accent-green, #2d6a4f);
}

/* Canvas Area */
.design-canvas-area {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 2px 16px rgba(0,0,0,0.06);
    border: 1px solid var(--border-light, #e5e5e5);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.canvas-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--border-light, #eee);
    background: #fafafa;
}

.canvas-toolbar-left,
.canvas-toolbar-right {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.canvas-action-btn {
    padding: 0.4rem 0.6rem;
    border: 1px solid #e0e0e0;
    background: #fff;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.8rem;
    transition: all 0.15s;
    color: #555;
}

.canvas-action-btn:hover {
    background: #f0f0f0;
    color: #222;
}

.canvas-action-btn.danger:hover {
    background: #fee;
    color: #c0392b;
    border-color: #f5c6cb;
}

.side-toggle-btn {
    font-weight: 600;
    padding: 0.4rem 0.8rem;
}

.side-toggle-btn.active {
    background: var(--accent-green, #2ECC40);
    color: #fff;
    border-color: var(--accent-green, #2ECC40);
}

.side-toggle-btn.active:hover {
    background: #27ae60;
    color: #fff;
}

.canvas-wrapper {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
    background: repeating-conic-gradient(#f0f0f0 0% 25%, #fafafa 0% 50%) 50% / 20px 20px;
    position: relative;
    overflow: hidden;
}

.mockup-container {
    position: relative;
    width: 400px;
    height: 500px;
}

.mockup-svg {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: 1;
}

#designCanvas {
    position: absolute;
    z-index: 2;
    cursor: crosshair;
    border-radius: 4px;
}

/* Right Panel - Preview & Settings */
.design-preview-panel {
    background: #fff;
    border-radius: 16px;
    padding: 1.25rem;
    box-shadow: 0 2px 16px rgba(0,0,0,0.06);
    border: 1px solid var(--border-light, #e5e5e5);
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.preview-section h6 {
    font-weight: 700;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-light, #888);
    margin-bottom: 0.75rem;
}

.live-preview-box {
    width: 100%;
    aspect-ratio: 3/4;
    background: #f8f9fa;
    border-radius: 12px;
    border: 1px solid #eee;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    position: relative;
}

.live-preview-box img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.preview-placeholder {
    text-align: center;
    color: #bbb;
}

.preview-placeholder i {
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
}

/* Text Input */
.text-input-group {
    display: flex;
    gap: 0.4rem;
}

.text-input-group input {
    flex: 1;
    padding: 0.5rem 0.7rem;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 0.85rem;
}

.text-input-group button {
    padding: 0.5rem 0.7rem;
    border: none;
    background: var(--accent-green, #2d6a4f);
    color: #fff;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.8rem;
}

/* Font Selector */
.font-selector {
    width: 100%;
    padding: 0.45rem 0.6rem;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 0.85rem;
    background: #fff;
    margin-bottom: 0.5rem;
}

/* Upload Area */
.upload-area {
    border: 2px dashed #ddd;
    border-radius: 12px;
    padding: 1rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
    background: #fafafa;
}

.upload-area:hover {
    border-color: var(--accent-green, #2d6a4f);
    background: rgba(45,106,79,0.03);
}

.upload-area i {
    font-size: 1.5rem;
    color: #bbb;
    margin-bottom: 0.4rem;
}

.upload-area p {
    font-size: 0.78rem;
    color: #999;
    margin: 0;
}

/* Submit Section */
.submit-section {
    margin-top: auto;
}

.submit-section textarea {
    width: 100%;
    padding: 0.6rem;
    border: 1px solid #ddd;
    border-radius: 10px;
    resize: vertical;
    min-height: 60px;
    font-size: 0.85rem;
    margin-bottom: 0.75rem;
}

.btn-submit-design {
    width: 100%;
    padding: 0.75rem 1.5rem;
    border: none;
    background: var(--accent-green, #2d6a4f);
    color: #fff;
    border-radius: 12px;
    font-size: 0.95rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.btn-submit-design:hover {
    background: #245a42;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(45,106,79,0.3);
}

.btn-submit-design:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

/* Apparel Color Selector */
.apparel-color-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.apparel-color-btn {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    border: 2px solid #ddd;
    cursor: pointer;
    transition: all 0.15s;
    padding: 0;
}

.apparel-color-btn:hover,
.apparel-color-btn.active {
    transform: scale(1.15);
    border-color: #333;
}

/* My Designs Section */
.my-designs-section {
    margin-top: 2rem;
}

.designs-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 1rem;
}

.design-card {
    background: #fff;
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid #eee;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    transition: all 0.2s;
}

.design-card:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,0.08);
}

.design-card-img {
    width: 100%;
    aspect-ratio: 1;
    object-fit: cover;
    background: #f8f9fa;
}

.design-card-body {
    padding: 0.75rem;
}

.design-card-body h6 {
    font-weight: 700;
    margin-bottom: 0.25rem;
    font-size: 0.85rem;
}

.design-card-body small {
    color: #999;
}

.design-status-badge {
    display: inline-block;
    padding: 0.2rem 0.5rem;
    border-radius: 6px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
}

.design-status-badge.pending { background: #fff3cd; color: #856404; }
.design-status-badge.approved { background: #d4edda; color: #155724; }
.design-status-badge.revision { background: #f8d7da; color: #721c24; }
.design-status-badge.completed { background: #d1ecf1; color: #0c5460; }
.design-status-badge.cancelled { background: #e2e3e5; color: #383d41; }

/* Responsive */
@media (max-width: 1100px) {
    .design-workspace {
        grid-template-columns: 220px 1fr;
    }
    .design-preview-panel {
        grid-column: 1 / -1;
        flex-direction: row;
        flex-wrap: wrap;
    }
    .live-preview-box {
        aspect-ratio: auto;
        height: 200px;
        width: 200px;
    }
}

@media (max-width: 768px) {
    .design-workspace {
        grid-template-columns: 1fr;
    }
    /* The mockup keeps its 400x500 layout and is scaled to fit by
       applyMockupScale(), so the canvas and elements stay on the shirt. */
    .canvas-wrapper { padding: 0.75rem; }
    .canvas-toolbar { flex-wrap: wrap; gap: 0.4rem; }
    .canvas-toolbar-left { flex-wrap: wrap; }
    .design-tool-header h1 {
        font-size: 1.6rem;
    }
}

/* Draggable elements */
.draggable-element {
    position: absolute;
    cursor: move;
    z-index: 10;
    user-select: none;
    border: 2px solid transparent;
    padding: 2px;
}

.draggable-element:hover,
.draggable-element.selected {
    border-color: var(--accent-green, #2d6a4f);
}

.draggable-element .resize-handle {
    position: absolute;
    width: 10px;
    height: 10px;
    background: var(--accent-green, #2d6a4f);
    border-radius: 50%;
    bottom: -5px;
    right: -5px;
    cursor: se-resize;
    display: none;
}

.draggable-element.selected .resize-handle {
    display: block;
}

.draggable-element .delete-handle {
    position: absolute;
    width: 18px;
    height: 18px;
    background: #e74c3c;
    color: #fff;
    border-radius: 50%;
    top: -8px;
    right: -8px;
    cursor: pointer;
    display: none;
    font-size: 10px;
    line-height: 18px;
    text-align: center;
}

.draggable-element.selected .delete-handle {
    display: block;
}

/* ===== 3D Preview ===== */
.preview-3d-container {
    perspective: 800px;
    width: 100%;
    aspect-ratio: 3/4;
    position: relative;
    cursor: grab;
}
.preview-3d-container:active { cursor: grabbing; }
.preview-3d-inner {
    width: 100%;
    height: 100%;
    position: relative;
    transform-style: preserve-3d;
    transition: transform 0.1s ease-out;
}
.preview-3d-inner.spinning {
    animation: spin3d 6s linear infinite;
}
.preview-3d-face {
    position: absolute;
    width: 100%;
    height: 100%;
    backface-visibility: hidden;
    border-radius: 12px;
    overflow: hidden;
    background: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: center;
}
.preview-3d-face canvas {
    max-width: 100%;
    max-height: 100%;
}
.preview-3d-front { transform: rotateY(0deg); }
.preview-3d-back { transform: rotateY(180deg); }
.preview-3d-controls {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    margin-top: 0.5rem;
}
.preview-3d-controls button {
    padding: 0.3rem 0.6rem;
    border: 1px solid #e0e0e0;
    background: #fff;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.75rem;
    transition: all 0.15s;
    color: #555;
}
.preview-3d-controls button:hover { background: #f0f0f0; }
.preview-3d-controls button.active { background: var(--accent-green, #2d6a4f); color: #fff; border-color: var(--accent-green); }
.preview-3d-label {
    font-size: 0.7rem;
    color: #aaa;
    text-align: center;
    margin-top: 0.25rem;
}

@keyframes spin3d {
    from { transform: rotateY(0deg); }
    to { transform: rotateY(360deg); }
}

/* ===== Apparel Tabs (Basic / Couple / Corporate) ===== */
.apparel-tabs {
    display: flex;
    gap: 0.3rem;
    margin-bottom: 0.6rem;
}
.apparel-tab {
    flex: 1;
    padding: 0.4rem 0.3rem;
    border: 1px solid var(--border-light, #e0e0e0);
    background: #fff;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.66rem;
    font-weight: 700;
    color: #666;
    transition: all 0.15s;
    white-space: nowrap;
}
.apparel-tab i { display: block; font-size: 0.85rem; margin-bottom: 0.15rem; }
.apparel-tab:hover { border-color: var(--accent-green, #2d6a4f); }
.apparel-tab.active {
    background: var(--accent-green, #2d6a4f);
    color: #fff;
    border-color: var(--accent-green, #2d6a4f);
}

/* ===== Partner Toggle (Couple Wear) ===== */
.partner-toggle { display: inline-flex; align-items: center; gap: 0.4rem; }
.partner-btn.active {
    background: #d6336c;
    color: #fff;
    border-color: #d6336c;
}
.partner-btn.active:hover { background: #b02a59; color: #fff; }

/* ===== Corporate Logo Placement Guide ===== */
.logo-zone-guide {
    position: absolute;
    z-index: 5;   /* above the artwork, like the logo in the print; clicks pass through */
    flex-direction: column;
    border: 2px dashed rgba(45,106,79,0.75);
    border-radius: 6px;
    background: rgba(45,106,79,0.06);
    pointer-events: none;
    display: flex;
    align-items: center;
    justify-content: center;
}
.logo-zone-guide span {
    font-size: 0.6rem;
    font-weight: 800;
    letter-spacing: 1.5px;
    color: rgba(45,106,79,0.8);
}
.logo-zone-guide small { font-size: 0.5rem; color: rgba(45,106,79,0.7); line-height: 1.1; }
.logo-zone-guide img { position: absolute; inset: 0; width: 100%; height: 100%; }
.logo-zone-guide.filled { border-color: rgba(45,106,79,0.3); background: none; }
.logo-zone-guide.filled span, .logo-zone-guide.filled small { display: none; }

/* ===== Couple Wear Side-by-Side Preview ===== */
.couple-preview-grid { display: flex; gap: 0.5rem; }
.couple-preview-cell { flex: 1; text-align: center; }
.couple-preview-cell canvas {
    width: 100%;
    height: auto;
    background: #f8f9fa;
    border: 1px solid #eee;
    border-radius: 10px;
}
.couple-preview-label {
    font-size: 0.7rem;
    font-weight: 700;
    color: #d6336c;
    margin-top: 0.25rem;
}

/* ===== Animal Stamps ===== */
.stamp-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 0.35rem;
    margin-top: 0.5rem;
}
.stamp-btn {
    aspect-ratio: 1/1;
    border: 1px solid var(--border-light, #e0e0e0);
    background: #fff;
    border-radius: 8px;
    cursor: pointer;
    padding: 4px;
    transition: all 0.15s;
    display: flex;
    align-items: center;
    justify-content: center;
}
.stamp-btn:hover {
    border-color: var(--accent-green, #2d6a4f);
    background: #f0fdf4;
    transform: translateY(-1px);
}
.stamp-btn svg { width: 100%; height: 100%; display: block; }

/* ===== Price Calculator ===== */
.price-calculator {
    background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);
    border: 1px solid #bbf7d0;
    border-radius: 12px;
    padding: 0.85rem;
}
.price-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.8rem;
    color: #555;
    padding: 0.3rem 0;
}
.price-row.total {
    border-top: 2px solid #86efac;
    margin-top: 0.4rem;
    padding-top: 0.5rem;
    font-weight: 800;
    font-size: 1rem;
    color: var(--accent-green, #2d6a4f);
}
.price-label { color: #666; }
.price-value { font-weight: 600; color: #333; }
.price-select {
    padding: 0.3rem 0.5rem;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 0.78rem;
    background: #fff;
}

/* ===== Rotate handle (element transform) ===== */
.draggable-element .rotate-handle {
    position: absolute;
    width: 18px;
    height: 18px;
    background: var(--accent-green, #2d6a4f);
    border: 2px solid #fff;
    border-radius: 50%;
    top: -28px;
    left: 50%;
    transform: translateX(-50%);
    cursor: grab;
    display: none;
    box-shadow: 0 1px 4px rgba(0,0,0,0.3);
}
.draggable-element .rotate-handle::after {
    content: '\21bb';
    color: #fff;
    font-size: 11px;
    line-height: 14px;
    display: block;
    text-align: center;
}
.draggable-element.selected .rotate-handle { display: block; }

/* Touch screens: handles big enough for a finger (the mockup is also scaled
   down to fit a phone, so they need the extra size). */
@media (pointer: coarse) {
    .draggable-element .resize-handle {
        width: 36px; height: 36px; bottom: -18px; right: -18px;
        border: 3px solid #fff; box-shadow: 0 1px 4px rgba(0,0,0,0.3);
    }
    .draggable-element .delete-handle {
        width: 36px; height: 36px; top: -18px; right: -18px;
        font-size: 20px; line-height: 36px;
    }
    .draggable-element .rotate-handle { width: 38px; height: 38px; top: -54px; }
    .draggable-element .rotate-handle::after { font-size: 20px; line-height: 34px; }
}

/* ===== AI Design Generator panel ===== */
.ai-design-section {
    background: linear-gradient(135deg, rgba(45,106,79,0.06), rgba(200,169,110,0.08));
    border: 1px solid #e6e1d5 !important;
    border-radius: 10px;
}
.ai-pill {
    font-size: 0.6rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    background: #1a1a1a;
    color: #fff;
    padding: 2px 7px;
    border-radius: 999px;
    margin-left: 4px;
    vertical-align: middle;
}
.ai-design-hint {
    font-size: 0.72rem;
    color: #888;
    margin: 0 0 0.5rem;
    line-height: 1.3;
}
.ai-prompt {
    width: 100%;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 0.5rem;
    font-size: 0.82rem;
    resize: vertical;
    font-family: inherit;
    box-sizing: border-box;
}
.ai-prompt:focus { outline: none; border-color: var(--accent-green, #2d6a4f); }
.ai-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.3rem;
    margin: 0.5rem 0;
}
.ai-chip {
    border: 1px solid #ddd;
    background: #fff;
    border-radius: 999px;
    font-size: 0.7rem;
    padding: 0.25rem 0.6rem;
    cursor: pointer;
    color: #555;
    transition: all 0.15s ease;
}
.ai-chip:hover { border-color: var(--accent-green, #2d6a4f); color: var(--accent-green, #2d6a4f); }
.ai-generate-btn {
    width: 100%;
    border: none;
    border-radius: 8px;
    padding: 0.6rem;
    font-weight: 700;
    font-size: 0.85rem;
    color: #fff;
    background: linear-gradient(135deg, #2d6a4f, #1a1a1a);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.ai-generate-btn:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(45,106,79,0.3); }
.ai-generate-btn:disabled { opacity: 0.7; cursor: wait; }

/* ===== Layers panel ===== */
.layers-list {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    max-height: 230px;
    overflow-y: auto;
}
.layers-empty {
    font-size: 0.72rem;
    color: #aaa;
    text-align: center;
    padding: 0.75rem 0.25rem;
    line-height: 1.4;
}
.layer-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.35rem 0.4rem;
    border: 1px solid #eee;
    border-radius: 8px;
    cursor: pointer;
    background: #fff;
    transition: all 0.15s ease;
}
.layer-row:hover { border-color: #ccc; }
.layer-row.selected { border-color: var(--accent-green, #2d6a4f); background: rgba(45,106,79,0.06); }
.layer-thumb {
    width: 30px;
    height: 30px;
    flex-shrink: 0;
    border-radius: 6px;
    background: #f4f4f4;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    color: #888;
    font-size: 0.85rem;
}
.layer-thumb img { width: 100%; height: 100%; object-fit: contain; }
.layer-label {
    flex: 1;
    min-width: 0;
    font-size: 0.78rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: #333;
}
.layer-actions { display: flex; gap: 1px; flex-shrink: 0; }
.layer-actions button {
    border: none;
    background: transparent;
    color: #999;
    width: 24px;
    height: 24px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.72rem;
    transition: all 0.15s ease;
}
.layer-actions button:hover { background: #f0f0f0; color: var(--accent-green, #2d6a4f); }
.layer-actions button:last-child:hover { color: #e74c3c; }

/* ============================================================
   MODERN REFRESH — visual polish only (no HTML/JS changes).
   Harmonizes the page with the site's premium gold + dark look.
   ============================================================ */
:root { --design-gold: #c8a96e; --design-gold-soft: rgba(200,169,110,0.10); }

.design-tool-container { padding: 2rem 1.5rem 3rem; }
.design-tool-header h1 { letter-spacing: -0.5px; }
.design-tool-header p { font-size: 1.02rem; }

/* Panels — softer, layered, premium */
.design-toolbar, .design-canvas-area, .design-preview-panel {
    border-radius: 18px !important;
    box-shadow: 0 10px 30px rgba(0,0,0,0.06), 0 2px 8px rgba(0,0,0,0.04) !important;
    border: 1px solid #ececec !important;
}
.design-toolbar { overflow-x: hidden; }                 /* kill the stray horizontal scrollbar */
.design-toolbar::-webkit-scrollbar { width: 8px; }
.design-toolbar::-webkit-scrollbar-thumb { background: #e2e2e2; border-radius: 8px; }
.design-toolbar::-webkit-scrollbar-thumb:hover { background: #cfcfcf; }

/* Section headers get a slim gold accent bar */
.tool-section h6 {
    display: flex; align-items: center; gap: 0.5rem;
    color: #555 !important;
}
.tool-section h6::before {
    content: ''; width: 3px; height: 14px; border-radius: 3px;
    background: var(--design-gold); flex-shrink: 0;
}

/* Buttons — smoother hover + subtle lift */
.tool-btn, .apparel-type-btn, .canvas-action-btn, .color-swatch-btn, .apparel-color-btn {
    transition: all 0.18s ease !important;
}
.tool-btn:hover, .apparel-type-btn:hover, .canvas-action-btn:hover { transform: translateY(-1px); }

/* Apparel type active → gold, matching the site */
.apparel-type-btn.active {
    border-color: var(--design-gold) !important;
    background: var(--design-gold-soft) !important;
    color: #8a6d3b !important;
}
.apparel-type-btn.active i { color: var(--design-gold) !important; }

/* Selected apparel colour gets a clean gold ring */
.apparel-color-btn.active, .apparel-color-btn.selected {
    box-shadow: 0 0 0 2px #fff, 0 0 0 4px var(--design-gold) !important;
}

/* AI Generate panel → premium gold (ties into the landing-page look) */
.ai-design-section {
    background: linear-gradient(135deg, var(--design-gold-soft), rgba(26,26,26,0.03)) !important;
    border-color: #ece4d3 !important;
}
.ai-pill { background: linear-gradient(135deg, #d8b878, #c8a96e) !important; color: #1a1a1a !important; }
.ai-generate-btn {
    background: linear-gradient(135deg, #d8b878, #c8a96e) !important;
    color: #1a1a1a !important;
    box-shadow: 0 8px 22px rgba(200,169,110,0.35) !important;
}
.ai-generate-btn:hover:not(:disabled) { box-shadow: 0 12px 28px rgba(200,169,110,0.5) !important; }

/* Saved-design card: spec line, price and blocked-state styles. */
.design-spec {
    display: flex; align-items: center; flex-wrap: wrap; gap: 0.35rem;
    font-size: 0.76rem; color: #666; margin-top: 0.35rem;
}
.design-swatch {
    width: 13px; height: 13px; border-radius: 50%;
    border: 1px solid rgba(0,0,0,0.25); display: inline-block; flex: none;
}
.design-note { font-size: 0.78rem; color: #888; margin: 0.35rem 0 0; }
.design-price-row {
    display: flex; align-items: baseline; justify-content: space-between;
    margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px dashed #e8e8e8;
}
.design-price-label { font-size: 0.74rem; color: #777; }
.design-price-label em { font-style: normal; color: #16a34a; font-weight: 600; }
.design-price { font-size: 1rem; font-weight: 700; color: #1a1a1a; }
.design-date { display: block; font-size: 0.72rem; color: #9a9a9a; margin-top: 0.2rem; }
.design-order-btn {
    background: var(--accent-green); color: #fff; border-radius: 8px;
    font-size: 0.78rem; font-weight: 600;
}
.design-order-btn:hover { color: #fff; filter: brightness(1.06); }
.design-edit-btn { border: 1px solid #d9d9d9; background: #fff; color: #333; font-weight: 600; }
.design-edit-btn:hover { border-color: var(--design-gold); color: #8a6d3b; }
html.dark-mode .design-edit-btn { background: #2a2a31; border-color: #3a3a44; color: #ddd; }
/* Shown instead of the button when a design cannot be ordered. */
.design-blocked {
    font-size: 0.74rem; color: #8a6d3b; background: #fff8e6;
    border: 1px solid #f0e0b8; border-radius: 8px; padding: 0.45rem 0.6rem;
    text-align: center;
}
/* Stands in for artwork whose file is no longer on disk. */
.design-card-missing {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: 0.35rem; background: #f7f7f7; color: #a0a0a0;
    font-size: 0.76rem; text-align: center;
}
.design-card-missing i { font-size: 1.4rem; opacity: 0.65; }

/* My Designs cards — premium hover */
.design-card {
    border-radius: 16px !important;
    border: 1px solid #ececec !important;
    box-shadow: 0 4px 16px rgba(0,0,0,0.05) !important;
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease !important;
    overflow: hidden;
}
.design-card:hover {
    transform: translateY(-4px) !important;
    box-shadow: 0 16px 36px rgba(0,0,0,0.10) !important;
    border-color: var(--design-gold) !important;
}

/* Submit button — refined dark with a soft lift */
.btn-submit-design { border-radius: 14px !important; transition: all 0.2s ease !important; }
.btn-submit-design:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 12px 28px rgba(26,26,26,0.25) !important;
}

/* ============================================================
   3D PREVIEW DEPTH — visual only (no HTML/JS changes).
   Adds perspective tilt, studio shading, and a soft shirt shadow.
   ============================================================ */
/* Stronger, slightly top-down viewpoint → more 3D during the spin */
.preview-3d-container {
    perspective: 620px !important;
    perspective-origin: 50% 30%;
}
/* GPU-accelerated, slightly slower + more graceful spin */
.preview-3d-inner { will-change: transform; }
.preview-3d-inner.spinning { animation-duration: 7.5s !important; }

/* Studio-light gradient + inner shading on each face for depth */
.preview-3d-face {
    background: radial-gradient(ellipse at 50% 32%, #ffffff 0%, #eef0f3 82%) !important;
    box-shadow: inset 0 -18px 30px rgba(0,0,0,0.05), inset 0 8px 20px rgba(255,255,255,0.6);
}
/* Soft contact shadow that traces the shirt silhouette */
.preview-3d-face canvas { filter: drop-shadow(0 12px 14px rgba(0,0,0,0.18)); }
/* Subtle directional sheen for a "lit" look */
.preview-3d-face::after {
    content: '';
    position: absolute;
    inset: 0;
    pointer-events: none;
    border-radius: 12px;
    background: linear-gradient(135deg, rgba(255,255,255,0.16), transparent 42%, rgba(0,0,0,0.07));
}

/* ===== Real 3D garment (js/design-3d.js) ===== */
.garment3d {
    position: relative;
    width: 100%;
    aspect-ratio: 3/4;
    border-radius: 12px;
    overflow: hidden;
    background: radial-gradient(ellipse at 50% 32%, #ffffff 0%, #eef0f3 82%);
    cursor: grab;
    touch-action: none;   /* drag rotates the garment instead of scrolling */
}
.garment3d:active { cursor: grabbing; }
.garment3d canvas { display: block; width: 100%; height: 100%; }
html.dark-mode .garment3d { background: #26262c; }
.garment3d-status {
    position: absolute;
    inset: auto 0 45% 0;
    text-align: center;
    font-size: 0.8rem;
    color: #888;
    pointer-events: none;
}

/* ===== Sleeve Designer ===== */
.sleeve-pick { display: grid; grid-template-columns: 1fr 1fr; gap: 0.4rem; }
.sleeve-pick .tool-btn { justify-content: center; font-size: 0.8rem; padding: 0.55rem 0.4rem; }
.sleeve-count {
    min-width: 18px; padding: 0 5px; border-radius: 9px; font-size: 0.68rem; line-height: 18px;
    background: var(--design-gold); color: #fff; text-align: center;
}
.sleeve-count:empty { display: none; }
.sleeve-hint { font-size: 0.68rem; color: #aaa; margin: 0.45rem 0 0; line-height: 1.3; }

/* Clickable sleeves on the flat mockup. The SVG itself lets clicks through
   (the design canvas sits under it); only the hotspots catch them. */
.sleeve-hotspots {
    position: absolute; inset: 0; width: 100%; height: 100%;
    z-index: 6; pointer-events: none; overflow: visible;
}
.sleeve-spot { pointer-events: auto; cursor: pointer; }
.sleeve-spot .ring { fill: rgba(200,169,110,0.08); stroke: var(--design-gold); stroke-width: 1.4; stroke-dasharray: 4 3; opacity: 0.55; transition: opacity .15s; }
.sleeve-spot .plus { fill: var(--design-gold); font: 700 16px/1 sans-serif; text-anchor: middle; dominant-baseline: central; opacity: 0.8; }
.sleeve-spot:hover .ring, .sleeve-spot:focus .ring { opacity: 1; fill: rgba(200,169,110,0.18); }
.sleeve-spot.filled .ring { opacity: 0; }
.sleeve-spot.filled:hover .ring, .sleeve-spot.filled:focus .ring { opacity: 1; fill: none; }
.sleeve-spot:focus { outline: none; }

.sleeve-editor {
    position: fixed; inset: 0; z-index: 1080;
    display: flex; align-items: center; justify-content: center;
    padding: 16px; background: rgba(15,15,20,0.45);
}
.sleeve-editor[hidden] { display: none; }
.sleeve-editor-card {
    width: 100%; max-width: 640px; max-height: calc(100vh - 32px); overflow-y: auto;
    background: #fff; color: #222; border-radius: 18px; padding: 1rem 1.1rem 1.1rem;
    box-shadow: 0 24px 60px rgba(0,0,0,0.25);
}
.sleeve-editor-head { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; }
.sleeve-editor-head h5 { margin: 0; font-weight: 700; font-size: 1.05rem; }
.sleeve-editor-head h5 small { font-weight: 500; color: #999; font-size: 0.8rem; }
.sleeve-x { border: 0; background: none; font-size: 1.6rem; line-height: 1; color: #888; cursor: pointer; padding: 0 0.25rem; }
.sleeve-tabs { display: flex; gap: 0.4rem; margin: 0.75rem 0; }
.sleeve-tabs button {
    flex: 1; border: 1px solid #e0e0e0; background: #fff; color: inherit; border-radius: 10px;
    padding: 0.45rem; font-size: 0.85rem; font-weight: 600; cursor: pointer;
}
.sleeve-tabs button.active { border-color: var(--design-gold); background: var(--design-gold-soft); color: #8a6d3b; }
.sleeve-body { display: grid; grid-template-columns: 260px 1fr; gap: 1rem; }
.sleeve-stage {
    display: block; width: 260px; height: 260px; border-radius: 14px;
    box-shadow: inset 0 0 0 1px rgba(0,0,0,0.08); touch-action: none; cursor: default;
}
.sleeve-stage-hint { font-size: 0.7rem; color: #999; text-align: center; margin-top: 0.3rem; }
.sleeve-view { display: flex; gap: 0.3rem; margin-bottom: 0.45rem; width: 260px; }
.sleeve-view[hidden] { display: none; }
.sleeve-view button {
    flex: 1; border: 1px solid #e0e0e0; background: #fff; color: inherit; border-radius: 8px;
    padding: 0.3rem; font-size: 0.75rem; font-weight: 600; cursor: pointer;
}
.sleeve-view button.active { background: #1a1a1a; border-color: #1a1a1a; color: #fff; }
.sleeve-stage[hidden] { display: none; }

/* ===== Templates ===== */
.tpl-open-btn {
    width: 100%; padding: 0.65rem; border-radius: 12px; border: 1px dashed var(--design-gold);
    background: var(--design-gold-soft); color: #8a6d3b; font-weight: 700; font-size: 0.85rem; cursor: pointer;
}
.tpl-open-btn:hover { background: rgba(200,169,110,0.2); }
.tpl-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 0.6rem; margin-top: 0.8rem; }
.tpl-grid[hidden] { display: none; }
.tpl-card {
    display: flex; flex-direction: column; align-items: flex-start; gap: 0.3rem; text-align: left;
    border: 1px solid #e6e6e6; border-radius: 12px; background: #fff; color: inherit; padding: 0.7rem; cursor: pointer;
    transition: border-color .15s, transform .15s;
}
.tpl-card:hover { border-color: var(--design-gold); transform: translateY(-2px); }
.tpl-card strong { font-size: 0.88rem; }
.tpl-card small { font-size: 0.72rem; color: #888; line-height: 1.3; }
.tpl-card-wrap { max-width: 760px; }
.tpl-tabs { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-top: 0.8rem; }
.tpl-tabs[hidden] { display: none; }
.tpl-tab {
    border: 1px solid #e0e0e0; border-radius: 999px; background: #fff; color: inherit;
    padding: 0.3rem 0.8rem; font-size: 0.78rem; font-weight: 600; cursor: pointer;
}
.tpl-tab span { opacity: 0.55; font-weight: 500; margin-left: 0.15rem; }
.tpl-tab:hover { border-color: var(--design-gold); }
.tpl-tab.active { background: #1a1a1a; border-color: #1a1a1a; color: #fff; }
.tpl-thumb {
    width: 100%; height: 92px; border-radius: 10px; display: flex; align-items: center; justify-content: center;
    border: 1px solid rgba(0,0,0,0.12); font-size: 1.9rem; margin-bottom: 0.2rem;
}
.tpl-thumb img { width: 76px; height: 76px; object-fit: contain; }
.tpl-form-title { margin: 0.9rem 0 0.2rem; font-weight: 700; }
.tpl-form-hint { font-size: 0.75rem; color: #888; margin-bottom: 0.6rem; }
.tpl-field { display: block; font-size: 0.78rem; font-weight: 600; color: #666; margin-bottom: 0.55rem; }
.tpl-field input { display: block; width: 100%; margin-top: 0.2rem; border: 1px solid #e0e0e0; border-radius: 8px; padding: 0.45rem 0.6rem; font-size: 0.88rem; background: #fff; color: inherit; }
html.dark-mode .tpl-tab:not(.active) { background: #2a2a31; border-color: #3a3a44; }
html.dark-mode .tpl-tab.active { background: var(--design-gold); border-color: var(--design-gold); color: #1a1a1a; }
html.dark-mode .tpl-card, html.dark-mode .tpl-field input { background: #2a2a31; border-color: #3a3a44; }
html.dark-mode .tpl-open-btn { color: var(--design-gold); }

/* ===== Text effects ===== */
.text-effects { margin-top: 0.55rem; display: grid; gap: 0.35rem; font-size: 0.78rem; color: #666; }
.fx-row { display: flex; align-items: center; gap: 0.45rem; margin: 0; }
.fx-row input[type=range] { flex: 1; accent-color: var(--design-gold); }
.fx-row #textCurveLabel { min-width: 58px; text-align: right; font-size: 0.72rem; color: #999; }
.fx-check input[type=checkbox] { accent-color: var(--design-gold); }
.fx-check input[type=color] { width: 26px; height: 20px; border: 0; padding: 0; background: none; cursor: pointer; margin-left: auto; }
html.dark-mode .text-effects { color: #bbb; }

/* ===== Draft banner ===== */
.draft-banner {
    display: flex; align-items: center; justify-content: space-between; gap: 0.6rem; flex-wrap: wrap;
    padding: 0.55rem 1rem; font-size: 0.82rem; color: #6b5317;
    background: #fff8e6; border-bottom: 1px solid #f0e0b8;
}
.draft-banner[hidden] { display: none; }
.draft-banner-actions { display: inline-flex; gap: 0.4rem; }
html.dark-mode .draft-banner { background: #2e2a1f; color: #e8d6a6; border-color: #4a4230; }

/* ===== Main canvas 3D mode ===== */
.view-toggle { display: inline-flex; }
.view-toggle .canvas-action-btn { border-radius: 0; font-weight: 600; }
.view-toggle .canvas-action-btn:first-child { border-radius: 8px 0 0 8px; }
.view-toggle .canvas-action-btn:last-child { border-radius: 0 8px 8px 0; margin-left: -1px; }
.view-toggle .canvas-action-btn.active { background: #1a1a1a; border-color: #1a1a1a; color: #fff; }
.canvas-3d { flex: 1; position: relative; min-height: 548px; max-height: 640px; display: flex; flex-direction: column; }
.canvas-3d[hidden] { display: none; }
.canvas-3d .garment3d { flex: 1; width: 100%; height: auto; aspect-ratio: auto; border-radius: 0; }
.canvas-3d-reset {
    position: absolute; top: 12px; right: 12px; z-index: 2;
    border: 1px solid #e0e0e0; background: rgba(255,255,255,0.92); color: #333;
    border-radius: 999px; padding: 0.35rem 0.8rem; font-size: 0.78rem; font-weight: 600; cursor: pointer;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
.canvas-3d-reset:hover { border-color: var(--design-gold); color: #8a6d3b; }
html.dark-mode .canvas-3d-reset { background: rgba(42,42,49,0.92); border-color: #3a3a44; color: #ddd; }
.canvas-3d-hint {
    position: absolute; left: 50%; bottom: 12px; transform: translateX(-50%); z-index: 2;
    font-size: 0.72rem; color: #777; background: rgba(255,255,255,0.85); padding: 0.3rem 0.75rem;
    border-radius: 999px; white-space: nowrap; pointer-events: none;
}
html.dark-mode .canvas-3d-hint { background: rgba(30,30,36,0.85); color: #bbb; }
@media (max-width: 768px) {
    .canvas-3d { min-height: 420px; }
    .canvas-3d-hint { white-space: normal; width: 90%; text-align: center; }
}
.sleeve-3d { width: 260px; height: 260px; }
.sleeve-3d[hidden] { display: none; }
.sleeve-3d .garment3d { width: 100%; height: 100%; aspect-ratio: auto; border-radius: 14px; }
.sleeve-sel { margin-top: 0.5rem; font-size: 0.75rem; color: #777; }
.sleeve-sel label { display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.25rem; }
.sleeve-sel input[type=range] { flex: 1; accent-color: var(--design-gold); }
.sleeve-sel-actions { display: flex; gap: 0.4rem; }
.sleeve-sel-actions + .sleeve-sel-actions { margin-top: 0.4rem; }
.sleeve-align .sleeve-btn { padding: 0.35rem 0.5rem; }
.sleeve-editor [hidden] { display: none !important; }
.sleeve-label { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #888; margin: 0.1rem 0 0.35rem; display: flex; align-items: center; justify-content: space-between; }
.sleeve-label input[type=color] { width: 28px; height: 22px; border: 0; padding: 0; background: none; cursor: pointer; }
.sleeve-sticker-grid, .sleeve-stamp-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 0.3rem; margin-bottom: 0.7rem; }
.sleeve-sticker-grid button, .sleeve-stamp-grid button {
    aspect-ratio: 1/1; border: 1px solid #e6e6e6; background: #fff; border-radius: 8px; cursor: pointer;
    font-size: 1.25rem; line-height: 1; padding: 3px; display: flex; align-items: center; justify-content: center;
    transition: transform .12s, border-color .12s;
}
.sleeve-sticker-grid button:hover, .sleeve-stamp-grid button:hover { transform: translateY(-1px); border-color: var(--design-gold); }
.sleeve-stamp-grid svg { width: 100%; height: 100%; display: block; }
.sleeve-text-row { display: flex; gap: 0.4rem; margin-bottom: 0.6rem; }
.sleeve-text-row input { flex: 1; min-width: 0; border: 1px solid #e0e0e0; border-radius: 8px; padding: 0.4rem 0.55rem; font-size: 0.85rem; background: #fff; color: inherit; }
.sleeve-btn {
    border: 1px solid #e0e0e0; background: #fff; color: inherit; border-radius: 8px;
    padding: 0.4rem 0.7rem; font-size: 0.8rem; font-weight: 600; cursor: pointer; white-space: nowrap;
}
.sleeve-btn:hover { border-color: var(--design-gold); }
.sleeve-btn.primary { background: var(--design-gold); border-color: var(--design-gold); color: #fff; }
.sleeve-btn.danger:hover { border-color: #dc3545; color: #dc3545; }
.sleeve-btn.full { width: 100%; }
.sleeve-foot { display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 1rem; flex-wrap: wrap; }
.sleeve-foot .sleeve-btn:first-child { margin-right: auto; }
@media (max-width: 640px) {
    .sleeve-body { grid-template-columns: 1fr; }
    .sleeve-stage, .sleeve-3d, .sleeve-view { margin-left: auto; margin-right: auto; }
}
html.dark-mode .sleeve-view button:not(.active) { background: #2a2a31; border-color: #3a3a44; }
html.dark-mode .sleeve-editor-card { background: #1f1f25; color: #eee; }
html.dark-mode .sleeve-tabs button,
html.dark-mode .sleeve-btn:not(.primary),
html.dark-mode .sleeve-text-row input,
html.dark-mode .sleeve-sticker-grid button,
html.dark-mode .sleeve-stamp-grid button { background: #2a2a31; border-color: #3a3a44; }
html.dark-mode .sleeve-tabs button.active { background: rgba(200,169,110,0.18); color: var(--design-gold); }
/* Phones & tablets: app layout. One column with the shirt first, tools under it.
   (Side-by-side here stretched the canvas to the toolbar's height and pushed
   the shirt off screen on tablets.) */
@media (max-width: 991.98px) {
    body .design-workspace { grid-template-columns: 1fr; min-height: 0; }
    body .design-canvas-area { order: -1; }
    body .design-toolbar { max-height: none; }
    body .design-tool-container { padding: 0.75rem 0.75rem 1.5rem; }
    body .design-tool-header { margin-bottom: 0.9rem; text-align: left; }
    body .design-tool-header nav,
    body .design-tool-header p { display: none; }
    body .design-tool-header h1 { font-size: 1.35rem; margin: 0; }
}
/* Phones: canvas tools on one swipeable row instead of three wrapped rows. */
@media (max-width: 575.98px) {
    body .canvas-toolbar { flex-wrap: nowrap; overflow-x: auto; gap: 0.4rem; scrollbar-width: none; }
    body .canvas-toolbar::-webkit-scrollbar { display: none; }
    body .canvas-toolbar-left { flex-wrap: nowrap; }
    body .canvas-toolbar > *,
    body .canvas-action-btn { flex-shrink: 0; }
}
</style>

<div class="design-tool-container">
    <div class="design-tool-header">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb justify-content-center" style="font-size:0.85rem;">
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Home</a></li>
                <li class="breadcrumb-item"><a href="shop.php" class="text-decoration-none">Shop</a></li>
                <li class="breadcrumb-item active">Design Your Apparel</li>
            </ol>
        </nav>
        <h1><i class="fas fa-palette me-2"></i>Design Your Apparel</h1>
        <p>Create your own custom clothing design. Draw, upload images, add text, and preview on real apparel mockups.</p>
    </div>

    <div class="design-workspace">
        <!-- Left Toolbar -->
        <div class="design-toolbar">
            <!-- Apparel Type (data-driven from includes/apparel-config.php) -->
            <?php
            $groupMeta = [
                'basic'     => ['label' => 'Basic',       'icon' => 'fa-shirt'],
                'couple'    => ['label' => 'Couple Wear', 'icon' => 'fa-heart'],
                'corporate' => ['label' => 'Corporate',   'icon' => 'fa-briefcase'],
            ];
            $grouped = [];
            foreach ($apparelConfig as $key => $cfg) { $grouped[$cfg['group']][$key] = $cfg; }
            ?>
            <!-- Templates -->
            <div class="tool-section">
                <button type="button" class="tpl-open-btn" onclick="openTemplates()">
                    <i class="fas fa-swatchbook me-1"></i> Start from a template
                </button>
            </div>

            <div class="tool-section">
                <h6><i class="fas fa-shirt me-1"></i> Apparel Type</h6>
                <div class="apparel-tabs">
                    <?php foreach ($groupMeta as $gKey => $gMeta): ?>
                    <button class="apparel-tab <?php echo $gKey === 'basic' ? 'active' : ''; ?>"
                            data-group="<?php echo $gKey; ?>" onclick="setApparelGroup('<?php echo $gKey; ?>')">
                        <i class="fas <?php echo $gMeta['icon']; ?>"></i> <?php echo $gMeta['label']; ?>
                    </button>
                    <?php endforeach; ?>
                </div>
                <?php foreach ($groupMeta as $gKey => $gMeta): ?>
                <div class="apparel-types apparel-group" data-group="<?php echo $gKey; ?>"
                     style="<?php echo $gKey === 'basic' ? '' : 'display:none;'; ?>">
                    <?php foreach (($grouped[$gKey] ?? []) as $key => $cfg): ?>
                    <button class="apparel-type-btn <?php echo $key === 'tshirt' ? 'active' : ''; ?>"
                            data-type="<?php echo $key; ?>" onclick="setApparelType('<?php echo $key; ?>')">
                        <i class="fas <?php echo $cfg['icon']; ?>"></i>
                        <?php echo htmlspecialchars($cfg['label']); ?>
                    </button>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Apparel Color -->
            <div class="tool-section">
                <h6><i class="fas fa-fill-drip me-1"></i> Apparel Color</h6>
                <div class="apparel-color-grid">
                    <button class="apparel-color-btn active" style="background:#FFFFFF" data-color="#FFFFFF" onclick="setApparelColor(this, '#FFFFFF')" title="White"></button>
                    <button class="apparel-color-btn" style="background:#000000" data-color="#000000" onclick="setApparelColor(this, '#000000')" title="Black"></button>
                    <button class="apparel-color-btn" style="background:#001F3F" data-color="#001F3F" onclick="setApparelColor(this, '#001F3F')" title="Navy"></button>
                    <button class="apparel-color-btn" style="background:#808080" data-color="#808080" onclick="setApparelColor(this, '#808080')" title="Gray"></button>
                    <button class="apparel-color-btn" style="background:#FF4136" data-color="#FF4136" onclick="setApparelColor(this, '#FF4136')" title="Red"></button>
                    <button class="apparel-color-btn" style="background:#2ECC40" data-color="#2ECC40" onclick="setApparelColor(this, '#2ECC40')" title="Green"></button>
                    <button class="apparel-color-btn" style="background:#0074D9" data-color="#0074D9" onclick="setApparelColor(this, '#0074D9')" title="Blue"></button>
                    <button class="apparel-color-btn" style="background:#FFDC00" data-color="#FFDC00" onclick="setApparelColor(this, '#FFDC00')" title="Yellow"></button>
                    <button class="apparel-color-btn" style="background:#FF69B4" data-color="#FF69B4" onclick="setApparelColor(this, '#FF69B4')" title="Pink"></button>
                    <button class="apparel-color-btn" style="background:#800000" data-color="#800000" onclick="setApparelColor(this, '#800000')" title="Maroon"></button>
                </div>
            </div>

            <!-- AI Design Generator -->
            <div class="tool-section ai-design-section">
                <h6><i class="fas fa-wand-magic-sparkles me-1"></i> AI Design <span class="ai-pill">Gemini</span></h6>
                <p class="ai-design-hint">Describe an idea — AI creates the artwork and drops it on your canvas.</p>
                <textarea id="aiPrompt" class="ai-prompt" rows="2" maxlength="500" placeholder="e.g. minimalist mountain sunset with birds"></textarea>
                <div class="ai-chips">
                    <button type="button" class="ai-chip" data-prompt="bold retro sunset with palm trees, 80s style">Retro sunset</button>
                    <button type="button" class="ai-chip" data-prompt="cute kawaii cat mascot, sticker style">Kawaii cat</button>
                    <button type="button" class="ai-chip" data-prompt="minimalist line-art mountain range">Line mountains</button>
                    <button type="button" class="ai-chip" data-prompt="fierce flaming basketball, streetwear graphic">Flaming ball</button>
                </div>
                <button type="button" id="aiGenerateBtn" class="ai-generate-btn" onclick="generateAIDesign()">
                    <i class="fas fa-wand-magic-sparkles"></i> Generate Design
                </button>
            </div>

            <!-- Drawing Tools -->
            <div class="tool-section">
                <h6><i class="fas fa-pen me-1"></i> Drawing Tools</h6>
                <button class="tool-btn active" data-tool="brush" onclick="setTool('brush')">
                    <i class="fas fa-paintbrush"></i> Brush
                </button>
                <button class="tool-btn" data-tool="eraser" onclick="setTool('eraser')">
                    <i class="fas fa-eraser"></i> Eraser
                </button>
                <button class="tool-btn" data-tool="line" onclick="setTool('line')">
                    <i class="fas fa-minus"></i> Line
                </button>
                <button class="tool-btn" data-tool="rect" onclick="setTool('rect')">
                    <i class="fas fa-square"></i> Rectangle
                </button>
                <button class="tool-btn" data-tool="circle" onclick="setTool('circle')">
                    <i class="fas fa-circle"></i> Circle
                </button>
            </div>

            <!-- Brush Settings -->
            <div class="tool-section">
                <h6><i class="fas fa-sliders-h me-1"></i> Brush Settings</h6>
                <label style="font-size:0.78rem; color:#888;">Size: <span id="brushSizeLabel">4</span>px</label>
                <input type="range" class="brush-size-slider" id="brushSize" min="1" max="30" value="4" oninput="setBrushSize(this.value)">
                
                <label style="font-size:0.78rem; color:#888; margin-top:0.4rem; display:block;">Color</label>
                <div class="color-grid">
                    <button class="color-swatch-btn active" style="background:#000000" onclick="setBrushColor(this, '#000000')"></button>
                    <button class="color-swatch-btn" style="background:#FFFFFF" onclick="setBrushColor(this, '#FFFFFF')"></button>
                    <button class="color-swatch-btn" style="background:#FF4136" onclick="setBrushColor(this, '#FF4136')"></button>
                    <button class="color-swatch-btn" style="background:#0074D9" onclick="setBrushColor(this, '#0074D9')"></button>
                    <button class="color-swatch-btn" style="background:#2ECC40" onclick="setBrushColor(this, '#2ECC40')"></button>
                    <button class="color-swatch-btn" style="background:#FFDC00" onclick="setBrushColor(this, '#FFDC00')"></button>
                    <button class="color-swatch-btn" style="background:#FF69B4" onclick="setBrushColor(this, '#FF69B4')"></button>
                    <button class="color-swatch-btn" style="background:#B10DC9" onclick="setBrushColor(this, '#B10DC9')"></button>
                    <button class="color-swatch-btn" style="background:#FF851B" onclick="setBrushColor(this, '#FF851B')"></button>
                    <button class="color-swatch-btn" style="background:#8B4513" onclick="setBrushColor(this, '#8B4513')"></button>
                </div>
                <div class="mt-2">
                    <input type="color" id="customColor" value="#000000" style="width:100%; height: 28px; border:none; cursor:pointer; border-radius:6px;" onchange="setBrushColor(null, this.value)">
                </div>
            </div>

            <!-- Text Tool -->
            <div class="tool-section">
                <h6><i class="fas fa-font me-1"></i> Add Text</h6>
                <select class="font-selector" id="fontFamily">
                    <option value="Arial">Arial</option>
                    <option value="Georgia">Georgia</option>
                    <option value="Impact">Impact</option>
                    <option value="Courier New">Courier New</option>
                    <option value="Comic Sans MS">Comic Sans MS</option>
                    <option value="Trebuchet MS">Trebuchet MS</option>
                    <option value="Verdana">Verdana</option>
                    <option value="Times New Roman">Times New Roman</option>
                </select>
                <div style="display:flex; gap:0.3rem; margin-bottom:0.5rem;">
                    <input type="number" id="fontSize" value="24" min="8" max="72" style="width:60px; padding:0.35rem; border:1px solid #ddd; border-radius:6px; font-size:0.8rem;" title="Font size">
                    <button class="canvas-action-btn" id="boldBtn" onclick="toggleBold()" title="Bold"><i class="fas fa-bold"></i></button>
                    <button class="canvas-action-btn" id="italicBtn" onclick="toggleItalic()" title="Italic"><i class="fas fa-italic"></i></button>
                </div>
                <div class="text-input-group">
                    <input type="text" id="textInput" placeholder="Type text here..." maxlength="100">
                    <button onclick="addTextToCanvas()" title="Add Text"><i class="fas fa-plus"></i></button>
                </div>
                <!-- Text effects: with any of these on, the text is added as print-quality art -->
                <div class="text-effects">
                    <label class="fx-row">Curve
                        <input type="range" id="textCurve" min="-100" max="100" step="5" value="0" oninput="updateCurveLabel()">
                        <span id="textCurveLabel">Straight</span>
                    </label>
                    <label class="fx-row fx-check"><input type="checkbox" id="textOutline"> Outline
                        <input type="color" id="textOutlineColor" value="#FFFFFF" title="Outline colour">
                    </label>
                    <label class="fx-row fx-check"><input type="checkbox" id="textShadow"> Shadow</label>
                </div>
            </div>

            <!-- Upload Image -->
            <div class="tool-section">
                <h6><i class="fas fa-image me-1"></i> Upload Image</h6>
                <div class="upload-area" onclick="document.getElementById('imageUpload').click()">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <p>Click to upload image or logo</p>
                    <p style="font-size:0.7rem;">(PNG, JPG, max 5MB)</p>
                </div>
                <input type="file" id="imageUpload" accept="image/png,image/jpeg,image/webp" style="display:none" onchange="handleImageUpload(this)">
            </div>

            <!-- Animal Stamps -->
            <div class="tool-section">
                <h6><i class="fas fa-paw me-1"></i> Animal Stamps</h6>
                <label style="font-size:0.78rem; color:#888;">Size: <span id="stampSizeLabel">90</span>px</label>
                <input type="range" class="brush-size-slider" id="stampSize" min="30" max="200" value="90" oninput="setStampSize(this.value)">
                <div class="stamp-grid" id="stampGrid"></div>
                <p style="font-size:0.68rem; color:#aaa; margin-top:0.45rem; line-height:1.3;">Pick a size, click a stamp to drop it on the design, then drag to reposition. Select &amp; tap the &times; to delete.</p>
            </div>

            <!-- Company Logo (corporate types only) -->
            <div class="tool-section" id="logoToolSection" style="display:none;">
                <h6><i class="fas fa-building me-1"></i> Company Logo</h6>
                <button type="button" class="tool-btn" style="justify-content:center;" onclick="openLogoEditor()">
                    <i class="fas fa-pen-ruler"></i> Design logo <span class="sleeve-count" id="logoCount"></span>
                </button>
                <p class="sleeve-hint">Upload your logo or build one, then centre it with one click. You can also click the LOGO box on the shirt.</p>
            </div>

            <!-- Sleeve Designs (hidden for sleeveless types) -->
            <div class="tool-section" id="sleeveToolSection">
                <h6><i class="fas fa-shirt me-1"></i> Sleeve Designs</h6>
                <div class="sleeve-pick">
                    <button type="button" class="tool-btn" onclick="openSleeveEditor('left')">Left sleeve <span class="sleeve-count" id="sleeveCountLeft"></span></button>
                    <button type="button" class="tool-btn" onclick="openSleeveEditor('right')">Right sleeve <span class="sleeve-count" id="sleeveCountRight"></span></button>
                </div>
                <p class="sleeve-hint">Add stickers, text or a photo on a sleeve. You can also click a sleeve on the shirt or the 3D preview.</p>
            </div>

            <!-- Layers -->
            <div class="tool-section">
                <h6><i class="fas fa-layer-group me-1"></i> Layers</h6>
                <div class="layers-list" id="layersList">
                    <div class="layers-empty">No elements yet. Add text, an image, a stamp, or generate with AI.</div>
                </div>
                <p style="font-size:0.68rem; color:#aaa; margin-top:0.45rem; line-height:1.3;">Click to select &bull; use the arrows to bring forward / send back &bull; trash to remove.</p>
            </div>
        </div>

        <!-- Canvas Area -->
        <div class="design-canvas-area">
            <div class="canvas-toolbar">
                <div class="canvas-toolbar-left">
                    <span id="partnerToggle" class="partner-toggle" style="display:none;">
                        <button class="canvas-action-btn partner-btn active" id="partnerABtn" onclick="switchPartner('A')" title="Edit Partner A"><i class="fas fa-user"></i> A</button>
                        <button class="canvas-action-btn partner-btn" id="partnerBBtn" onclick="switchPartner('B')" title="Edit Partner B"><i class="fas fa-user"></i> B</button>
                        <span style="color:#ccc; font-size:0.8rem;">|</span>
                    </span>
                    <button class="canvas-action-btn side-toggle-btn active" id="frontSideBtn" onclick="switchSide('front')" title="Front View"><i class="fas fa-tshirt"></i> Front</button>
                    <button class="canvas-action-btn side-toggle-btn" id="backSideBtn" onclick="switchSide('back')" title="Back View"><i class="fas fa-retweet"></i> Back</button>
                    <span style="color:#ccc; font-size:0.8rem;">|</span>
                    <button class="canvas-action-btn" onclick="undoAction()" title="Undo"><i class="fas fa-undo"></i></button>
                    <button class="canvas-action-btn" onclick="redoAction()" title="Redo"><i class="fas fa-redo"></i></button>
                    <span id="zoomGroup" class="canvas-toolbar-left">
                    <span style="color:#ccc; font-size:0.8rem;">|</span>
                    <button class="canvas-action-btn" onclick="zoomIn()" title="Zoom In"><i class="fas fa-search-plus"></i></button>
                    <button class="canvas-action-btn" onclick="zoomOut()" title="Zoom Out"><i class="fas fa-search-minus"></i></button>
                    <button class="canvas-action-btn" onclick="resetZoom()" title="Reset View"><i class="fas fa-expand"></i></button>
                    </span>
                </div>
                <div class="canvas-toolbar-right">
                    <span class="view-toggle" id="mainViewToggle" style="display:none;">
                        <button class="canvas-action-btn active" data-view="2d" onclick="setMainView('2d')" title="Design on the flat mockup">2D</button>
                        <button class="canvas-action-btn" data-view="3d" onclick="setMainView('3d')" title="See and turn your design in 3D"><i class="fas fa-cube"></i> 3D</button>
                    </span>
                    <button class="canvas-action-btn" onclick="deleteSelected()" title="Delete Selected"><i class="fas fa-trash"></i></button>
                    <button class="canvas-action-btn danger" onclick="clearCanvas()" title="Clear All"><i class="fas fa-times"></i> Clear</button>
                </div>
            </div>
            <!-- Shown on load when this browser has an unsaved design (see startDraftsAndEditing) -->
            <div class="draft-banner" id="draftBanner" hidden>
                <span><i class="fas fa-clock-rotate-left me-1"></i> You have an unsaved design from <strong id="draftTime"></strong>.</span>
                <span class="draft-banner-actions">
                    <button type="button" class="canvas-action-btn" onclick="restoreDraft()"><i class="fas fa-rotate-left"></i> Restore</button>
                    <button type="button" class="canvas-action-btn" onclick="discardDraft()">Start fresh</button>
                </span>
            </div>
            <!-- 3D mode: the page's 3D preview moves in here (see setMainView) -->
            <div class="canvas-3d" id="canvas3dSlot" hidden>
                <button type="button" class="canvas-3d-reset" onclick="resetView3D()" title="Back to the normal view">
                    <i class="fas fa-arrows-rotate me-1"></i> Reset view
                </button>
                <div class="canvas-3d-hint"><i class="fas fa-hand-pointer me-1"></i> Drag to rotate &bull; scroll to zoom &bull; <span id="canvas3dSleeveHint">click a sleeve to design it &bull; </span>switch to 2D to draw</div>
            </div>
            <div class="canvas-wrapper" id="canvasWrapper">
                <div class="mockup-container" id="mockupContainer">
                    <!-- SVG Mockup will be drawn here -->
                    <svg class="mockup-svg" id="mockupSvg" viewBox="0 0 400 500" xmlns="http://www.w3.org/2000/svg"></svg>
                    <canvas id="designCanvas" width="200" height="240"></canvas>
                    <!-- Draggable elements layer -->
                    <div id="elementsLayer" style="position:absolute; top:0; left:0; width:100%; height:100%; z-index:5; pointer-events:none;"></div>
                    <!-- Corporate logo placement guide (shown only for corporate wear) -->
                    <!-- Click inside (a tap, not a stroke) opens the Logo Designer; see stopDrawing() -->
                    <div id="logoZoneGuide" class="logo-zone-guide" style="display:none;">
                        <img id="logoZoneArt" alt="" hidden>
                        <span>LOGO</span>
                        <small>click to design</small>
                    </div>
                    <!-- Clickable sleeves; filled by refreshSleeveUI() -->
                    <svg class="sleeve-hotspots" id="sleeveHotspots" viewBox="0 0 400 500" xmlns="http://www.w3.org/2000/svg"></svg>
                </div>
            </div>
        </div>

        <!-- Right Panel -->
        <div class="design-preview-panel">
            <!-- 3D Apparel Preview -->
            <div class="preview-section">
                <h6 id="previewHeading"><i class="fas fa-cube me-1"></i> Live Preview</h6>
                <!-- Real 3D model; shown instead of the flat previews below once
                     js/design-3d.js has loaded and the apparel type has a model. -->
                <div id="garment3dWrap" style="display:none;">
                    <div class="garment3d" id="garment3d">
                        <div class="garment3d-status" id="garment3dStatus"></div>
                    </div>
                    <div class="preview-3d-controls">
                        <button onclick="Design3D.face('front')" title="Show the front"><i class="fas fa-shirt"></i> Front</button>
                        <button onclick="Design3D.toggleSpin()" id="spin3dBtn" class="active" title="Auto Spin"><i class="fas fa-sync-alt"></i> Spin</button>
                        <button onclick="Design3D.face('back')" title="Show the back"><i class="fas fa-retweet"></i> Back</button>
                        <button onclick="resetView3D()" title="Reset the view (turn and zoom)"><i class="fas fa-arrows-rotate"></i></button>
                    </div>
                    <div class="preview-3d-label">Drag to rotate &bull; Scroll or pinch to zoom &bull; <span id="sleeve3dHint">Click a sleeve to design it</span></div>
                </div>
                <div id="singlePreviewWrap">
                <div class="preview-3d-container" id="preview3dContainer">
                    <div class="preview-3d-inner" id="preview3dInner">
                        <div class="preview-3d-face preview-3d-front">
                            <canvas id="previewCanvasFront" width="400" height="500"></canvas>
                        </div>
                        <div class="preview-3d-face preview-3d-back">
                            <canvas id="previewCanvasBack" width="400" height="500"></canvas>
                        </div>
                    </div>
                </div>
                <div class="preview-3d-controls">
                    <button onclick="rotate3DLeft()" title="Rotate Left"><i class="fas fa-arrow-rotate-left"></i></button>
                    <button onclick="toggle3DSpin()" id="spinBtn" class="active" title="Auto Spin"><i class="fas fa-sync-alt"></i> Spin</button>
                    <button onclick="flipPreview()" id="flipBtn" title="Flip Front/Back"><i class="fas fa-retweet"></i> Flip</button>
                    <button onclick="rotate3DRight()" title="Rotate Right"><i class="fas fa-arrow-rotate-right"></i></button>
                </div>
                <div class="preview-3d-label">Drag to rotate &bull; Spin to auto-rotate &bull; Flip shows the back</div>
                </div><!-- /singlePreviewWrap -->
                <!-- Couple Wear: side-by-side preview of both partners -->
                <div id="couplePreview" style="display:none;">
                    <div class="couple-preview-grid">
                        <div class="couple-preview-cell">
                            <canvas id="couplePreviewA" width="300" height="375"></canvas>
                            <div class="couple-preview-label">Partner A</div>
                        </div>
                        <div class="couple-preview-cell">
                            <canvas id="couplePreviewB" width="300" height="375"></canvas>
                            <div class="couple-preview-label">Partner B</div>
                        </div>
                    </div>
                    <div class="preview-3d-label">Both garments &bull; front view</div>
                </div>
                <!-- Hidden canvas for updatePreview compatibility -->
                <canvas id="previewCanvas" style="display:none;" width="400" height="500"></canvas>
                <div id="previewPlaceholder" style="display:none;"></div>
            </div>

            <!-- Price Auto Calculator -->
            <div class="preview-section">
                <h6><i class="fas fa-calculator me-1"></i> Price Estimate</h6>
                <div class="price-calculator">
                    <div class="price-row">
                        <span class="price-label">Apparel Base:</span>
                        <span class="price-value" id="priceBase">₱0</span>
                    </div>
                    <div class="price-row">
                        <span class="price-label">Print Size:</span>
                        <select class="price-select" id="printSizeSelect" onchange="calculatePrice()">
                            <option value="small">Small (4×4")</option>
                            <option value="medium" selected>Medium (8×8")</option>
                            <option value="large">Large (12×12")</option>
                            <option value="full">Full Print</option>
                        </select>
                    </div>
                    <div class="price-row">
                        <span class="price-label">Print Size Cost:</span>
                        <span class="price-value" id="pricePrintSize">₱0</span>
                    </div>
                    <div class="price-row">
                        <span class="price-label">Colors Used:</span>
                        <span class="price-value" id="priceColorsCount">1</span>
                    </div>
                    <div class="price-row">
                        <span class="price-label">Color Cost:</span>
                        <span class="price-value" id="priceColors">₱0</span>
                    </div>
                    <div class="price-row" id="priceSleeveRow" style="display:none;">
                        <span class="price-label">Sleeve Prints (<span id="priceSleeveCount">0</span>):</span>
                        <span class="price-value" id="priceSleeves">₱0</span>
                    </div>
                    <div class="price-row" id="priceLogoRow" style="display:none;">
                        <span class="price-label">Logo Print:</span>
                        <span class="price-value" id="priceLogo">₱0</span>
                    </div>
                    <div class="price-row total">
                        <span>Estimated Total:</span>
                        <span id="priceTotal">₱0</span>
                    </div>
                </div>
            </div>

            <!-- Design Info -->
            <div class="preview-section">
                <h6><i class="fas fa-info-circle me-1"></i> Design Info</h6>
                <div style="font-size:0.82rem; color:#666;">
                    <div class="d-flex justify-content-between mb-1">
                        <span>Apparel:</span>
                        <strong id="infoType">T-Shirt</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span>Color:</span>
                        <strong id="infoColor">White</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span>Elements:</span>
                        <strong id="infoElements">0</strong>
                    </div>
                </div>
            </div>

            <!-- Size & Quantity -->
            <div class="preview-section">
                <h6><i class="fas fa-ruler me-1"></i> Size & Quantity</h6>
                <div style="margin-bottom:0.5rem;">
                    <label style="font-size:0.78rem; color:#888; display:block; margin-bottom:0.3rem;">Size</label>
                    <select class="price-select" id="sizeSelect" style="width:100%;" onchange="calculatePrice()">
                        <option value="XS">XS - Extra Small</option>
                        <option value="S">S - Small</option>
                        <option value="M" selected>M - Medium</option>
                        <option value="L">L - Large</option>
                        <option value="XL">XL - Extra Large</option>
                        <option value="2XL">2XL - Double Extra Large</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:0.78rem; color:#888; display:block; margin-bottom:0.3rem;">Quantity</label>
                    <div style="display:flex; align-items:center; gap:0.5rem;">
                        <button type="button" onclick="adjustQty(-1)" style="width:32px;height:32px;border:1px solid #ddd;border-radius:8px;background:#fff;cursor:pointer;font-size:1rem;">−</button>
                        <input type="number" id="quantityInput" value="1" min="1" max="100" style="width:50px;text-align:center;padding:0.35rem;border:1px solid #ddd;border-radius:8px;font-size:0.85rem;" onchange="calculatePrice()">
                        <button type="button" onclick="adjustQty(1)" style="width:32px;height:32px;border:1px solid #ddd;border-radius:8px;background:#fff;cursor:pointer;font-size:1rem;">+</button>
                    </div>
                </div>
            </div>

            <!-- Discount Type -->
            <div class="preview-section">
                <h6><i class="fas fa-ticket-alt me-1"></i> Discount</h6>
                <select class="price-select" id="discountSelect" style="width:100%;" onchange="calculatePrice()">
                    <option value="regular">Regular (No Discount)</option>
                    <?php if ($user_discount === 'senior'): ?>
                    <option value="senior" selected>Senior Citizen (20% off)</option>
                    <?php endif; ?>
                    <?php if ($user_discount === 'pwd'): ?>
                    <option value="pwd" selected>PWD (20% off)</option>
                    <?php endif; ?>
                </select>
                <div id="discountRow" style="display:none; margin-top:0.5rem;">
                    <div class="price-row" style="color:#27ae60;">
                        <span class="price-label">Discount:</span>
                        <span class="price-value" id="priceDiscount" style="color:#27ae60;">-₱0</span>
                    </div>
                </div>
            </div>

            <!-- Notes & Submit -->
            <div class="submit-section">
                <h6 style="font-weight:700; font-size:0.8rem; text-transform:uppercase; letter-spacing:0.5px; color:#888; margin-bottom:0.5rem;">
                    <i class="fas fa-sticky-note me-1"></i> Notes for Admin
                </h6>
                <textarea id="designNotes" placeholder="Any special instructions, preferred fabric, sizing notes..."></textarea>
                <button class="btn-submit-design" id="submitDesignBtn" onclick="submitDesign()">
                    <i class="fas fa-paper-plane"></i> Submit & Proceed to Order
                </button>
            </div>
        </div>
    </div>

    <!-- My Designs Section -->
    <div class="my-designs-section">
        <h3 style="font-weight:700; margin-bottom:1rem;"><i class="fas fa-palette me-2"></i>My Designs</h3>
        <div class="designs-grid" id="myDesignsGrid">
            <div class="text-center py-4 text-muted" id="noDesignsMsg">
                <i class="fas fa-palette" style="font-size:2rem; margin-bottom:0.5rem; display:block; opacity:0.3;"></i>
                <p>No designs yet. Create your first custom design above!</p>
            </div>
        </div>
    </div>
</div>

<!-- Design templates -->
<div class="sleeve-editor" id="templateModal" hidden role="dialog" aria-modal="true" aria-labelledby="templateTitle">
    <div class="sleeve-editor-card tpl-card-wrap">
        <div class="sleeve-editor-head">
            <h5 id="templateTitle"><i class="fas fa-swatchbook me-1"></i> Design Templates</h5>
            <button type="button" class="sleeve-x" onclick="closeTemplates()" aria-label="Close">&times;</button>
        </div>
        <div class="tpl-tabs" id="templateTabs"></div>
        <div class="tpl-grid" id="templateGrid"></div>
        <div id="templateForm" hidden>
            <h6 class="tpl-form-title" id="templateFormTitle"></h6>
            <p class="tpl-form-hint">Change the words, then use the template. You can edit everything afterwards.</p>
            <div id="templateFields"></div>
            <div class="sleeve-foot">
                <button type="button" class="sleeve-btn" onclick="backToTemplates()"><i class="fas fa-arrow-left me-1"></i> Back</button>
                <button type="button" class="sleeve-btn primary" onclick="applyTemplate()">Use template</button>
            </div>
        </div>
    </div>
</div>

<!-- Sleeve Designer: a mini canvas for one sleeve at a time -->
<div class="sleeve-editor" id="sleeveEditor" hidden role="dialog" aria-modal="true" aria-labelledby="sleeveEditorTitle">
    <div class="sleeve-editor-card">
        <div class="sleeve-editor-head">
            <h5 id="sleeveEditorTitle"><i class="fas fa-shirt me-1"></i> <span id="sleeveEditorName">Sleeve Designer</span> <small id="sleeveEditorPartner"></small></h5>
            <button type="button" class="sleeve-x" onclick="closeSleeveEditor()" aria-label="Close">&times;</button>
        </div>
        <div class="sleeve-tabs">
            <button type="button" data-which="left" onclick="switchSleeve('left')">Left sleeve</button>
            <button type="button" data-which="right" onclick="switchSleeve('right')">Right sleeve</button>
        </div>
        <div class="sleeve-body">
            <div>
                <div class="sleeve-view" id="sleeveViewToggle">
                    <button type="button" data-view="2d" onclick="setSleeveView('2d')"><i class="fas fa-pen-ruler me-1"></i> 2D Edit</button>
                    <button type="button" data-view="3d" onclick="setSleeveView('3d')"><i class="fas fa-cube me-1"></i> 3D View</button>
                </div>
                <canvas class="sleeve-stage" id="sleeveStage" width="520" height="520" aria-label="Sleeve design area"></canvas>
                <!-- The page's 3D preview moves in here while 3D View is on -->
                <div class="sleeve-3d" id="sleeve3dSlot" hidden></div>
                <div class="sleeve-stage-hint" id="sleeveStageHint">Drag to move &bull; scroll to resize</div>
                <div class="sleeve-sel" id="sleeveSel" hidden>
                    <label>Size <input type="range" id="sleeveSelSize" min="16" max="280" oninput="setSleeveSel('size', this.value)"></label>
                    <label>Rotate <input type="range" id="sleeveSelRot" min="-180" max="180" oninput="setSleeveSel('rot', this.value)"></label>
                    <div class="sleeve-sel-actions sleeve-align" aria-label="Align">
                        <button type="button" class="sleeve-btn" onclick="alignSleeveSel('h')" title="Centre left-to-right"><i class="fas fa-grip-lines-vertical"></i></button>
                        <button type="button" class="sleeve-btn" onclick="alignSleeveSel('v')" title="Centre top-to-bottom"><i class="fas fa-grip-lines"></i></button>
                        <button type="button" class="sleeve-btn" onclick="alignSleeveSel('c')" title="Centre"><i class="fas fa-crosshairs"></i> Centre</button>
                        <button type="button" class="sleeve-btn" onclick="alignSleeveSel('fit')" title="Make it as big as fits, centred"><i class="fas fa-expand"></i> Fit</button>
                    </div>
                    <button type="button" class="sleeve-btn full" id="sleeveBgBtn" onclick="removeBgFromSleeveSel()" style="margin-top:0.4rem;" hidden>
                        <i class="fas fa-wand-magic-sparkles me-1"></i> Remove white background
                    </button>
                    <div class="sleeve-sel-actions">
                        <button type="button" class="sleeve-btn" onclick="sleeveSelToFront()"><i class="fas fa-arrow-up"></i> To front</button>
                        <button type="button" class="sleeve-btn danger" onclick="deleteSleeveSel()"><i class="fas fa-trash"></i> Delete</button>
                    </div>
                </div>
            </div>
            <div>
                <div class="sleeve-label"><span id="sleeveAiLabel">AI sticker</span></div>
                <div class="sleeve-text-row">
                    <input type="text" id="sleeveAiPrompt" maxlength="120" placeholder="e.g. a small tiger badge" onkeydown="if (event.key === 'Enter') generateSleeveAI()">
                    <button type="button" class="sleeve-btn" id="sleeveAiBtn" onclick="generateSleeveAI()"><i class="fas fa-wand-magic-sparkles"></i> Create</button>
                </div>
                <div class="sleeve-label">Stickers</div>
                <div class="sleeve-sticker-grid" id="sleeveStickerGrid"></div>
                <div class="sleeve-label">Animal stamps <input type="color" id="sleeveColor" value="#1a1a1a" title="Stamp and text colour"></div>
                <div class="sleeve-stamp-grid" id="sleeveStampGrid"></div>
                <div class="sleeve-label">Text</div>
                <div class="sleeve-text-row">
                    <input type="text" id="sleeveText" maxlength="14" placeholder="Name or number" onkeydown="if (event.key === 'Enter') addSleeveText()">
                    <button type="button" class="sleeve-btn" onclick="addSleeveText()">Add</button>
                </div>
                <button type="button" class="sleeve-btn full" id="sleeveUploadBtn" onclick="document.getElementById('sleeveUpload').click()"><i class="fas fa-cloud-upload-alt me-1"></i> Upload image</button>
                <input type="file" id="sleeveUpload" accept="image/png,image/jpeg,image/webp" hidden onchange="handleSleeveUpload(this)">
            </div>
        </div>
        <div class="sleeve-foot">
            <button type="button" class="sleeve-btn danger" id="clearDesignerBtn" onclick="clearSleeve()">Clear sleeve</button>
            <button type="button" class="sleeve-btn" id="copySleeveBtn" onclick="copySleeveToOther()">Copy to other sleeve</button>
            <button type="button" class="sleeve-btn primary" onclick="closeSleeveEditor()">Done</button>
        </div>
    </div>
</div>

<script>
// ===== Design Tool State =====
const state = {
    currentTool: 'brush',
    brushColor: '#000000',
    brushSize: 4,
    apparelType: 'tshirt',
    apparelColor: '#FFFFFF',
    isDrawing: false,
    isBold: false,
    isItalic: false,
    elements: [],
    undoStack: [],
    redoStack: [],
    selectedElement: null,
    zoom: 1,
    startX: 0,
    startY: 0,
    drawingShape: null,
    currentSide: 'front',
    // Separate data for each side
    frontCanvasData: null,
    backCanvasData: null,
    frontInk: false,   // whether each saved side has drawing on it
    backInk: false,
    colorsUsed: 1,     // from the print artwork, see scheduleColorCount()
    frontElements: [],
    backElements: [],
    frontUndoStack: [],
    backUndoStack: [],
    frontRedoStack: [],
    backRedoStack: [],
    // Couple Wear: which partner is being edited and per-partner design bundles.
    // A bundle stores the same 8 per-side fields above, snapshotted per partner.
    currentPartner: 'A',
    partners: { A: null, B: null },
    pendingStampSize: 90,
    // Sleeve prints, per partner (non-couple types use A). See Sleeve Designer.
    sleeves: emptySleeves(),
    // Corporate logo items, laid out in the logo zone. See Logo Designer.
    logo: [],
    // Main canvas shows the 3D garment instead of the flat mockup.
    view3d: false
};

// ===== Apparel Config (data-driven, mirrors includes/apparel-config.php) =====
const APPAREL_CONFIG = <?php echo json_encode($apparelConfig, JSON_UNESCAPED_SLASHES); ?>;
function configOf(type) { return APPAREL_CONFIG[type] || APPAREL_CONFIG.tshirt; }
function shapeOf(type) { return configOf(type).shape; }
function areaOf(type) { return configOf(type).area; }
function isCouple(type) { return !!configOf(type).couple; }
function logoZoneOf(type) { return configOf(type).logoZone || null; }

const canvas = document.getElementById('designCanvas');
const ctx = canvas.getContext('2d');
const mockupContainer = document.getElementById('mockupContainer');
const elementsLayer = document.getElementById('elementsLayer');

// ===== Apparel Mockup SVGs =====
const mockups = {
    tshirt: `
        <!-- T-Shirt shape -->
        <path d="M120,60 L100,60 Q60,60 50,100 L30,160 L70,180 L90,120 L90,420 Q90,440 110,440 L290,440 Q310,440 310,420 L310,120 L330,180 L370,160 L350,100 Q340,60 300,60 L280,60 Q270,40 250,30 L200,20 L150,30 Q130,40 120,60 Z"
              fill="APPAREL_COLOR" stroke="STROKE_COLOR" stroke-width="2"/>
        <!-- Collar -->
        <ellipse cx="200" cy="55" rx="55" ry="20" fill="none" stroke="STROKE_COLOR" stroke-width="2"/>
        <!-- Sleeves detail -->
        <path d="M90,120 L70,180 L30,160" fill="none" stroke="DETAIL_COLOR" stroke-width="1"/>
        <path d="M310,120 L330,180 L370,160" fill="none" stroke="DETAIL_COLOR" stroke-width="1"/>
    `,
    hoodie: `
        <!-- Hoodie shape -->
        <path d="M120,80 L100,80 Q60,80 50,120 L20,200 L70,210 L80,140 L80,420 Q80,440 100,440 L300,440 Q320,440 320,420 L320,140 L330,210 L380,200 L350,120 Q340,80 300,80 L280,80 Q270,55 250,45 L200,35 L150,45 Q130,55 120,80 Z"
              fill="APPAREL_COLOR" stroke="STROKE_COLOR" stroke-width="2"/>
        <!-- Hood -->
        <path d="M120,80 Q110,30 160,15 L200,10 L240,15 Q290,30 280,80"
              fill="APPAREL_COLOR" stroke="STROKE_COLOR" stroke-width="2"/>
        <!-- Pocket -->
        <rect x="140" y="300" width="120" height="60" rx="8" fill="none" stroke="DETAIL_COLOR" stroke-width="1.5"/>
        <!-- Front zipper or kangaroo pocket line -->
        <line x1="200" y1="90" x2="200" y2="300" stroke="DETAIL_COLOR" stroke-width="1"/>
        <!-- Drawstrings -->
        <line x1="180" y1="85" x2="175" y2="130" stroke="STROKE_COLOR" stroke-width="1.5"/>
        <line x1="220" y1="85" x2="225" y2="130" stroke="STROKE_COLOR" stroke-width="1.5"/>
    `,
    polo: `
        <!-- Polo shape -->
        <path d="M120,65 L100,65 Q60,65 50,105 L30,170 L75,185 L90,120 L90,420 Q90,440 110,440 L290,440 Q310,440 310,420 L310,120 L325,185 L370,170 L350,105 Q340,65 300,65 L280,65 Q270,45 250,35 L200,25 L150,35 Q130,45 120,65 Z"
              fill="APPAREL_COLOR" stroke="STROKE_COLOR" stroke-width="2"/>
        <!-- Collar (polo style) -->
        <path d="M145,55 Q155,35 200,30 Q245,35 255,55 L250,70 Q240,50 200,45 Q160,50 150,70 Z"
              fill="APPAREL_COLOR" stroke="STROKE_COLOR" stroke-width="2"/>
        <!-- Button placket -->
        <line x1="200" y1="60" x2="200" y2="160" stroke="STROKE_COLOR" stroke-width="1.5"/>
        <circle cx="200" cy="80" r="3" fill="STROKE_COLOR"/>
        <circle cx="200" cy="105" r="3" fill="STROKE_COLOR"/>
        <circle cx="200" cy="130" r="3" fill="STROKE_COLOR"/>
    `,
    corp_polo: `
        <!-- Corporate polo (collar + chest pocket) -->
        <path d="M120,65 L100,65 Q60,65 50,105 L30,170 L75,185 L90,120 L90,420 Q90,440 110,440 L290,440 Q310,440 310,420 L310,120 L325,185 L370,170 L350,105 Q340,65 300,65 L280,65 Q270,45 250,35 L200,25 L150,35 Q130,45 120,65 Z"
              fill="APPAREL_COLOR" stroke="STROKE_COLOR" stroke-width="2"/>
        <path d="M145,55 Q155,35 200,30 Q245,35 255,55 L250,70 Q240,50 200,45 Q160,50 150,70 Z"
              fill="APPAREL_COLOR" stroke="STROKE_COLOR" stroke-width="2"/>
        <line x1="200" y1="60" x2="200" y2="150" stroke="STROKE_COLOR" stroke-width="1.5"/>
        <circle cx="200" cy="80" r="3" fill="STROKE_COLOR"/>
        <circle cx="200" cy="105" r="3" fill="STROKE_COLOR"/>
        <!-- Chest pocket -->
        <rect x="240" y="120" width="50" height="58" rx="3" fill="none" stroke="DETAIL_COLOR" stroke-width="1.5"/>
    `,
    longsleeve: `
        <!-- Corporate long sleeve -->
        <path d="M120,60 L100,60 Q70,60 55,95 L20,300 L60,315 L90,150 L90,420 Q90,440 110,440 L290,440 Q310,440 310,420 L310,150 L340,315 L380,300 L345,95 Q330,60 300,60 L280,60 Q270,40 250,30 L200,20 L150,30 Q130,40 120,60 Z"
              fill="APPAREL_COLOR" stroke="STROKE_COLOR" stroke-width="2"/>
        <ellipse cx="200" cy="55" rx="55" ry="20" fill="none" stroke="STROKE_COLOR" stroke-width="2"/>
        <!-- Chest pocket (the 3D model has one here too; the logo zone mirrors it) -->
        <rect x="240" y="120" width="50" height="58" rx="3" fill="none" stroke="DETAIL_COLOR" stroke-width="1.5"/>
        <!-- Cuffs -->
        <rect x="22" y="294" width="42" height="22" rx="4" fill="none" stroke="DETAIL_COLOR" stroke-width="1.5"/>
        <rect x="336" y="294" width="42" height="22" rx="4" fill="none" stroke="DETAIL_COLOR" stroke-width="1.5"/>
    `,
    vest: `
        <!-- Corporate vest -->
        <path d="M140,70 L120,70 Q95,75 90,110 L85,420 Q85,440 105,440 L295,440 Q315,440 315,420 L310,110 Q305,75 280,70 L260,70 L200,150 Z"
              fill="APPAREL_COLOR" stroke="STROKE_COLOR" stroke-width="2"/>
        <path d="M140,70 L200,170 L260,70" fill="none" stroke="STROKE_COLOR" stroke-width="2"/>
        <line x1="200" y1="170" x2="200" y2="430" stroke="STROKE_COLOR" stroke-width="1.5"/>
        <circle cx="200" cy="215" r="3" fill="STROKE_COLOR"/>
        <circle cx="200" cy="260" r="3" fill="STROKE_COLOR"/>
        <circle cx="200" cy="305" r="3" fill="STROKE_COLOR"/>
        <circle cx="200" cy="350" r="3" fill="STROKE_COLOR"/>
    `
};

// Back view mockup SVGs
const mockupsBack = {
    tshirt: `
        <!-- T-Shirt back shape -->
        <path d="M120,60 L100,60 Q60,60 50,100 L30,160 L70,180 L90,120 L90,420 Q90,440 110,440 L290,440 Q310,440 310,420 L310,120 L330,180 L370,160 L350,100 Q340,60 300,60 L280,60 Q270,40 250,30 L200,20 L150,30 Q130,40 120,60 Z"
              fill="APPAREL_COLOR" stroke="STROKE_COLOR" stroke-width="2"/>
        <!-- Back neckline -->
        <path d="M145,55 Q170,65 200,67 Q230,65 255,55" fill="none" stroke="STROKE_COLOR" stroke-width="2"/>
        <!-- Sleeves detail -->
        <path d="M90,120 L70,180 L30,160" fill="none" stroke="DETAIL_COLOR" stroke-width="1"/>
        <path d="M310,120 L330,180 L370,160" fill="none" stroke="DETAIL_COLOR" stroke-width="1"/>
    `,
    hoodie: `
        <!-- Hoodie back shape -->
        <path d="M120,80 L100,80 Q60,80 50,120 L20,200 L70,210 L80,140 L80,420 Q80,440 100,440 L300,440 Q320,440 320,420 L320,140 L330,210 L380,200 L350,120 Q340,80 300,80 L280,80 Q270,55 250,45 L200,35 L150,45 Q130,55 120,80 Z"
              fill="APPAREL_COLOR" stroke="STROKE_COLOR" stroke-width="2"/>
        <!-- Hood (back view) -->
        <path d="M120,80 Q110,30 160,15 L200,10 L240,15 Q290,30 280,80"
              fill="APPAREL_COLOR" stroke="STROKE_COLOR" stroke-width="2"/>
        <!-- Hood center seam -->
        <line x1="200" y1="10" x2="200" y2="80" stroke="DETAIL_COLOR" stroke-width="1.5"/>
    `,
    polo: `
        <!-- Polo back shape -->
        <path d="M120,65 L100,65 Q60,65 50,105 L30,170 L75,185 L90,120 L90,420 Q90,440 110,440 L290,440 Q310,440 310,420 L310,120 L325,185 L370,170 L350,105 Q340,65 300,65 L280,65 Q270,45 250,35 L200,25 L150,35 Q130,45 120,65 Z"
              fill="APPAREL_COLOR" stroke="STROKE_COLOR" stroke-width="2"/>
        <!-- Back collar -->
        <path d="M145,55 Q155,35 200,30 Q245,35 255,55 L250,70 Q240,50 200,45 Q160,50 150,70 Z"
              fill="APPAREL_COLOR" stroke="STROKE_COLOR" stroke-width="2"/>
        <!-- Back yoke seam -->
        <path d="M90,140 Q200,120 310,140" fill="none" stroke="DETAIL_COLOR" stroke-width="1"/>
    `,
    corp_polo: `
        <path d="M120,65 L100,65 Q60,65 50,105 L30,170 L75,185 L90,120 L90,420 Q90,440 110,440 L290,440 Q310,440 310,420 L310,120 L325,185 L370,170 L350,105 Q340,65 300,65 L280,65 Q270,45 250,35 L200,25 L150,35 Q130,45 120,65 Z"
              fill="APPAREL_COLOR" stroke="STROKE_COLOR" stroke-width="2"/>
        <path d="M145,55 Q155,35 200,30 Q245,35 255,55 L250,70 Q240,50 200,45 Q160,50 150,70 Z"
              fill="APPAREL_COLOR" stroke="STROKE_COLOR" stroke-width="2"/>
        <path d="M90,140 Q200,120 310,140" fill="none" stroke="DETAIL_COLOR" stroke-width="1"/>
    `,
    longsleeve: `
        <path d="M120,60 L100,60 Q70,60 55,95 L20,300 L60,315 L90,150 L90,420 Q90,440 110,440 L290,440 Q310,440 310,420 L310,150 L340,315 L380,300 L345,95 Q330,60 300,60 L280,60 Q270,40 250,30 L200,20 L150,30 Q130,40 120,60 Z"
              fill="APPAREL_COLOR" stroke="STROKE_COLOR" stroke-width="2"/>
        <path d="M145,55 Q170,65 200,67 Q230,65 255,55" fill="none" stroke="STROKE_COLOR" stroke-width="2"/>
        <rect x="22" y="294" width="42" height="22" rx="4" fill="none" stroke="DETAIL_COLOR" stroke-width="1.5"/>
        <rect x="336" y="294" width="42" height="22" rx="4" fill="none" stroke="DETAIL_COLOR" stroke-width="1.5"/>
    `,
    vest: `
        <path d="M140,70 L120,70 Q95,75 90,110 L85,420 Q85,440 105,440 L295,440 Q315,440 315,420 L310,110 Q305,75 280,70 L260,70 Q230,60 200,60 Q170,60 140,70 Z"
              fill="APPAREL_COLOR" stroke="STROKE_COLOR" stroke-width="2"/>
        <path d="M160,68 Q200,82 240,68" fill="none" stroke="STROKE_COLOR" stroke-width="2"/>
        <path d="M90,150 Q200,130 310,150" fill="none" stroke="DETAIL_COLOR" stroke-width="1"/>
    `
};

// Design area positioning on mockup — built from the data-driven apparel config
// so adding a new type in includes/apparel-config.php needs no JS changes.
const designAreas = {};
Object.keys(APPAREL_CONFIG).forEach(t => { designAreas[t] = APPAREL_CONFIG[t].area; });

// ===== Init =====
function init() {
    applyMockupScale();
    updateMockup();
    positionCanvas();
    setupCanvasEvents();
    loadMyDesigns();
    saveCanvasState();
}

function isDarkColor(hexColor) {
    const r = parseInt(hexColor.slice(1,3), 16);
    const g = parseInt(hexColor.slice(3,5), 16);
    const b = parseInt(hexColor.slice(5,7), 16);
    return (r * 299 + g * 587 + b * 114) / 1000 < 128;
}

function getContrastStroke(hexColor) {
    if (isDarkColor(hexColor)) {
        return { stroke: 'rgba(255,255,255,0.25)', detail: 'rgba(255,255,255,0.15)' };
    }
    return { stroke: 'rgba(0,0,0,0.13)', detail: 'rgba(0,0,0,0.07)' };
}

// Returns the inner SVG markup for a given apparel type + side, recoloured.
// Lighten (+pct) or darken (-pct) a hex colour — used to build the fabric
// shading gradient so every apparel colour gets realistic depth.
function shadeColor(hex, pct) {
    const n = parseInt(String(hex).replace('#', ''), 16);
    if (isNaN(n)) return hex;
    const amt = Math.round(2.55 * pct);
    const clamp = (v) => Math.max(0, Math.min(255, v));
    const r = clamp((n >> 16) + amt);
    const g = clamp(((n >> 8) & 0xff) + amt);
    const b = clamp((n & 0xff) + amt);
    return '#' + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
}

function mockupInnerFor(type, side, color) {
    const colors = getContrastStroke(color);
    const set = side === 'front' ? mockups : mockupsBack;
    const shape = shapeOf(type);

    // Replace the flat apparel fill with a fabric gradient (soft chest light,
    // darker edges) — realism that still follows the chosen colour, so the
    // preview, 3D flip, couple tiles AND exported order images all inherit it.
    const inner = (set[shape] || set.tshirt)
        .replace(/fill="APPAREL_COLOR"/g, 'fill="url(#fabricGrad)"')
        .replace(/APPAREL_COLOR/g, color)
        .replace(/STROKE_COLOR/g, colors.stroke)
        .replace(/DETAIL_COLOR/g, colors.detail)
        .replace(/stroke-width="2"/g, 'stroke-width="1.2"'); // softer outline, less cartoon

    const light = shadeColor(color, 10);
    const dark  = shadeColor(color, -16);

    return `
        <defs>
            <radialGradient id="fabricGrad" cx="50%" cy="28%" r="85%">
                <stop offset="0%" stop-color="${light}"/>
                <stop offset="55%" stop-color="${color}"/>
                <stop offset="100%" stop-color="${dark}"/>
            </radialGradient>
            <radialGradient id="groundShadow" cx="50%" cy="50%" r="50%">
                <stop offset="0%" stop-color="rgba(0,0,0,0.22)"/>
                <stop offset="70%" stop-color="rgba(0,0,0,0.08)"/>
                <stop offset="100%" stop-color="rgba(0,0,0,0)"/>
            </radialGradient>
        </defs>
        <ellipse cx="200" cy="454" rx="130" ry="16" fill="url(#groundShadow)"/>
        ${inner}`;
}

function updateMockup() {
    const svg = document.getElementById('mockupSvg');
    svg.innerHTML = mockupInnerFor(state.apparelType, state.currentSide, state.apparelColor);
    updateLogoZoneGuide();
    refreshSleeveUI();
}

function positionCanvas() {
    const area = designAreas[state.apparelType];
    canvas.style.left = area.x + 'px';
    canvas.style.top = area.y + 'px';
    canvas.width = area.w;
    canvas.height = area.h;
    canvas.style.width = area.w + 'px';
    canvas.style.height = area.h + 'px';
    
    // Restore drawing
    restoreCanvasState();
}

// ===== Tool Selection =====
function setTool(tool) {
    // Drawing needs the flat canvas.
    if (state.view3d) {
        setMainView('2d');
        showToast('Switched to 2D so you can draw.', 'info');
    }
    state.currentTool = tool;
    document.querySelectorAll('.tool-btn').forEach(b => b.classList.remove('active'));
    document.querySelector(`.tool-btn[data-tool="${tool}"]`).classList.add('active');
    canvas.style.cursor = tool === 'eraser' ? 'cell' : 'crosshair';
}

// Switch which apparel group (tab) is visible. Does not change the selected type.
function setApparelGroup(group) {
    document.querySelectorAll('.apparel-tab').forEach(t => t.classList.toggle('active', t.dataset.group === group));
    document.querySelectorAll('.apparel-group').forEach(g => { g.style.display = g.dataset.group === group ? '' : 'none'; });
}

// Shows/positions the dashed corporate logo placement guide (front view only).
function updateLogoZoneGuide() {
    const guide = document.getElementById('logoZoneGuide');
    if (!guide) return;
    const zone = logoZoneOf(state.apparelType);
    if (zone && state.currentSide === 'front') {
        guide.style.display = 'flex';
        guide.style.left = zone.x + 'px';
        guide.style.top = zone.y + 'px';
        guide.style.width = zone.w + 'px';
        guide.style.height = zone.h + 'px';
        // Show the designed logo in the box (drawn on top, as in the print).
        const img = document.getElementById('logoZoneArt');
        let src = '';
        if (state.logo.length) {
            try { src = renderSleeve(state.logo, 0.6).toDataURL(); } catch (e) { /* unreadable image */ }
        }
        if (src) img.src = src;
        img.hidden = !src;
        guide.classList.toggle('filled', !!src);
    } else {
        guide.style.display = 'none';
    }
}

// True when there is anything designed that switching apparel would wipe:
// drawing or elements on either side, the other Couple Wear partner, sleeves
// or the logo. (Drawing on a side not on screen is tracked by its Ink flag.)
function hasDesignWork() {
    if (state.elements.length || printCanvasHasInk(canvas)) return true;
    const other = state.currentSide === 'front' ? 'back' : 'front';
    if (state[other + 'Elements'].length || state[other + 'Ink']) return true;
    const others = ['A', 'B'].filter(p => p !== state.currentPartner).map(p => state.partners[p]).filter(Boolean);
    if (others.some(b => (b.frontElements || []).length || (b.backElements || []).length || b.frontInk || b.backInk)) return true;
    if (state.logo.length) return true;
    return ['A', 'B'].some(p => state.sleeves[p].left.length || state.sleeves[p].right.length);
}

// `force` skips the checks: used when a saved design or draft is being loaded.
function setApparelType(type, force) {
    if (!APPAREL_CONFIG[type]) return;
    if (!force) {
        if (type === state.apparelType) return;   // re-clicking the current type must not wipe the design
        if (hasDesignWork() && !confirm('Switching to ' + configOf(type).label
                + ' will clear your current design (front, back, sleeves and logo). Continue?')) return;
    }
    state.apparelType = type;
    document.querySelectorAll('.apparel-type-btn').forEach(b => b.classList.remove('active'));
    const typeBtn = document.querySelector(`.apparel-type-btn[data-type="${type}"]`);
    if (typeBtn) typeBtn.classList.add('active');

    document.getElementById('infoType').textContent = configOf(type).label;

    // Reset both sides when changing apparel type
    state.frontCanvasData = null;
    state.backCanvasData = null;
    state.frontInk = false;
    state.backInk = false;
    state.frontElements = [];
    state.backElements = [];
    state.frontUndoStack = [];
    state.backUndoStack = [];
    state.frontRedoStack = [];
    state.backRedoStack = [];
    state.undoStack = [];
    state.redoStack = [];
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    elementsLayer.innerHTML = '';
    state.elements = [];
    state.selectedElement = null;

    // Reset to front side
    state.currentSide = 'front';
    document.getElementById('frontSideBtn').classList.add('active');
    document.getElementById('backSideBtn').classList.remove('active');

    // Couple Wear: reset partners and reveal the Partner A/B toggle
    const couple = isCouple(type);
    state.currentPartner = 'A';
    state.partners = { A: null, B: null };
    state.sleeves = emptySleeves();
    state.logo = [];
    closeSleeveEditor();
    const partnerToggle = document.getElementById('partnerToggle');
    if (partnerToggle) partnerToggle.style.display = couple ? 'inline-flex' : 'none';
    document.getElementById('partnerABtn')?.classList.add('active');
    document.getElementById('partnerBBtn')?.classList.remove('active');

    updateMockup();
    positionCanvas();
    saveCanvasState();
    refreshViewToggle();
    updatePreview();
}

function setApparelColor(btn, color) {
    state.apparelColor = color;
    document.querySelectorAll('.apparel-color-btn').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    
    const colorNames = {'#FFFFFF':'White','#000000':'Black','#001F3F':'Navy','#808080':'Gray','#FF4136':'Red','#2ECC40':'Green','#0074D9':'Blue','#FFDC00':'Yellow','#FF69B4':'Pink','#800000':'Maroon'};
    document.getElementById('infoColor').textContent = colorNames[color] || color;
    
    updateMockup();
    updatePreview();
}

// ===== Front/Back Side Switching =====
function switchSide(side) {
    if (state.currentSide === side) return;
    
    // Save current side's canvas data and elements
    saveCurrentSideData();
    
    // Switch side
    state.currentSide = side;
    
    // Update toggle buttons
    document.getElementById('frontSideBtn').classList.toggle('active', side === 'front');
    document.getElementById('backSideBtn').classList.toggle('active', side === 'back');
    
    // Restore the other side's data
    restoreSideData(side);
    
    // Update mockup SVG
    updateMockup();
    positionCanvas();
    updatePreview();
    // Turn the 3D garment to the side being edited.
    if (use3D()) Design3D.face(side);
}

function saveCurrentSideData() {
    const dataUrl = canvas.toDataURL();
    const side = state.currentSide;
    
    if (side === 'front') {
        state.frontCanvasData = dataUrl;
        state.frontElements = Array.from(elementsLayer.children);
        state.frontUndoStack = [...state.undoStack];
        state.frontRedoStack = [...state.redoStack];
    } else {
        state.backCanvasData = dataUrl;
        state.backElements = Array.from(elementsLayer.children);
        state.backUndoStack = [...state.undoStack];
        state.backRedoStack = [...state.redoStack];
    }
    state[side + 'Ink'] = printCanvasHasInk(canvas);   // for hasDesignWork()
}

function restoreSideData(side) {
    // Clear current canvas and elements
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    elementsLayer.innerHTML = '';
    state.elements = [];
    state.selectedElement = null;
    
    const savedData = side === 'front' ? state.frontCanvasData : state.backCanvasData;
    const savedElements = side === 'front' ? state.frontElements : state.backElements;
    
    // Restore undo/redo stacks
    state.undoStack = side === 'front' ? [...state.frontUndoStack] : [...state.backUndoStack];
    state.redoStack = side === 'front' ? [...state.frontRedoStack] : [...state.backRedoStack];
    
    // Restore canvas drawing
    if (savedData) {
        const img = new Image();
        img.onload = function() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.drawImage(img, 0, 0);
            schedule3D();   // the drawing arrives after the preview already ran
        };
        img.src = savedData;
    }
    
    // Restore draggable elements
    if (savedElements && savedElements.length > 0) {
        savedElements.forEach(el => {
            elementsLayer.appendChild(el);
            state.elements.push(el);
        });
    }
    
    updateElementCount();
    
    // If no undo data exists yet for this side, initialize it
    if (state.undoStack.length === 0) {
        saveCanvasState();
    }
}

// ===== Couple Wear: Partner A / Partner B =====
// A "bundle" snapshots the 8 per-side design fields for one partner.
function snapshotBundle() {
    return {
        frontCanvasData: state.frontCanvasData,
        backCanvasData: state.backCanvasData,
        frontElements: state.frontElements,
        backElements: state.backElements,
        frontUndoStack: state.frontUndoStack,
        backUndoStack: state.backUndoStack,
        frontRedoStack: state.frontRedoStack,
        backRedoStack: state.backRedoStack,
        frontInk: state.frontInk,
        backInk: state.backInk
    };
}

function applyBundle(b) {
    b = b || {};
    state.frontCanvasData = b.frontCanvasData || null;
    state.backCanvasData = b.backCanvasData || null;
    state.frontElements = b.frontElements || [];
    state.backElements = b.backElements || [];
    state.frontUndoStack = b.frontUndoStack || [];
    state.backUndoStack = b.backUndoStack || [];
    state.frontRedoStack = b.frontRedoStack || [];
    state.backRedoStack = b.backRedoStack || [];
    state.frontInk = !!b.frontInk;
    state.backInk = !!b.backInk;
}

function switchPartner(p) {
    if (!isCouple(state.apparelType) || state.currentPartner === p) return;

    // Persist the active side, then stash the whole partner bundle.
    saveCurrentSideData();
    state.partners[state.currentPartner] = snapshotBundle();

    // Load the other partner and reset to front.
    state.currentPartner = p;
    applyBundle(state.partners[p]);
    state.currentSide = 'front';

    document.getElementById('partnerABtn').classList.toggle('active', p === 'A');
    document.getElementById('partnerBBtn').classList.toggle('active', p === 'B');
    document.getElementById('frontSideBtn').classList.add('active');
    document.getElementById('backSideBtn').classList.remove('active');

    restoreSideData('front');
    updateMockup();
    positionCanvas();
    updatePreview();
}

function setBrushColor(btn, color) {
    state.brushColor = color;
    document.querySelectorAll('.color-swatch-btn').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    document.getElementById('customColor').value = color;
}

function setBrushSize(size) {
    state.brushSize = parseInt(size);
    document.getElementById('brushSizeLabel').textContent = size;
}

function toggleBold() {
    state.isBold = !state.isBold;
    document.getElementById('boldBtn').classList.toggle('active', state.isBold);
}

function toggleItalic() {
    state.isItalic = !state.isItalic;
    document.getElementById('italicBtn').classList.toggle('active', state.isItalic);
}

// ===== Canvas Drawing =====
function setupCanvasEvents() {
    canvas.addEventListener('mousedown', startDrawing);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', stopDrawing);
    canvas.addEventListener('mouseleave', stopDrawing);
    
    // Touch support
    canvas.addEventListener('touchstart', (e) => { e.preventDefault(); startDrawing(getTouchEvent(e)); });
    canvas.addEventListener('touchmove', (e) => { e.preventDefault(); draw(getTouchEvent(e)); });
    canvas.addEventListener('touchend', (e) => { e.preventDefault(); stopDrawing(e); });
}

function getTouchEvent(e) {
    const touch = e.touches[0];
    const rect = canvas.getBoundingClientRect();
    // rect is on-screen size; the canvas may be scaled (fit to phone, zoom).
    const k = canvas.width / rect.width;
    return {
        offsetX: (touch.clientX - rect.left) * k,
        offsetY: (touch.clientY - rect.top) * k,
        preventDefault: () => {}
    };
}

function startDrawing(e) {
    state.isDrawing = true;
    state.strokeMoved = false;
    const x = e.offsetX;
    const y = e.offsetY;
    state.startX = x;
    state.startY = y;
    
    if (state.currentTool === 'brush' || state.currentTool === 'eraser') {
        ctx.beginPath();
        ctx.moveTo(x, y);
        ctx.lineWidth = state.brushSize;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        
        if (state.currentTool === 'eraser') {
            ctx.globalCompositeOperation = 'destination-out';
            ctx.strokeStyle = 'rgba(0,0,0,1)';
        } else {
            ctx.globalCompositeOperation = 'source-over';
            ctx.strokeStyle = state.brushColor;
        }
    }
    
    if (['line', 'rect', 'circle'].includes(state.currentTool)) {
        state.drawingShape = ctx.getImageData(0, 0, canvas.width, canvas.height);
    }
}

// Canvas point (design-area pixels) to mockup space, for the logo zone.
function canvasInLogoZone(x, y) {
    const a = areaOf(state.apparelType);
    return state.currentSide === 'front' && inLogoZone(a.x + x, a.y + y);
}

function draw(e) {
    if (!state.isDrawing) {
        // Hovering the logo box: it opens the Logo Designer on a click.
        if (e.offsetX !== undefined && hasLogo()) {
            canvas.style.cursor = canvasInLogoZone(e.offsetX, e.offsetY) ? 'pointer'
                : (state.currentTool === 'eraser' ? 'cell' : 'crosshair');
        }
        return;
    }
    state.strokeMoved = true;

    const x = e.offsetX;
    const y = e.offsetY;
    
    if (state.currentTool === 'brush' || state.currentTool === 'eraser') {
        ctx.lineTo(x, y);
        ctx.stroke();
    } else if (state.currentTool === 'line') {
        ctx.putImageData(state.drawingShape, 0, 0);
        ctx.beginPath();
        ctx.moveTo(state.startX, state.startY);
        ctx.lineTo(x, y);
        ctx.strokeStyle = state.brushColor;
        ctx.lineWidth = state.brushSize;
        ctx.stroke();
    } else if (state.currentTool === 'rect') {
        ctx.putImageData(state.drawingShape, 0, 0);
        ctx.beginPath();
        ctx.strokeStyle = state.brushColor;
        ctx.lineWidth = state.brushSize;
        ctx.strokeRect(state.startX, state.startY, x - state.startX, y - state.startY);
    } else if (state.currentTool === 'circle') {
        ctx.putImageData(state.drawingShape, 0, 0);
        const rx = Math.abs(x - state.startX) / 2;
        const ry = Math.abs(y - state.startY) / 2;
        const cx = state.startX + (x - state.startX) / 2;
        const cy = state.startY + (y - state.startY) / 2;
        ctx.beginPath();
        ctx.ellipse(cx, cy, rx, ry, 0, 0, Math.PI * 2);
        ctx.strokeStyle = state.brushColor;
        ctx.lineWidth = state.brushSize;
        ctx.stroke();
    }
    
    updatePreview();
}

function stopDrawing(e) {
    if (state.isDrawing) {
        state.isDrawing = false;
        ctx.globalCompositeOperation = 'source-over';
        // A tap (no stroke) on the logo box opens the Logo Designer instead.
        if (!state.strokeMoved && e && (e.type === 'mouseup' || e.type === 'touchend')
            && canvasInLogoZone(state.startX, state.startY)) {
            openLogoEditor();
            return;
        }
        saveCanvasState();
        updatePreview();
    }
}

// ===== Text Tool =====
function addTextToCanvas() {
    const text = document.getElementById('textInput').value.trim();
    if (!text) return;
    
    const fontFamily = document.getElementById('fontFamily').value;
    const fontSize = parseInt(document.getElementById('fontSize').value) || 24;

    // Curve, outline or shadow: add it as text art (an image drawn at print size).
    const curve = parseInt(document.getElementById('textCurve').value, 10) || 0;
    const outline = document.getElementById('textOutline').checked;
    const shadow = document.getElementById('textShadow').checked;
    if (curve || outline || shadow) {
        const art = renderTextArt({
            text, family: fontFamily, size: fontSize, bold: state.isBold, italic: state.isItalic,
            color: state.brushColor, curve, outline, outlineColor: document.getElementById('textOutlineColor').value, shadow
        });
        addImageElementFromUrl(art.toDataURL('image/png'), { scale: 1 / TEXT_ART_SCALE, label: text, center: true });
        document.getElementById('textInput').value = '';
        return;
    }
    
    let fontStyle = '';
    if (state.isItalic) fontStyle += 'italic ';
    if (state.isBold) fontStyle += 'bold ';
    
    // Create draggable text element
    const el = document.createElement('div');
    el.className = 'draggable-element';
    el.style.pointerEvents = 'auto';
    el.innerHTML = `
        <span style="font-family:'${fontFamily}'; font-size:${fontSize}px; color:${state.brushColor}; ${state.isBold ? 'font-weight:bold;' : ''} ${state.isItalic ? 'font-style:italic;' : ''} white-space:nowrap; user-select:none;">${escapeHtml(text)}</span>
        <div class="rotate-handle" title="Rotate"></div>
        <div class="resize-handle"></div>
        <div class="delete-handle">&times;</div>
    `;
    
    const area = designAreas[state.apparelType];
    el.style.left = (area.x + 10) + 'px';
    el.style.top = (area.y + 10) + 'px';
    el.dataset.type = 'text';
    
    setupDraggable(el);
    elementsLayer.appendChild(el);
    state.elements.push(el);
    updateElementCount();
    saveCanvasState();
    
    document.getElementById('textInput').value = '';
    updatePreview();
}

// ===== Text art: curved, outlined or shadowed text =====
// Drawn at TEXT_ART_SCALE times the chosen size so it stays sharp in the
// print file (which renders the design at PRINT_SCALE = 4), then added as an
// image element and shown at 1/TEXT_ART_SCALE.
const TEXT_ART_SCALE = 4;

function updateCurveLabel() {
    const v = parseInt(document.getElementById('textCurve').value, 10) || 0;
    document.getElementById('textCurveLabel').textContent =
        v === 0 ? 'Straight' : (v > 0 ? 'Arch ' : 'Smile ') + Math.abs(v) + '%';
}

/**
 * o: { text, family, size, bold, italic, color, curve (-100..100; + arches
 * up like a rainbow, - bends down like a smile; 100 = a half circle),
 * outline, outlineColor, shadow }. Returns a canvas, transparent around the text.
 */
function renderTextArt(o) {
    const fs = o.size * TEXT_ART_SCALE;
    const font = (o.italic ? 'italic ' : '') + (o.bold ? 'bold ' : '') + fs + 'px "' + String(o.family).replace(/"/g, '') + '", Arial, sans-serif';
    const chars = Array.from(o.text);   // keeps emoji / accented letters whole
    const m = document.createElement('canvas').getContext('2d');
    m.font = font;
    const widths = chars.map(ch => m.measureText(ch).width);
    const W = widths.reduce((a, b) => a + b, 0);
    const pad = fs * 0.35 + (o.outline ? fs * 0.1 : 0) + (o.shadow ? fs * 0.12 : 0);

    const theta = Math.abs(o.curve) / 100 * Math.PI;   // total angle the text spans
    const R = theta ? W / theta : 0;
    const halfW = theta ? R * Math.sin(theta / 2) : W / 2;
    const sag = theta ? R * (1 - Math.cos(theta / 2)) : 0;

    const cv = document.createElement('canvas');
    cv.width = Math.ceil(2 * halfW + fs + 2 * pad);
    cv.height = Math.ceil(sag + fs * 1.3 + 2 * pad);
    const c = cv.getContext('2d');
    c.font = font;
    c.textAlign = 'center';
    c.textBaseline = 'middle';
    c.lineJoin = 'round';

    // Every glyph: position and turn. Straight text is laid out left to right.
    const place = [];
    if (!theta) {
        let x = cv.width / 2 - W / 2;
        chars.forEach((ch, i) => { place.push({ ch, x: x + widths[i] / 2, y: cv.height / 2, a: 0 }); x += widths[i]; });
    } else {
        const up = o.curve > 0;
        const cx = cv.width / 2;
        // Circle centre below the text for an arch, above it for a smile.
        const cy = up ? pad + fs * 0.65 + R : cv.height - pad - fs * 0.65 - R;
        let acc = 0;
        chars.forEach((ch, i) => {
            const a = -theta / 2 + (acc + widths[i] / 2) / R;
            acc += widths[i];
            place.push(up
                ? { ch, x: cx + R * Math.sin(a), y: cy - R * Math.cos(a), a }
                : { ch, x: cx + R * Math.sin(a), y: cy + R * Math.cos(a), a: -a });
        });
    }

    const drawAll = fn => place.forEach(p => {
        c.save();
        c.translate(p.x, p.y);
        c.rotate(p.a);
        fn(p.ch);
        c.restore();
    });
    if (o.shadow) {
        c.shadowColor = 'rgba(0,0,0,0.35)';
        c.shadowOffsetX = c.shadowOffsetY = fs * 0.05;
        c.shadowBlur = fs * 0.08;
    }
    if (o.outline) {
        c.strokeStyle = o.outlineColor;
        c.lineWidth = fs * 0.14;
        drawAll(ch => c.strokeText(ch, 0, 0));
        c.shadowColor = 'transparent';   // the outline already carries the shadow
    }
    c.fillStyle = o.color;
    drawAll(ch => c.fillText(ch, 0, 0));
    return cv;
}

// ===== Image Upload =====
// Uploads are validated AND re-encoded server-side (MIME check, size limit,
// EXIF strip, randomized filename). We never embed the raw browser file; we draw
// the sanitized same-origin URL the server returns. The client-side checks below
// are just a fast first line of defense — the server is the source of truth.
function handleImageUpload(input) {
    const file = input.files[0];
    if (!file) return;

    if (file.size > 5 * 1024 * 1024) {
        showToast('Image must be less than 5MB', 'error');
        input.value = '';
        return;
    }
    if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type)) {
        showToast('Only PNG, JPG, and WEBP images are allowed', 'error');
        input.value = '';
        return;
    }

    const fd = new FormData();
    fd.append('action', 'upload_asset');
    fd.append('asset', file);

    fetch('includes/custom-design-ajax.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                showToast(data.message || 'Upload failed.', 'error');
                return;
            }
            addImageElementFromUrl(data.url);
            showToast('Image added!', 'success');
        })
        .catch(() => showToast('Upload failed. Please try again.', 'error'));

    input.value = '';
}

// Creates a draggable/resizable image element from a (same-origin) URL.
// opts.scale: shown size as a fraction of the image's pixels (text art is
// drawn at 4x for print, shown at 1/4); opts.label: its name in Layers (and
// marks it as text art); opts.center: centre it across the print area.
function addImageElementFromUrl(url, opts) {
    opts = opts || {};
    const img = new Image();
    img.onload = function() {
        const area = areaOf(state.apparelType);
        const maxW = area.w - 20;
        const maxH = area.h - 20;
        let w = img.width * (opts.scale || 1);
        let h = img.height * (opts.scale || 1);
        if (w > maxW) { h = h * (maxW / w); w = maxW; }
        if (h > maxH) { w = w * (maxH / h); h = maxH; }

        const el = document.createElement('div');
        el.className = 'draggable-element';
        el.style.pointerEvents = 'auto';
        el.innerHTML = `
            <img src="${url}" style="width:${w}px; height:${h}px; display:block; user-select:none; pointer-events:none;">
            <div class="rotate-handle" title="Rotate"></div>
            <div class="resize-handle"></div>
            <div class="delete-handle">&times;</div>
        `;
        el.style.left = (opts.center ? Math.round(area.x + (area.w - w) / 2) - 4 : area.x + 10) + 'px';
        el.style.top = (area.y + 10) + 'px';
        el.dataset.type = 'image';
        if (opts.label) el.dataset.label = String(opts.label).slice(0, 60);

        setupDraggable(el);
        elementsLayer.appendChild(el);
        state.elements.push(el);
        updateElementCount();
        saveCanvasState();
        updatePreview();
    };
    img.src = url;
}

// ===== AI Design Generator (Gemini) =====
function generateAIDesign() {
    const promptEl = document.getElementById('aiPrompt');
    const prompt = (promptEl.value || '').trim();
    if (!prompt) {
        showToast('Type a design idea first.', 'error');
        promptEl.focus();
        return;
    }

    const btn = document.getElementById('aiGenerateBtn');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating…';

    const fd = new FormData();
    fd.append('action', 'ai_generate');
    fd.append('prompt', prompt);

    fetch('includes/custom-design-ajax.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                showToast(data.message || 'AI design failed.', 'error');
                return;
            }
            // The AI draws on white; take the white away so it prints cleanly on any colour.
            return removeWhiteBackground(data.url).catch(() => null).then(clean => {
                addImageElementFromUrl(clean || data.url);
                showToast('AI design added! Drag, resize, or rotate it to fit.', 'success');
            });
        })
        .catch(() => showToast('Network error. Please try again.', 'error'))
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = orig;
        });
}

// Example prompt chips fill the textarea.
document.querySelectorAll('.ai-chip').forEach((chip) => {
    chip.addEventListener('click', () => {
        const inp = document.getElementById('aiPrompt');
        inp.value = chip.dataset.prompt || '';
        inp.focus();
    });
});

// ===== Animal Stamps (inline SVG, bundled locally — no external service) =====
const ANIMAL_STAMPS = {
    lion: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" fill="currentColor"><path d="M50 6 L60 20 L77 16 L73 33 L90 40 L76 51 L86 67 L68 66 L65 84 L50 74 L35 84 L32 66 L14 67 L24 51 L10 40 L27 33 L23 16 L40 20 Z"/><circle cx="34" cy="33" r="6"/><circle cx="66" cy="33" r="6"/><circle cx="50" cy="50" r="21"/></svg>`,
    tiger: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" fill="currentColor"><path d="M18 22 L40 44 L34 30 Z"/><path d="M82 22 L60 44 L66 30 Z"/><path d="M24 30 L42 46 Q50 42 58 46 L76 30 L72 58 Q72 82 50 86 Q28 82 28 58 Z"/></svg>`,
    fox: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" fill="currentColor"><path d="M50 86 L16 26 L40 42 Q50 38 60 42 L84 26 Z"/></svg>`,
    wolf: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" fill="currentColor"><path d="M22 30 L40 44 Q50 28 60 44 L78 30 L72 60 Q72 82 50 88 Q28 82 28 60 Z"/></svg>`,
    eagle: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" fill="currentColor"><path d="M28 28 Q50 16 72 28 Q74 46 60 54 L78 60 L58 62 Q50 74 42 62 Q26 50 28 28 Z"/></svg>`,
    bear: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" fill="currentColor"><circle cx="28" cy="28" r="13"/><circle cx="72" cy="28" r="13"/><circle cx="50" cy="56" r="30"/></svg>`,
    butterfly: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" fill="currentColor"><ellipse cx="30" cy="34" rx="22" ry="18"/><ellipse cx="70" cy="34" rx="22" ry="18"/><ellipse cx="34" cy="70" rx="17" ry="15"/><ellipse cx="66" cy="70" rx="17" ry="15"/><rect x="46" y="26" width="8" height="52" rx="4"/></svg>`,
    dragon: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" fill="currentColor"><path d="M28 72 Q16 40 44 34 L38 18 L52 32 Q58 31 64 33 L60 16 L74 34 Q90 44 80 64 L92 68 L74 72 Q70 82 56 76 L52 86 L46 76 Q34 78 28 72 Z"/></svg>`,
    unicorn: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" fill="currentColor"><path d="M40 90 L40 54 Q28 49 31 35 Q33 24 47 25 L52 6 L59 28 Q70 33 70 50 L70 90 Z"/></svg>`,
    shark: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" fill="currentColor"><path d="M8 56 Q40 40 74 48 L68 26 L82 50 Q94 52 94 60 Q80 66 70 63 L80 82 L60 65 Q34 72 8 56 Z"/></svg>`
};
const STAMP_ORDER = [
    ['lion','Lion'], ['tiger','Tiger'], ['fox','Fox'], ['wolf','Wolf'], ['eagle','Eagle'],
    ['bear','Bear'], ['butterfly','Butterfly'], ['dragon','Dragon'], ['unicorn','Unicorn'], ['shark','Shark']
];

function renderStampPalette() {
    const grid = document.getElementById('stampGrid');
    if (!grid) return;
    grid.innerHTML = STAMP_ORDER.map(([key, label]) =>
        `<button type="button" class="stamp-btn" title="${label}" style="color:#333;" onclick="placeStamp('${key}')">${ANIMAL_STAMPS[key]}</button>`
    ).join('');
}

function setStampSize(v) {
    state.pendingStampSize = parseInt(v) || 90;
    document.getElementById('stampSizeLabel').textContent = v;
}

// Drops a stamp (sized by the slider) into the design as a draggable element.
// It uses the current brush color and is rendered as an SVG-data-URL <img> so it
// reuses the existing drag/resize/delete and canvas-export pipeline, and counts
// toward the "Elements" total automatically.
function placeStamp(key) {
    const tpl = ANIMAL_STAMPS[key];
    if (!tpl) return;
    const size = state.pendingStampSize;
    const area = areaOf(state.apparelType);
    const colored = tpl.replace(/currentColor/g, state.brushColor);
    const dataUrl = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(colored);

    const el = document.createElement('div');
    el.className = 'draggable-element';
    el.style.pointerEvents = 'auto';
    el.dataset.type = 'image';
    el.dataset.stamp = key;
    el.style.left = Math.round(area.x + area.w / 2 - size / 2) + 'px';
    el.style.top = Math.round(area.y + area.h / 2 - size / 2) + 'px';
    el.innerHTML = `
        <img src="${dataUrl}" style="width:${size}px; height:${size}px; display:block; user-select:none; pointer-events:none;">
        <div class="rotate-handle" title="Rotate"></div>
        <div class="resize-handle"></div>
        <div class="delete-handle">&times;</div>
    `;

    setupDraggable(el);
    elementsLayer.appendChild(el);
    state.elements.push(el);
    updateElementCount();
    saveCanvasState();
    updatePreview();
}

// ===== Sleeve Designer =====
// Each sleeve has its own items (stickers, stamps, text, photos) laid out in a
// SLEEVE_SIZE square. They show on the 3D sleeves, on the flat mockups, and go
// to production as their own print files.
const SLEEVE_SIZE = 300;
const SLEEVE_SIDES = ['left', 'right'];   // the wearer's left and right
const SLEEVE_STICKERS = ['🐶','🐱','🐼','🦊','🐯','🦁','🐻','🐨','🐸','🐵','🦄','🐧',
                         '🐙','🦋','🐝','🐢','🐬','🦖','❤️','⭐','🔥','⚡','🌸','👑'];
// Where the sleeve on the viewer's left sits on each flat mockup (400x500
// space); the other sleeve is its mirror. Missing = sleeveless (the vest).
const SLEEVE_SPOTS = {
    tshirt:     { x: 62, y: 135, s: 38 },
    hoodie:     { x: 58, y: 158, s: 38 },
    polo:       { x: 62, y: 140, s: 38 },
    corp_polo:  { x: 62, y: 140, s: 38 },
    longsleeve: { x: 64, y: 150, s: 30 }
};
const sleeveEd = { partner: 'A', which: 'left', sel: null, drag: null, opener: null, view: '2d' };

function emptySleeves() {
    return { A: { left: [], right: [] }, B: { left: [], right: [] } };
}

function hasSleeves() { return !!SLEEVE_SPOTS[shapeOf(state.apparelType)]; }

// Front view shows the wearer's right sleeve on the viewer's left; the back view the reverse.
function sleeveSpotsFor(side) {
    const s = SLEEVE_SPOTS[shapeOf(state.apparelType)];
    if (!s) return [];
    const near = { x: s.x, y: s.y, s: s.s }, far = { x: 400 - s.x, y: s.y, s: s.s };
    return side === 'front'
        ? [{ which: 'right', ...near }, { which: 'left', ...far }]
        : [{ which: 'left', ...near }, { which: 'right', ...far }];
}

function sleeveFont(it) {
    return it.emoji
        ? it.size + 'px "Segoe UI Emoji","Apple Color Emoji","Noto Color Emoji",sans-serif'
        : '700 ' + it.size + 'px Poppins, Arial, sans-serif';
}

// Width/height of an item, unrotated. `c` is any 2D context (for text metrics).
function sleeveItemBox(c, it) {
    if (it.kind === 'img') {
        const r = it.img.naturalWidth ? it.img.naturalHeight / it.img.naturalWidth : 1;
        return { w: it.size, h: it.size * r };
    }
    c.font = sleeveFont(it);
    return { w: c.measureText(it.text).width, h: it.size };
}

function drawSleeveItem(c, it) {
    c.save();
    c.translate(it.x, it.y);
    c.rotate(it.rot * Math.PI / 180);
    if (it.kind === 'img') {
        if (it.img.complete && it.img.naturalWidth) {
            const b = sleeveItemBox(c, it);
            c.drawImage(it.img, -b.w / 2, -b.h / 2, b.w, b.h);
        }
    } else {
        c.font = sleeveFont(it);
        c.fillStyle = it.color;
        c.textAlign = 'center';
        c.textBaseline = 'middle';
        c.fillText(it.text, 0, 0);
    }
    c.restore();
}

/** One sleeve's artwork on transparency, SLEEVE_SIZE * scale pixels square. */
function renderSleeve(items, scale) {
    const cv = document.createElement('canvas');
    cv.width = cv.height = Math.round(SLEEVE_SIZE * scale);
    const c = cv.getContext('2d');
    c.scale(scale, scale);
    items.forEach(it => drawSleeveItem(c, it));
    return cv;
}

/** Paint a partner's sleeve art onto a flat mockup (400x500 space). */
function drawSleevesOn(c, side, partner) {
    const sleeves = state.sleeves[partner || 'A'];
    sleeveSpotsFor(side).forEach(sp => {
        const items = sleeves[sp.which];
        if (items.length) c.drawImage(renderSleeve(items, 1), sp.x - sp.s / 2, sp.y - sp.s / 2, sp.s, sp.s);
    });
}

// The items the designer is editing: a sleeve, or ('logo') the corporate logo.
function sleeveItems() {
    return sleeveEd.which === 'logo' ? state.logo : state.sleeves[sleeveEd.partner][sleeveEd.which];
}

// ===== Logo Designer =====
// Corporate types have a logo zone on the front. The Logo Designer (the same
// mini canvas as the sleeves) lays out its items in a SLEEVE_SIZE square that
// maps onto that zone. The logo is drawn on top of the front artwork, so it
// shows in every preview, the 3D model and the front print file, and it also
// goes to production as its own file.
function hasLogo() { return !!logoZoneOf(state.apparelType); }

/** Paint the logo onto a front view (400x500 mockup space). */
function drawLogoOn(c, side) {
    const zone = logoZoneOf(state.apparelType);
    if (side !== 'front' || !zone || !state.logo.length) return;
    c.drawImage(renderSleeve(state.logo, 2), zone.x, zone.y, zone.w, zone.h);
}

/** True when (x, y), in mockup space, is inside the logo zone. */
function inLogoZone(x, y) {
    const z = logoZoneOf(state.apparelType);
    return !!z && x >= z.x && x <= z.x + z.w && y >= z.y && y <= z.y + z.h;
}

function openLogoEditor() {
    if (!hasLogo()) return;
    if (state.currentSide !== 'front') switchSide('front');   // the logo is on the front
    openDesigner('logo');
}

function logoPrintData() {
    return hasLogo() && state.logo.length ? renderSleeve(state.logo, 2).toDataURL('image/png') : '';
}

// Click the logo box on the 3D model (front) to open the Logo Designer.
(function setupLogo3DClick() {
    const g3d = document.getElementById('garment3d');
    let down = null;
    g3d.addEventListener('pointerdown', e => { down = { x: e.clientX, y: e.clientY, t: performance.now() }; });
    g3d.addEventListener('pointerup', e => {
        const d = down;
        down = null;
        if (!d || Math.hypot(e.clientX - d.x, e.clientY - d.y) > 6 || performance.now() - d.t > 500) return;
        if (!hasLogo() || !window.Design3D || !document.getElementById('sleeveEditor').hidden) return;
        const uv = Design3D.printUV(e.clientX, e.clientY, 0, 'front');
        if (!uv) return;
        const a = areaOf(state.apparelType);
        if (inLogoZone(a.x + uv.u * a.w, a.y + (1 - uv.v) * a.h)) openLogoEditor();
    });
})();

// Everything that shows sleeves: toolbar counts, the mockup hotspots, the open editor.
function refreshSleeveUI() {
    const on = hasSleeves();
    document.getElementById('sleeveToolSection').style.display = on ? '' : 'none';
    document.getElementById('sleeve3dHint').style.display = on ? '' : 'none';
    const mine = state.sleeves[state.currentPartner] || state.sleeves.A;
    document.getElementById('sleeveCountLeft').textContent = mine.left.length || '';
    document.getElementById('sleeveCountRight').textContent = mine.right.length || '';
    document.getElementById('logoToolSection').style.display = hasLogo() ? '' : 'none';
    document.getElementById('logoCount').textContent = state.logo.length || '';
    updateLogoZoneGuide();

    const svg = document.getElementById('sleeveHotspots');
    svg.innerHTML = sleeveSpotsFor(state.currentSide).map(sp => {
        const items = mine[sp.which];
        let art = '<text class="plus" x="' + sp.x + '" y="' + sp.y + '">+</text>';
        if (items.length) {
            try {
                art = '<image href="' + renderSleeve(items, 0.4).toDataURL() + '" x="' + (sp.x - sp.s / 2) + '" y="' + (sp.y - sp.s / 2)
                    + '" width="' + sp.s + '" height="' + sp.s + '"/>';
            } catch (e) { /* unreadable image: keep the plain hotspot */ }
        }
        const label = 'Design the ' + sp.which + ' sleeve';
        return '<g class="sleeve-spot' + (items.length ? ' filled' : '') + '" tabindex="0" role="button" aria-label="' + label
            + '" data-which="' + sp.which + '"><title>' + label + '</title>'
            + '<circle class="ring" cx="' + sp.x + '" cy="' + sp.y + '" r="' + (sp.s / 2 + 5) + '"/>' + art + '</g>';
    }).join('');

    if (!document.getElementById('sleeveEditor').hidden) drawSleeveStage();
}

document.getElementById('sleeveHotspots').addEventListener('click', e => {
    const g = e.target.closest('.sleeve-spot');
    if (g) openSleeveEditor(g.dataset.which);
});
document.getElementById('sleeveHotspots').addEventListener('keydown', e => {
    const g = e.target.closest('.sleeve-spot');
    if (g && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); openSleeveEditor(g.dataset.which); }
});
window.addEventListener('design3d-sleeve', e => {
    openSleeveEditor(e.detail.which, isCouple(state.apparelType) && e.detail.slot === 1 ? 'B' : 'A');
});

function openSleeveEditor(which, partner) {
    if (!hasSleeves()) return;
    openDesigner(which === 'right' ? 'right' : 'left', partner);
}

// Opens the mini canvas on a sleeve ('left' / 'right') or on the logo ('logo').
function openDesigner(which, partner) {
    const ed = document.getElementById('sleeveEditor');
    if (ed.hidden) sleeveEd.opener = document.activeElement;
    const logo = which === 'logo';
    const couple = isCouple(state.apparelType) && !logo;
    sleeveEd.partner = couple ? (partner || state.currentPartner) : 'A';
    document.getElementById('sleeveEditorName').textContent = logo ? 'Logo Designer' : 'Sleeve Designer';
    document.getElementById('sleeveEditorPartner').textContent = couple ? '· Partner ' + sleeveEd.partner : '';
    ed.querySelector('.sleeve-tabs').hidden = logo;
    document.getElementById('copySleeveBtn').hidden = logo;
    document.getElementById('clearDesignerBtn').textContent = logo ? 'Clear logo' : 'Clear sleeve';
    document.getElementById('sleeveAiLabel').textContent = logo ? 'AI logo' : 'AI sticker';
    document.getElementById('sleeveAiPrompt').placeholder = logo ? 'Company name, e.g. ACME Foods' : 'e.g. a small tiger badge';
    ed.hidden = false;
    document.getElementById('sleeveViewToggle').hidden = !use3D();
    setSleeveView(sleeveEd.view);
    switchSleeve(which);
    (logo ? ed.querySelector('.sleeve-x') : ed.querySelector('.sleeve-tabs button.active'))?.focus();
}

// 2D Edit shows the flat sleeve canvas; 3D View borrows the page's 3D preview
// and flies the camera to the sleeve being designed.
function setSleeveView(view) {
    const three = view === '3d' && use3D();
    sleeveEd.view = three ? '3d' : '2d';
    document.querySelectorAll('#sleeveViewToggle button').forEach(b => b.classList.toggle('active', b.dataset.view === sleeveEd.view));
    const g3d = document.getElementById('garment3d');
    const slot = document.getElementById('sleeve3dSlot');
    document.getElementById('sleeveStage').hidden = three;
    slot.hidden = !three;
    document.getElementById('sleeveStageHint').textContent = three
        ? 'Drag a sticker to move it • drag the shirt to rotate'
        : 'Drag to move • scroll to resize';
    if (three) {
        if (g3d.parentElement !== slot) slot.appendChild(g3d);
        focusSleeve3D();
    } else {
        restoreGarment3D();
    }
}

function focusSleeve3D() {
    if (sleeveEd.view !== '3d' || !use3D()) return;
    if (sleeveEd.which !== 'logo') {
        Design3D.focusSleeve(sleeveSlot(), sleeveEd.which);
        return;
    }
    // Close-up of the logo zone's centre on the front print.
    const z = logoZoneOf(state.apparelType), a = areaOf(state.apparelType);
    if (!z) return;
    if (!Design3D.focusPrint(0, 'front', (z.x + z.w / 2 - a.x) / a.w, 1 - (z.y + z.h / 2 - a.y) / a.h)) {
        Design3D.home('front');
    }
}

// Which 3D garment holds the sleeve being edited (Couple Wear: B is the second).
function sleeveSlot() {
    return isCouple(state.apparelType) && sleeveEd.partner === 'B' ? 1 : 0;
}

// Put the 3D preview back where it lives (the main canvas in 3D mode, else
// the side panel), at its default framing.
function restoreGarment3D() {
    const g3d = document.getElementById('garment3d');
    const home = garment3dHome();
    if (g3d.parentElement === home) return;
    home.prepend(g3d);
    if (window.Design3D) Design3D.home();
}

function garment3dHome() {
    return document.getElementById(state.view3d ? 'canvas3dSlot' : 'garment3dWrap');
}

// ===== Main canvas 2D / 3D mode =====
// 3D mode moves the 3D garment into the main canvas; the side panel then shows
// the flat front/back preview instead. Drawing still happens in 2D.
function setMainView(view) {
    state.view3d = view === '3d' && use3D();
    document.querySelectorAll('#mainViewToggle button').forEach(b =>
        b.classList.toggle('active', b.dataset.view === (state.view3d ? '3d' : '2d')));
    document.getElementById('canvasWrapper').style.display = state.view3d ? 'none' : '';
    if (!state.view3d) applyMockupScale();   // the page may have been resized while hidden
    document.getElementById('canvas3dSlot').hidden = !state.view3d;
    document.getElementById('zoomGroup').style.display = state.view3d ? 'none' : '';
    document.getElementById('canvas3dSleeveHint').style.display = hasSleeves() ? '' : 'none';
    updatePreview();
    // While the sleeve editor has the model, it goes home when the editor closes.
    if (document.getElementById('sleeveEditor').hidden) {
        const g3d = document.getElementById('garment3d');
        const home = garment3dHome();
        if (g3d.parentElement !== home) home.prepend(g3d);
        if (use3D()) Design3D.home(state.currentSide);   // re-frame for the new box
    }
}

// Back to the normal framing (turn and zoom) of the side being edited.
function resetView3D() {
    if (use3D()) Design3D.home(state.currentSide);
}

// The toggle only shows when this apparel type has a working 3D model.
function refreshViewToggle() {
    const ok = use3D();
    document.getElementById('mainViewToggle').style.display = ok ? 'inline-flex' : 'none';
    if (!ok && state.view3d) setMainView('2d');
}

function closeSleeveEditor() {
    const ed = document.getElementById('sleeveEditor');
    if (!ed || ed.hidden) return;
    ed.hidden = true;
    restoreGarment3D();
    sleeveEd.sel = null;
    sleeveEd.drag = null;
    if (sleeveEd.opener && document.contains(sleeveEd.opener)) sleeveEd.opener.focus();
}

function switchSleeve(which) {
    sleeveEd.which = which;
    sleeveEd.sel = null;
    document.querySelectorAll('.sleeve-tabs button').forEach(b => b.classList.toggle('active', b.dataset.which === which));
    syncSleeveSel();
    drawSleeveStage();
    focusSleeve3D();
}

function drawSleeveStage() {
    const cv = document.getElementById('sleeveStage');
    const c = cv.getContext('2d');
    const k = cv.width / SLEEVE_SIZE;
    c.setTransform(k, 0, 0, k, 0, 0);
    c.clearRect(0, 0, SLEEVE_SIZE, SLEEVE_SIZE);
    // The fabric colour behind, so the artwork is judged against the real shirt.
    c.fillStyle = state.apparelColor;
    c.fillRect(0, 0, SLEEVE_SIZE, SLEEVE_SIZE);
    c.save();
    c.setLineDash([6, 5]);
    c.strokeStyle = isDarkColor(state.apparelColor) ? 'rgba(255,255,255,0.35)' : 'rgba(0,0,0,0.2)';
    c.strokeRect(10, 10, SLEEVE_SIZE - 20, SLEEVE_SIZE - 20);
    // Faint centre lines, to line things up by eye.
    c.setLineDash([2, 6]);
    c.beginPath();
    c.moveTo(SLEEVE_SIZE / 2, 10); c.lineTo(SLEEVE_SIZE / 2, SLEEVE_SIZE - 10);
    c.moveTo(10, SLEEVE_SIZE / 2); c.lineTo(SLEEVE_SIZE - 10, SLEEVE_SIZE / 2);
    c.stroke();
    c.restore();

    sleeveItems().forEach(it => drawSleeveItem(c, it));

    // While dragging: a solid guide on each centre line the item snapped to.
    const g = sleeveEd.guides;
    if (g && (g.v || g.h)) {
        c.save();
        c.strokeStyle = '#e0407b';
        c.lineWidth = 1.2;
        c.beginPath();
        if (g.v) { c.moveTo(SLEEVE_SIZE / 2, 0); c.lineTo(SLEEVE_SIZE / 2, SLEEVE_SIZE); }
        if (g.h) { c.moveTo(0, SLEEVE_SIZE / 2); c.lineTo(SLEEVE_SIZE, SLEEVE_SIZE / 2); }
        c.stroke();
        c.restore();
    }

    const it = sleeveEd.sel;
    if (it) {
        const b = sleeveItemBox(c, it);
        c.save();
        c.translate(it.x, it.y);
        c.rotate(it.rot * Math.PI / 180);
        c.setLineDash([5, 4]);
        c.lineWidth = 1.5;
        c.strokeStyle = '#c8a96e';
        c.strokeRect(-b.w / 2 - 5, -b.h / 2 - 5, b.w + 10, b.h + 10);
        c.restore();
    }
}

// Redraw the stage now; push the change to the shirt previews once a frame.
let sleeveFrame = 0;
function sleeveChanged() {
    drawSleeveStage();
    if (sleeveFrame) return;
    sleeveFrame = requestAnimationFrame(() => {
        sleeveFrame = 0;
        refreshSleeveUI();
        updatePreview();
    });
}

function syncSleeveSel() {
    const it = sleeveEd.sel;
    document.getElementById('sleeveSel').hidden = !it;
    if (!it) return;
    // Photos and logos only; the built-in stamps have no background.
    document.getElementById('sleeveBgBtn').hidden = !(it.kind === 'img' && it.src && !it.src.startsWith('data:image/svg'));
    document.getElementById('sleeveSelSize').value = it.size;
    document.getElementById('sleeveSelRot').value = it.rot;
}

function setSleeveSel(prop, v) {
    if (!sleeveEd.sel) return;
    sleeveEd.sel[prop] = parseFloat(v) || 0;
    sleeveChanged();
}

function deleteSleeveSel() {
    const items = sleeveItems();
    const i = items.indexOf(sleeveEd.sel);
    if (i !== -1) items.splice(i, 1);
    sleeveEd.sel = null;
    syncSleeveSel();
    sleeveChanged();
}

function sleeveSelToFront() {
    const items = sleeveItems();
    const i = items.indexOf(sleeveEd.sel);
    if (i === -1) return;
    items.push(items.splice(i, 1)[0]);
    sleeveChanged();
}

const MAX_SLEEVE_ITEMS = 12;
function addSleeveItem(it) {
    const items = sleeveItems();
    if (items.length >= MAX_SLEEVE_ITEMS) {
        showToast((sleeveEd.which === 'logo' ? 'The logo' : 'A sleeve') + ' fits up to ' + MAX_SLEEVE_ITEMS + ' items.', 'error');
        return;
    }
    // Stagger new items a little so they don't hide each other.
    const off = (items.length % 5) * 12 - 24;
    Object.assign(it, { x: SLEEVE_SIZE / 2 + off, y: SLEEVE_SIZE / 2 + off, rot: 0 });
    items.push(it);
    sleeveEd.sel = it;
    syncSleeveSel();
    sleeveChanged();
}

function sleeveImage(src) {
    const img = new Image();
    img.onload = sleeveChanged;   // it draws once it has loaded
    img.src = src;
    return img;
}

function addSleeveSticker(emoji) {
    addSleeveItem({ kind: 'text', emoji: true, text: emoji, size: 130, color: '#000' });
}

function addSleeveStamp(key) {
    const tpl = ANIMAL_STAMPS[key];
    if (!tpl) return;
    const colored = tpl.replace(/currentColor/g, document.getElementById('sleeveColor').value);
    const src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(colored);
    addSleeveItem({ kind: 'img', src, img: sleeveImage(src), size: 150 });
}

function addSleeveText() {
    const input = document.getElementById('sleeveText');
    const text = input.value.trim();
    if (!text) { input.focus(); return; }
    const it = { kind: 'text', text, size: 64, color: document.getElementById('sleeveColor').value };
    // Shrink long text so it starts inside the square.
    const w = sleeveItemBox(document.getElementById('sleeveStage').getContext('2d'), it).w;
    if (w > SLEEVE_SIZE * 0.8) it.size = Math.max(16, Math.floor(it.size * SLEEVE_SIZE * 0.8 / w));
    addSleeveItem(it);
    input.value = '';
}

// Same server-side validation and re-encoding as the main image upload.
function handleSleeveUpload(input) {
    const file = input.files[0];
    input.value = '';
    if (!file) return;
    if (file.size > 5 * 1024 * 1024) { showToast('Image must be less than 5MB', 'error'); return; }
    if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type)) {
        showToast('Only PNG, JPG, and WEBP images are allowed', 'error');
        return;
    }
    // The upload can finish after the editor moved to another sleeve; keep the target.
    const target = designerTarget();
    const btn = document.getElementById('sleeveUploadBtn');
    btn.disabled = true;
    const fd = new FormData();
    fd.append('action', 'upload_asset');
    fd.append('asset', file);
    fetch('includes/custom-design-ajax.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (!data.success) { showToast(data.message || 'Upload failed.', 'error'); return; }
            if (!backToDesignerTarget(target)) return;
            // A logo upload fills most of the logo box; a sleeve photo starts smaller.
            addSleeveItem({ kind: 'img', src: data.url, img: sleeveImage(data.url), size: target.which === 'logo' ? 240 : 170 });
        })
        .catch(() => showToast('Upload failed. Please try again.', 'error'))
        .finally(() => { btn.disabled = false; });
}

// Uploads and AI requests finish later; by then the designer may be on another
// sleeve, or the apparel may have changed. designerTarget() remembers where
// the result belongs; backToDesignerTarget() goes back there, or returns
// false when that place no longer exists (the result is then dropped).
function designerTarget() {
    return { type: state.apparelType, partner: sleeveEd.partner, which: sleeveEd.which };
}

function backToDesignerTarget(t) {
    if (t.type !== state.apparelType) return false;
    if (t.partner !== sleeveEd.partner || t.which !== sleeveEd.which) {
        if (!document.getElementById('sleeveEditor').hidden) {
            openDesigner(t.which, t.partner);   // show where it lands
        } else {
            sleeveEd.partner = t.partner;
            sleeveEd.which = t.which;
        }
    }
    return true;
}

// AI in the designer: a logo from a company name, or a sleeve sticker from an idea.
function generateSleeveAI() {
    const input = document.getElementById('sleeveAiPrompt');
    const idea = input.value.trim();
    if (!idea) { input.focus(); return; }
    const logo = sleeveEd.which === 'logo';
    const prompt = logo
        ? 'A clean, modern company logo for "' + idea + '": a simple emblem or monogram together with the name, '
          + '2 to 3 flat colours, bold shapes, no tiny details, centred, on a plain white background'
        : 'A small sleeve badge or sticker of ' + idea + ': simple bold shapes, a thick outline, '
          + '2 to 4 flat colours, centred, on a plain white background';
    const target = designerTarget();
    const btn = document.getElementById('sleeveAiBtn');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    const fd = new FormData();
    fd.append('action', 'ai_generate');
    fd.append('prompt', prompt);
    fetch('includes/custom-design-ajax.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (!data.success) { showToast(data.message || 'AI design failed.', 'error'); return; }
            return removeWhiteBackground(data.url).catch(() => null).then(clean => {
                if (!backToDesignerTarget(target)) return;
                const src = clean || data.url;
                addSleeveItem({ kind: 'img', src, img: sleeveImage(src), size: target.which === 'logo' ? 250 : 200 });
                alignSleeveSel('c');
                input.value = '';
                showToast(target.which === 'logo' ? 'AI logo added!' : 'AI sticker added!', 'success');
            });
        })
        .catch(() => showToast('Network error. Please try again.', 'error'))
        .finally(() => { btn.disabled = false; btn.innerHTML = orig; });
}

// Clears whatever the designer is on (a sleeve or the logo).
function clearSleeve() {
    sleeveItems().length = 0;
    sleeveEd.sel = null;
    syncSleeveSel();
    sleeveChanged();
}

function copySleeveToOther() {
    const items = sleeveItems();
    if (!items.length) { showToast('This sleeve is empty.', 'error'); return; }
    const other = sleeveEd.which === 'left' ? 'right' : 'left';
    state.sleeves[sleeveEd.partner][other] = items.map(it => ({ ...it }));
    showToast('Copied to the ' + other + ' sleeve.', 'success');
    sleeveChanged();
}

// Move an item (kept inside the square). Near the centre lines it snaps onto
// them, and the stage shows a guide, so a logo or badge is easy to centre.
const SNAP = 6;
function moveSleeveItem(it, x, y) {
    const c = SLEEVE_SIZE / 2;
    x = Math.max(0, Math.min(SLEEVE_SIZE, x));
    y = Math.max(0, Math.min(SLEEVE_SIZE, y));
    sleeveEd.guides = { v: Math.abs(x - c) < SNAP, h: Math.abs(y - c) < SNAP };
    it.x = sleeveEd.guides.v ? c : x;
    it.y = sleeveEd.guides.h ? c : y;
}

function endSnap() {
    sleeveEd.guides = null;
    drawSleeveStage();
}

// Alignment buttons: centre across ('h'), down ('v'), both ('c'), or 'fit'
// (as large as fits inside the dashed area, centred).
function alignSleeveSel(how) {
    const it = sleeveEd.sel;
    if (!it) return;
    const c = SLEEVE_SIZE / 2;
    if (how === 'h' || how === 'c' || how === 'fit') it.x = c;
    if (how === 'v' || how === 'c' || how === 'fit') it.y = c;
    if (how === 'fit') {
        const b = sleeveItemBox(document.getElementById('sleeveStage').getContext('2d'), it);
        const a = it.rot * Math.PI / 180;   // the rotated box has to fit
        const w = Math.abs(b.w * Math.cos(a)) + Math.abs(b.h * Math.sin(a));
        const h = Math.abs(b.w * Math.sin(a)) + Math.abs(b.h * Math.cos(a));
        const k = (SLEEVE_SIZE - 20) / Math.max(w, h, 1);
        it.size = Math.max(16, Math.min(280, it.size * k));
    }
    syncSleeveSel();
    sleeveChanged();
}

// The top item of the open sleeve under point p (sleeve space), or null.
function sleeveHitTest(p) {
    const c = document.getElementById('sleeveStage').getContext('2d');
    const items = sleeveItems();
    for (let i = items.length - 1; i >= 0; i--) {
        const it = items[i], b = sleeveItemBox(c, it);
        const a = -it.rot * Math.PI / 180, dx = p.x - it.x, dy = p.y - it.y;
        const lx = dx * Math.cos(a) - dy * Math.sin(a), ly = dx * Math.sin(a) + dy * Math.cos(a);
        if (Math.abs(lx) <= b.w / 2 + 4 && Math.abs(ly) <= b.h / 2 + 4) return it;
    }
    return null;
}

// Stage pointer handling: pick the top item under the pointer and drag it.
(function setupSleeveStage() {
    const cv = document.getElementById('sleeveStage');
    const toStage = e => {
        const r = cv.getBoundingClientRect();
        return { x: (e.clientX - r.left) * SLEEVE_SIZE / r.width, y: (e.clientY - r.top) * SLEEVE_SIZE / r.height };
    };
    const hitTest = sleeveHitTest;
    cv.addEventListener('pointerdown', e => {
        const p = toStage(e);
        sleeveEd.sel = hitTest(p);
        syncSleeveSel();
        if (sleeveEd.sel) {
            sleeveEd.drag = { dx: p.x - sleeveEd.sel.x, dy: p.y - sleeveEd.sel.y };
            cv.setPointerCapture(e.pointerId);
            cv.style.cursor = 'grabbing';
        }
        drawSleeveStage();
    });
    cv.addEventListener('pointermove', e => {
        const p = toStage(e);
        if (!sleeveEd.drag || !sleeveEd.sel) {
            if (e.pointerType === 'mouse') cv.style.cursor = hitTest(p) ? 'grab' : 'default';
            return;
        }
        moveSleeveItem(sleeveEd.sel, p.x - sleeveEd.drag.dx, p.y - sleeveEd.drag.dy);
        sleeveChanged();
    });
    const end = () => { sleeveEd.drag = null; cv.style.cursor = 'default'; endSnap(); };
    cv.addEventListener('pointerup', end);
    cv.addEventListener('pointercancel', end);
    cv.addEventListener('wheel', e => {
        if (!sleeveEd.sel) return;
        e.preventDefault();
        sleeveEd.sel.size = Math.max(16, Math.min(280, sleeveEd.sel.size * (e.deltaY < 0 ? 1.08 : 0.93)));
        syncSleeveSel();
        sleeveChanged();
    }, { passive: false });
})();

// 3D View: drag a sticker/stamp on the 3D sleeve itself. A press on an item
// moves it within the sleeve's print area; a press anywhere else still turns
// the model. Runs in the capture phase so the 3D controls never see a press
// that grabbed an item.
(function setupSleeve3DDrag() {
    const slotEl = document.getElementById('sleeve3dSlot');
    let drag = null;
    const toSleeve = e => {
        if (!window.Design3D) return null;
        if (sleeveEd.which === 'logo') {
            // The logo is part of the front print: front texture -> mockup -> logo space.
            const uv = Design3D.printUV(e.clientX, e.clientY, 0, 'front');
            const z = logoZoneOf(state.apparelType), a = areaOf(state.apparelType);
            if (!uv || !z) return null;
            return {
                x: (a.x + uv.u * a.w - z.x) / z.w * SLEEVE_SIZE,
                y: (a.y + (1 - uv.v) * a.h - z.y) / z.h * SLEEVE_SIZE
            };
        }
        const uv = Design3D.printUV(e.clientX, e.clientY, sleeveSlot(), sleeveEd.which);
        return uv && { x: uv.u * SLEEVE_SIZE, y: (1 - uv.v) * SLEEVE_SIZE };   // texture v runs up
    };
    const setCursor = c => { const cv = slotEl.querySelector('canvas'); if (cv) cv.style.cursor = c; };

    slotEl.addEventListener('pointerdown', e => {
        if (e.button > 0 || slotEl.hidden) return;
        const p = toSleeve(e);
        const it = p && sleeveHitTest(p);
        if (!it) return;   // not on an item: let the model rotate
        e.stopPropagation();
        e.preventDefault();
        sleeveEd.sel = it;
        syncSleeveSel();
        drawSleeveStage();
        drag = { id: e.pointerId, it, dx: p.x - it.x, dy: p.y - it.y };
        slotEl.setPointerCapture(e.pointerId);
        setCursor('grabbing');
    }, true);

    slotEl.addEventListener('pointermove', e => {
        if (!drag || e.pointerId !== drag.id) return;
        e.stopPropagation();
        const p = toSleeve(e);
        if (!p) return;   // off the print area: the item waits at the edge
        moveSleeveItem(drag.it, p.x - drag.dx, p.y - drag.dy);
        sleeveChanged();
    }, true);

    const end = e => {
        if (!drag || e.pointerId !== drag.id) return;
        e.stopPropagation();
        drag = null;
        setCursor('');
        endSnap();
    };
    slotEl.addEventListener('pointerup', end, true);
    slotEl.addEventListener('pointercancel', end, true);
})();

document.getElementById('sleeveEditor').addEventListener('click', e => {
    if (e.target.id === 'sleeveEditor') closeSleeveEditor();   // backdrop
});
document.addEventListener('keydown', e => {
    if (document.getElementById('sleeveEditor').hidden) return;
    if (e.key === 'Escape') closeSleeveEditor();
    else if ((e.key === 'Delete' || e.key === 'Backspace') && sleeveEd.sel && !/INPUT|TEXTAREA/.test(e.target.tagName)) {
        e.preventDefault();
        deleteSleeveSel();
    }
});

function renderSleevePalettes() {
    document.getElementById('sleeveStickerGrid').innerHTML = SLEEVE_STICKERS.map(s =>
        '<button type="button" onclick="addSleeveSticker(\'' + s + '\')" aria-label="Add ' + s + ' sticker">' + s + '</button>'
    ).join('');
    document.getElementById('sleeveStampGrid').innerHTML = STAMP_ORDER.map(([key, label]) =>
        '<button type="button" style="color:#333;" title="' + label + '" onclick="addSleeveStamp(\'' + key + '\')">' + ANIMAL_STAMPS[key] + '</button>'
    ).join('');
}

/**
 * Print file for one sleeve: the artwork alone on transparency, at twice the
 * editor resolution. Couple Wear puts Partner A and B side by side. Empty
 * string when there is nothing on that sleeve.
 */
function sleevePrintData(which) {
    const cv = sleevePrintCanvas(which);
    return cv ? cv.toDataURL('image/png') : '';
}

// The sleeve print file as a canvas, or null when nothing is on that sleeve.
function sleevePrintCanvas(which) {
    if (!hasSleeves()) return null;
    const parts = (isCouple(state.apparelType) ? ['A', 'B'] : ['A']).map(p => state.sleeves[p][which]);
    if (parts.every(items => !items.length)) return null;
    const S = SLEEVE_SIZE * 2, gap = 40;
    const out = document.createElement('canvas');
    out.width = parts.length * S + (parts.length - 1) * gap;
    out.height = S;
    const c = out.getContext('2d');
    parts.forEach((items, i) => c.drawImage(renderSleeve(items, 2), i * (S + gap), 0));
    return out;
}

// ===== Drag & Resize =====
// Pointer events, so it works with a finger too. Screen-pixel moves are divided
// by the mockup's on-screen scale (fit to phone, zoom) to get mockup pixels.
function setupDraggable(el) {
    let isDragging = false;
    let isResizing = false;
    let isRotating = false;
    let startX, startY, startLeft, startTop, startW, startH, startFont, scale;
    let changed = false;
    let cx, cy, startAngle, startRotation;
    el.style.touchAction = 'none';   // a finger drags the element instead of scrolling

    el.addEventListener('pointerdown', function(e) {
        if (e.button > 0) return;
        if (e.target.classList.contains('delete-handle')) {
            el.remove();
            state.elements = state.elements.filter(item => item !== el);
            if (state.selectedElement === el) state.selectedElement = null;
            updateElementCount();
            saveCanvasState();
            updatePreview();
            return;
        }

        // Select this element
        document.querySelectorAll('.draggable-element').forEach(d => d.classList.remove('selected'));
        el.classList.add('selected');
        state.selectedElement = el;
        renderLayers();

        if (e.target.classList.contains('rotate-handle')) {
            isRotating = true;
            const r = el.getBoundingClientRect();
            cx = r.left + r.width / 2;
            cy = r.top + r.height / 2;
            startAngle = Math.atan2(e.clientY - cy, e.clientX - cx);
            startRotation = parseFloat(el.dataset.rotation) || 0;
        } else if (e.target.classList.contains('resize-handle')) {
            isResizing = true;
            const content = el.querySelector('img, span');
            startW = content.offsetWidth;
            startH = content.offsetHeight;
            startFont = parseFloat(content.style.fontSize) || 24;
            startX = e.clientX;
            startY = e.clientY;
        } else {
            isDragging = true;
            startX = e.clientX;
            startY = e.clientY;
            startLeft = el.offsetLeft;
            startTop = el.offsetTop;
        }
        scale = mockupScale();
        el.setPointerCapture(e.pointerId);

        e.stopPropagation();
    });

    el.addEventListener('pointermove', function(e) {
        if (isDragging || isResizing || isRotating) changed = true;
        if (isDragging) {
            el.style.left = (startLeft + (e.clientX - startX) / scale) + 'px';
            el.style.top = (startTop + (e.clientY - startY) / scale) + 'px';
            updatePreview();
        }
        if (isResizing) {
            const content = el.querySelector('img, span');
            const newW = Math.max(20, startW + (e.clientX - startX) / scale);
            const ratio = newW / startW;
            content.style.width = newW + 'px';
            if (content.tagName === 'IMG') {
                content.style.height = (startH * ratio) + 'px';
            } else {
                // From the size at the start of the drag, not the current one,
                // or the text grows exponentially.
                content.style.fontSize = (startFont * ratio) + 'px';
            }
            updatePreview();
        }
        if (isRotating) {
            const ang = Math.atan2(e.clientY - cy, e.clientX - cx);
            const deg = startRotation + (ang - startAngle) * 180 / Math.PI;
            el.dataset.rotation = deg;
            el.style.transform = 'rotate(' + deg + 'deg)';
            updatePreview();
        }
    });

    const end = function() {
        isDragging = false;
        isResizing = false;
        isRotating = false;
        if (changed) saveCanvasState();   // one undo step per move / resize / turn
        changed = false;
    };
    el.addEventListener('pointerup', end);
    el.addEventListener('pointercancel', end);
}

// Click on canvas wrapper to deselect elements
document.getElementById('canvasWrapper').addEventListener('click', function(e) {
    if (e.target === this || e.target.id === 'canvasWrapper') {
        document.querySelectorAll('.draggable-element').forEach(d => d.classList.remove('selected'));
        state.selectedElement = null;
    }
});

function deleteSelected() {
    if (state.selectedElement) {
        state.selectedElement.remove();
        state.elements = state.elements.filter(item => item !== state.selectedElement);
        state.selectedElement = null;
        updateElementCount();
        saveCanvasState();
        updatePreview();
    }
}

// ===== Undo / Redo =====
// Each step holds the drawing AND the elements of the side being edited:
// { c: PNG data URL (null = blank), e: [elements as data] }, so stickers,
// text and images can be undone too. (A plain string is a drawing-only step.)
function historyEntry() {
    return { c: canvas.toDataURL(), e: state.elements.map(serializeElement) };
}

function saveCanvasState() {
    state.undoStack.push(historyEntry());
    if (state.undoStack.length > 30) state.undoStack.shift();
    state.redoStack = [];
}

// Draws a step's drawing onto the canvas, then calls `then`.
function drawHistoryCanvas(entry, then) {
    const data = typeof entry === 'string' ? entry : (entry && entry.c);
    if (!data) {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        if (then) then();
        return;
    }
    const img = new Image();
    img.onload = function() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(img, 0, 0);
        if (then) then();
    };
    img.src = data;
}

// Puts a whole step back: its drawing and, when it has them, its elements.
function applyHistoryEntry(entry) {
    if (entry && typeof entry === 'object' && Array.isArray(entry.e)) {
        elementsLayer.innerHTML = '';
        state.selectedElement = null;
        state.elements = entry.e.map(elementFromData).filter(Boolean);
        state.elements.forEach(el => elementsLayer.appendChild(el));
        updateElementCount();
    }
    drawHistoryCanvas(entry, updatePreview);
    updatePreview();
}

// Redraws the current step's drawing only (after the canvas was resized).
function restoreCanvasState() {
    if (state.undoStack.length > 0) {
        drawHistoryCanvas(state.undoStack[state.undoStack.length - 1], schedule3D);   // arrives after the preview ran
    }
}

function undoAction() {
    if (state.undoStack.length > 1) {
        state.redoStack.push(state.undoStack.pop());
        applyHistoryEntry(state.undoStack[state.undoStack.length - 1]);
    }
}

function redoAction() {
    if (state.redoStack.length > 0) {
        const entry = state.redoStack.pop();
        state.undoStack.push(entry);
        applyHistoryEntry(entry);
    }
}

// Ctrl/Cmd+Z undo, Ctrl/Cmd+Y or Ctrl/Cmd+Shift+Z redo (not while typing,
// and not while the Sleeve/Logo Designer is open, which has its own items).
document.addEventListener('keydown', e => {
    if (!(e.ctrlKey || e.metaKey) || e.altKey) return;
    if (/INPUT|TEXTAREA|SELECT/.test(e.target.tagName) || e.target.isContentEditable) return;
    if (!document.getElementById('sleeveEditor').hidden) return;
    const k = e.key.toLowerCase();
    if (k === 'z' && !e.shiftKey) { e.preventDefault(); undoAction(); }
    else if (k === 'y' || (k === 'z' && e.shiftKey)) { e.preventDefault(); redoAction(); }
});

function clearCanvas() {
    if (!confirm('Clear all drawing and elements?')) return;
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    elementsLayer.innerHTML = '';
    state.elements = [];
    state.selectedElement = null;
    saveCanvasState();
    updateElementCount();
    updatePreview();
}

// ===== Zoom =====
function zoomIn() {
    state.zoom = Math.min(state.zoom + 0.1, 2);
    applyMockupScale();
}

function zoomOut() {
    state.zoom = Math.max(state.zoom - 0.1, 0.5);
    applyMockupScale();
}

function resetZoom() {
    state.zoom = 1;
    applyMockupScale();
}

// The mockup is laid out at 400x500 (every position is in that space) and
// scaled down to fit narrow screens, times the user's zoom. Negative margins
// give back the space the fit-scaling frees, so no big gap is left around it.
function applyMockupScale() {
    const wrap = document.getElementById('canvasWrapper');
    const cs = getComputedStyle(wrap);
    const avail = wrap.clientWidth - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight);
    const fit = avail > 0 ? Math.min(1, avail / 400) : 1;
    mockupContainer.style.transform = 'scale(' + (fit * state.zoom) + ')';
    mockupContainer.style.margin = (-250 * (1 - fit)) + 'px ' + (-200 * (1 - fit)) + 'px';
}
window.addEventListener('resize', applyMockupScale);

// Size of one mockup pixel on screen (fit-to-screen times zoom).
function mockupScale() {
    return mockupContainer.getBoundingClientRect().width / 400 || 1;
}

// ===== Live Preview =====
function updatePreview() {
    // Every design change comes through here: recount colours, save the draft.
    scheduleColorCount();
    scheduleDraftSave();
    const heading = document.getElementById('previewHeading');
    const single = document.getElementById('singlePreviewWrap');
    const couple = document.getElementById('couplePreview');

    if (isCouple(state.apparelType)) {
        // Couple Wear: show both partners side by side.
        if (single) single.style.display = 'none';
        if (couple) couple.style.display = 'block';
        if (heading) heading.innerHTML = '<i class="fas fa-heart me-1"></i> Couple Preview';
        updateCouplePreview();
        calculatePrice();
        sync3D();
        return;
    }

    if (single) single.style.display = '';
    if (couple) couple.style.display = 'none';
    if (heading) heading.innerHTML = '<i class="fas fa-cube me-1"></i> Live Preview';

    // The flat front/back cards are hidden while the 3D model shows; skip
    // redrawing them on every brush stroke.
    if (sync3D()) {
        calculatePrice();
        return;
    }

    const frontCanvas = document.getElementById('previewCanvasFront');
    const backCanvas = document.getElementById('previewCanvasBack');
    const frontCtx = frontCanvas.getContext('2d');
    const backCtx = backCanvas.getContext('2d');

    frontCanvas.width = 400;
    frontCanvas.height = 500;
    backCanvas.width = 400;
    backCanvas.height = 500;

    // Draw front side
    drawPreviewSide(frontCtx, 'front');
    // Draw back side
    drawPreviewSide(backCtx, 'back');

    // Update price when preview updates
    calculatePrice();
}

// Renders both partners' front composites into the couple preview tiles.
function updateCouplePreview() {
    saveCurrentSideData();
    const curBundle = snapshotBundle();
    const bundleA = state.currentPartner === 'A' ? curBundle : (state.partners.A || {});
    const bundleB = state.currentPartner === 'B' ? curBundle : (state.partners.B || {});
    const ca = document.getElementById('couplePreviewA');
    const cb = document.getElementById('couplePreviewB');
    if (ca) renderGarmentToContext(ca.getContext('2d'), 0, 0, ca.width, ca.height, bundleA, 'front', previewBg(), 'A');
    if (cb) renderGarmentToContext(cb.getContext('2d'), 0, 0, cb.width, cb.height, bundleB, 'front', previewBg(), 'B');
}

// Draws a full garment composite (shape + saved drawing + saved elements) for the
// given bundle/side into a target context at (ox,oy) scaled to w×h. Returns a
// Promise that resolves once all async image draws complete (used by submit).
function renderGarmentToContext(pCtx, ox, oy, w, h, bundle, side, bg, partner) {
    return new Promise(resolve => {
        const sc = w / 400;
        pCtx.clearRect(ox, oy, w, h);
        // Exports omit `bg` and keep the fixed light backdrop; on-screen callers
        // pass previewBg() so the preview follows the site theme.
        pCtx.fillStyle = bg || '#f8f9fa';
        pCtx.fillRect(ox, oy, w, h);

        const svgStr = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 500">${mockupInnerFor(state.apparelType, side, state.apparelColor)}</svg>`;
        const url = URL.createObjectURL(new Blob([svgStr], { type: 'image/svg+xml;charset=utf-8' }));
        const img = new Image();
        img.onload = function() {
            pCtx.drawImage(img, ox, oy, w, h);
            URL.revokeObjectURL(url);

            const area = areaOf(state.apparelType);
            const data = side === 'front' ? bundle.frontCanvasData : bundle.backCanvasData;
            const els = (side === 'front' ? bundle.frontElements : bundle.backElements) || [];
            const drawEls = () => {
                pCtx.save();
                pCtx.translate(ox, oy);
                pCtx.scale(sc, sc);
                drawSleevesOn(pCtx, side, partner);
                renderSavedElementsToCanvas(pCtx, els);
                pCtx.restore();
                resolve();
            };
            if (data) {
                const di = new Image();
                di.onload = function() {
                    pCtx.drawImage(di, ox + area.x * sc, oy + area.y * sc, area.w * sc, area.h * sc);
                    drawEls();
                };
                di.onerror = drawEls;
                di.src = data;
            } else {
                drawEls();
            }
        };
        img.onerror = () => resolve();
        img.src = url;
    });
}

// Backdrop for ON-SCREEN preview canvases only. Follows the site theme so the
// (often white) garment stays visible in dark mode. Exported/submitted images
// keep their fixed light backdrop — admins and print files expect that.
function previewBg() {
    return document.documentElement.classList.contains('dark-mode') ? '#26262c' : '#f0f0f0';
}

// Repaint the previews whenever the site theme is toggled, so the backdrop
// switches immediately without needing to touch the canvas first.
new MutationObserver(() => {
    try {
        updatePreview();
        if (isCouple(state.apparelType)) updateCouplePreview();
    } catch (e) {}
}).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

function drawPreviewSide(pCtx, side) {
    pCtx.clearRect(0, 0, 400, 500);
    pCtx.fillStyle = previewBg();
    pCtx.fillRect(0, 0, 400, 500);

    const svgStr = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 500">${mockupInnerFor(state.apparelType, side, state.apparelColor)}</svg>`;
    const svgBlob = new Blob([svgStr], { type: 'image/svg+xml;charset=utf-8' });
    const svgUrl = URL.createObjectURL(svgBlob);
    const svgImg = new Image();

    svgImg.onload = function() {
        pCtx.drawImage(svgImg, 0, 0, 400, 500);
        URL.revokeObjectURL(svgUrl);
        drawSleevesOn(pCtx, side, state.currentPartner);

        const area = designAreas[state.apparelType];

        if (side === state.currentSide) {
            // Active side: draw directly from the live canvas and elements
            pCtx.drawImage(canvas, area.x, area.y, area.w, area.h);
            renderElementsToCanvas(pCtx);
            drawLogoOn(pCtx, side);
        } else {
            // Inactive side: draw from saved data
            const savedData = side === 'front' ? state.frontCanvasData : state.backCanvasData;
            const savedElements = side === 'front' ? state.frontElements : state.backElements;

            if (savedData) {
                const savedImg = new Image();
                savedImg.onload = function() {
                    pCtx.drawImage(savedImg, area.x, area.y, area.w, area.h);
                    // Render saved elements
                    if (savedElements && savedElements.length > 0) {
                        renderSavedElementsToCanvas(pCtx, savedElements);
                    }
                    drawLogoOn(pCtx, side);
                };
                savedImg.src = savedData;
            } else {
                if (savedElements && savedElements.length > 0) {
                    renderSavedElementsToCanvas(pCtx, savedElements);
                }
                drawLogoOn(pCtx, side);
            }
        }
    };
    svgImg.src = svgUrl;
}

// Draws one design element (text or image) onto a canvas context, honoring its
// position and rotation. Shared by the live-canvas and per-side exporters so the
// submitted order matches exactly what the user sees.
function drawDesignElement(targetCtx, el) {
    // The content sits inside the element's 2px border + 2px padding.
    const left = (parseInt(el.style.left) || 0) + 4;
    const top = (parseInt(el.style.top) || 0) + 4;
    const rot = parseFloat(el.dataset.rotation) || 0;

    if (el.dataset.type === 'text') {
        const span = el.querySelector('span');
        if (!span) return;
        const fs = parseInt(span.style.fontSize) || 24;
        targetCtx.font = span.style.fontStyle + ' ' + span.style.fontWeight + ' ' + span.style.fontSize + ' ' + span.style.fontFamily;
        targetCtx.fillStyle = span.style.color;
        if (rot) {
            const tw = targetCtx.measureText(span.textContent).width;
            const ccx = left + tw / 2, ccy = top + fs / 2;
            targetCtx.save();
            targetCtx.translate(ccx, ccy);
            targetCtx.rotate(rot * Math.PI / 180);
            targetCtx.fillText(span.textContent, -tw / 2, fs / 2);
            targetCtx.restore();
        } else {
            targetCtx.fillText(span.textContent, left, top + fs);
        }
    } else if (el.dataset.type === 'image') {
        const img = el.querySelector('img');
        if (!img) return;
        if (img.complete && img.naturalWidth > 0) {
            const w = parseInt(img.style.width);
            const h = parseInt(img.style.height);
            if (rot) {
                const ccx = left + w / 2, ccy = top + h / 2;
                targetCtx.save();
                targetCtx.translate(ccx, ccy);
                targetCtx.rotate(rot * Math.PI / 180);
                targetCtx.drawImage(img, -w / 2, -h / 2, w, h);
                targetCtx.restore();
            } else {
                targetCtx.drawImage(img, left, top, w, h);
            }
        } else {
            // Image not loaded yet — re-run preview once it loads.
            img.addEventListener('load', () => updatePreview(), { once: true });
        }
    }
}

function renderElementsToCanvas(targetCtx) {
    state.elements.forEach(el => drawDesignElement(targetCtx, el));
}

function renderSavedElementsToCanvas(targetCtx, savedElements) {
    savedElements.forEach(el => drawDesignElement(targetCtx, el));
}

function updateElementCount() {
    document.getElementById('infoElements').textContent = state.elements.length;
    renderLayers();
}

// ===== Layers panel =====
function renderLayers() {
    const list = document.getElementById('layersList');
    if (!list) return;
    if (state.elements.length === 0) {
        list.innerHTML = '<div class="layers-empty">No elements yet. Add text, an image, a stamp, or generate with AI.</div>';
        return;
    }
    let html = '';
    // Top of the stack (last in array) shown first.
    for (let i = state.elements.length - 1; i >= 0; i--) {
        const el = state.elements[i];
        let label, thumb;
        if (el.dataset.type === 'text') {
            const span = el.querySelector('span');
            label = span ? span.textContent : 'Text';
            thumb = '<i class="fas fa-font"></i>';
        } else {
            const img = el.querySelector('img');
            label = el.dataset.stamp
                ? (el.dataset.stamp.charAt(0).toUpperCase() + el.dataset.stamp.slice(1) + ' stamp')
                : (el.dataset.label ? el.dataset.label + ' (text art)' : 'Image');
            thumb = img ? '<img src="' + img.src + '" alt="">' : '<i class="fas fa-image"></i>';
        }
        const sel = (el === state.selectedElement) ? ' selected' : '';
        html += '<div class="layer-row' + sel + '" data-index="' + i + '">'
            +   '<span class="layer-thumb">' + thumb + '</span>'
            +   '<span class="layer-label">' + escapeHtml(label) + '</span>'
            +   '<span class="layer-actions">'
            +     '<button type="button" title="Bring forward" onclick="moveLayer(' + i + ', 1)"><i class="fas fa-chevron-up"></i></button>'
            +     '<button type="button" title="Send back" onclick="moveLayer(' + i + ', -1)"><i class="fas fa-chevron-down"></i></button>'
            +     (el.dataset.type === 'image' && !el.dataset.stamp && !el.dataset.label
                     ? '<button type="button" title="Remove white background" onclick="removeBgFromLayer(' + i + ')"><i class="fas fa-wand-magic-sparkles"></i></button>' : '')
            +     '<button type="button" title="Delete" onclick="deleteLayer(' + i + ')"><i class="fas fa-trash"></i></button>'
            +   '</span>'
            + '</div>';
    }
    list.innerHTML = html;
    list.querySelectorAll('.layer-row').forEach(row => {
        row.addEventListener('click', (e) => {
            if (e.target.closest('button')) return;
            selectLayer(parseInt(row.dataset.index));
        });
    });
}

function selectLayer(i) {
    const el = state.elements[i];
    if (!el) return;
    document.querySelectorAll('.draggable-element').forEach(d => d.classList.remove('selected'));
    el.classList.add('selected');
    state.selectedElement = el;
    renderLayers();
}

function deleteLayer(i) {
    const el = state.elements[i];
    if (!el) return;
    el.remove();
    state.elements.splice(i, 1);
    if (state.selectedElement === el) state.selectedElement = null;
    updateElementCount();
    saveCanvasState();
    updatePreview();
}

// Reorders z-stacking. Re-appends the DOM in array order so the visible stack
// matches the exporter (which draws state.elements in order).
function moveLayer(i, dir) {
    const ni = i + dir;
    if (ni < 0 || ni >= state.elements.length) return;
    const tmp = state.elements[i];
    state.elements[i] = state.elements[ni];
    state.elements[ni] = tmp;
    state.elements.forEach(el => elementsLayer.appendChild(el));
    renderLayers();
    saveCanvasState();
    updatePreview();
}

// ===== Submit Design =====
function submitDesign() {
    const btn = document.getElementById('submitDesignBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
    
    // Save current side data first
    saveCurrentSideData();
    
    // Generate front image
    generateSideImage('front', function(frontImageData) {
        // Generate back image
        generateSideImage('back', function(backImageData) {
        // ...then the print-ready artwork for each side: the design on its own,
        // on transparency, which is the file production actually needs.
        generatePrintArtwork('front', function(printFrontData) {
        generatePrintArtwork('back', function(printBackData) {
            const notes = document.getElementById('designNotes').value.trim();
            
            const formData = new FormData();
            formData.append('action', 'save');
            formData.append('product_type', state.apparelType);
            formData.append('design_image', frontImageData);
            formData.append('design_image_back', backImageData);
            formData.append('print_front', printFrontData);
            formData.append('print_back', printBackData);
            formData.append('sleeve_left', sleevePrintData('left'));
            formData.append('sleeve_right', sleevePrintData('right'));
            formData.append('logo_print', logoPrintData());
            formData.append('notes', notes);
            const priceData = {
                apparelColor: state.apparelColor,
                apparelType: state.apparelType,
                // Both sides (saveCurrentSideData ran above, so the active side is in its array).
                elementsCount: state.frontElements.length + state.backElements.length,
                printSize: document.getElementById('printSizeSelect')?.value || 'medium',
                // The server recounts colours from the print files on save and
                // overwrites this; it is kept for reference only.
                colorsUsed: state.colorsUsed || 1,
                size: document.getElementById('sizeSelect')?.value || 'M',
                quantity: parseInt(document.getElementById('quantityInput')?.value) || 1,
                discountType: document.getElementById('discountSelect')?.value || 'regular',
                baseCost: pricing.base[state.apparelType] || 350,
                printSizeCost: pricing.printSize[document.getElementById('printSizeSelect')?.value || 'medium'] || 100,
                colorCost: Math.max(0, ((state.colorsUsed || 1) - 1)) * pricing.colorCost,
                extraPrintCost: extraPrints().cost
            };

            formData.append('design_data', JSON.stringify(priceData));
            // So the design can be reopened with "Edit" in My Designs.
            formData.append('editor_state', JSON.stringify(snapshotDesign()));
            
            fetch('includes/custom-design-ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showToast('Design submitted! Redirecting to order summary...', 'success');
                    draftReady = false;   // saved for real now; stop keeping a draft
                    clearDraft();
                    const params = new URLSearchParams({
                        design_id: data.design_id,
                        type: priceData.apparelType,
                        color: priceData.apparelColor,
                        size: priceData.size,
                        qty: priceData.quantity,
                        print_size: priceData.printSize,
                        discount: priceData.discountType
                    });
                    setTimeout(() => {
                        window.location.href = 'custom-order-summary.php?' + params.toString();
                    }, 1000);
                } else {
                    showToast(data.message || 'Failed to submit design.', 'error');
                }
            })
            .catch(err => {
                console.error(err);
                showToast('Network error. Please try again.', 'error');
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit & Proceed to Order';
            });
        });
        });
        });
    });
}

function generateSideImage(side, callback) {
    // Couple Wear: export both partners side by side into one image.
    if (isCouple(state.apparelType)) {
        generateCoupleSideImage(side, callback);
        return;
    }

    const finalCanvas = document.createElement('canvas');
    finalCanvas.width = 400;
    finalCanvas.height = 500;
    const fctx = finalCanvas.getContext('2d');
    
    fctx.fillStyle = '#f0f0f0';
    fctx.fillRect(0, 0, 400, 500);
    
    const svgStr = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 500">${mockupInnerFor(state.apparelType, side, state.apparelColor)}</svg>`;
    const svgBlob = new Blob([svgStr], { type: 'image/svg+xml;charset=utf-8' });
    const svgUrl = URL.createObjectURL(svgBlob);
    const svgImg = new Image();
    
    svgImg.onload = function() {
        fctx.drawImage(svgImg, 0, 0, 400, 500);
        URL.revokeObjectURL(svgUrl);
        drawSleevesOn(fctx, side, state.currentPartner);

        const area = designAreas[state.apparelType];
        
        if (side === state.currentSide) {
            // Active side: use live canvas
            fctx.drawImage(canvas, area.x, area.y, area.w, area.h);
            renderElementsToCanvas(fctx);
            drawLogoOn(fctx, side);
            callback(finalCanvas.toDataURL('image/png'));
        } else {
            // Saved side: restore from saved data
            const savedData = side === 'front' ? state.frontCanvasData : state.backCanvasData;
            const savedElements = side === 'front' ? state.frontElements : state.backElements;

            if (savedData) {
                const savedImg = new Image();
                savedImg.onload = function() {
                    fctx.drawImage(savedImg, area.x, area.y, area.w, area.h);
                    if (savedElements && savedElements.length > 0) {
                        renderSavedElementsToCanvas(fctx, savedElements);
                    }
                    drawLogoOn(fctx, side);
                    callback(finalCanvas.toDataURL('image/png'));
                };
                savedImg.src = savedData;
            } else {
                if (savedElements && savedElements.length > 0) {
                    renderSavedElementsToCanvas(fctx, savedElements);
                }
                drawLogoOn(fctx, side);
                callback(finalCanvas.toDataURL('image/png'));
            }
        }
    };
    svgImg.src = svgUrl;
}

/**
 * Scale factor for the print file.
 *
 * The freehand drawing layer is a raster the size of the design area (about
 * 140x180), so it can only be upscaled. Text and images are re-drawn from
 * source at this scale, which is what actually matters: AI artwork and
 * uploaded logos come out crisp rather than blocky.
 */
const PRINT_SCALE = 4;

/**
 * Render the ARTWORK ONLY -- no garment, no background -- cropped to the
 * printable area, on transparency.
 *
 * design_image is a mockup: garment plus artwork flattened together. Sending
 * that to a printer would print the drawing of the t-shirt too. This produces
 * the file that actually goes to DTG/screen printing.
 *
 * Elements are positioned in mockup coordinates (the 400x500 space), so the
 * context is translated by the design area's origin to crop to it. Anything
 * outside the printable area falls off the canvas, which is correct.
 */
function generatePrintArtwork(side, callback) {
    printCanvasFor(side, cv => callback(cv ? cv.toDataURL('image/png') : ''));
}

/**
 * The print artwork of one side as a canvas, or null when that side is blank
 * (a blank print file is worse than none: the admin would download an empty
 * PNG and think the artwork was lost). The print file and the colour count
 * both come from this, so they always agree.
 */
function printCanvasFor(side, callback) {
    if (!designAreas[state.apparelType]) { callback(null); return; }
    if (!isCouple(state.apparelType)) {
        renderArtwork(side, PRINT_SCALE, out => callback(printCanvasHasInk(out) ? out : null));
        return;
    }
    // Couple Wear: both partners in one file, A on the left. The partner being
    // edited renders from the live editor, the other from their saved bundle.
    const bundleOf = p => p === state.currentPartner ? undefined : (state.partners[p] || {});
    renderArtwork(side, PRINT_SCALE, function (a) {
        renderArtwork(side, PRINT_SCALE, function (b) {
            if (!printCanvasHasInk(a) && !printCanvasHasInk(b)) { callback(null); return; }
            const gap = 10 * PRINT_SCALE;
            const out = document.createElement('canvas');
            out.width = a.width * 2 + gap;
            out.height = a.height;
            const octx = out.getContext('2d');
            octx.drawImage(a, 0, 0);
            octx.drawImage(b, a.width + gap, 0);
            callback(out);
        }, bundleOf('B'));
    }, bundleOf('A'));
}

/**
 * Colours in the print artwork, for pricing. Counted the SAME way as the
 * server does on save (countPrintColors() in custom-design-ajax.php), over
 * the same print files: every 2nd pixel in each direction, pixels at least
 * half opaque, each channel rounded to 5 levels; a colour counts when it
 * covers at least 1% of the inked pixels. 1 to 10.
 */
function countPrintColors(canvases) {
    const buckets = new Map();
    let total = 0;
    canvases.forEach(cv => {
        let d;
        try { d = cv.getContext('2d').getImageData(0, 0, cv.width, cv.height).data; } catch (e) { return; }
        for (let y = 0; y < cv.height; y += 2) {
            for (let x = 0; x < cv.width; x += 2) {
                const i = (y * cv.width + x) * 4;
                if (d[i + 3] < 128) continue;
                const k = Math.round(d[i] / 64) * 25 + Math.round(d[i + 1] / 64) * 5 + Math.round(d[i + 2] / 64);
                buckets.set(k, (buckets.get(k) || 0) + 1);
                total++;
            }
        }
    });
    if (!total) return 1;
    let n = 0;
    buckets.forEach(c => { if (c >= total * 0.01) n++; });
    return Math.max(1, Math.min(10, n));
}

// Recount after the design settles (rendering the print files is not free).
let colorTimer = 0, colorSeq = 0;
function scheduleColorCount() {
    clearTimeout(colorTimer);
    colorTimer = setTimeout(() => {
        const seq = ++colorSeq;
        printCanvasFor('front', f => printCanvasFor('back', b => {
            if (seq !== colorSeq) return;   // a newer recount is on its way
            state.colorsUsed = countPrintColors([f, b, sleevePrintCanvas('left'), sleevePrintCanvas('right')].filter(Boolean));
            calculatePrice();
        }));
    }, 500);
}

/**
 * The artwork of one side, cropped to the printable area, on transparency,
 * as a canvas at `scale`. Shared by the print file and the 3D preview so the
 * garment on screen shows exactly what gets printed.
 *
 * `bundle` (optional) renders a Couple Wear partner who is not being edited
 * from their saved bundle; without it the live editor state is used.
 */
function renderArtwork(side, scale, callback, bundle) {
    const area = designAreas[state.apparelType];

    const out = document.createElement('canvas');
    out.width  = Math.round(area.w * scale);
    out.height = Math.round(area.h * scale);
    const octx = out.getContext('2d');

    // No fill: the canvas starts transparent, which is what printing needs.
    octx.setTransform(scale, 0, 0, scale, -area.x * scale, -area.y * scale);

    const finish = () => {
        octx.setTransform(1, 0, 0, 1, 0, 0);
        callback(out);
    };

    const live = !bundle && side === state.currentSide;
    const src = bundle || state;

    const drawLayerThen = (next) => {
        const savedData = side === 'front' ? src.frontCanvasData : src.backCanvasData;
        if (live) {
            octx.drawImage(canvas, area.x, area.y, area.w, area.h);
            next();
        } else if (savedData) {
            const img = new Image();
            img.onload  = function () { octx.drawImage(img, area.x, area.y, area.w, area.h); next(); };
            img.onerror = function () { next(); };
            img.src = savedData;
        } else {
            next();
        }
    };

    drawLayerThen(function () {
        const els = live
            ? state.elements
            : (side === 'front' ? src.frontElements : src.backElements);
        if (els && els.length) {
            els.forEach(el => drawDesignElement(octx, el));
        }
        if (!bundle) drawLogoOn(octx, side);   // bundles are Couple Wear, which has no logo zone
        finish();
    });
}

/** True when at least one pixel is not fully transparent. */
function printCanvasHasInk(cv) {
    try {
        const d = cv.getContext('2d').getImageData(0, 0, cv.width, cv.height).data;
        for (let i = 3; i < d.length; i += 4) {
            if (d[i] !== 0) { return true; }
        }
    } catch (e) {
        // Tainted canvas (a cross-origin image): assume there is artwork
        // rather than silently discarding it.
        return true;
    }
    return false;
}

// Builds an 800×500 side-by-side image of Partner A + Partner B for a given side.
function generateCoupleSideImage(side, callback) {
    saveCurrentSideData();
    const curBundle = snapshotBundle();
    const bundleA = state.currentPartner === 'A' ? curBundle : (state.partners.A || {});
    const bundleB = state.currentPartner === 'B' ? curBundle : (state.partners.B || {});

    const fc = document.createElement('canvas');
    fc.width = 800;
    fc.height = 500;
    const fctx = fc.getContext('2d');
    fctx.fillStyle = '#f0f0f0';
    fctx.fillRect(0, 0, 800, 500);

    Promise.all([
        renderGarmentToContext(fctx, 0, 0, 400, 500, bundleA, side, undefined, 'A'),
        renderGarmentToContext(fctx, 400, 0, 400, 500, bundleB, side, undefined, 'B')
    ]).then(() => callback(fc.toDataURL('image/png')));
}

// ===== Load My Designs =====
function loadMyDesigns() {
    fetch('includes/custom-design-ajax.php?action=list')
    .then(r => r.json())
    .then(data => {
        const grid = document.getElementById('myDesignsGrid');
        const noMsg = document.getElementById('noDesignsMsg');

        if (data.success && data.designs.length > 0) {
            if (noMsg) noMsg.style.display = 'none';
            grid.innerHTML = data.designs.map(renderDesignCard).join('');
        } else {
            grid.innerHTML = '';
            if (noMsg) {
                noMsg.style.display = 'block';
                grid.appendChild(noMsg);
            }
        }
    })
    .catch(err => console.error('Failed to load designs:', err));
}

/** Peso formatting that matches the rest of the site. */
function pesos(n) {
    return '₱' + Number(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

const PRINT_SIZE_LABELS = { small: 'Small 4×4"', medium: 'Medium 8×8"', large: 'Large 12×12"', full: 'Full Print' };

/**
 * One saved design.
 *
 * The card carries the configuration the customer actually saved -- colour,
 * size, print size and quantity -- and hands those to the order page. It used
 * to send a fixed white / M / qty 1 / medium, so a black hoodie was re-ordered
 * as a white t-shirt-sized print.
 */
function renderDesignCard(d) {
    const type  = escapeHtml(d.product_type.charAt(0).toUpperCase() + d.product_type.slice(1));
    const st    = String(d.status || '').toLowerCase();

    // Which statuses may still be ordered. 'cancelled' and 'revision' may not:
    // one is dead, the other is waiting on changes.
    const orderable = (st === 'pending' || st === 'approved' || st === 'completed');
    const actionLabel = st === 'completed' ? 'Order again' : 'Order Now';

    const orderUrl = 'custom-order-summary.php'
        + '?design_id=' + encodeURIComponent(d.id)
        + '&type='       + encodeURIComponent(d.product_type)
        + '&color='      + encodeURIComponent(d.apparel_color || '#FFFFFF')
        + '&size='       + encodeURIComponent(d.size || 'M')
        + '&qty='        + encodeURIComponent(d.quantity || 1)
        + '&print_size=' + encodeURIComponent(d.print_size || 'medium')
        + '&discount='   + encodeURIComponent(d.discount_type || 'regular');

    // A missing file is stated plainly instead of showing a stock placeholder
    // that could be mistaken for the customer's own artwork.
    const preview = d.image_missing
        ? `<div class="design-card-img design-card-missing">
               <i class="fas fa-triangle-exclamation"></i>
               <span>Preview unavailable</span>
           </div>`
        : `<img src="${escapeHtml(d.design_image)}" class="design-card-img" alt="${type} design"
                onerror="this.outerHTML='&lt;div class=\\'design-card-img design-card-missing\\'&gt;&lt;i class=\\'fas fa-triangle-exclamation\\'&gt;&lt;/i&gt;&lt;span&gt;Preview unavailable&lt;/span&gt;&lt;/div&gt;'">`;

    const swatch = `<span class="design-swatch" style="background:${escapeHtml(d.apparel_color || '#FFFFFF')}"></span>`;

    return `
        <div class="design-card">
            ${preview}
            <div class="design-card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <h6>${type}</h6>
                    <span class="design-status-badge ${escapeHtml(st)}">${escapeHtml(st)}</span>
                </div>

                <div class="design-spec">
                    ${swatch}
                    <span>Size ${escapeHtml(d.size || 'M')}</span>
                    <span>&middot;</span>
                    <span>${escapeHtml(PRINT_SIZE_LABELS[d.print_size] || 'Medium')}</span>
                    <span>&middot;</span>
                    <span>Qty ${parseInt(d.quantity, 10) || 1}</span>
                </div>

                ${d.notes ? `<p class="design-note">${escapeHtml(d.notes.substring(0, 60))}${d.notes.length > 60 ? '...' : ''}</p>` : ''}

                <div class="design-price-row">
                    <span class="design-price-label">
                        Estimated${(d.discount_type && d.discount_type !== 'regular') ? ` <em>(${escapeHtml(d.discount_type.toUpperCase())} -20%)</em>` : ''}
                    </span>
                    <span class="design-price">${pesos(d.price_total)}</span>
                </div>
                <small class="design-date">Saved ${new Date(String(d.created_at).replace(' ', 'T')).toLocaleDateString()}</small>

                ${orderable
                    ? `<a href="${orderUrl}" class="btn btn-sm w-100 mt-2 design-order-btn">
                           <i class="fas fa-shopping-cart"></i> ${actionLabel}
                       </a>`
                    : `<div class="design-blocked mt-2">${st === 'cancelled'
                            ? 'This design was cancelled and can no longer be ordered.'
                            : 'Waiting for changes before this can be ordered.'}</div>`}
                ${d.editable
                    ? `<a href="custom-design.php?edit=${encodeURIComponent(d.id)}" class="btn btn-sm w-100 mt-2 design-edit-btn"
                          onclick="return confirmReplaceDesign()">
                           <i class="fas fa-pen"></i> Edit design
                       </a>`
                    : ''}
            </div>
        </div>
    `;
}

// ===== Utilities =====
function escapeHtml(str) {
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
}

// ===== 3D Preview Rotation =====
let rotation3D = { y: 0, spinning: true, dragging: false, lastX: 0 };

function init3DPreview() {
    const container = document.getElementById('preview3dContainer');
    const inner = document.getElementById('preview3dInner');

    if (!container || !inner) return;

    // Start spinning
    inner.classList.add('spinning');

    // Mouse drag rotation
    container.addEventListener('mousedown', function(e) {
        rotation3D.dragging = true;
        rotation3D.lastX = e.clientX;
        inner.classList.remove('spinning');
        rotation3D.spinning = false;
        document.getElementById('spinBtn').classList.remove('active');
        e.preventDefault();
    });

    document.addEventListener('mousemove', function(e) {
        if (!rotation3D.dragging) return;
        const dx = e.clientX - rotation3D.lastX;
        rotation3D.y += dx * 0.8;
        rotation3D.lastX = e.clientX;
        inner.style.transform = `rotateY(${rotation3D.y}deg)`;
    });

    document.addEventListener('mouseup', function() {
        rotation3D.dragging = false;
    });

    // Touch drag rotation
    container.addEventListener('touchstart', function(e) {
        rotation3D.dragging = true;
        rotation3D.lastX = e.touches[0].clientX;
        inner.classList.remove('spinning');
        rotation3D.spinning = false;
        document.getElementById('spinBtn').classList.remove('active');
    }, { passive: true });

    document.addEventListener('touchmove', function(e) {
        if (!rotation3D.dragging) return;
        const dx = e.touches[0].clientX - rotation3D.lastX;
        rotation3D.y += dx * 0.8;
        rotation3D.lastX = e.touches[0].clientX;
        inner.style.transform = `rotateY(${rotation3D.y}deg)`;
    }, { passive: true });

    document.addEventListener('touchend', function() {
        rotation3D.dragging = false;
    });
}

function toggle3DSpin() {
    const inner = document.getElementById('preview3dInner');
    const btn = document.getElementById('spinBtn');
    rotation3D.spinning = !rotation3D.spinning;

    if (rotation3D.spinning) {
        inner.classList.add('spinning');
        inner.style.transform = '';
        btn.classList.add('active');
    } else {
        inner.classList.remove('spinning');
        const computedStyle = getComputedStyle(inner);
        const matrix = computedStyle.transform;
        inner.style.transform = matrix;
        btn.classList.remove('active');
    }
}

function rotate3DLeft() {
    const inner = document.getElementById('preview3dInner');
    inner.classList.remove('spinning');
    rotation3D.spinning = false;
    document.getElementById('spinBtn').classList.remove('active');
    rotation3D.y -= 45;
    inner.style.transition = 'transform 0.4s ease';
    inner.style.transform = `rotateY(${rotation3D.y}deg)`;
    setTimeout(() => { inner.style.transition = 'transform 0.1s ease-out'; }, 400);
}

function rotate3DRight() {
    const inner = document.getElementById('preview3dInner');
    inner.classList.remove('spinning');
    rotation3D.spinning = false;
    document.getElementById('spinBtn').classList.remove('active');
    rotation3D.y += 45;
    inner.style.transition = 'transform 0.4s ease';
    inner.style.transform = `rotateY(${rotation3D.y}deg)`;
    setTimeout(() => { inner.style.transition = 'transform 0.1s ease-out'; }, 400);
}

// Flips the preview card 180° to reveal the back (and back again).
function flipPreview() {
    const inner = document.getElementById('preview3dInner');
    inner.classList.remove('spinning');
    rotation3D.spinning = false;
    document.getElementById('spinBtn')?.classList.remove('active');
    const flipped = inner.dataset.flipped === '1';
    rotation3D.y = flipped ? 0 : 180;
    inner.dataset.flipped = flipped ? '0' : '1';
    inner.style.transition = 'transform 0.5s ease';
    inner.style.transform = `rotateY(${rotation3D.y}deg)`;
    setTimeout(() => { inner.style.transition = 'transform 0.1s ease-out'; }, 500);
}

// ===== Real 3D garment (js/design-3d.js) =====
// The 2D canvas stays the editor; the 3D model mirrors it live. Types with no
// model (model3d: null) and browsers without WebGL keep the flat preview.
function use3D() {
    const model = configOf(state.apparelType).model3d;
    return !!(window.Design3D && model && Design3D.canShow(model.src));
}

// Shows the 3D model or the flat previews in the side panel. Returns true when
// the side panel shows 3D. In main-canvas 3D mode the side panel keeps the
// flat previews, and the model (in the canvas) is still kept up to date.
function sync3D() {
    const on = use3D();
    const side = on && !state.view3d;
    document.getElementById('garment3dWrap').style.display = side ? '' : 'none';
    if (side) {
        document.getElementById('singlePreviewWrap').style.display = 'none';
        document.getElementById('couplePreview').style.display = 'none';
    }
    if (on) schedule3D();
    return side;
}

// Brush strokes call updatePreview on every mouse move; repaint 3D at most once a frame.
let pending3D = false;
function schedule3D() {
    if (pending3D || !use3D()) return;
    pending3D = true;
    requestAnimationFrame(() => { pending3D = false; update3D(); });
}

let seq3D = 0;
function update3D() {
    if (!use3D()) return;
    const type = state.apparelType;
    const area = areaOf(type);
    const couple = isCouple(type);
    const token = ++seq3D;
    Design3D.show(configOf(type).model3d, state.apparelColor, couple ? 2 : 1, area.h / area.w);

    const slots = couple ? ['A', 'B'] : [null];
    slots.forEach((p, slot) => {
        // The partner being edited renders from the live editor; the other from their saved bundle.
        const bundle = p && p !== state.currentPartner ? (state.partners[p] || {}) : undefined;
        ['front', 'back'].forEach(side => {
            // Mid-stroke only the side under the brush changes; skip re-decoding the rest.
            if (state.isDrawing && (bundle || side !== state.currentSide)) return;
            renderArtwork(side, 3, cv => {
                if (token === seq3D) Design3D.setArtwork(slot, side, cv);
            }, bundle);
        });
        // Sleeves are not touched by the brush.
        if (!state.isDrawing) {
            SLEEVE_SIDES.forEach(which => Design3D.setArtwork(slot, which, renderSleeve(state.sleeves[p || 'A'][which], 1)));
        }
    });
}

// The module loads async, before or after init(); if init() already ran,
// switch to 3D now (otherwise init()'s own updatePreview does it).
let designToolReady = false;
window.addEventListener('design3d-ready', () => {
    if (!designToolReady) return;
    refreshViewToggle();
    updatePreview();
    // Opens facing front and spinning; only turn it if the back is being edited.
    if (use3D() && state.currentSide === 'back') Design3D.face('back');
});

// ===== Price Auto Calculation =====
// Base prices come from the data-driven apparel config so new types priced there
// flow through automatically (couple sets ₱1,500; corporate ₱950/₱1,050/₱1,100).
// Print-size and extra-print prices come from includes/apparel-config.php, the
// same numbers the server charges with (customDesignPrice()).
const pricing = {
    base: {},
    printSize: <?php echo json_encode(getPrintSizePrices()); ?>,
    extra: <?php echo json_encode(getExtraPrintPrices()); ?>,   // { sleeve, logo }
    colorCost: 25 // per color used
};
Object.keys(APPAREL_CONFIG).forEach(t => { pricing.base[t] = APPAREL_CONFIG[t].base; });

// Printed sleeves (a sleeve counts once, even on both Couple Wear partners)
// and the logo. The server counts the same way, from the print files it saved.
function extraPrints() {
    const sleeves = hasSleeves()
        ? SLEEVE_SIDES.filter(w => ['A', 'B'].some(p => state.sleeves[p][w].length)).length : 0;
    const logo = hasLogo() && state.logo.length > 0;
    return { sleeves, logo, cost: sleeves * pricing.extra.sleeve + (logo ? pricing.extra.logo : 0) };
}

function calculatePrice() {
    const baseCost = pricing.base[state.apparelType] || 350;
    const printSize = document.getElementById('printSizeSelect')?.value || 'medium';
    const printSizeCost = pricing.printSize[printSize] || 100;
    const colorsUsed = state.colorsUsed || 1;   // see scheduleColorCount()
    const colorCost = Math.max(0, (colorsUsed - 1)) * pricing.colorCost; // first color free
    const extras = extraPrints();

    document.getElementById('priceBase').textContent = '₱' + baseCost.toLocaleString();
    document.getElementById('pricePrintSize').textContent = '₱' + printSizeCost.toLocaleString();
    document.getElementById('priceColorsCount').textContent = colorsUsed;
    document.getElementById('priceColors').textContent = colorsUsed > 1 ? '₱' + colorCost.toLocaleString() : 'Free';
    document.getElementById('priceSleeveRow').style.display = extras.sleeves ? '' : 'none';
    document.getElementById('priceSleeveCount').textContent = extras.sleeves;
    document.getElementById('priceSleeves').textContent = '₱' + (extras.sleeves * pricing.extra.sleeve).toLocaleString();
    document.getElementById('priceLogoRow').style.display = extras.logo ? '' : 'none';
    document.getElementById('priceLogo').textContent = '₱' + pricing.extra.logo.toLocaleString();
    // Quantity
    const qty = parseInt(document.getElementById('quantityInput')?.value) || 1;
    let subtotal = (baseCost + printSizeCost + colorCost + extras.cost) * qty;

    // Discount
    const discountType = document.getElementById('discountSelect')?.value || 'regular';
    let discountPercent = 0;
    if (discountType === 'senior' || discountType === 'pwd') discountPercent = 0.20;
    const discountAmount = subtotal * discountPercent;
    const finalTotal = subtotal - discountAmount;

    if (discountPercent > 0) {
        document.getElementById('discountRow').style.display = 'block';
        document.getElementById('priceDiscount').textContent = '-₱' + discountAmount.toLocaleString();
    } else {
        document.getElementById('discountRow').style.display = 'none';
    }

    document.getElementById('priceTotal').textContent = '₱' + finalTotal.toLocaleString();
    scheduleDraftSave();   // quantity, size and print size are part of the draft
}

function adjustQty(delta) {
    const input = document.getElementById('quantityInput');
    let val = parseInt(input.value) || 1;
    val = Math.max(1, Math.min(100, val + delta));
    input.value = val;
    calculatePrice();
}

// ===== Remove white background =====
// Near-white pixels connected to the image border become transparent, so the
// white box around a logo or an AI artwork disappears while white INSIDE the
// art (eyes, highlights) stays. Pixels at the edge of the removed area that
// are almost white fade out, which keeps the outline smooth.
function whiteToTransparent(img, maxSide) {
    const k = Math.min(1, maxSide / Math.max(img.naturalWidth, img.naturalHeight));
    const w = Math.max(1, Math.round(img.naturalWidth * k));
    const h = Math.max(1, Math.round(img.naturalHeight * k));
    const cv = document.createElement('canvas');
    cv.width = w;
    cv.height = h;
    const c = cv.getContext('2d');
    c.drawImage(img, 0, 0, w, h);
    const im = c.getImageData(0, 0, w, h), d = im.data;
    const HARD = 235, SOFT = 200;   // min(R,G,B) at/above HARD is background; SOFT..HARD fades
    const minCh = i => Math.min(d[i], d[i + 1], d[i + 2]);
    const bg = new Uint8Array(w * h);
    const stack = [];
    const visit = p => {
        if (bg[p]) return;
        const i = p * 4;
        if (d[i + 3] < 16 || minCh(i) >= HARD) { bg[p] = 1; stack.push(p); }
    };
    for (let x = 0; x < w; x++) { visit(x); visit((h - 1) * w + x); }
    for (let y = 0; y < h; y++) { visit(y * w); visit(y * w + w - 1); }
    while (stack.length) {
        const p = stack.pop(), x = p % w;
        if (x > 0) visit(p - 1);
        if (x < w - 1) visit(p + 1);
        if (p >= w) visit(p - w);
        if (p < w * (h - 1)) visit(p + w);
    }
    let removed = 0;
    for (let p = 0; p < w * h; p++) {
        const i = p * 4;
        if (bg[p]) { d[i + 3] = 0; removed++; continue; }
        const x = p % w;
        const edge = (x > 0 && bg[p - 1]) || (x < w - 1 && bg[p + 1]) || (p >= w && bg[p - w]) || (p < w * (h - 1) && bg[p + w]);
        const m = minCh(i);
        if (edge && m > SOFT) d[i + 3] = Math.round(d[i + 3] * (HARD - m) / (HARD - SOFT));
    }
    c.putImageData(im, 0, 0);
    return { canvas: cv, removed: removed / (w * h) };
}

// Store a canvas as a design asset (same validated upload as a user image),
// so saved designs and drafts refer to a short URL, not megabytes of data.
function uploadCanvasAsset(cv) {
    return new Promise((resolve, reject) => {
        cv.toBlob(blob => {
            if (!blob) { reject(new Error('encode')); return; }
            const fd = new FormData();
            fd.append('action', 'upload_asset');
            fd.append('asset', blob, 'artwork.png');
            fetch('includes/custom-design-ajax.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => data.success ? resolve(data.url) : reject(new Error(data.message)))
                .catch(reject);
        }, 'image/png');
    });
}

/**
 * A copy of the image at `src` with its white background removed: resolves to
 * its URL, or to null when there was no white background to remove.
 */
function removeWhiteBackground(src) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => {
            let r;
            try { r = whiteToTransparent(img, 1024); } catch (e) { reject(e); return; }
            if (r.removed < 0.005) { resolve(null); return; }
            // If the upload fails the design still works, from the data itself.
            uploadCanvasAsset(r.canvas).then(resolve, () => resolve(r.canvas.toDataURL('image/png')));
        };
        img.onerror = () => reject(new Error('load'));
        img.src = src;
    });
}

// Layers panel button on an image.
function removeBgFromLayer(i) {
    const el = state.elements[i];
    const img = el && el.querySelector('img');
    if (!img) return;
    showToast('Removing the white background…', 'info');
    removeWhiteBackground(img.getAttribute('src'))
        .then(url => {
            if (!url) { showToast('No white background found on this image.', 'info'); return; }
            img.src = url;
            saveCanvasState();
            updatePreview();
            renderLayers();
            showToast('Background removed!', 'success');
        })
        .catch(() => showToast('Could not process this image.', 'error'));
}

// Sleeve / Logo Designer button on the selected image.
function removeBgFromSleeveSel() {
    const it = sleeveEd.sel;
    if (!it || it.kind !== 'img' || !it.src) return;
    const btn = document.getElementById('sleeveBgBtn');
    btn.disabled = true;
    removeWhiteBackground(it.src)
        .then(url => {
            if (!url) { showToast('No white background found on this image.', 'info'); return; }
            it.src = url;
            it.img = sleeveImage(url);
            sleeveChanged();
            showToast('Background removed!', 'success');
        })
        .catch(() => showToast('Could not process this image.', 'error'))
        .finally(() => { btn.disabled = false; });
}

// ===== Design templates =====
// Each template builds a design snapshot (the same format as drafts and saved
// designs) from a few fields the customer fills in, and loadDesign() puts it
// on the canvas. Text is drawn as text art, so it prints sharp.
function tplArt(text, o, cx, cy, w, maxH) {
    const cv = renderTextArt(Object.assign({ text, family: 'Arial', size: 28, bold: true, italic: false,
        color: '#000000', curve: 0, outline: false, outlineColor: '#FFFFFF', shadow: false }, o));
    if (maxH) w = Math.min(w, maxH * cv.width / cv.height);   // short words would otherwise grow too tall
    const h = cv.height * w / cv.width;
    // Positions are of the element box: its content sits 4px in (border + padding).
    return { type: 'image', src: cv.toDataURL('image/png'), left: Math.round(cx - w / 2 - 4), top: Math.round(cy - h / 2 - 4),
             w: w + 'px', h: h + 'px', rot: 0, label: text };
}

function tplSnapshot(type, color, sides, extra) {
    const side = els => ({ canvas: null, elements: els || [] });
    const bundle = p => p ? { front: side(p.front), back: side(p.back) } : null;
    return Object.assign({ v: 1, type, color,
        partners: { A: bundle(sides.A), B: bundle(sides.B) },
        sleeves: { A: { left: [], right: [] }, B: { left: [], right: [] } }, logo: [], form: {} }, extra || {});
}

const tplTextItem = (text, size, color, y) => ({ kind: 'text', text, size, color, x: SLEEVE_SIZE / 2, y: y || SLEEVE_SIZE / 2, rot: 0 });

// Flat illustrations for the illustrated templates. Few solid colours each,
// since every print colour is priced (see countPrintColors). No <text>: the
// print must not depend on fonts (only the glitch card preview uses it).
const TPL_ART = (() => {
    const art = (w, h, body) => ({ w, h, body });
    const r1 = n => Math.round(n * 10) / 10;
    const pine = (x, y, h) => 'M' + r1(x - h * 0.32) + ' ' + y + 'L' + x + ' ' + (y - h) + 'L' + r1(x + h * 0.32) + ' ' + y + 'Z';
    const sparkle = (x, y, r) => { const q = r * 0.25;
        return `M${x} ${y - r}L${x + q} ${y - q}L${x + r} ${y}L${x + q} ${y + q}L${x} ${y + r}L${x - q} ${y + q}L${x - r} ${y}L${x - q} ${y - q}Z`; };
    const wave = y => `M0 ${y}Q12.5 ${y - 8} 25 ${y}T50 ${y}T75 ${y}T100 ${y}V100H0Z`;
    const mirror = s => s + '<g transform="translate(100 0) scale(-1 1)">' + s + '</g>';
    const leaf = 'M50 94C14 70 12 30 50 6C88 30 86 70 50 94Z';
    const hud = '<path d="M2 14V2H14M86 2H98V14M98 46V58H86M14 58H2V46" fill="none" stroke="#FFFFFF" stroke-width="2.5"/>';

    const trees = ['', ''];
    [28, 40, 34, 50, 38, 56, 42, 48, 32, 44].forEach((h, i) => { trees[i % 2] += pine(6 + i * 12, 68, h); });

    let petals = '';
    for (let i = 0; i < 12; i++) petals += `<ellipse cx="50" cy="23" rx="6.5" ry="14" transform="rotate(${i * 30} 50 40)"/>`;

    let mane = '';
    for (let i = 0; i < 32; i++) {
        const r = i % 2 ? 35 : 47, t = i * Math.PI / 16;
        mane += (i ? 'L' : 'M') + r1(50 + r * Math.sin(t)) + ' ' + r1(52 - r * Math.cos(t));
    }

    let strandA = '', strandB = '', rungs = '';
    for (let y = 6; y <= 94; y += 2) {
        const s = Math.sin((y - 6) / 88 * Math.PI * 4), c = y === 6 ? 'M' : 'L';
        strandA += c + r1(32 + 22 * s) + ' ' + y;
        strandB += c + r1(32 - 22 * s) + ' ' + y;
        if (y % 6 === 4 && Math.abs(s) > 0.25) rungs += 'M' + r1(32 - 22 * s) + ' ' + y + 'H' + r1(32 + 22 * s);
    }

    const rays = [-180, -145, -110, -70, -35, 0].map(d => {
        const t = d * Math.PI / 180, c = Math.cos(t), s = Math.sin(t);
        return 'M' + r1(50 + 33 * c) + ' ' + r1(42 + 33 * s) + 'L' + r1(50 + 40 * c) + ' ' + r1(42 + 40 * s);
    }).join('');

    let grid = '';
    [76, 79, 83, 88, 94, 99].forEach(y => { grid += `M0 ${y}H100`; });
    for (let k = -6; k <= 6; k++) grid += `M${50 + k * 4} 76L${50 + k * 20} 100`;

    const rocket = 'M50 8C66 22 66 50 62 78H38C34 50 34 22 50 8Z';

    // Regular polygon / 5-point star / heart as path data.
    const poly = (cx, cy, r, n, rot) => {
        let d = '';
        for (let i = 0; i < n; i++) { const t = (rot + i * 360 / n) * Math.PI / 180; d += (i ? 'L' : 'M') + r1(cx + r * Math.cos(t)) + ' ' + r1(cy + r * Math.sin(t)); }
        return d + 'Z';
    };
    const star = (cx, cy, R) => {
        let d = '';
        for (let i = 0; i < 10; i++) { const t = (-90 + i * 36) * Math.PI / 180, r = i % 2 ? R * 0.42 : R; d += (i ? 'L' : 'M') + r1(cx + r * Math.cos(t)) + ' ' + r1(cy + r * Math.sin(t)); }
        return d + 'Z';
    };
    const heart = (cx, cy, s) => 'M' + cx + ' ' + r1(cy + s * 0.9) + 'C' + r1(cx - s * 1.2) + ' ' + r1(cy + s * 0.1) + ' ' + r1(cx - s) + ' ' + r1(cy - s * 0.9) + ' ' + cx + ' ' + r1(cy - s * 0.35)
        + 'C' + r1(cx + s) + ' ' + r1(cy - s * 0.9) + ' ' + r1(cx + s * 1.2) + ' ' + r1(cy + s * 0.1) + ' ' + cx + ' ' + r1(cy + s * 0.9) + 'Z';
    const ray = (cx, cy, deg, r0, r1_, half) => {   // thin triangle pointing outward
        const p = (d, r) => r1(cx + r * Math.cos(d * Math.PI / 180)) + ' ' + r1(cy + r * Math.sin(d * Math.PI / 180));
        return 'M' + p(deg - half, r0) + 'L' + p(deg, r1_) + 'L' + p(deg + half, r0) + 'Z';
    };

    let soccer = '', soccerSeams = '';
    for (let k = 0; k < 5; k++) {
        const a = -90 + 72 * k, b = (a + 36) * Math.PI / 180, ar = a * Math.PI / 180;
        soccer += poly(r1(50 + 37 * Math.cos(b)), r1(50 + 37 * Math.sin(b)), 13, 5, a + 36 + 180);
        soccerSeams += 'M' + r1(50 + 13 * Math.cos(ar)) + ' ' + r1(50 + 13 * Math.sin(ar)) + 'L' + r1(50 + 30 * Math.cos(ar)) + ' ' + r1(50 + 30 * Math.sin(ar));
    }

    let phSun = '';
    for (let i = 0; i < 8; i++) {
        const d = i * 45 - 90;
        phSun += ray(50, 52, d, 17, 44, 5) + ray(50, 52, d - 13, 17, 33, 3) + ray(50, 52, d + 13, 17, 33, 3);
    }

    let icing = '', sprinkles = '';
    for (let i = 0; i < 64; i++) {
        const t = i / 64 * Math.PI * 2, r = 33 + 2.5 * Math.sin(t * 10);
        icing += (i ? 'L' : 'M') + r1(50 + r * Math.cos(t)) + ' ' + r1(50 + r * Math.sin(t));
    }
    for (let i = 0; i < 14; i++) {
        const t = i / 14 * Math.PI * 2 + (i % 2) * 0.2, r = i % 2 ? 20 : 27;
        sprinkles += `<rect x="-3" y="-1.2" width="6" height="2.4" rx="1.2" transform="translate(${r1(50 + r * Math.cos(t))} ${r1(50 + r * Math.sin(t))}) rotate(${i * 47})"/>`;
    }
    const circle = r => `M${50 - r} 50a${r} ${r} 0 1 0 ${2 * r} 0a${r} ${r} 0 1 0 ${-2 * r} 0Z`;   // centred at 50,50
    const petal = 'M50 44Q41 30 50 13Q59 30 50 44Z';

    return {
        mountain: art(100, 100,
            '<defs><clipPath id="c"><circle cx="50" cy="50" r="42"/></clipPath></defs>'
            + '<circle cx="50" cy="50" r="47" fill="none" stroke="#FFFFFF" stroke-width="3"/>'
            + '<g clip-path="url(#c)"><circle cx="68" cy="34" r="9" fill="#FFDC00"/>'
            + '<path d="M-2 86L30 42L44 60L60 32L102 86Z" fill="#7FDBFF"/>'
            + '<path d="M24.2 50L30 42L36.2 50L33 48L30 51L27 48ZM53.1 44L60 32L69.3 44L65 41.5L61 45L57 41.5Z" fill="#FFFFFF"/>'
            + '<path d="M0 86H100V100H0Z' + pine(14, 90, 26) + pine(24, 90, 18) + pine(78, 90, 28) + pine(89, 90, 20) + '" fill="#2ECC40"/></g>'),
        ocean: art(100, 100,
            '<defs><clipPath id="c"><circle cx="50" cy="50" r="45"/></clipPath></defs>'
            + '<circle cx="50" cy="50" r="45" fill="#7FDBFF"/>'
            + '<g clip-path="url(#c)"><circle cx="50" cy="58" r="20" fill="#FF851B"/>'
            + `<path d="${wave(62)}" fill="#0074D9"/><path d="${wave(74)}" fill="#001F3F"/><path d="${wave(86)}" fill="#0074D9"/></g>`
            + '<path d="M22 30q4-4 8 0q4-4 8 0M58 22q3-3 6 0q3-3 6 0" fill="none" stroke="#001F3F" stroke-width="2" stroke-linecap="round"/>'
            + '<circle cx="50" cy="50" r="46.5" fill="none" stroke="#001F3F" stroke-width="3"/>'),
        forest: art(120, 72,
            '<defs><mask id="m"><rect width="120" height="72" fill="#fff"/><circle cx="104" cy="10" r="8" fill="#000"/></mask></defs>'
            + '<circle cx="99" cy="14" r="9" fill="#FFDC00" mask="url(#m)"/>'
            + '<g fill="#FFFFFF"><circle cx="12" cy="14" r="1.3"/><circle cx="30" cy="8" r="1.3"/><circle cx="48" cy="6" r="1.1"/><circle cx="60" cy="4" r="1.3"/><circle cx="78" cy="8" r="1.1"/></g>'
            + `<path d="${trees[0]}" fill="#2ECC40"/><path d="${trees[1]}" fill="#1E8C2E"/>`
            + '<rect y="67" width="120" height="5" rx="2" fill="#1E8C2E"/>'),
        daisy: art(100, 100,
            '<path d="M50 50V97" stroke="#2ECC40" stroke-width="4" stroke-linecap="round"/>'
            + '<g fill="#2ECC40"><ellipse cx="39" cy="78" rx="12" ry="5" transform="rotate(30 39 78)"/><ellipse cx="61" cy="87" rx="12" ry="5" transform="rotate(-30 61 87)"/></g>'
            + `<g fill="#FFFFFF">${petals}</g><circle cx="50" cy="40" r="10" fill="#FFDC00"/>`),
        leaves: art(100, 100,
            `<g fill="#1E8C2E"><path d="${leaf}" transform="rotate(-38 50 94) translate(10 18.8) scale(0.8)"/>`
            + `<path d="${leaf}" transform="rotate(38 50 94) translate(10 18.8) scale(0.8)"/></g>`
            + `<path d="${leaf}" fill="#2ECC40"/>`
            + '<path d="M50 90V16M50 32L36 22M50 32L64 22M50 48L31 36M50 48L69 36M50 64L32 52M50 64L68 52M50 78L39 70M50 78L61 70" stroke="#FFFFFF" stroke-width="2.5" stroke-linecap="round" fill="none"/>'),
        lion: art(100, 100,
            `<path d="${mane}Z" fill="#FF851B"/>`
            + '<g fill="#FFDC00"><circle cx="30" cy="30" r="9"/><circle cx="70" cy="30" r="9"/><ellipse cx="50" cy="55" rx="27" ry="29"/></g>'
            + '<g fill="#FF851B"><circle cx="30" cy="29" r="4.5"/><circle cx="70" cy="29" r="4.5"/></g>'
            + '<g fill="#FFFFFF"><circle cx="43.5" cy="68" r="8"/><circle cx="56.5" cy="68" r="8"/></g>'
            + '<g fill="#3D2B1F"><ellipse cx="39" cy="49" rx="3.2" ry="4.2"/><ellipse cx="61" cy="49" rx="3.2" ry="4.2"/><path d="M43 59H57L50 66Z"/></g>'
            + '<path d="M50 66V71M40 42l6 2M60 42l-6 2" stroke="#3D2B1F" stroke-width="2" stroke-linecap="round"/>'),
        cat: art(100, 100,
            '<path d="M18 44L20 10L42 28Q50 26 58 28L80 10L82 44Q86 84 50 86Q14 84 18 44Z" fill="#1A1A1A"/>'
            + '<g fill="#FF69B4"><path d="M24 34L25 18L37 29ZM76 34L75 18L63 29ZM46 63H54L50 68Z"/></g>'
            + '<g fill="#FFDC00"><ellipse cx="37" cy="51" rx="7" ry="8"/><ellipse cx="63" cy="51" rx="7" ry="8"/></g>'
            + '<g fill="#1A1A1A"><ellipse cx="37" cy="51" rx="2.2" ry="6"/><ellipse cx="63" cy="51" rx="2.2" ry="6"/></g>'
            + '<path d="M50 68Q46 73 42 70M50 68Q54 73 58 70M30 63L6 59M30 68L6 70M70 63L94 59M70 68L94 70" stroke="#FFFFFF" stroke-width="1.6" stroke-linecap="round" fill="none"/>'),
        dog: art(100, 100,
            '<g fill="#1A1A1A"><ellipse cx="22" cy="46" rx="11" ry="23" transform="rotate(18 22 46)"/><ellipse cx="78" cy="46" rx="11" ry="23" transform="rotate(-18 78 46)"/></g>'
            + '<ellipse cx="50" cy="50" rx="28" ry="30" fill="#C68642"/>'
            + '<ellipse cx="50" cy="67" rx="16" ry="12" fill="#FFFFFF"/>'
            + '<g fill="#1A1A1A"><circle cx="39" cy="45" r="4"/><circle cx="61" cy="45" r="4"/><ellipse cx="50" cy="60" rx="6.5" ry="4.5"/></g>'
            + '<g fill="#FFFFFF"><circle cx="40.4" cy="43.6" r="1.3"/><circle cx="62.4" cy="43.6" r="1.3"/></g>'
            + '<path d="M46.5 71.5Q50 81 53.5 71.5Z" fill="#FF69B4"/>'
            + '<path d="M50 64V69M43 69Q50 75 57 69" stroke="#1A1A1A" stroke-width="2" stroke-linecap="round" fill="none"/>'),
        paw: art(100, 90,
            '<g fill="#1A1A1A"><ellipse cx="50" cy="66" rx="19" ry="16"/><ellipse cx="25" cy="42" rx="8" ry="10" transform="rotate(-20 25 42)"/>'
            + '<ellipse cx="40" cy="24" rx="8" ry="10"/><ellipse cx="60" cy="24" rx="8" ry="10"/><ellipse cx="75" cy="42" rx="8" ry="10" transform="rotate(20 75 42)"/></g>'),
        panda: art(100, 100,
            '<g fill="#1A1A1A"><circle cx="24" cy="26" r="12"/><circle cx="76" cy="26" r="12"/></g>'
            + '<ellipse cx="50" cy="55" rx="36" ry="32" fill="#FFFFFF"/>'
            + '<g fill="#1A1A1A"><ellipse cx="35" cy="51" rx="8.5" ry="11" transform="rotate(35 35 51)"/><ellipse cx="65" cy="51" rx="8.5" ry="11" transform="rotate(-35 65 51)"/><ellipse cx="50" cy="66" rx="5.5" ry="4"/></g>'
            + '<g fill="#FFFFFF"><circle cx="36.5" cy="49" r="3.2"/><circle cx="63.5" cy="49" r="3.2"/></g>'
            + '<g fill="#FF69B4"><ellipse cx="27" cy="67" rx="5" ry="3"/><ellipse cx="73" cy="67" rx="5" ry="3"/></g>'
            + '<path d="M50 70V73M50 73Q46 78 42 75M50 73Q54 78 58 75" stroke="#1A1A1A" stroke-width="2" stroke-linecap="round" fill="none"/>'),
        butterfly: art(100, 100,
            '<g fill="#0074D9" stroke="#1A1A1A" stroke-width="2.5" stroke-linejoin="round">'
            + mirror('<path d="M48 48C36 18 8 8 8 30C8 46 28 54 48 52Z"/><path d="M48 56C30 56 14 70 22 84C30 94 46 80 48 60Z"/>') + '</g>'
            + '<g fill="#FFDC00">' + mirror('<circle cx="22" cy="30" r="6"/><circle cx="33" cy="41" r="3.5"/><circle cx="29" cy="75" r="4.5"/>') + '</g>'
            + '<path d="M49 32Q44 18 38 13M51 32Q56 18 62 13" stroke="#1A1A1A" stroke-width="1.8" stroke-linecap="round" fill="none"/>'
            + '<g fill="#1A1A1A"><circle cx="38" cy="13" r="2"/><circle cx="62" cy="13" r="2"/><circle cx="50" cy="34" r="4"/><ellipse cx="50" cy="57" rx="3.2" ry="20"/></g>'),
        atom: art(100, 100,
            '<g fill="none" stroke="#39CCCC" stroke-width="3">'
            + [0, 60, 120].map(a => `<ellipse cx="50" cy="50" rx="45" ry="16" transform="rotate(${a} 50 50)"/>`).join('') + '</g>'
            + '<circle cx="50" cy="50" r="9" fill="#FFDC00"/>'
            + '<g fill="#FFFFFF">' + [[95, 0], [5, 60], [95, 120]].map(([x, a]) => `<circle cx="${x}" cy="50" r="4" transform="rotate(${a} 50 50)"/>`).join('') + '</g>'),
        dna: art(64, 100,
            `<path d="${rungs}" stroke="#FFFFFF" stroke-width="2.5" stroke-linecap="round"/>`
            + `<path d="${strandA}" stroke="#FF69B4" stroke-width="4.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>`
            + `<path d="${strandB}" stroke="#39CCCC" stroke-width="4.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>`),
        flask: art(100, 100,
            '<path d="M31 60H69L80 84Q82 89 76 89H24Q18 89 20 84Z" fill="#2ECC40"/>'
            + '<g fill="#FFDC00"><circle cx="40" cy="76" r="4"/><circle cx="55" cy="69" r="3"/><circle cx="61" cy="80" r="5"/><circle cx="47" cy="50" r="2.5"/><circle cx="53" cy="41" r="2"/><circle cx="48" cy="28" r="3"/></g>'
            + '<path d="M38 8H62M43 8V38L18 84Q15 92 24 92H76Q85 92 82 84L57 38V8" stroke="#FFFFFF" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" fill="none"/>'),
        planet: art(100, 100,
            '<defs><clipPath id="p"><circle cx="50" cy="52" r="26"/></clipPath></defs>'
            + '<g transform="rotate(-18 50 52)">'
            + '<ellipse cx="50" cy="52" rx="46" ry="12" fill="none" stroke="#FFDC00" stroke-width="4"/>'
            + '<circle cx="50" cy="52" r="26" fill="#FF851B"/>'
            + '<path d="M20 44H80M20 59H80" stroke="#FF4136" stroke-width="4" clip-path="url(#p)"/>'
            + '<path d="M4 52A46 12 0 0 0 96 52" fill="none" stroke="#FFDC00" stroke-width="4"/></g>'
            + `<path d="${sparkle(14, 16, 5)}${sparkle(86, 14, 4)}${sparkle(88, 86, 5)}${sparkle(12, 84, 3.5)}" fill="#FFFFFF"/>`),
        bulb: art(100, 100,
            `<path d="${rays}" stroke="#FFFFFF" stroke-width="3" stroke-linecap="round"/>`
            + '<path d="M50 14C30 14 22 30 22 42C22 54 32 60 36 70H64C68 60 78 54 78 42C78 30 70 14 50 14Z" fill="#FFDC00"/>'
            + '<path d="M42 64V48L46 52L50 46L54 52L58 48V64" stroke="#FF851B" stroke-width="2.5" stroke-linejoin="round" fill="none"/>'
            + '<g fill="#AAAAAA"><rect x="37" y="72" width="26" height="5" rx="2"/><rect x="37" y="79" width="26" height="5" rx="2"/><path d="M42 86H58L54 92H46Z"/></g>'),
        synthwave: art(100, 100,
            '<defs><clipPath id="s"><circle cx="50" cy="42" r="30"/></clipPath></defs>'
            + '<g clip-path="url(#s)"><rect y="10" width="100" height="22" fill="#FFDC00"/><rect y="32" width="100" height="12" fill="#FF851B"/>'
            + '<g fill="#FF69B4"><rect y="44" width="100" height="5"/><rect y="51.5" width="100" height="4"/><rect y="58" width="100" height="3.2"/><rect y="63.6" width="100" height="2.6"/><rect y="68.4" width="100" height="2"/></g></g>'
            + `<path d="${grid}" stroke="#39CCCC" stroke-width="1.4" fill="none"/>`),
        robot: art(100, 100,
            '<path d="M50 8V18" stroke="#FFFFFF" stroke-width="3"/><circle cx="50" cy="7" r="4.5" fill="#39CCCC"/>'
            + '<g fill="#39CCCC"><rect x="9" y="36" width="10" height="20" rx="3"/><rect x="81" y="36" width="10" height="20" rx="3"/><path d="M20 98Q20 82 36 80H64Q80 82 80 98Z"/></g>'
            + '<rect x="18" y="18" width="64" height="56" rx="12" fill="#FFFFFF"/>'
            + '<g fill="#1A1A1A"><rect x="26" y="29" width="48" height="22" rx="11"/><rect x="35" y="58" width="30" height="8" rx="4"/><rect x="41" y="74" width="18" height="7"/></g>'
            + '<g fill="#39CCCC"><circle cx="39" cy="40" r="5"/><circle cx="61" cy="40" r="5"/></g>'
            + '<path d="M43 58V66M50 58V66M57 58V66" stroke="#FFFFFF" stroke-width="1.6"/>'),
        rocket: art(100, 100,
            `<defs><clipPath id="r"><path d="${rocket}"/></clipPath></defs>`
            + `<path d="${sparkle(16, 18, 5)}${sparkle(84, 26, 4)}${sparkle(18, 60, 3.5)}${sparkle(86, 66, 4.5)}" fill="#FFFFFF"/>`
            + '<path d="M40 78Q50 104 60 78Z" fill="#FF851B"/><path d="M45 78Q50 94 55 78Z" fill="#FFDC00"/>'
            + '<path d="M38 56L22 82L39 76ZM62 56L78 82L61 76Z" fill="#FF4136"/>'
            + `<path d="${rocket}" fill="#FFFFFF"/>`
            + '<g fill="#FF4136" clip-path="url(#r)"><rect width="100" height="28"/><rect y="66" width="100" height="4"/></g>'
            + '<circle cx="50" cy="46" r="8" fill="#FFDC00" stroke="#FF4136" stroke-width="3"/>'),
        circuit: art(100, 100,
            [0, 90, 180, 270].map(r => `<g transform="rotate(${r} 50 50)">`
                + '<path d="M39 31V24L28 13M50 31V9M61 31V24L72 13" stroke="#2ECC40" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" fill="none"/>'
                + '<g fill="#39CCCC"><circle cx="28" cy="13" r="3.5"/><circle cx="50" cy="9" r="3.5"/><circle cx="72" cy="13" r="3.5"/></g></g>').join('')
            + '<rect x="31" y="31" width="38" height="38" rx="4" fill="none" stroke="#2ECC40" stroke-width="3"/>'
            + '<rect x="41" y="41" width="18" height="18" rx="2" fill="#39CCCC"/>'),
        basketball: art(100, 100,
            '<path d="M2 36H14M0 50H11M2 64H14" stroke="#FFFFFF" stroke-width="3" stroke-linecap="round"/>'
            + '<circle cx="56" cy="50" r="38" fill="#FF851B"/>'
            + '<path d="M56 12V88M18 50H94M32 22Q50 50 32 78M80 22Q62 50 80 78" stroke="#1A1A1A" stroke-width="3" fill="none"/>'),
        volleyball: art(100, 100,
            '<defs><clipPath id="v"><circle cx="50" cy="50" r="42"/></clipPath></defs>'
            + '<circle cx="50" cy="50" r="42" fill="#FFFFFF"/>'
            + '<g clip-path="url(#v)">' + [0, 120, 240].map(r => `<g transform="rotate(${r} 50 50)">`
                + '<path d="M50 50C50 32 58 18 76 9L54 4C41 13 35 26 37 43Z" fill="#FFDC00"/>'
                + '<path d="M50 50C50 32 58 18 76 9M37 43C35 26 41 13 54 4" fill="none" stroke="#001F3F" stroke-width="3"/></g>').join('') + '</g>'
            + '<circle cx="50" cy="50" r="42" fill="none" stroke="#001F3F" stroke-width="3.5"/>'),
        medal: art(100, 100,
            '<path d="M28 4H46L60 46H42Z" fill="#FF4136"/><path d="M72 4H54L40 46H58Z" fill="#FFFFFF"/>'
            + '<circle cx="50" cy="68" r="27" fill="#FFDC00" stroke="#FF851B" stroke-width="3.5"/>'
            + `<path d="${star(50, 69, 15)}" fill="#FF851B"/>`),
        dumbbell: art(100, 100,
            '<path d="M58 2L28 54H48L38 98L74 40H54L68 2Z" fill="#FFDC00"/>'
            + '<g transform="rotate(-30 50 50)"><rect x="24" y="45" width="52" height="10" rx="2" fill="#AAAAAA"/>'
            + '<g fill="#FFFFFF"><rect x="14" y="28" width="11" height="44" rx="3"/><rect x="5" y="35" width="9" height="30" rx="3"/>'
            + '<rect x="75" y="28" width="11" height="44" rx="3"/><rect x="86" y="35" width="9" height="30" rx="3"/></g></g>'),
        soccer: art(100, 100,
            '<defs><clipPath id="f"><circle cx="50" cy="50" r="43"/></clipPath></defs>'
            + '<circle cx="50" cy="50" r="43" fill="#FFFFFF"/>'
            + `<g clip-path="url(#f)" fill="#1A1A1A"><path d="${poly(50, 50, 13, 5, -90)}${soccer}"/></g>`
            + `<path d="${soccerSeams}" stroke="#1A1A1A" stroke-width="2.5" stroke-linecap="round"/>`
            + '<circle cx="50" cy="50" r="43" fill="none" stroke="#1A1A1A" stroke-width="3"/>'),

        cross: art(100, 100,
            '<path d="' + Array.from({ length: 16 }, (_, i) => ray(50, 42, i * 22.5, 24, i % 2 ? 36 : 47, i % 2 ? 3 : 4)).join('') + '" fill="#FFDC00"/>'
            + '<path d="M44 12H56V32H74V44H56V92H44V44H26V32H44Z" fill="#FFFFFF"/>'),
        dove: art(100, 90,
            '<g fill="#FFFFFF"><path d="M52 44Q52 14 78 4Q76 30 64 46Z"/>'
            + '<path d="M4 50L28 58Q40 44 60 44Q70 30 82 32Q92 33 96 38L88 40Q86 52 74 58Q56 70 34 66L12 78L18 64Z"/>'
            + '<path d="M40 48Q26 20 44 6Q54 26 58 46Z"/></g>'
            + '<circle cx="84" cy="36" r="1.8" fill="#1A1A1A"/>'
            + '<path d="M91 39Q92 54 84 66" stroke="#2ECC40" stroke-width="2" stroke-linecap="round" fill="none"/>'
            + '<g fill="#2ECC40"><ellipse cx="96" cy="48" rx="5" ry="2.4" transform="rotate(-20 96 48)"/><ellipse cx="85" cy="52" rx="5" ry="2.4" transform="rotate(30 85 52)"/>'
            + '<ellipse cx="92" cy="60" rx="5" ry="2.4" transform="rotate(-35 92 60)"/><ellipse cx="80" cy="66" rx="5" ry="2.4" transform="rotate(20 80 66)"/></g>'),
        faithHeart: art(100, 100,
            `<path d="${heart(50, 52, 40)}" fill="#FF4136"/>`
            + '<path d="M45 28H55V40H66V50H55V74H45V50H34V40H45Z" fill="#FFFFFF"/>'),
        church: art(100, 100,
            '<path d="M50 2V14M45 6H55" stroke="#FFDC00" stroke-width="3" stroke-linecap="round"/>'
            + '<rect x="41" y="22" width="18" height="24" fill="#FFFFFF"/><path d="M38 24L50 12L62 24Z" fill="#FFDC00"/>'
            + '<path d="M10 54L50 32L90 54Z" fill="#FFDC00"/><rect x="18" y="52" width="64" height="40" fill="#FFFFFF"/>'
            + '<g fill="#1A1A1A"><circle cx="50" cy="31" r="3.5"/><path d="M43 92V76Q50 66 57 76V92Z"/>'
            + '<path d="M26 72V63Q30 57 34 63V72Z"/><path d="M66 72V63Q70 57 74 63V72Z"/></g>'
            + '<rect x="6" y="92" width="88" height="4" rx="2" fill="#FFDC00"/>'),
        candle: art(100, 100,
            '<path d="' + [-150, -120, -60, -30].map(d => ray(50, 30, d, 20, 30, 4)).join('') + '" fill="#FFDC00"/>'
            + '<path d="M50 8C61 22 63 34 50 46C37 34 39 22 50 8Z" fill="#FF851B"/><path d="M50 22C56 30 56 38 50 44C44 38 44 30 50 22Z" fill="#FFDC00"/>'
            + '<rect x="37" y="50" width="26" height="38" rx="3" fill="#FFFFFF"/>'
            + '<path d="M50 44V51" stroke="#FFFFFF" stroke-width="2"/>'
            + '<ellipse cx="50" cy="90" rx="24" ry="6" fill="#AAAAAA"/>'),

        coffee: art(100, 100,
            '<path d="M36 30Q30 22 36 15Q42 8 36 2M50 30Q44 22 50 15Q56 8 50 2M64 30Q58 22 64 15Q70 8 64 2" stroke="#AAAAAA" stroke-width="3" stroke-linecap="round" fill="none"/>'
            + '<path d="M74 48H80Q90 48 90 60Q90 72 80 72H74" stroke="#FF4136" stroke-width="7" fill="none"/>'
            + '<path d="M22 38H74V76Q74 92 58 92H38Q22 92 22 76Z" fill="#FF4136"/>'
            + '<ellipse cx="48" cy="38" rx="26" ry="5" fill="#6B3E1F"/>'
            + `<path d="${heart(48, 64, 10)}" fill="#FFFFFF"/>`),
        pizza: art(100, 100,
            '<path d="M16 24Q50 12 84 24L50 96Z" fill="#FFDC00"/>'
            + '<g fill="#FF4136"><circle cx="37" cy="34" r="7"/><circle cx="63" cy="36" r="7"/><circle cx="50" cy="55" r="7"/><circle cx="50" cy="76" r="5"/></g>'
            + '<path d="M10 16Q50 2 90 16L84 27Q50 14 16 27Z" fill="#C68642"/>'),
        milktea: art(100, 100,
            '<path d="M57 2L66 4L57 40L48 38Z" fill="#FFFFFF"/>'
            + '<path d="M26 36H74L68 94H32Z" fill="#C68642"/>'
            + '<path d="M22 34Q50 12 78 34Z" fill="#FFFFFF"/><rect x="20" y="32" width="60" height="6" rx="3" fill="#FFFFFF"/>'
            + '<g fill="#1A1A1A"><circle cx="38" cy="86" r="4.5"/><circle cx="48" cy="88" r="4.5"/><circle cx="58" cy="86" r="4.5"/><circle cx="43" cy="78" r="4.5"/><circle cx="53" cy="79" r="4.5"/><circle cx="62" cy="76" r="4"/>'
            + '<circle cx="42" cy="56" r="2.8"/><circle cx="58" cy="56" r="2.8"/></g>'
            + '<path d="M46 62Q50 66 54 62" stroke="#1A1A1A" stroke-width="2" stroke-linecap="round" fill="none"/>'
            + '<g fill="#FF69B4"><ellipse cx="37" cy="62" rx="3.5" ry="2"/><ellipse cx="63" cy="62" rx="3.5" ry="2"/></g>'),
        donut: art(100, 100,
            `<path d="${circle(42)}${circle(13)}" fill="#C68642" fill-rule="evenodd"/>`
            + `<path d="${icing}Z${circle(16)}" fill="#FF69B4" fill-rule="evenodd"/>`
            + `<g fill="#FFFFFF">${sprinkles}</g>`),
        burger: art(100, 90,
            '<path d="M14 66H86Q86 84 70 84H30Q14 84 14 66Z" fill="#C68642"/>'
            + '<rect x="11" y="50" width="78" height="16" rx="8" fill="#6B3E1F"/>'
            + '<path d="M12 46H88V51L79 60L71 51H40L32 60L24 51H12Z" fill="#FFDC00"/>'
            + '<path d="M8 44Q14 36 20 44T32 44T44 44T56 44T68 44T80 44T92 44V49H8Z" fill="#2ECC40"/>'
            + '<path d="M12 42Q12 6 50 6Q88 6 88 42Z" fill="#C68642"/>'
            + '<g fill="#FFFFFF"><ellipse cx="34" cy="20" rx="2.5" ry="1.4" transform="rotate(-25 34 20)"/><ellipse cx="50" cy="15" rx="2.5" ry="1.4"/><ellipse cx="66" cy="20" rx="2.5" ry="1.4" transform="rotate(25 66 20)"/>'
            + '<ellipse cx="42" cy="28" rx="2.5" ry="1.4" transform="rotate(-10 42 28)"/><ellipse cx="58" cy="28" rx="2.5" ry="1.4" transform="rotate(10 58 28)"/><ellipse cx="26" cy="32" rx="2.5" ry="1.4" transform="rotate(-35 26 32)"/><ellipse cx="74" cy="32" rx="2.5" ry="1.4" transform="rotate(35 74 32)"/></g>'),

        phSun: art(100, 104,
            `<path d="${phSun}" fill="#FFDC00"/><circle cx="50" cy="52" r="17" fill="#FFDC00"/>`
            + `<path d="${star(9, 9, 8)}${star(91, 9, 8)}${star(50, 97, 8)}" fill="#FFDC00"/>`),
        jeepney: art(120, 76,
            '<rect x="8" y="16" width="82" height="8" rx="3" fill="#FF4136"/>'
            + '<path d="M10 60V30Q10 24 16 24H84L92 38H108Q114 38 114 44V60Z" fill="#FFFFFF"/>'
            + '<g fill="#0074D9"><rect x="16" y="29" width="11" height="11" rx="2"/><rect x="30" y="29" width="11" height="11" rx="2"/><rect x="44" y="29" width="11" height="11" rx="2"/><rect x="58" y="29" width="11" height="11" rx="2"/><path d="M73 29H83L89 40H73Z"/></g>'
            + '<rect x="10" y="45" width="104" height="4" fill="#FF4136"/><rect x="10" y="51" width="104" height="3" fill="#0074D9"/>'
            + '<path d="M98 38L102 30L106 38Z" fill="#FF4136"/>'
            + '<g fill="#1A1A1A"><circle cx="30" cy="62" r="10"/><circle cx="94" cy="62" r="10"/><rect x="108" y="56" width="9" height="4" rx="1.5"/></g>'
            + '<g fill="#FFFFFF"><circle cx="30" cy="62" r="4"/><circle cx="94" cy="62" r="4"/></g>'),
        carabao: art(100, 100,
            '<g fill="#AAAAAA"><path d="M40 34C26 32 10 26 6 8C2 26 14 42 38 44Z"/><path d="M60 34C74 32 90 26 94 8C98 26 86 42 62 44Z"/></g>'
            + '<g fill="#4A4A4A"><ellipse cx="28" cy="48" rx="10" ry="4.5" transform="rotate(20 28 48)"/><ellipse cx="72" cy="48" rx="10" ry="4.5" transform="rotate(-20 72 48)"/>'
            + '<path d="M36 32Q50 26 64 32Q68 52 64 68Q62 88 50 90Q38 88 36 68Q32 52 36 32Z"/></g>'
            + '<ellipse cx="50" cy="80" rx="12" ry="9" fill="#AAAAAA"/>'
            + '<g fill="#1A1A1A"><ellipse cx="45" cy="80" rx="2.2" ry="3"/><ellipse cx="55" cy="80" rx="2.2" ry="3"/></g>'
            + '<g fill="#FFFFFF"><circle cx="42" cy="52" r="3.2"/><circle cx="58" cy="52" r="3.2"/></g>'
            + '<g fill="#1A1A1A"><circle cx="42.5" cy="52.5" r="1.7"/><circle cx="58.5" cy="52.5" r="1.7"/></g>'),
        sampaguita: art(100, 100,
            '<path d="M50 50Q50 76 50 98M50 76Q36 70 28 58M50 84Q64 78 72 66" stroke="#2ECC40" stroke-width="3" stroke-linecap="round" fill="none"/>'
            + '<g fill="#2ECC40"><ellipse cx="34" cy="84" rx="13" ry="5.5" transform="rotate(25 34 84)"/><ellipse cx="66" cy="92" rx="13" ry="5.5" transform="rotate(-25 66 92)"/></g>'
            + '<g fill="#FFFFFF"><ellipse cx="27" cy="55" rx="4.5" ry="7" transform="rotate(-50 27 55)"/><ellipse cx="73" cy="63" rx="4.5" ry="7" transform="rotate(50 73 63)"/></g>'
            + '<g fill="#FFFFFF" stroke="#1E8C2E" stroke-width="1">'
            + [0, 45, 90, 135, 180, 225, 270, 315].map(r => `<path d="${petal}" transform="rotate(${r} 50 44)"/>`).join('')
            + [22.5, 112.5, 202.5, 292.5].map(r => `<path d="${petal}" transform="rotate(${r} 50 44) translate(15 13.2) scale(0.7)"/>`).join('')
            + '</g><circle cx="50" cy="44" r="4" fill="#FFDC00"/>'),
        kubo: art(100, 100,
            '<g fill="#6B3E1F"><rect x="22" y="70" width="5" height="24"/><rect x="47" y="70" width="5" height="24"/><rect x="72" y="70" width="5" height="24"/><rect x="14" y="66" width="72" height="5" rx="1"/></g>'
            + '<rect x="20" y="44" width="60" height="23" fill="#C68642"/>'
            + '<path d="M26 44V66M32 44V66M38 44V66M62 44V66M68 44V66M74 44V66" stroke="#6B3E1F" stroke-width="1"/>'
            + '<rect x="42" y="49" width="15" height="11" rx="1" fill="#6B3E1F"/>'
            + '<path d="M84 71L92 94M90 71L98 94M86 77H92M88 83H94M90 89H96" stroke="#6B3E1F" stroke-width="2" stroke-linecap="round"/>'
            + '<path d="M4 48L50 8L96 48Z" fill="#E3B448"/>'
            + '<path d="M22 34H78M14 42H86M32 25H68" stroke="#6B3E1F" stroke-width="1.5" stroke-linecap="round"/>'),
        hud: art(100, 60, hud),
        glitchCard: art(100, 60, hud
            + '<g font-family="Impact, Arial Black, sans-serif" font-size="24" text-anchor="middle"><text x="48" y="39" fill="#39CCCC">GLITCH</text>'
            + '<text x="52" y="39" fill="#FF4136">GLITCH</text><text x="50" y="39" fill="#FFFFFF">GLITCH</text></g>')
    };
})();

const tplSvgMarkup = (art, w, h) =>
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + art.w + ' ' + art.h + '" width="' + w + '" height="' + h + '">' + art.body + '</svg>';

// An illustration as a design element, fitted into a box x box square at (cx, cy).
function tplSvg(art, cx, cy, box, label) {
    const s = box / Math.max(art.w, art.h), w = Math.round(art.w * s), h = Math.round(art.h * s);
    return { type: 'image', src: 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(tplSvgMarkup(art, art.w * 4, art.h * 4)),
             left: Math.round(cx - w / 2 - 4), top: Math.round(cy - h / 2 - 4), w: w + 'px', h: h + 'px', rot: 0, label: label || 'Artwork' };
}

// Illustrated template: arched title, artwork and subtitle on the front, two
// lines (plus optional small art) on the back. Positions scale with the design area.
function tplIllustrated(d) {
    const base = { family: 'Impact', bold: false };
    return {
        id: d.id, cat: d.cat, name: d.name, color: d.color, blurb: d.blurb, art: d.art,
        fields: [['title', 'Top text', d.title], ['sub', 'Bottom text', d.sub]],
        build(v) {
            const type = d.type || 'tshirt', a = APPAREL_CONFIG[type].area, cx = a.x + a.w / 2, k = a.h / 180;
            const at = f => a.y + a.h * f;
            const back = d.back.map(([text, o], i) => tplArt(text, Object.assign({}, base, o), cx, at(0.2 + i * 0.22), 124, (i ? 28 : 36) * k));
            if (d.backArt) back.push(tplSvg(d.backArt, cx, at(0.68), 56 * k, d.name));
            return tplSnapshot(type, d.color, { A: {
                front: [tplArt(v.title, Object.assign({ curve: 45 }, base, d.titleStyle), cx, at(0.17), 132, 46 * k),
                        tplSvg(d.art, cx, at(0.52), (d.artBox || 88) * k, d.name),
                        tplArt(v.sub, Object.assign({}, base, d.subStyle), cx, at(0.89), 116, 24 * k)],
                back
            } });
        }
    };
}

const TEMPLATES = [
    {
        id: 'barkada', cat: 'occasions', name: 'Barkada Trip', icon: 'fa-umbrella-beach', color: '#FFDC00',
        blurb: 'Arched trip title, place and year; "Tropa Goals" on the back.',
        fields: [['title', 'Trip title', 'BARKADA TRIP'], ['place', 'Place', 'BORACAY'], ['year', 'Year', '2026']],
        build(v) {
            const a = APPAREL_CONFIG.tshirt.area, cx = a.x + a.w / 2;
            return tplSnapshot('tshirt', '#FFDC00', { A: {
                front: [tplArt(v.title, { color: '#001F3F', curve: 55, outline: true, shadow: true }, cx, a.y + 38, 130),
                        tplArt(v.place, { color: '#FF4136', outline: true }, cx, a.y + 88, 110),
                        tplArt(v.year, { color: '#001F3F', curve: -40 }, cx, a.y + 125, 70)],
                back: [tplArt('TROPA GOALS', { color: '#001F3F', curve: 50, outline: true }, cx, a.y + 45, 130),
                       tplArt(v.year, { color: '#FF4136', size: 40 }, cx, a.y + 100, 80)]
            } }, { sleeves: { A: { left: [Object.assign(tplTextItem('⭐', 150, '#000000'), { emoji: true })], right: [] }, B: { left: [], right: [] } } });
        }
    },
    {
        id: 'family', cat: 'occasions', name: 'Family Reunion', icon: 'fa-people-roof', color: '#FFFFFF',
        blurb: 'Your family name in an arch, with the reunion year.',
        fields: [['family', 'Family name', 'SANTOS'], ['year', 'Year', '2026']],
        build(v) {
            const a = APPAREL_CONFIG.tshirt.area, cx = a.x + a.w / 2;
            const heart = tplArt('❤', { color: '#FF4136', bold: false }, cx, a.y + 130, 56);
            return tplSnapshot('tshirt', '#FFFFFF', { A: {
                front: [tplArt('THE ' + v.family + ' FAMILY', { color: '#800000', curve: 60, shadow: true }, cx, a.y + 38, 130),
                        tplArt('REUNION', { color: '#001F3F' }, cx, a.y + 88, 110), heart],
                back: [tplArt(v.family, { color: '#800000', curve: 40, outline: true, outlineColor: '#FFDC00' }, cx, a.y + 45, 120),
                       tplArt(v.year, { color: '#001F3F', size: 40 }, cx, a.y + 100, 80)]
            } });
        }
    },
    {
        id: 'company', cat: 'occasions', name: 'Company Uniform', icon: 'fa-briefcase', color: '#001F3F',
        blurb: 'Navy corporate polo: logo by the pocket, company name across the back.',
        fields: [['company', 'Company name', 'ACME CORP'], ['tagline', 'Tagline', 'Quality since 1998']],
        build(v) {
            const a = APPAREL_CONFIG.corp_polo.area, cx = a.x + a.w / 2;
            return tplSnapshot('corp_polo', '#001F3F', { A: {
                front: [],
                back: [tplArt(v.company, { color: '#FFFFFF', curve: 35 }, cx, a.y + 45, 140),
                       tplArt(v.tagline, { color: '#FFDC00', bold: false, italic: true }, cx, a.y + 90, 120)]
            } }, { logo: [tplTextItem(v.company, 44, '#FFFFFF', 135), tplTextItem(v.tagline, 22, '#FFDC00', 190)] });
        }
    },
    {
        id: 'jersey', cat: 'occasions', name: 'Team Jersey', icon: 'fa-basketball', color: '#FF4136',
        blurb: 'Team name on the front; player name and big number on the back and sleeve.',
        fields: [['team', 'Team name', 'WARRIORS'], ['player', 'Player name', 'SANTOS'], ['number', 'Number', '23']],
        build(v) {
            const a = APPAREL_CONFIG.tshirt.area, cx = a.x + a.w / 2;
            return tplSnapshot('tshirt', '#FF4136', { A: {
                front: [tplArt(v.team, { color: '#FFFFFF', curve: 45, outline: true, outlineColor: '#001F3F' }, cx, a.y + 40, 130),
                        tplArt(v.number, { color: '#FFFFFF', size: 60, outline: true, outlineColor: '#001F3F' }, cx, a.y + 115, 70)],
                back: [tplArt(v.player, { color: '#FFFFFF', curve: 35, outline: true, outlineColor: '#001F3F' }, cx, a.y + 35, 120),
                       tplArt(v.number, { color: '#FFFFFF', size: 90, outline: true, outlineColor: '#001F3F' }, cx, a.y + 110, 110)]
            } }, { sleeves: { A: { left: [tplTextItem(v.number, 150, '#FFFFFF')], right: [] }, B: { left: [], right: [] } } });
        }
    },
    {
        id: 'couple', cat: 'occasions', name: 'Couple Goals', icon: 'fa-heart', color: '#FFFFFF',
        blurb: 'A matching set: one shirt each, with your special date on the back.',
        fields: [['a', 'Partner A says', 'HIS'], ['b', 'Partner B says', 'HERS'], ['date', 'Special date', '02.14.2026']],
        build(v) {
            const a = APPAREL_CONFIG.couple_tshirt.area, cx = a.x + a.w / 2;
            const front = t => [tplArt(t, { color: '#000000', size: 40 }, cx, a.y + 55, 100),
                                tplArt('❤', { color: '#FF4136', bold: false }, cx, a.y + 112, 64)];
            const back = [tplArt(v.date, { color: '#FF4136', curve: 30 }, cx, a.y + 50, 120)];
            return tplSnapshot('couple_tshirt', '#FFFFFF', {
                A: { front: front(v.a), back }, B: { front: front(v.b), back }
            });
        }
    },

    // ---- Nature ----
    tplIllustrated({ id: 'mountain', cat: 'nature', name: 'Mountain Explorer', color: '#001F3F', art: TPL_ART.mountain,
        blurb: 'Mountain badge with sun and pine trees, for hikers and trips.',
        title: 'EXPLORE MORE', sub: 'MT. PULAG', titleStyle: { color: '#FFFFFF' }, subStyle: { color: '#FFDC00' },
        back: [['ADVENTURE AWAITS', { color: '#FFFFFF', curve: 30 }], ['TAKE ONLY MEMORIES', { color: '#7FDBFF' }]] }),
    tplIllustrated({ id: 'ocean', cat: 'nature', name: 'Ocean Sunset', color: '#FFFFFF', art: TPL_ART.ocean,
        blurb: 'Sunset over rolling waves, for beach trips and surf crews.',
        title: 'SALT LIFE', sub: 'LA UNION', titleStyle: { color: '#001F3F' }, subStyle: { color: '#0074D9' },
        back: [['GOOD VIBES', { color: '#001F3F', curve: 30 }], ['HIGH TIDES', { color: '#0074D9' }]] }),
    tplIllustrated({ id: 'forest', cat: 'nature', name: 'Into the Woods', color: '#000000', art: TPL_ART.forest, artBox: 124,
        blurb: 'A pine forest under the moon and stars.',
        title: 'INTO THE WOODS', sub: 'SAGADA', titleStyle: { color: '#FFFFFF' }, subStyle: { color: '#2ECC40' },
        back: [['LEAVE NO TRACE', { color: '#FFFFFF', curve: 30 }], ['WILD & FREE', { color: '#2ECC40' }]] }),
    tplIllustrated({ id: 'daisy', cat: 'nature', name: 'Hello Sunshine', color: '#FF69B4', art: TPL_ART.daisy,
        blurb: 'A cheerful daisy with a sunny message.',
        title: 'HELLO SUNSHINE', sub: 'STAY WILD', titleStyle: { color: '#FFFFFF' },
        subStyle: { color: '#FFFFFF', family: 'Georgia', bold: true, italic: true },
        back: [['BLOOM WHERE', { color: '#FFFFFF', curve: 30 }], ['YOU ARE PLANTED', { color: '#FFDC00', family: 'Georgia', bold: true, italic: true }]] }),
    tplIllustrated({ id: 'plantita', cat: 'nature', name: 'Plantita', color: '#FFFFFF', art: TPL_ART.leaves,
        blurb: 'Fresh green leaves for plant lovers.',
        title: 'PLANTITA', sub: 'PLANT PARENT', titleStyle: { color: '#1E8C2E' }, subStyle: { color: '#1A1A1A' },
        back: [['STAY ROOTED', { color: '#1E8C2E', curve: 30 }], ['KEEP GROWING', { color: '#1A1A1A' }]] }),

    // ---- Animals ----
    tplIllustrated({ id: 'lion', cat: 'animals', name: 'Wild Lion', color: '#000000', art: TPL_ART.lion,
        blurb: 'A bold lion face with a spiky golden mane.',
        title: 'WILD AT HEART', sub: 'KING OF THE JUNGLE', titleStyle: { color: '#FFDC00' }, subStyle: { color: '#FF851B' },
        back: [['BRAVE', { color: '#FFDC00', curve: 30 }], ['STRONG • FEARLESS', { color: '#FF851B' }]] }),
    tplIllustrated({ id: 'cat', cat: 'animals', name: 'Cat Person', color: '#FF69B4', art: TPL_ART.cat, backArt: TPL_ART.paw,
        blurb: 'A cute black cat with golden eyes.',
        title: 'CAT PERSON', sub: 'MEOW SQUAD', titleStyle: { color: '#1A1A1A' }, subStyle: { color: '#1A1A1A' },
        back: [['NINE LIVES', { color: '#1A1A1A', curve: 30 }], ['ZERO REGRETS', { color: '#FFFFFF' }]] }),
    tplIllustrated({ id: 'dog', cat: 'animals', name: 'Dog Lover', color: '#FFDC00', art: TPL_ART.dog, backArt: TPL_ART.paw,
        blurb: 'A happy puppy face, with a paw print on the back.',
        title: 'DOG LOVER', sub: "BANTAY'S HUMAN", titleStyle: { color: '#1A1A1A' }, subStyle: { color: '#1A1A1A' },
        back: [['WHO RESCUED WHO?', { color: '#1A1A1A', curve: 30 }], ['ADOPT DON\'T SHOP', { color: '#1A1A1A' }]] }),
    tplIllustrated({ id: 'panda', cat: 'animals', name: 'Chill Panda', color: '#2ECC40', art: TPL_ART.panda,
        blurb: 'A sleepy, blushing panda, in just black and white.',
        title: 'STAY CHILL', sub: 'PANDA SQUAD', titleStyle: { color: '#FFFFFF' }, subStyle: { color: '#1A1A1A' },
        back: [['NAP QUEEN', { color: '#FFFFFF', curve: 30 }], ['DO NOT DISTURB', { color: '#1A1A1A' }]] }),
    tplIllustrated({ id: 'butterfly', cat: 'animals', name: 'Butterfly', color: '#FFFFFF', art: TPL_ART.butterfly,
        blurb: 'A blue butterfly with golden spots.',
        title: 'SPREAD YOUR WINGS', sub: 'BE FREE', titleStyle: { color: '#0074D9' },
        subStyle: { color: '#1A1A1A', family: 'Georgia', bold: true, italic: true },
        back: [['TRANSFORM', { color: '#0074D9', curve: 30 }], ['AND FLY', { color: '#1A1A1A', family: 'Georgia', bold: true, italic: true }]] }),

    // ---- Science ----
    tplIllustrated({ id: 'atom', cat: 'science', name: 'Science Club', color: '#001F3F', art: TPL_ART.atom,
        blurb: 'A glowing atom, for science clubs and fairs.',
        title: 'SCIENCE CLUB', sub: 'STAY CURIOUS', titleStyle: { color: '#39CCCC' }, subStyle: { color: '#FFFFFF' },
        back: [['THINK LIKE A PROTON', { color: '#39CCCC', curve: 30 }], ['STAY POSITIVE', { color: '#FFDC00' }]] }),
    tplIllustrated({ id: 'dna', cat: 'science', name: 'DNA Helix', color: '#000000', art: TPL_ART.dna,
        blurb: 'A pink and cyan double helix for biology lovers.',
        title: "IT'S IN MY DNA", sub: 'BIOLOGY CLUB', titleStyle: { color: '#FF69B4' }, subStyle: { color: '#39CCCC' },
        back: [['GENETICALLY', { color: '#FF69B4', curve: 30 }], ['AWESOME', { color: '#39CCCC' }]] }),
    tplIllustrated({ id: 'chemistry', cat: 'science', name: 'Chemistry', color: '#800000', art: TPL_ART.flask,
        blurb: 'A bubbling flask, for chemistry classes and clubs.',
        title: 'CHEM SQUAD', sub: 'GOOD CHEMISTRY', titleStyle: { color: '#FFFFFF' }, subStyle: { color: '#FFDC00' },
        back: [['TRUST ME', { color: '#FFFFFF', curve: 30 }], ["I'M A CHEMIST", { color: '#2ECC40' }]] }),
    tplIllustrated({ id: 'astronomy', cat: 'science', name: 'Astronomy', color: '#001F3F', art: TPL_ART.planet,
        blurb: 'A ringed planet among the stars.',
        title: 'OUT OF THIS WORLD', sub: 'ASTRONOMY CLUB', titleStyle: { color: '#FFDC00' }, subStyle: { color: '#FFFFFF' },
        back: [['LOOK UP', { color: '#FFDC00', curve: 30 }], ['AT THE STARS', { color: '#FFFFFF' }]] }),
    tplIllustrated({ id: 'bulb', cat: 'science', name: 'Bright Ideas', color: '#000000', art: TPL_ART.bulb,
        blurb: 'A shining light bulb, for innovators and inventors.',
        title: 'BRIGHT IDEAS', sub: 'INNOVATION CLUB', titleStyle: { color: '#FFDC00' }, subStyle: { color: '#FFFFFF' },
        back: [['E = mc²', { color: '#FFDC00', family: 'Georgia', bold: true, italic: true }], ['STAY CURIOUS', { color: '#FFFFFF' }]] }),

    // ---- Futuristic ----
    tplIllustrated({ id: 'synthwave', cat: 'futuristic', name: 'Neon Synthwave', color: '#000000', type: 'hoodie', art: TPL_ART.synthwave,
        blurb: 'Retro-future hoodie: striped neon sun over a glowing grid.',
        title: 'NEON DREAMS', sub: 'EST. 2026', titleStyle: { color: '#FF69B4', italic: true }, subStyle: { color: '#39CCCC' },
        back: [['RETRO FUTURE', { color: '#FF69B4', italic: true, curve: 30 }], ['1984 • 2084', { color: '#39CCCC' }]] }),
    tplIllustrated({ id: 'robot', cat: 'futuristic', name: 'Robotics Team', color: '#808080', type: 'hoodie', art: TPL_ART.robot,
        blurb: 'A friendly robot hoodie for robotics and tech teams.',
        title: 'ROBOTICS TEAM', sub: 'BUILD • CODE • WIN', titleStyle: { color: '#FFFFFF' }, subStyle: { color: '#1A1A1A' },
        back: [['BEEP BOOP', { color: '#FFFFFF', curve: 30 }], ['HUMAN MODE: OFF', { color: '#1A1A1A' }]] }),
    tplIllustrated({ id: 'rocket', cat: 'futuristic', name: 'To the Moon', color: '#001F3F', art: TPL_ART.rocket,
        blurb: 'A rocket blasting off between the stars.',
        title: 'TO THE MOON', sub: 'DREAM BIG', titleStyle: { color: '#FFFFFF' }, subStyle: { color: '#FFDC00' },
        back: [['NEXT STOP', { color: '#FFFFFF', curve: 30 }], ['THE STARS', { color: '#FFDC00' }]] }),
    tplIllustrated({ id: 'circuit', cat: 'futuristic', name: 'Tech Crew', color: '#000000', art: TPL_ART.circuit,
        blurb: 'A glowing circuit chip, for coders and IT teams.',
        title: 'TECH CREW', sub: 'LEVEL UP',
        titleStyle: { color: '#2ECC40', family: 'Courier New', bold: true }, subStyle: { color: '#39CCCC', family: 'Courier New', bold: true },
        back: [['</>', { color: '#2ECC40', family: 'Courier New', bold: true }], ['HELLO, WORLD', { color: '#39CCCC', family: 'Courier New', bold: true }]] }),
    {
        id: 'glitch', cat: 'futuristic', name: 'Glitch', color: '#000000', art: TPL_ART.glitchCard,
        blurb: 'Cyber glitch text with an RGB split inside a HUD frame.',
        fields: [['title', 'Big word', 'GLITCH'], ['sub', 'Bottom text', 'SYSTEM ERROR 404']],
        build(v) {
            const a = APPAREL_CONFIG.tshirt.area, cx = a.x + a.w / 2, cy = a.y + 92;
            const mono = c => ({ family: 'Courier New', bold: true, color: c });
            const big = c => ({ family: 'Impact', bold: false, size: 40, color: c });
            return tplSnapshot('tshirt', '#000000', { A: {
                front: [tplArt('// ACCESS GRANTED', mono('#39CCCC'), cx, a.y + 28, 124, 18),
                        tplSvg(TPL_ART.hud, cx, cy, 136, 'HUD frame'),
                        // Same text three times, nudged apart: the RGB-split glitch look.
                        tplArt(v.title, big('#39CCCC'), cx - 2, cy - 1, 112, 50),
                        tplArt(v.title, big('#FF4136'), cx + 2, cy + 1, 112, 50),
                        tplArt(v.title, big('#FFFFFF'), cx, cy, 112, 50),
                        tplArt(v.sub, mono('#FFFFFF'), cx, a.y + 156, 124, 20)],
                back: [tplArt('REBOOTING...', mono('#39CCCC'), cx, a.y + 50, 124, 26),
                       tplArt('LOADING 67%', mono('#FF4136'), cx, a.y + 90, 110, 22)]
            } });
        }
    },

    // ---- Sports ----
    tplIllustrated({ id: 'basketball', cat: 'sports', name: 'Ball Is Life', color: '#000000', art: TPL_ART.basketball,
        blurb: 'A speeding basketball, for hoopers and liga teams.',
        title: 'BALL IS LIFE', sub: 'HOOPS SQUAD', titleStyle: { color: '#FF851B' }, subStyle: { color: '#FFFFFF' },
        back: [['PUSO', { color: '#FF851B', curve: 30 }], ['NEVER STOP BALLING', { color: '#FFFFFF' }]] }),
    tplIllustrated({ id: 'volleyball', cat: 'sports', name: 'Volleyball Club', color: '#0074D9', art: TPL_ART.volleyball,
        blurb: 'A yellow and white volleyball, for spikers and setters.',
        title: 'SPIKE IT', sub: 'VOLLEYBALL CLUB', titleStyle: { color: '#FFDC00' }, subStyle: { color: '#FFFFFF' },
        back: [['SET • SPIKE', { color: '#FFFFFF', curve: 30 }], ['REPEAT', { color: '#FFDC00' }]] }),
    tplIllustrated({ id: 'runclub', cat: 'sports', name: 'Run Club', color: '#001F3F', art: TPL_ART.medal,
        blurb: 'A gold finisher medal, for fun runs and marathons.',
        title: 'RUN CLUB', sub: '21K FINISHER', titleStyle: { color: '#FFFFFF' }, subStyle: { color: '#FFDC00' },
        back: [['EARNED', { color: '#FFDC00', curve: 30 }], ['NOT GIVEN', { color: '#FFFFFF' }]] }),
    tplIllustrated({ id: 'gym', cat: 'sports', name: 'Gym Rat', color: '#000000', art: TPL_ART.dumbbell,
        blurb: 'A dumbbell with a lightning bolt, for gym buddies.',
        title: 'NO PAIN NO GAIN', sub: 'GYM RAT', titleStyle: { color: '#FFDC00' }, subStyle: { color: '#FFFFFF' },
        back: [['LIFT HEAVY', { color: '#FFFFFF', curve: 30 }], ['STAY HUMBLE', { color: '#FFDC00' }]] }),
    tplIllustrated({ id: 'football', cat: 'sports', name: 'Football Club', color: '#2ECC40', art: TPL_ART.soccer,
        blurb: 'A classic black and white football.',
        title: 'GOAL GETTER', sub: 'FOOTBALL CLUB', titleStyle: { color: '#FFFFFF' }, subStyle: { color: '#1A1A1A' },
        back: [['ONE TEAM', { color: '#FFFFFF', curve: 30 }], ['ONE GOAL', { color: '#1A1A1A' }]] }),

    // ---- Faith ----
    tplIllustrated({ id: 'blessed', cat: 'faith', name: 'Blessed', color: '#001F3F', art: TPL_ART.cross,
        blurb: 'A cross with golden rays of light.',
        title: 'BLESSED', sub: 'PSALM 23:1', titleStyle: { color: '#FFFFFF' }, subStyle: { color: '#FFDC00' },
        back: [['THE LORD IS', { color: '#FFFFFF', curve: 30 }], ['MY SHEPHERD', { color: '#FFDC00' }]] }),
    tplIllustrated({ id: 'dove', cat: 'faith', name: 'Faith Over Fear', color: '#0074D9', art: TPL_ART.dove,
        blurb: 'A white dove carrying an olive branch.',
        title: 'FAITH OVER FEAR', sub: 'ISAIAH 41:10', titleStyle: { color: '#FFFFFF' }, subStyle: { color: '#FFFFFF' },
        back: [['FEAR NOT', { color: '#FFFFFF', curve: 30 }], ['FOR I AM WITH YOU', { color: '#FFFFFF' }]] }),
    tplIllustrated({ id: 'godislove', cat: 'faith', name: 'God Is Love', color: '#FFFFFF', art: TPL_ART.faithHeart,
        blurb: 'A red heart with a cross inside.',
        title: 'GOD IS LOVE', sub: '1 JOHN 4:8', titleStyle: { color: '#001F3F' }, subStyle: { color: '#FF4136' },
        back: [['LOVED', { color: '#FF4136', curve: 30 }], ['CHOSEN • FORGIVEN', { color: '#001F3F' }]] }),
    tplIllustrated({ id: 'youth', cat: 'faith', name: 'Youth Ministry', color: '#800000', art: TPL_ART.church,
        blurb: 'A little chapel, for youth groups and church events.',
        title: 'YOUTH MINISTRY', sub: 'SERVE WITH JOY', titleStyle: { color: '#FFFFFF' }, subStyle: { color: '#FFDC00' },
        back: [['ONE BODY', { color: '#FFFFFF', curve: 30 }], ['ONE SPIRIT', { color: '#FFDC00' }]] }),
    tplIllustrated({ id: 'light', cat: 'faith', name: 'Let Your Light Shine', color: '#000000', art: TPL_ART.candle,
        blurb: 'A glowing candle with a verse.',
        title: 'LET YOUR LIGHT SHINE', sub: 'MATTHEW 5:16', titleStyle: { color: '#FFDC00' }, subStyle: { color: '#FFFFFF' },
        back: [['BE THE LIGHT', { color: '#FFDC00', curve: 30 }], ['IN THE DARK', { color: '#FFFFFF' }]] }),

    // ---- Food ----
    tplIllustrated({ id: 'coffee', cat: 'food', name: 'But First, Coffee', color: '#FFFFFF', art: TPL_ART.coffee,
        blurb: 'A steaming red mug, for coffee lovers.',
        title: 'BUT FIRST', sub: 'COFFEE', titleStyle: { color: '#6B3E1F' }, subStyle: { color: '#FF4136' },
        back: [['POWERED BY', { color: '#6B3E1F', curve: 30 }], ['CAFFEINE', { color: '#FF4136' }]] }),
    tplIllustrated({ id: 'pizza', cat: 'food', name: 'Pizza Is Life', color: '#000000', art: TPL_ART.pizza,
        blurb: 'A cheesy pepperoni slice.',
        title: 'PIZZA IS LIFE', sub: 'EXTRA CHEESE PLS', titleStyle: { color: '#FFDC00' }, subStyle: { color: '#FFFFFF' },
        back: [['IN CRUST', { color: '#FFDC00', curve: 30 }], ['WE TRUST', { color: '#FF4136' }]] }),
    tplIllustrated({ id: 'milktea', cat: 'food', name: 'Milk Tea Addict', color: '#FF69B4', art: TPL_ART.milktea,
        blurb: 'A cute smiling cup of pearl milk tea.',
        title: 'MILK TEA ADDICT', sub: 'MORE PEARLS PLS', titleStyle: { color: '#FFFFFF' }, subStyle: { color: '#1A1A1A' },
        back: [['SIP SIP', { color: '#FFFFFF', curve: 30 }], ['HOORAY', { color: '#1A1A1A' }]] }),
    tplIllustrated({ id: 'donut', cat: 'food', name: 'Donut Worry', color: '#FFDC00', art: TPL_ART.donut,
        blurb: 'A pink frosted donut with sprinkles.',
        title: 'DONUT WORRY', sub: 'BE HAPPY', titleStyle: { color: '#1A1A1A' }, subStyle: { color: '#1A1A1A' },
        back: [['SWEET', { color: '#1A1A1A', curve: 30 }], ['VIBES ONLY', { color: '#1A1A1A' }]] }),
    tplIllustrated({ id: 'burger', cat: 'food', name: 'Burger Time', color: '#000000', art: TPL_ART.burger,
        blurb: 'A stacked cheeseburger with sesame seeds.',
        title: 'BURGER TIME', sub: 'EAT • SLEEP • REPEAT', titleStyle: { color: '#FFDC00' }, subStyle: { color: '#FFFFFF' },
        back: [['LIFE IS SHORT', { color: '#FFFFFF', curve: 30 }], ['EAT THE BURGER', { color: '#FFDC00' }]] }),

    // ---- Pinoy Pride ----
    tplIllustrated({ id: 'proudpinoy', cat: 'pinoy', name: 'Proud Pinoy', color: '#001F3F', art: TPL_ART.phSun,
        blurb: 'The sun and three stars of the Philippine flag.',
        title: 'PROUD PINOY', sub: 'MABUHAY!', titleStyle: { color: '#FFFFFF' }, subStyle: { color: '#FFDC00' },
        back: [['ISANG BANSA', { color: '#FFFFFF', curve: 30 }], ['ISANG DIWA', { color: '#FF4136' }]] }),
    tplIllustrated({ id: 'jeepney', cat: 'pinoy', name: 'Jeepney', color: '#FFDC00', art: TPL_ART.jeepney, artBox: 124,
        blurb: 'The King of the Road, with red and blue stripes.',
        title: 'PARA PO!', sub: 'BIYAHENG MAYNILA', titleStyle: { color: '#1A1A1A' }, subStyle: { color: '#FF4136' },
        back: [['KING OF THE ROAD', { color: '#1A1A1A', curve: 30 }], ['BAYAD PO', { color: '#0074D9' }]] }),
    tplIllustrated({ id: 'kalabaw', cat: 'pinoy', name: 'Kalabaw', color: '#2ECC40', art: TPL_ART.carabao,
        blurb: 'The hardworking carabao, our national animal.',
        title: 'SIPAG AT TIYAGA', sub: 'KALABAW POWER', titleStyle: { color: '#FFFFFF' }, subStyle: { color: '#1A1A1A' },
        back: [['BATANG', { color: '#FFFFFF', curve: 30 }], ['PROBINSYA', { color: '#1A1A1A' }]] }),
    tplIllustrated({ id: 'sampaguita', cat: 'pinoy', name: 'Sampaguita', color: '#001F3F', art: TPL_ART.sampaguita,
        blurb: 'The sampaguita, our national flower.',
        title: 'DALAGANG PILIPINA', sub: 'SAMPAGUITA', titleStyle: { color: '#FFFFFF' },
        subStyle: { color: '#FFDC00', family: 'Georgia', bold: true, italic: true },
        back: [['PINAY', { color: '#FFFFFF', curve: 30 }], ['PRIDE', { color: '#FFDC00', family: 'Georgia', bold: true, italic: true }]] }),
    tplIllustrated({ id: 'bahaykubo', cat: 'pinoy', name: 'Bahay Kubo', color: '#FFFFFF', art: TPL_ART.kubo,
        blurb: 'A nipa hut on stilts, straight from the song.',
        title: 'BAHAY KUBO', sub: 'KAHIT MUNTI', titleStyle: { color: '#6B3E1F' }, subStyle: { color: '#1E8C2E' },
        back: [['ANG HALAMAN DOON', { color: '#6B3E1F', curve: 30 }], ['AY SARI-SARI', { color: '#1E8C2E' }]] })
];

const TPL_CATS = [['all', 'All'], ['occasions', 'Occasions'], ['nature', 'Nature'], ['animals', 'Animals'], ['science', 'Science'],
                  ['futuristic', 'Futuristic'], ['sports', 'Sports'], ['faith', 'Faith'], ['food', 'Food'], ['pinoy', 'Pinoy Pride']];
let tplCat = 'all';

function tplThumb(t) {
    const inner = t.art
        ? '<img src="data:image/svg+xml;charset=utf-8,' + encodeURIComponent(tplSvgMarkup(t.art, t.art.w * 2, t.art.h * 2)) + '" alt="">'
        : '<i class="fas ' + t.icon + '"></i>';
    return '<span class="tpl-thumb" style="background:' + t.color + ';color:' + (isDarkColor(t.color) ? '#fff' : '#333') + '">' + inner + '</span>';
}

function renderTemplateGrid() {
    document.getElementById('templateTabs').innerHTML = TPL_CATS.map(([key, label]) => {
        const n = key === 'all' ? TEMPLATES.length : TEMPLATES.filter(t => t.cat === key).length;
        return '<button type="button" class="tpl-tab' + (key === tplCat ? ' active' : '') + '" aria-pressed="' + (key === tplCat)
            + '" onclick="setTemplateCat(\'' + key + '\')">' + label + ' <span>' + n + '</span></button>';
    }).join('');
    document.getElementById('templateGrid').innerHTML = TEMPLATES.filter(t => tplCat === 'all' || t.cat === tplCat).map(t =>
        '<button type="button" class="tpl-card" onclick="pickTemplate(\'' + t.id + '\')">' + tplThumb(t)
        + '<strong>' + escapeHtml(t.name) + '</strong><small>' + escapeHtml(t.blurb) + '</small></button>'
    ).join('');
}

function setTemplateCat(key) {
    tplCat = key;
    renderTemplateGrid();
}

function openTemplates() {
    renderTemplateGrid();
    document.getElementById('templateForm').hidden = true;
    document.getElementById('templateTabs').hidden = false;
    document.getElementById('templateGrid').hidden = false;
    document.getElementById('templateModal').hidden = false;
}

function closeTemplates() { document.getElementById('templateModal').hidden = true; }

let pickedTemplate = null;
function pickTemplate(id) {
    pickedTemplate = TEMPLATES.find(t => t.id === id);
    if (!pickedTemplate) return;
    document.getElementById('templateFormTitle').textContent = pickedTemplate.name;
    document.getElementById('templateFields').innerHTML = pickedTemplate.fields.map(([key, label, dflt]) =>
        '<label class="tpl-field">' + escapeHtml(label)
        + '<input type="text" maxlength="30" data-key="' + key + '" value="' + escapeHtml(dflt).replace(/"/g, '&quot;') + '"></label>'
    ).join('');
    document.getElementById('templateGrid').hidden = true;
    document.getElementById('templateTabs').hidden = true;
    document.getElementById('templateForm').hidden = false;
    document.querySelector('#templateFields input')?.focus();
}

function backToTemplates() {
    document.getElementById('templateForm').hidden = true;
    document.getElementById('templateTabs').hidden = false;
    document.getElementById('templateGrid').hidden = false;
}

// Each template is laid out for one apparel (mostly the T-shirt). Move and
// scale it into the print area of the apparel being designed, so every
// template works on hoodies, polos, couple sets and corporate wear too.
// A one-person template on a couple set goes on both partners. A template
// built around the pocket logo (Company Uniform) keeps its corporate apparel
// when the chosen one has no logo spot, or its front would come out blank.
function fitTemplate(snap, type) {
    if (!APPAREL_CONFIG[type] || type === snap.type) return snap;
    if ((snap.logo || []).length && !logoZoneOf(type)) return snap;
    const from = areaOf(snap.type), full = areaOf(type), lz = logoZoneOf(type);
    // Corporate wear: the front print goes below the pocket logo spot, not over it.
    const below = lz ? lz.y + lz.h + 6 : full.y;
    const frontArea = { x: full.x, y: below, w: full.w, h: full.y + full.h - below };
    const fitInto = to => {
        const k = Math.min(1, to.w / from.w, to.h / from.h);   // never upscale the rendered text art
        const dx = to.x + (to.w - from.w * k) / 2, dy = to.y + (to.h - from.h * k) / 2;
        return e => {
            // left/top are the element box; its content sits 4px in (border + padding).
            const moved = Object.assign({}, e, {
                left: Math.round(dx + (e.left + 4 - from.x) * k - 4),
                top: Math.round(dy + (e.top + 4 - from.y) * k - 4)
            });
            if (e.type === 'image') { moved.w = parseFloat(e.w) * k + 'px'; moved.h = parseFloat(e.h) * k + 'px'; }
            return moved;
        };
    };
    const side = (j, fit) => j && { canvas: null, elements: (j.elements || []).map(fit) };
    const bundle = p => p && { front: side(p.front, fitInto(frontArea)), back: side(p.back, fitInto(full)) };
    const P = snap.partners || {}, S = snap.sleeves || {};
    const copyA = !P.B && isCouple(type);
    return Object.assign({}, snap, {
        type,
        partners: { A: bundle(P.A), B: bundle(copyA ? P.A : P.B) },
        sleeves: { A: S.A, B: copyA ? S.A : S.B }
    });
}

function applyTemplate() {
    const t = pickedTemplate;
    if (!t) return;
    const values = {};
    document.querySelectorAll('#templateFields input').forEach(i => {
        const dflt = t.fields.find(f => f[0] === i.dataset.key)[2];
        values[i.dataset.key] = i.value.trim() || dflt;   // an empty field keeps the example text
    });
    if (hasDesignWork() && !confirm('Replace your current design with the "' + t.name + '" template?')) return;
    if (!loadDesign(fitTemplate(t.build(values), state.apparelType))) { showToast('Could not apply that template.', 'error'); return; }
    closeTemplates();
    showToast('Template applied! Everything on it can be moved, resized or deleted.', 'success');
}

document.getElementById('templateModal').addEventListener('click', e => {
    if (e.target.id === 'templateModal') closeTemplates();   // backdrop
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && !document.getElementById('templateModal').hidden) closeTemplates();
});

// ===== Saving the design: drafts and "Edit" from My Designs =====
// A snapshot is plain JSON: apparel, colour, both sides of each partner
// (drawing as a PNG data URL, elements as data), sleeves, logo and the order
// fields. Images are referenced by URL (uploads/design_assets/...) or are the
// small built-in stamp SVGs, so a snapshot stays small.
const DRAFT_KEY = 'tp-design-draft-<?php echo (int) $_SESSION['user_id']; ?>';

// Only images the studio itself produces may come back from a snapshot.
function safeImageSrc(src) {
    src = String(src || '');
    if (/^uploads\/design_assets\/[\w.-]+$/.test(src)) return src;
    if (/^data:image\/(png|jpeg|webp|svg\+xml)[;,]/.test(src)) return src;
    return null;
}

function serializeElement(el) {
    const d = {
        type: el.dataset.type,
        left: parseFloat(el.style.left) || 0,
        top: parseFloat(el.style.top) || 0,
        rot: parseFloat(el.dataset.rotation) || 0
    };
    if (d.type === 'text') {
        const s = el.querySelector('span');
        d.text = s.textContent;
        d.style = { fontFamily: s.style.fontFamily, fontSize: s.style.fontSize, color: s.style.color,
                    fontWeight: s.style.fontWeight, fontStyle: s.style.fontStyle, width: s.style.width };
    } else {
        const img = el.querySelector('img');
        d.src = img.getAttribute('src');
        d.w = img.style.width;
        d.h = img.style.height;
        if (el.dataset.stamp) d.stamp = el.dataset.stamp;
        if (el.dataset.label) d.label = el.dataset.label;
    }
    return d;
}

// Rebuilds a draggable element exactly like addTextToCanvas / addImageElementFromUrl do.
function elementFromData(d) {
    if (!d || (d.type !== 'text' && d.type !== 'image')) return null;
    const el = document.createElement('div');
    el.className = 'draggable-element';
    el.style.pointerEvents = 'auto';
    el.dataset.type = d.type;
    let content;
    if (d.type === 'text') {
        content = document.createElement('span');
        content.textContent = String(d.text || '').slice(0, 200);
        const s = d.style || {};
        // Assigned property by property: the browser drops anything invalid.
        content.style.fontFamily = s.fontFamily || 'Arial';
        content.style.fontSize = s.fontSize || '24px';
        content.style.color = s.color || '#000000';
        content.style.fontWeight = s.fontWeight || '';
        content.style.fontStyle = s.fontStyle || '';
        if (s.width) content.style.width = s.width;
        content.style.whiteSpace = 'nowrap';
        content.style.userSelect = 'none';
    } else {
        const src = safeImageSrc(d.src);
        if (!src) return null;
        content = document.createElement('img');
        content.src = src;
        content.style.width = d.w;
        content.style.height = d.h;
        content.style.display = 'block';
        content.style.userSelect = 'none';
        content.style.pointerEvents = 'none';
        if (d.stamp && ANIMAL_STAMPS[d.stamp]) el.dataset.stamp = d.stamp;
        if (d.label) el.dataset.label = String(d.label).slice(0, 60);
    }
    el.appendChild(content);
    el.insertAdjacentHTML('beforeend', '<div class="rotate-handle" title="Rotate"></div><div class="resize-handle"></div><div class="delete-handle">&times;</div>');
    el.style.left = (Number(d.left) || 0) + 'px';
    el.style.top = (Number(d.top) || 0) + 'px';
    if (Number(d.rot)) {
        el.dataset.rotation = Number(d.rot);
        el.style.transform = 'rotate(' + Number(d.rot) + 'deg)';
    }
    setupDraggable(el);
    return el;
}

function serializeItems(items) {
    return items.map(it => ({ kind: it.kind, emoji: !!it.emoji, text: it.text, color: it.color,
                              src: it.src, size: it.size, x: it.x, y: it.y, rot: it.rot }));
}

function itemsFromData(list) {
    const num = (v, lo, hi, dflt) => { v = Number(v); return isFinite(v) ? Math.max(lo, Math.min(hi, v)) : dflt; };
    return (Array.isArray(list) ? list : []).slice(0, MAX_SLEEVE_ITEMS).map(d => {
        const it = { x: num(d.x, 0, SLEEVE_SIZE, SLEEVE_SIZE / 2), y: num(d.y, 0, SLEEVE_SIZE, SLEEVE_SIZE / 2),
                     rot: num(d.rot, -180, 180, 0), size: num(d.size, 16, 280, 100) };
        if (d.kind === 'img') {
            const src = safeImageSrc(d.src);
            return src ? Object.assign(it, { kind: 'img', src, img: sleeveImage(src) }) : null;
        }
        const color = /^#[0-9A-Fa-f]{6}$/.test(d.color) ? d.color : '#000000';
        return Object.assign(it, { kind: 'text', emoji: !!d.emoji, text: String(d.text || '').slice(0, 40), color });
    }).filter(Boolean);
}

function snapshotDesign() {
    saveCurrentSideData();
    const sideJson = (b, side) => ({
        canvas: b[side + 'Ink'] ? b[side + 'CanvasData'] : null,
        elements: (b[side + 'Elements'] || []).map(serializeElement)
    });
    const bundleJson = b => b ? { front: sideJson(b, 'front'), back: sideJson(b, 'back') } : null;
    const partners = { A: null, B: null };
    partners[state.currentPartner] = bundleJson(snapshotBundle());
    ['A', 'B'].forEach(p => { if (p !== state.currentPartner) partners[p] = bundleJson(state.partners[p]); });
    return {
        v: 1,
        savedAt: Date.now(),
        type: state.apparelType,
        color: state.apparelColor,
        partners,
        sleeves: { A: { left: serializeItems(state.sleeves.A.left), right: serializeItems(state.sleeves.A.right) },
                   B: { left: serializeItems(state.sleeves.B.left), right: serializeItems(state.sleeves.B.right) } },
        logo: serializeItems(state.logo),
        form: {
            size: document.getElementById('sizeSelect')?.value,
            qty: document.getElementById('quantityInput')?.value,
            printSize: document.getElementById('printSizeSelect')?.value,
            notes: document.getElementById('designNotes')?.value
        }
    };
}

/** Puts a snapshot back into the studio. False when it is not usable. */
function loadDesign(snap) {
    if (!snap || snap.v !== 1 || !APPAREL_CONFIG[snap.type]) return false;
    closeSleeveEditor();
    setApparelType(snap.type, true);   // a clean slate for that apparel
    if (/^#[0-9A-Fa-f]{6}$/.test(snap.color || '')) {
        setApparelColor(document.querySelector('.apparel-color-btn[data-color="' + snap.color.toUpperCase() + '"]'), snap.color.toUpperCase());
    }

    const canvasData = v => (typeof v === 'string' && v.startsWith('data:image/png;base64,')) ? v : null;
    const toBundle = j => {
        if (!j) return null;
        const f = canvasData(j.front && j.front.canvas), b = canvasData(j.back && j.back.canvas);
        return {
            frontCanvasData: f, backCanvasData: b,
            frontElements: ((j.front && j.front.elements) || []).map(elementFromData).filter(Boolean),
            backElements: ((j.back && j.back.elements) || []).map(elementFromData).filter(Boolean),
            // First undo step = the loaded side, drawing and elements.
            frontUndoStack: [{ c: f, e: (j.front && j.front.elements) || [] }],
            backUndoStack: [{ c: b, e: (j.back && j.back.elements) || [] }],
            frontRedoStack: [], backRedoStack: [],
            frontInk: !!f, backInk: !!b
        };
    };
    const P = snap.partners || {};
    applyBundle(toBundle(P.A) || {});
    if (isCouple(snap.type)) state.partners.B = toBundle(P.B);

    const S = snap.sleeves || {};
    if (hasSleeves()) {
        ['A', 'B'].forEach(p => {
            if (p === 'B' && !isCouple(snap.type)) return;
            const s = S[p] || {};
            state.sleeves[p] = { left: itemsFromData(s.left), right: itemsFromData(s.right) };
        });
    }
    if (hasLogo()) state.logo = itemsFromData(snap.logo);

    const F = snap.form || {};
    const setSelect = (id, v) => {
        const el = document.getElementById(id);
        if (el && [...el.options].some(o => o.value === v)) el.value = v;
    };
    setSelect('sizeSelect', F.size);
    setSelect('printSizeSelect', F.printSize);
    const qty = document.getElementById('quantityInput');
    if (qty) qty.value = Math.max(1, Math.min(100, parseInt(F.qty, 10) || 1));
    const notes = document.getElementById('designNotes');
    if (notes && typeof F.notes === 'string') notes.value = F.notes.slice(0, 1000);

    restoreSideData('front');   // partner A's front into the editor
    updateMockup();
    updatePreview();
    return true;
}

// --- Drafts: the design is kept in this browser while you work on it ---
let draftReady = false;   // false until a waiting draft is restored or dismissed
let draftTimer = 0;
// At most a second after a change, even while changes keep coming (the save
// takes whatever the design is at that moment).
function scheduleDraftSave() {
    if (!draftReady || draftTimer) return;
    draftTimer = setTimeout(() => { draftTimer = 0; saveDraft(); }, 1000);
}
// Leaving or hiding the page saves at once, so the last change is never lost.
window.addEventListener('pagehide', () => { if (draftReady && draftTimer) saveDraft(); });
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden' && draftReady && draftTimer) saveDraft();
});

function saveDraft() {
    try {
        if (!hasDesignWork()) { localStorage.removeItem(DRAFT_KEY); return; }
        localStorage.setItem(DRAFT_KEY, JSON.stringify(snapshotDesign()));
    } catch (e) { /* storage full or blocked: a draft is a convenience, not essential */ }
}

function readDraft() {
    try {
        const d = JSON.parse(localStorage.getItem(DRAFT_KEY) || 'null');
        return d && d.v === 1 && APPAREL_CONFIG[d.type] ? d : null;
    } catch (e) { return null; }
}

function clearDraft() {
    clearTimeout(draftTimer);
    draftTimer = 0;
    try { localStorage.removeItem(DRAFT_KEY); } catch (e) {}
}

function restoreDraft() {
    const d = readDraft();
    document.getElementById('draftBanner').hidden = true;
    if (d && loadDesign(d)) showToast('Your design is back!', 'success');
    draftReady = true;
}

function discardDraft() {
    clearDraft();
    document.getElementById('draftBanner').hidden = true;
    draftReady = true;
}

// Before leaving the page for "Edit" on another saved design.
function confirmReplaceDesign() {
    return !hasDesignWork() || confirm('Open this saved design? It will replace what is on the canvas now.');
}

// On load: open a saved design (?edit=ID from My Designs), or offer the draft.
function startDraftsAndEditing() {
    const editId = parseInt(new URLSearchParams(location.search).get('edit'), 10);
    if (editId > 0) {
        fetch('includes/custom-design-ajax.php?action=editor&id=' + editId)
            .then(r => r.json())
            .then(data => {
                if (data.success && loadDesign(data.editor)) {
                    showToast('Design loaded. Submitting it saves a new copy; the original stays as it is.', 'success');
                } else {
                    showToast(data.message || 'Could not open that design.', 'error');
                }
            })
            .catch(() => showToast('Could not open that design.', 'error'))
            .finally(() => {
                // A refresh should not load it again over your edits.
                history.replaceState(null, '', location.pathname);
                draftReady = true;
            });
        return;
    }
    const d = readDraft();
    if (!d) { draftReady = true; return; }
    document.getElementById('draftTime').textContent = new Date(d.savedAt).toLocaleString();
    document.getElementById('draftBanner').hidden = false;
}

// Init on page load
document.addEventListener('DOMContentLoaded', function() {
    renderStampPalette();
    renderSleevePalettes();
    init();
    init3DPreview();
    updatePreview();
    calculatePrice();
    refreshViewToggle();   // in case the 3D module loaded first
    designToolReady = true;
    startDraftsAndEditing();
    // "Browse Templates" on the home page links here with ?templates=1.
    if (new URLSearchParams(location.search).get('templates') === '1') {
        openTemplates();
        history.replaceState(null, '', location.pathname);   // a refresh should not reopen it
    }
    // Order fields are part of the draft too.
    ['designNotes', 'sizeSelect', 'quantityInput', 'printSizeSelect'].forEach(id =>
        document.getElementById(id)?.addEventListener('input', scheduleDraftSave));
});
</script>

<!-- three.js r169 for the 3D preview, served locally from js/lib/three (no
     CDN, so it works on a poor connection). If it cannot load, the module
     never runs, window.Design3D stays undefined and the flat preview is used. -->
<script type="importmap">
{
    "imports": {
        "three": "./js/lib/three/build/three.module.js",
        "three/addons/": "./js/lib/three/examples/jsm/"
    }
}
</script>
<script type="module" async src="js/design-3d.js?v=<?php echo @filemtime(__DIR__ . '/js/design-3d.js'); ?>"></script>

<!--
    CHATBOT MOUNT POINT
    The site chatbot is intentionally NOT built here. When integrating it, mount
    the chatbot widget below (or include its partial). Do not block the design
    tool's scripts above. Example:
    <?php /* include 'includes/chatbot/chatbot-widget.php'; */ ?>
-->

<?php include 'includes/footer/footer.php'; ?>
