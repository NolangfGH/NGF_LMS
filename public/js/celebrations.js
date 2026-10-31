(() => {
    let active=false;
    window.addEventListener('codequest:celebrate',()=>{
        if(active)return;active=true;
        const message=document.createElement('div');message.setAttribute('role','status');message.textContent='Great progress! Reflect on what worked and take the next small step.';
        message.style.cssText='position:fixed;bottom:22px;right:22px;z-index:100;max-width:340px;padding:16px 20px;background:#0b4e00;color:white;border-radius:16px;font:700 13px/1.7 sans-serif;box-shadow:0 4px 20px #0002';document.body.append(message);
        const reduced=matchMedia('(prefers-reduced-motion: reduce)').matches||document.documentElement.dataset.studentMotion==='reduce'||document.documentElement.dataset.teacherMotion==='reduce';
        const pieces=[];
        if(!reduced)for(let i=0;i<30;i++){const piece=document.createElement('span');piece.setAttribute('aria-hidden','true');piece.style.cssText=`pointer-events:none;position:fixed;z-index:99;width:8px;height:12px;left:${Math.random()*100}vw;top:-20px;background:${['#0b4e00','#a1f885','#e7ba28','#88c6e4'][i%4]};border-radius:2px`;document.body.append(piece);pieces.push(piece);piece.animate([{transform:'translateY(0) rotate(0deg)'},{transform:`translateY(105vh) rotate(${Math.random()*500}deg)`}],{duration:1700+Math.random()*500,easing:'ease-in'});}
        setTimeout(()=>{pieces.forEach(piece=>piece.remove());message.remove();active=false;},4500);
    });
})();
