(() => {
    'use strict';
    const $=id=>document.getElementById(id),csrf=document.querySelector('meta[name=csrf-token]').content;
    let state=null,busy=false,stationDirty=false;const dirtyProviders=new Set();
    const element=(tag,text,cls)=>{const e=document.createElement(tag);if(text!==undefined)e.textContent=text;if(cls)e.className=cls;return e;};
    function notice(text,error=false){const e=$('providers-notice');e.textContent=text;e.hidden=false;e.classList.toggle('error',error);}
    async function request(path,data){const r=await fetch('/admin/providers'+path,{method:data?'POST':'GET',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf},body:data?JSON.stringify(data):undefined,cache:'no-store'});const d=await r.json();if(!r.ok)throw Error(Object.values(d.errors||{}).flat().join(' ')||d.message||'Provider request failed.');return d;}
    function field(form,label,type,value){const l=element('label',label),input=element('input');input.type=type;input.setAttribute('aria-label',label);if(type==='checkbox'){l.className='check';input.checked=value;}else input.value=value??'';if(type==='checkbox')l.prepend(input);else l.append(input);form.append(l);return input;}
    async function action(fn){if(busy)return;busy=true;document.querySelectorAll('#sources button,#sources input,#sources select').forEach(b=>b.disabled=true);try{await fn();await load();}catch(e){notice(e.message,true);}finally{busy=false;document.querySelectorAll('#sources button,#sources input,#sources select').forEach(b=>b.disabled=false);}}
    const time=v=>v?new Date(v.replace(' ','T')+(v.includes('T')?'':'Z')).toLocaleString():'Never';
    function providerCard(provider){
        const card=element('article',undefined,'provider-card');card.dataset.provider=provider.id;
        card.append(element('h3',provider.name));
        const status=provider.transport;
        card.append(element('p','Connectivity: '+(status?.connectivity||'not tested')+' · Last contact: '+time(status?.last_connected_at)));
        card.append(element('p','Collection: '+(provider.collection.last_success_at?'last success '+time(provider.collection.last_success_at):'not collected yet')+' · Failures: '+provider.collection.consecutive_failures));
        card.append(element('p',provider.id==='ambient'?(provider.configured?'Ambient credentials configured ('+provider.mode+' managed). Saved keys are never displayed.':'No Ambient credentials configured. Set environment keys or choose an Admin override.'):'Keyless forecast integration · '+provider.forecast_days+' days.','muted'));
        if(provider.collection.last_error)card.append(element('p','Collection: '+provider.collection.last_error,'provider-error'));
        if(status?.last_error)card.append(element('p',status.last_error,'provider-error'));
        const form=element('form'),grid=element('div',undefined,'field-grid');form.append(grid);
        const label=element('label','Configuration source'),mode=element('select');mode.setAttribute('aria-label',provider.name+' configuration source');for(const [v,t]of [['environment','Environment managed'],['admin','Admin override']])mode.add(new Option(t,v));mode.value=provider.mode;label.append(mode);grid.append(label);
        const enabled=field(grid,provider.name+' enabled','checkbox',provider.enabled);
        const options=element('div');form.append(options);
        let api,app,days;
        if(provider.id==='ambient'){
            options.append(element('p','In your AmbientWeather.net account, create your API key and an application key. Keys stay on this server, encrypted with APP_KEY. Preserve APP_KEY and private database backups. Blank replacement fields keep existing Admin keys; switching to environment management removes the override.','muted'));
            api=field(options,'Ambient API key','password','');app=field(options,'Ambient application key','password','');for(const input of[api,app]){input.autocomplete='off';input.maxLength=2048;input.spellcheck=false;}
            options.append(element('p',provider.configured?'Credentials configured. Saved values are never displayed.':'Credentials are not configured.','muted'));
        }else{
            options.append(element('p','The existing Open-Meteo integration is keyless. Forecast location and time zone continue to follow Radar & location and the installation settings. No paid API or new forecast provider is enabled.','muted'));
            days=field(options,'Forecast days','number',provider.forecast_days);days.min=1;days.max=16;
        }
        function source(){options.hidden=mode.value==='environment';}mode.addEventListener('change',source);source();
        const controls=element('div',undefined,'provider-actions'),save=element('button','Save connection'),probe=element('button',provider.id==='ambient'?'Test & discover stations':'Test connection');probe.type='button';controls.append(save,probe);form.append(controls);card.append(form);
        form.addEventListener('input',()=>dirtyProviders.add(provider.id));
        form.addEventListener('submit',e=>{e.preventDefault();action(async()=>{await request('/'+provider.id,{revision:provider.revision,mode:mode.value,enabled:enabled.checked,...(api?{api_key:api.value,application_key:app.value}:{forecast_days:Number(days.value)})});dirtyProviders.delete(provider.id);notice('Connection settings saved. Test the connection or wait for scheduled collection.');});});
        probe.addEventListener('click',()=>action(async()=>{const d=await request('/'+provider.id+'/probe',{});notice(d.message);}));
        card.append(element('p','Connection tests and scheduled collection share a request budget and provider backoff. Save credential changes before testing. Tests do not overwrite stored forecast data.','muted'));
        return card;
    }
    function stations(){
        const root=$('provider-stations');root.replaceChildren();
        if(!state.stations.length){root.append(element('p','No stations discovered yet. Configure Ambient and choose Test & discover stations.'));$('providers-save-stations').hidden=true;return;}
        $('providers-save-stations').hidden=false;
        for(const station of state.stations){
            const card=element('article',undefined,'provider-card');card.dataset.stationId=station.id;
            card.append(element('h3',station.label));if(station.collection.last_error)card.append(element('p',station.collection.last_error,'provider-error'));
            const grid=element('div',undefined,'field-grid');card.append(grid);
            const label=field(grid,station.label+' name','text',station.label);label.maxLength=100;label.addEventListener('input',()=>{station.label=label.value;stationDirty=true;});
            const enabled=field(grid,'Collect '+station.label,'checkbox',station.enabled);enabled.addEventListener('change',()=>{station.enabled=enabled.checked;stationDirty=true;});
            const l=element('label','Primary station'),radio=element('input');radio.type='radio';radio.name='provider-primary';radio.checked=state.primary_station_id===station.id;radio.setAttribute('aria-label','Use '+station.label+' as primary');radio.addEventListener('change',()=>{state.primary_station_id=station.id;stationDirty=true;});l.className='check';l.prepend(radio);card.append(l);
            card.append(element('p',station.external_id+' · Last seen: '+time(station.last_seen_at)+' · Collected: '+time(station.last_collection_at),'muted'));
            const detail=element('details'),summary=element('summary',station.measurements.length+' discovered measurement channels');detail.append(summary);
            const list=element('div',undefined,'measurement-list');
            for(const m of station.measurements){const row=element('div',undefined,'measurement-row');row.append(element('strong',m.label),element('span',m.state, 'status-pill '+(['fresh'].includes(m.state)?'ok':'stale')),element('span',m.quantity?(!m.present&&m.last_value!==null?'Last known: ':'')+(m.last_value===null?'—':m.last_value)+' '+m.unit:'Meaning / units unverified · diagnostic only'),element('small',m.field+' · Observed: '+time(m.observed_at)));list.append(row);}
            detail.append(list);card.append(detail);root.append(card);
        }
    }
    async function load(){const next=await request('');if(stationDirty&&state){next.stations=state.stations;next.primary_station_id=state.primary_station_id;next.station_revision=state.station_revision;}state=next;const cards=state.providers.map(provider=>dirtyProviders.has(provider.id)?document.querySelector('[data-provider="'+provider.id+'"]')||providerCard(provider):providerCard(provider));$('provider-connections').replaceChildren(...cards);stations();window.dispatchEvent(new CustomEvent('weather:providers-loaded',{detail:state}));}
    $('providers-reload').addEventListener('click',()=>action(async()=>{notice('Provider status refreshed.');}));
    $('providers-save-stations').addEventListener('click',()=>action(async()=>{const d=await request('/stations',{revision:state.station_revision,primary_station_id:state.primary_station_id,stations:state.stations.map(s=>({id:s.id,label:s.label,enabled:!!s.enabled}))});stationDirty=false;notice(d.message);}));
    window.addEventListener('beforeunload',e=>{if(stationDirty||dirtyProviders.size){e.preventDefault();e.returnValue='';}});
    document.querySelector('[data-section="sources"]').addEventListener('click',()=>load().catch(e=>notice(e.message,true)));
})();
