// ============================================================
// script.js — MY Buddy Frontend Logic
// ============================================================
// LOCATION LOGIC:
//   - User TYPES an area → mode = 'text' (backend does Text Search)
//   - User clicks GPS button → mode = 'nearby' (backend does Nearby Search)
// ============================================================

// ── DOM Elements ─────────────────────────────────────────────
const form           = document.getElementById('buddy-form');
const submitBtn      = document.getElementById('submit-btn');
const statusBox      = document.getElementById('status');
const resultsBox     = document.getElementById('results');
const resultsSection = document.getElementById('results-section');
const locationInput  = document.getElementById('location');
const useLocationBtn = document.getElementById('use-location-btn');
const locationNote   = document.getElementById('location-note');
const budgetSlider   = document.getElementById('budget');
const budgetDisplay  = document.getElementById('budget-display');

// GPS coordinates — only set when user clicks the location button
let detectedLatitude  = '';
let detectedLongitude = '';

// ── Budget Slider ─────────────────────────────────────────────
function updateSlider() {
  const val = parseInt(budgetSlider.value, 10);
  const max = parseInt(budgetSlider.max, 10);
  const pct = (val / max) * 100;
  budgetDisplay.textContent = 'RM ' + val;
  budgetSlider.style.setProperty('--slider-pct', pct + '%');
}

budgetSlider.addEventListener('input', updateSlider);
updateSlider();

// ── Quick Category Tags ───────────────────────────────────────
const quickTags = document.querySelectorAll('.tag-btn');
const needInput = document.getElementById('need');

quickTags.forEach(function (btn) {
  btn.addEventListener('click', function () {
    // Remove active state from all tags
    quickTags.forEach(function (b) { b.classList.remove('active'); });
    // Set this one as active
    btn.classList.add('active');
    // Fill the input with the tag value in the current language
    const value = currentLang === 'ms'
      ? (btn.getAttribute('data-value-ms') || btn.getAttribute('data-value'))
      : btn.getAttribute('data-value');
    needInput.value = value;
    // Focus the input so user can edit if they want
    needInput.focus();
  });
});

// If user types in the input, deselect any active tag
needInput.addEventListener('input', function () {
  quickTags.forEach(function (b) { b.classList.remove('active'); });
});

