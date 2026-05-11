/* ═══════════════════════════════════════
   MYBuddy — Frontend Logic
   ═══════════════════════════════════════ */

var form=document.getElementById('buddy-form'),
    submitBtn=document.getElementById('submit-btn'),
    statusBox=document.getElementById('status'),
    resultsBox=document.getElementById('results'),
    resultsSection=document.getElementById('results-section'),
    locInput=document.getElementById('location'),
    gpsBtn=document.getElementById('use-location-btn'),
    locNote=document.getElementById('location-note'),
    slider=document.getElementById('budget'),
    budgetDisp=document.getElementById('budget-display'),
    needInput=document.getElementById('need'),
    detLat='',detLng='';

// Slider
function updSlider(){var v=+slider.value,p=(v/+slider.max)*100;budgetDisp.textContent='RM '+v;slider.style.setProperty('--slider-pct',p+'%')}
slider.addEventListener('input',updSlider);updSlider();

// Default date
(function(){var d=document.getElementById('plan-date');if(d){var n=new Date(),s=n.getFullYear()+'-'+String(n.getMonth()+1).padStart(2,'0')+'-'+String(n.getDate()).padStart(2,'0');d.value=s;d.min=s}})();

// Chips
var chips=document.querySelectorAll('.chip');
chips.forEach(function(c){c.addEventListener('click',function(){
  if(c.classList.contains('active')){c.classList.remove('active');needInput.value='';return}
  chips.forEach(function(x){x.classList.remove('active')});c.classList.add('active');
  needInput.value=currentLang==='ms'?(c.getAttribute('data-value-ms')||c.getAttribute('data-value')):c.getAttribute('data-value');
})});
needInput.addEventListener('input',function(){chips.forEach(function(x){x.classList.remove('active')})});

// GPS
if(gpsBtn){
  if(location.protocol!=='https:'&&location.hostname!=='localhost'&&location.hostname!=='127.0.0.1'){gpsBtn.disabled=true;locNote.textContent='GPS needs HTTPS. Type area.'}
  gpsBtn.addEventListener('click',function(){
    if(!navigator.geolocation||location.protocol!=='https:'&&location.hostname!=='localhost'&&location.hostname!=='127.0.0.1'){show('GPS requires HTTPS.','error');return}
    gpsBtn.disabled=true;locNote.textContent='Detecting...';
    navigator.geolocation.getCurrentPosition(async function(p){
      var la=p.coords.latitude.toFixed(6),lo=p.coords.longitude.toFixed(6);detLat=la;detLng=lo;
      try{var r=await fetch('maps-config.php'),k=await r.json();
        if(k.success){var g=await fetch('https://maps.googleapis.com/maps/api/geocode/json?latlng='+la+','+lo+'&key='+encodeURIComponent(k.apiKey)),d=await g.json();
          if(d.status==='OK'&&d.results.length){var a=areaName(d.results[0]);locInput.value=a;locNote.textContent='✓ '+a}
          else{locInput.value=la+','+lo;locNote.textContent='✓ GPS set'}}
        else{locInput.value=la+','+lo;locNote.textContent='✓ GPS set'}}
      catch(e){locInput.value=la+','+lo;locNote.textContent='✓ GPS set'}
      gpsBtn.disabled=false;
    },function(){detLat='';detLng='';show('Location denied.','error');locNote.textContent='Type area';gpsBtn.disabled=false},{enableHighAccuracy:true,timeout:10000});
  });
}
locInput.addEventListener('input',function(){detLat='';detLng='';locNote.textContent=t('location_note')});

