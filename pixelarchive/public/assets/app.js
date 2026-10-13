/* PixelArchive — Alpine components and small helpers (loaded after Tailwind, before Alpine). */

/* ---------- Global helpers (used from inline Alpine handlers) ---------- */

async function api(url, data) {
    try {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data ?? {}),
        });
        const json = await res.json().catch(() => ({}));
        return { ok: res.ok && json.ok !== false, ...json };
    } catch (e) {
        return { ok: false, error: 'Network error — is the server still running?' };
    }
}

function toast(message, kind = 'success') {
    window.dispatchEvent(new CustomEvent('toast', { detail: { message, kind } }));
}

function fmtTime(sec) {
    sec = Math.max(0, Math.floor(sec || 0));
    const h = Math.floor(sec / 3600), m = Math.floor((sec % 3600) / 60), s = sec % 60;
    return h > 0
        ? `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
        : `${m}:${String(s).padStart(2, '0')}`;
}

async function confirmDelete(id, type) {
    if (!confirm(type === 'playlist'
        ? 'Delete this playlist? (Media keeps its tags.)'
        : 'Delete this media item and its file permanently?')) return;
    const url = type === 'playlist' ? '/api/playlists/delete' : '/api/media/delete';
    const r = await api(url, { id, type });
    if (r.ok) {
        toast(type === 'playlist' ? 'Playlist deleted' : 'Item deleted');
        setTimeout(() => location.href = type === 'playlist' ? '/playlists' : '/', 500);
    } else {
        toast(r.error || 'Delete failed', 'error');
    }
}

/* Player tag editor on the detail page */
function addTagPrompt(tags) {
    const input = document.getElementById('new-tag-input');
    if (input) { input.classList.remove('hidden'); input.focus(); }
}
async function addTagFromInput(tags) {
    const input = document.getElementById('new-tag-input');
    if (!input) return;
    const value = input.value.trim().toLowerCase();
    input.value = '';
    input.classList.add('hidden');
    if (!value) return;
    const params = new URLSearchParams(location.search);
    const r = await api('/api/media/update', {
        id: params.get('id'),
        type: document.body.dataset.playerType || 'video',
        tags: [...tags, value],
    });
    if (r.ok && r.item) {
        tags.length = 0;
        tags.push(...r.item.tags);
        toast('Tag added');
    } else {
        toast(r.error || 'Could not add tag', 'error');
    }
}

/* ---------- Alpine data factories ---------- */

document.addEventListener('alpine:init', () => {

    Alpine.store('ui', {
        sidebarOpen: false,
        toggleSidebar() { this.sidebarOpen = !this.sidebarOpen; },
        closeSidebar() { this.sidebarOpen = false; },
    });

    // Root wires the mobile drawer class onto the sidebar element.
    Alpine.data('appRoot', () => ({
        init() {
            const aside = document.querySelector('aside');
            this.$watch(() => Alpine.store('ui').sidebarOpen, (open) => {
                if (!aside) return;
                if (window.innerWidth < 1024) {
                    aside.classList.toggle('hidden', !open);
                    aside.classList.toggle('flex', open);
                    aside.classList.add('drawer-open');
                } else {
                    aside.classList.remove('hidden');
                    aside.classList.add('lg:flex', 'flex');
                }
            });
            // Desktop default: visible.
            if (aside && window.innerWidth >= 1024) {
                aside.classList.remove('hidden');
                aside.classList.add('flex');
            }
            if (aside && window.innerWidth < 1024) {
                aside.classList.add('hidden');
            }
        },
    }));

    Alpine.data('toast', () => ({
        visible: false, message: '', kind: 'success', _t: null,
        show(detail) {
            this.message = detail.message;
            this.kind = detail.kind || 'success';
            this.visible = true;
            clearTimeout(this._t);
            this._t = setTimeout(() => { this.visible = false; }, 3500);
        },
    }));

    /* Video player with trim + autoplay-next queue */
    Alpine.data('player', (cfg) => ({
        src: cfg.src,
        duration: cfg.duration || 0,
        current: 0,
        seekPos: 0,
        playing: false,
        muted: false,
        vol: 100,
        controlsVisible: true,
        settingsOpen: false,
        upNext: false,
        countdown: 5,
        nextQueue: cfg.next || [],
        _timer: null,
        _hideTimer: null,
        trimStart: '',
        trimEnd: '',
        trimming: false,
        trimMsg: '',
        trimError: '',

        init() {
            document.body.dataset.playerType = 'video';
        },

        /* --- transport --- */
        v() { return this.$refs.video; },
        toggle() { this.v().paused ? this.v().play() : this.v().pause(); },
        skip(n) { this.v().currentTime = Math.min(Math.max(0, this.v().currentTime + n), this.duration); },
        scrub() { this.v().currentTime = parseFloat(this.seekPos); },
        setVolume() { this.v().volume = this.vol / 100; this.muted = this.vol === 0; this.v().muted = this.muted; },
        toggleMute() { this.muted = !this.muted; this.v().muted = this.muted; },
        fullscreen() {
            const el = this.v().parentElement;
            document.fullscreenElement ? document.exitFullscreen() : el.requestFullscreen?.();
        },
        onMeta() { this.duration = this.v().duration || this.duration; },
        onTime() {
            this.current = this.v().currentTime;
            if (!this.v().dragging) this.seekPos = this.current;
        },
        fmt(t) { return fmtTime(t); },
        wakeControls() {
            this.controlsVisible = true;
            clearTimeout(this._hideTimer);
            this._hideTimer = setTimeout(() => { if (this.playing) this.controlsVisible = false; }, 2500);
        },

        handleKey(e) {
            if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) return;
            switch (e.key.toLowerCase()) {
                case ' ': e.preventDefault(); this.toggle(); break;
                case 'arrowright': this.skip(5); break;
                case 'arrowleft': this.skip(-5); break;
                case 'f': this.fullscreen(); break;
                case 'm': this.toggleMute(); break;
            }
            this.wakeControls();
        },

        /* --- up next --- */
        next() { return this.nextQueue[0] || null; },
        videoEnded() {
            if (this.next()) {
                this.upNext = true;
                this.countdown = 5;
                clearInterval(this._timer);
                this._timer = setInterval(() => {
                    this.countdown--;
                    if (this.countdown <= 0) this.goNext();
                }, 1000);
            }
        },
        cancelUpNext() {
            this.upNext = false;
            clearInterval(this._timer);
        },
        goNext() {
            const n = this.next();
            if (!n) return;
            clearInterval(this._timer);
            location.href = '/player?id=' + encodeURIComponent(n.id) + (cfg.ctx ? '&' + cfg.ctx : '');
        },

        /* --- trim --- */
        async runTrim() {
            this.trimError = ''; this.trimMsg = '';
            if (!this.trimStart.trim() || !this.trimEnd.trim()) {
                this.trimError = 'Enter both a start and an end time.'; return;
            }
            this.trimming = true;
            const r = await api('/api/media/trim', {
                id: cfg.id, start: this.trimStart.trim(), end: this.trimEnd.trim(),
            });
            this.trimming = false;
            if (r.ok) {
                this.duration = r.duration;
                this.trimMsg = 'Section removed. New duration: ' + fmtTime(r.duration) + '. Reloading…';
                setTimeout(() => location.reload(), 1200);
            } else {
                this.trimError = r.error || 'Trim failed.';
            }
        },
    }));

    /* Image viewer prev/next */
    Alpine.data('imageViewer', (siblings) => ({
        zoom: false,
        idx: -1,
        list() { return [{ src: null }, ...siblings]; },
        hasPrev() { return siblings.length > 0; },
        hasNext() { return siblings.length > 0; },
        prev() { this.step(-1); },
        nextImg() { this.step(1); },
        step(d) {
            if (siblings.length === 0) return;
            this.idx = (this.idx + d + siblings.length) % siblings.length;
            const target = siblings[this.idx];
            if (target) this.$refs.img.src = target.src;
        },
        handleKey(e) {
            if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) return;
            if (e.key === 'ArrowLeft') this.prev();
            if (e.key === 'ArrowRight') this.nextImg();
        },
    }));

    /* Upload form */
    Alpine.data('uploadForm', (maxFiles) => ({
        videoNames: [], imageNames: [],
        busy: false, message: '', hasError: false,
        busyDl: false, dlMessage: '', dlError: false,
        url: '', title: '',

        pickVideos(e) { this.videoNames = [...e.target.files].map(f => f.name); },
        pickImages(e) { this.imageNames = [...e.target.files].map(f => f.name); },

        async upload() {
            const total = this.videoNames.length + this.imageNames.length;
            if (total === 0) { this.hasError = true; this.message = 'Choose at least one file first.'; return; }
            if (total > maxFiles) { this.hasError = true; this.message = `Too many files (${total}). Max ${maxFiles} per batch.`; return; }
            this.busy = true; this.message = ''; this.hasError = false;
            const form = new FormData();
            (this.$refs.videoInput.files || []).forEach(f => form.append('videos[]', f));
            (this.$refs.imageInput.files || []).forEach(f => form.append('images[]', f));
            try {
                const res = await fetch('/api/upload', { method: 'POST', body: form });
                const json = await res.json().catch(() => ({}));
                if (res.ok && json.ok) {
                    this.message = `Uploaded ${json.created} item(s)` +
                        (json.errors?.length ? ` — skipped: ${json.errors.join('; ')}` : '.') +
                        ' Refreshing…';
                    setTimeout(() => location.href = '/', 900);
                } else {
                    this.hasError = true;
                    this.message = json.error || 'Upload failed.';
                }
            } catch {
                this.hasError = true;
                this.message = 'Network error during upload.';
            }
            this.busy = false;
        },

        async download() {
            this.busyDl = true; this.dlMessage = ''; this.dlError = false;
            const r = await api('/api/download', { url: this.url, title: this.title });
            this.busyDl = false;
            if (r.ok) {
                this.dlError = false;
                this.dlMessage = 'Download complete. Opening…';
                setTimeout(() => location.href = '/player?id=' + encodeURIComponent(r.item.id), 700);
            } else {
                this.dlError = true;
                this.dlMessage = r.error || 'Download failed.';
            }
        },
    }));
});
