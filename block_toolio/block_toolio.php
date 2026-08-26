<?php
defined('MOODLE_INTERNAL') || die();

class block_toolio extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_toolio');
    }

    public function applicable_formats() {
        return ['course-view' => true, 'mod' => true, 'site' => false, 'my' => false];
    }

    public function get_content() {
        global $COURSE, $PAGE, $DB;
        if ($this->content !== null) { return $this->content; }
        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        require_once(__DIR__ . '/state_lib.php');

        $context   = context_course::instance($COURSE->id);
        $isTeacher = has_capability('moodle/course:manageactivities', $context);
        $modinfo   = get_fast_modinfo($COURSE);
        $sesskey   = sesskey();
        $returnurl = (new moodle_url('/course/view.php', ['id' => $COURSE->id]))->out(false);

        $secrows = $DB->get_records('course_sections', ['course' => $COURSE->id], 'section ASC', 'id, section, name');
        $sectionNames = [];
        $sectiondata  = new stdClass();
        foreach ($secrows as $sec) {
            $label = trim(strip_tags($sec->name ?: ''));
            if ($label === '') { $label = get_string('section') . ' ' . $sec->section; }
            $sectionNames[$sec->section] = $label;
            if ($sec->section == 0) { continue; }
            $secobj = new stdClass();
            $secobj->name = $label;
            $secobj->cms  = [];
            if (!empty($modinfo->sections[$sec->section])) {
                foreach ($modinfo->sections[$sec->section] as $cmid) {
                    $secCm = $modinfo->get_cm($cmid);
                    if ($secCm->modname === 'toolio') { continue; }
                    $e = new stdClass(); $e->id = $cmid; $e->name = $secCm->name;
                    $secobj->cms[] = $e;
                }
            }
            $sectiondata->{$sec->section} = $secobj;
        }

        $state = block_toolio_build_state($COURSE, $context, $isTeacher);

        $html = '<div class="buw-wrap"><div id="buw-lists-' . (int)$COURSE->id . '" class="buw-lists"></div>';

        if ($isTeacher) {
            // — NEUER KOFFER + PICKER —
            $sectionopts = '';
            foreach ($secrows as $sec) {
                if ($sec->section == 0) { continue; }
                $sectionopts .= '<option value="' . (int)$sec->section . '">' . s($sectionNames[$sec->section]) . '</option>';
            }
            $cu    = (new moodle_url('/mod/toolio/create.php'))->out(false);
            $wid   = 'buw-picker-' . $COURSE->id;
            $secid = 'buw-sec-'    . $COURSE->id;
            $afid  = 'buw-after-'  . $COURSE->id;

            $html .= '<button type="button" class="btn btn-primary btn-sm w-100 buw-toggle-btn" data-target="' . $wid . '"><i class="fa fa-briefcase me-1"></i> ' . get_string('startlive', 'block_toolio') . '</button>';
            $formcid = (int)$COURSE->id;
            $html .= '<div id="' . $wid . '" class="buw-picker"><form id="buw-form-' . $formcid . '" method="GET" action="' . $cu . '" class="mt-2">';
            $html .= '<input type="hidden" name="courseid" value="' . $formcid . '"><input type="hidden" name="sesskey" value="' . s($sesskey) . '">';
            $html .= '<label class="buw-label">' . get_string('sessionname', 'block_toolio') . '</label>';
            $html .= '<input type="text" name="sessionname" class="form-control form-control-sm mb-2" style="text-align:left" placeholder="Was ist geplant\u2026" required maxlength="255" autocomplete="off">';
            $html .= '<div class="d-flex gap-1 mb-2">';
            $html .= '<button type="button" class="buw-form-toggle buw-active" data-target="buw-vis-' . $formcid . '" data-icon-on="fa-eye" data-icon-off="fa-eye-slash" title="Sichtbar / Verborgen"><i class="fa fa-eye"></i> Sichtbar</button>';
            $html .= '<button type="button" class="buw-form-toggle" data-target="buw-pin-' . $formcid . '" data-icon-on="fa-thumb-tack" data-icon-off="fa-thumb-tack" title="Anpinnen"><i class="fa fa-thumb-tack"></i> Anpinnen</button>';
            $html .= '<input type="hidden" id="buw-vis-' . $formcid . '" name="visible" value="1">';
            $html .= '<input type="hidden" id="buw-pin-' . $formcid . '" name="pinned" value="0">';
            $html .= '</div>';
            $html .= '<label class="buw-label">' . get_string('choosesection', 'block_toolio') . '</label>';
            $html .= '<select name="section" id="' . $secid . '" class="form-select form-select-sm mb-2">' . $sectionopts . '</select>';
            $html .= '<label class="buw-label">' . get_string('chooseposition', 'block_toolio') . '</label>';
            $html .= '<select name="after" id="' . $afid . '" class="form-select form-select-sm mb-3"><option value="0">' . s(get_string('positionend', 'block_toolio')) . '</option></select>';
            $html .= '<div class="d-flex gap-2"><button type="button" class="btn btn-link btn-sm flex-fill buw-cancel-btn text-muted" data-target="' . $wid . '">' . get_string('cancel', 'block_toolio') . '</button>';
            $html .= '<button type="submit" class="btn btn-primary btn-sm flex-fill"><i class="fa fa-plus me-1"></i> ' . get_string('start', 'block_toolio') . '</button></div></form></div>';

        }

        $html .= '</div>';
        $this->content->text = $html;

        $secjson     = json_encode($sectiondata);
        $statejson   = json_encode($state);
        $endlabel    = addslashes(s(get_string('positionend', 'block_toolio')));
        $cid         = (int)$COURSE->id;
        $pinurl      = (new moodle_url('/blocks/toolio/pin.php'))->out(false);
        $visurl      = (new moodle_url('/blocks/toolio/toggle_visibility.php'))->out(false);
        $renameurl   = (new moodle_url('/mod/toolio/rename.php'))->out(false);
        $deletebase  = (new moodle_url('/mod/toolio/delete.php'))->out(false);
        $viewbase    = (new moodle_url('/mod/toolio/view.php'))->out(false);
        $stateurl    = (new moodle_url('/blocks/toolio/state.php'))->out(false);
        $prefurl     = (new moodle_url('/lib/ajax/service.php', ['sesskey' => sesskey(), 'info' => 'core_user_update_user_preferences']))->out(false);
        $lblPinned   = json_encode(get_string('pinnedheader', 'block_toolio'));
        $lblSessions = json_encode(get_string('sessionrunning', 'block_toolio'));
        $lblNone     = json_encode(get_string('nosession', 'block_toolio'));

        $PAGE->requires->js_init_code("
            (function() {
                if (!document.getElementById('buw-styles')) {
                    var s = document.createElement('style'); s.id = 'buw-styles';
                    s.textContent =
                        '.buw-wrap{font-size:.875rem;text-align:left}'+
                        '.buw-section-hdr{font-size:.68rem;font-weight:700;color:#555;text-transform:uppercase;letter-spacing:.08em;padding-bottom:3px;border-bottom:1px solid #e9ecef;margin-bottom:5px}'+
                        '.buw-mt{margin-top:14px}'+
                        '.buw-session-row{display:flex;align-items:center;gap:1px;margin:0 -6px;padding:3px 6px;border-radius:6px;transition:background .12s}'+
                        '.buw-session-row:hover{background:#f3f6fa}'+
                        '.buw-session-link{flex:1;min-width:0;overflow:hidden;color:#1565c0;text-decoration:none;font-weight:500;font-size:.85rem;padding:2px 0;display:flex;flex-direction:column;justify-content:center}'+
                        '.buw-session-link:hover{text-decoration:underline;color:#0d47a1}'+
                        '.buw-session-name{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}'+
                        '.buw-group{margin-bottom:11px}'+
                        '.buw-group-label{font-size:.78rem;font-weight:600;color:#4b5563;margin:2px 0 4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:flex;align-items:center;gap:5px}'+
                        '.buw-group-items{border-left:2px solid #e6e9ef;padding-left:10px;margin-left:6px;display:flex;flex-direction:column;gap:1px}'+
                        '.buw-pinlink{font-weight:600}'+
                        '.buw-vis-btn,.buw-pin-btn,.buw-rename-btn,.buw-delete-btn{border:none;background:transparent;color:#c2c7d0;width:26px;height:24px;padding:0;cursor:pointer;line-height:1;font-size:.82rem;transition:color .15s;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;flex:none}'+
                        '.buw-vis-btn:hover{color:#1565c0}'+
                        '.buw-pin-btn{position:relative;overflow:visible}'+
                        '.buw-pin-btn:hover{color:#f59e0b}.buw-pin-btn.buw-pinned{color:#f59e0b}'+
                        '.buw-pin-btn.buw-pinned:hover{color:#c62828}'+
                        '.buw-pin-btn.buw-pinned:hover::after{content:\"\";position:absolute;left:50%;top:50%;width:1px;height:100%;background:#c62828;transform:translate(-50%,-50%) rotate(-45deg);pointer-events:none}'+
                        '.buw-rename-btn:hover{color:#555}'+
                        '.buw-delete-btn:hover{color:#c62828}'+
                        '.buw-toggle-btn{margin-top:10px}'+
                        '.buw-picker{margin-top:8px;display:none;padding:12px;background:#f8f9fa;border-radius:8px;border:1px solid #e9ecef}'+
                        '.buw-picker.open{display:block}'+
                        '.buw-label{display:block;font-size:.75rem;color:#6c757d;margin-bottom:3px;font-weight:500}'+
                        '.buw-course-pin{position:absolute;right:10px;top:50%;transform:translateY(-50%);z-index:10;border:none;background:rgba(255,255,255,.9);border-radius:4px;color:#d1d5db;cursor:pointer;font-size:.82rem;padding:2px 6px;transition:color .15s;line-height:1}'+
                        '.buw-course-pin:hover,.buw-course-pin.buw-pinned{color:#f59e0b}'+
                        'body.editing .buw-course-pin{right:40px}'+
                        '.buw-hidden-item .buw-session-link{opacity:.5;font-style:italic}'+
                        '.buw-picker form{text-align:left}.buw-picker .form-control,.buw-picker .form-select{text-align:left!important}'+
                        '.buw-form-toggle{border:1px solid #dee2e6;background:#f8f9fa;color:#adb5bd;padding:3px 7px;border-radius:4px;cursor:pointer;font-size:.75rem;line-height:1.4;transition:all .15s}'+
                        '.buw-form-toggle.buw-active{background:#fff8e1;border-color:#fcd34d;color:#f59e0b}';
                    document.head.appendChild(s);
                }
                var buwSec     = {$secjson};
                var buwState   = {$statejson};
                var buwLastSig = buwState.sig;
                var buwPinnedIds = buwState.pinnedIds || [];
                var buwCid     = {$cid};
                var buwSesskey = '{$sesskey}';
                var U = {pin:'{$pinurl}', vis:'{$visurl}', rename:'{$renameurl}', del:'{$deletebase}', view:'{$viewbase}', state:'{$stateurl}', pref:'{$prefurl}', ret:'{$returnurl}'};
                var L = {pinned:{$lblPinned}, sessions:{$lblSessions}, none:{$lblNone}};

                function buwEsc(t){var d=document.createElement('div');d.textContent=(t==null?'':String(t));return d.innerHTML;}

                function buwRow(item, kind){
                    var hidden=!item.visible;
                    var h='<div class=\"buw-session-row'+(hidden?' buw-hidden-item':'')+'\" data-cmid=\"'+item.cmid+'\">';
                    h+='<a href=\"'+buwEsc(item.url)+'\" class=\"buw-session-link'+(kind!=='session'?' buw-pinlink':'')+'\"><span class=\"buw-session-name\">'+buwEsc(item.name)+'</span></a>';
                    if(kind==='student'){ return h+'</div>'; }
                    var visAction=item.visible?'hide':'show';
                    var visIcon=item.visible?'fa-eye':'fa-eye-slash';
                    h+='<button type=\"button\" class=\"buw-vis-btn\" data-cmid=\"'+item.cmid+'\" data-action=\"'+visAction+'\" title=\"'+(item.visible?'Verbergen':'Anzeigen')+'\"><i class=\"fa '+visIcon+'\"></i></button>';
                    var pinned=(kind==='pinned')?true:!!item.pinned;
                    var inpinned=(kind==='pinned')?'1':'';
                    h+='<button type=\"button\" class=\"buw-pin-btn'+(pinned?' buw-pinned':'')+'\" data-cmid=\"'+item.cmid+'\" data-inpinned=\"'+inpinned+'\" title=\"'+(pinned?'Loslösen':'Anpinnen')+'\"><i class=\"fa fa-thumb-tack\"></i></button>';
                    if(kind==='session'){
                        h+='<button type=\"button\" class=\"buw-rename-btn\" data-cmid=\"'+item.cmid+'\" title=\"Umbenennen\"><i class=\"fa fa-pencil\"></i></button>';
                        h+='<a href=\"'+buwEsc(U.del)+'?id='+item.cmid+'&sesskey='+encodeURIComponent(buwSesskey)+'&returnurl='+encodeURIComponent(U.ret)+'\" class=\"buw-delete-btn\" title=\"Entfernen\"><i class=\"fa fa-trash\"></i></a>';
                    }
                    return h+'</div>';
                }

                function buwGroups(items, kind){
                    var h='', cur=null, open=false;
                    items.forEach(function(it){
                        if(it.sectionnum!==cur){
                            if(open) h+='</div></div>';
                            cur=it.sectionnum; open=true;
                            h+='<div class=\"buw-group\">';
                            h+='<div class=\"buw-group-label\">'+(it.sectionname?buwEsc(it.sectionname):'\u2014')+'</div>';
                            h+='<div class=\"buw-group-items\">';
                        }
                        h+=buwRow(it,kind);
                    });
                    if(open) h+='</div></div>';
                    return h;
                }

                function buwRenderLists(){
                    var c=document.getElementById('buw-lists-'+buwCid); if(!c) return;
                    var html='';
                    if(buwState.isTeacher){
                        if(buwState.pinned.length){
                            html+='<div class=\"buw-section-hdr\">'+buwEsc(L.pinned)+'</div>'+buwGroups(buwState.pinned,'pinned');
                        }
                        if(buwState.sessions.length){
                            html+='<div class=\"buw-section-hdr'+(buwState.pinned.length?' buw-mt':'')+'\">'+buwEsc(L.sessions)+'</div>'+buwGroups(buwState.sessions,'session');
                        }
                    } else {
                        if(buwState.pinned.length){ html+=buwGroups(buwState.pinned,'student'); }
                        else { html+='<p class=\"text-muted small mb-0\">'+buwEsc(L.none)+'</p>'; }
                    }
                    c.innerHTML=html;
                }

                function buwSyncCoursePins(){
                    document.querySelectorAll('.buw-course-pin').forEach(function(b){
                        var id=parseInt(b.getAttribute('data-cmid'));
                        if(buwPinnedIds.indexOf(id)!==-1){ b.classList.add('buw-pinned'); } else { b.classList.remove('buw-pinned'); }
                    });
                }

                function buwApplyState(d){
                    buwLastSig=d.sig; buwState=d; buwPinnedIds=d.pinnedIds||[];
                    buwRenderLists(); buwSyncCoursePins();
                }

                function buwRefresh(force){
                    if(document.querySelector('.buw-rename-input')) return;
                    fetch(U.state+'?courseid='+buwCid+'&sesskey='+encodeURIComponent(buwSesskey))
                        .then(function(r){return r.json();})
                        .then(function(d){ if(!d) return; if(!force && d.sig===buwLastSig) return; buwApplyState(d); })
                        .catch(function(){});
                }

                function buwStartRename(btn){
                    var row=btn.closest('.buw-session-row'); if(!row) return;
                    var link=row.querySelector('.buw-session-link'), ns=row.querySelector('.buw-session-name'), orig=ns.textContent.trim();
                    var inp=document.createElement('input'); inp.type='text'; inp.value=orig; inp.className='form-control form-control-sm buw-rename-input'; inp.style.cssText='flex:1;min-width:0;text-align:left';
                    link.style.display='none'; row.insertBefore(inp, link); inp.focus(); inp.select();
                    function save(){
                        var n=inp.value.trim()||orig;
                        fetch(U.rename+'?id='+btn.getAttribute('data-cmid')+'&name='+encodeURIComponent(n)+'&sesskey='+encodeURIComponent(buwSesskey))
                            .then(function(r){return r.json();})
                            .then(function(){ inp.remove(); link.style.display=''; buwRefresh(true); })
                            .catch(function(){ inp.remove(); link.style.display=''; });
                    }
                    inp.addEventListener('blur', save);
                    inp.addEventListener('keydown', function(e){ if(e.key==='Enter'){e.preventDefault();save();} if(e.key==='Escape'){inp.remove();link.style.display='';} });
                }

                function buwInjectPins(root){
                    if(!buwState.isTeacher) return;
                    var sel='li.activity[data-id]:not([data-buwpin]):not(.modtype_subsection):not(.modtype_label),li.activity[data-activityid]:not([data-buwpin]):not(.modtype_subsection):not(.modtype_label)';
                    (root||document).querySelectorAll(sel).forEach(function(li){
                        var cmid=parseInt(li.getAttribute('data-id')||li.getAttribute('data-activityid'));
                        if(!cmid) return;
                        if(li.offsetHeight>100) return;
                        li.setAttribute('data-buwpin','1');
                        var host=li.querySelector('.activity-item')||li;
                        var refEl=host.querySelector('.activity-actions')||host.querySelector('.activityname, .cmname, a.aalink, .instancename')||host;
                        var isPinned=buwPinnedIds.indexOf(cmid)!==-1;
                        var btn=document.createElement('button');
                        btn.type='button';
                        btn.className='buw-course-pin'+(isPinned?' buw-pinned':'');
                        btn.title='Anpinnen';
                        btn.setAttribute('data-cmid', cmid);
                        btn.innerHTML='<i class=\"fa fa-thumb-tack\"></i>';
                        btn.addEventListener('click', function(e){
                            e.preventDefault(); e.stopPropagation();
                            var action=btn.classList.contains('buw-pinned')?'unpin':'pin';
                            fetch(U.pin+'?cmid='+cmid+'&action='+action+'&sesskey='+encodeURIComponent(buwSesskey))
                                .then(function(r){return r.json();})
                                .then(function(d){ if(d.success){ btn.classList.toggle('buw-pinned'); buwRefresh(true); } });
                        });
                        if(getComputedStyle(host).position==='static'){ host.style.position='relative'; }
                        host.appendChild(btn);
                        try{ var ir=host.getBoundingClientRect(), nr=refEl.getBoundingClientRect(); btn.style.top=Math.round(nr.top-ir.top+nr.height/2)+'px'; }catch(e){}
                    });
                }

                function fillAfter(n){
                    var sel=document.getElementById('buw-after-'+buwCid); if(!sel) return;
                    sel.innerHTML='<option value=\"0\">{$endlabel}</option>';
                    var sec=buwSec[n]; if(!sec||!sec.cms) return;
                    sec.cms.forEach(function(c){ var o=document.createElement('option'); o.value=c.id; o.textContent='nach: '+c.name; sel.appendChild(o); });
                }

                function buwInit(){
                    buwRenderLists();
                    var lists=document.getElementById('buw-lists-'+buwCid);
                    if(lists){
                        lists.addEventListener('click', function(e){
                            var pinBtn=e.target.closest('.buw-pin-btn');
                            var visBtn=e.target.closest('.buw-vis-btn');
                            var renBtn=e.target.closest('.buw-rename-btn');
                            var delBtn=e.target.closest('.buw-delete-btn');
                            if(pinBtn){ e.preventDefault();
                                var action=pinBtn.classList.contains('buw-pinned')?'unpin':'pin';
                                fetch(U.pin+'?cmid='+pinBtn.getAttribute('data-cmid')+'&action='+action+'&sesskey='+encodeURIComponent(buwSesskey))
                                    .then(function(r){return r.json();}).then(function(d){ if(d.success) buwRefresh(true); });
                                return;
                            }
                            if(visBtn){ e.preventDefault();
                                fetch(U.vis+'?cmid='+visBtn.getAttribute('data-cmid')+'&action='+visBtn.getAttribute('data-action')+'&sesskey='+encodeURIComponent(buwSesskey))
                                    .then(function(r){return r.json();}).then(function(d){ if(d.success) buwRefresh(true); });
                                return;
                            }
                            if(delBtn){ e.preventDefault(); if(!confirm('Stunde entfernen?')) return;
                                fetch(delBtn.getAttribute('href')+'&format=json')
                                    .then(function(r){return r.json();}).then(function(d){ if(d.success) buwRefresh(true); });
                                return;
                            }
                            if(renBtn){ e.preventDefault(); buwStartRename(renBtn); return; }
                        });
                    }

                    var ss=document.getElementById('buw-sec-'+buwCid);
                    if(ss){ fillAfter(ss.value); ss.addEventListener('change', function(){ fillAfter(this.value); }); }
                    document.querySelectorAll('.buw-toggle-btn').forEach(function(b){ b.addEventListener('click', function(){ var p=document.getElementById(b.dataset.target); if(p) p.classList.toggle('open'); }); });
                    document.querySelectorAll('.buw-cancel-btn').forEach(function(b){ b.addEventListener('click', function(){ var p=document.getElementById(b.dataset.target); if(p) p.classList.remove('open'); }); });
                    document.querySelectorAll('.buw-form-toggle').forEach(function(tb){ tb.addEventListener('click', function(){ var act=tb.classList.toggle('buw-active'); var hh=document.getElementById(tb.dataset.target); if(hh) hh.value=act?'1':'0'; var i=tb.querySelector('i'); if(i) i.className='fa '+(act?tb.dataset.iconOn:tb.dataset.iconOff); }); });
                    var buwForm=document.getElementById('buw-form-'+buwCid);
                    if(buwForm){
                        buwForm.addEventListener('submit', function(e){
                            e.preventDefault();
                            var fd=new FormData(buwForm); fd.set('format','json');
                            var qs=Array.from(fd.entries()).map(function(p){return encodeURIComponent(p[0])+'='+encodeURIComponent(p[1]);}).join('&');
                            fetch(buwForm.action+'?'+qs).then(function(r){return r.json();}).then(function(d){ if(d.success && d.cmid){ fetch(U.pref,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify([{index:0,methodname:'core_user_update_user_preferences',args:{preferences:[{type:'drawer-open-block',value:'1'}]}}])}).catch(function(){}).then(function(){ window.location.href=U.view+'?id='+d.cmid; }); } });
                        });
                    }

                    buwInjectPins(document); buwSyncCoursePins();
                    var obs=new MutationObserver(function(muts){ muts.forEach(function(m){ m.addedNodes.forEach(function(n){ if(n.nodeType===1) buwInjectPins(n); }); }); });
                    obs.observe(document.body, {childList:true, subtree:true});

                    setInterval(function(){ if(!document.hidden) buwRefresh(false); }, 4000);
                    document.addEventListener('visibilitychange', function(){ if(!document.hidden) buwRefresh(false); });
                }

                if(document.readyState==='loading'){ document.addEventListener('DOMContentLoaded', buwInit); } else { buwInit(); }
            })();
        ");
        return $this->content;
    }
}
