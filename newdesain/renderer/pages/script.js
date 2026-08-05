const fs = require('fs');
let html = fs.readFileSync('setup.html', 'utf8');

const tabLogic = 
<script>
  document.addEventListener('DOMContentLoaded', () => {
    const params = new URLSearchParams(window.location.search);
    const tab = params.get('tab') || 'sistem';
    
    // Assign IDs if not present
    const cards = document.querySelectorAll('.card');
    if(cards[0]) cards[0].id = 'card-umum';
    if(cards[1]) cards[1].id = 'card-field';
    if(cards[2]) cards[2].id = 'card-material';
    if(cards[3]) cards[3].id = 'card-fitur';
    if(cards[4]) cards[4].id = 'card-lisensi';
    if(cards[5]) cards[5].id = 'card-kas';
    if(cards[6]) cards[6].id = 'card-danger';
    if(cards[7]) cards[7].id = 'card-struk';

    // Hide all
    document.querySelectorAll('.card').forEach(c => {
       if(c.parentElement.classList.contains('col-md-6') || c.parentElement.classList.contains('col-md-12')) {
          c.parentElement.style.display = 'none';
       }
    });

    if (tab === 'profil') {
       if(document.getElementById('card-umum')) document.getElementById('card-umum').parentElement.style.display = 'block';
       if(document.getElementById('card-struk')) document.getElementById('card-struk').parentElement.style.display = 'block';
       if(document.getElementById('card-lisensi')) document.getElementById('card-lisensi').parentElement.style.display = 'block';
    } else if (tab === 'koneksi') {
       if(document.getElementById('card-umum')) document.getElementById('card-umum').parentElement.style.display = 'block';
    } else {
       if(document.getElementById('card-field')) document.getElementById('card-field').parentElement.style.display = 'block';
       if(document.getElementById('card-material')) document.getElementById('card-material').parentElement.style.display = 'block';
       if(document.getElementById('card-fitur')) document.getElementById('card-fitur').parentElement.style.display = 'block';
       if(document.getElementById('card-kas')) document.getElementById('card-kas').parentElement.style.display = 'block';
       if(document.getElementById('card-danger')) document.getElementById('card-danger').parentElement.style.display = 'block';
    }
  });
</script>
;

html = html.replace('</body>', tabLogic + '\n</body>');
fs.writeFileSync('setup.html', html);
console.log('Modified setup.html');
