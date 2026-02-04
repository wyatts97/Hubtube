const BaseAdapter = require('./base');

class RedTubeAdapter extends BaseAdapter {
    constructor() {
        super('RedTube', 'https://www.redtube.com');
    }

    async search(query, page = 1) {
        const searchUrl = `${this.baseUrl}/?search=${encodeURIComponent(query)}&page=${page}`;
        const $ = await this.fetchPage(searchUrl);
        
        const videos = [];
        
        $('.video-item, .videoBox').each((i, el) => {
            const $el = $(el);
            const sourceId = $el.attr('data-video-id') || $el.attr('data-id') || '';
            
            if (!sourceId) {
                // Try to extract from link
                const $link = $el.find('a').first();
                const href = $link.attr('href') || '';
                const match = href.match(/\/(\d+)/);
                if (match) {
                    // Use the matched ID
                }
            }
            
            if (!sourceId) return;
            
            const $img = $el.find('img').first();
            const $title = $el.find('.video-title, .title').first();
            const $duration = $el.find('.duration, .video-duration').first();
            const $views = $el.find('.views, .video-views').first();
            
            const durationText = $duration.text().trim();
            const duration = this.parseDuration(durationText);
            
            let views = 0;
            const viewsText = $views.text().trim();
            const viewsMatch = viewsText.match(/([\d.]+)\s*(M|K)?/i);
            if (viewsMatch) {
                views = parseFloat(viewsMatch[1]);
                if (viewsMatch[2]?.toUpperCase() === 'M') views *= 1000000;
                else if (viewsMatch[2]?.toUpperCase() === 'K') views *= 1000;
                views = Math.floor(views);
            }
            
            const video = this.standardizeResult({
                sourceId,
                title: $title.text().trim(),
                duration,
                thumbnail: $img.attr('data-src') || $img.attr('data-thumb_url') || $img.attr('src') || '',
                url: `${this.baseUrl}/${sourceId}`,
                embedUrl: `${this.baseUrl}/embed/${sourceId}`,
                embedCode: `<iframe src="${this.baseUrl}/embed/${sourceId}" frameborder="0" width="640" height="360" allowfullscreen></iframe>`,
                views
            });
            
            videos.push(video);
        });
        
        const hasNextPage = $('.pagination .next, .page-next').length > 0;
        const hasPrevPage = page > 1;
        
        return {
            site: 'redtube',
            query,
            page,
            hasNextPage,
            hasPrevPage,
            totalResults: videos.length,
            videos
        };
    }

    async getVideoDetails(videoId) {
        const videoUrl = `${this.baseUrl}/${videoId}`;
        const $ = await this.fetchPage(videoUrl);
        
        const title = $('h1.video-title').text().trim() || $('title').text().split(' - ')[0].trim();
        
        let duration = 0;
        const durationMeta = $('meta[property="video:duration"]').attr('content');
        if (durationMeta) {
            duration = parseInt(durationMeta);
        }
        
        const thumbnail = $('meta[property="og:image"]').attr('content') || '';
        
        const tags = [];
        $('.video-tags a, .tag-list a').each((i, el) => {
            tags.push($(el).text().trim());
        });
        
        const actors = [];
        $('.pornstars-list a, .video-pornstars a').each((i, el) => {
            actors.push($(el).text().trim());
        });
        
        return this.standardizeResult({
            sourceId: videoId,
            title,
            duration,
            thumbnail,
            url: videoUrl,
            embedUrl: `${this.baseUrl}/embed/${videoId}`,
            embedCode: `<iframe src="${this.baseUrl}/embed/${videoId}" frameborder="0" width="640" height="360" allowfullscreen></iframe>`,
            tags,
            actors
        });
    }
}

module.exports = new RedTubeAdapter();
