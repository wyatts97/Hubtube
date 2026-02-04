const fetch = require('node-fetch');
const cheerio = require('cheerio');

class BaseAdapter {
    constructor(name, baseUrl) {
        this.name = name;
        this.baseUrl = baseUrl;
        this.enabled = true;
        this.headers = {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language': 'en-US,en;q=0.5',
        };
    }

    async fetchPage(url) {
        const response = await fetch(url, {
            headers: this.headers,
            timeout: 15000
        });
        const html = await response.text();
        return cheerio.load(html);
    }

    async search(query, page = 1) {
        throw new Error('search() must be implemented by adapter');
    }

    async getVideoDetails(videoId) {
        throw new Error('getVideoDetails() must be implemented by adapter');
    }

    formatDuration(seconds) {
        if (!seconds) return '0:00';
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    }

    parseDuration(durationStr) {
        if (!durationStr) return 0;
        const parts = durationStr.split(':').map(Number);
        if (parts.length === 3) {
            return parts[0] * 3600 + parts[1] * 60 + parts[2];
        } else if (parts.length === 2) {
            return parts[0] * 60 + parts[1];
        }
        return 0;
    }

    standardizeResult(video) {
        return {
            sourceId: video.sourceId || '',
            sourceSite: this.name.toLowerCase().replace(/\s/g, ''),
            title: video.title || 'Untitled',
            duration: video.duration || 0,
            durationFormatted: this.formatDuration(video.duration),
            thumbnail: video.thumbnail || '',
            thumbnailPreview: video.thumbnailPreview || null,
            url: video.url || '',
            embedUrl: video.embedUrl || '',
            embedCode: video.embedCode || '',
            views: video.views || 0,
            rating: video.rating || 0,
            tags: video.tags || [],
            actors: video.actors || [],
            uploadDate: video.uploadDate || null
        };
    }
}

module.exports = BaseAdapter;
