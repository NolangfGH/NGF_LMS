(() => {
    function describe(state, now) {
        let phase = state.phase;
        if (['countdown','active','overtime'].includes(phase)) phase = now < state.started_at ? 'countdown' : (now >= state.deadline_at ? 'overtime' : 'active');
        const seconds = Math.max(0, Math.ceil(((phase === 'countdown' ? state.started_at : state.deadline_at) - now)/1000));
        const format = value => `${Math.floor(value/60)}:${String(value%60).padStart(2,'0')}`;
        const labels = {inviting:'Waiting for both students to accept', countdown:'Get ready — coding starts together', active:'Challenge in progress', overtime:'Time limit reached — overtime submissions allowed', awarded:'Winner finalized — coding closed', cancelled:'Challenge closed without a winner'};
        return {phase, label:labels[phase] || phase,
            writable:state.roster_valid && ['active','overtime'].includes(phase),
            time:['countdown','active'].includes(phase) ? format(seconds) : (phase === 'overtime' ? 'OVERTIME' : '—'),
            percent:phase === 'countdown' || phase === 'inviting' ? 100 : (phase === 'active' ? Math.max(0,Math.min(100,100*(state.deadline_at-now)/(state.time_limit_minutes*60000))) : 0),
        };
    }
    function render(state, now) {
        const result = describe(state,now), get = id => document.getElementById(id);
        const setText=(id,value)=>{const node=get(id);if(node.textContent!==value) node.textContent=value;};
        setText('match-phase',result.label);
        setText('match-time',result.time);
        get('match-time-fill').style.width=`${result.percent}%`;
        get('match-time-bar').setAttribute('aria-valuenow',Math.round(result.percent));
        get('match-roster-warning').hidden=state.roster_valid;
        setText('match-result',state.phase==='awarded' ? `${state.award_title}: ${state.winner} · +${state.xp_awarded} XP\n${state.award_note || ''}` : (state.phase==='cancelled' ? `Closed: ${state.cancel_reason || ''}` : ''));
        get('match-final-banner').hidden=!['awarded','cancelled'].includes(state.phase);
        setText('match-final-banner',get('match-result').textContent);
        return result;
    }
    window.CodingMatchClock={describe,render};
})();
