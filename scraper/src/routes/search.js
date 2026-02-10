const express = require('express');
const router = express.Router();
const adapters = require('../adapters');
const cache = require('../utils/cache');

router.get('/:site', async (req, res) => {
    try {
        const { site } = req.params;
        const { q: query, page = 1 } = req.query;

        if (!query) {
            return res.status(400).json({ error: 'Query parameter "q" is required' });
        }

        const adapter = adapters[site];
        if (!adapter) {
            return res.status(404).json({ 
                error: `Site "${site}" not found`,
                availableSites: Object.keys(adapters)
            });
        }

        if (!adapter.enabled) {
            return res.status(400).json({ error: `Site "${site}" is currently disabled` });
        }

        // Check cache first
        const cacheKey = `${site}:${query}:${page}`;
        const cached = cache.get(cacheKey);
        if (cached) {
            return res.json({ ...cached, cached: true });
        }

        // Fetch from adapter
        const results = await adapter.search(query, parseInt(page));
        
        // Cache the results
        cache.set(cacheKey, results);

        res.json({ ...results, cached: false });
    } catch (error) {
        console.error('Search error:', error);
        
        // Check if this is a blocked/geo-restricted error
        if (error.code === 'BLOCKED') {
            return res.status(403).json({ 
                error: 'Site access blocked',
                message: error.message,
                blocked: true,
                details: error.details,
                suggestion: 'This site may be geo-restricted in your region (common in Texas). Try using a VPN or proxy, or try a different site.'
            });
        }
        
        res.status(500).json({ 
            error: 'Failed to fetch videos',
            message: error.message 
        });
    }
});

// Multi-page search: fetches pages fromPage..toPage and combines results
router.get('/:site/multi', async (req, res) => {
    try {
        const { site } = req.params;
        const { q: query, from = 1, to = 3 } = req.query;

        if (!query) {
            return res.status(400).json({ error: 'Query parameter "q" is required' });
        }

        const adapter = adapters[site];
        if (!adapter) {
            return res.status(404).json({ error: `Site "${site}" not found` });
        }
        if (!adapter.enabled) {
            return res.status(400).json({ error: `Site "${site}" is currently disabled` });
        }

        const fromPage = Math.max(1, parseInt(from));
        const toPage = Math.min(fromPage + 9, parseInt(to)); // cap at 10 pages per request
        const allVideos = [];
        let lastPage = fromPage;
        let hasMore = false;

        for (let page = fromPage; page <= toPage; page++) {
            const cacheKey = `${site}:${query}:${page}`;
            let results = cache.get(cacheKey);

            if (!results) {
                results = await adapter.search(query, page);
                cache.set(cacheKey, results);
            }

            if (results.videos) {
                allVideos.push(...results.videos);
            }
            lastPage = page;
            hasMore = results.hasNextPage || false;

            if (!hasMore) break;

            // Small delay between pages
            if (page < toPage) {
                await new Promise(r => setTimeout(r, 300));
            }
        }

        res.json({
            site,
            query,
            page: fromPage,
            lastPage,
            hasNextPage: hasMore,
            hasPrevPage: fromPage > 1,
            totalResults: allVideos.length,
            videos: allVideos,
            cached: false,
        });
    } catch (error) {
        console.error('Multi-page search error:', error);
        if (error.code === 'BLOCKED') {
            return res.status(403).json({
                error: 'Site access blocked',
                message: error.message,
                blocked: true,
            });
        }
        res.status(500).json({ error: 'Failed to fetch videos', message: error.message });
    }
});

router.get('/:site/video/:videoId', async (req, res) => {
    try {
        const { site, videoId } = req.params;

        const adapter = adapters[site];
        if (!adapter) {
            return res.status(404).json({ error: `Site "${site}" not found` });
        }

        // Check cache
        const cacheKey = `${site}:video:${videoId}`;
        const cached = cache.get(cacheKey);
        if (cached) {
            return res.json({ ...cached, cached: true });
        }

        const details = await adapter.getVideoDetails(videoId);
        
        cache.set(cacheKey, details);

        res.json({ ...details, cached: false });
    } catch (error) {
        console.error('Video details error:', error);
        
        // Check if this is a blocked/geo-restricted error
        if (error.code === 'BLOCKED') {
            return res.status(403).json({ 
                error: 'Site access blocked',
                message: error.message,
                blocked: true,
                suggestion: 'This site may be geo-restricted in your region.'
            });
        }
        
        res.status(500).json({ 
            error: 'Failed to fetch video details',
            message: error.message 
        });
    }
});

module.exports = router;