// Submit
form.addEventListener('submit',async function(e){
  e.preventDefault();
  var need=needInput.value.trim(),loc=locInput.value.trim();
  if(!need){show(t('status_no_need'),'error');return}
  if(!loc){show(t('status_no_area'),'error');return}
  var payload={need:need,budget:+slider.value,location:loc,latitude:detLat,longitude:detLng,
    mode:(detLat&&detLng)?'nearby':'text',
    plan_date:document.getElementById('plan-date').value,
    plan_time:document.getElementById('plan-time').value,
    plan_group:document.getElementById('plan-group').value};
  submitBtn.disabled=true;submitBtn.textContent='Searching...';
  show('<span class="spinner"></span>'+t('status_searching'),'loading');
  resultsBox.innerHTML='';resultsSection.style.display='none';
  try{var r=await fetch('chat.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
    var d=await r.json();if(!d.success){show(d.error||'Error','error');return}
    show(d.message||'Done!','success');showResults(d.recommendations||[]);
  }catch(err){show('Cannot connect to server.','error')}
  finally{submitBtn.disabled=false;submitBtn.textContent=t('btn_search')}
});

// Results
var curRes=[];
function showResults(items){
  resultsBox.innerHTML='';
  if(!items||!items.length){resultsSection.style.display='block';document.getElementById('results-toolbar').style.display='none';document.getElementById('results-count').textContent='';resultsBox.innerHTML='<p style="text-align:center;color:var(--text3);padding:32px 0">'+t('results_none')+'</p>';return}
  curRes=items.map(function(x,i){return Object.assign({},x,{_i:i})});
  resultsSection.style.display='block';document.getElementById('results-toolbar').style.display='flex';
  document.getElementById('sort-select').value='recommended';document.getElementById('filter-rating').value='0';
  render();setTimeout(function(){resultsSection.scrollIntoView({behavior:'smooth',block:'start'})},120);
}
function render(){
  var s=document.getElementById('sort-select').value,mr=parseFloat(document.getElementById('filter-rating').value);
  var list=curRes.filter(function(x){if(mr>0){var r=parseFloat(x.rating);if(isNaN(r)||r<mr)return false}return true}).slice();
  if(s==='rating-high')list.sort(function(a,b){return(parseFloat(b.rating)||0)-(parseFloat(a.rating)||0)});
  else if(s==='price-low')list.sort(function(a,b){return pn(a)-pn(b)});
  else if(s==='price-high')list.sort(function(a,b){return pn(b)-pn(a)});
  else list.sort(function(a,b){return a._i-b._i});
  draw(list);
}
function pn(x){var m=(x.estimated_price||'').match(/(\d+)/);return m?+m[1]:9999}
document.getElementById('sort-select').addEventListener('change',render);
document.getElementById('filter-rating').addEventListener('change',render);
document.getElementById('reset-filters').addEventListener('click',function(){document.getElementById('sort-select').value='recommended';document.getElementById('filter-rating').value='0';render()});

function draw(items){
  resultsBox.innerHTML='';var c=document.getElementById('results-count');
  if(!items.length){c.textContent=t('results_no_match');return}
  c.textContent=t('results_showing')+' '+items.length+' '+(items.length>1?t('results_results'):t('results_result'));
  items.forEach(function(it,idx){
    var n=it.name||'',d=it.description||'',pr=it.estimated_price||'',
        reason=it.suitability_reason||'',rat=it.rating!=null?it.rating:'',
        fb=it.feedback||'',cat=it.category||'',tp=it.type||'';
    var gu='https://www.google.com/search?q='+encodeURIComponent(n+' '+d);
    var mu='https://www.google.com/maps/search/?api=1&query='+encodeURIComponent(n);
    var img='';
    if(it.photo_url)img='<img src="'+esc(it.photo_url)+'" alt="'+esc(n)+'" loading="lazy" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'"><div class="r-ph" style="display:none">'+em(tp||cat)+'</div>';
    else img='<div class="r-ph">'+em(tp||cat)+'</div>';
    var h='<a href="'+gu+'" target="_blank" rel="noopener" class="r-link"><div class="r-card">'+
      '<div class="r-img">'+img+'<div class="r-rank">'+(idx+1)+'</div></div>'+
      '<div class="r-body">'+
        '<div class="r-top"><h3>'+esc(n)+'</h3>'+(cat?'<span class="r-badge">'+esc(cat)+'</span>':'')+'</div>'+
        '<div class="r-meta">'+(tp?'<span>'+esc(tp)+'</span>':'')+(rat?'<span class="star">★ '+esc(String(rat))+'</span>':'')+(pr?'<span class="price">'+esc(pr)+'</span>':'')+'</div>'+
        (d?'<p class="r-desc">'+esc(d)+'</p>':'')+
        (reason?'<div class="r-why"><b>Why:</b> '+esc(reason)+'</div>':'')+
        (fb?'<p class="r-review">"'+esc(fb)+'"</p>':'')+
        '<div class="r-actions"><span class="r-btn det">'+t('btn_view_details')+'</span><a href="'+mu+'" target="_blank" rel="noopener" class="r-btn map" onclick="event.stopPropagation()">Maps ↗</a></div>'+
      '</div></div></a>';
    resultsBox.insertAdjacentHTML('beforeend',h);
  });
}
function em(s){s=(s||'').toLowerCase();if(s.indexOf('food')>-1||s.indexOf('restaurant')>-1)return'🍜';if(s.indexOf('cafe')>-1)return'☕';if(s.indexOf('nature')>-1||s.indexOf('park')>-1)return'🌿';if(s.indexOf('shop')>-1)return'🛍️';if(s.indexOf('activity')>-1)return'🎯';return'📍'}
function esc(s){var d=document.createElement('div');d.textContent=String(s);return d.innerHTML}
function show(h,c){statusBox.innerHTML=h;statusBox.className='status-toast '+c}
function areaName(r){var ci='',st='',co='';if(!r.address_components)return r.formatted_address||'';r.address_components.forEach(function(c){if(c.types.indexOf('locality')>-1)ci=c.long_name;if(c.types.indexOf('administrative_area_level_1')>-1)st=c.long_name;if(c.types.indexOf('country')>-1)co=c.long_name});if(ci&&co)return ci+', '+co;if(st&&co)return st+', '+co;return r.formatted_address||''}

/* ─── LANGUAGE ─── */
var currentLang='en';
var L={
en:{hero_badge:'AI-Powered Discovery Engine',hero_title:'Find the best places in Malaysia — <span class="hero-gradient">powered by AI.</span>',hero_sub:'Describe what you\'re looking for and let our AI recommend the perfect food, cafes, activities, and shopping spots near you.',
features_title:'How MYBuddy Works',features_desc:'Three simple steps to discover the best of Malaysia',
f1_title:'Tell Us What You Want',f1_desc:'Pick a category or describe what you\'re looking for — food, cafes, activities, or shopping.',
f2_title:'Set Your Location',f2_desc:'Type your area or use GPS. Set your budget and preferred time to get personalized results.',
f3_title:'Get AI Recommendations',f3_desc:'Our AI searches Google Maps, ranks results by relevance, and shows you the top picks with photos.',
search_title:'Start Your Search',search_desc:'Fill in the details below and let AI do the rest',
step1_label:'What are you looking for?',step2_label:'Where & Budget',step3_label:'Plan Your Visit',step3_optional:'optional',
placeholder_need:'Or describe what you want...',
tag_food:'🍜 Food',tag_cafe:'☕ Cafe',tag_weekend:'🌴 Weekend',tag_shopping:'🛍️ Shopping',tag_nature:'🌿 Nature',tag_budget:'💰 Budget',tag_family:'👨‍👩‍👧 Family',tag_date:'💕 Date',
placeholder_area:'e.g. Bangi, KL, Penang',location_note:'Type area or use GPS',label_budget:'Budget',
label_date:'Date',label_time:'Time',label_group:'Group',
time_anytime:'Anytime',time_morning:'Morning',time_afternoon:'Afternoon',time_evening:'Evening',time_night:'Night',
group_any:'Anyone',group_solo:'Solo',group_couple:'Couple',group_friends:'Friends',group_family:'Family',
btn_search:'Find Recommendations →',results_title:'Recommendations',results_hint:'AI-ranked from best match',
sort_recommended:'Best',sort_rating:'Rating',sort_price_low:'Price ↑',sort_price_high:'Price ↓',rating_any:'Any ★',btn_reset:'Reset',
footer:'© 2025 MYBuddy — Powered by AI & Google Maps',btn_view_details:'Details',btn_open_maps:'Maps',
status_searching:'Finding best spots...',status_no_need:'Select or type what you want.',status_no_area:'Enter a location.',
results_showing:'Showing',results_result:'result',results_results:'results',results_no_match:'No match.',results_none:'No results found. Try different input.'},
ms:{hero_badge:'Enjin Penemuan AI',hero_title:'Cari tempat terbaik di Malaysia — <span class="hero-gradient">dikuasakan AI.</span>',hero_sub:'Beritahu kami apa yang anda mahu dan AI kami cadangkan tempat makanan, kafe, aktiviti, dan membeli-belah terbaik berdekatan anda.',
features_title:'Cara MYBuddy Berfungsi',features_desc:'Tiga langkah mudah untuk terokai Malaysia',
f1_title:'Beritahu Kami',f1_desc:'Pilih kategori atau taip apa yang anda cari.',
f2_title:'Tetapkan Lokasi',f2_desc:'Taip kawasan atau guna GPS. Tetapkan bajet dan masa pilihan.',
f3_title:'Dapatkan Cadangan AI',f3_desc:'AI kami cari di Google Maps, susun mengikut kesesuaian, dan tunjukkan pilihan terbaik.',
search_title:'Mula Carian',search_desc:'Isi butiran dan biarkan AI lakukan selebihnya',
step1_label:'Apa yang anda cari?',step2_label:'Lokasi & Bajet',step3_label:'Rancang Lawatan',step3_optional:'pilihan',
placeholder_need:'Atau taip apa yang anda mahu...',
tag_food:'🍜 Makanan',tag_cafe:'☕ Kafe',tag_weekend:'🌴 Hujung Minggu',tag_shopping:'🛍️ Beli-belah',tag_nature:'🌿 Alam',tag_budget:'💰 Bajet',tag_family:'👨‍👩‍👧 Keluarga',tag_date:'💕 Dating',
placeholder_area:'cth. Bangi, KL, Penang',location_note:'Taip kawasan atau GPS',label_budget:'Bajet',
label_date:'Tarikh',label_time:'Masa',label_group:'Kumpulan',
time_anytime:'Bila-bila',time_morning:'Pagi',time_afternoon:'T.Hari',time_evening:'Petang',time_night:'Malam',
group_any:'Sesiapa',group_solo:'Solo',group_couple:'Pasangan',group_friends:'Kawan',group_family:'Keluarga',
btn_search:'Cari Cadangan →',results_title:'Cadangan',results_hint:'Disusun AI dari paling sesuai',
sort_recommended:'Terbaik',sort_rating:'Rating',sort_price_low:'Harga ↑',sort_price_high:'Harga ↓',rating_any:'Semua ★',btn_reset:'Reset',
footer:'© 2025 MYBuddy — Dikuasakan AI & Google Maps',btn_view_details:'Butiran',btn_open_maps:'Maps',
status_searching:'Mencari tempat terbaik...',status_no_need:'Pilih atau taip apa yang anda cari.',status_no_area:'Masukkan lokasi.',
results_showing:'',results_result:'hasil',results_results:'hasil',results_no_match:'Tiada padanan.',results_none:'Tiada hasil. Cuba input lain.'}
};
function t(k){return(L[currentLang]&&L[currentLang][k])||(L.en[k])||k}
function applyLang(){
  document.querySelectorAll('[data-i18n]').forEach(function(el){var k=el.getAttribute('data-i18n'),v=t(k);if(k==='hero_title')el.innerHTML=v;else el.textContent=v});
  document.querySelectorAll('[data-i18n-placeholder]').forEach(function(el){el.placeholder=t(el.getAttribute('data-i18n-placeholder'))});
  document.querySelectorAll('option[data-i18n]').forEach(function(el){el.textContent=t(el.getAttribute('data-i18n'))});
  document.querySelectorAll('.chip[data-i18n]').forEach(function(el){el.textContent=t(el.getAttribute('data-i18n'))});
  document.getElementById('lang-en').classList.toggle('active',currentLang==='en');
  document.getElementById('lang-ms').classList.toggle('active',currentLang==='ms');
  if(curRes.length)render();
}
document.getElementById('lang-toggle').addEventListener('click',function(){currentLang=currentLang==='en'?'ms':'en';applyLang();try{localStorage.setItem('ml',currentLang)}catch(e){}});
(function(){try{var s=localStorage.getItem('ml');if(s==='ms'||s==='en')currentLang=s}catch(e){} applyLang()})();
