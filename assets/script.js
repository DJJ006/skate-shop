document.addEventListener('DOMContentLoaded', () => {
    
    // --- 1. MOBILE HAMBURGER NAVIGATION ---
    const menuBtn = document.getElementById('menu-btn');
    const navMenu = document.getElementById('nav-menu');

    if (menuBtn && navMenu) {
        menuBtn.addEventListener('click', () => {
            navMenu.classList.toggle('active');
            const icon = menuBtn.querySelector('.material-icons');
            icon.textContent = navMenu.classList.contains('active') ? 'close' : 'menu';
        });

        document.querySelectorAll('.nav-item').forEach(link => {
            link.addEventListener('click', () => {
                navMenu.classList.remove('active');
                menuBtn.querySelector('.material-icons').textContent = 'menu';
            });
        });
    }

    // --- 2. VIDEO PLAYER (Only runs if video exists) ---
    const video = document.getElementById('video-display');
    if (video) {
        const playIcon = document.getElementById('play-icon');
        const timer = document.getElementById('timer');
        const monitor = document.getElementById('monitor-container');
        const volumeSlider = document.getElementById('volume-slider');
        const tapes = document.querySelectorAll('.tape-item');
        const progressBar = document.getElementById('progress-bar');
        const currTimeText = document.getElementById('current-time');
        const durationText = document.getElementById('total-duration');

        function formatTime(seconds) {
            let m = Math.floor(seconds / 60);
            let s = Math.floor(seconds % 60);
            return `${m}:${s.toString().padStart(2, '0')}`;
        }

        function updateTimecode() {
            let fps = 30;
            let totalSeconds = video.currentTime;
            let hours = Math.floor(totalSeconds / 3600);
            let minutes = Math.floor((totalSeconds % 3600) / 60);
            let seconds = Math.floor(totalSeconds % 60);
            let frames = Math.floor((totalSeconds % 1) * fps);
            const pad = (n) => n.toString().padStart(2, '0');
            if (timer) timer.innerText = `${pad(hours)}:${pad(minutes)}:${pad(seconds)}:${pad(frames)}`;
            requestAnimationFrame(updateTimecode);
        }

        function playTape(index) {
            const tape = tapes[index];
            monitor.classList.add('glitch-active');
            setTimeout(() => {
                tapes.forEach(t => t.classList.remove('active'));
                tape.classList.add('active');
                video.querySelector('source').src = tape.getAttribute('data-video');
                document.getElementById('reel-title').innerText = tape.getAttribute('data-title');
                document.getElementById('reel-meta').innerText = tape.getAttribute('data-meta');
                video.load();
                video.play();
                playIcon.style.opacity = '0';
                setTimeout(() => monitor.classList.remove('glitch-active'), 300);
            }, 200);
        }

        video.addEventListener('timeupdate', () => {
            if (!video.duration) return;
            const percentage = (video.currentTime / video.duration) * 100;
            progressBar.value = percentage;
            progressBar.style.setProperty('--buffered', `${percentage}%`);
            currTimeText.innerText = formatTime(video.currentTime);
        });

        video.addEventListener('loadedmetadata', () => {
            durationText.innerText = formatTime(video.duration);
        });

        progressBar.addEventListener('input', () => {
            video.currentTime = (progressBar.value / 100) * video.duration;
        });

        video.addEventListener('ended', () => {
            let currentIndex = Array.from(tapes).findIndex(t => t.classList.contains('active'));
            playTape((currentIndex + 1) % tapes.length);
        });

        volumeSlider.addEventListener('input', (e) => { video.volume = e.target.value; });

        tapes.forEach((tape, index) => {
            tape.addEventListener('click', () => playTape(index));
        });

        document.getElementById('play-trigger').addEventListener('click', () => {
            if (video.paused) { video.play(); playIcon.style.opacity = '0'; }
            else { video.pause(); playIcon.style.opacity = '1'; }
        });

        updateTimecode();
    }

    // --- 3. COMMERCIAL SLIDER (Only runs if slides exist) ---
    const slides = document.querySelectorAll('.commercial-slide');
    if (slides.length > 0) {
        const dots = document.querySelectorAll('.dot');
        const prevBtn = document.getElementById('prev-ad');
        const nextBtn = document.getElementById('next-ad');
        let currentSlide = 0;

        function showSlide(index) {
            slides.forEach(s => s.classList.remove('active'));
            dots.forEach(d => d.classList.remove('active'));
            slides[index].classList.add('active');
            dots[index].classList.add('active');
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', () => {
                currentSlide = (currentSlide + 1) % slides.length;
                showSlide(currentSlide);
            });
        }

        if (prevBtn) {
            prevBtn.addEventListener('click', () => {
                currentSlide = (currentSlide - 1 + slides.length) % slides.length;
                showSlide(currentSlide);
            });
        }

        // Auto-slide
        setInterval(() => {
            currentSlide = (currentSlide + 1) % slides.length;
            showSlide(currentSlide);
        }, 5000);
    }



    
        const qnaItems = document.querySelectorAll('.qna-item');
    
        qnaItems.forEach(item => {
            item.addEventListener('click', () => {
                // Optional: Close other open items (Single-dropdown mode)
                qnaItems.forEach(otherItem => {
                    if (otherItem !== item) otherItem.classList.remove('active');
                });
    
                // Toggle current item
                item.classList.toggle('active');
            });
        });
    
});