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
    $migrationSQL = file_get_contents(__DIR__ . '/migrate_custom_designs.sql');
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
    .mockup-container {
        width: 300px;
        height: 380px;
    }
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

/* ===== AI Design Suggestion ===== */
.ai-suggest-input {
    display: flex;
    gap: 0.4rem;
}
.ai-suggest-input input {
    flex: 1;
    padding: 0.5rem 0.7rem;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 0.82rem;
}
.ai-suggest-input button {
    padding: 0.5rem 0.7rem;
    border: none;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.8rem;
    white-space: nowrap;
    transition: all 0.2s;
}
.ai-suggest-input button:hover { opacity: 0.9; transform: translateY(-1px); }
.ai-suggest-input button:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

.ai-suggestions-list {
    margin-top: 0.6rem;
    max-height: 300px;
    overflow-y: auto;
}
.ai-suggestion-card {
    background: linear-gradient(135deg, #f8f9ff 0%, #f0f0ff 100%);
    border: 1px solid #e0e0f0;
    border-radius: 10px;
    padding: 0.65rem;
    margin-bottom: 0.5rem;
    cursor: pointer;
    transition: all 0.2s;
}
.ai-suggestion-card:hover {
    border-color: #667eea;
    box-shadow: 0 2px 8px rgba(102,126,234,0.15);
}
.ai-suggestion-card h6 {
    font-size: 0.78rem;
    font-weight: 700;
    color: #4a4a8a;
    margin-bottom: 0.3rem;
}
.ai-suggestion-card p {
    font-size: 0.72rem;
    color: #666;
    margin: 0 0 0.3rem;
    line-height: 1.4;
}
.ai-suggestion-colors {
    display: flex;
    gap: 4px;
    margin-bottom: 0.25rem;
}
.ai-suggestion-colors span {
    width: 18px;
    height: 18px;
    border-radius: 50%;
    border: 1px solid #ccc;
    display: inline-block;
    cursor: pointer;
    transition: transform 0.15s;
}
.ai-suggestion-colors span:hover { transform: scale(1.3); }
.ai-suggestion-tip {
    font-size: 0.68rem;
    color: #8888aa;
    font-style: italic;
}
.ai-apply-btn {
    display: block;
    width: 100%;
    margin-top: 0.5rem;
    padding: 0.35rem 0.6rem;
    background: linear-gradient(135deg, #6C63FF, #FF6584);
    color: #fff;
    border: none;
    border-radius: 6px;
    font-size: 0.72rem;
    font-weight: 600;
    cursor: pointer;
    transition: opacity 0.2s, transform 0.15s;
}
.ai-apply-btn:hover { opacity: 0.9; transform: translateY(-1px); }
.ai-apply-btn:active { transform: scale(0.97); }
.ai-loading {
    text-align: center;
    padding: 1rem;
    color: #888;
}
.ai-loading i { animation: spin 1s linear infinite; }

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
    z-index: 4;
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
                    <span style="color:#ccc; font-size:0.8rem;">|</span>
                    <button class="canvas-action-btn" onclick="zoomIn()" title="Zoom In"><i class="fas fa-search-plus"></i></button>
                    <button class="canvas-action-btn" onclick="zoomOut()" title="Zoom Out"><i class="fas fa-search-minus"></i></button>
                    <button class="canvas-action-btn" onclick="resetZoom()" title="Reset View"><i class="fas fa-expand"></i></button>
                </div>
                <div class="canvas-toolbar-right">
                    <button class="canvas-action-btn" onclick="deleteSelected()" title="Delete Selected"><i class="fas fa-trash"></i></button>
                    <button class="canvas-action-btn danger" onclick="clearCanvas()" title="Clear All"><i class="fas fa-times"></i> Clear</button>
                </div>
            </div>
            <div class="canvas-wrapper" id="canvasWrapper">
                <div class="mockup-container" id="mockupContainer">
                    <!-- SVG Mockup will be drawn here -->
                    <svg class="mockup-svg" id="mockupSvg" viewBox="0 0 400 500" xmlns="http://www.w3.org/2000/svg"></svg>
                    <canvas id="designCanvas" width="200" height="240"></canvas>
                    <!-- Draggable elements layer -->
                    <div id="elementsLayer" style="position:absolute; top:0; left:0; width:100%; height:100%; z-index:5; pointer-events:none;"></div>
                    <!-- Corporate logo placement guide (shown only for corporate wear) -->
                    <div id="logoZoneGuide" class="logo-zone-guide" style="display:none;">
                        <span>LOGO</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Panel -->
        <div class="design-preview-panel">
            <!-- 3D Apparel Preview -->
            <div class="preview-section">
                <h6 id="previewHeading"><i class="fas fa-cube me-1"></i> Live Preview</h6>
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
    pendingStampSize: 90
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
    updateMockup();
    positionCanvas();
    setupCanvasEvents();
    loadMyDesigns();
    saveCanvasState();
}

function getContrastStroke(hexColor) {
    const r = parseInt(hexColor.slice(1,3), 16);
    const g = parseInt(hexColor.slice(3,5), 16);
    const b = parseInt(hexColor.slice(5,7), 16);
    const brightness = (r * 299 + g * 587 + b * 114) / 1000;
    if (brightness < 128) {
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
    } else {
        guide.style.display = 'none';
    }
}

function setApparelType(type) {
    if (!APPAREL_CONFIG[type]) return;
    state.apparelType = type;
    document.querySelectorAll('.apparel-type-btn').forEach(b => b.classList.remove('active'));
    const typeBtn = document.querySelector(`.apparel-type-btn[data-type="${type}"]`);
    if (typeBtn) typeBtn.classList.add('active');

    document.getElementById('infoType').textContent = configOf(type).label;

    // Reset both sides when changing apparel type
    state.frontCanvasData = null;
    state.backCanvasData = null;
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
    const partnerToggle = document.getElementById('partnerToggle');
    if (partnerToggle) partnerToggle.style.display = couple ? 'inline-flex' : 'none';
    document.getElementById('partnerABtn')?.classList.add('active');
    document.getElementById('partnerBBtn')?.classList.remove('active');

    updateMockup();
    positionCanvas();
    saveCanvasState();
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
        backRedoStack: state.backRedoStack
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
    return {
        offsetX: touch.clientX - rect.left,
        offsetY: touch.clientY - rect.top,
        preventDefault: () => {}
    };
}

function startDrawing(e) {
    state.isDrawing = true;
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

function draw(e) {
    if (!state.isDrawing) return;
    
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
    
    document.getElementById('textInput').value = '';
    updatePreview();
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
function addImageElementFromUrl(url) {
    const img = new Image();
    img.onload = function() {
        const area = areaOf(state.apparelType);
        const maxW = area.w - 20;
        const maxH = area.h - 20;
        let w = img.width;
        let h = img.height;
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
        el.style.left = (area.x + 10) + 'px';
        el.style.top = (area.y + 10) + 'px';
        el.dataset.type = 'image';

        setupDraggable(el);
        elementsLayer.appendChild(el);
        state.elements.push(el);
        updateElementCount();
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
            addImageElementFromUrl(data.url);
            showToast('AI design added! Drag, resize, or rotate it to fit.', 'success');
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
    updatePreview();
}

// ===== Drag & Resize =====
function setupDraggable(el) {
    let isDragging = false;
    let isResizing = false;
    let isRotating = false;
    let startX, startY, startLeft, startTop, startW, startH;
    let cx, cy, startAngle, startRotation;

    el.addEventListener('mousedown', function(e) {
        if (e.target.classList.contains('delete-handle')) {
            el.remove();
            state.elements = state.elements.filter(item => item !== el);
            if (state.selectedElement === el) state.selectedElement = null;
            updateElementCount();
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
            startX = e.clientX;
            startY = e.clientY;
        } else {
            isDragging = true;
            startX = e.clientX;
            startY = e.clientY;
            startLeft = el.offsetLeft;
            startTop = el.offsetTop;
        }

        e.stopPropagation();
    });

    document.addEventListener('mousemove', function(e) {
        if (isDragging) {
            el.style.left = (startLeft + (e.clientX - startX)) + 'px';
            el.style.top = (startTop + (e.clientY - startY)) + 'px';
            updatePreview();
        }
        if (isResizing) {
            const content = el.querySelector('img, span');
            const newW = Math.max(20, startW + (e.clientX - startX));
            const ratio = newW / startW;
            content.style.width = newW + 'px';
            if (content.tagName === 'IMG') {
                content.style.height = (startH * ratio) + 'px';
            } else {
                content.style.fontSize = (parseFloat(content.style.fontSize) * ratio) + 'px';
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

    document.addEventListener('mouseup', function() {
        isDragging = false;
        isResizing = false;
        isRotating = false;
    });
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
        updatePreview();
    }
}

// ===== Undo / Redo =====
function saveCanvasState() {
    state.undoStack.push(canvas.toDataURL());
    if (state.undoStack.length > 30) state.undoStack.shift();
    state.redoStack = [];
}

function restoreCanvasState() {
    if (state.undoStack.length > 0) {
        const img = new Image();
        img.onload = function() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.drawImage(img, 0, 0);
        };
        img.src = state.undoStack[state.undoStack.length - 1];
    }
}

function undoAction() {
    if (state.undoStack.length > 1) {
        state.redoStack.push(state.undoStack.pop());
        const img = new Image();
        img.onload = function() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.drawImage(img, 0, 0);
            updatePreview();
        };
        img.src = state.undoStack[state.undoStack.length - 1];
    }
}

function redoAction() {
    if (state.redoStack.length > 0) {
        const data = state.redoStack.pop();
        state.undoStack.push(data);
        const img = new Image();
        img.onload = function() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.drawImage(img, 0, 0);
            updatePreview();
        };
        img.src = data;
    }
}

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
    mockupContainer.style.transform = `scale(${state.zoom})`;
}

function zoomOut() {
    state.zoom = Math.max(state.zoom - 0.1, 0.5);
    mockupContainer.style.transform = `scale(${state.zoom})`;
}

function resetZoom() {
    state.zoom = 1;
    mockupContainer.style.transform = 'scale(1)';
}

// ===== Live Preview =====
function updatePreview() {
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
        return;
    }

    if (single) single.style.display = '';
    if (couple) couple.style.display = 'none';
    if (heading) heading.innerHTML = '<i class="fas fa-cube me-1"></i> Live Preview';

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
    if (ca) renderGarmentToContext(ca.getContext('2d'), 0, 0, ca.width, ca.height, bundleA, 'front', previewBg());
    if (cb) renderGarmentToContext(cb.getContext('2d'), 0, 0, cb.width, cb.height, bundleB, 'front', previewBg());
}

// Draws a full garment composite (shape + saved drawing + saved elements) for the
// given bundle/side into a target context at (ox,oy) scaled to w×h. Returns a
// Promise that resolves once all async image draws complete (used by submit).
function renderGarmentToContext(pCtx, ox, oy, w, h, bundle, side, bg) {
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

        const area = designAreas[state.apparelType];

        if (side === state.currentSide) {
            // Active side: draw directly from the live canvas and elements
            pCtx.drawImage(canvas, area.x, area.y, area.w, area.h);
            renderElementsToCanvas(pCtx);
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
                };
                savedImg.src = savedData;
            } else if (savedElements && savedElements.length > 0) {
                renderSavedElementsToCanvas(pCtx, savedElements);
            }
        }
    };
    svgImg.src = svgUrl;
}

