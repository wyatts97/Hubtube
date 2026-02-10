require('dotenv').config();
const express = require('express');
const cors = require('cors');
const rateLimit = require('express-rate-limit');
const searchRoutes = require('./routes/search');

const app = express();
const PORT = process.env.PORT || 3001;

// Middleware
app.use(cors());
app.use(express.json());

// Rate limiting
const limiter = rateLimit({
    windowMs: parseInt(process.env.RATE_LIMIT_WINDOW_MS) || 60000,
    max: parseInt(process.env.RATE_LIMIT_MAX_REQUESTS) || 30,
    message: { error: 'Too many requests, please try again later.' }
});
app.use('/api/', limiter);

// Routes
app.use('/api/search', searchRoutes);

// Thumbnail proxy - serves external thumbnails through our server to avoid hotlink blocking
const ALLOWED_THUMB_DOMAINS = [
    'img-cf.xvideos-cdn.com', 'img-hw.xvideos-cdn.com', 'img-l3.xvideos-cdn.com',
    'cdn77-pic.xvideos-cdn.com', 'thumb-cdn77.xvideos-cdn.com',
    'ci.phncdn.com', 'di.phncdn.com', 'ei.phncdn.com',
    'img-egc.xnxx-cdn.com', 'img-l3.xnxx-cdn.com', 'img-hw.xnxx-cdn.com',
    'cdn77-pic.xnxx-cdn.com', 'thumb-cdn77.xnxx-cdn.com',
    'thumbs-cdn.redtube.com', 'thumb-cdn77.redtube.com',
    'fi1.ypncdn.com', 'fi2.ypncdn.com',
];
app.get('/api/thumb', async (req, res) => {
    const { url } = req.query;
    if (!url) return res.status(400).send('Missing url parameter');
    
    try {
        const parsed = new URL(url);
        // Block non-HTTPS, private IPs, and localhost
        if (parsed.protocol !== 'https:') return res.status(403).send('Only HTTPS URLs allowed');
        const host = parsed.hostname;
        if (host === 'localhost' || host === '127.0.0.1' || host.startsWith('10.') || 
            host.startsWith('192.168.') || host.startsWith('169.254.') || host === '0.0.0.0' ||
            host.startsWith('172.') || host.endsWith('.local') || host.endsWith('.internal')) {
            return res.status(403).send('Private addresses not allowed');
        }
        // Check domain allowlist
        const allowed = ALLOWED_THUMB_DOMAINS.some(d => host === d || host.endsWith('.' + d));
        if (!allowed) return res.status(403).send('Domain not allowed');

        const fetch = require('node-fetch');
        const response = await fetch(url, {
            headers: {
                'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
                'Accept': 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
                'Referer': parsed.origin + '/',
            },
            timeout: 10000,
            redirect: 'follow',
        });
        
        if (!response.ok) return res.status(response.status).send('Failed to fetch thumbnail');
        
        const contentType = response.headers.get('content-type') || 'image/jpeg';
        if (!contentType.startsWith('image/')) return res.status(403).send('Not an image');
        res.set('Content-Type', contentType);
        res.set('Cache-Control', 'public, max-age=86400');
        response.body.pipe(res);
    } catch (e) {
        res.status(500).send('Proxy error');
    }
});

// Health check
app.get('/health', (req, res) => {
    res.json({ status: 'ok', timestamp: new Date().toISOString() });
});

// Available sites endpoint
app.get('/api/sites', (req, res) => {
    const adapters = require('./adapters');
    const sites = Object.keys(adapters).map(key => ({
        id: key,
        name: adapters[key].name,
        enabled: adapters[key].enabled
    }));
    res.json({ sites });
});

app.listen(PORT, () => {
    console.log(`HubTube Scraper running on port ${PORT}`);
});
