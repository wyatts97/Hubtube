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
