const BaseAdapter = require('./base');

class XNXXAdapter extends BaseAdapter {
    constructor() {
        super('XNXX', 'https://www.xnxx.com');
    }

    async search(query, page = 1) {
        const searchUrl = `${this.baseUrl}/search/${encodeURIComponent(query)}/${page}`;
        const $ = await this.fetchPage(searchUrl);
        
        const videos = [];
        
        $('.thumb-block, .mozaique .thumb').each((i, el) => {
            const $el = $(el);
            const $link = $el.find('a').first();
            const href = $link.attr('href') || '';
            
            // Extract video ID from URL (format: /video-xxxxx/title)
            const videoIdMatch = href.match(/video-([a-z0-9]+)/i);
            const sourceId = videoIdMatch ? videoIdMatch[1] : '';
            
            if (!sourceId) return;
            
            const $img = $el.find('img').first();
            const $title = $el.find('.thumb-under p a, .title').first();
            const $duration = $el.find('.duration, .video-duration').first();
            const $metadata = $el.find('.metadata, .video-metadata').first();
            
            const durationText = $duration.text().trim();
            const duration = this.parseDuration(durationText);
            
            // Parse views
            let views = 0;
            const viewsText = $metadata.text();
            const viewsMatch = viewsText.match(/([\d.]+)\s*(M|K)?/i);
            if (viewsMatch) {
                views = parseFloat(viewsMatch[1]);
                if (viewsMatch[2]?.toUpperCase() === 'M') views *= 1000000;
                else if (viewsMatch[2]?.toUpperCase() === 'K') views *= 1000;
                views = Math.floor(views);
            }
            
            const video = this.standardizeResult({
                sourceId,
                title: $title.attr('title') || $title.text().trim(),
                duration,
                thumbnail: $img.attr('data-src') || $img.attr('src') || '',
                url: `${this.baseUrl}${href}`,
                embedUrl: `${this.baseUrl}/embedframe/${sourceId}`,
                embedCode: `<iframe src="${this.baseUrl}/embedframe/${sourceId}" frameborder="0" width="640" height="360" allowfullscreen></iframe>`,
                views
            });
            
            videos.push(video);
        });
        
        const hasNextPage = $('.pagination .next, .nav-page-next').length > 0;
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
