const BaseAdapter = require('./base');

class YouPornAdapter extends BaseAdapter {
    constructor() {
        super('YouPorn', 'https://www.youporn.com');
    }

    async search(query, page = 1) {
        const searchUrl = `${this.baseUrl}/search/?query=${encodeURIComponent(query)}&page=${page}`;
        const $ = await this.fetchPage(searchUrl);
        
        const videos = [];
        const seenIds = new Set();
        
        // YouPorn uses div.video-box or similar containers
        $('div.video-box, .video-listing .video-item').each((i, el) => {
            const $el = $(el);
            const $link = $el.find('a[href*="/watch/"]').first();
            const href = $link.attr('href') || '';
            
            // Extract video ID from URL - format: /watch/12345/title
            const videoIdMatch = href.match(/\/watch\/(\d+)/);
            const sourceId = videoIdMatch ? videoIdMatch[1] : '';
            
            if (!sourceId || seenIds.has(sourceId)) return;
            seenIds.add(sourceId);
            
            const $img = $el.find('img').first();
            const $title = $el.find('.video-box-title a, .video-title a').first();
            const $duration = $el.find('.video-duration, .duration').first();
            const $views = $el.find('.video-views, .views').first();
            const $rating = $el.find('.video-rating .percent, .rating').first();
            
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
            
            let rating = 0;
            const ratingText = $rating.text().trim() || '';
            const ratingMatch = ratingText.match(/(\d+)/);
            if (ratingMatch) {
                rating = parseInt(ratingMatch[1]);
            }
            
            const video = this.standardizeResult({
                sourceId,
                title,
                duration,
                thumbnail,
                url: href.startsWith('http') ? href : `${this.baseUrl}${href}`,
                embedUrl: `${this.baseUrl}/embed/${sourceId}`,
                embedCode: `<iframe src="${this.baseUrl}/embed/${sourceId}" frameborder="0" width="640" height="360" allowfullscreen></iframe>`,
                views,
                rating
            });
            
            videos.push(video);
        });
        
        const hasNextPage = $('a.next, .pagination .page-next').length > 0;
        const hasPrevPage = page > 1;
        
        return {
            site: 'youporn',
            query,
            page,
            hasNextPage,
            hasPrevPage,
            totalResults: videos.length,
            videos
        };
    }

    async getVideoDetails(videoId) {
        const videoUrl = `${this.baseUrl}/watch/${videoId}`;
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
        $('.pornstars a, .video-pornstars a').each((i, el) => {
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

module.exports = new YouPornAdapter();
