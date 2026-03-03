(function() {
    const initSlider = () => {
        const slides = document.querySelectorAll('.commercial-slide');
        const dots = document.querySelectorAll('.dot');
        const prevBtn = document.getElementById('prev-ad');
        const nextBtn = document.getElementById('next-ad');
        
        if (slides.length === 0) return; // Exit if not on the shop page

        let currentSlide = 0;
        let slideInterval;

        function showSlide(index) {
            // Remove active from everyone
            slides.forEach(s => {
                s.style.opacity = "0";
                s.classList.remove('active');
            });
            dots.forEach(d => d.classList.remove('active'));
            
            // Apply to target
            slides[index].classList.add('active');
            slides[index].style.opacity = "1";
            if(dots[index]) dots[index].classList.add('active');
        }

        // 1. Set initial state immediately
        showSlide(0);

        // 2. Navigation Click Events
        if (nextBtn) {
            nextBtn.onclick = (e) => {
                e.preventDefault();
                currentSlide = (currentSlide + 1) % slides.length;
                showSlide(currentSlide);
                resetTimer();
            };
        }

        if (prevBtn) {
            prevBtn.onclick = (e) => {
                e.preventDefault();
                currentSlide = (currentSlide - 1 + slides.length) % slides.length;
                showSlide(currentSlide);
                resetTimer();
            };
        }

        // 3. Auto-play Logic
        function startTimer() {
            slideInterval = setInterval(() => {
                currentSlide = (currentSlide + 1) % slides.length;
                showSlide(currentSlide);
            }, 5000);
        }

        function resetTimer() {
            clearInterval(slideInterval);
            startTimer();
        }

        startTimer();
    };

    // Run as soon as DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSlider);
    } else {
        initSlider();
    }
})();