const $=s=>document.querySelector(s);let M,OK,charts={};
const api=async(a,body,qs='')=>{const r=await fetch(`api.php?action=${a}${qs}`,{method:body?'POST':'GET',headers:{'Content-Type':'application/json'},body:body?JSON.stringify(body):undefined});
 const j=await r.json().catch(()=>({error:'Server did not return JSON. Check that PHP and the database are running.'}));if(!r.ok)throw new Error(j.error||'Error');return j};
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const fmt=n=>Number(n).toLocaleString()+' '+M.app.currency;
const toast=(m,bad)=>{const t=$('#toast');t.textContent=m;t.className=bad?'show bad':'show';setTimeout(()=>t.className='',2600)};
const tbl=(h,rows)=>rows.length?`<table><tr>${h.map(x=>`<th>${x}</th>`).join('')}</tr>${rows.map(r=>`<tr>${r.map(c=>`<td>${c}</td>`).join('')}</tr>`).join('')}</table>`:'<p>Nothing to show yet.</p>';
const opts=(a,v,l)=>a.map(x=>`<option value="${x[v]}">${esc(x[l])}</option>`).join('');
const COL=['#0e8f8a','#d9822b'],P=['#0e8f8a','#d9822b','#4f6bd8','#b8467a','#7aa63a','#8a6fd1','#c2382f','#3f9fd1'];
const mk=(id,cfg)=>{charts[id]?.destroy();charts[id]=new Chart($('#'+id),cfg)};
const tabs={dashboard:['Dashboard',dash],customers:['Customers',custs],pos:['Point of sale',pos],visit:['Check-in',visit]};

async function boot(){try{$('#appname').textContent=(await api('info')).name}catch{}
 try{M=await api('me');start()}catch{$('#login').classList.remove('hidden')}}
function start(){Chart.defaults.color='#667085';$('#login').classList.add('hidden');$('#app').classList.remove('hidden');
 $('#brand').textContent=M.app.name;document.title=M.app.name;$('#who').textContent=`${M.user.name} (${M.user.role})`;
 OK=Object.keys(tabs).filter(k=>k!=='dashboard'||M.user.role!=='staff');
 $('#nav').innerHTML=OK.map(k=>`<a href="#${k}">${tabs[k][0]}</a>`).join('');go()}
function go(){const k=OK.includes(location.hash.slice(1))?location.hash.slice(1):OK[0];
 document.querySelectorAll('#nav a').forEach(a=>a.classList.toggle('on',a.hash==='#'+k));
 Object.values(charts).forEach(c=>c.destroy());charts={};tabs[k][1]().catch(e=>toast(e.message,1))}
window.onhashchange=()=>M&&go();
$('#lb').onclick=async()=>{try{await api('login',{username:$('#u').value,password:$('#p').value});M=await api('me');start()}catch(e){$('#le').textContent=e.message}};
$('#p').addEventListener('keydown',e=>{if(e.key==='Enter')$('#lb').click()});$('#out').onclick=async()=>{await api('logout',{});location.reload()};
async function dash(days=7){
 const d=await api('dashboard',null,`&days=${days}`),k=d.kpi,vals=d.trend.values,avg=vals.reduce((a,b)=>a+b,0)/vals.length;
 $('#view').innerHTML=`<div class="bar"><h2>Dashboard</h2><select id="rng">${[7,30,90].map(x=>`<option value="${x}" ${x==days?'selected':''}>Last ${x} days</option>`).join('')}</select></div>
 <div class="kpis">${[['Revenue',fmt(k.revenue)],['Orders',k.orders],['Visits',k.visits],['Customers',k.customers],['Use both establishments',k.cross_customers]].map(([a,b])=>`<div class="card"><small>${a}</small><h3>${b}</h3></div>`).join('')}</div>
 <div class="grid">${[1,2,3,4,5].map(i=>`<div class="card"><canvas id="c${i}"></canvas></div>`).join('')}</div>
 <div class="grid"><div class="card"><h3>At-risk members: no visit in ${d.risk_days}+ days</h3>${tbl(['Name','Phone','Days away'],d.at_risk.map(r=>[esc(r.name),esc(r.phone),r.days]))}</div>
 <div class="card"><h3>High spenders who rarely visit</h3>${tbl(['Name','Spent','Visits'],d.whales.map(r=>[esc(r.full_name),fmt(r.spent),r.visits]))}</div></div>`;
 $('#rng').onchange=e=>dash(+e.target.value);
 mk('c1',{type:'line',data:{labels:d.trend.labels,datasets:[{label:'Revenue',data:vals,borderColor:COL[0],tension:.25,pointRadius:5,pointBackgroundColor:vals.map(v=>v<avg*.8?'#c2382f':COL[0])}]},options:{plugins:{title:{display:true,text:'Daily revenue (red = 20% below average)'},legend:{display:false}}}});
 mk('c2',{type:'doughnut',data:{labels:d.venues.map(v=>v.name),datasets:[{data:d.venues.map(v=>v.revenue),backgroundColor:COL}]},options:{plugins:{title:{display:true,text:'Revenue by establishment'}}}});
 mk('c3',{type:'bar',data:{labels:d.top.map(c=>c.full_name),datasets:[{data:d.top.map(c=>c.spent),backgroundColor:COL[1]}]},options:{indexAxis:'y',plugins:{title:{display:true,text:'Top customers by spending'},legend:{display:false}}}});
 mk('c4',{type:'pie',data:{labels:d.activity.map(a=>a.label),datasets:[{data:d.activity.map(a=>a.n),backgroundColor:P}]},options:{plugins:{title:{display:true,text:'Visits by activity'}}}});
 mk('c5',{type:'bar',data:{labels:[...Array(24).keys()].map(h=>h+'h'),datasets:[{data:d.peak,backgroundColor:COL[0]}]},options:{plugins:{title:{display:true,text:'Peak visit hours'},legend:{display:false}}}});
}
async function custs(q=''){const {customers}=await api('customers',null,`&q=${encodeURIComponent(q)}`);
 $('#view').innerHTML=`<div class="bar"><h2>Customers</h2><input id="sq" placeholder="Search name, phone or email" value="${esc(q)}"></div>
 <form id="cf" class="card row"><input name="full_name" placeholder="Full name" required><input name="email" placeholder="Email (optional)"><input name="phone" placeholder="Phone"><button>Add customer</button></form>
 <div class="card">${tbl(['Name','Email','Phone','Spent','Visits','Status'],customers.map(c=>[esc(c.full_name),esc(c.email),esc(c.phone),fmt(c.spent),c.visits,`<span class="tag ${c.status}">${c.status}</span>`]))}</div>`;
 $('#sq').onchange=e=>custs(e.target.value);
 $('#cf').onsubmit=async e=>{e.preventDefault();try{await api('customers',Object.fromEntries(new FormData(e.target)));toast('Customer added');custs()}catch(x){toast(x.message,1)}}}