// ── "Use My Current Location" Button ─────────────────────────
if (useLocationBtn) {
  useLocationBtn.addEventListener('click', function () {
    if (!navigator.geolocation) {
      showStatus('Your browser does not support location access.', 'error');
      return;
    }

    useLocationBtn.disabled = true;
    useLocationBtn.innerHTML = '<span class="spinner"></span> Detecting...';
    locationNote.textContent = 'Please allow location permission in your browser.';

    navigator.geolocation.getCurrentPosition(
      async function (position) {
        const latitude  = position.coords.latitude.toFixed(6);
        const longitude = position.coords.longitude.toFixed(6);

        detectedLatitude  = latitude;
        detectedLongitude = longitude;

        locationNote.textContent = 'Converting coordinates to area name...';

        try {
          const keyResponse = await fetch('maps-config.php');
          const keyData     = await keyResponse.json();

          if (!keyData.success) {
            locationInput.value      = latitude + ', ' + longitude;
            locationNote.textContent = 'Using GPS coordinates.';
            resetLocationBtn();
            return;
          }

          const geocodeUrl =
            'https://maps.googleapis.com/maps/api/geocode/json?latlng=' +
            latitude + ',' + longitude +
            '&key=' + encodeURIComponent(keyData.apiKey);

          const geocodeResponse = await fetch(geocodeUrl);
          const geocodeData     = await geocodeResponse.json();

          if (geocodeData.status === 'OK' && geocodeData.results.length > 0) {
            const areaName = getSimpleAreaName(geocodeData.results[0]);
            locationInput.value      = areaName;
            locationNote.textContent = '✓ Detected: ' + areaName;
            showStatus('Location detected: ' + areaName, 'success');
          } else {
            locationInput.value      = latitude + ', ' + longitude;
            locationNote.textContent = 'Using GPS coordinates.';
          }

        } catch (err) {
          locationInput.value      = latitude + ', ' + longitude;
          locationNote.textContent = 'Using GPS coordinates.';
          console.error('Reverse geocoding error:', err);
        }

        resetLocationBtn();
      },

      function (error) {
        detectedLatitude  = '';
        detectedLongitude = '';

        let message = 'Unable to get your location. Please type your area.';
        if (error.code === 1) message = 'Location permission denied. Please type your area.';
        else if (error.code === 2) message = 'Location unavailable. Please type your area.';
        else if (error.code === 3) message = 'Location request timed out. Try again.';

        showStatus(message, 'error');
        locationNote.textContent = 'Type an area or use GPS';
        resetLocationBtn();
      },
      { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
    );
  });
}

function resetLocationBtn() {
  useLocationBtn.disabled  = false;
  useLocationBtn.innerHTML =
    '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/><line x1="12" y1="2" x2="12" y2="4"/><line x1="12" y1="20" x2="12" y2="22"/><line x1="2" y1="12" x2="4" y2="12"/><line x1="20" y1="12" x2="22" y2="12"/></svg> Use current location';
}

// ── Clear GPS coords when user types manually ─────────────────
locationInput.addEventListener('input', function () {
  detectedLatitude  = '';
  detectedLongitude = '';
  locationNote.textContent = 'Type an area or use GPS';
});

// ── Form Submission ───────────────────────────────────────────
form.addEventListener('submit', async function (event) {
  event.preventDefault();

  const need     = document.getElementById('need').value.trim();
  const budget   = parseInt(budgetSlider.value, 10);
  const location = locationInput.value.trim();

  if (!need) {
    showStatus(t('status_no_need'), 'error');
    return;
  }
  if (!location) {
    showStatus(t('status_no_area'), 'error');
    return;
  }

  const searchMode = (detectedLatitude && detectedLongitude) ? 'nearby' : 'text';

  const userData = {
    need:      need,
    budget:    budget,
    location:  location,
    latitude:  detectedLatitude,
    longitude: detectedLongitude,
    mode:      searchMode
  };

  // Loading state
  submitBtn.disabled    = true;
  submitBtn.innerHTML   = '<span class="spinner"></span> Searching...';
  showStatus('<span class="spinner"></span> ' + t('status_searching'), 'loading');
  resultsBox.innerHTML         = '';
  resultsSection.style.display = 'none';

  try {
    const response = await fetch('chat.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify(userData)
    });

    const data = await response.json();

    if (!data.success) {
      showStatus(data.error || 'Something went wrong. Please try again.', 'error');
      return;
    }

    showStatus(data.message || 'Recommendations ready!', 'success');
    displayRecommendations(data.recommendations || []);

  } catch (error) {
    showStatus('Could not connect to the server. Make sure XAMPP Apache is running.', 'error');
    console.error('Fetch error:', error);

  } finally {
    submitBtn.disabled  = false;
    submitBtn.innerHTML =
      '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg> Search';
  }
});

// ── Display Recommendation Cards ─────────────────────────────

// Store raw results for filtering/sorting
let currentResults = [];

function displayRecommendations(items) {
  resultsBox.innerHTML = '';

  if (!items || items.length === 0) {
    resultsSection.style.display = 'block';
    document.getElementById('results-toolbar').style.display = 'none';
    document.getElementById('results-count').textContent = '';
    resultsBox.innerHTML =
      '<p style="color:var(--text-muted); text-align:center; padding:24px 0;">' + t('results_none') + '</p>';
    return;
  }

  // Store original results and assign original rank
  currentResults = items.map(function (item, i) {
    return Object.assign({}, item, { _originalRank: i });
  });

  resultsSection.style.display = 'block';
  document.getElementById('results-toolbar').style.display = 'flex';

  // Reset filter/sort controls
  document.getElementById('sort-select').value = 'recommended';
  document.getElementById('filter-rating').value = '0';

  // Render with current filters
  applyFiltersAndRender();

  // Smooth scroll to results
  setTimeout(function () {
    resultsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }, 100);
}

// ── Apply Filters and Sort, then Render ───────────────────────
function applyFiltersAndRender() {
  const sortBy        = document.getElementById('sort-select').value;
  const filterRating  = parseFloat(document.getElementById('filter-rating').value);

  // Filter
  let filtered = currentResults.filter(function (item) {
    // Rating filter
    if (filterRating > 0) {
      const r = parseFloat(item.rating);
      if (isNaN(r) || r < filterRating) return false;
    }
    return true;
  });

  // Sort
  filtered = filtered.slice(); // copy before sorting
  switch (sortBy) {
    case 'rating-high':
      filtered.sort(function (a, b) {
        return (parseFloat(b.rating) || 0) - (parseFloat(a.rating) || 0);
      });
      break;
    case 'price-low':
      filtered.sort(function (a, b) {
        return extractMinPrice(a) - extractMinPrice(b);
      });
      break;
    case 'price-high':
      filtered.sort(function (a, b) {
        return extractMinPrice(b) - extractMinPrice(a);
      });
      break;
    case 'recommended':
    default:
      filtered.sort(function (a, b) {
        return a._originalRank - b._originalRank;
      });
      break;
  }

  // Render
  renderCards(filtered);
}

