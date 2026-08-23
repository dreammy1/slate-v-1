from pathlib import Path
import re

root = Path(__file__).resolve().parents[1]
source = (root / 'admin/visual_editor.php').read_text()
css_match = re.search(r'<style>(.*?)</style>', source, re.S)
js_match = re.search(r'<script>\n(.*?)\n\s*</script>', source, re.S)
if not css_match or not js_match:
    print('Static editor assets already exist; extraction skipped.')
    raise SystemExit(0)
css = css_match.group(1).strip() + '\n'
js = js_match.group(1)
# Replace PHP-only values with a runtime configuration object supplied by PHP.
js = js.replace("var pageId=<?= (int)$pageData['id'] ?>,csrf=<?= json_encode(csrf_token()) ?>,serverDoc=<?= json_encode($pageData['document'], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>,key='slate-visual-editor-mvp-v1',seoDefaults=<?= json_encode($seoSettings, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>,doc,selected='title',history=[],future=[];try{doc=serverDoc||JSON.parse(localStorage.getItem(key)||'null')}catch(e){}if(!doc)doc=seed;", "var cfg=window.SLATE_VISUAL_EDITOR_CONFIG||{},pageId=cfg.pageId||0,csrf=cfg.csrf||'',serverDoc=cfg.serverDoc||null,key='slate-visual-editor-mvp-v1',seoDefaults=cfg.seoDefaults||{},doc,selected='title',history=[],future=[];try{doc=serverDoc||JSON.parse(localStorage.getItem(key)||'null')}catch(e){}if(!doc)doc=seed;")
(root / 'assets/css/visual-editor.v1.css').write_text(css)
(root / 'assets/js/visual-editor.v1.js').write_text(js + '\n')
print(len(css), len(js))
