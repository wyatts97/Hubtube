const BaseAdapter = require('./base');

class PornHubAdapter extends BaseAdapter {
    constructor() {
        super('PornHub', 'https://www.pornhub.com');
    }

    async search(query, page = 1) {
        const searchUrl = `${this.baseUrl}/video/search?search=${encodeURIComponent(query)}&page=${page}`;
        const $ = await this.fetchPage(searchUrl);
        
        const videos = [];
        
        $('.pcVideoListItem, .videoBox').each((i, el) => {
            const $el = $(el);
            const sourceId = $el.attr('data-video-vkey') || $el.attr('data-id') || '';
            
            if (!sourceId) return;
            
            const $link = $el.find('a.videoPreviewBg, a.linkVideoThumb').first();
            const $img = $el.find('img.thumb, img').first();
            const $title = $el.find('.title a, .videoTitle a').first();
            const $duration = $el.find('.duration, .videoDuration').first();
            const $views = $el.find('.views var, .videoViews var').first();
            const $rating = $el.find('.rating-container .value, .videoRating .value').first();
            
            const durationText = $duration.text().trim();
            const duration = this.parseDuration(durationText);
            
            // Parse views
            let views = 0;
            const viewsText = $views.text().trim();
            const viewsMatch = viewsText.match(/([\d.]+)\s*(M|K)?/i);
            if (viewsMatch) {
                views = parseFloat(viewsMatch[1]);
                if (viewsMatch[2]?.toUpperCase() === 'M') views *= 1000000;
                else if (viewsMatch[2]?.toUpperCase() === 'K') views *= 1000;
                views = Math.floor(views);
            }
            
            // Parse rating
            let rating = 0;
            const ratingText = $rating.text().trim();
            const ratingMatch = ratingText.match(/(\d+)/);
            if (ratingMatch) {
                rating = parseInt(ratingMatch[1]);
            }
            
            const video = this.standardizeResult({
                sourceId,
                title: $title.attr('title') || $title.text().trim(),
                duration,
                thumbnail: $img.attr('data-src') || $img.attr('data-thumb_url') || $img.attr('src') || '',
                url: `${this.baseUrl}/view_video.php?viewkey=${sourceId}`,
                embedUrl: `${this.baseUrl}/embed/${sourceId}`,
                embedCode: `<iframe src="${this.baseUrl}/embed/${sourceId}" frameborder="0" width="640" height="360" allowfullscreen></iframe>`,
                views,
                rating
            });
            
            videos.push(video);
        });
        
        // Check for pagination
        const hasNextPage = $('.pagination3 .page_next').length > 0;
        const hasPrevPage = page > 1;
        
        return {
            site: 'pornhub',
            query,
            page,
            hasNextPage,
            hasPrevPage,
            totalResults: videos.length,
            videos
        };
    }

    async getVideoDetails(videoId) {
        const videoUrl = `${this.baseUrl}/view_video.php?viewkey=${videoId}`;
        const $ = await this.fetchPage(videoUrl);
        
        const title = $('h1.title span').text().trim() || $('title').text().split(' - ')[0].trim();
        
        // Get duration from meta or player
        let duration = 0;
        const durationMeta = $('meta[property="video:duration"]').attr('content');
        if (durationMeta) {
            duration = parseInt(durationMeta);
        }
        
        // Get thumbnail
        const thumbnail = $('meta[property="og:image"]').attr('content') || '';
        
        // Get tags
        const tags = [];
        $('.categoriesWrapper a, .tagsWrapper a').each((i, el) => {
            tags.push($(el).text().trim());
        });
        
        // Get actors/pornstars
        const actors = [];
        $('.pornstarsWrapper a').each((i, el) => {
            actors.push($(el).text().trim());
        });
        
        // Get views
        let views = 0;
        const viewsText = $('.count').first().text();
        const viewsMatch = viewsText.match(/([\d,]+)/);
        if (viewsMatch) {
            views = parseInt(viewsMatch[1].replace(/,/g, ''));
        }
        
        // Get rating
        let rating = 0;
        const ratingText = $('.percent').text();
        const ratingMatch = ratingText.match(/(\d+)/);
        if (ratingMatch) {
            rating = parseInt(ratingMatch[1]);
        }
        
        return this.standardizeResult({
            sourceId: videoId,
            title,
            duration,
            thumbnail,
            url: videoUrl,
            embedUrl: `${this.baseUrl}/embed/${videoId}`,
            embedCode: `<iframe src="${this.baseUrl}/embed/${videoId}" frameborder="0" width="640" height="360" allowfullscreen></iframe>`,
            views,
            rating,
            tags,
            actors
        });
    }
}

module.exports = new PornHubAdapter();
