from pathlib import Path
import re

root = Path(__file__).resolve().parents[1]
js_path = root / 'assets/js/visual-editor.v1.js'
js = js_path.read_text()
patch = r'''
// Performance layer: delegated events, debounced persistence, and incremental node patches.
var saveTimer=null, saveInFlight=false, saveQueued=false;
function save(){ localStorage.setItem(key,JSON.stringify(doc)); var s=document.getElementById('ve-save-state'); if(s){s.textContent='Queued';s.className='ve-status'}; clearTimeout(saveTimer); saveTimer=setTimeout(function(){if(saveInFlight){saveQueued=true;return} saveInFlight=true; if(s)s.textContent='Saving…'; api('save').then(function(r){if(s){s.textContent=r.ok?'Saved to server':'Save blocked';s.className='ve-status '+(r.ok?'saved':'')} if(r.issues&&r.issues.length) alert(r.issues.join('\n'));}).catch(function(){if(s)s.textContent='Offline draft'}).finally(function(){saveInFlight=false;if(saveQueued){saveQueued=false;save()}})},450) }
function syncSelection(){document.querySelectorAll('[data-node]').forEach(function(el){el.classList.toggle('selected',el.dataset.node===selected)});document.querySelectorAll('[data-layer]').forEach(function(el){el.classList.toggle('active',el.dataset.layer===selected)});var n=find(doc,selected),tag=document.getElementById('ve-selected-tag');if(tag)tag.textContent=n?n.tag:'—'}
function patchNode(id){var n=find(doc,id),old=document.querySelector('[data-node="'+CSS.escape(id)+'"]');if(!n||!old)return;var wrap=document.createElement('template');wrap.innerHTML=html(n).trim();var next=wrap.content.firstElementChild;if(next){old.replaceWith(next);syncSelection()}}
function layers(){var box=document.getElementById('ve-layers');if(!box)return;var out='';function row(n,depth){out+='<button draggable="true" class="ve-layer '+(n.id===selected?'active':'')+'" data-layer="'+esc(n.id)+'" style="padding-left:'+(9+depth*14)+'px"><span class="ve-chevron">'+(n.children&&n.children.length?'▾':'·')+'</span><span class="ve-tag">'+esc(n.tag)+'</span><span>'+esc(n.label)+'</span></button>';(n.children||[]).forEach(function(c){row(c,depth+1)})}row(doc,0);box.innerHTML=out}
function inspector(){var n=find(doc,selected),box=document.getElementById('ve-inspector');if(!n||!box)return;var canText=!['div','main','section','article','footer'].includes(n.tag);box.innerHTML='<div class="ve-field"><label>Element tag</label><select id="ve-tag">'+['div','section','header','footer','main','nav','article','aside','h1','h2','h3','p','a','button','img','ul','li','span'].map(function(t){return '<option '+(t===n.tag?'selected':'')+'>'+t+'</option>'}).join('')+'</select></div><div class="ve-field"><label>Label</label><input id="ve-label" value="'+esc(n.label)+'"></div>'+(canText?'<div class="ve-field"><label>Text content</label><textarea id="ve-text">'+esc(n.text)+'</textarea></div>':'')+'<div class="ve-field"><label>CSS class</label><input id="ve-class" value="'+esc((n.attrs||{}).class||'')+'" placeholder="component-class"></div><div class="ve-divider"></div><div class="ve-field"><label>Styles</label><div class="ve-style-grid"><input id="ve-color" placeholder="color" value="'+esc((n.styles||{}).color||'')+'"><input id="ve-bg" placeholder="background" value="'+esc((n.styles||{}).background||'')+'"><input id="ve-padding" placeholder="padding" value="'+esc((n.styles||{}).padding||'')+'"><input id="ve-gap" placeholder="gap" value="'+esc((n.styles||{}).gap||'')+'"></div></div><button class="ve-btn" id="ve-duplicate" type="button">Duplicate</button> <button class="ve-btn" id="ve-delete" type="button">Delete</button>'}
function render(){var page=document.getElementById('ve-page');if(!page)return;page.innerHTML='<div class="ve-node '+(selected==='root'?'selected':'')+'" draggable="true" data-node="root" data-label="Page">'+(doc.children||[]).map(html).join('')+'</div>';var c=document.getElementById('ve-node-count');if(c)c.textContent=count(doc)+' nodes';layers();inspector();syncSelection()}
function commitField(id,value){var n=find(doc,selected);if(!n)return;history.push(clone(doc));future=[];n.attrs=n.attrs||{};n.styles=n.styles||{};if(id==='ve-label')n.label=value;if(id==='ve-text')n.text=value;if(id==='ve-class')n.attrs.class=value;if(id==='ve-color')n.styles.color=value;if(id==='ve-bg')n.styles.background=value;if(id==='ve-padding')n.styles.padding=value;if(id==='ve-gap')n.styles.gap=value;patchNode(selected);layers();save()}
(function bindDelegated(){var canvas=document.getElementById('ve-page'),layerBox=document.getElementById('ve-layers'),ins=document.getElementById('ve-inspector');if(canvas)canvas.addEventListener('click',function(e){var el=e.target.closest('[data-node]');if(!el)return;selected=el.dataset.node;syncSelection();inspector()});if(layerBox)layerBox.addEventListener('click',function(e){var el=e.target.closest('[data-layer]');if(!el)return;selected=el.dataset.layer;syncSelection();inspector()});if(ins)ins.addEventListener('input',function(e){if(/^ve-/.test(e.target.id)&&e.target.id!=='ve-tag')commitField(e.target.id,e.target.value)});if(ins)ins.addEventListener('change',function(e){if(e.target.id==='ve-tag'){var n=find(doc,selected);if(n){history.push(clone(doc));n.tag=e.target.value;save();render()}}});})();
render();
'''
js = js.split('\n// Performance layer:')[0].rstrip()
if not js.endswith('})();'):
    js += '\n})();'
js = js[:-len('})();')].rstrip() + '\n' + patch + '\n})();'
js_path.write_text(js)

page = root / 'admin/visual_editor.php'
s = page.read_text()
s = re.sub(r'<style>.*?</style>\s*', '<link rel="stylesheet" href="<?= e(SLATE_URL) ?>/assets/css/visual-editor.v1.css?v=1">\n', s, count=1, flags=re.S)
s = re.sub(r'<script>\n.*?\n\s*</script>', '<script>window.SLATE_VISUAL_EDITOR_CONFIG=<?= json_encode([\'pageId\'=>(int)$pageData[\'id\'],\'csrf\'=>csrf_token(),\'serverDoc\'=>$pageData[\'document\'],\'seoDefaults\'=>$seoSettings], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;</script>\n<script src="<?= e(SLATE_URL) ?>/assets/js/visual-editor.v1.js?v=1" defer></script>', s, count=1, flags=re.S)
page.write_text(s)
print('js_bytes', len(js), 'page_bytes', len(s))
