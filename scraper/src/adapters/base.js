const fetch = require('node-fetch');
const cheerio = require('cheerio');

const USER_AGENTS = [
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
    'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:133.0) Gecko/20100101 Firefox/133.0',
];

class BaseAdapter {
    constructor(name, baseUrl) {
        this.name = name;
        this.baseUrl = baseUrl;
        this.enabled = true;
        
        // Common blocked/restricted page indicators
        this.blockedIndicators = [
            'access denied',
            'not available in your',
            'geo-restricted',
            'country is not',
            'region is not',
            'unavailable in your location',
            'restricted in your area',
            '403 forbidden',
            'captcha',
            'verify you are human',
            'please wait while we verify',
            'checking your browser'
        ];
    }

    getHeaders() {
        return {
            'User-Agent': USER_AGENTS[Math.floor(Math.random() * USER_AGENTS.length)],
            'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language': 'en-US,en;q=0.9',
            'Accept-Encoding': 'gzip, deflate, br',
            'Cache-Control': 'no-cache',
            'Sec-Fetch-Dest': 'document',
            'Sec-Fetch-Mode': 'navigate',
            'Sec-Fetch-Site': 'none',
            'Sec-Fetch-User': '?1',
            'Upgrade-Insecure-Requests': '1',
        };
    }

    async fetchPage(url, retries = 2) {
        let lastError = null;
        
        for (let attempt = 0; attempt <= retries; attempt++) {
            try {
                if (attempt > 0) {
                    await new Promise(r => setTimeout(r, 1000 * attempt));
                }
                
                const response = await fetch(url, {
                    headers: this.getHeaders(),
                    timeout: 20000,
                    redirect: 'follow',
                    compress: true,
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
            } catch (e) {
                lastError = e;
                if (e.code === 'BLOCKED') throw e;
            }
        }
        
        throw lastError;
    }
    
    checkIfBlocked(html, statusCode) {
        const htmlLower = html.toLowerCase();
        
        if (statusCode === 403) {
            return { blocked: true, reason: 'Access forbidden (403) - Your IP may be blocked or geo-restricted' };
        }
        if (statusCode === 451) {
            return { blocked: true, reason: 'Content unavailable for legal reasons (451) - Geo-restricted in your region' };
        }
        if (statusCode === 503 && (htmlLower.includes('cloudflare') || htmlLower.includes('captcha'))) {
            return { blocked: true, reason: 'Service unavailable (503) - Cloudflare protection detected' };
        }
        
        for (const indicator of this.blockedIndicators) {
            if (htmlLower.includes(indicator)) {
                return { 
                    blocked: true, 
                    reason: `Site returned a block/restriction page. Detected: "${indicator}". Your IP may be geo-blocked.`
                };
            }
        }
        
        // Only flag as blocked if truly tiny AND no doctype
        if (html.length < 200 && !htmlLower.includes('<!doctype') && !htmlLower.includes('<html')) {
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
        if (!seconds || isNaN(seconds)) return '0:00';
        seconds = Math.floor(seconds);
        const hours = Math.floor(seconds / 3600);
        const mins = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;
        if (hours > 0) {
            return `${hours}:${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
        }
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    }

    parseDuration(durationStr) {
        if (!durationStr) return 0;
        
        // Clean the string
        const cleaned = durationStr.trim().replace(/[^0-9:hms]/gi, '');
        
        // Handle "XhYmZs" format
        const hmsMatch = cleaned.match(/(\d+)h\s*(\d+)m\s*(\d+)s/i);
        if (hmsMatch) {
            return parseInt(hmsMatch[1]) * 3600 + parseInt(hmsMatch[2]) * 60 + parseInt(hmsMatch[3]);
        }
        const msMatch = cleaned.match(/(\d+)m\s*(\d+)s/i);
        if (msMatch) {
            return parseInt(msMatch[1]) * 60 + parseInt(msMatch[2]);
        }
        
        // Handle "H:MM:SS" or "M:SS" or "MM:SS" format
        const parts = durationStr.trim().split(':').map(s => parseInt(s.trim()));
        if (parts.some(isNaN)) return 0;
        
        if (parts.length === 3) {
            return parts[0] * 3600 + parts[1] * 60 + parts[2];
        } else if (parts.length === 2) {
            return parts[0] * 60 + parts[1];
        } else if (parts.length === 1) {
            return parts[0];
        }
        return 0;
    }

    /**
     * Clean and validate a thumbnail URL.
     * Ensures https, removes tracking params, validates it looks like an image URL.
     */
    cleanThumbnailUrl(url) {
        if (!url || typeof url !== 'string') return '';
        url = url.trim();
        
        // Skip data URIs and blank/placeholder images
        if (url.startsWith('data:') || url.includes('blank.gif') || url.includes('placeholder')) {
            return '';
        }
        
        // Ensure absolute URL
        if (url.startsWith('//')) {
            url = 'https:' + url;
        }
        
        // Must be http(s)
        if (!url.startsWith('http')) {
            return '';
        }
        
        // Replace THUMBNUM placeholder used by XNXX/XVideos for lazy-loaded thumbnails
        if (url.includes('THUMBNUM')) {
            url = url.replace(/THUMBNUM/g, '1');
        }
        
        return url;
    }

    standardizeResult(video) {
        const thumbnail = this.cleanThumbnailUrl(video.thumbnail);
        const thumbnailPreview = this.cleanThumbnailUrl(video.thumbnailPreview);
        
        return {
            sourceId: video.sourceId || '',
            sourceSite: this.name.toLowerCase().replace(/\s/g, ''),
            title: (video.title || 'Untitled').trim(),
            duration: video.duration || 0,
            durationFormatted: this.formatDuration(video.duration),
            thumbnail,
            thumbnailPreview: thumbnailPreview || null,
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
