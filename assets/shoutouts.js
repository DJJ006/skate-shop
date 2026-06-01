document.addEventListener('DOMContentLoaded', () => {
    const formAlert = document.getElementById('form-alert');
    if (formAlert) {
        setTimeout(() => {
            formAlert.classList.add('fade-out');
            setTimeout(() => { formAlert.style.display = 'none'; }, 500);
        }, 4000);
    }

    const openBtn = document.getElementById('open-shoutout-modal');
    const modal = document.getElementById('shoutoutUploadModal');
    const closeBtn = document.getElementById('close-shoutout-modal');
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

    const sTitle = document.getElementById('shoutout-modal-title');
    const sTitleCtr = document.getElementById('shoutout-title-counter');
    if (sTitle && sTitleCtr) {
        const maxT = parseInt(sTitle.getAttribute('maxlength'), 10) || 50;
        sTitle.addEventListener('input', () => updateCounter(sTitle, sTitleCtr, maxT));
        updateCounter(sTitle, sTitleCtr, maxT);
    }

    const sBody = document.getElementById('shoutout-modal-body');
    const sBodyCtr = document.getElementById('shoutout-body-counter');
    if (sBody && sBodyCtr) {
        const maxB = parseInt(sBody.getAttribute('maxlength'), 10) || 350;
        sBody.addEventListener('input', () => updateCounter(sBody, sBodyCtr, maxB));
        updateCounter(sBody, sBodyCtr, maxB);
    }
});
