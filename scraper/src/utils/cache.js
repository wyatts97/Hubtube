const NodeCache = require('node-cache');

const cacheTTL = parseInt(process.env.CACHE_TTL) || 3600; // 1 hour default

const cache = new NodeCache({
    stdTTL: cacheTTL,
    checkperiod: 120,
    useClones: false
});

module.exports = cache;
