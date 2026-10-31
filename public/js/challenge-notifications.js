(() => {
    for(const root of document.querySelectorAll('[data-challenge-notices]')) {
        if(root.dataset.initialized) continue;
        root.dataset.initialized='1';
        const find=name=>root.querySelector(`[data-notice-${name}]`);
        let polling=false,through=0,fingerprint='',stopped=false;
        async function poll() {
            if(polling || stopped || document.hidden) return;
            polling=true;
            try {
                const response=await fetch(root.dataset.challengeNotices,{headers:{Accept:'application/json'},cache:'no-store',signal:AbortSignal.timeout(10000)});
                if(!response.ok) {if([401,403,419].includes(response.status)) stopped=true;throw new Error('Notifications unavailable. Sign in again if your session ended.');}
                const data=await response.json();
                find('count').textContent=`${data.unread} unread`;
                find('invitations').hidden=!data.invitations;
                if(find('invitation-link')) find('invitation-link').hidden=!data.invitations;
                find('invitations').textContent=data.invitations ? `${data.invitations} challenge invitation(s) need your response. Open Challenges to accept or decline.` : '';
                find('mark').hidden=!data.unread;
                through=Math.max(0,...data.items.map(item=>item.id));
                const next=JSON.stringify(data.items);
                if(next!==fingerprint && !find('items').contains(document.activeElement)) {
                    fingerprint=next;
                    const rows=data.items.map(item=>{
                        const link=document.createElement('a');link.href=item.url;link.className='block rounded-xl border border-green-100 bg-paper p-3 text-sm';
                        const text=document.createElement('p');text.textContent=(item.unread ? '● ' : '')+item.message;
                        const time=document.createElement('p');time.className='mt-2 text-xs text-secondary';time.textContent=new Date(item.time).toLocaleString();link.append(text,time);return link;
                    });
                    if(!rows.length) {const empty=document.createElement('p');empty.textContent='No challenge notifications yet.';empty.className='text-xs text-secondary';rows.push(empty);}
                    find('items').replaceChildren(...rows);
                }
                find('connection').textContent='12 most recent in-app notifications · updates every 8 seconds. Open a notice to view the challenge.';
            } catch(error) {find('connection').textContent=error.message;}
            finally {polling=false;}
        }
        find('mark').addEventListener('click',async()=>{
            if(!through) return;
            find('mark').disabled=true;
            try {
                const response=await fetch(root.dataset.noticeRead,{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':root.dataset.noticeCsrf},body:JSON.stringify({through}),signal:AbortSignal.timeout(10000)});
                if(!response.ok) throw new Error('Could not mark notifications as read. Please retry.');
                await poll();
            } catch(error) {find('connection').textContent=error.message;}
            finally {find('mark').disabled=false;}
        });
        poll();setInterval(poll,8000);document.addEventListener('visibilitychange',poll);
    }
})();
