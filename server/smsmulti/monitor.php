<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Device Monitor</title>

<style>
body{
    font-family: Arial, sans-serif;
    background:#f4f6f9;
    margin:0;
    padding:15px;
}

h2{
    text-align:center;
    margin-bottom:15px;
}

/* Filter buttons */
.filters{
    display:flex;
    justify-content:center;
    gap:10px;
    margin-bottom:15px;
    flex-wrap:wrap;
}

.filters button{
    padding:8px 16px;
    border:none;
    border-radius:20px;
    cursor:pointer;
    background:#ddd;
    font-weight:600;
}

.filters button.active{
    background:#007bff;
    color:white;
}

/* Device cards */
.device-list{
    display:grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap:15px;
}

.card{
    background:white;
    border-radius:10px;
    padding:15px;
    box-shadow:0 4px 10px rgba(0,0,0,0.08);
    transition:transform .2s;
}

.card:hover{
    transform:translateY(-3px);
}

.device-id{
    font-weight:bold;
    font-size:16px;
    margin-bottom:6px;
}

.status{
    display:inline-block;
    padding:4px 10px;
    border-radius:12px;
    font-size:12px;
    font-weight:bold;
}

.online{ background:#d4edda; color:#155724; }
.offline{ background:#f8d7da; color:#721c24; }

.lastseen{
    margin-top:8px;
    font-size:13px;
    color:#555;
}

.sending{
    margin-top:5px;
    font-size:12px;
    color:#888;
}
</style>
</head>

<body>

<h2>📡 Device Online / Offline Monitor</h2>

<div class="filters">
    <button class="active" onclick="setFilter('all')">All</button>
    <button onclick="setFilter('online')">Online</button>
    <button onclick="setFilter('offline')">Offline</button>
</div>

<div class="device-list" id="deviceList"></div>

<script>
let currentFilter = "online";
let lastHash = "";
let cache = [];

function setFilter(filter){
    currentFilter = filter;
    document.querySelectorAll(".filters button").forEach(b=>b.classList.remove("active"));
    event.target.classList.add("active");
    render(cache);
}

function formatTime(ts){
    return ts ? new Date(ts * 1000).toLocaleString() : "—";
}

// Simple hash to detect JSON change
function hashData(data){
    return JSON.stringify(data);
}

async function fetchDevices(){
    try{
        const res = await fetch("device.php", {
            cache: "no-store" // VERY IMPORTANT
        });

        const data = await res.json();
        const newHash = hashData(data);

        // If no change → do nothing (FAST)
        if(newHash === lastHash) return;

        lastHash = newHash;
        cache = data.sort((a,b)=>b.last_seen - a.last_seen);
        render(cache);

    }catch(e){
        console.error("Fetch error", e);
    }
}

function render(data){
    const list = document.getElementById("deviceList");
    list.innerHTML = "";

    data.forEach(d=>{
        if(currentFilter !== "all" && d.status !== currentFilter) return;

        const card = document.createElement("div");
        card.className = "card";

        card.innerHTML = `
            <div class="device-id">📱 ${d.device_id}</div>
            <span class="status ${d.status}">${d.status.toUpperCase()}</span>
            <div class="lastseen">Last seen: ${formatTime(d.last_seen)}</div>
            <div class="sending">Sending: ${d.sending ? "Yes" : "No"}</div>
        `;

        list.appendChild(card);
    });
}

// INITIAL LOAD
fetchDevices();

// 🔥 FAST REAL-TIME POLLING (1 second)
setInterval(fetchDevices, 1000);
</script>


</body>
</html>
