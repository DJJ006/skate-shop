document.addEventListener('DOMContentLoaded', () => {
    // Auto-hide alert
    const alertMsg = document.getElementById('form-alert');
    if (alertMsg) {
        setTimeout(() => { alertMsg.classList.add('fade-out'); setTimeout(() => alertMsg.style.display = 'none', 500); }, 4000);
    }

    // Upload modal
    const uploadBtn = document.getElementById('open-upload-modal');
    const uploadModal = document.getElementById('uploadModal');
    const closeUpload = document.getElementById('close-upload-modal');
    if (uploadBtn && uploadModal) {
        uploadBtn.addEventListener('click', () => uploadModal.classList.add('active'));
        closeUpload.addEventListener('click', () => uploadModal.classList.remove('active'));
        uploadModal.addEventListener('click', (e) => { if (e.target === uploadModal) uploadModal.classList.remove('active'); });
    }

    // Video switching
    const tapes = document.querySelectorAll('.tape-item');
    const iframe = document.getElementById('reel-video-display');
    const monitor = document.getElementById('monitor-container');
    const reelTitle = document.getElementById('reel-title');
    const reelMeta = document.getElementById('reel-meta');
    const reelPlatform = document.getElementById('reel-platform');
    const reelDesc = document.getElementById('reel-desc');
    const likeBtn = document.getElementById('like-btn');
    const likeCount = document.getElementById('like-count');
    const commentsBox = document.getElementById('comments-section');
    const commentsList = document.getElementById('comments-list');
    const commentForm = document.getElementById('comment-form');
    const commentInput = document.getElementById('comment-input');
    const toggleCommentsBtn = document.getElementById('toggle-comments');
    const commentSort = document.getElementById('comment-sort');
    let currentReelId = document.getElementById('current-reel-id') ? document.getElementById('current-reel-id').value : 0;

    function loadComments(reelId) {
        if (!commentsBox) return;
        const sort = commentSort ? commentSort.value : 'oldest';
        fetch('reels-api.php?action=get_comments&reel_id=' + reelId + '&sort=' + sort)
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                commentsList.innerHTML = '';
                if (data.comments.length === 0) {
                    commentsList.innerHTML = '<p class="no-comments">No comments yet. Be the first!</p>';
                    return;
                }
                data.comments.forEach(c => appendComment(c));
            });
    }

    function appendComment(c, prepend = false) {
        const div = document.createElement('div');
        div.className = 'comment-item';
        div.id = 'comment-' + c.id;
        let actions = '';
        if (c.is_owner) {
            actions = '<div class="comment-actions">' +
                '<button class="comment-edit-btn" onclick="editComment(' + c.id + ')">EDIT</button>' +
                '<button class="comment-delete-btn" onclick="deleteComment(' + c.id + ')">DELETE</button>' +
                '</div>';
        }
        div.innerHTML = '<div class="comment-header"><strong><a href="javascript:void(0)" onclick="openUserProfile(\'' + encodeURIComponent(c.username) + '\')" style="color:var(--primary); text-decoration:underline; font-weight:bold;">@' + c.username + '</a></strong><span class="comment-date">' + c.created_at + '</span></div>' +
            '<p class="comment-text" id="comment-text-' + c.id + '">' + c.comment + '</p>' + actions;

        if (prepend) {
            commentsList.prepend(div);
        } else {
            commentsList.appendChild(div);
        }
    }

    // Sort listener
    if (commentSort) {
        commentSort.addEventListener('change', () => {
            loadComments(currentReelId);
        });
    }

    // Toggle comments
    if (toggleCommentsBtn && commentsBox) {
        toggleCommentsBtn.addEventListener('click', () => {
            commentsBox.classList.toggle('open');
            if (commentsBox.classList.contains('open')) {
                loadComments(currentReelId);
                toggleCommentsBtn.textContent = 'HIDE COMMENTS';
            } else {
                toggleCommentsBtn.textContent = 'COMMENTS';
            }
        });
    }

    // Like button
    if (likeBtn) {
        likeBtn.addEventListener('click', () => {
            const fd = new FormData();
            fd.append('action', 'toggle_like');
            fd.append('reel_id', currentReelId);
            fetch('reels-api.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        likeCount.textContent = data.count;
                        likeBtn.classList.toggle('liked', data.liked);
                    }
                });
        });
    }

    // Comment submit
    if (commentForm) {
        commentForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const text = commentInput.value.trim();
            if (!text) return;
            const fd = new FormData();
            fd.append('action', 'add_comment');
            fd.append('reel_id', currentReelId);
            fd.append('comment', text);
            fetch('reels-api.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const noComments = commentsList.querySelector('.no-comments');
                        if (noComments) noComments.remove();

                        // If sort is newest, prepend. Otherwise append.
                        const isNewest = commentSort && commentSort.value === 'newest';
                        appendComment(data.comment, isNewest);

                        commentInput.value = '';
                        // Trigger character counter update
                        commentInput.dispatchEvent(new Event('input'));
                    }
                });
        });
    }

    // Character counters
    function updateCounter(inputEl, counterEl, maxLen) {
        if (!inputEl || !counterEl) return;
        const remaining = maxLen - inputEl.value.length;
        counterEl.textContent = remaining + ' characters remaining';

        counterEl.classList.toggle('warning', remaining <= maxLen * 0.2);
        counterEl.classList.toggle('danger', remaining <= maxLen * 0.1);
    }

    if (commentInput) {
        const commentCounter = document.getElementById('comment-counter');
        commentInput.addEventListener('input', () => updateCounter(commentInput, commentCounter, 75));
    }

    const modalTitle = document.getElementById('modal-title');
    const titleCounter = document.getElementById('title-counter');
    if (modalTitle) {
        modalTitle.addEventListener('input', () => updateCounter(modalTitle, titleCounter, 35));
    }

    const modalDesc = document.getElementById('modal-desc');
    const descCounter = document.getElementById('desc-counter');
    if (modalDesc) {
        modalDesc.addEventListener('input', () => updateCounter(modalDesc, descCounter, 100));
    }

    // Tape switching
    if (tapes.length > 0 && iframe) {
        tapes.forEach(tape => {
            tape.addEventListener('click', function () {
                monitor.classList.add('glitch-active');
                setTimeout(() => {
                    tapes.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    iframe.src = this.getAttribute('data-video');
                    reelTitle.innerText = this.getAttribute('data-title');
                    reelMeta.innerHTML = this.getAttribute('data-meta');
                    reelPlatform.innerText = this.getAttribute('data-platform');
                    const desc = this.getAttribute('data-desc');
                    if (desc) { reelDesc.innerHTML = desc.replace(/\n/g, '<br>'); reelDesc.style.display = 'block'; }
                    else { reelDesc.innerHTML = ''; reelDesc.style.display = 'none'; }

                    // Update reel ID, likes, comments
                    currentReelId = this.getAttribute('data-reel-id');
                    document.getElementById('current-reel-id').value = currentReelId;
                    if (likeCount) likeCount.textContent = this.getAttribute('data-likes');
                    if (likeBtn) {
                        const userLiked = this.getAttribute('data-user-liked') === '1';
                        likeBtn.classList.toggle('liked', userLiked);
                    }

                    // Reload comments if open
                    if (commentsBox && commentsBox.classList.contains('open')) {
                        loadComments(currentReelId);
                    }

                    setTimeout(() => monitor.classList.remove('glitch-active'), 300);
                }, 200);
            });
        });
    }

    // Load initial comments if section is defaulting open
    if (currentReelId > 0 && commentsBox && commentsBox.classList.contains('open')) {
        loadComments(currentReelId);
    }

    // Search and Sort Logic
    const searchInput = document.getElementById('reel-search');
    const sortSelect = document.getElementById('reel-sort');
    const tapeList = document.getElementById('tape-list');

    if (searchInput && sortSelect && tapeList && tapes.length > 0) {
        let tapeItemsArray = Array.from(tapes);

        function filterAndSortReels() {
            const searchTerm = searchInput.value.toLowerCase().trim();
            const sortBy = sortSelect.value;

            // 1. Filter
            tapeItemsArray.forEach(item => {
                const title = item.getAttribute('data-title').toLowerCase();
                const username = item.getAttribute('data-username').toLowerCase();
                if (title.includes(searchTerm) || username.includes(searchTerm)) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });

            // 2. Sort visible items
            let visibleItems = tapeItemsArray.filter(item => item.style.display !== 'none');
            let hiddenItems = tapeItemsArray.filter(item => item.style.display === 'none');

            visibleItems.sort((a, b) => {
                const dateA = parseInt(a.getAttribute('data-date')) || 0;
                const dateB = parseInt(b.getAttribute('data-date')) || 0;
                const likesA = parseInt(a.getAttribute('data-likes')) || 0;
                const likesB = parseInt(b.getAttribute('data-likes')) || 0;
                const commentsA = parseInt(a.getAttribute('data-comments')) || 0;
                const commentsB = parseInt(b.getAttribute('data-comments')) || 0;

                if (sortBy === 'newest') return dateB - dateA;
                if (sortBy === 'oldest') return dateA - dateB;
                if (sortBy === 'most_liked') {
                    if (likesB !== likesA) return likesB - likesA;
                    return dateB - dateA;
                }
                if (sortBy === 'most_commented') {
                    if (commentsB !== commentsA) return commentsB - commentsA;
                    return dateB - dateA;
                }
                return 0;
            });

            // 3. Re-render DOM
            tapeList.innerHTML = '';
            visibleItems.forEach(item => tapeList.appendChild(item));
            hiddenItems.forEach(item => tapeList.appendChild(item));
        }

        searchInput.addEventListener('input', filterAndSortReels);
        sortSelect.addEventListener('change', filterAndSortReels);
    }
});

