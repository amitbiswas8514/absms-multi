<?php
require_once __DIR__.'/common.php';
gateway_require_admin();
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Device monitor</title>
<style>body{font:16px system-ui,sans-serif;margin:2rem auto;padding:0 1rem;max-width:64rem;color:#172033}nav{display:flex;gap:1rem}.devices{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1rem}.card{border:1px solid #ccd3de;border-radius:.7rem;padding:1rem}.status{font-weight:700}.online{color:#12633b}.offline{color:#9b1c1c}.muted{color:#586477}</style></head>
<body><nav><a href="sendsms.php">Queue SMS</a><a href="devices.php">Devices</a><form method="post" action="logout.php"><input type="hidden" name="_csrf" value="<?= gateway_escape(gateway_csrf_token()) ?>"><button type="submit">Sign out</button></form></nav>
<h1>Device monitor</h1><p class="muted">Device IDs are shortened here. Do not share or publish this page.</p><div class="devices" id="devices" aria-live="polite"></div><p id="error" role="alert" class="muted"></p>
<script>
const list=document.getElementById('devices');
const errorBox=document.getElementById('error');
async function refreshDevices(){
  try{
    const response=await fetch('device.php',{cache:'no-store',credentials:'same-origin',headers:{'Accept':'application/json'}});
    if(!response.ok) throw new Error('Device list unavailable. Sign in again if your session expired.');
    const devices=await response.json();
    list.replaceChildren();
    for(const device of devices){
      const card=document.createElement('article'); card.className='card';
      const title=document.createElement('h2'); title.textContent=device.label || 'Unnamed device';
      const id=document.createElement('p'); id.textContent=device.device_id ? 'Device ID: '+device.device_id.slice(0,8)+'…' : 'Not paired yet';
      const status=document.createElement('p'); const online=Boolean(device.active && device.last_seen && Date.now()/1000-device.last_seen<30);
      status.className='status '+(online?'online':'offline'); status.textContent=online?'Online':(device.active?'Offline':'Revoked');
      const seen=document.createElement('p'); seen.className='muted'; seen.textContent=device.last_seen?'Last seen: '+new Date(device.last_seen*1000).toLocaleString():'No heartbeat yet';
      card.append(title,id,status,seen); list.appendChild(card);
    }
    errorBox.textContent='';
  }catch(error){errorBox.textContent=error.message || 'Unable to load devices.';}
}
refreshDevices(); setInterval(refreshDevices,5000);
</script></body></html>
