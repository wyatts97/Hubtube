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
        
        // Common blocked/restricted page indicators
        this.blockedIndicators = [
            'access denied',
            'blocked',
            'forbidden',
            'not available in your',
            'geo-restricted',
            'country is not',
            'region is not',
            'unavailable in your location',
            'content not available',
            'restricted in your area',
            '403 forbidden',
            'captcha',
            'verify you are human',
            'cloudflare',
            'ddos protection',
            'please wait while we verify',
            'checking your browser'
        ];
    }

    async fetchPage(url) {
        const response = await fetch(url, {
            headers: this.headers,
            timeout: 15000,
            redirect: 'follow'
        });
        
        const html = await response.text();
        
        // Check for blocked/restricted responses
        const blockStatus = this.checkIfBlocked(html, response.status);
        if (blockStatus.blocked) {
            const error = new Error(blockStatus.reason);
            error.code = 'BLOCKED';
            error.details = blockStatus;
            throw error;
        }
        
        return cheerio.load(html);
    }
    
    checkIfBlocked(html, statusCode) {
        const htmlLower = html.toLowerCase();
        
        // Check HTTP status codes
        if (statusCode === 403) {
            return { blocked: true, reason: 'Access forbidden (403) - Your IP may be blocked or geo-restricted' };
        }
        if (statusCode === 451) {
            return { blocked: true, reason: 'Content unavailable for legal reasons (451) - Geo-restricted in your region' };
        }
        if (statusCode === 503) {
            return { blocked: true, reason: 'Service unavailable (503) - Site may be blocking automated requests' };
        }
        
        // Check for common block indicators in HTML
        for (const indicator of this.blockedIndicators) {
            if (htmlLower.includes(indicator)) {
                return { 
                    blocked: true, 
                    reason: `Site returned a block/restriction page. Detected: "${indicator}". Your IP may be geo-blocked (common in Texas for adult sites).`
                };
            }
        }
        
        // Check if page is suspiciously small (likely a block page)
        if (html.length < 500 && !htmlLower.includes('<!doctype')) {
            return { blocked: true, reason: 'Received unusually small response - possible block page' };
        }
        
        return { blocked: false };
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
