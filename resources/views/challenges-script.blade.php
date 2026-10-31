<script>
(() => {
    const cards=Array.from(document.querySelectorAll('[data-challenge-card]'));
    const controls=['challengeSearch','challengeSource','challengeDifficulty','challengeStatus'].map(id=>document.getElementById(id));
    const [search,source,difficulty,status]=controls;
    function filter(){
        let shown=0;
        for(const card of cards){
            const match=(!search.value.trim()||card.textContent.toLowerCase().includes(search.value.trim().toLowerCase())) &&
                (source.value==='all'||card.dataset.source===source.value) &&
                (difficulty.value==='all'||card.dataset.difficulty===difficulty.value) &&
                (status.value==='all'||(status.value==='closed' ? card.dataset.closed==='1' : (card.dataset.status===status.value && (status.value!=='not_started'||card.dataset.closed!=='1'))));
            card.hidden=!match;card.style.display=match?'':'none';if(match)shown++;
        }
        document.getElementById('challengeResultCount').textContent=shown+' of '+cards.length+' challenges shown';
        document.getElementById('challengeNoMatches').hidden=shown>0;
    }
    controls.forEach(control=>{control.addEventListener('input',filter);control.addEventListener('change',filter);});
    document.getElementById('clearChallengeFilters').onclick=()=>{search.value='';source.value=difficulty.value=status.value='all';if(header)header.value='';filter();};
    const header=document.querySelector('header input');if(header){header.placeholder='Search challenges…';header.addEventListener('input',()=>{search.value=header.value;filter();});}
    const serverTime={{ now()->getTimestampMs() }},started=performance.now();
    function deadlines(){
        let changed=false;
        for(const card of cards){
            if(!card.dataset.deadline||serverTime+performance.now()-started<Number(card.dataset.deadline))continue;
            const button=card.querySelector('[data-challenge-open]');if(button){button.disabled=true;button.textContent='Challenge closed';}
            if(card.dataset.closed!=='1'){
                card.dataset.closed='1';changed=true;
                const label=card.querySelector('[data-challenge-label]');if(label)label.textContent='Closed · '+label.textContent;
            }
        }
        if(changed)filter();
    }
    document.addEventListener('submit',event=>{
        if(!event.target.querySelector('[data-challenge-open]'))return;
        deadlines();if(event.target.querySelector('[data-challenge-open]').disabled)event.preventDefault();
    });
    filter();deadlines();setInterval(deadlines,1000);
    let interacting=false;
    document.addEventListener('input',()=>interacting=true);
    document.addEventListener('change',()=>interacting=true);
    document.addEventListener('toggle',event=>{if(event.target.open)interacting=true;},true);
    setInterval(()=>{
        if(!document.hidden&&!interacting&&cards.some(card=>card.dataset.status==='awaiting_review'))location.reload();
    },60000);
})();
</script>
