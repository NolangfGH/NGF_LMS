(() => {
    'use strict';
    for (const root of document.querySelectorAll('[data-learning-notifications]')) {
        if (root.dataset.learningInitialized) continue;
        root.dataset.learningInitialized='1';
        const find=name=>root.querySelector(`[data-learning-${name}]`);
        const filterButtons=[...root.querySelectorAll('[data-learning-filter]')];
        const labels=Object.fromEntries(filterButtons.map(button=>[button.dataset.learningFilter,button.childNodes[0].textContent.trim()]));
        let category='', before=null, through=0, loading=false, stopped=false, olderLoaded=false, fingerprint='',pendingReset=false;
        let rendered=[];
        function safeUrl(value) {
            if (!value) return null;
            try { const url=new URL(value,window.location.href); return ['http:','https:'].includes(url.protocol) && url.origin===window.location.origin ? url.href : null; }
            catch (_) { return null; }
        }
        async function mark(data,keepalive=false) {
            const response=await fetch(root.dataset.learningRead,{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':root.dataset.learningCsrf},body:JSON.stringify(data),keepalive});
            if(!response.ok) throw new Error('Could not mark notifications as read. Please retry.');
        }
        function render(items) {
            const rows=items.map(item=>{
                const row=document.createElement('article'); row.setAttribute('role','listitem');
                row.className='rounded-xl border p-4 '+(item.unread ? 'border-green-300 bg-green-50' : 'border-green-100 bg-white');
                const heading=document.createElement('div'); heading.className='flex items-start justify-between gap-3';
                const title=document.createElement('h3'); title.className='text-sm font-bold text-green-950';title.textContent=(item.unread ? '● ' : '')+item.title;
                const tag=document.createElement('span');tag.className='shrink-0 rounded-full bg-white px-2 py-1 text-[10px] font-bold text-green-800';tag.textContent=labels[item.category] || 'Classroom';
                heading.append(title,tag);
                const message=document.createElement('p');message.className='mt-2 break-words text-xs leading-6 text-secondary';message.textContent=item.message;
                const time=document.createElement('time');time.className='mt-2 block text-[10px] text-secondary';time.dateTime=item.time;
                try { time.textContent=new Intl.DateTimeFormat(undefined,{timeZone:root.dataset.learningTimezone,dateStyle:'medium',timeStyle:'short'}).format(new Date(item.time)); }
                catch (_) { time.textContent='Saved notification'; }
                const actions=document.createElement('div');actions.className='mt-3 flex flex-wrap items-center justify-between gap-3';
                const url=safeUrl(item.url);
                if(url) {
                    const link=document.createElement('a');link.href=url;link.className='text-xs font-bold text-green-900 underline';link.textContent='Open update →';
                    link.addEventListener('click',()=>{ if(item.unread) mark({ids:[item.id]},true).catch(()=>{}); });
                    actions.append(link);
                } else {
                    const unavailable=document.createElement('span');unavailable.className='text-[11px] text-secondary';unavailable.textContent='This activity is no longer available.';actions.append(unavailable);
                }
                if(item.unread) {
                    const button=document.createElement('button');button.type='button';button.className='rounded-lg border border-green-200 bg-white px-3 py-1.5 text-[11px] font-bold';button.textContent='Mark read';
                    button.addEventListener('click',async()=>{
                        button.disabled=true;
                        try { await mark({ids:[item.id]});item.unread=false;rendered=rendered.filter(row=>!find('unread').checked || row.unread);render(rendered);await load(); }
                        catch(error) { find('connection').textContent=error.message;button.disabled=false; }
                    });actions.append(button);
                }
                row.append(heading,message,time,actions);return row;
            });
            if(!rows.length) {const empty=document.createElement('p');empty.className='rounded-xl bg-green-50 p-5 text-xs leading-6 text-secondary lg:col-span-2';empty.textContent=find('unread').checked ? 'No unread notifications match this filter.' : 'No notifications match this filter yet.';rows.push(empty);}
            find('items').replaceChildren(...rows);
        }
        async function load({older=false,reset=false}={}) {
            if(loading) {if(reset) pendingReset=true;return;}
            if(stopped || document.hidden) return;
            loading=true;
            if(reset) {olderLoaded=false;before=null;fingerprint='';}
            try {
                const url=new URL(root.dataset.learningNotifications,window.location.href);
                if(category) url.searchParams.set('category',category);
                if(find('unread').checked) url.searchParams.set('unread_only','1');
                if(older && before) url.searchParams.set('before',String(before));
                const response=await fetch(url.href,{headers:{Accept:'application/json'},cache:'no-store',signal:AbortSignal.timeout(10000)});
                if(!response.ok) {
                    if([401,403,419].includes(response.status)) {
                        stopped=true;rendered=[];find('items').replaceChildren();find('count').textContent='Sign in required';find('mark').hidden=true;find('older').hidden=true;find('invitations').hidden=true;
                        for(const badge of root.querySelectorAll('[data-learning-category-count]')) badge.textContent='—';
                    }
                    throw new Error('Notifications unavailable. Sign in again if your session ended.');
                }
                const data=await response.json();
                through=data.through;
                find('count').textContent=`${data.unread} unread`;
                for(const badge of root.querySelectorAll('[data-learning-category-count]')) badge.textContent=badge.dataset.learningCategoryCount ? (data.counts[badge.dataset.learningCategoryCount] || 0) : data.unread;
                find('mark').hidden=!data.unread;
                find('invitations').hidden=!data.invitations;
                find('invitation-text').textContent=data.invitations ? `${data.invitations} challenge invitation(s) still need your response.` : '';
                const next=JSON.stringify(data.items);
                if(older) {
                    const known=new Set(rendered.map(item=>item.id));rendered.push(...data.items.filter(item=>!known.has(item.id)));olderLoaded=true;before=data.next_cursor;render(rendered);
                } else if(!olderLoaded && !find('items').contains(document.activeElement)) {
                    if(next!==fingerprint || reset) {rendered=data.items;render(rendered);fingerprint=next;}
                    before=data.next_cursor;
                }
                find('older').hidden=!before;
                find('connection').textContent=olderLoaded ? 'Viewing older updates. Use Refresh for the latest notifications. Unread counts update every 8 seconds.' : 'Saved in-app notifications · updates every 8 seconds. Times use your classroom timezone.';
            } catch(error) {find('connection').textContent=error.message;}
            finally {loading=false;if(pendingReset) {pendingReset=false;load({reset:true});}}
        }
        for(const button of filterButtons) button.addEventListener('click',()=>{
            if(loading) return;
            category=button.dataset.learningFilter;
            for(const option of filterButtons) {
                const active=option===button;option.setAttribute('aria-pressed',String(active));
                option.className='shrink-0 rounded-full border border-green-200 px-3 py-2 text-xs font-bold '+(active ? 'bg-green-900 text-white' : 'bg-white text-green-900');
            }
            find('panel').open=true;load({reset:true});
        });
        find('unread').addEventListener('change',()=>load({reset:true}));
        find('refresh').addEventListener('click',()=>load({reset:true}));
        find('older').addEventListener('click',()=>load({older:true}));
        find('mark').addEventListener('click',async()=>{
            if(!through) return;
            find('mark').disabled=true;
            try {await mark({through});await load({reset:true});}
            catch(error) {find('connection').textContent=error.message;}
            finally {find('mark').disabled=false;}
        });
        load();window.setInterval(()=>load(),8000);document.addEventListener('visibilitychange',()=>load());
    }
})();