// Draws one design element (text or image) onto a canvas context, honoring its
// position and rotation. Shared by the live-canvas and per-side exporters so the
// submitted order matches exactly what the user sees.
function drawDesignElement(targetCtx, el) {
    const left = parseInt(el.style.left) || 0;
    const top = parseInt(el.style.top) || 0;
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
                : 'Image';
            thumb = img ? '<img src="' + img.src + '" alt="">' : '<i class="fas fa-image"></i>';
        }
        const sel = (el === state.selectedElement) ? ' selected' : '';
        html += '<div class="layer-row' + sel + '" data-index="' + i + '">'
            +   '<span class="layer-thumb">' + thumb + '</span>'
            +   '<span class="layer-label">' + escapeHtml(label) + '</span>'
            +   '<span class="layer-actions">'
            +     '<button type="button" title="Bring forward" onclick="moveLayer(' + i + ', 1)"><i class="fas fa-chevron-up"></i></button>'
            +     '<button type="button" title="Send back" onclick="moveLayer(' + i + ', -1)"><i class="fas fa-chevron-down"></i></button>'
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
            formData.append('notes', notes);
            const priceData = {
                apparelColor: state.apparelColor,
                apparelType: state.apparelType,
                elementsCount: state.elements.length,
                printSize: document.getElementById('printSizeSelect')?.value || 'medium',
                colorsUsed: countColorsUsed(),
                size: document.getElementById('sizeSelect')?.value || 'M',
                quantity: parseInt(document.getElementById('quantityInput')?.value) || 1,
                discountType: document.getElementById('discountSelect')?.value || 'regular',
                baseCost: pricing.base[state.apparelType] || 350,
                printSizeCost: pricing.printSize[document.getElementById('printSizeSelect')?.value || 'medium'] || 100,
                colorCost: Math.max(0, (countColorsUsed() - 1)) * pricing.colorCost
            };

            formData.append('design_data', JSON.stringify(priceData));
            
            fetch('includes/custom-design-ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showToast('Design submitted! Redirecting to order summary...', 'success');
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
        
        const area = designAreas[state.apparelType];
        
        if (side === state.currentSide) {
            // Active side: use live canvas
            fctx.drawImage(canvas, area.x, area.y, area.w, area.h);
            renderElementsToCanvas(fctx);
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
                    callback(finalCanvas.toDataURL('image/png'));
                };
                savedImg.src = savedData;
            } else {
                if (savedElements && savedElements.length > 0) {
                    renderSavedElementsToCanvas(fctx, savedElements);
                }
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
    const area = designAreas[state.apparelType];
    if (!area) { callback(''); return; }

    const out = document.createElement('canvas');
    out.width  = Math.round(area.w * PRINT_SCALE);
    out.height = Math.round(area.h * PRINT_SCALE);
    const octx = out.getContext('2d');

    // No fill: the canvas starts transparent, which is what printing needs.
    octx.setTransform(PRINT_SCALE, 0, 0, PRINT_SCALE, -area.x * PRINT_SCALE, -area.y * PRINT_SCALE);

    const finish = () => {
        octx.setTransform(1, 0, 0, 1, 0, 0);
        // A blank print file is worse than none: the admin would download an
        // empty PNG and think the artwork was lost.
        callback(printCanvasHasInk(out) ? out.toDataURL('image/png') : '');
    };

    const drawLayerThen = (next) => {
        const savedData = side === 'front' ? state.frontCanvasData : state.backCanvasData;
        if (side === state.currentSide) {
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
        const els = side === state.currentSide
            ? state.elements
            : (side === 'front' ? state.frontElements : state.backElements);
        if (els && els.length) {
            els.forEach(el => drawDesignElement(octx, el));
        }
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
        renderGarmentToContext(fctx, 0, 0, 400, 500, bundleA, side),
        renderGarmentToContext(fctx, 400, 0, 400, 500, bundleB, side)
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
                <small class="design-date">Saved ${new Date(d.created_at).toLocaleDateString()}</small>

                ${orderable
                    ? `<a href="${orderUrl}" class="btn btn-sm w-100 mt-2 design-order-btn">
                           <i class="fas fa-shopping-cart"></i> ${actionLabel}
                       </a>`
                    : `<div class="design-blocked mt-2">${st === 'cancelled'
                            ? 'This design was cancelled and can no longer be ordered.'
                            : 'Waiting for changes before this can be ordered.'}</div>`}
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

// ===== AI Design Suggestions =====
function getAISuggestions() {
    const prompt = document.getElementById('aiPromptInput').value.trim();
    if (!prompt) {
        showToast('Please describe your design idea first.', 'error');
        return;
    }

    const btn = document.getElementById('aiSuggestBtn');
    const container = document.getElementById('aiSuggestionsContainer');

    btn.disabled = true;
    container.innerHTML = '<div class="ai-loading"><i class="fas fa-spinner fa-spin"></i><p style="font-size:0.78rem; margin-top:0.4rem;">Generating ideas...</p></div>';

    fetch('includes/design-ai-suggest.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ prompt: prompt, apparel_type: state.apparelType })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.suggestions) {
            renderAISuggestions(data.suggestions);
        } else {
            container.innerHTML = `<p style="font-size:0.78rem; color:#c0392b; margin-top:0.5rem;">${escapeHtml(data.error || 'Failed to generate suggestions.')}</p>`;
        }
    })
    .catch(() => {
        container.innerHTML = '<p style="font-size:0.78rem; color:#c0392b; margin-top:0.5rem;">Network error. Please try again.</p>';
    })
    .finally(() => {
        btn.disabled = false;
    });
}

function renderAISuggestions(suggestions) {
    const container = document.getElementById('aiSuggestionsContainer');
    container.innerHTML = '<div class="ai-suggestions-list">' + suggestions.map((s, i) => `
        <div class="ai-suggestion-card" onclick="applyAISuggestion(${i})">
            <h6><i class="fas fa-lightbulb me-1" style="color:#f0c040;"></i>${escapeHtml(s.name || 'Suggestion ' + (i+1))}</h6>
            <p>${escapeHtml(s.description || '')}</p>
            ${s.colors && s.colors.length ? `
                <div class="ai-suggestion-colors">
                    ${s.colors.map(c => `<span style="background:${escapeHtml(c)}" title="${escapeHtml(c)}" onclick="event.stopPropagation(); setBrushColor(null, '${escapeHtml(c)}')"></span>`).join('')}
                </div>
            ` : ''}
            ${s.placement ? `<p style="font-size:0.7rem; margin:0;"><strong>Placement:</strong> ${escapeHtml(s.placement)}</p>` : ''}
            ${s.tip ? `<div class="ai-suggestion-tip"><i class="fas fa-star me-1"></i>${escapeHtml(s.tip)}</div>` : ''}
            <button class="ai-apply-btn" onclick="event.stopPropagation(); applyAISuggestion(${i})">
                <i class="fas fa-paint-brush me-1"></i> Apply Design
            </button>
        </div>
    `).join('') + '</div>';

    // Store suggestions for applying
    window._aiSuggestions = suggestions;
}

function applyAISuggestion(index) {
    const s = window._aiSuggestions?.[index];
    if (!s) return;

    // Apply first suggested color as brush color
    if (s.colors && s.colors.length > 0) {
        setBrushColor(null, s.colors[0]);
        document.getElementById('customColor').value = s.colors[0];
    }

    // Auto-draw the design on canvas
    autoDrawDesign(s);

    showToast(`Applied "${s.name}" design to canvas!`, 'success');
}

// ===== Auto Design Drawing Engine =====
function autoDrawDesign(suggestion) {
    const colors = suggestion.colors || ['#333333', '#FFFFFF'];
    const name = (suggestion.name || '').toLowerCase();
    const desc = (suggestion.description || '').toLowerCase();
    const w = canvas.width;
    const h = canvas.height;

    ctx.save();

    // Detect theme from suggestion name/description keywords
    const theme = detectDesignTheme(name, desc);

    // Draw based on detected theme
    switch(theme) {
        case 'floral':     drawFloralDesign(colors, w, h); break;
        case 'geometric':  drawGeometricDesign(colors, w, h); break;
        case 'minimalist': drawMinimalistDesign(colors, w, h); break;
        case 'vintage':    drawVintageDesign(colors, w, h); break;
        case 'abstract':   drawAbstractDesign(colors, w, h); break;
        case 'streetwear': drawStreetwearDesign(colors, w, h); break;
        case 'nature':     drawNatureDesign(colors, w, h); break;
        case 'typography': drawTypographyDesign(colors, w, h, suggestion.name); break;
        default:           drawDefaultDesign(colors, w, h, suggestion.name); break;
    }

    ctx.restore();
    saveCanvasState();
    updatePreview();
}

function detectDesignTheme(name, desc) {
    const text = name + ' ' + desc;
    const themes = {
        'floral':     ['floral', 'flower', 'bloom', 'botanical', 'petal', 'garden', 'rose', 'tropical', 'leaf', 'leaves'],
        'geometric':  ['geometric', 'geometry', 'prism', 'grid', 'polygon', 'triangle', 'hexagon', 'sacred', 'shape'],
        'minimalist': ['minimalist', 'minimal', 'clean line', 'negative space', 'simple', 'subtle'],
        'vintage':    ['vintage', 'retro', 'badge', 'faded', '70s', '80s', 'old school', 'classic', 'nostalgic', 'sunset'],
        'abstract':   ['abstract', 'splash', 'ink blot', 'color block', 'paint', 'watercolor', 'expressionist'],
        'streetwear': ['street', 'urban', 'graffiti', 'glitch', 'tag', 'hype', 'edge', 'drip'],
        'nature':     ['mountain', 'ocean', 'wave', 'forest', 'tree', 'wild', 'nature', 'outdoor', 'adventure', 'sea'],
        'typography': ['type only', 'typograph', 'lettering', 'font', 'text', 'statement', 'word'],
    };
    for (const [theme, keywords] of Object.entries(themes)) {
        for (const kw of keywords) {
            if (text.includes(kw)) return theme;
        }
    }
    return 'default';
}

// --- Floral Design ---
function drawFloralDesign(colors, w, h) {
    const cx = w / 2, cy = h / 2 - 10;

    // Draw main flower
    drawFlower(cx, cy, 28, 6, colors[0], colors[1] || '#FFFFFF');

    // Smaller accent flowers
    drawFlower(cx - 40, cy - 30, 14, 5, colors[2] || colors[0], colors[1] || '#FFFFFF');
    drawFlower(cx + 38, cy - 25, 12, 5, colors[2] || colors[0], colors[1] || '#FFFFFF');
    drawFlower(cx - 25, cy + 40, 10, 5, colors[3] || colors[0], colors[1] || '#FFFFFF');
    drawFlower(cx + 30, cy + 35, 11, 5, colors[2] || colors[0], colors[1] || '#FFFFFF');

    // Stems and leaves
    ctx.strokeStyle = colors[2] || '#3E5C50';
    ctx.lineWidth = 1.5;
    ctx.beginPath();
    ctx.moveTo(cx, cy + 28); ctx.quadraticCurveTo(cx - 10, cy + 60, cx - 5, cy + 80);
    ctx.stroke();
    ctx.moveTo(cx - 40, cy - 16); ctx.quadraticCurveTo(cx - 35, cy + 10, cx - 10, cy + 50);
    ctx.stroke();

    // Leaves
    drawLeaf(cx + 10, cy + 50, 12, 0.3, colors[2] || '#3E5C50');
    drawLeaf(cx - 20, cy + 35, 10, -0.4, colors[2] || '#3E5C50');
}

function drawFlower(x, y, radius, petals, petalColor, centerColor) {
    for (let i = 0; i < petals; i++) {
        const angle = (Math.PI * 2 / petals) * i;
        const px = x + Math.cos(angle) * radius * 0.6;
        const py = y + Math.sin(angle) * radius * 0.6;
        ctx.beginPath();
        ctx.ellipse(px, py, radius * 0.55, radius * 0.3, angle, 0, Math.PI * 2);
        ctx.fillStyle = petalColor;
        ctx.globalAlpha = 0.8;
        ctx.fill();
        ctx.globalAlpha = 1;
    }
    // Center
    ctx.beginPath();
    ctx.arc(x, y, radius * 0.25, 0, Math.PI * 2);
    ctx.fillStyle = centerColor;
    ctx.fill();
}

function drawLeaf(x, y, size, angle, color) {
    ctx.save();
    ctx.translate(x, y);
    ctx.rotate(angle);
    ctx.beginPath();
    ctx.moveTo(0, 0);
    ctx.quadraticCurveTo(size * 0.6, -size * 0.5, size, 0);
    ctx.quadraticCurveTo(size * 0.6, size * 0.5, 0, 0);
    ctx.fillStyle = color;
    ctx.globalAlpha = 0.7;
    ctx.fill();
    ctx.globalAlpha = 1;
    ctx.restore();
}

// --- Geometric Design ---
function drawGeometricDesign(colors, w, h) {
    const cx = w / 2, cy = h / 2 - 10;

    // Outer circle
    ctx.beginPath();
    ctx.arc(cx, cy, 55, 0, Math.PI * 2);
    ctx.strokeStyle = colors[0];
    ctx.lineWidth = 2;
    ctx.stroke();

    // Inner triangles
    for (let i = 0; i < 3; i++) {
        const angle = (Math.PI * 2 / 3) * i - Math.PI / 2;
        ctx.beginPath();
        for (let j = 0; j < 3; j++) {
            const a = angle + (Math.PI * 2 / 3) * j;
            const px = cx + Math.cos(a) * 40;
            const py = cy + Math.sin(a) * 40;
            j === 0 ? ctx.moveTo(px, py) : ctx.lineTo(px, py);
        }
        ctx.closePath();
        ctx.strokeStyle = colors[i % colors.length];
        ctx.lineWidth = 1.5;
        ctx.stroke();
    }

    // Center hexagon
    ctx.beginPath();
    for (let i = 0; i < 6; i++) {
        const a = (Math.PI / 3) * i - Math.PI / 6;
        const px = cx + Math.cos(a) * 18;
        const py = cy + Math.sin(a) * 18;
        i === 0 ? ctx.moveTo(px, py) : ctx.lineTo(px, py);
    }
    ctx.closePath();
    ctx.fillStyle = colors[1] || colors[0];
    ctx.globalAlpha = 0.3;
    ctx.fill();
    ctx.globalAlpha = 1;
    ctx.strokeStyle = colors[0];
    ctx.lineWidth = 1.5;
    ctx.stroke();

    // Radiating lines
    for (let i = 0; i < 12; i++) {
        const a = (Math.PI / 6) * i;
        ctx.beginPath();
        ctx.moveTo(cx + Math.cos(a) * 20, cy + Math.sin(a) * 20);
        ctx.lineTo(cx + Math.cos(a) * 55, cy + Math.sin(a) * 55);
        ctx.strokeStyle = colors[2] || colors[0];
        ctx.globalAlpha = 0.25;
        ctx.lineWidth = 0.8;
        ctx.stroke();
        ctx.globalAlpha = 1;
    }

    // Small dots at intersections
    for (let i = 0; i < 6; i++) {
        const a = (Math.PI / 3) * i;
        ctx.beginPath();
        ctx.arc(cx + Math.cos(a) * 40, cy + Math.sin(a) * 40, 3, 0, Math.PI * 2);
        ctx.fillStyle = colors[1] || '#FFFFFF';
        ctx.fill();
    }
}

// --- Minimalist Design ---
function drawMinimalistDesign(colors, w, h) {
    const cx = w / 2, cy = h / 2 - 20;

    // Simple continuous line drawing — abstract face/shape
    ctx.strokeStyle = colors[0];
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';

    ctx.beginPath();
    ctx.moveTo(cx - 20, cy - 15);
    ctx.quadraticCurveTo(cx - 25, cy - 30, cx - 10, cy - 35);
    ctx.quadraticCurveTo(cx + 5, cy - 40, cx + 15, cy - 30);
    ctx.quadraticCurveTo(cx + 25, cy - 20, cx + 20, cy - 5);
    ctx.quadraticCurveTo(cx + 15, cy + 10, cx, cy + 15);
    ctx.quadraticCurveTo(cx - 15, cy + 20, cx - 20, cy + 5);
    ctx.quadraticCurveTo(cx - 25, cy - 5, cx - 20, cy - 15);
    ctx.stroke();

    // Single accent dot
    if (colors[2]) {
        ctx.beginPath();
        ctx.arc(cx + 5, cy - 20, 4, 0, Math.PI * 2);
        ctx.fillStyle = colors[2];
        ctx.fill();
    }

    // Thin horizontal line below
    ctx.beginPath();
    ctx.moveTo(cx - 35, cy + 40);
    ctx.lineTo(cx + 35, cy + 40);
    ctx.strokeStyle = colors[0];
    ctx.globalAlpha = 0.3;
    ctx.lineWidth = 1;
    ctx.stroke();
    ctx.globalAlpha = 1;
}

// --- Vintage/Retro Design ---
function drawVintageDesign(colors, w, h) {
    const cx = w / 2, cy = h / 2 - 5;

    // Outer badge circle
    ctx.beginPath();
    ctx.arc(cx, cy, 55, 0, Math.PI * 2);
    ctx.strokeStyle = colors[0] || '#2C1810';
    ctx.lineWidth = 2.5;
    ctx.stroke();

    // Inner circle
    ctx.beginPath();
    ctx.arc(cx, cy, 48, 0, Math.PI * 2);
    ctx.strokeStyle = colors[0] || '#2C1810';
    ctx.lineWidth = 1;
    ctx.stroke();

    // Fill inside with subtle color
    ctx.beginPath();
    ctx.arc(cx, cy, 47, 0, Math.PI * 2);
    ctx.fillStyle = colors[2] || '#F5E6D0';
    ctx.globalAlpha = 0.2;
    ctx.fill();
    ctx.globalAlpha = 1;

    // Star at center
    drawStar(cx, cy - 5, 12, 6, 5, colors[0] || '#2C1810');

    // "EST." text on top curve
    ctx.save();
    ctx.font = 'bold 8px Georgia, serif';
    ctx.fillStyle = colors[0] || '#2C1810';
    ctx.textAlign = 'center';
    ctx.fillText('★ EST. 2024 ★', cx, cy - 28);

    // Bottom text
    ctx.font = 'bold 7px Georgia, serif';
    ctx.fillText('PREMIUM QUALITY', cx, cy + 20);

    // Decorative lines
    ctx.beginPath();
    ctx.moveTo(cx - 35, cy + 10); ctx.lineTo(cx - 10, cy + 10);
    ctx.moveTo(cx + 10, cy + 10); ctx.lineTo(cx + 35, cy + 10);
    ctx.strokeStyle = colors[0] || '#2C1810';
    ctx.lineWidth = 1;
    ctx.stroke();
    ctx.restore();
}

function drawStar(cx, cy, outerR, innerR, points, color) {
    ctx.beginPath();
    for (let i = 0; i < points * 2; i++) {
        const r = i % 2 === 0 ? outerR : innerR;
        const a = (Math.PI / points) * i - Math.PI / 2;
        const px = cx + Math.cos(a) * r;
        const py = cy + Math.sin(a) * r;
        i === 0 ? ctx.moveTo(px, py) : ctx.lineTo(px, py);
    }
    ctx.closePath();
    ctx.fillStyle = color;
    ctx.fill();
}

// --- Abstract Design ---
function drawAbstractDesign(colors, w, h) {
    const cx = w / 2, cy = h / 2 - 10;

    // Random paint splashes
    for (let i = 0; i < 8; i++) {
        const x = cx + (Math.random() - 0.5) * 120;
        const y = cy + (Math.random() - 0.5) * 100;
        const r = 8 + Math.random() * 25;
        ctx.beginPath();
        ctx.arc(x, y, r, 0, Math.PI * 2);
        ctx.fillStyle = colors[i % colors.length];
        ctx.globalAlpha = 0.15 + Math.random() * 0.35;
        ctx.fill();
        ctx.globalAlpha = 1;
    }

    // Flowing lines
    for (let l = 0; l < 3; l++) {
        ctx.beginPath();
        ctx.moveTo(cx - 60, cy - 30 + l * 30);
        ctx.bezierCurveTo(
            cx - 20, cy - 50 + l * 25,
            cx + 20, cy + 10 + l * 20,
            cx + 60, cy - 20 + l * 30
        );
        ctx.strokeStyle = colors[l % colors.length];
        ctx.lineWidth = 2;
        ctx.globalAlpha = 0.7;
        ctx.stroke();
        ctx.globalAlpha = 1;
    }

    // Center accent shape
    ctx.beginPath();
    ctx.arc(cx, cy, 15, 0, Math.PI * 2);
    ctx.fillStyle = colors[0];
    ctx.globalAlpha = 0.5;
    ctx.fill();
    ctx.globalAlpha = 1;
}

// --- Streetwear Design ---
function drawStreetwearDesign(colors, w, h) {
    const cx = w / 2, cy = h / 2 - 15;

    // Bold background block
    ctx.fillStyle = colors[0] || '#1A1A1A';
    ctx.globalAlpha = 0.85;
    ctx.fillRect(cx - 55, cy - 40, 110, 70);
    ctx.globalAlpha = 1;

    // "HYPE" text
    ctx.font = 'bold 26px Impact, sans-serif';
    ctx.fillStyle = colors[1] || '#FFFFFF';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText('HYPE', cx, cy - 10);

    // Accent line below
    ctx.fillStyle = colors[2] || '#FF0000';
    ctx.fillRect(cx - 40, cy + 18, 80, 4);

    // Small text
    ctx.font = '7px Arial, sans-serif';
    ctx.fillStyle = colors[1] || '#FFFFFF';
    ctx.fillText('LIMITED EDITION', cx, cy + 35);

    // Glitch effect lines
    ctx.globalAlpha = 0.3;
    ctx.fillStyle = colors[2] || '#FF0000';
    ctx.fillRect(cx - 58, cy - 15, 116, 2);
    ctx.fillStyle = colors[3] || '#00FFFF';
    ctx.fillRect(cx - 53, cy + 5, 106, 1.5);
    ctx.globalAlpha = 1;

    // Corner marks
    ctx.strokeStyle = colors[1] || '#FFFFFF';
    ctx.lineWidth = 1.5;
    // Top-left
    ctx.beginPath(); ctx.moveTo(cx - 55, cy - 32); ctx.lineTo(cx - 55, cy - 40); ctx.lineTo(cx - 47, cy - 40); ctx.stroke();
    // Bottom-right
    ctx.beginPath(); ctx.moveTo(cx + 55, cy + 22); ctx.lineTo(cx + 55, cy + 30); ctx.lineTo(cx + 47, cy + 30); ctx.stroke();
}

// --- Nature / Mountain Design ---
function drawNatureDesign(colors, w, h) {
    const cx = w / 2, cy = h / 2;

    // Circle frame
    ctx.beginPath();
    ctx.arc(cx, cy - 5, 50, 0, Math.PI * 2);
    ctx.strokeStyle = colors[0] || '#2C3E50';
    ctx.lineWidth = 1.5;
    ctx.stroke();

    // Clip to circle
    ctx.save();
    ctx.beginPath();
    ctx.arc(cx, cy - 5, 49, 0, Math.PI * 2);
    ctx.clip();

    // Sky gradient
    const grad = ctx.createLinearGradient(0, cy - 50, 0, cy + 40);
    grad.addColorStop(0, colors[3] || '#F39C12');
    grad.addColorStop(0.4, colors[2] || '#ECF0F1');
    grad.addColorStop(1, colors[0] || '#2C3E50');
    ctx.fillStyle = grad;
    ctx.fillRect(cx - 55, cy - 55, 110, 100);

    // Sun
    ctx.beginPath();
    ctx.arc(cx, cy - 30, 12, 0, Math.PI * 2);
    ctx.fillStyle = colors[3] || '#F39C12';
    ctx.globalAlpha = 0.8;
    ctx.fill();
    ctx.globalAlpha = 1;

    // Mountains
    ctx.beginPath();
    ctx.moveTo(cx - 55, cy + 20);
    ctx.lineTo(cx - 20, cy - 20);
    ctx.lineTo(cx + 5, cy + 5);
    ctx.lineTo(cx + 25, cy - 15);
    ctx.lineTo(cx + 55, cy + 20);
    ctx.closePath();
    ctx.fillStyle = colors[0] || '#2C3E50';
    ctx.fill();

    // Snow caps
    ctx.beginPath();
    ctx.moveTo(cx - 25, cy - 14);
    ctx.lineTo(cx - 20, cy - 20);
    ctx.lineTo(cx - 15, cy - 14);
    ctx.closePath();
    ctx.fillStyle = '#FFFFFF';
    ctx.globalAlpha = 0.7;
    ctx.fill();
    ctx.globalAlpha = 1;

    ctx.restore();

    // Text below
    ctx.font = '7px Arial, sans-serif';
    ctx.fillStyle = colors[0] || '#2C3E50';
    ctx.textAlign = 'center';
    ctx.fillText('ADVENTURE AWAITS', cx, cy + 55);
}

// --- Typography Design ---
function drawTypographyDesign(colors, w, h, title) {
    const cx = w / 2, cy = h / 2 - 10;
    const word = (title || 'CREATE').toUpperCase().split(' ')[0];

    // Main large text
    ctx.font = 'bold 32px Impact, sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';

    // Shadow
    ctx.fillStyle = colors[1] || '#CCCCCC';
    ctx.globalAlpha = 0.3;
    ctx.fillText(word, cx + 2, cy + 2);
    ctx.globalAlpha = 1;

    // Main text
    ctx.fillStyle = colors[0] || '#333333';
    ctx.fillText(word, cx, cy);

    // Accent underline
    const textW = ctx.measureText(word).width;
    ctx.fillStyle = colors[2] || '#E74C3C';
    ctx.fillRect(cx - textW / 2, cy + 20, textW, 3);

    // Small decorative text
    ctx.font = '7px Arial, sans-serif';
    ctx.fillStyle = colors[0] || '#333333';
    ctx.globalAlpha = 0.5;
    ctx.fillText('— THREAD & PRESS HUB —', cx, cy + 35);
    ctx.globalAlpha = 1;
}

// --- Default Fallback Design ---
function drawDefaultDesign(colors, w, h, title) {
    const cx = w / 2, cy = h / 2 - 10;

    // Abstract logo mark
    ctx.beginPath();
    ctx.arc(cx, cy - 10, 30, 0, Math.PI * 2);
    ctx.strokeStyle = colors[0] || '#2C3E50';
    ctx.lineWidth = 2.5;
    ctx.stroke();

    // Inner cross pattern
    ctx.beginPath();
    ctx.moveTo(cx - 20, cy - 10); ctx.lineTo(cx + 20, cy - 10);
    ctx.moveTo(cx, cy - 30); ctx.lineTo(cx, cy + 10);
    ctx.strokeStyle = colors[1] || '#E74C3C';
    ctx.lineWidth = 2;
    ctx.stroke();

    // Accent dots
    for (let i = 0; i < 4; i++) {
        const a = (Math.PI / 2) * i;
        ctx.beginPath();
        ctx.arc(cx + Math.cos(a) * 20, cy - 10 + Math.sin(a) * 20, 3, 0, Math.PI * 2);
        ctx.fillStyle = colors[2] || colors[0];
        ctx.fill();
    }

    // Text
    if (title) {
        ctx.font = 'bold 10px Arial, sans-serif';
        ctx.fillStyle = colors[0] || '#2C3E50';
        ctx.textAlign = 'center';
        const shortTitle = title.length > 20 ? title.substring(0, 20) : title;
        ctx.fillText(shortTitle.toUpperCase(), cx, cy + 35);
    }
}

// Allow Enter key to trigger AI suggest
document.getElementById('aiPromptInput')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') getAISuggestions();
});

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

