const BaseAdapter = require('./base');

class XVideosAdapter extends BaseAdapter {
    constructor() {
        super('XVideos', 'https://www.xvideos.com');
    }

    async search(query, page = 1) {
        const searchUrl = `${this.baseUrl}/?k=${encodeURIComponent(query)}&p=${page - 1}`;
        const $ = await this.fetchPage(searchUrl);
        
        const videos = [];
        
        $('.thumb-block').each((i, el) => {
            const $el = $(el);
            const $link = $el.find('.thumb a').first();
            const $img = $el.find('.thumb img');
            const $title = $el.find('.thumb-under .title a');
            const $duration = $el.find('.duration');
            const $metadata = $el.find('.metadata');
            
            const href = $link.attr('href') || '';
            const videoIdMatch = href.match(/video(\d+)/);
            const sourceId = videoIdMatch ? videoIdMatch[1] : '';
            
            if (!sourceId) return;
            
            const durationText = $duration.text().trim();
            const duration = this.parseDuration(durationText);
            
            // Extract views from metadata
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
        
        // Check for pagination
        const hasNextPage = $('.pagination .next-page').length > 0;
        const hasPrevPage = page > 1;
        
        return {
            site: 'xvideos',
            query,
            page,
            hasNextPage,
            hasPrevPage,
            totalResults: videos.length,
            videos
        };
    }

    async getVideoDetails(videoId) {
        const videoUrl = `${this.baseUrl}/video${videoId}/`;
        const $ = await this.fetchPage(videoUrl);
        
        const title = $('h2.page-title').text().trim() || $('title').text().split(' - ')[0].trim();
        
        // Get duration from video player script
        let duration = 0;
        const scriptContent = $('script').text();
        const durationMatch = scriptContent.match(/setVideoHLS\([^)]*'duration':\s*(\d+)/);
        if (durationMatch) {
            duration = parseInt(durationMatch[1]);
        }
        
        // Get thumbnail
        const thumbnail = $('meta[property="og:image"]').attr('content') || '';
        
        // Get tags
        const tags = [];
        $('.video-tags-list a').each((i, el) => {
            tags.push($(el).text().trim());
        });
        
        // Get actors/models
        const actors = [];
        $('.video-metadata .actor a').each((i, el) => {
            actors.push($(el).text().trim());
        });
        
        // Get views
        let views = 0;
        const viewsText = $('.video-metadata').text();
        const viewsMatch = viewsText.match(/([\d,]+)\s*views/i);
        if (viewsMatch) {
            views = parseInt(viewsMatch[1].replace(/,/g, ''));
        }
        
        return this.standardizeResult({
            sourceId: videoId,
            title,
            duration,
            thumbnail,
            url: videoUrl,
            embedUrl: `${this.baseUrl}/embedframe/${videoId}`,
            embedCode: `<iframe src="${this.baseUrl}/embedframe/${videoId}" frameborder="0" width="640" height="360" allowfullscreen></iframe>`,
            views,
            tags,
            actors
        });
    }
}

module.exports = new XVideosAdapter();
