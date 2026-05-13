document.addEventListener('DOMContentLoaded', () => {
    const formAlert = document.getElementById('form-alert');
    if (formAlert) {
        setTimeout(() => {
            formAlert.classList.add('fade-out');
            setTimeout(() => { formAlert.style.display = 'none'; }, 500);
        }, 4000);
    }

    const openBtn = document.getElementById('open-qna-modal');
    const modal = document.getElementById('qnaUploadModal');
    const closeBtn = document.getElementById('close-qna-modal');
    if (openBtn && modal) {
        openBtn.addEventListener('click', () => modal.classList.add('active'));
    }
    if (closeBtn && modal) {
        closeBtn.addEventListener('click', () => modal.classList.remove('active'));
    }
    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) modal.classList.remove('active');
        });
    }

    function updateCounter(inputEl, counterEl, maxLen) {
        if (!inputEl || !counterEl) return;
        const remaining = maxLen - inputEl.value.length;
        counterEl.textContent = remaining + ' characters remaining';
        counterEl.classList.toggle('warning', remaining <= maxLen * 0.2);
        counterEl.classList.toggle('danger', remaining <= maxLen * 0.1);
    }

    const qTitle = document.getElementById('qna-modal-title');
    const qTitleCtr = document.getElementById('qna-title-counter');
    if (qTitle && qTitleCtr) {
        const maxT = parseInt(qTitle.getAttribute('maxlength'), 10) || 50;
        qTitle.addEventListener('input', () => updateCounter(qTitle, qTitleCtr, maxT));
        updateCounter(qTitle, qTitleCtr, maxT);
    }

    const qBody = document.getElementById('qna-modal-body');
    const qBodyCtr = document.getElementById('qna-body-counter');
    if (qBody && qBodyCtr) {
        const maxB = parseInt(qBody.getAttribute('maxlength'), 10) || 350;
        qBody.addEventListener('input', () => updateCounter(qBody, qBodyCtr, maxB));
        updateCounter(qBody, qBodyCtr, maxB);
    }
});