async function pos(){const {customers}=await api('customers');
 $('#view').innerHTML=`<h2>Point of sale</h2><div class="card form"><select id="pc">${opts(customers,'id','full_name')}</select><select id="pv">${opts(M.venues,'id','name')}</select><div id="pi"></div>
 <label>Discount %<input id="pd" type="number" min="0" max="100" value="0"></label><select id="pm"><option>Cash</option><option>Mobile Money</option><option>Card</option></select><h3 id="pt"></h3><button id="ps">Record order</button></div>`;
 const items=()=>M.venues.find(x=>x.id==$('#pv').value).items;
 const calc=()=>{let s=0;document.querySelectorAll('#pi input').forEach(i=>s+=i.value*items().find(x=>x.id==i.dataset.id).price);$('#pt').textContent='Total: '+fmt(s*(1-$('#pd').value/100)*(1+M.app.tax_pct/100))};
 const list=()=>{$('#pi').innerHTML=items().map(i=>`<div class="row"><span>${esc(i.name)} (${fmt(i.price)})</span><input type="number" min="0" value="0" data-id="${i.id}" style="width:80px"></div>`).join('');calc()};
 $('#pv').onchange=list;$('#pi').oninput=calc;$('#pd').oninput=calc;list();
 $('#ps').onclick=async()=>{const its=[...document.querySelectorAll('#pi input')].filter(i=>+i.value>0).map(i=>({item_id:+i.dataset.id,qty:+i.value}));
  try{const r=await api('order',{customer_id:$('#pc').value,venue_id:$('#pv').value,payment_method:$('#pm').value,discount_pct:+$('#pd').value,items:its});toast('Order recorded: '+fmt(r.total));list()}catch(e){toast(e.message,1)}}}
async function visit(){const {customers}=await api('customers');
 $('#view').innerHTML=`<h2>Check-in</h2><div class="card form"><select id="vc">${opts(customers,'id','full_name')}</select><select id="vv">${opts(M.venues,'id','name')}</select><select id="va"></select>
 <label>Duration (minutes)<input id="vd" type="number" min="0" value="60" style="width:90px"></label><button id="vs">Record visit</button></div>`;
 const acts=()=>{$('#va').innerHTML=M.venues.find(x=>x.id==$('#vv').value).activities.map(a=>`<option>${esc(a)}</option>`).join('')};$('#vv').onchange=acts;acts();
 $('#vs').onclick=async()=>{try{await api('visit',{customer_id:$('#vc').value,venue_id:$('#vv').value,activity:$('#va').value,duration_min:+$('#vd').value});toast('Visit recorded')}catch(e){toast(e.message,1)}}}
boot();