// ── Extract numeric price from estimated_price string ─────────
function extractMinPrice(item) {
  const priceStr = item.estimated_price || '';
  const match = priceStr.match(/(\d+)/);
  if (match) return parseInt(match[1], 10);
  // If no number found, put at end
  return 9999;
}

// ── Render Cards ──────────────────────────────────────────────
function renderCards(items) {
  resultsBox.innerHTML = '';

  const countEl = document.getElementById('results-count');

  if (items.length === 0) {
    countEl.textContent = t('results_no_match');
    return;
  }

  countEl.textContent = t('results_showing') + ' ' + items.length + ' ' + (items.length > 1 ? t('results_results') : t('results_result'));

  items.forEach(function (item, index) {
    const name     = item.name || 'Unnamed';
    const price    = item.estimated_price || buildPrice(item);
    const reason   = item.suitability_reason || 'Suitable based on your request.';
    const rating   = item.rating !== undefined && item.rating !== null ? item.rating : '';
    const feedback = item.feedback || '';
    const category = item.category || '';
    const type     = item.type     || '';

    // Build Google Search URL for this place
    const searchQuery = name + (item.description ? ' ' + item.description : '');
    const googleUrl = 'https://www.google.com/search?q=' + encodeURIComponent(searchQuery);

    // Build Google Maps URL for this place
    const mapsQuery = name + (item.description ? ', ' + item.description : '');
    const mapsUrl = 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(mapsQuery);

    // Create a clickable link wrapper (whole card links to Google Search)
    const link = document.createElement('a');
    link.href = googleUrl;
    link.target = '_blank';
    link.rel = 'noopener noreferrer';
    link.className = 'card-link';

    const card = document.createElement('article');
    card.className = 'card';

    // Meta line
    const metaParts = [];
    if (type)  metaParts.push(escapeHtml(type));
    if (price) metaParts.push(escapeHtml(price));
    const metaLine = metaParts.join(' &bull; ');

    // Rating
    const ratingHtml = rating
      ? '<span class="meta" style="display:inline-block; margin-right:12px;">⭐ ' + escapeHtml(String(rating)) + '</span>'
      : '';

    // Review
    const reviewHtml = feedback
      ? '<p class="review">&ldquo;' + escapeHtml(feedback) + '&rdquo;</p>'
      : '';

    // Badge
    const badgeHtml = category
      ? '<span class="badge">' + escapeHtml(category) + '</span>'
      : '';

    card.innerHTML =
      '<div class="card-top">' +
        '<div>' +
          '<h3>' + (index + 1) + '. ' + escapeHtml(name) + '</h3>' +
          (metaLine ? '<p class="meta">' + metaLine + '</p>' : '') +
        '</div>' +
        badgeHtml +
      '</div>' +
      (item.description ? '<p>' + escapeHtml(item.description) + '</p>' : '') +
      '<div class="reason"><strong>Why suitable:</strong> ' + escapeHtml(reason) + '</div>' +
      '<div style="margin-top:10px;">' + ratingHtml + '</div>' +
      reviewHtml +
      '<div class="card-actions">' +
        '<span class="card-action-item card-action-search">' +
          '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg> ' +
          t('btn_view_details') +
        '</span>' +
        '<a href="' + mapsUrl + '" target="_blank" rel="noopener noreferrer" class="card-action-item card-action-maps" onclick="event.stopPropagation();">' +
          '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg> ' +
          t('btn_open_maps') +
        '</a>' +
      '</div>';

    link.appendChild(card);
    resultsBox.appendChild(link);
  });
}

// ── Filter/Sort Event Listeners ───────────────────────────────
document.getElementById('sort-select').addEventListener('change', applyFiltersAndRender);
document.getElementById('filter-rating').addEventListener('change', applyFiltersAndRender);

document.getElementById('reset-filters').addEventListener('click', function () {
  document.getElementById('sort-select').value = 'recommended';
  document.getElementById('filter-rating').value = '0';
  applyFiltersAndRender();
});