// ===== Price Auto Calculation =====
// Base prices come from the data-driven apparel config so new types priced there
// flow through automatically (couple sets ₱1,500; corporate ₱950/₱1,050/₱1,100).
const pricing = {
    base: {},
    printSize: { small: 50, medium: 100, large: 180, full: 300 },
    colorCost: 25 // per color used
};
Object.keys(APPAREL_CONFIG).forEach(t => { pricing.base[t] = APPAREL_CONFIG[t].base; });

function calculatePrice() {
    const baseCost = pricing.base[state.apparelType] || 350;
    const printSize = document.getElementById('printSizeSelect')?.value || 'medium';
    const printSizeCost = pricing.printSize[printSize] || 100;
    const colorsUsed = countColorsUsed();
    const colorCost = Math.max(0, (colorsUsed - 1)) * pricing.colorCost; // first color free

    document.getElementById('priceBase').textContent = '₱' + baseCost.toLocaleString();
    document.getElementById('pricePrintSize').textContent = '₱' + printSizeCost.toLocaleString();
    document.getElementById('priceColorsCount').textContent = colorsUsed;
    document.getElementById('priceColors').textContent = colorsUsed > 1 ? '₱' + colorCost.toLocaleString() : 'Free';
    // Quantity
    const qty = parseInt(document.getElementById('quantityInput')?.value) || 1;
    let subtotal = (baseCost + printSizeCost + colorCost) * qty;

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
}

