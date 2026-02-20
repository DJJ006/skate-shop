document.addEventListener('DOMContentLoaded', () => {
    // 1. SELECT ELEMENTS
    const video = document.getElementById('video-display');
    const playIcon = document.getElementById('play-icon');
    const timer = document.getElementById('timer');
    const monitor = document.getElementById('monitor-container');
    const volumeSlider = document.getElementById('volume-slider');
    const tapes = document.querySelectorAll('.tape-item');
    const progressBar = document.getElementById('progress-bar');
    const currTimeText = document.getElementById('current-time');
    const durationText = document.getElementById('total-duration');

    // ------------VIDEO PLAYERIS---------------
    
    // Format for progress bar (M:SS)
    function formatTime(seconds) {
        let m = Math.floor(seconds / 60);
        let s = Math.floor(seconds % 60);
        return `${m}:${s.toString().padStart(2, '0')}`;
    }

    // Format for SMPTE Timecode (HH:MM:SS:FF)
    function updateTimecode() {
        let fps = 30;
        let totalSeconds = video.currentTime;
        
        let hours = Math.floor(totalSeconds / 3600);
        let minutes = Math.floor((totalSeconds % 3600) / 60);
        let seconds = Math.floor(totalSeconds % 60);
        let frames = Math.floor((totalSeconds % 1) * fps);

        const pad = (n) => n.toString().padStart(2, '0');
        timer.innerText = `${pad(hours)}:${pad(minutes)}:${pad(seconds)}:${pad(frames)}`;
        requestAnimationFrame(updateTimecode);
    }

    // Tape Switcher Logic
    function playTape(index) {
        const tape = tapes[index];
        
        // Trigger Glitch Effect
        monitor.classList.add('glitch-active');
        
        setTimeout(() => {
            // UI Updates
            tapes.forEach(t => t.classList.remove('active'));
            tape.classList.add('active');

            // Source Updates
            video.querySelector('source').src = tape.getAttribute('data-video');
            document.getElementById('reel-title').innerText = tape.getAttribute('data-title');
            document.getElementById('reel-meta').innerText = tape.getAttribute('data-meta');

            video.load();
            video.play();
            playIcon.style.opacity = '0';
            
            // Remove glitch after load
            setTimeout(() => monitor.classList.remove('glitch-active'), 300);
        }, 200);
    }

    // 3. PROGRESS BAR & SCRUBBING
    
    // Update Slider as Video Plays
    video.addEventListener('timeupdate', () => {
        if (!video.duration) return;
        
        const percentage = (video.currentTime / video.duration) * 100;
        progressBar.value = percentage;
        
        // CSS variable for the red fill effect
        progressBar.style.setProperty('--buffered', `${percentage}%`);
        currTimeText.innerText = formatTime(video.currentTime);
    });

    // Set duration once file loads
    video.addEventListener('loadedmetadata', () => {
        durationText.innerText = formatTime(video.duration);
    });

    // Dragging the bar (Scrubbing)
    progressBar.addEventListener('input', () => {
        const seekTime = (progressBar.value / 100) * video.duration;
        video.currentTime = seekTime;
        progressBar.style.setProperty('--buffered', `${progressBar.value}%`);
    });

    // 4. EVENT LISTENERS

    // Autoplay Next Tape
    video.addEventListener('ended', () => {
        let currentIndex = Array.from(tapes).findIndex(t => t.classList.contains('active'));
        let nextIndex = (currentIndex + 1) % tapes.length;
        playTape(nextIndex);
    });

    // Volume Control
    volumeSlider.addEventListener('input', (e) => {
        video.volume = e.target.value;
    });

    // Manual Tape Clicks
    tapes.forEach((tape, index) => {
        tape.addEventListener('click', () => playTape(index));
    });

    // Master Play/Pause Trigger
    document.getElementById('play-trigger').addEventListener('click', () => {
        if (video.paused) { 
            video.play(); 
            playIcon.style.opacity = '0'; 
        } else { 
            video.pause(); 
            playIcon.style.opacity = '1'; 
        }
    });

    // 5. INITIALIZE
    updateTimecode();
});

// - - - - - - - - - - - - - - - - - - - - - - - 