// ── Helpers ───────────────────────────────────────────────────
function buildPrice(item) {
  if (item.price_min !== undefined && item.price_max !== undefined) {
    return 'RM' + item.price_min + ' - RM' + item.price_max;
  }
  return '';
}

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = String(text);
  return div.innerHTML;
}

function showStatus(html, type) {
  statusBox.innerHTML = html;
  statusBox.className = 'status ' + type;
}

function getSimpleAreaName(result) {
  let city = '', state = '', country = '';

  if (!result.address_components) {
    return result.formatted_address || 'Detected location';
  }

  result.address_components.forEach(function (c) {
    if (c.types.includes('locality'))                      city    = c.long_name;
    if (c.types.includes('administrative_area_level_1'))   state   = c.long_name;
    if (c.types.includes('country'))                       country = c.long_name;
  });

  if (city && country)  return city + ', ' + country;
  if (state && country) return state + ', ' + country;
  return result.formatted_address || 'Detected location';
}

// ============================================================
// LANGUAGE SWITCH — English / Bahasa Melayu
// ============================================================

let currentLang = 'en'; // default

const translations = {
  en: {
    nav_tagline: 'Your AI Travel & Lifestyle Companion',
    hero_title: 'Discover the best of Malaysia',
    hero_sub: 'Food, cafes, activities and shopping — tell us what you need and we\'ll find the perfect spot for you.',
    label_need: '🔍 What are you looking for?',
    placeholder_need: 'e.g. cheap dinner, cafe to study, weekend activity, best nasi lemak',
    tag_food: '🍜 Food Hunting',
    tag_cafe: '☕ Cafe Study',
    tag_weekend: '🌴 Weekend Activity',
    tag_shopping: '🛍️ Shopping',
    tag_nature: '🌿 Nature',
    tag_budget: '💸 Budget Friendly',
    tag_family: '👨‍👩‍👧 Family Friendly',
    tag_date: '💕 Date Spot',
    label_area: '📍 Area',
    placeholder_area: 'e.g. KL, Penang, Johor Bahru',
    btn_location: 'Use current location',
    location_note: 'Type an area or use GPS',
    label_budget: '💰 Budget:',
    btn_search: 'Search',
    results_title: 'Recommended for You',
    results_hint: 'Sorted from most suitable to least suitable',
    toolbar_sort: 'Sort by:',
    toolbar_rating: 'Min Rating:',
    sort_recommended: 'Most Recommended',
    sort_rating: 'Highest Rating',
    sort_price_low: 'Lowest Price',
    sort_price_high: 'Highest Price',
    rating_any: 'Any',
    btn_reset: 'Reset',
    footer: '© 2025 MY Buddy — Powered by AI & Google Maps',
    btn_view_details: 'View Details',
    btn_open_maps: 'Open in Maps',
    status_searching: 'Finding the best recommendations for you...',
    status_no_need: 'Please tell us what you are looking for.',
    status_no_area: 'Please enter an area or use your current location.',
    results_showing: 'Showing',
    results_result: 'result',
    results_results: 'results',
    results_no_match: 'No results match your filters. Try adjusting them.',
    results_none: 'No recommendations found. Try a different area or change your request.'
  },
  ms: {
    nav_tagline: 'Teman AI Pelancongan & Gaya Hidup Anda',
    hero_title: 'Terokai yang terbaik di Malaysia',
    hero_sub: 'Makanan, kafe, aktiviti dan membeli-belah — beritahu kami apa yang anda perlukan dan kami akan carikan tempat terbaik untuk anda.',
    label_need: '🔍 Apa yang anda cari?',
    placeholder_need: 'cth. makan malam murah, kafe untuk belajar, aktiviti hujung minggu',
    tag_food: '🍜 Cari Makanan',
    tag_cafe: '☕ Kafe Belajar',
    tag_weekend: '🌴 Aktiviti Hujung Minggu',
    tag_shopping: '🛍️ Membeli-belah',
    tag_nature: '🌿 Alam Semula Jadi',
    tag_budget: '💸 Mesra Bajet',
    tag_family: '👨‍👩‍👧 Mesra Keluarga',
    tag_date: '💕 Tempat Dating',
    label_area: '📍 Kawasan',
    placeholder_area: 'cth. KL, Pulau Pinang, Johor Bahru',
    btn_location: 'Guna lokasi semasa',
    location_note: 'Taip kawasan atau guna GPS',
    label_budget: '💰 Bajet:',
    btn_search: 'Cari',
    results_title: 'Disyorkan untuk Anda',
    results_hint: 'Disusun dari paling sesuai ke kurang sesuai',
    toolbar_sort: 'Susun:',
    toolbar_rating: 'Rating Min:',
    sort_recommended: 'Paling Disyorkan',
    sort_rating: 'Rating Tertinggi',
    sort_price_low: 'Harga Terendah',
    sort_price_high: 'Harga Tertinggi',
    rating_any: 'Semua',
    btn_reset: 'Set Semula',
    footer: '© 2025 MY Buddy — Dikuasakan oleh AI & Google Maps',
    btn_view_details: 'Lihat Butiran',
    btn_open_maps: 'Buka di Maps',
    status_searching: 'Sedang mencari cadangan terbaik untuk anda...',
    status_no_need: 'Sila beritahu kami apa yang anda cari.',
    status_no_area: 'Sila masukkan kawasan atau guna lokasi semasa anda.',
    results_showing: 'Menunjukkan',
    results_result: 'hasil',
    results_results: 'hasil',
    results_no_match: 'Tiada hasil sepadan dengan penapis anda. Cuba laraskan.',
    results_none: 'Tiada cadangan ditemui. Cuba kawasan lain atau ubah carian anda.'
  }
};

