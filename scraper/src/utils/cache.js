const fs = require('fs');
const path = require('path');
const NodeCache = require('node-cache');

const cacheTTL = parseInt(process.env.CACHE_TTL) || 3600; // 1 hour default
const CACHE_FILE = path.join(__dirname, '..', '..', '.cache', 'scraper-cache.json');

const memCache = new NodeCache({
    stdTTL: cacheTTL,
    checkperiod: 120,
    useClones: false
});

// Restore cache from disk on startup
try {
    if (fs.existsSync(CACHE_FILE)) {
        const raw = JSON.parse(fs.readFileSync(CACHE_FILE, 'utf8'));
        const now = Date.now();
        let restored = 0;
        for (const [key, entry] of Object.entries(raw)) {
            if (entry.expires > now) {
                const ttl = Math.floor((entry.expires - now) / 1000);
                memCache.set(key, entry.value, ttl);
                restored++;
            }
        }
        if (restored > 0) {
            console.log(`Cache: restored ${restored} entries from disk`);
        }
    }
} catch (e) {
    // Ignore corrupt cache file
}

// Persist cache to disk periodically (every 5 minutes)
const persistCache = () => {
    try {
        const keys = memCache.keys();
        const data = {};
        for (const key of keys) {
            const ttl = memCache.getTtl(key);
            const value = memCache.get(key);
            if (value !== undefined && ttl) {
                data[key] = { value, expires: ttl };
            }
        }
        const dir = path.dirname(CACHE_FILE);
        if (!fs.existsSync(dir)) {
            fs.mkdirSync(dir, { recursive: true });
        }
        fs.writeFileSync(CACHE_FILE, JSON.stringify(data), 'utf8');
    } catch (e) {
        // Silent fail — disk cache is best-effort
    }
};

setInterval(persistCache, 5 * 60 * 1000);

// Also persist on graceful shutdown
process.on('SIGINT', () => { persistCache(); process.exit(0); });
process.on('SIGTERM', () => { persistCache(); process.exit(0); });

module.exports = memCache;
