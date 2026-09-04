/**
 * Support Chat - Client-side JavaScript
 * Handles real-time messaging, image uploads, and auto-refresh
 */
(function() {
    'use strict';

    const isAdmin = window.SUPPORT_CHAT_ADMIN || false;
    const ajaxUrl = window.SUPPORT_CHAT_AJAX_URL || 'includes/support-chat-ajax.php';
    const POLL_INTERVAL = 4000; // 4 seconds

    const form = document.getElementById('supportChatForm');
    const messagesContainer = document.getElementById('supportMessages');
    const messageInput = document.getElementById('chatMessage');
    const imageInput = document.getElementById('chatImage');
    const imagePreview = document.getElementById('imagePreview');
    const previewImg = document.getElementById('previewImg');
    const removeImageBtn = document.getElementById('removeImage');
    const sendBtn = document.getElementById('sendBtn');
    const sidebar = document.getElementById('supportSidebar');
    const toggleBtn = document.getElementById('toggleSidebar');
    const toggleBtnEmpty = document.getElementById('toggleSidebarEmpty');

    let lastMessageId = 0;
    let pollTimer = null;
    let isSending = false;

    // Initialize
    function init() {
        if (messagesContainer) {
            // Find the last message ID from data attributes
            const msgs = messagesContainer.querySelectorAll('.support-msg[data-msg-id]');
            if (msgs.length > 0) {
                const lastMsg = msgs[msgs.length - 1];
                lastMessageId = parseInt(lastMsg.getAttribute('data-msg-id')) || 0;
            }
            scrollToBottom();
            startPolling();
        }

        if (form) {
            form.addEventListener('submit', handleSend);
        }

        if (imageInput) {
            imageInput.addEventListener('change', handleImageSelect);
        }

        if (removeImageBtn) {
            removeImageBtn.addEventListener('click', clearImagePreview);
        }

        if (messageInput) {
            messageInput.addEventListener('keydown', handleKeyDown);
            messageInput.addEventListener('input', autoResize);
        }

        // Mobile sidebar toggle
        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => sidebar.classList.toggle('show'));
        }
        if (toggleBtnEmpty) {
            toggleBtnEmpty.addEventListener('click', () => sidebar.classList.toggle('show'));
        }
    }

    // Auto-resize textarea
    function autoResize() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    }

    // Handle Enter key (Shift+Enter for new line)
    function handleKeyDown(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            form.dispatchEvent(new Event('submit'));
        }
    }

    // Handle image selection
    function handleImageSelect() {
        const file = imageInput.files[0];
        if (!file) return;

        // Validate
        const allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!allowed.includes(file.type)) {
            alert('Invalid file type. Only JPEG, PNG, GIF, WebP are allowed.');
            imageInput.value = '';
            return;
        }

        if (file.size > 5 * 1024 * 1024) {
            alert('File too large. Maximum 5MB allowed.');
            imageInput.value = '';
            return;
        }

        // Show preview
        const reader = new FileReader();
        reader.onload = function(e) {
            previewImg.src = e.target.result;
            imagePreview.style.display = 'inline-block';
        };
        reader.readAsDataURL(file);
    }

    // Clear image preview
    function clearImagePreview() {
        imageInput.value = '';
        imagePreview.style.display = 'none';
        previewImg.src = '';
    }

    // Send message
    function handleSend(e) {
        e.preventDefault();
        if (isSending) return;

        const message = messageInput.value.trim();
        const hasImage = imageInput.files.length > 0;

        if (!message && !hasImage) return;

        isSending = true;
        sendBtn.disabled = true;

        const formData = new FormData();
        formData.append('action', 'send_message');
        formData.append('conversation_id', form.querySelector('[name="conversation_id"]').value);
        formData.append('message', message);

        if (hasImage) {
            formData.append('image', imageInput.files[0]);
        }

        fetch(ajaxUrl, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Append the message to the chat
                appendMessage(data.message, true);
                messageInput.value = '';
                messageInput.style.height = 'auto';
                clearImagePreview();
                
                if (data.message.id) {
                    lastMessageId = data.message.id;
                }
            } else {
                alert(data.error || 'Failed to send message');
            }
        })
        .catch(() => {
            alert('Network error. Please try again.');
        })
        .finally(() => {
            isSending = false;
            sendBtn.disabled = false;
            messageInput.focus();
        });
    }

    // Append a message to the chat area
    function appendMessage(msg, isOwn) {
        const msgDiv = document.createElement('div');
        
        // For user page: user messages go right, admin left
        // For admin page: admin messages go right, user left
        let alignClass;
        if (isAdmin) {
            alignClass = msg.sender_type === 'admin' ? 'support-msg-user' : 'support-msg-admin';
        } else {
            alignClass = msg.sender_type === 'user' ? 'support-msg-user' : 'support-msg-admin';
        }
        
        msgDiv.className = 'support-msg ' + alignClass + ' support-msg-new';
        if (msg.id) msgDiv.setAttribute('data-msg-id', msg.id);

        const senderIcon = msg.sender_type === 'admin' ? 'fa-user-shield' : 'fa-user';
        const senderBadge = msg.sender_type === 'admin' 
            ? '<span class="badge bg-primary ms-1" style="font-size: 0.65rem;">Admin</span>'
            : (isAdmin ? '<span class="badge bg-info ms-1" style="font-size: 0.65rem;">Customer</span>' : '');

        // Build image path (admin page needs ../ prefix)
        const imgPrefix = isAdmin ? '../' : '';

        let imageHtml = '';
        if (msg.image_path) {
            imageHtml = `
                <div class="support-msg-image">
                    <a href="${imgPrefix}${escapeHtml(msg.image_path)}" target="_blank">
                        <img src="${imgPrefix}${escapeHtml(msg.image_path)}" alt="Shared image" loading="lazy">
                    </a>
                </div>`;
        }

        let textHtml = '';
        if (msg.message) {
            textHtml = `<div class="support-msg-text">${escapeHtml(msg.message).replace(/\n/g, '<br>')}</div>`;
        }

        const time = msg.created_at_formatted || msg.created_at;

        msgDiv.innerHTML = `
            <div class="support-msg-bubble">
                <div class="support-msg-sender">
                    <i class="fas ${senderIcon}"></i>
                    ${escapeHtml(msg.sender_name)}
                    ${senderBadge}
                </div>
                ${imageHtml}
                ${textHtml}
                <div class="support-msg-time">${escapeHtml(time)}</div>
            </div>
        `;

        messagesContainer.appendChild(msgDiv);
        scrollToBottom();
    }

    // Poll for new messages
    function startPolling() {
        pollTimer = setInterval(fetchNewMessages, POLL_INTERVAL);
    }

    function fetchNewMessages() {
        const convId = form ? form.querySelector('[name="conversation_id"]').value : null;
        if (!convId) return;

        const url = `${ajaxUrl}?action=get_messages&conversation_id=${convId}&last_id=${lastMessageId}`;

        fetch(url)
            .then(res => res.json())
            .then(data => {
                if (data.success && data.messages.length > 0) {
                    data.messages.forEach(msg => {
                        // Don't re-append messages we already have
                        if (msg.id > lastMessageId) {
                            // Only append if it wasn't sent by us (already shown)
                            const existing = messagesContainer.querySelector(`[data-msg-id="${msg.id}"]`);
                            if (!existing) {
                                appendMessage(msg, false);
                            }
                            lastMessageId = msg.id;
                        }
                    });
                }
            })
            .catch(() => {});
    }

    // Scroll to bottom of messages
    function scrollToBottom() {
        if (messagesContainer) {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
    }

    // Escape HTML to prevent XSS
    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
