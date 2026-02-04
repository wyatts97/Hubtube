const BaseAdapter = require('./base');

class XHamsterAdapter extends BaseAdapter {
    constructor() {
        super('xHamster', 'https://xhamster.com');
    }

    async search(query, page = 1) {
        const searchUrl = `${this.baseUrl}/search/${encodeURIComponent(query)}?page=${page}`;
        const $ = await this.fetchPage(searchUrl);
        
        const videos = [];
        const seenIds = new Set();
        
        // xHamster uses article.thumb-list__item or div.video-thumb
        $('article.thumb-list__item, div.thumb-list__item').each((i, el) => {
            const $el = $(el);
            const $link = $el.find('a[href*="/videos/"]').first();
            const href = $link.attr('href') || '';
            
            // Extract video ID from URL - format: /videos/title-123456
            const videoIdMatch = href.match(/videos\/[^\/]+-(\d+)/);
            const sourceId = videoIdMatch ? videoIdMatch[1] : '';
            
            if (!sourceId || seenIds.has(sourceId)) return;
            seenIds.add(sourceId);
            
            const $img = $el.find('img').first();
            const $title = $el.find('a.video-thumb-info__name, .video-thumb__title a').first();
            const $duration = $el.find('.thumb-image-container__duration, .duration').first();
            const $views = $el.find('.video-thumb-info__views, .views').first();
            
            let title = $title.attr('title') || $title.text().trim() || 'Untitled';
            
            const durationText = $duration.text().trim();
            const duration = this.parseDuration(durationText);
            
            // Get thumbnail
            let thumbnail = $img.attr('data-src') || $img.attr('src') || '';
            
            // Parse views
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
                url: href.startsWith('http') ? href : `${this.baseUrl}${href}`,
                embedUrl: `${this.baseUrl}/xembed.php?video=${sourceId}`,
                embedCode: `<iframe src="${this.baseUrl}/xembed.php?video=${sourceId}" frameborder="0" width="640" height="360" allowfullscreen></iframe>`,
                views
            });
            
            videos.push(video);
        });
        
        const hasNextPage = $('a.next, .pager__button--next').length > 0;
        const hasPrevPage = page > 1;
        
        return {
            site: 'xhamster',
            query,
            page,
            hasNextPage,
            hasPrevPage,
            totalResults: videos.length,
            videos
        };
    }

    async getVideoDetails(videoId) {
        const videoUrl = `${this.baseUrl}/videos/${videoId}`;
        const $ = await this.fetchPage(videoUrl);
        
        const title = $('h1.with-player-container').text().trim() || $('title').text().split(' - ')[0].trim();
        
        let duration = 0;
        const durationMeta = $('meta[itemprop="duration"]').attr('content');
        if (durationMeta) {
            const match = durationMeta.match(/PT(\d+)M(\d+)S/);
            if (match) {
                duration = parseInt(match[1]) * 60 + parseInt(match[2]);
            }
        }
        
        const thumbnail = $('meta[property="og:image"]').attr('content') || '';
        
        const tags = [];
        $('.categories-container a, .video-tag').each((i, el) => {
            tags.push($(el).text().trim());
        });
        
        const actors = [];
        $('.pornstar-label a').each((i, el) => {
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

module.exports = new XHamsterAdapter();