function t(key) {
  return translations[currentLang][key] || translations['en'][key] || key;
}

function applyLanguage() {
  // Update all elements with data-i18n (innerHTML)
  document.querySelectorAll('[data-i18n]').forEach(function (el) {
    const key = el.getAttribute('data-i18n');
    const text = t(key);

    // For elements that contain child elements (like labels with icons or badges)
    if (key === 'label_budget') {
      // Only update the text node before the badge, don't rebuild innerHTML
      // This preserves the live #budget-display span reference
      const badge = el.querySelector('.budget-badge');
      if (badge) {
        // Remove all child nodes except the badge
        while (el.firstChild && el.firstChild !== badge) {
          el.removeChild(el.firstChild);
        }
        // Insert new text before the badge
        const labelText = document.createTextNode(' ' + t('label_budget').replace('💰 ', '') + ' ');
        const icon = document.createElement('span');
        icon.className = 'label-icon';
        icon.textContent = '💰';
        el.insertBefore(labelText, badge);
        el.insertBefore(icon, labelText);
      }
    } else if (key === 'btn_location') {
      el.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/><line x1="12" y1="2" x2="12" y2="4"/><line x1="12" y1="20" x2="12" y2="22"/><line x1="2" y1="12" x2="4" y2="12"/><line x1="20" y1="12" x2="22" y2="12"/></svg> ' + text;
    } else if (key === 'btn_search') {
      el.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg> ' + text;
    } else if (key === 'label_need') {
      el.innerHTML = '<span class="label-icon">🔍</span> ' + text.replace('🔍 ', '');
    } else if (key === 'label_area') {
      el.innerHTML = '<span class="label-icon">📍</span> ' + text.replace('📍 ', '');
    } else {
      el.textContent = text;
    }
  });

  // Update placeholders
  document.querySelectorAll('[data-i18n-placeholder]').forEach(function (el) {
    const key = el.getAttribute('data-i18n-placeholder');
    el.placeholder = t(key);
  });

  // Update select options
  document.querySelectorAll('option[data-i18n]').forEach(function (el) {
    const key = el.getAttribute('data-i18n');
    el.textContent = t(key);
  });

  // Update quick tag button text
  document.querySelectorAll('.tag-btn[data-i18n]').forEach(function (el) {
    const key = el.getAttribute('data-i18n');
    el.textContent = t(key);
  });

  // Update the switch active state
  document.getElementById('lang-en').classList.toggle('active', currentLang === 'en');
  document.getElementById('lang-ms').classList.toggle('active', currentLang === 'ms');

  // Re-render cards if results exist (to update button text)
  if (currentResults.length > 0) {
    applyFiltersAndRender();
  }
}

// Language toggle click handler
document.getElementById('lang-toggle').addEventListener('click', function () {
  currentLang = currentLang === 'en' ? 'ms' : 'en';
  applyLanguage();
  // Save preference
  try { localStorage.setItem('mybuddy_lang', currentLang); } catch (e) {}
});

// Load saved language preference on page load
(function () {
  try {
    const saved = localStorage.getItem('mybuddy_lang');
    if (saved === 'ms' || saved === 'en') {
      currentLang = saved;
    }
  } catch (e) {}
  applyLanguage();
})();
