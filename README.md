# MY Buddy – Malaysia AI Travel & Lifestyle Companion

An AI-powered hyperlocal recommendation website that helps users discover food, cafes, activities, shopping, and services across Malaysia. Powered by **Google Gemini AI** and **Google Places API**.

---

## Features

- **AI-Powered Recommendations** – Gemini AI ranks real places based on your specific need and budget
- **Google Maps Integration** – Text Search for any area in Malaysia + GPS nearby search
- **Smart Sorting** – Sort by Most Recommended, Highest Rating, Lowest/Highest Price
- **Min Rating Filter** – Show only places rated 3+, 4+, or 4.5+
- **Quick Category Buttons** – One-tap search for Food, Cafe, Shopping, Nature, etc.
- **Budget Slider** – Visual RM 0–500 slider for easy budget control
- **Bilingual (EN/BM)** – Full English and Bahasa Melayu language switch
- **Google Maps Directions** – "Open in Maps" button on every result
- **View Details** – Click any result to see reviews, photos, and hours on Google
- **Mobile Responsive** – Works on phone, tablet, and desktop
- **No sign-up required** – Works instantly in any browser

---

## Project Structure

```
C:\xampp\htdocs\ChatGPT\
│
├── index.html          # Main frontend page (HTML)
├── style.css           # All styling (CSS)
├── script.js           # Frontend logic, language switch, filters (JavaScript)
│
├── chat.php            # Backend API — receives search, calls Google Places + Gemini
├── config.php          # Loads .env file, exposes API keys to PHP
├── data.php            # Local fallback recommendation data (PHP array)
├── maps-config.php     # Provides Google Maps API key to frontend (JSON endpoint)
│
├── .env                # API keys (NEVER commit this file)
├── env.example         # Template for .env (safe to commit)
├── .htaccess           # Blocks direct access to .env via browser
├── gitignore.txt       # Git ignore rules (rename to .gitignore)
│
├── PRESENTATION.md     # Hackathon slide content (markdown)
└── README.md           # This file
```

---

## How It Works

```
User (browser)
    │
    ├── index.html + style.css + script.js
    │       │
    │       ▼
    │   [User fills form → clicks Search]
    │       │
    │       ▼  POST JSON
    │   chat.php
    │       │
    │       ├── Google Places API (Text Search or Nearby Search)
    │       │       │
    │       │       ▼ places found
    │       ├── Gemini AI (ranks + describes places)
    │       │       │
    │       │       ▼ ranked JSON
    │       └── Returns recommendations to frontend
    │
    └── script.js renders cards with sort/filter
```

---

## Setup Instructions

### Prerequisites

- **XAMPP** (Apache + PHP 8.x) — [Download here](https://www.apachefriends.org/)
- **Google Cloud account** with billing enabled
- **Gemini API key** — [Get from Google AI Studio](https://aistudio.google.com/app/apikey)
- **Google Maps API key** — [Get from Google Cloud Console](https://console.cloud.google.com/apis/credentials)

### Step 1: Place the project files

Copy the entire project folder to your XAMPP htdocs directory:

```
C:\xampp\htdocs\ChatGPT\
```

All files should be directly inside this folder (not in a subfolder).

### Step 2: Create the .env file

Copy `env.example` and rename it to `.env`:

```
GEMINI_API_KEY=your_real_gemini_api_key_here
GOOGLE_MAPS_API_KEY=your_real_google_maps_api_key_here
```

Replace the placeholder values with your actual API keys.

### Step 3: Enable Google APIs

In your [Google Cloud Console](https://console.cloud.google.com/apis/library), enable these APIs:

1. **Places API (New)** — for searching places
2. **Geocoding API** — for converting GPS coordinates to area names
3. **Generative Language API** — for Gemini AI (or use AI Studio key)

Make sure billing is active on your Google Cloud project.

### Step 4: Start XAMPP

1. Open XAMPP Control Panel
2. Start **Apache**
3. Open your browser and go to: **http://localhost/ChatGPT/**

---

## API Keys Security

| File | Purpose | Commit? |
|------|---------|---------|
| `.env` | Stores real API keys | ❌ NEVER |
| `env.example` | Template showing required keys | ✅ Yes |
| `.htaccess` | Blocks browser access to .env | ✅ Yes |
| `config.php` | Reads .env into PHP environment | ✅ Yes |

The `.htaccess` file contains:

```apache
<Files ".env">
    Require all denied
</Files>
```

This prevents anyone from accessing your API keys via the browser.

---

## Git Setup

Rename `gitignore.txt` to `.gitignore` before pushing to GitHub:

```bash
ren gitignore.txt .gitignore
```

This ensures `.env` and sensitive files are never committed.

---

## File Descriptions

| File | Role |
|------|------|
| `index.html` | Main page layout — navbar, hero, search form, results section |
| `style.css` | All visual styling — responsive, modern travel-platform design |
| `script.js` | Form handling, GPS location, budget slider, language switch, card rendering, sort/filter |
| `chat.php` | Backend brain — receives user input, calls Google Places Text Search, sends results to Gemini for ranking, returns JSON |
| `config.php` | Loads `.env` variables into PHP using `putenv()` and `$_ENV` |
| `data.php` | Local Malaysian recommendation data (fallback when APIs are unavailable) |
| `maps-config.php` | JSON endpoint that provides the Google Maps API key to the frontend for reverse geocoding |

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Frontend | HTML5, CSS3, Vanilla JavaScript |
| Backend | PHP 8.x (no framework) |
| AI | Google Gemini 2.5 Flash (OpenAI-compatible endpoint) |
| Places | Google Places API (New) — Text Search |
| Maps | Google Maps URLs for directions |
| Font | Inter (Google Fonts) |
| Server | XAMPP Apache (local) / any PHP hosting |

---

## Troubleshooting

| Problem | Solution |
|---------|----------|
| "Could not connect to server" | Make sure XAMPP Apache is running |
| "Invalid JSON input" | Check browser console for fetch errors |
| "Google Maps API key not configured" | Add `GOOGLE_MAPS_API_KEY` to your `.env` file |
| "Gemini API key not set" | Add `GEMINI_API_KEY` to your `.env` file |
| Results always the same | Make sure Google Places API (New) is enabled with billing |
| Budget slider not updating | Clear browser cache (Ctrl+Shift+R) |
| Language switch not working | Check browser console for JS errors |

---

## Browser Support

- Google Chrome (recommended)
- Mozilla Firefox
- Microsoft Edge
- Safari (iOS/macOS)

---

## License

This project was built for educational/hackathon purposes.

---

## Team

- [Team Member 1] — [Role]
- [Team Member 2] — [Role]
- [Team Member 3] — [Role]
- [Team Member 4] — [Role]
