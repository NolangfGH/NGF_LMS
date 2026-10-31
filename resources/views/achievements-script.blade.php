<script>
(() => {
    const cards=Array.from(document.querySelectorAll('[data-achievement-card]'));
    const search=document.getElementById('achievementSearch'),status=document.getElementById('achievementStatus'),category=document.getElementById('achievementCategory');
    const headerSearch=document.querySelector('header input');
    function filter(){
        const query=search.value.trim().toLowerCase();let visible=0;
        for(const card of cards){
            const matches=(!query||card.textContent.toLowerCase().includes(query)) &&
                (status.value==='all'||(status.value==='earned'?card.dataset.earned==='1':card.dataset.earned==='0')) &&
                (category.value==='all'||card.dataset.category===category.value);
            card.hidden=!matches;card.style.display=matches?'':'none';if(matches)visible++;
        }
        document.getElementById('achievementResultCount').textContent=visible+' of '+cards.length+' milestones shown';
        document.getElementById('achievementNoMatches').hidden=visible>0;
    }
    [search,status,category].forEach(control=>control.addEventListener('input',filter));
    [status,category].forEach(control=>control.addEventListener('change',filter));
    if(headerSearch){headerSearch.placeholder='Search achievements…';headerSearch.addEventListener('input',()=>{search.value=headerSearch.value;filter();});}
    search.addEventListener('input',()=>{if(headerSearch)headerSearch.value=search.value;});
    document.getElementById('clearAchievementFilters').addEventListener('click',()=>{search.value='';status.value='all';category.value='all';if(headerSearch)headerSearch.value='';filter();});
    filter();
    // Pause refresh while a student is using filters, reading evidence, or focusing a goal.
    let interacting=false;document.addEventListener('input',()=>interacting=true);
    document.addEventListener('change',()=>interacting=true);
    document.addEventListener('toggle',event=>{if(event.target.open)interacting=true;},true);
    setInterval(()=>{if(!document.hidden&&!interacting)location.reload();},60000);
})();
</script>
