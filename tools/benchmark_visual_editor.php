<?php
declare(strict_types=1);
$cssText = file_get_contents(__DIR__ . '/../assets/css/visual-editor.v1.css') ?: '';
$jsText = file_get_contents(__DIR__ . '/../assets/js/visual-editor.v1.js') ?: '';
$bench = <<<'JS'
(function(){
  var t0=performance.now();
  var nodes=document.querySelectorAll('[data-node]').length;
  var layers=document.querySelectorAll('[data-layer]').length;
  var t1=performance.now();
  var result={domReadyMs:+(performance.now()-t0).toFixed(2),nodeCount:nodes,layerCount:layers,staticCssBytes:CSS_TEXT_BYTES,staticJsBytes:JS_TEXT_BYTES,documentHtmlBytes:document.documentElement.outerHTML.length};
  var samples=[];for(var i=0;i<20;i++){var s=performance.now();var el=document.querySelector('#ve-page');el.getBoundingClientRect();samples.push(performance.now()-s)}
  samples.sort(function(a,b){return a-b});result.layoutP50Ms=+samples[10].toFixed(3);result.layoutP95Ms=+samples[19].toFixed(3);
  document.body.insertAdjacentHTML('afterbegin','<pre id="benchmark-results" style="position:fixed;right:12px;bottom:12px;z-index:9999;margin:0;padding:12px 14px;background:#0f172a;color:#dbeafe;border-radius:10px;font:12px ui-monospace,monospace;box-shadow:0 12px 30px #0004">'+JSON.stringify(result,null,2)+'</pre>');
})();
JS;
$bench = str_replace(['CSS_TEXT_BYTES','JS_TEXT_BYTES'], [(string)strlen($cssText),(string)strlen($jsText)], $bench);
echo "<!doctype html><html><head><meta charset=\"utf-8\"><title>Visual Editor benchmark</title><style>body{margin:20px;font-family:system-ui;background:#f1f5f9}</style><style>{$cssText}</style></head><body><main class=\"content\"><div class=\"ve-shell\"><div class=\"ve-top\"><div class=\"ve-brand\">Slate Visual Editor benchmark</div><span class=\"ve-pill\">local fixture</span><span class=\"ve-status\">benchmarking</span><button id=\"ve-import\" hidden></button><button id=\"ve-undo\" hidden></button><button id=\"ve-redo\" hidden></button><button id=\"ve-preview\" hidden></button><button id=\"ve-publish\" hidden></button></div><div class=\"ve-body\"><aside class=\"ve-panel ve-left\"><div class=\"ve-panel-head\"><span>Layers</span><span id=\"ve-node-count\"></span></div><div class=\"ve-layers\" id=\"ve-layers\"></div></aside><section class=\"ve-canvas-wrap\"><div class=\"ve-canvas-tools\"><button data-device=\"desktop\" hidden></button><button data-device=\"tablet\" hidden></button><button data-device=\"mobile\" hidden></button></div><div class=\"ve-canvas\"><div class=\"ve-page\" id=\"ve-page\"></div></div></section><div id=\"ve-import-panel\" style=\"display:none\"><button id=\"ve-import-close\"></button><textarea id=\"ve-import-source\"></textarea><button id=\"ve-import-apply\"></button></div><aside class=\"ve-panel ve-right\"><div class=\"ve-panel-head\">Inspector</div><div class=\"ve-inspector\" id=\"ve-inspector\"></div></aside></div></div></main><script>localStorage.removeItem('slate-visual-editor-mvp-v1');window.onerror=function(message){document.body.insertAdjacentHTML('afterbegin','<pre id=\'benchmark-error\'>'+String(message)+'</pre>')};</script><script>{$jsText}</script><script>{$bench}</script></body></html>";
