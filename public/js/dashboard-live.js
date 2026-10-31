document.querySelectorAll('[data-dashboard-live]').forEach(panel=>{
    let busy=false;
    setInterval(async()=>{if(busy||document.hidden||panel.contains(document.activeElement))return;busy=true;
        try{const response=await fetch(panel.dataset.dashboardLive,{headers:{Accept:'application/json'},cache:'no-store'});if(!response.ok)return;const data=await response.json();if(!panel.contains(document.activeElement))panel.innerHTML=data.html;}catch{}finally{busy=false;}
    },30000);
});
