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
