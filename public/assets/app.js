(() => {
    const toast = (message, isError = false) => {
        const element = document.getElementById('toast');
        if (!element) return;
        element.textContent = message;
        element.classList.toggle('toast-error', isError);
        element.classList.add('toast-show');
        window.clearTimeout(element._hideTimer);
        element._hideTimer = window.setTimeout(() => element.classList.remove('toast-show'), 3600);
    };

    const request = async (url, data, method = 'POST') => {
        const response = await fetch(url, {
            method,
            headers: data instanceof FormData ? {} : { 'Content-Type': 'application/json' },
            body: method === 'GET' ? undefined : (data instanceof FormData ? data : JSON.stringify(data))
        });
        let result;
        try { result = await response.json(); }
        catch { throw new Error('The server returned an unreadable response.'); }
        if (!response.ok || !result.success) throw new Error(result.message || 'The request failed.');
        return result;
    };

    window.toggleFavorite = async (id, type, button) => {
        try {
            const result = await request('/api/media/update', { action: 'favorite', id, type });
            button.textContent = result.favorite ? '♥' : '♡';
            button.classList.toggle('text-cyan-400', result.favorite);
            button.classList.toggle('text-neutral-400', !result.favorite);
            button.setAttribute('aria-label', result.favorite ? 'Remove from favorites' : 'Add to favorites');
            toast(result.favorite ? 'Added to favorites.' : 'Removed from favorites.');
        } catch (error) { toast(error.message, true); }
    };

    window.uploadManager = () => ({
        busy: false,
        message: '',
        selected: 'No files selected',
        init() {
            const input = document.getElementById('upload-files');
            input?.addEventListener('change', () => {
                this.selected = input.files.length ? `${input.files.length} file(s) selected` : 'No files selected';
            });
        },
        async uploadLocal(form) {
            this.busy = true;
            this.message = '';
            try {
                const result = await request('/api/upload', new FormData(form));
                this.message = result.message;
                toast(result.errors?.length ? `${result.message} ${result.errors.join(' ')}` : result.message, !!result.errors?.length);
                window.setTimeout(() => { window.location.href = '/'; }, 900);
            } catch (error) {
                this.message = error.message;
                toast(error.message, true);
            } finally { this.busy = false; }
        }
    });

    window.downloadManager = () => ({
        url: '',
        busy: false,
        message: '',
        async downloadUrl() {
            this.busy = true;
            this.message = '';
            try {
                const result = await request('/api/download', { url: this.url });
                this.message = result.message;
                toast(result.message);
                window.setTimeout(() => { window.location.href = `/player?id=${encodeURIComponent(result.item.id)}&type=video`; }, 700);
            } catch (error) {
                this.message = error.message;
                toast(error.message, true);
            } finally { this.busy = false; }
        }
    });

    window.playlistManager = () => ({
        async createPlaylist(form) {
            const values = Object.fromEntries(new FormData(form).entries());
            try {
                const result = await request('/api/media/update', { action: 'playlist_create', ...values });
                toast('Playlist created.');
                window.location.href = `/playlists?id=${encodeURIComponent(result.playlist.id)}`;
            } catch (error) { toast(error.message, true); }
        },
        async deletePlaylist(id) {
            if (!window.confirm('Delete this playlist? Media items will not be affected.')) return;
            try {
                await request('/api/media/update', { action: 'playlist_delete', id });
                toast('Playlist deleted.');
                window.location.href = '/playlists';
            } catch (error) { toast(error.message, true); }
        }
    });

    window.mediaSettings = (initial) => ({
        item: initial,
        title: initial.title || '',
        tags: (initial.tags || []).join(', '),
        openSettings: false,
        busy: false,
        trimStart: '',
        trimEnd: '',
        async save() {
            this.busy = true;
            try {
                const result = await request('/api/media/update', {
                    id: this.item.id, type: this.item.type, title: this.title, tags: this.tags
                });
                this.item = result.item;
                toast('Media details saved.');
                window.setTimeout(() => window.location.reload(), 500);
            } catch (error) { toast(error.message, true); }
            finally { this.busy = false; }
        },
        async favorite() {
            try {
                const result = await request('/api/media/update', { action: 'favorite', id: this.item.id, type: this.item.type });
                this.item.favorite = result.favorite;
                toast(result.favorite ? 'Added to favorites.' : 'Removed from favorites.');
            } catch (error) { toast(error.message, true); }
        },
        async trim() {
            if (!window.confirm('Remove the selected section from this video? This replaces the stored video.')) return;
            this.busy = true;
            try {
                await request('/api/media/trim', { id: this.item.id, start: this.trimStart, end: this.trimEnd });
                toast('Video section removed.');
                window.location.reload();
            } catch (error) { toast(error.message, true); }
            finally { this.busy = false; }
        },
        async remove() {
            if (!window.confirm('Permanently delete this media file and its library entry?')) return;
            this.busy = true;
            try {
                await request('/api/media/delete', { id: this.item.id, type: this.item.type });
                window.location.href = this.item.type === 'image' ? '/gallery' : '/';
            } catch (error) { toast(error.message, true); this.busy = false; }
        }
    });

    window.settingsForm = (settings) => ({
        settings,
        busy: false,
        async save() {
            this.busy = true;
            try {
                const result = await request('/api/settings', this.settings);
                this.settings = result.settings;
                toast(result.message);
            } catch (error) { toast(error.message, true); }
            finally { this.busy = false; }
        }
    });

    window.videoControls = (item, next, context) => ({
        item, next, context,
        playing: false, currentTime: 0, duration: 0, volume: 1,
        showNext: false, countdown: 5, timer: null, openSettings: false,
        init() {
            this.$watch('volume', () => this.setVolume());
            window.addEventListener('keydown', (event) => {
                if (event.target.matches('input, textarea, select')) return;
                if (event.code === 'Space') { event.preventDefault(); this.togglePlay(); }
                else if (event.key === 'ArrowLeft') this.$refs.video.currentTime = Math.max(0, this.currentTime - 5);
                else if (event.key === 'ArrowRight') this.$refs.video.currentTime = Math.min(this.duration, this.currentTime + 5);
                else if (event.key.toLowerCase() === 'f') this.fullscreen();
                else if (event.key.toLowerCase() === 'm') this.volume = this.volume ? 0 : 1;
            });
        },
        syncTime() {
            this.currentTime = this.$refs.video.currentTime || 0;
            this.duration = this.$refs.video.duration || 0;
        },
        togglePlay() {
            if (this.$refs.video.paused) this.$refs.video.play().catch(() => toast('Unable to play this video.', true));
            else this.$refs.video.pause();
        },
        seek() { this.$refs.video.currentTime = Number(this.currentTime); },
        setVolume() { if (this.$refs.video) this.$refs.video.volume = Number(this.volume); },
        fullscreen() {
            const element = this.$refs.video.parentElement;
            if (document.fullscreenElement) document.exitFullscreen();
            else element.requestFullscreen?.();
        },
        timeLabel(time) {
            if (!Number.isFinite(time)) return '0:00';
            const seconds = Math.floor(time);
            const minutes = Math.floor(seconds / 60);
            const remainder = String(seconds % 60).padStart(2, '0');
            return minutes >= 60
                ? `${Math.floor(minutes / 60)}:${String(minutes % 60).padStart(2, '0')}:${remainder}`
                : `${minutes}:${remainder}`;
        },
        onEnded() {
            if (!this.next) return;
            this.showNext = true;
            this.countdown = 5;
            this.timer = window.setInterval(() => {
                this.countdown -= 1;
                if (this.countdown <= 0) this.playNext();
            }, 1000);
        },
        cancelNext() {
            window.clearInterval(this.timer);
            this.showNext = false;
            this.$refs.video.currentTime = 0;
            this.$refs.video.play().catch(() => {});
        },
        playNext() {
            window.clearInterval(this.timer);
            if (this.next) window.location.href = `/player?id=${encodeURIComponent(this.next.id)}&type=video${this.context}`;
        }
    });
})();
