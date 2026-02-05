const BaseAdapter = require('./base');

class XNXXAdapter extends BaseAdapter {
    constructor() {
        super('XNXX', 'https://www.xnxx.com');
    }

    async search(query, page = 1) {
        const searchUrl = `${this.baseUrl}/search/${encodeURIComponent(query)}/${page}`;
        const $ = await this.fetchPage(searchUrl);
        
        const videos = [];
        const seenIds = new Set();
        
        // Use only .thumb-block which is the main container
        $('.mozaique .thumb-block').each((i, el) => {
            const $el = $(el);
            const $thumbInside = $el.find('.thumb-inside');
            const $thumbUnder = $el.find('.thumb-under');
            
            const $link = $thumbInside.find('a').first();
            const href = $link.attr('href') || '';
            
            // Extract video ID from URL (format: /video-xxxxx/title)
            const videoIdMatch = href.match(/video-([a-z0-9]+)/i);
            const sourceId = videoIdMatch ? videoIdMatch[1] : '';
            
            // Skip if no ID or already seen (dedup)
            if (!sourceId || seenIds.has(sourceId)) return;
            seenIds.add(sourceId);
            
            const $img = $thumbInside.find('img').first();
            const $title = $thumbUnder.find('a').first();
            const $duration = $thumbInside.find('.duration').first();
            const $metadata = $thumbUnder.find('.metadata').first();
            
            // Get title from title attribute or text
            let title = $title.attr('title') || $title.text().trim() || 'Untitled';
            
            const durationText = $duration.text().trim();
            const duration = this.parseDuration(durationText);
            
            // Get thumbnail - prefer data-src for lazy loaded images
            let thumbnail = $img.attr('data-src') || $img.attr('src') || '';
            
            // XNXX uses THUMBNUM as a JS placeholder for lazy-loaded thumbnails.
            // Replace it with a concrete number (1) to get a valid thumbnail URL.
            if (thumbnail.includes('THUMBNUM')) {
                thumbnail = thumbnail.replace(/THUMBNUM/g, '1');
            }
            
            // Parse views from metadata
            let views = 0;
            const metaText = $metadata.text() || '';
            const viewsMatch = metaText.match(/([\d.]+)\s*(M|K)?/i);
            if (viewsMatch) {
                views = parseFloat(viewsMatch[1]);
                if (viewsMatch[2]?.toUpperCase() === 'M') views *= 1000000;
                else if (viewsMatch[2]?.toUpperCase() === 'K') views *= 1000;
                views = Math.floor(views);
            }
            
            // Clean THUMBNUM from href too (XNXX sometimes includes it in the URL path)
            const cleanHref = href.replace(/\/THUMBNUM\//g, '/');
            
            const video = this.standardizeResult({
                sourceId,
                title,
                duration,
                thumbnail,
                url: `${this.baseUrl}${cleanHref}`,
                embedUrl: `${this.baseUrl}/embedframe/${sourceId}`,
                embedCode: `<iframe src="${this.baseUrl}/embedframe/${sourceId}" frameborder="0" width="640" height="360" allowfullscreen></iframe>`,
                views
            });
            
            videos.push(video);
        });
        
        const hasNextPage = $('.pagination .next-page, .nav-page-next').length > 0;
        const hasPrevPage = page > 1;
        
        return {
            site: 'xnxx',
            query,
            page,
            hasNextPage,
            hasPrevPage,
            totalResults: videos.length,
            videos
        };
    }

    async getVideoDetails(videoId) {
        const videoUrl = `${this.baseUrl}/video-${videoId}/`;
        const $ = await this.fetchPage(videoUrl);
        
        const title = $('h1.video-title, .video-title').text().trim() || $('title').text().split(' - ')[0].trim();
        
        let duration = 0;
        const durationText = $('.video-duration, .metadata .duration').text().trim();
        duration = this.parseDuration(durationText);
        
        const thumbnail = $('meta[property="og:image"]').attr('content') || '';
        
        const tags = [];
        $('.video-tags a, .metadata-row a').each((i, el) => {
            tags.push($(el).text().trim());
        });
        
        return this.standardizeResult({
            sourceId: videoId,
            title,
            duration,
            thumbnail,
            url: videoUrl,
            embedUrl: `${this.baseUrl}/embedframe/${videoId}`,
            embedCode: `<iframe src="${this.baseUrl}/embedframe/${videoId}" frameborder="0" width="640" height="360" allowfullscreen></iframe>`,
            tags
        });
    }
}

module.exports = new XNXXAdapter();