// Global functions for edit/delete
function editComment(id) {
    const textEl = document.getElementById('comment-text-' + id);
    if (!textEl || textEl.classList.contains('editing')) return; // Prevent duplicate edit mode

    const oldText = textEl.textContent;
    textEl.classList.add('editing');

    // Create wrapper to hold input and counter
    const editContainer = document.createElement('div');
    editContainer.className = 'comment-edit-wrapper';
    editContainer.style.width = '100%';
    editContainer.style.display = 'flex';
    editContainer.style.flexDirection = 'column';
    editContainer.style.gap = '0.5rem';

    const row = document.createElement('div');
    row.style.display = 'flex';
    row.style.gap = '0.5rem';

    const input = document.createElement('input');
    input.type = 'text';
    input.value = oldText;
    input.maxLength = 75;
    input.className = 'comment-edit-input';

    const saveBtn = document.createElement('button');
    saveBtn.textContent = 'SAVE';
    saveBtn.className = 'comment-save-btn';

    const cancelBtn = document.createElement('button');
    cancelBtn.textContent = 'CANCEL';
    cancelBtn.className = 'comment-edit-btn'; // Use same styling as edit btn

    const counter = document.createElement('div');
    counter.className = 'char-counter';
    counter.style.marginTop = '7px';
    counter.style.marginBottom = '0';

    function updateEditCounter() {
        const remaining = 75 - input.value.length;
        counter.textContent = remaining + ' characters remaining';
        counter.classList.toggle('warning', remaining <= 15);
        counter.classList.toggle('danger', remaining <= 5);
    }

    updateEditCounter();
    input.addEventListener('input', updateEditCounter);

    row.appendChild(input);
    row.appendChild(saveBtn);
    row.appendChild(cancelBtn);
    editContainer.appendChild(row);
    editContainer.appendChild(counter);

    textEl.innerHTML = '';
    textEl.appendChild(editContainer);
    input.focus();

    const finishEdit = (newText) => {
        textEl.classList.remove('editing');
        textEl.textContent = newText;
    };

    saveBtn.addEventListener('click', () => {
        const newText = input.value.trim();
        if (!newText) return;

        const fd = new FormData();
        fd.append('action', 'edit_comment');
        fd.append('comment_id', id);
        fd.append('comment', newText);

        fetch('reels-api.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) finishEdit(data.comment);
                else finishEdit(oldText);
            })
            .catch(() => finishEdit(oldText));
    });

    cancelBtn.addEventListener('click', () => finishEdit(oldText));

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') saveBtn.click();
        if (e.key === 'Escape') cancelBtn.click();
    });
}

function deleteComment(id) {
    if (!confirm('DELETE THIS COMMENT?')) return;
    const fd = new FormData();
    fd.append('action', 'delete_comment');
    fd.append('comment_id', id);
    fetch('reels-api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const el = document.getElementById('comment-' + id);
                if (el) el.remove();
            }
        });
}
