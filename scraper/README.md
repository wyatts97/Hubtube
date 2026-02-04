# HubTube Video Scraper Microservice

A Node.js microservice that scrapes video metadata from adult tube sites for embedding in HubTube.

## Supported Sites

- **XVideos** - xvideos.com
- **PornHub** - pornhub.com
- **xHamster** - xhamster.com
- **XNXX** - xnxx.com
- **RedTube** - redtube.com
- **YouPorn** - youporn.com

## Installation

```bash
cd scraper
npm install
```

## Configuration

Copy `.env.example` to `.env` and configure:

```env
PORT=3001
CACHE_TTL=3600          # Cache duration in seconds (1 hour)
RATE_LIMIT_WINDOW_MS=60000   # Rate limit window (1 minute)
RATE_LIMIT_MAX_REQUESTS=30   # Max requests per window
```

## Running

### Development
```bash
npm run dev
```

### Production
```bash
npm start
```

## API Endpoints

### Health Check
```
GET /health
```

### List Available Sites
```
GET /api/sites
```

### Search Videos
```
GET /api/search/:site?q=keyword&page=1
```

**Parameters:**
- `site` - Site identifier (xvideos, pornhub, xhamster, xnxx, redtube, youporn)
- `q` - Search query (required)
- `page` - Page number (default: 1)

**Response:**
```json
{
  "site": "xvideos",
  "query": "keyword",
  "page": 1,
  "hasNextPage": true,
  "hasPrevPage": false,
  "totalResults": 27,
  "videos": [
    {
      "sourceId": "12345678",
      "sourceSite": "xvideos",
      "title": "Video Title",
      "duration": 360,
      "durationFormatted": "6:00",
      "thumbnail": "https://...",
      "url": "https://www.xvideos.com/video12345678/...",
      "embedUrl": "https://www.xvideos.com/embedframe/12345678",
      "embedCode": "<iframe src=\"...\" ...></iframe>",
      "views": 150000,
      "rating": 95,
      "tags": [],
      "actors": []
    }
  ],
  "cached": false
}
```

### Get Video Details
```
GET /api/search/:site/video/:videoId
```

## Architecture

```
scraper/
├── src/
│   ├── index.js           # Express server entry point
│   ├── routes/
│   │   └── search.js      # Search API routes
│   ├── adapters/
│   │   ├── index.js       # Adapter registry
│   │   ├── base.js        # Base adapter class
│   │   ├── xvideos.js     # XVideos adapter
│   │   ├── pornhub.js     # PornHub adapter
│   │   ├── xhamster.js    # xHamster adapter
│   │   ├── xnxx.js        # XNXX adapter
│   │   ├── redtube.js     # RedTube adapter
│   │   └── youporn.js     # YouPorn adapter
│   └── utils/
│       └── cache.js       # In-memory caching
├── package.json
└── .env.example
```

## Adding New Sites

1. Create a new adapter file in `src/adapters/`
2. Extend the `BaseAdapter` class
3. Implement `search(query, page)` and `getVideoDetails(videoId)` methods
4. Register the adapter in `src/adapters/index.js`

Example:
```javascript
const BaseAdapter = require('./base');

class NewSiteAdapter extends BaseAdapter {
    constructor() {
        super('NewSite', 'https://www.newsite.com');
    }

    async search(query, page = 1) {
        // Implement search logic
        // Return standardized results using this.standardizeResult()
    }

    async getVideoDetails(videoId) {
        // Implement video details fetching
    }
}

module.exports = new NewSiteAdapter();
```

## Laravel Integration

Add to your `.env`:
```env
SCRAPER_URL=http://localhost:3001
```

The Laravel app communicates with this service via `App\Services\EmbedScraperService`.

## Notes

- Results are cached for 1 hour by default to reduce load on source sites
- Rate limiting is enabled to prevent abuse
- All adapters use web scraping - site structure changes may require adapter updates
- Some sites may block requests; consider using proxies for production
