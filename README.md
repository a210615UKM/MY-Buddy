# MYBuddy — AI-Powered Malaysia Discovery Platform

A futuristic AI-powered recommendation platform that helps users discover the best food, cafes, activities, and shopping spots across Malaysia. Built with **Google Gemini AI** and **Google Places API**, featuring a premium dark SaaS-style interface.

## 🌐 Live Demo

> **Try it here:** [http://lrgs.ftsm.ukm.my/users/a210615/MY-Buddy/](http://lrgs.ftsm.ukm.my/users/a210615/MY-Buddy/)

---

## Static UI

> [https://a210615ukm.github.io/MY-Buddy/](https://a210615ukm.github.io/MY-Buddy/)

## ✨ Features

- **AI-Powered Ranking** — Gemini AI analyzes and ranks real places based on your specific need, budget, time, and group
- **Google Places Integration** — Real-time search across all of Malaysia with photo results
- **Activity Planning** — Select date, time of day, and group type for personalized recommendations
- **Smart Filters** — Sort by Best Match, Rating, or Price; filter by minimum rating
- **Quick Category Chips** — One-tap selection for Food, Cafe, Weekend, Shopping, Nature, Budget, Family, Date
- **Budget Slider** — Visual RM 0–500 range control
- **GPS Location** — Auto-detect location with reverse geocoding (HTTPS required)
- **Bilingual** — Full English and Bahasa Melayu language toggle
- **Place Photos** — Real Google Places photos displayed in result cards
- **Google Maps Links** — Open any result directly in Google Maps
- **Mobile Responsive** — Optimized for phone, tablet, and desktop
- **No sign-up required** — Works instantly in any browser

---

## 🖥️ UI Design

The interface follows a **futuristic AI SaaS landing page** style:

- Dark premium background with subtle grid pattern and gradient orbs
- Floating rounded navigation bar with glassmorphism
- Hero section with gradient text and animated badge
- Interactive dashboard mockup preview
- Feature cards with glass effect and hover animations
- Glassmorphism search panel with glowing top border
- Consistent 180×180px result card images with rank badges
- Smooth transitions and hover effects throughout

---

## 📁 Project Structure

```
MY-Buddy/
├── index.html          # Main page — hero, features, search form, results
├── style.css           # Full styling — dark theme, glassmorphism, responsive
├── script.js           # Frontend logic — form, GPS, chips, filters, language, rendering
│
├── chat.php            # Backend — Google Places search + Gemini AI ranking
├── config.php          # Loads .env variables into PHP
├── data.php            # Local fallback recommendation data
├── maps-config.php     # JSON endpoint providing Google Maps API key to frontend
├── cacert.pem          # SSL certificate bundle (for servers without updated CA)
│
├── .env                # API keys (NEVER commit)
├── env.example         # Template for .env
├── .htaccess           # Security + MIME types + cache control
├── gitignore.txt       # Git ignore rules (rename to .gitignore)
│
├── static.yml          # GitHub Pages static deployment config
├── README.md           # This file
└── test-server.php     # Server diagnostic tool (delete after testing)
```

---

## ⚙️ How It Works

```
Browser (index.html + script.js)
    │
    ├─ User selects category / types need
    ├─ Sets location, budget, date, time, group
    ├─ Clicks "Find Recommendations"
    │
    ▼ POST /chat.php (JSON)
    │
    ├─ Google Places API (Text Search with photos)
    │       │
    │       ▼ Real places with ratings, photos, addresses
    │
    ├─ Gemini AI (ranks, filters, describes)
    │       │
    │       ▼ Top 5 ranked recommendations with reasons
    │
    └─ Returns JSON → script.js renders cards with photos
```

---

## 🚀 Setup Instructions

### Prerequisites

- **PHP Server** (XAMPP, shared hosting, or university server)
- **Gemini API Key** — [Google AI Studio](https://aistudio.google.com/app/apikey)
- **Google Maps API Key** — [Google Cloud Console](https://console.cloud.google.com/apis/credentials)

### Step 1: Place project files

Upload all files to your PHP-capable server.

### Step 2: Create .env file

Copy `env.example` to `.env` and add your keys:

```
GEMINI_API_KEY=your_gemini_key_here
GOOGLE_MAPS_API_KEY=your_google_maps_key_here
```

### Step 3: Enable Google APIs

In [Google Cloud Console](https://console.cloud.google.com/apis/library), enable:

1. **Places API (New)** — for searching places
2. **Geocoding API** — for GPS → address conversion
3. **Generative Language API** — for Gemini AI

Ensure billing is active.

### Step 4: Start and open

- **Local (XAMPP):** Start Apache → open `http://localhost/MY-Buddy/`
- **Remote server:** Navigate to your server URL

---

## ⚠️ Important Note

This project requires a **PHP server** to function. GitHub Pages only serves static files and **cannot run PHP**, so the search feature will not work on GitHub Pages. Use a PHP-capable hosting environment instead.

---

## 🌐 Remote Server Deployment

If deploying to a shared hosting server (e.g. university server):

1. Upload all files including `cacert.pem`
2. The `cacert.pem` file fixes SSL certificate issues on servers without updated CA bundles
3. All PHP files are compatible with **PHP 5.5+**
4. Run `test-server.php` to verify: PHP, curl, .env, and outbound HTTPS all work
5. Delete `test-server.php` after confirming

**Note:** GPS location requires HTTPS. On HTTP servers, users must type their area manually.

---

## 🔒 Security

| File | Purpose | Safe to commit? |
|------|---------|:---:|
| `.env` | Real API keys | ❌ |
| `env.example` | Key template | ✅ |
| `.htaccess` | Blocks .env access + MIME types | ✅ |
| `config.php` | Reads .env into PHP | ✅ |
| `cacert.pem` | SSL certificates | ✅ |

---

## 🛠️ Tech Stack

| Layer | Technology |
|-------|-----------|
| Frontend | HTML5, CSS3, Vanilla JavaScript |
| Backend | PHP (5.5+ compatible, no framework) |
| AI | Google Gemini 2.5 Flash |
| Places | Google Places API (New) — Text Search + Photos |
| Maps | Google Maps URL links |
| Font | Inter (Google Fonts) |
| Design | Dark theme, glassmorphism, CSS gradients |
| Server | Any PHP hosting (XAMPP, shared hosting, university server) |

---

## 🐛 Troubleshooting

| Problem | Solution |
|---------|----------|
| "Cannot connect to server" | Make sure you're running on a PHP server, not GitHub Pages |
| Localhost looks unstyled | Hard refresh with `Ctrl+Shift+R` to clear cache |
| SSL certificate error on remote server | Make sure `cacert.pem` is uploaded |
| GPS says "permission denied" | Site must be on HTTPS; type area manually on HTTP |
| Results always the same | Enable Places API (New) with billing in Google Cloud |
| No photos in results | Ensure Places API returns `places.photos` field |
| PHP syntax error on old server | All code is PHP 5.5+ compatible |

---

## 📱 Browser Support

- Google Chrome (recommended)
- Mozilla Firefox
- Microsoft Edge
- Safari (iOS/macOS)

---

## 📄 License

Built for educational and hackathon purposes.