function adjustQty(delta) {
    const input = document.getElementById('quantityInput');
    let val = parseInt(input.value) || 1;
    val = Math.max(1, Math.min(100, val + delta));
    input.value = val;
    calculatePrice();
}

function countColorsUsed() {
    const colors = new Set();
    colors.add(state.brushColor);

    // Sample canvas pixels to count unique colors
    try {
        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        const data = imageData.data;
        const step = 8; // sample every 8th pixel for performance
        for (let i = 0; i < data.length; i += 4 * step) {
            const a = data[i + 3];
            if (a > 30) {
                const r = data[i];
                const g = data[i + 1];
                const b = data[i + 2];
                // Quantize to reduce noise
                const qr = Math.round(r / 32) * 32;
                const qg = Math.round(g / 32) * 32;
                const qb = Math.round(b / 32) * 32;
                colors.add(`${qr},${qg},${qb}`);
            }
        }
    } catch (e) {
        // Canvas may be tainted
    }

    // Count text/element colors
    state.elements.forEach(el => {
        const span = el.querySelector('span');
        if (span && span.style.color) {
            colors.add(span.style.color);
        }
    });

    return Math.min(colors.size, 20); // cap at 20
}

// Init on page load
document.addEventListener('DOMContentLoaded', function() {
    renderStampPalette();
    init();
    init3DPreview();
    updatePreview();
    calculatePrice();
});
</script>

<!--
    CHATBOT MOUNT POINT
    The site chatbot is intentionally NOT built here. When integrating it, mount
    the chatbot widget below (or include its partial). Do not block the design
    tool's scripts above. Example:
    <?php /* include 'includes/chatbot/chatbot-widget.php'; */ ?>
-->

<?php include 'includes/footer/footer.php'; ?>
