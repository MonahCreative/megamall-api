{{-- New-order chime. Browsers only allow sound after the page has been clicked once, so the first click "unlocks" it. --}}
<div
    wire:poll.20s="check"
    x-data="{
        audio: null,
        init() {
            const unlock = () => {
                this.audio ??= new (window.AudioContext || window.webkitAudioContext)();
                this.audio.resume();
            };
            window.addEventListener('pointerdown', unlock, { once: true });
            window.addEventListener('keydown', unlock, { once: true });
        },
        chime() {
            if (! this.audio) return;
            const start = this.audio.currentTime;
            [[880, 0], [1175, 0.18], [1568, 0.36]].forEach(([frequency, delay]) => {
                const tone = this.audio.createOscillator();
                const volume = this.audio.createGain();
                tone.type = 'sine';
                tone.frequency.value = frequency;
                volume.gain.setValueAtTime(0.0001, start + delay);
                volume.gain.exponentialRampToValueAtTime(0.25, start + delay + 0.02);
                volume.gain.exponentialRampToValueAtTime(0.0001, start + delay + 0.3);
                tone.connect(volume).connect(this.audio.destination);
                tone.start(start + delay);
                tone.stop(start + delay + 0.35);
            });
        },
    }"
    x-on:mm-new-order.window="chime()"
></div>
