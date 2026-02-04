const BaseAdapter = require('./base');

class RedTubeAdapter extends BaseAdapter {
    constructor() {
        super('RedTube', 'https://www.redtube.com');
    }

    async search(query, page = 1) {
        const searchUrl = `${this.baseUrl}/?search=${encodeURIComponent(query)}&page=${page}`;
        const $ = await this.fetchPage(searchUrl);
        
        const videos = [];
        const seenIds = new Set();
        
        // RedTube uses li.videoblock or div.video_block
        $('li.videoblock, .video_block_wrapper').each((i, el) => {
            const $el = $(el);
            const $link = $el.find('a[href*="/"]').first();
            const href = $link.attr('href') || '';
            
            // Extract video ID from URL - format: /12345/title
            const videoIdMatch = href.match(/\/(\d+)/);
            const sourceId = videoIdMatch ? videoIdMatch[1] : '';
            
            if (!sourceId || seenIds.has(sourceId)) return;
            seenIds.add(sourceId);
            
            const $img = $el.find('img').first();
            const $title = $el.find('.video_title, .video-title a, a[title]').first();
            const $duration = $el.find('.duration, .video_duration').first();
            const $views = $el.find('.video_count, .views').first();
            
            let title = $title.attr('title') || $title.text().trim() || 'Untitled';
            
            const durationText = $duration.text().trim();
            const duration = this.parseDuration(durationText);
            
            // Get thumbnail
            let thumbnail = $img.attr('data-thumb_url') || $img.attr('data-src') || $img.attr('src') || '';
            
            let views = 0;
            const viewsText = $views.text().trim() || '';
            const viewsMatch = viewsText.match(/([\d.]+)\s*(M|K)?/i);
            if (viewsMatch) {
                views = parseFloat(viewsMatch[1]);
                if (viewsMatch[2]?.toUpperCase() === 'M') views *= 1000000;
                else if (viewsMatch[2]?.toUpperCase() === 'K') views *= 1000;
                views = Math.floor(views);
            }
            
            const video = this.standardizeResult({
                sourceId,
                title,
                duration,
                thumbnail,
                url: `${this.baseUrl}/${sourceId}`,
                embedUrl: `${this.baseUrl}/embed/${sourceId}`,
                embedCode: `<iframe src="${this.baseUrl}/embed/${sourceId}" frameborder="0" width="640" height="360" allowfullscreen></iframe>`,
                views
            });
            
            videos.push(video);
        });
        
        const hasNextPage = $('a.page_next, .pagination .next').length > 0;
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
