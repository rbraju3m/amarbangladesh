{{--
    Runs while the page is parsed, before the first paint: a signed-in reader sees their name and avatar
    in the header / tab bar straight away instead of "লগইন" switching to it once Alpine starts (the
    blink on every page load). Alpine then takes over with the same state. Marks: data-me="in" (shown
    when signed in, has x-cloak), "out" (hidden), "name" (text), "letter" (first letter).
--}}
<script>try{var m=JSON.parse(localStorage.getItem('bd.member'));if(m&&m.token&&m.account)document.currentScript.parentElement.querySelectorAll('[data-me]').forEach(function(e){var k=e.dataset.me;if(k==='in')e.removeAttribute('x-cloak');else if(k==='out')e.style.display='none';else if(k==='name')e.textContent=m.name||@js(__('আমি'));else if(k==='letter')e.textContent=(m.name||'?').slice(0,1)})}catch(e){}</script>
